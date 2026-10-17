<?php
/**
 * 文件：core/Config.php
 * 作用：系统配置读写（vs_config 表，初始数据见 database.sql）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class Config
{
    /** @var array|null */
    private static $cache = null;

    /**
     * 加载全部配置（仅从数据库读取）
     *
     * @return array
     */
    public static function all()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = array();

        try {
            if (InstallChecker::isInstalled()) {
                $pdo = Database::connect();
                $table = Database::table('config');
                $stmt = $pdo->query(
                    'SELECT `key` AS cfg_key, `value` AS cfg_value FROM `' . $table . '`'
                );
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    if (isset($row['cfg_key'])) {
                        self::$cache[$row['cfg_key']] = $row['cfg_value'];
                    }
                }
            }
        } catch (Exception $e) {
            // 安装阶段数据库未就绪
        }

        return self::$cache;
    }

    /**
     * 获取配置项
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : $default;
    }

    /**
     * 设置配置项
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     * @throws Exception
     */
    public static function set($key, $value)
    {
        $key = (string) $key;
        // 安装完成标记只允许置为 1，禁止经通用接口清除（防重装绕过）
        if (class_exists('InstallChecker') && $key === InstallChecker::CONFIG_KEY_DONE) {
            if (trim((string) $value) !== '1') {
                throw new Exception('禁止修改或清除安装完成标记');
            }
            $value = '1';
        }

        $pdo = Database::connect();
        $table = Database::table('config');
        $stmt = $pdo->prepare(
            'INSERT INTO `' . $table . '` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute(array($key, (string) $value));

        if (self::$cache !== null) {
            self::$cache[$key] = (string) $value;
        } else {
            self::$cache = null;
        }
        SiteContext::clearCache();
    }

    /**
     * 批量保存配置
     *
     * @param array $items
     * @return void
     * @throws Exception
     */
    public static function setMany(array $items)
    {
        if (array_key_exists('checkin_points_min', $items) || array_key_exists('checkin_points_max', $items)) {
            $range = self::normalizeCheckinPointsRange(
                array_key_exists('checkin_points_min', $items)
                    ? $items['checkin_points_min']
                    : self::get('checkin_points_min', '10'),
                array_key_exists('checkin_points_max', $items)
                    ? $items['checkin_points_max']
                    : self::get('checkin_points_max', '30')
            );
            $items['checkin_points_min'] = (string) $range['min'];
            $items['checkin_points_max'] = (string) $range['max'];
        }

        $pdo = Database::connect();
        $ownsTransaction = !$pdo->inTransaction();
        $table = Database::table('config');
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '` (`key`, `value`) VALUES (?, ?) '
                . 'ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            foreach ($items as $key => $value) {
                $key = (string) $key;
                if (class_exists('InstallChecker') && $key === InstallChecker::CONFIG_KEY_DONE) {
                    if (trim((string) $value) !== '1') {
                        throw new Exception('禁止修改或清除安装完成标记');
                    }
                    $value = '1';
                }
                $stmt->execute(array($key, (string) $value));
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        self::$cache = null;
        SiteContext::clearCache();
    }

    /**
     * Keep check-in point bounds valid for both persistence and runtime reads.
     *
     * @param mixed $min
     * @param mixed $max
     * @return array{min:int,max:int}
     */
    public static function normalizeCheckinPointsRange($min, $max)
    {
        $min = max(1, min(1000000, (int) $min));
        $max = max(1, min(1000000, (int) $max));
        if ($max < $min) {
            $max = $min;
        }
        return array('min' => $min, 'max' => $max);
    }

    /**
     * 邮箱发信是否已配置可用
     * 条件：mail_enabled=1，且 mailsmtp 启用池至少 1 条（on=1 且 host/user/fromemail 非空）
     *
     * @return bool
     */
    public static function isMailEnabled()
    {
        if (self::get('mail_enabled') !== '1') {
            return false;
        }
        if (class_exists('Mailer')) {
            return Mailer::hasReadyAccount();
        }
        return false;
    }

    /**
     * 获取会话超时秒数（写死在代码中）
     *
     * @return int
     */
    public static function sessionTimeout()
    {
        return defined('VS_SESSION_TIMEOUT') ? (int) VS_SESSION_TIMEOUT : 1800;
    }

    /**
     * 清除缓存
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$cache = null;
        SiteContext::clearCache();
    }
}
