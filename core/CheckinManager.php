<?php
/**
 * 文件：core/CheckinManager.php
 * 作用：每日签到（user.lastcheckin；同用户同日唯一；主题经 FrontendUser / PointsManager 调用）
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
        $min = (int) Config::get('checkin_points_min', '10');
        $max = (int) Config::get('checkin_points_max', '30');
        if ($min < 1) {
            $min = 1;
        }
        if ($max < $min) {
            $max = $min;
        }
        $checked = $enabled ? self::hasCheckedInToday((int) $userId) : false;
        return array(
            'enabled'       => $enabled,
            'checked_today' => $checked,
            'min'           => $min,
            'max'           => $max,
            'show_banner'   => $enabled && !$checked,
        );
    }
}
