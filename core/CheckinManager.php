<?php
/**
 * 文件：core/CheckinManager.php
 * 作用：每日签到（user.lastcheckin；同用户同日唯一；主题经 FrontendUser / PointsManager 调用）
 *       系统级外部 API 开关与用户解析见 isSystemApiEnabled / resolveUserForSystemApi
 */

class CheckinManager
{
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
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `lastcheckin` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $d = $stmt->fetchColumn();
            if ($d === false || $d === null || $d === '') {
                return false;
            }
            return substr((string) $d, 0, 10) === self::today();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 占位：把 lastcheckin 更新为今日（仅当尚未是今日）
     *
     * @param int   $userId
     * @param float $points 保留参数兼容旧调用；积分以 orders 为准，不写入 user
     * @return true|string
     */
    public static function record($userId, $points = 0)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '参数无效';
        }
        if (!self::tableReady()) {
            return '签到功能尚未就绪';
        }
        try {
            $pdo = Database::connect();
            $today = self::today();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('user') . '`
                 SET `lastcheckin` = ?
                 WHERE `id` = ? AND (`lastcheckin` IS NULL OR `lastcheckin` < ?)'
            );
            $stmt->execute(array($today, $userId, $today));
            if ((int) $stmt->rowCount() < 1) {
                return '今日已签到';
            }
            return true;
        } catch (Exception $e) {
            return '签到记录失败';
        }
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
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('user') . '`
                 SET `lastcheckin` = NULL
                 WHERE `id` = ? AND `lastcheckin` = ?'
            );
            $stmt->execute(array($userId, self::today()));
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
        $checked = $enabled ? self::hasCheckedInToday((int) $userId) : false;
        return array(
            'enabled'       => $enabled,
            'checked_today' => $checked,
            'min'           => $range['min'],
            'max'           => $range['max'],
            'show_banner'   => $enabled && !$checked,
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
        $min = (int) Config::get('checkin_points_min', '10');
        $max = (int) Config::get('checkin_points_max', '30');
        if ($min < 1) {
            $min = 1;
        }
        if ($max < $min) {
            $max = $min;
        }
        if ($max > 1000000) {
            $max = 1000000;
        }
        return array('min' => $min, 'max' => $max);
    }

    /**
     * 系统 API：只读功能配置
     *
     * @return array{feature_enabled:bool,ready:bool,min:int,max:int}
     */
    public static function systemApiConfig()
    {
        $range = self::pointsRange();
        return array(
            'feature_enabled' => Config::get('checkin_enabled', '0') === '1',
            'ready'           => self::tableReady(),
            'min'             => $range['min'],
            'max'             => $range['max'],
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
        $last = '';
        if ($userId > 0 && self::tableReady()) {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare(
                    'SELECT `lastcheckin` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($userId));
                $d = $stmt->fetchColumn();
                if ($d !== false && $d !== null && $d !== '') {
                    $last = substr((string) $d, 0, 10);
                }
            } catch (Exception $e) {
                $last = '';
            }
        }
        return array(
            'userid'          => $userId,
            'feature_enabled' => !empty($banner['enabled']),
            'checked_today'   => !empty($banner['checked_today']),
            'lastcheckin'     => $last,
            'min'             => isset($banner['min']) ? (int) $banner['min'] : 0,
            'max'             => isset($banner['max']) ? (int) $banner['max'] : 0,
        );
    }
}
