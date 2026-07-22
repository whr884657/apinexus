<?php
/**
 * 文件：core/DatabaseMigrator.php
 * 作用：版本更新时执行 install/migrations 下的增量 SQL（数据库结构更新）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 * 仅处理 ApiNexus 正式库表（admin / user / config / api / category / mailrate / apilog / apikey / orders）。
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

        self::purgeLegacyArtifacts();

        $applied = self::getAppliedVersions();

        if (!in_array('1.0.20', $applied, true) && self::tableColumnExists('admin', 'avatar')) {
            self::markApplied('1.0.20');
        }

        if (!in_array('1.8.0', $applied, true) && self::tableColumnExists('admin', 'binduid')) {
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

        if (!in_array('2.11.0', $applied, true) && self::tableExists('mailrate')) {
            self::markApplied('2.11.0');
        }

        if (!in_array('2.12.0', $applied, true) && self::tableExists('api')) {
            self::markApplied('2.12.0');
        }

        if (!in_array('2.15.0', $applied, true) && (self::tableExists('api_category') || self::tableExists('category'))) {
            self::markApplied('2.15.0');
        }

        if (!in_array('2.15.1', $applied, true) && self::tableExists('category') && !self::tableExists('api_category')) {
            self::markApplied('2.15.1');
        }

        if (!in_array('2.16.0', $applied, true) && self::tableColumnExists('category', 'icon')) {
            self::markApplied('2.16.0');
        }

        if (!in_array('3.0.0', $applied, true) && self::tableColumnExists('user', 'role')) {
            self::markApplied('3.0.0');
        }

        // 新装已含 3.6.0+ 接口扩展字段时跳过旧 DROP 重建
        if (!in_array('3.6.0', $applied, true) && self::tableColumnExists('api', 'aidoc')) {
            self::markApplied('3.6.0');
        }

        if (!in_array('3.8.0', $applied, true) && self::tableColumnExists('api', 'audit')) {
            self::markApplied('3.8.0');
        }

        // 新装已含 3.9.0（字段去下划线）时跳过迁移
        if (!in_array('3.9.0', $applied, true) && self::tableColumnExists('api', 'needkey')) {
            self::markApplied('3.9.0');
        }

        // 新装已含 3.10.0（rejectreason + 审核三态）时跳过迁移
        if (!in_array('3.10.0', $applied, true) && self::tableColumnExists('api', 'rejectreason')) {
            self::markApplied('3.10.0');
        }

        // 新装已含 3.11.0（代理字段）时跳过迁移
        if (!in_array('3.11.0', $applied, true) && self::tableColumnExists('api', 'proxyslug')) {
            self::markApplied('3.11.0');
        }

        // 新装已含 3.18.0（apilog）时跳过迁移
        if (!in_array('3.18.0', $applied, true) && self::tableExists('apilog')) {
            self::markApplied('3.18.0');
        }

        // 新装已含 3.19.0（iploc）时跳过迁移
        if (!in_array('3.19.0', $applied, true) && self::tableColumnExists('apilog', 'iploc')) {
            self::markApplied('3.19.0');
        }

        // 新装已含 3.29.0（apikey）时跳过迁移；早期误建 token 则重命名
        if (self::tableExists('token') && !self::tableExists('apikey')) {
            try {
                $pdo = Database::connect();
                self::execStatement(
                    $pdo,
                    'RENAME TABLE `' . Database::table('token') . '` TO `' . Database::table('apikey') . '`'
                );
            } catch (Exception $e) {
                // 留待下次结构更新重试
            }
        }
        if (!in_array('3.29.0', $applied, true) && self::tableExists('apikey')) {
            self::markApplied('3.29.0');
        }

        // 新装已含 3.33.0（积分/订单/接口计费）时跳过迁移
        if (!in_array('3.33.0', $applied, true)
            && self::tableExists('orders')
            && self::tableColumnExists('user', 'points')
            && self::tableColumnExists('api', 'charge')) {
            self::markApplied('3.33.0');
        }

        // 新装无历史 /proxy.php?s= 地址时，3.12.0 的 UPDATE 幂等，不强制跳过

        // 新装已含 5.8.0 apilog 复合索引时跳过
        if (!in_array('5.8.0', $applied, true) && self::tableIndexExists('apilog', 'idx_createtime_id')) {
            self::markApplied('5.8.0');
        }

        // 5.8.0 重构：热天数 / 计划任务密钥（幂等；兼容已跑过旧版 keep_days 的站点）
        self::ensureApilogArchiveConfig();
    }

    /**
     * 确保调用日志冷热归档配置存在（幂等）
     *
     * @return void
     */
    private static function ensureApilogArchiveConfig()
    {
        try {
            $all = Config::all();
            $hot = isset($all['apilog_hot_days']) ? trim((string) $all['apilog_hot_days']) : '';
            if ($hot === '' || (int) $hot < 1) {
                $fromKeep = isset($all['apilog_keep_days']) ? (int) $all['apilog_keep_days'] : 30;
                if ($fromKeep < 1) {
                    $fromKeep = 30;
                }
                Config::set('apilog_hot_days', (string) $fromKeep);
            }
            if (!array_key_exists('apilog_cron_key', $all)) {
                Config::set('apilog_cron_key', '');
            }
            if (!array_key_exists('apilog_archive_enabled', $all)) {
                Config::set('apilog_archive_enabled', '1');
            }
            if (array_key_exists('apilog_keep_days', $all)) {
                $pdo = Database::connect();
                $table = Database::table('config');
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                $stmt->execute(array('apilog_keep_days'));
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * 索引是否已存在
     *
     * @param string $tableShort 不含前缀的表名
     * @param string $indexName
     * @return bool
     */
    public static function tableIndexExists($tableShort, $indexName)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table($tableShort);
            $stmt = $pdo->prepare('SHOW INDEX FROM `' . $table . '` WHERE `Key_name` = ?');
            $stmt->execute(array($indexName));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 清理从旧「文件管理系统」误迁入的表/配置残留（幂等）
     *
     * @return void
     */
    public static function purgeLegacyArtifacts()
    {
        $legacyTables = array('domain', 'file_folder', 'file_item');
        foreach ($legacyTables as $short) {
            if (!self::tableExists($short)) {
                continue;
            }
            try {
                $pdo = Database::connect();
                self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . Database::table($short) . '`');
            } catch (Exception $e) {
                // 留待下次结构更新重试
            }
        }

        $legacyKeys = array(
            'storage_local_public_slug',
            'bound_domains',
            'primary_domain',
        );
        try {
            $pdo = Database::connect();
            $table = Database::table('config');
            foreach ($legacyKeys as $key) {
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                $stmt->execute(array($key));
            }
            Config::clearCache();
        } catch (Exception $e) {
            // ignore
        }

        if (self::tableExists('mailrate') && self::tableExists('mail_code_rate_log')) {
            try {
                $pdo = Database::connect();
                self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . Database::table('mail_code_rate_log') . '`');
            } catch (Exception $e) {
                // 留待下次结构更新重试
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

        $sql = file_get_contents($file);
        $sql = str_replace('{prefix}', $prefix, $sql);
        self::assertPrefixedTables($sql, $prefix, basename($file));
        $statements = DatabaseInstaller::parseSqlStatements($sql);

        foreach ($statements as $statement) {
            self::execStatement($pdo, $statement);
        }
    }

    /**
     * 校验迁移脚本是否使用了表前缀占位符（仅检查 ApiNexus 正式表）
     *
     * @param string $sql
     * @param string $prefix
     * @param string $filename
     * @return void
     * @throws Exception
     */
    public static function assertPrefixedTables($sql, $prefix, $filename)
    {
        $shortTables = array(
            'admin',
            'user',
            'config',
            'api',
            'category',
            'mailrate',
        );
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
     * v1.0.20：安全添加 avatar 字段（已存在则跳过）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyAdminAvatarUrlColumn(PDO $pdo, $prefix)
    {
        if (self::tableColumnExists('admin', 'avatar')) {
            return;
        }

        $table = $prefix . 'admin';
        $pdo->exec(
            'ALTER TABLE `' . $table . '` ADD COLUMN `avatar` varchar(500) NOT NULL DEFAULT \'\' '
            . 'COMMENT \'自定义头像链接\' AFTER `email`'
        );
    }

    /**
     * v2.11.0：security_rate_hit → mailrate 表与字段规范化
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyMailCodeRateLogMigration(PDO $pdo, $prefix)
    {
        $oldTable = $prefix . 'security_rate_hit';
        $newTable = $prefix . 'mailrate';

        $create = 'CREATE TABLE IF NOT EXISTS `' . $newTable . '` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'主键 ID\',
            `limitkey` varchar(64) NOT NULL COMMENT \'限流键（SHA256）\',
            `createtime` int unsigned NOT NULL COMMENT \'命中时间（Unix 时间戳）\',
            PRIMARY KEY (`id`),
            KEY `idx_limitkey_createtime` (`limitkey`, `createtime`)
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
                    'INSERT INTO `' . $newTable . '` (`limitkey`, `createtime`)
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
