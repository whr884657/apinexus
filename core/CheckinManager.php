<?php
/**
 * 文件：core/CheckinManager.php
 * 作用：每日签到（user.lastcheckin 存 JSON：days 最多 7 个日期，streak 为连续天数）
 *       同用户同日唯一；断签则清空 days 且 streak 回到 1。主题经 FrontendUser / PointsManager 调用。
 *       系统级外部 API 开关与用户解析见 isSystemApiEnabled / resolveUserForSystemApi
 */

class CheckinManager
{
    /** @var array<int,array> 本次请求内占位成功后的快照，供入账失败回滚 */
    private static $occupy = array();
    /**
     * user.lastcheckin 列是否可用（取代旧 checkin 表探测）
     *
     * @return bool
     */
    public static function tableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            if (class_exists('DatabaseMigrator')) {
                DatabaseMigrator::ensureLastcheckinJson48();
            }
            $ready = class_exists('DatabaseMigrator')
                ? DatabaseMigrator::tableColumnExists('user', 'lastcheckin')
                : self::probeLastcheckinColumn();
        } catch (Exception $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * @return bool
     */
    private static function probeLastcheckinColumn()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . Database::table('user') . '` LIKE ' . $pdo->quote('lastcheckin'));
            return (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 清除静态探测缓存（迁移后可选调用）
     *
     * @return void
     */
    public static function resetReadyCache()
    {
        // tableReady 用 static $ready；通过反射无法轻易清，重载进程即可。保留空方法供对称调用。
    }

    /**
     * @return string Y-m-d
     */
    public static function today()
    {
        return date('Y-m-d');
    }

    /**
     * @param int $userId
     * @return bool
     */
    public static function hasCheckedInToday($userId)
    {
        $view = self::present(self::loadRaw((int) $userId));
        return !empty($view['checked_today']);
    }

    /**
     * 把库里的 lastcheckin 原文解释成对外状态。
     * 连续未断（最后一天是今天或昨天）才返回 days 与 streak；已断则天数视为 0，日期数组为空。
     * lastcheckin 仍返回库里最后一次成功签到日。
     *
     * @param mixed $raw
     * @return array{lastcheckin:string,checked_today:bool,checkin_days:array,streak_days:int,streak_full:bool}
     */
    public static function present($raw)
    {
        $state = self::decode($raw);
        $last = $state['last'];
        $alive = self::isAlive($state);
        return array(
            'lastcheckin'   => $last,
            'checked_today' => ($last !== '' && $last === self::today()),
            'checkin_days'  => $alive ? $state['days'] : array(),
            'streak_days'   => $alive ? (int) $state['streak'] : 0,
            'streak_full'   => $alive && (int) $state['streak'] >= 7,
        );
    }

    /**
     * 连续满 7 天后的额外积分。关闭或未满 7 天为 0。
     * 百分比只乘本次随机积分，四舍五入；结果为 0 且百分比大于 0 时至少 1。
     *
     * @param float $base
     * @param int   $streak
     * @return int
     */
    public static function bonusAmount($base, $streak)
    {
        $cfg = self::bonusConfig();
        if ((int) $streak < 7 || $cfg['mode'] === 'off' || (int) $cfg['value'] < 1) {
            return 0;
        }
        if ($cfg['mode'] === 'fixed') {
            return (int) $cfg['value'];
        }
        $bonus = (int) round(((float) $base) * ((int) $cfg['value']) / 100);
        if ($bonus < 1) {
            $bonus = 1;
        }
        return $bonus;
    }

    /**
     * @return array{mode:string,value:int}
     */
    public static function bonusConfig()
    {
        $mode = strtolower(trim((string) Config::get('checkin_streak_mode', 'off')));
        if ($mode !== 'fixed' && $mode !== 'percent') {
            $mode = 'off';
        }
        $value = (int) Config::get('checkin_streak_value', '0');
        if ($value < 0) {
            $value = 0;
        }
        if ($mode === 'percent' && $value > 500) {
            $value = 500;
        }
        if ($mode === 'fixed' && $value > 1000000) {
            $value = 1000000;
        }
        return array('mode' => $mode, 'value' => $value);
    }

    /**
     * 占位：把 lastcheckin 更新为今日（仅当尚未是今日）
     *
     * @param int   $userId
     * @param float $points 保留参数兼容旧调用；积分以 orders 为准，不写入 user
     * @return true|string
     */
    public static function record($userId, $points = 0, $pdo = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '参数无效';
        }
        if (!self::tableReady()) {
            return '签到功能尚未就绪';
        }
        $ownsTransaction = false;
        try {
            $pdo = $pdo ?: Database::connect();
            $ownsTransaction = !$pdo->inTransaction();
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            $stmt = $pdo->prepare(
                'SELECT `status`, `lastcheckin` FROM `' . Database::table('user') . '`'
                . ' WHERE `id` = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(array($userId));
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || (int) $user['status'] !== 1) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return '账号不可用';
            }
            $prevRaw = isset($user['lastcheckin']) ? trim((string) $user['lastcheckin']) : null;
            if ($prevRaw === '') {
                $prevRaw = null;
            }
            $state = self::decode($prevRaw);
            $today = self::today();
            if ($state['last'] === $today) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return '今日已签到';
            }
            if ($state['last'] !== '' && $state['last'] === self::shiftDay($today, -1) && (int) $state['streak'] > 0) {
                $days = $state['days'];
                $days[] = $today;
                if (count($days) > 7) {
                    $days = array_slice($days, -7);
                }
                $streak = (int) $state['streak'] + 1;
            } else {
                $days = array($today);
                $streak = 1;
            }
            $json = self::encodeState($days, $streak);
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `lastcheckin` = ? WHERE `id` = ?'
            );
            $stmt->execute(array($json, $userId));
            self::$occupy[$userId] = array(
                'prev'        => $prevRaw,
                'json'        => $json,
                'streak'      => $streak,
                'days'        => $days,
                'lastcheckin' => $today,
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($ownsTransaction && isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return '签到记录失败';
        }
    }

    /**
     * 本次占位成功后的连续天数与日期（入账前读取）
     *
     * @param int $userId
     * @return array|null
     */
    public static function occupyMeta($userId)
    {
        $userId = (int) $userId;
        return isset(self::$occupy[$userId]) ? self::$occupy[$userId] : null;
    }

    /**
     * 积分入账失败时回滚「今日已签」占位
     *
     * @param int $userId
     * @return void
     */
    public static function deleteToday($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady()) {
            return;
        }
        $meta = self::occupyMeta($userId);
        if ($meta === null || empty($meta['json'])) {
            return;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('user') . '`
                 SET `lastcheckin` = ?
                 WHERE `id` = ? AND `lastcheckin` = ?'
            );
            $prev = array_key_exists('prev', $meta) ? $meta['prev'] : null;
            if ($prev === null || $prev === '') {
                $stmt->bindValue(1, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(1, (string) $prev, PDO::PARAM_STR);
            }
            $stmt->bindValue(2, $userId, PDO::PARAM_INT);
            $stmt->bindValue(3, (string) $meta['json'], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * 用户中心横幅状态（业务语义，不含内部键名）
     *
     * @param int $userId
     * @return array{enabled:bool,checked_today:bool,min:int,max:int,show_banner:bool}
     */
    public static function bannerState($userId)
    {
        $enabled = Config::get('checkin_enabled', '0') === '1' && self::tableReady();
        $range = self::pointsRange();
        $view = $enabled ? self::present(self::loadRaw((int) $userId)) : self::present(null);
        $checked = !empty($view['checked_today']);
        return array(
            'enabled'       => $enabled,
            'checked_today' => $checked,
            'min'           => $range['min'],
            'max'           => $range['max'],
            'show_banner'   => $enabled && !$checked,
            'streak_days'   => isset($view['streak_days']) ? (int) $view['streak_days'] : 0,
            'streak_full'   => !empty($view['streak_full']),
            'checkin_days'  => isset($view['checkin_days']) ? $view['checkin_days'] : array(),
        );
    }

    /**
     * 系统级签到 API 是否开启（与功能开关 checkin_enabled 独立）
     *
     * @return bool
     */
    public static function isSystemApiEnabled()
    {
        return class_exists('SystemApiKey') && SystemApiKey::isGateOn('checkin');
    }

    /**
     * @return array{min:int,max:int}
     */
    public static function pointsRange()
    {
        return Config::normalizeCheckinPointsRange(
            Config::get('checkin_points_min', '10'),
            Config::get('checkin_points_max', '30')
        );
    }

    /**
     * 系统 API：只读功能配置
     *
     * @return array{feature_enabled:bool,ready:bool,min:int,max:int}
     */
    public static function systemApiConfig()
    {
        $range = self::pointsRange();
        $bonus = self::bonusConfig();
        return array(
            'feature_enabled' => Config::get('checkin_enabled', '0') === '1',
            'ready'           => self::tableReady(),
            'min'             => $range['min'],
            'max'             => $range['max'],
            'bonus_mode'      => $bonus['mode'],
            'bonus_value'     => $bonus['value'],
            'bonus_rule'      => '连续签到满 7 天后，之后每个连续签到日都额外赠送；中断后从 1 重新计算',
        );
    }

    /**
     * 系统 API：按 userid / username / email 解析用户（三选一）
     *
     * @param array $input
     * @return array{ok:bool,msg?:string,userid?:int}
     */
    public static function resolveUserForSystemApi(array $input)
    {
        $userId = 0;
        if (isset($input['userid'])) {
            $userId = (int) $input['userid'];
        } elseif (isset($input['user_id'])) {
            $userId = (int) $input['user_id'];
        } elseif (isset($input['uid'])) {
            $userId = (int) $input['uid'];
        }

        $account = '';
        if (isset($input['username'])) {
            $account = trim((string) $input['username']);
        }
        if ($account === '' && isset($input['email'])) {
            $account = trim((string) $input['email']);
        }
        if ($account === '' && isset($input['account'])) {
            $account = trim((string) $input['account']);
        }

        if ($userId <= 0 && $account === '') {
            return array('ok' => false, 'msg' => '请提供 userid 或 username 或 email');
        }

        if (!class_exists('UserManager')) {
            return array('ok' => false, 'msg' => '用户模块未就绪');
        }

        $row = null;
        if ($userId > 0) {
            $row = UserManager::findById($userId);
        } else {
            if (function_exists('mb_substr')) {
                $account = mb_substr($account, 0, 128, 'UTF-8');
            } else {
                $account = substr($account, 0, 128);
            }
            $row = UserManager::findByAccount($account);
        }

        if (!is_array($row) || empty($row['id'])) {
            return array('ok' => false, 'msg' => '该用户未注册，请注册后再签到', 'reason' => 'not_registered');
        }
        if (isset($row['status']) && (int) $row['status'] !== 1) {
            return array('ok' => false, 'msg' => '账户已被封禁，不支持签到', 'reason' => 'banned');
        }

        return array('ok' => true, 'userid' => (int) $row['id']);
    }

    /**
     * 系统 API：某用户签到状态（脱敏，不含邮箱等）
     *
     * @param int $userId
     * @return array
     */
    public static function statusForSystemApi($userId)
    {
        $userId = (int) $userId;
        $banner = self::bannerState($userId);
        $view = self::present($userId > 0 ? self::loadRaw($userId) : null);
        $bonus = self::bonusConfig();
        return array(
            'userid'          => $userId,
            'feature_enabled' => !empty($banner['enabled']),
            'checked_today'   => !empty($view['checked_today']),
            'lastcheckin'     => $view['lastcheckin'],
            'checkin_days'    => $view['checkin_days'],
            'streak_days'     => $view['streak_days'],
            'streak_full'     => $view['streak_full'],
            'bonus_mode'      => $bonus['mode'],
            'bonus_value'     => $bonus['value'],
            'min'             => isset($banner['min']) ? (int) $banner['min'] : 0,
            'max'             => isset($banner['max']) ? (int) $banner['max'] : 0,
        );
    }

    /**
     * @param int $userId
     * @return string|null
     */
    private static function loadRaw($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `lastcheckin` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $d = $stmt->fetchColumn();
            if ($d === false || $d === null) {
                return null;
            }
            $s = trim((string) $d);
            return $s === '' ? null : $s;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param mixed $raw
     * @return array{days:array,streak:int,last:string}
     */
    private static function decode($raw)
    {
        $empty = array('days' => array(), 'streak' => 0, 'last' => '');
        if ($raw === null) {
            return $empty;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return $empty;
        }
        $days = array();
        $streak = 0;
        if ($s[0] === '{' || $s[0] === '[') {
            $json = json_decode($s, true);
            if (is_array($json)) {
                if ($s[0] === '[') {
                    $days = self::normalizeDays($json);
                    $streak = count($days);
                } else {
                    $days = self::normalizeDays(isset($json['days']) && is_array($json['days']) ? $json['days'] : array());
                    $streak = isset($json['streak']) ? (int) $json['streak'] : count($days);
                }
            }
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            $days = array(substr($s, 0, 10));
            $streak = 1;
        }
        if (count($days) === 0) {
            return $empty;
        }
        if ($streak < count($days)) {
            $streak = count($days);
        }
        return array(
            'days'   => $days,
            'streak' => $streak,
            'last'   => (string) $days[count($days) - 1],
        );
    }

    /**
     * @param array $days
     * @return array
     */
    private static function normalizeDays(array $days)
    {
        $out = array();
        foreach ($days as $day) {
            $day = substr(trim((string) $day), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            $out[$day] = $day;
        }
        $list = array_values($out);
        sort($list);
        if (count($list) > 7) {
            $list = array_slice($list, -7);
        }
        return $list;
    }

    /**
     * @param array $days
     * @param int   $streak
     * @return string
     */
    private static function encodeState(array $days, $streak)
    {
        return json_encode(array(
            'days'   => array_values($days),
            'streak' => (int) $streak,
        ), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $state
     * @return bool
     */
    private static function isAlive(array $state)
    {
        if (empty($state['last']) || (int) $state['streak'] < 1) {
            return false;
        }
        $today = self::today();
        return $state['last'] === $today || $state['last'] === self::shiftDay($today, -1);
    }

    /**
     * @param string $day
     * @param int    $delta
     * @return string
     */
    private static function shiftDay($day, $delta)
    {
        $ts = strtotime($day . ' ' . (int) $delta . ' day');
        if ($ts === false) {
            return $day;
        }
        return date('Y-m-d', $ts);
    }
}
