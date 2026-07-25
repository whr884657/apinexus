<?php
/**
 * 文件：core/CheckinManager.php
 * 作用：每日签到记录（同用户同日唯一；主题经 FrontendUser / PointsManager 调用）
 */

class CheckinManager
{
    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('checkin');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            $ready = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            $ready = false;
        }
        return $ready;
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
                'SELECT `id` FROM `' . self::table() . '`
                 WHERE `userid` = ? AND `checkindate` = ? LIMIT 1'
            );
            $stmt->execute(array($userId, self::today()));
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int   $userId
     * @param float $points
     * @return true|string
     */
    public static function record($userId, $points)
    {
        $userId = (int) $userId;
        $points = round((float) $points, 4);
        if ($userId <= 0 || $points <= 0) {
            return '参数无效';
        }
        if (!self::tableReady()) {
            return '签到表未就绪';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                 (`userid`, `checkindate`, `points`, `createtime`)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute(array($userId, self::today(), $points));
            return true;
        } catch (Exception $e) {
            // 唯一键冲突 = 今日已签
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                return '今日已签到';
            }
            return '签到记录失败';
        }
    }

    /**
     * 积分入账失败时回滚当日占位
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
                'DELETE FROM `' . self::table() . '` WHERE `userid` = ? AND `checkindate` = ? LIMIT 1'
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
