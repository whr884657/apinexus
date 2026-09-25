<?php
/**
 * 文件：core/ThemeSettingsStore.php
 * 作用：主题展示配置本地 SQLite 存储（core/data/{themeId}/theme.db）
 *
 * 自 13.26.45 起权威存储在本地；MySQL config.themesettings 仅作升级一次性迁出源。
 * 启用主题 ID（frontend_theme）仍在 MySQL。
 */

class ThemeSettingsStore
{
    /** MySQL 遗留总表键（迁出后保持 {}，禁止再写入分桶） */
    const LEGACY_CONFIG_KEY = 'themesettings';

    /** 已完成本地化标记：local */
    const STORAGE_FLAG_KEY = 'themesettings_storage';
    const STORAGE_LOCAL = 'local';

    /** 内置主题 ID（仓库预建目录） */
    public static function builtinThemeIds()
    {
        return array('default', 'slate', 'three', 'docs', 'muming');
    }

    /**
     * @return string
     */
    public static function dataRoot()
    {
        return VS_ROOT . '/core/data';
    }

    /**
     * @param string $themeId
     * @return string
     */
    public static function themeDir($themeId)
    {
        $themeId = self::normalizeThemeId($themeId);
        if ($themeId === '') {
            return '';
        }
        return self::dataRoot() . '/' . $themeId;
    }

    /**
     * @param string $themeId
     * @return string
     */
    public static function dbPath($themeId)
    {
        $dir = self::themeDir($themeId);
        return $dir === '' ? '' : ($dir . '/theme.db');
    }

    /**
     * @return bool
     */
    public static function sqliteAvailable()
    {
        return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    }

    /**
     * 是否已标记为主题配置本地化完成
     *
     * @return bool
     */
    public static function isMigratedToLocal()
    {
        if (!class_exists('Config')) {
            return false;
        }
        return trim((string) Config::get(self::STORAGE_FLAG_KEY, '')) === self::STORAGE_LOCAL;
    }

    /**
     * 确保目录 + deny .htaccess（幂等）
     *
     * @param string $themeId
     * @return true|string
     */
    public static function ensureLocalDir($themeId)
    {
        $themeId = self::normalizeThemeId($themeId);
        if ($themeId === '') {
            return '无效的主题';
        }
        $root = self::dataRoot();
        if (!is_dir($root)) {
            if (!@mkdir($root, 0755, true) && !is_dir($root)) {
                return '无法创建主题数据根目录';
            }
        }
        self::writeDenyHtaccess($root);

        $dir = self::themeDir($themeId);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return '无法创建主题数据目录';
            }
        }
        self::writeDenyHtaccess($dir);
        return true;
    }

    /**
     * 确保内置主题目录骨架存在
     *
     * @return void
     */
    public static function ensureBuiltinDirs()
    {
        foreach (self::builtinThemeIds() as $id) {
            self::ensureLocalDir($id);
        }
    }

    /**
     * 读取主题整桶配置
     *
     * @param string $themeId
     * @return array<string, mixed>
     */
    public static function read($themeId)
    {
        $themeId = self::normalizeThemeId($themeId);
        if ($themeId === '') {
            return array();
        }
        if (!self::sqliteAvailable()) {
            return array();
        }
        $path = self::dbPath($themeId);
        if ($path === '' || !is_file($path)) {
            return array();
        }
        try {
            $pdo = self::openDb($path, false);
            if ($pdo === null) {
                return array();
            }
            $stmt = $pdo->query('SELECT `payload` FROM `theme_settings` WHERE `id` = 1 LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!is_array($row) || !isset($row['payload'])) {
                return array();
            }
            $decoded = json_decode((string) $row['payload'], true);
            return is_array($decoded) ? $decoded : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 写入主题整桶配置（覆盖）
     *
     * @param string $themeId
     * @param array<string, mixed> $data
     * @return true|string
     */
    public static function write($themeId, array $data)
    {
        $themeId = self::normalizeThemeId($themeId);
        if ($themeId === '') {
            return '无效的主题';
        }
        if (!self::sqliteAvailable()) {
            return '服务器未启用 PDO SQLite，无法保存主题设置';
        }
        $ensured = self::ensureLocalDir($themeId);
        if ($ensured !== true) {
            return $ensured;
        }
        $path = self::dbPath($themeId);
        if ($path === '') {
            return '无效的主题数据路径';
        }
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return '主题设置编码失败';
        }
        try {
            $pdo = self::openDb($path, true);
            if ($pdo === null) {
                return '无法打开主题配置库';
            }
            $stmt = $pdo->prepare(
                'INSERT OR REPLACE INTO `theme_settings` (`id`, `payload`) VALUES (1, :p)'
            );
            $stmt->execute(array(':p' => $encoded));
            return true;
        } catch (Exception $e) {
            return '写入主题配置库失败';
        }
    }

    /**
     * 删除主题本地目录（孤儿清理）
     *
     * @param string $themeId
     * @return void
     */
    public static function removeThemeLocal($themeId)
    {
        $themeId = self::normalizeThemeId($themeId);
        if ($themeId === '') {
            return;
        }
        // 禁止删出 data 根或越界
        if (in_array($themeId, array('.', '..'), true)) {
            return;
        }
        $dir = self::themeDir($themeId);
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $rootReal = realpath(self::dataRoot());
        $dirReal = realpath($dir);
        if ($rootReal === false || $dirReal === false) {
            return;
        }
        if (strpos($dirReal, $rootReal) !== 0 || $dirReal === $rootReal) {
            return;
        }
        self::rmTree($dirReal);
    }

    /**
     * 列出 core/data 下已有的主题子目录名
     *
     * @return string[]
     */
    public static function listLocalThemeIds()
    {
        $root = self::dataRoot();
        if (!is_dir($root)) {
            return array();
        }
        $out = array();
        $dirs = glob($root . '/*', GLOB_ONLYDIR);
        if (!is_array($dirs)) {
            return array();
        }
        foreach ($dirs as $dir) {
            $id = basename($dir);
            if (self::normalizeThemeId($id) === $id) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * 从 MySQL themesettings 强制覆盖写入本地，并清空库内桶、打标记
     *
     * @param bool $force 为 true 时即使已标记 local 也再跑（一般升级用 false）
     * @return true|string
     */
    public static function migrateFromMysql($force = false)
    {
        if (!class_exists('Config') || !class_exists('InstallChecker') || !InstallChecker::isInstalled()) {
            return true;
        }
        if (!$force && self::isMigratedToLocal()) {
            self::ensureBuiltinDirs();
            return true;
        }
        if (!self::sqliteAvailable()) {
            // 无扩展时仍尽量建目录；不打 local 标记，下次还可重试
            self::ensureBuiltinDirs();
            return '服务器未启用 PDO SQLite，主题配置暂无法迁入本地';
        }

        self::ensureBuiltinDirs();
        self::writeDenyHtaccess(self::dataRoot());

        $raw = Config::get(self::LEGACY_CONFIG_KEY, '{}');
        $decoded = array();
        if (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }

        foreach ($decoded as $themeId => $payload) {
            $themeId = self::normalizeThemeId((string) $themeId);
            if ($themeId === '') {
                continue;
            }
            $data = is_array($payload) ? $payload : array();
            $wrote = self::write($themeId, $data);
            if ($wrote !== true) {
                return $wrote;
            }
        }

        // 无桶的内置主题：确保空库存在（首次使用）
        foreach (self::builtinThemeIds() as $id) {
            $path = self::dbPath($id);
            if ($path !== '' && !is_file($path)) {
                $wrote = self::write($id, array());
                if ($wrote !== true) {
                    return $wrote;
                }
            }
        }

        try {
            Config::set(self::LEGACY_CONFIG_KEY, '{}');
            Config::set(self::STORAGE_FLAG_KEY, self::STORAGE_LOCAL);
        } catch (Exception $e) {
            return '清空库内主题设置标记失败';
        }
        return true;
    }

    /**
     * 读取 MySQL 遗留分桶（仅迁移用）
     *
     * @return array<string, array>
     */
    public static function readLegacyMysqlBuckets()
    {
        if (!class_exists('Config')) {
            return array();
        }
        $raw = Config::get(self::LEGACY_CONFIG_KEY, '{}');
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }
        $out = array();
        foreach ($decoded as $themeId => $payload) {
            $themeId = self::normalizeThemeId((string) $themeId);
            if ($themeId === '' || !is_array($payload)) {
                continue;
            }
            $out[$themeId] = $payload;
        }
        return $out;
    }

    /**
     * @param string $themeId
     * @return string 合法 id 或空串
     */
    private static function normalizeThemeId($themeId)
    {
        $themeId = trim((string) $themeId);
        if ($themeId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/i', $themeId)) {
            return '';
        }
        return $themeId;
    }

    /**
     * @param string $path
     * @param bool $createSchema
     * @return PDO|null
     */
    private static function openDb($path, $createSchema)
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($createSchema) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `theme_settings` (
                    `id` INTEGER PRIMARY KEY CHECK (`id` = 1),
                    `payload` TEXT NOT NULL DEFAULT \'{}\'
                )'
            );
        }
        return $pdo;
    }

    /**
     * @param string $dir
     * @return void
     */
    private static function writeDenyHtaccess($dir)
    {
        $dir = rtrim((string) $dir, '/\\');
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $file = $dir . '/.htaccess';
        if (is_file($file)) {
            return;
        }
        $body = "# Deny HTTP access to theme local settings (Apache)\n"
            . "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Order deny,allow\n"
            . "    Deny from all\n"
            . "</IfModule>\n";
        @file_put_contents($file, $body);
    }

    /**
     * @param string $dir
     * @return void
     */
    private static function rmTree($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::rmTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
