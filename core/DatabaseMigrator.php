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
     * 规范化版本号（去 v 前缀、空白）
     *
     * @param mixed $version
     * @return string
     */
    public static function normalizeVersionToken($version)
    {
        $v = trim((string) $version);
        if ($v === '') {
            return '';
        }
        if (isset($v[0]) && ($v[0] === 'v' || $v[0] === 'V')) {
            $v = substr($v, 1);
        }
        return trim($v);
    }

    /**
     * 写入 schema_migrations（写后清缓存并校验）
     *
     * @param array $versions
     * @return void
     * @throws Exception
     */
    private static function writeAppliedVersions(array $versions)
    {
        $clean = array();
        foreach ($versions as $v) {
            $n = self::normalizeVersionToken($v);
            if ($n === '' || !preg_match('/^\d+\.\d+\.\d+$/', $n)) {
                continue;
            }
            if (!in_array($n, $clean, true)) {
                $clean[] = $n;
            }
        }
        usort($clean, 'version_compare');
        $json = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('schema_migrations 序列化失败');
        }
        Config::set(self::CONFIG_KEY, $json);
        Config::clearCache();

        $verify = self::getAppliedVersions();
        if ($verify !== array_values($clean)) {
            // 二次直写兜底（避免缓存/键冲突导致页面仍见旧值）
            $pdo = Database::connect();
            $table = Database::table('config');
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '` (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            $stmt->execute(array(self::CONFIG_KEY, $json));
            Config::clearCache();
        }
    }

    /**
     * 执行尚未应用的结构更新
     *
     * @return array
     */
    public static function runPending($maxVersion = null)
    {
        if (!InstallChecker::isInstalled()) {
            return array('ok' => true, 'msg' => '系统未安装，跳过数据库结构更新', 'applied' => array());
        }

        if (defined('VS_VERSION')) {
            self::pruneAppliedAboveCodeVersion(VS_VERSION);
        }

        self::reconcileSchemaState();

        // 在线更新必须限制上限；未传时默认不超过当前代码版本（禁止一次跑光磁盘上的未来 .sql）
        if ($maxVersion === null || $maxVersion === '') {
            $maxVersion = defined('VS_VERSION') ? VS_VERSION : null;
        }

        $pending = self::getPendingFiles($maxVersion);
        if (count($pending) === 0) {
            return array('ok' => true, 'msg' => '数据库结构已是最新', 'applied' => array());
        }

        $applied = array();

        try {
            $pdo = Database::connect();
            $prefix = Database::prefix();

            foreach ($pending as $version => $file) {
                self::executeFile($pdo, $file, $prefix);
                if (self::hasSchemaProbe($version) && !self::versionSchemaReady($version)) {
                    throw new Exception('版本 v' . $version . ' 结构更新后校验失败');
                }
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

        // 新装已含 5.9.0 orders 复合索引时跳过
        if (!in_array('5.9.0', $applied, true) && self::tableIndexExists('orders', 'idx_userid_status_id')) {
            self::markApplied('5.9.0');
        }

        // 新装已含 6.0.0 用户资料字段时跳过
        if (!in_array('6.0.0', $applied, true) && self::tableColumnExists('user', 'wallpaper')) {
            self::markApplied('6.0.0');
        }

        // 新装已含 7.0.0 内容表时跳过
        if (!in_array('7.0.0', $applied, true) && self::tableExists('content')) {
            self::markApplied('7.0.0');
        }

        // 新装已含 7.1.0 封面布局字段时跳过
        if (!in_array('7.1.0', $applied, true) && self::tableColumnExists('content', 'coverlayout')) {
            self::markApplied('7.1.0');
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
            if (!array_key_exists('apilog_shard_rows', $all)) {
                Config::set('apilog_shard_rows', '5000');
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
     * @param string|null $maxVersion 仅返回 <= 该版本的脚本；在线更新必须传入目标版本，禁止一次跑光未来版
     * @return array
     */
    public static function getPendingFiles($maxVersion = null)
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

        $maxVersion = $maxVersion !== null ? self::normalizeVersionToken($maxVersion) : null;

        foreach ($files as $file) {
            $base = basename($file, '.sql');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $base)) {
                continue;
            }
            if (in_array($base, $applied, true)) {
                continue;
            }
            if ($maxVersion !== null && $maxVersion !== '' && version_compare($base, $maxVersion, '>')) {
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
     * @param string|null $maxVersion
     * @return bool
     */
    public static function hasPendingMigrations($maxVersion = null)
    {
        return count(self::getPendingFiles($maxVersion)) > 0;
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
        if ($raw === '' || $raw === null) {
            return array();
        }
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
        }
        if (!is_array($data)) {
            return array();
        }

        $out = array();
        foreach ($data as $v) {
            $n = self::normalizeVersionToken($v);
            if ($n !== '' && preg_match('/^\d+\.\d+\.\d+$/', $n) && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        usort($out, 'version_compare');
        return $out;
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
        $version = self::normalizeVersionToken($version);
        if ($version === '') {
            return;
        }
        // 代码版本尚未升到该迁移版本时禁止写入（防止回退后 reconcile 再次写回假记录）
        if (defined('VS_VERSION') && version_compare($version, self::normalizeVersionToken(VS_VERSION), '>')) {
            return;
        }
        $applied = self::getAppliedVersions();
        if (!in_array($version, $applied, true)) {
            $applied[] = $version;
            self::writeAppliedVersions($applied);
        }
    }

    /**
     * 取消已应用标记（结构未就绪或代码版本回退时）
     *
     * @param string $version
     * @return void
     */
    public static function unmarkApplied($version)
    {
        $version = self::normalizeVersionToken($version);
        if ($version === '') {
            return;
        }
        $applied = self::getAppliedVersions();
        $next = array();
        foreach ($applied as $v) {
            if (self::normalizeVersionToken($v) !== $version) {
                $next[] = $v;
            }
        }
        if (count($next) === count($applied)) {
            return;
        }
        self::writeAppliedVersions($next);
    }

    /**
     * 代码版本回退时：清除 schema_migrations 中高于当前代码版本的执行记录
     * （避免回退后再次升级时因「假已执行」而跳过 SQL）
     *
     * @param string $codeVersion VS_VERSION
     * @return array 被清除的版本号列表
     */
    public static function pruneAppliedAboveCodeVersion($codeVersion)
    {
        $codeVersion = self::normalizeVersionToken($codeVersion);
        if ($codeVersion === '' || !InstallChecker::isInstalled()) {
            return array();
        }

        // 先用缓存快速判断；确有超出版本再清缓存重读并写入（避免每请求都 reload config）
        $applied = self::getAppliedVersions();
        $maybeRemove = false;
        foreach ($applied as $v) {
            if (version_compare(self::normalizeVersionToken($v), $codeVersion, '>')) {
                $maybeRemove = true;
                break;
            }
        }
        if (!$maybeRemove) {
            return array();
        }

        Config::clearCache();
        $applied = self::getAppliedVersions();
        if (count($applied) === 0) {
            return array();
        }

        $kept = array();
        $removed = array();
        foreach ($applied as $v) {
            $v = self::normalizeVersionToken($v);
            if ($v === '') {
                continue;
            }
            if (version_compare($v, $codeVersion, '>')) {
                $removed[] = $v;
            } else {
                $kept[] = $v;
            }
        }

        if (count($removed) === 0) {
            return array();
        }

        self::writeAppliedVersions($kept);
        return $removed;
    }

    /**
     * 结构未就绪时强制取消标记并执行（含区间内所有有 .sql 的版本）
     *
     * @param string $fromVersion 升级前版本（不含）
     * @param string $toVersion   升级后版本（含）
     * @return array 实际执行成功的版本号
     */
    public static function forceMigrateRange($fromVersion, $toVersion)
    {
        $fromVersion = trim((string) $fromVersion);
        $toVersion = trim((string) $toVersion);
        if ($toVersion === '') {
            return array();
        }

        $dir = self::migrationsDir();
        if (!is_dir($dir)) {
            throw new Exception('缺少 install/migrations 目录，请重新下载更新包');
        }

        $files = glob($dir . '/*.sql');
        if ($files === false) {
            $files = array();
        }

        $candidates = array();
        foreach ($files as $file) {
            $base = basename($file, '.sql');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $base)) {
                continue;
            }
            if ($fromVersion !== '' && version_compare($base, $fromVersion, '<=')) {
                continue;
            }
            if (version_compare($base, $toVersion, '>')) {
                continue;
            }
            $candidates[$base] = $file;
        }
        uksort($candidates, 'version_compare');

        $appliedNow = array();
        $pdo = Database::connect();
        $prefix = Database::prefix();

        foreach ($candidates as $version => $file) {
            if (self::versionSchemaReady($version)) {
                self::markApplied($version);
                continue;
            }
            // 假「已执行」记录必须先清掉再跑
            self::unmarkApplied($version);
            self::executeFile($pdo, $file, $prefix);
            if (!self::versionSchemaReady($version)) {
                // 无专用探测的旧脚本：执行成功即认
                if (!self::hasSchemaProbe($version)) {
                    self::markApplied($version);
                    $appliedNow[] = $version;
                    continue;
                }
                throw new Exception(
                    '版本 v' . $version . ' 数据库结构更新后校验失败（表/字段未就绪）'
                );
            }
            self::markApplied($version);
            $appliedNow[] = $version;
        }

        Config::clearCache();
        return $appliedNow;
    }

    /**
     * 该版本是否有可探测的结构就绪条件
     *
     * @param string $version
     * @return bool
     */
    public static function hasSchemaProbe($version)
    {
        $version = trim((string) $version);
        return ($version === '7.0.0' || $version === '7.1.0');
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
            $column = trim((string) $column);
            if ($column === '') {
                return false;
            }

            // 优先 INFORMATION_SCHEMA（SHOW COLUMNS + 预处理 LIKE 在部分 MySQL 上会误判）
            try {
                $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
                if ($dbName !== '') {
                    $stmt = $pdo->prepare(
                        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                         LIMIT 1'
                    );
                    $stmt->execute(array($dbName, $table, $column));
                    if ($stmt->fetch(PDO::FETCH_NUM)) {
                        return true;
                    }
                    // INFORMATION_SCHEMA 未命中时仍用 SHOW 兜底（权限/延迟）
                }
            } catch (Exception $e) {
                // fall through
            }

            $sql = 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`';
            $stmt = $pdo->query($sql);
            if (!$stmt) {
                return false;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['Field']) && strcasecmp((string) $row['Field'], $column) === 0) {
                    return true;
                }
            }
            return false;
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

        if ($version === '6.0.1') {
            self::backfillOrphanAdminApis($pdo, $prefix);
            return;
        }

        if ($version === '7.0.0') {
            self::applyContentTable($pdo, $prefix);
            return;
        }

        if ($version === '7.1.0') {
            self::applyContentCoverLayoutColumn($pdo, $prefix);
            return;
        }

        $sql = file_get_contents($file);
        $sql = str_replace('{prefix}', $prefix, $sql);
        self::assertPrefixedTables($sql, $prefix, basename($file));
        $statements = DatabaseInstaller::parseSqlStatements($sql);
        if (count($statements) === 0) {
            throw new Exception('结构更新脚本无有效 SQL：' . basename($file));
        }

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
     * v6.0.1：历史管理员发布 userid=0 回填到全站唯一绑定身份
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function backfillOrphanAdminApis(PDO $pdo, $prefix)
    {
        $adminTable = $prefix . 'admin';
        $apiTable = $prefix . 'api';

        $stmt = $pdo->query(
            'SELECT DISTINCT `binduid` FROM `' . $adminTable . '`'
            . ' WHERE `binduid` IS NOT NULL AND `binduid` > 0 AND `status` = 1'
        );
        $uids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : array();
        if (!is_array($uids) || count($uids) !== 1) {
            return;
        }

        $uid = (int) $uids[0];
        if ($uid <= 0) {
            return;
        }

        $upd = $pdo->prepare(
            'UPDATE `' . $apiTable . '` SET `userid` = ? WHERE `userid` = 0'
        );
        $upd->execute(array($uid));
    }

    /**
     * v7.0.0：content 共用表（幂等）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyContentTable(PDO $pdo, $prefix)
    {
        if (self::tableExists('content')) {
            return;
        }
        $table = $prefix . 'content';
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT \'主键ID\',
              `kind` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'类型：0公告 1文章\',
              `title` varchar(200) NOT NULL DEFAULT \'\' COMMENT \'标题\',
              `summary` varchar(500) NOT NULL DEFAULT \'\' COMMENT \'摘要\',
              `body` mediumtext NOT NULL COMMENT \'正文Markdown\',
              `cover` varchar(500) NOT NULL DEFAULT \'\' COMMENT \'封面图链接（文章用，公告可空）\',
              `ispinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否置顶：0否 1是\',
              `ispopup` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否弹窗：0否 1是（公告）\',
              `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'状态：0草稿 1已发布 2下架\',
              `userid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT \'发布者用户ID\',
              `views` int(10) unsigned NOT NULL DEFAULT 0 COMMENT \'阅读量\',
              `sort` int(11) NOT NULL DEFAULT 0 COMMENT \'排序权重（越小越前）\',
              `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
              `updatetime` datetime DEFAULT NULL COMMENT \'最后更新时间\',
              PRIMARY KEY (`id`),
              KEY `idx_kind_status_id` (`kind`, `status`, `id`),
              KEY `idx_kind_pin_id` (`kind`, `ispinned`, `id`),
              KEY `idx_kind_popup` (`kind`, `ispopup`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'公告与文章共用内容表\''
        );
    }

    /**
     * v7.1.0：content.coverlayout（幂等）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyContentCoverLayoutColumn(PDO $pdo, $prefix)
    {
        if (self::tableColumnExists('content', 'coverlayout')) {
            return;
        }
        if (!self::tableExists('content')) {
            throw new Exception('content 表不存在，请先完成 7.0.0 结构更新');
        }
        $table = $prefix . 'content';
        $pdo->exec(
            'ALTER TABLE `' . $table . '` ADD COLUMN `coverlayout` tinyint(1) NOT NULL DEFAULT 0 '
            . 'COMMENT \'封面布局：0左侧 1右侧 2背景（仅文章）\' AFTER `cover`'
        );
    }

    /**
     * 指定版本要求的库结构是否已就绪
     *
     * @param string $version
     * @return bool
     */
    public static function versionSchemaReady($version)
    {
        $version = trim((string) $version);
        if ($version === '7.1.0') {
            return self::tableExists('content') && self::tableColumnExists('content', 'coverlayout');
        }
        if ($version === '7.0.0') {
            return self::tableExists('content');
        }
        $file = self::migrationsDir() . '/' . $version . '.sql';
        if (!is_file($file)) {
            return true;
        }
        return in_array($version, self::getAppliedVersions(), true);
    }

    /**
     * 确保目标版本结构已落地（幂等；在线更新 migrate 步强制对齐）
     *
     * @param string $version
     * @return array{applied:bool,msg:string}
     */
    public static function ensureVersionSchema($version)
    {
        $version = trim((string) $version);
        if ($version === '') {
            return array('applied' => false, 'msg' => '');
        }

        if (self::versionSchemaReady($version)) {
            self::markApplied($version);
            return array('applied' => false, 'msg' => '结构已就绪');
        }

        $file = self::migrationsDir() . '/' . $version . '.sql';
        if (!is_file($file)) {
            throw new Exception(
                '版本 v' . $version . ' 标记含数据库变更，但缺少 install/migrations/'
                . $version . '.sql，请重新下载更新包'
            );
        }

        $pdo = Database::connect();
        $prefix = Database::prefix();
        self::executeFile($pdo, $file, $prefix);
        self::markApplied($version);
        Config::clearCache();

        if (!self::versionSchemaReady($version)) {
            throw new Exception('版本 v' . $version . ' 结构更新后校验失败，请手动执行数据库结构更新');
        }

        return array('applied' => true, 'msg' => '已执行 ' . $version);
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
