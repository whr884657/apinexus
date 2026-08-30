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
        foreach ($items as $key => $value) {
            self::set($key, $value);
        }
        self::$cache = null;
        SiteContext::clearCache();
    }

    /**
     * 邮箱发信是否已配置可用
     *
     * @return bool
     */
    public static function isMailEnabled()
    {
        return self::get('mail_enabled') === '1'
            && self::get('mail_smtp_host') !== ''
            && self::get('mail_smtp_user') !== ''
            && self::get('mail_from_email') !== '';
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
