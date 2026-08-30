<?php
/**
 * 文件：core/InstallChecker.php
 * 作用：检测系统是否已完成安装（文件锁 + 库标记双保险）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class InstallChecker
{
    /** 系统配置表键：安装完成标记（值「1」=已安装；勿在后台表单暴露可改） */
    const CONFIG_KEY_DONE = 'install_done';

    /**
     * 安装锁文件路径
     *
     * @return string
     */
    public static function lockFile()
    {
        return VS_ROOT . '/config/install.lock';
    }

    /**
     * 数据库配置文件路径
     *
     * @return string
     */
    public static function configFile()
    {
        return VS_ROOT . '/config/database.php';
    }

    /**
     * 是否已安装
     *
     * 规则：须有 database.php；且「install.lock 存在」或「config.install_done=1」任一成立即视为已安装。
     * 删锁 alone 无法重进安装向导。无锁且库检测异常时 fail-closed（视为已装，禁止重装）。
     *
     * @return bool
     */
    public static function isInstalled()
    {
        if (!file_exists(self::configFile())) {
            return false;
        }
        if (file_exists(self::lockFile())) {
            return true;
        }
        $flag = self::probeDbInstallFlag();
        // true=已标记；false=明确未标记；null=库不可达 → fail-closed
        if ($flag === true) {
            return true;
        }
        if ($flag === false) {
            return false;
        }
        return true;
    }

    /**
     * 探测库中安装完成标记
     *
     * @return bool|null true=值为1；false=无键或非1；null=连接/查询失败
     */
    public static function probeDbInstallFlag()
    {
        try {
            if (!class_exists('Database')) {
                return null;
            }
            $pdo = Database::connect();
            $table = Database::table('config');
            $stmt = $pdo->prepare(
                'SELECT `value` FROM `' . $table . '` WHERE `key` = ? LIMIT 1'
            );
            $stmt->execute(array(self::CONFIG_KEY_DONE));
            $v = $stmt->fetchColumn();
            if ($v === false || $v === null) {
                return false;
            }
            return trim((string) $v) === '1';
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 库中安装完成标记是否为 1（兼容旧调用；异常时按未标记 false，业务请优先用 isInstalled）
     *
     * @return bool
     */
    public static function dbInstallFlagSet()
    {
        return self::probeDbInstallFlag() === true;
    }

    /**
     * 写入 / 强制置位库中安装完成标记（幂等）
     *
     * @return void
     * @throws Exception
     */
    public static function markInstalledInConfig()
    {
        if (!class_exists('Config')) {
            throw new Exception('Config 类不可用，无法写入安装标记');
        }
        Config::set(self::CONFIG_KEY_DONE, '1');
    }

    /**
     * 未安装时重定向到安装向导
     *
     * @return void
     */
    public static function requireInstalled()
    {
        if (!self::isInstalled()) {
            vs_redirect(vs_base_url() . '/install/');
        }
    }

    /**
     * 已安装时禁止访问安装向导
     *
     * @return void
     */
    public static function requireNotInstalled()
    {
        if (self::isInstalled()) {
            vs_redirect(vs_base_url() . '/');
        }
    }
}
