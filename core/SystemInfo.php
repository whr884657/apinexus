<?php
/**
 * 文件：core/SystemInfo.php
 * 作用：服务器与运行环境信息（关于页面）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class SystemInfo
{
    /**
     * 获取系统环境信息
     *
     * @return array
     */
    public static function collect()
    {
        $info = array(
            self::item('系统名称', 'misc-api'),
            self::item('系统版本', 'v' . VS_VERSION),
            self::item('PHP 版本', PHP_VERSION),
            self::item('操作系统', PHP_OS),
            self::item('时区', date_default_timezone_get()),
            self::item('服务器时间', date('Y-m-d H:i:s')),
            self::item('Redis 版本', RedisService::versionLabel()),
            self::item(
                '服务器软件',
                isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '未知'
            ),
        );

        if (InstallChecker::isInstalled()) {
            try {
                $pdo = Database::connect();
                $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                $info[] = self::item('MySQL 版本', $version);
            } catch (Exception $e) {
                $info[] = self::item('MySQL 版本', '连接失败');
            }
        }

        return $info;
    }

    /**
     * @param string $label
     * @param string $value
     * @return array
     */
    private static function item($label, $value)
    {
        return array(
            'label' => $label,
            'value' => $value,
        );
    }
}
