<?php
/**
 * 文件：core/DatabaseMigrator.php
 * 作用：版本更新时执行 install/migrations 下的增量 SQL（数据库结构更新）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class DatabaseMigrator
{
    const CONFIG_KEY = 'schema_migrations';

    /**
     * 结构更新脚本目录
     *
     * @return string
     */
    public static function migrationsDir()
    {
        return VS_ROOT . '/install/migrations';
    }

    /**
     * 执行尚未应用的结构更新
     *
     * @return array
     */
    public static function runPending()
    {
        if (!InstallChecker::isInstalled()) {
            return array('ok' => true, 'msg' => '系统未安装，跳过数据库结构更新', 'applied' => array());
        }

        self::reconcileSchemaState();

        $pending = self::getPendingFiles();
        if (count($pending) === 0) {
            return array('ok' => true, 'msg' => '数据库结构已是最新', 'applied' => array());
        }

        $applied = array();

        try {
            $pdo = Database::connect();
            $prefix = Database::prefix();

            foreach ($pending as $version => $file) {
                self::executeFile($pdo, $file, $prefix);
                self::markApplied($version);
                $applied[] = $version;
            }
        } catch (Exception $e) {
            return array(
                'ok'      => false,
                'msg'     => '数据库结构更新失败：' . $e->getMessage(),
                'applied' => $applied,
            );
        }

        Config::clearCache();

        return array(
            'ok'      => true,
            'msg'     => count($applied) > 0 ? '已执行 ' . count($applied) . ' 项数据库结构更新' : '数据库结构已是最新',
            'applied' => $applied,
        );
    }

    /**
     * 根据当前表结构同步已完成的更新记录（避免重复 ADD COLUMN 报错）
     *
     * @return void
     */
    public static function reconcileSchemaState()
    {
        if (!InstallChecker::isInstalled()) {
            return;
        }

        $applied = self::getAppliedVersions();

        if (!in_array('1.0.20', $applied, true) && self::tableColumnExists('admin', 'avatar_url')) {
            self::markApplied('1.0.20');
        }

        if (!in_array('1.8.0', $applied, true) && self::tableColumnExists('admin', 'bound_user_id')) {
            self::markApplied('1.8.0');
        }

        if (!in_array('1.9.0', $applied, true)) {
            $all = Config::all();
            if (array_key_exists('frontend_theme', $all)) {
                self::markApplied('1.9.0');
            }
        }

        if (!in_array('2.10.2', $applied, true) && self::tableExists('security_rate_hit')) {
            self::markApplied('2.10.2');
        }

        if (!in_array('2.11.0', $applied, true) && self::tableExists('mail_code_rate_log')) {
            self::markApplied('2.11.0');
        }

        if (!in_array('2.12.0', $applied, true) && self::tableExists('api')) {
            self::markApplied('2.12.0');
        }

        if (!in_array('2.15.0', $applied, true) && self::tableExists('api_category')) {
            self::markApplied('2.15.0');
        }

        if (!in_array('1.0.35', $applied, true)) {
            $all = Config::all();
            if (array_key_exists('storage_local_public_slug', $all)) {
                self::markApplied('1.0.35');
            }
        }

        if (!in_array('1.0.40', $applied, true) && !array_key_exists('storage_local_public_slug', Config::all())) {
            self::markApplied('1.0.40');
        }

        if (self::domainTableExists()) {
            try {
                $pdo = Database::connect();
                $prefix = Database::prefix();
                self::applyBoundDomainsMigration($pdo, $prefix);
                self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . $prefix . 'domain`');
            } catch (Exception $e) {
                // 留待正式迁移流程重试
            }
        }
    }

    /**
     * 待执行的结构更新脚本（按版本升序）
     *
     * @return array
     */
    public static function getPendingFiles()
    {
        self::reconcileSchemaState();

        $dir = self::migrationsDir();
        if (!is_dir($dir)) {
            return array();
        }

        $applied = self::getAppliedVersions();
        $pending = array();
        $files = glob($dir . '/*.sql');
        if ($files === false) {
            return array();
        }

        foreach ($files as $file) {
            $base = basename($file, '.sql');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $base)) {
                continue;
            }
            if (in_array($base, $applied, true)) {
                continue;
            }
            $pending[$base] = $file;
        }

        uksort($pending, 'version_compare');
        return $pending;
    }

    /**
     * 是否存在待执行的结构更新
     *
     * @return bool
     */
    public static function hasPendingMigrations()
    {
        return count(self::getPendingFiles()) > 0;
    }

    /**
     * 指定脚本是否尚未执行
     *
     * @param string $filename 如 1.0.15.sql
     * @return bool
     */
    public static function isMigrationPending($filename)
    {
        $version = preg_replace('/\.sql$/', '', basename($filename));
        $pending = self::getPendingFiles();
        return isset($pending[$version]);
    }

    /**
     * 已应用的结构更新版本
     *
     * @return array
     */
    public static function getAppliedVersions()
    {
        $raw = Config::get(self::CONFIG_KEY, '');
        if ($raw === '') {
            return array();
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array();
        }

        return array_values($data);
    }

    /**
     * 标记结构更新版本已应用
     *
     * @param string $version
     * @return void
     * @throws Exception
     */
    public static function markApplied($version)
    {
        $applied = self::getAppliedVersions();
        if (!in_array($version, $applied, true)) {
            $applied[] = $version;
            usort($applied, 'version_compare');
            Config::set(self::CONFIG_KEY, json_encode($applied, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 表字段是否已存在
     *
     * @param string $tableShort 不含前缀的表名，如 admin
     * @param string $column
     * @return bool
     */
    public static function tableColumnExists($tableShort, $column)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table($tableShort);
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute(array($column));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 表是否已存在
     *
     * @param string $tableShort 不含前缀的表名
     * @return bool
     */
    public static function tableExists($tableShort)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table($tableShort);
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
            return ($stmt && (bool) $stmt->fetch(PDO::FETCH_NUM));
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 执行单个结构更新脚本
     *
     * @param PDO    $pdo
     * @param string $file
     * @param string $prefix
     * @return void
     * @throws Exception
     */
    public static function executeFile(PDO $pdo, $file, $prefix)
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new Exception('结构更新脚本不可读：' . basename($file));
        }

        $version = basename($file, '.sql');
        if ($version === '1.0.20') {
            self::applyAdminAvatarUrlColumn($pdo, $prefix);
            return;
        }

        if ($version === '2.11.0') {
            self::applyMailCodeRateLogMigration($pdo, $prefix);
            return;
        }

        if ($version === '1.0.40') {
            self::applyLocalStorageDirectUrlMigration($pdo, $prefix);
            return;
        }

        if ($version === '1.0.47') {
            self::applyBoundDomainsMigration($pdo, $prefix);
        }

        $sql = file_get_contents($file);
        $sql = str_replace('{prefix}', $prefix, $sql);
        self::assertPrefixedTables($sql, $prefix, basename($file));
        $statements = DatabaseInstaller::parseSqlStatements($sql);

        foreach ($statements as $statement) {
            if ($version === '1.0.47' && trim($statement) === '') {
                continue;
            }
            self::execStatement($pdo, $statement);
        }
    }

    /**
     * v1.0.47：domain 表数据迁入 config.bound_domains（删表由 1.0.47.sql 执行）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyBoundDomainsMigration(PDO $pdo, $prefix)
    {
        $configTable = $prefix . 'config';
        $domainTable = $prefix . 'domain';

        $stmt = $pdo->prepare('SELECT `value` FROM `' . $configTable . '` WHERE `key` = ? LIMIT 1');
        $stmt->execute(array('bound_domains'));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing = Domain::decodeList($row ? (string) $row['value'] : '[]');

        $tableExists = false;
        try {
            $check = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($domainTable));
            $tableExists = (bool) $check->fetch(PDO::FETCH_NUM);
        } catch (Exception $e) {
            $tableExists = false;
        }

        if ($tableExists && count($existing) === 0) {
            $rows = $pdo->query('SELECT * FROM `' . $domainTable . '` ORDER BY `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
            $migrated = array();
            foreach ($rows as $item) {
                $migrated[] = array(
                    'id'            => (int) $item['id'],
                    'domain'        => Domain::normalizeHost($item['domain']),
                    'site_name'     => trim((string) $item['site_name']),
                    'icp_number'    => trim((string) $item['icp_number']),
                    'gongan_number' => trim((string) $item['gongan_number']),
                );
            }
            $existing = Domain::decodeList(json_encode($migrated, JSON_UNESCAPED_UNICODE));
        }

        $json = json_encode(array_values($existing), JSON_UNESCAPED_UNICODE);
        $upsert = $pdo->prepare(
            'INSERT INTO `' . $configTable . '` (`key`, `value`) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $upsert->execute(array('bound_domains', $json));

        Config::clearCache();
    }

    /**
     * domain 表是否仍存在（用于 1.0.47 迁移补偿）
     *
     * @return bool
     */
    public static function domainTableExists()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('domain');
            $check = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
            return (bool) $check->fetch(PDO::FETCH_NUM);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * v1.0.40：本地储存改回 upload 直链，清理 slug 网关
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyLocalStorageDirectUrlMigration(PDO $pdo, $prefix)
    {
        require_once VS_ROOT . '/core/Storage/LocalStorage/LocalStorageOptions.php';
        require_once VS_ROOT . '/core/Storage/LocalStorage/LocalStorageDriver.php';

        LocalStorageDriver::cleanupLegacyGateway();

        $table = $prefix . 'config';
        $pdo->exec("DELETE FROM `" . $table . "` WHERE `key` = 'storage_local_public_slug'");

        Config::clearCache();
        LocalStorageDriver::refreshStoredPublicUrls();
    }

    /**
     * 校验迁移脚本是否使用了表前缀占位符
     *
     * @param string $sql
     * @param string $prefix
     * @param string $filename
     * @return void
     * @throws Exception
     */
    public static function assertPrefixedTables($sql, $prefix, $filename)
    {
        $shortTables = array('admin', 'config', 'file_folder', 'file_item');
        foreach ($shortTables as $table) {
            if (preg_match('/`' . preg_quote($table, '/') . '`/i', $sql)
                && !preg_match('/`' . preg_quote($prefix . $table, '/') . '`/i', $sql)) {
                throw new Exception(
                    '结构更新脚本 ' . $filename . ' 未使用 {prefix} 表前缀（检测到 `' . $table . '`）'
                );
            }
        }
    }

    /**
     * v1.0.20：安全添加 avatar_url 字段（已存在则跳过）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyAdminAvatarUrlColumn(PDO $pdo, $prefix)
    {
        if (self::tableColumnExists('admin', 'avatar_url')) {
            return;
        }

        $table = $prefix . 'admin';
        $pdo->exec(
            'ALTER TABLE `' . $table . '` ADD COLUMN `avatar_url` varchar(500) NOT NULL DEFAULT \'\' '
            . 'COMMENT \'自定义头像链接\' AFTER `email`'
        );
    }

    /**
     * v2.11.0：security_rate_hit → mail_code_rate_log 表与字段规范化
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyMailCodeRateLogMigration(PDO $pdo, $prefix)
    {
        $oldTable = $prefix . 'security_rate_hit';
        $newTable = $prefix . 'mail_code_rate_log';

        $create = 'CREATE TABLE IF NOT EXISTS `' . $newTable . '` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `limit_key` varchar(64) NOT NULL COMMENT \'限流键 SHA256\',
            `created_at` int unsigned NOT NULL COMMENT \'命中时间 Unix 时间戳\',
            PRIMARY KEY (`id`),
            KEY `idx_limit_key_created` (`limit_key`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'邮箱验证码发信频率限制记录\'';
        self::execStatement($pdo, $create);

        $oldExists = false;
        try {
            $check = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($oldTable));
            $oldExists = (bool) $check->fetch(PDO::FETCH_NUM);
        } catch (Exception $e) {
            $oldExists = false;
        }

        if ($oldExists) {
            $countNew = (int) $pdo->query('SELECT COUNT(*) FROM `' . $newTable . '`')->fetchColumn();
            if ($countNew === 0) {
                $pdo->exec(
                    'INSERT INTO `' . $newTable . '` (`limit_key`, `created_at`)
                     SELECT `bucket`, `hit_at` FROM `' . $oldTable . '`'
                );
            }
            self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . $oldTable . '`');
        }
    }

    /**
     * 执行单条 SQL，忽略「已存在/已删除」类无害错误
     *
     * @param PDO    $pdo
     * @param string $statement
     * @return void
     * @throws Exception
     */
    public static function execStatement(PDO $pdo, $statement)
    {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (self::isIgnorableSqlError($e)) {
                return;
            }
            throw $e;
        }
    }

    /**
     * 是否为可忽略的结构更新错误（字段/索引已存在等）
     *
     * @param PDOException $e
     * @return bool
     */
    public static function isIgnorableSqlError(PDOException $e)
    {
        if (!is_array($e->errorInfo) || !isset($e->errorInfo[1])) {
            return false;
        }

        $code = (int) $e->errorInfo[1];
        return in_array($code, array(
            1050, // Table already exists
            1060, // Duplicate column name
            1061, // Duplicate key name
            1062, // Duplicate entry
            1091, // Can't DROP; check that column/key exists
        ), true);
    }
}
