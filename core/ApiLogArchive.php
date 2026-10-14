<?php
/**
 * 文件：core/ApiLogArchive.php
 * 作用：调用日志冷热分层——热数据留 MySQL；冷数据三层索引 + SQLite 分片（多读少写）
 *
 * 对外由 core/api/apilogarchive.php 按 action 执行（须系统密钥 + sysapion.apilogarchive）：
 *   archive — 写入本机冷库后从在线库删除（不可逆）
 *   purge   — 不写冷库，只删在线库过期行
 *   mirror  — 写入本机冷库，在线库保留（mirror_state 增量水位，避免重复堆分片）
 *
 * 目录结构（三层分离）：
 *   data/apilog/catalog/catalog.json     ← 总索引（日期 / ID 段 → 哪一天）
 *   data/apilog/days/YYYY-MM-DD/index.json ← 日索引（ID 段 → 哪个 .db）
 *   data/apilog/shards/YYYY-MM-DD/s0001.db ← 日志正文（SQLite；每片条数见后台「每个分片条数」，默认 5000）
 *   data/apilog/catalog/mirror_state.json ← mirror 增量水位（last_id）
 */

class ApiLogArchive
{
    const DEFAULT_HOT_DAYS = 30;
    const MAX_HOT_DAYS = 365;
    /** 每个 SQLite 分片默认条数（可配置） */
    const DEFAULT_SHARD_ROWS = 5000;
    const MIN_SHARD_ROWS = 100;
    const MAX_SHARD_ROWS = 50000;
    const LOCK_TTL = 1800;
    /** v3：catalog 日元数据可带 apiids 剪枝集合 */
    const CATALOG_VERSION = 3;

    /** 单日 catalog 最多记录多少个 apiid（防膨胀；超出仍可查分片，仅失去剪枝） */
    const MAX_DAY_APIIDS = 2000;

    /**
     * @return string
     */
    public static function rootDir()
    {
        return rtrim(str_replace('\\', '/', VS_ROOT), '/') . '/data/apilog';
    }

    /**
     * @return string
     */
    public static function catalogPath()
    {
        return self::rootDir() . '/catalog/catalog.json';
    }

    /**
     * @param string $day
     * @return string
     */
    public static function dayIndexPath($day)
    {
        $day = self::safeDay($day);
        return self::rootDir() . '/days/' . $day . '/index.json';
    }

    /**
     * @param string $day
     * @return string
     */
    public static function shardDir($day)
    {
        $day = self::safeDay($day);
        return self::rootDir() . '/shards/' . $day;
    }

    /**
     * 是否启用「调用日志清理 API」（与卡密对接同理：仅有系统密钥不够）
     *
     * @return bool
     */
    public static function isApiEnabled()
    {
        return class_exists('SystemApiKey') && SystemApiKey::isGateOn('apilogarchive');
    }

    /**
     * @deprecated 模式改由 API action 指定；保留读旧配置供迁移探测
     * @return bool
     */
    public static function isEnabled()
    {
        return self::isApiEnabled();
    }

    /**
     * @deprecated
     * @return bool
     */
    public static function isArchiveEnabled()
    {
        try {
            return trim((string) Config::get('apilog_archive_enabled', '0')) === '1';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @deprecated
     * @return bool
     */
    public static function isPurgeEnabled()
    {
        try {
            return trim((string) Config::get('apilog_purge_enabled', '0')) === '1';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @deprecated 请用 API action=archive|purge|mirror
     * @return string
     */
    public static function cleanupMode()
    {
        return 'off';
    }

    /**
     * mirror 增量水位文件
     *
     * @return string
     */
    public static function mirrorStatePath()
    {
        return self::rootDir() . '/catalog/mirror_state.json';
    }

    /**
     * @return int
     */
    public static function mirrorLastId()
    {
        $data = self::readJson(self::mirrorStatePath());
        if (!is_array($data) || !isset($data['last_id'])) {
            return 0;
        }
        return max(0, (int) $data['last_id']);
    }

    /**
     * @param int $lastId
     * @return void
     */
    public static function setMirrorLastId($lastId)
    {
        $lastId = max(0, (int) $lastId);
        self::ensureStorage();
        self::writeJson(self::mirrorStatePath(), array(
            'last_id' => $lastId,
            'updated' => date('c'),
        ));
    }

    /**
     * @return bool
     */
    public static function sqliteAvailable()
    {
        return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    }

    /**
     * 每个冷库分片写入条数（可配置，默认 5000）
     *
     * @return int
     */
    public static function shardRows()
    {
        try {
            $n = (int) Config::get('apilog_shard_rows', (string) self::DEFAULT_SHARD_ROWS);
        } catch (Exception $e) {
            $n = self::DEFAULT_SHARD_ROWS;
        }
        if ($n < self::MIN_SHARD_ROWS) {
            $n = self::DEFAULT_SHARD_ROWS;
        }
        if ($n > self::MAX_SHARD_ROWS) {
            $n = self::MAX_SHARD_ROWS;
        }
        return $n;
    }

    /**
     * @param int $n
     * @return int
     */
    public static function clampShardRows($n)
    {
        $n = (int) $n;
        if ($n < self::MIN_SHARD_ROWS) {
            $n = self::MIN_SHARD_ROWS;
        }
        if ($n > self::MAX_SHARD_ROWS) {
            $n = self::MAX_SHARD_ROWS;
        }
        return $n;
    }

    /**
     * MySQL 热数据天数
     *
     * @return int
     */
    public static function hotDays()
    {
        try {
            $n = (int) Config::get('apilog_hot_days', (string) self::DEFAULT_HOT_DAYS);
        } catch (Exception $e) {
            $n = self::DEFAULT_HOT_DAYS;
        }
        if ($n < 1) {
            $n = self::DEFAULT_HOT_DAYS;
        }
        if ($n > self::MAX_HOT_DAYS) {
            $n = self::MAX_HOT_DAYS;
        }
        return $n;
    }

    /**
     * @return string
     */
    public static function cronKey()
    {
        return SystemApiKey::get();
    }

    /**
     * @return string
     */
    public static function generateCronKey()
    {
        return SystemApiKey::generate();
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function validateCronKey($key)
    {
        return SystemApiKey::validate($key);
    }

    /**
     * 计划任务 URL（永不附带密钥；须用 Header Bearer / X-Api-Key，E412）
     *
     * @param bool $withKey 已废弃：忽略，禁止拼 ?key=
     * @return string
     */
    public static function cronUrl($withKey = false)
    {
        unset($withKey);
        return rtrim(vs_base_url(), '/') . '/core/api/apilogarchive';
    }

    /**
     * @return bool
     */
    public static function ensureStorage()
    {
        $root = self::rootDir();
        $dirs = array(
            $root,
            $root . '/catalog',
            $root . '/days',
            $root . '/shards',
        );
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }
        $ht = $root . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
        $idx = $root . '/index.html';
        if (!is_file($idx)) {
            @file_put_contents($idx, '');
        }
        if (!is_file(self::catalogPath())) {
            self::writeJson(self::catalogPath(), array(
                'version' => self::CATALOG_VERSION,
                'format'  => 'sqlite',
                'updated' => date('c'),
                'days'    => array(),
            ));
        }
        return is_dir($root) && is_writable($root);
    }

    /**
     * 按 API action 执行（archive / purge / mirror）
     *
     * @param string $action
     * @param int    $maxRounds
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int,mode?:string}
     */
    public static function runAction($action, $maxRounds = 50)
    {
        $action = strtolower(trim((string) $action));
        if ($action === 'purge') {
            $result = self::runPurge($maxRounds);
            $result['mode'] = 'purge';
            return $result;
        }
        if ($action === 'mirror') {
            $result = self::run($maxRounds, false);
            $result['mode'] = 'mirror';
            return $result;
        }
        if ($action === 'archive') {
            $result = self::run($maxRounds, true);
            $result['mode'] = 'archive';
            return $result;
        }
        return array(
            'ok'       => false,
            'msg'      => '缺少或错误的 action（须为 archive / purge / mirror）',
            'archived' => 0,
            'days'     => array(),
            'deleted'  => 0,
            'mode'     => '',
        );
    }

    /**
     * @deprecated 请传 action=；无参调用将失败
     * @param int $maxRounds
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int,mode?:string}
     */
    public static function runScheduled($maxRounds = 50)
    {
        return self::runAction('', $maxRounds);
    }

    /**
     * 过期日志直接删除（不写冷库）
     *
     * @param int $maxRounds
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function runPurge($maxRounds = 50)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            $empty['msg'] = '日志表未就绪';
            return $empty;
        }
        if (!self::acquireLock()) {
            $empty['msg'] = '已有日志清理任务在执行';
            return $empty;
        }

        $maxRounds = max(1, min(200, (int) $maxRounds));
        $batch = self::shardRows();
        $hotDays = self::hotDays();
        $deleted = 0;
        $lastMsg = '';

        try {
            $pdo = Database::connect();
            $table = Database::table('apilog');
            for ($i = 0; $i < $maxRounds; $i++) {
                $sql = 'SELECT `id` FROM `' . $table . '`
                     WHERE `createtime` < DATE_SUB(NOW(), INTERVAL ' . (int) $hotDays . ' DAY)
                     ORDER BY `id` ASC
                     LIMIT ' . (int) $batch;
                $stmt = $pdo->query($sql);
                $ids = array();
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $ids[] = (int) $row['id'];
                    }
                }
                if ($ids === array()) {
                    $lastMsg = $deleted > 0 ? '删除完成' : '没有超过保留天数的日志';
                    break;
                }
                $n = self::deleteIds($pdo, $table, $ids);
                $deleted += $n;
                $lastMsg = '已删除 ' . $deleted . ' 条';
                if ($n <= 0) {
                    return array(
                        'ok'       => false,
                        'msg'      => '删除失败，请稍后重试',
                        'archived' => 0,
                        'days'     => array(),
                        'deleted'  => $deleted,
                    );
                }
            }
        } catch (Exception $e) {
            return array(
                'ok'       => false,
                'msg'      => '删除失败，请稍后重试',
                'archived' => 0,
                'days'     => array(),
                'deleted'  => $deleted,
            );
        } finally {
            self::releaseLock();
        }

        if ($deleted > 0 && class_exists('RedisCache')) {
            RedisCache::invalidateApiLog();
        }

        return array(
            'ok'       => true,
            'msg'      => $deleted > 0 ? '删除完成' : $lastMsg,
            'archived' => 0,
            'days'     => array(),
            'deleted'  => $deleted,
        );
    }

    /**
     * 写入冷库；$deleteOnline=true 时再删在线库（archive），false 为 mirror（保留在线库）
     *
     * @param int  $maxRounds
     * @param bool $deleteOnline
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function run($maxRounds = 50, $deleteOnline = true)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
        $deleteOnline = (bool) $deleteOnline;
        if (!self::sqliteAvailable()) {
            $empty['msg'] = '服务器未启用 PDO SQLite，无法写入冷库';
            return $empty;
        }
        if (!self::ensureStorage()) {
            $empty['msg'] = '归档目录不可写';
            return $empty;
        }
        if (!self::acquireLock()) {
            $empty['msg'] = '已有归档任务在执行';
            return $empty;
        }

        $maxRounds = max(1, min(200, (int) $maxRounds));
        $archived = 0;
        $deleted = 0;
        $days = array();
        $lastMsg = '';

        try {
            for ($i = 0; $i < $maxRounds; $i++) {
                $result = self::runOnce(null, true, $deleteOnline);
                if (empty($result['ok'])) {
                    $result['archived'] = $archived + (isset($result['archived']) ? (int) $result['archived'] : 0);
                    $result['deleted'] = $deleted + (isset($result['deleted']) ? (int) $result['deleted'] : 0);
                    if (!empty($days) || !empty($result['days'])) {
                        $result['days'] = array_values(array_unique(array_merge(
                            $days,
                            isset($result['days']) && is_array($result['days']) ? $result['days'] : array()
                        )));
                    }
                    return $result;
                }

                $batchArchived = isset($result['archived']) ? (int) $result['archived'] : 0;
                $batchDeleted = isset($result['deleted']) ? (int) $result['deleted'] : 0;
                $archived += $batchArchived;
                $deleted += $batchDeleted;
                $lastMsg = isset($result['msg']) ? (string) $result['msg'] : '';
                if (!empty($result['days']) && is_array($result['days'])) {
                    foreach ($result['days'] as $d) {
                        $days[] = (string) $d;
                    }
                }
                if ($batchArchived <= 0) {
                    break;
                }
            }
        } finally {
            self::releaseLock();
        }

        if ($archived > 0 && $deleteOnline && class_exists('RedisCache')) {
            RedisCache::invalidateApiLog();
        }

        $doneMsg = $deleteOnline ? '归档完成' : '本地缓存完成（在线库未删除）';
        return array(
            'ok'       => true,
            'msg'      => $archived > 0 ? $doneMsg : $lastMsg,
            'archived' => $archived,
            'days'     => array_values(array_unique($days)),
            'deleted'  => $deleted,
        );
    }

    /**
     * 单批写入冷库；可选再删在线库
     *
     * @param int|null $limit
     * @param bool     $alreadyLocked 多轮 run() 时首轮已持锁则传 true
     * @param bool     $deleteOnline  true=archive 删库；false=mirror 保留库
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function runOnce($limit = null, $alreadyLocked = false, $deleteOnline = true)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
        $deleteOnline = (bool) $deleteOnline;
        if (!self::sqliteAvailable()) {
            $empty['msg'] = '服务器未启用 PDO SQLite，无法写入冷库';
            return $empty;
        }
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            $empty['msg'] = '日志表未就绪';
            return $empty;
        }
        if (!self::ensureStorage()) {
            $empty['msg'] = '归档目录不可写';
            return $empty;
        }
        if (!$alreadyLocked && !self::acquireLock()) {
            $empty['msg'] = '已有归档任务在执行';
            return $empty;
        }

        $limit = $limit === null ? self::shardRows() : self::clampShardRows((int) $limit);
        $hotDays = self::hotDays();
        $archived = 0;
        $deleted = 0;
        $dayTouched = array();
        $release = !$alreadyLocked;
        $mirrorAfterId = $deleteOnline ? 0 : self::mirrorLastId();

        try {
            $pdo = Database::connect();
            $table = Database::table('apilog');
            // INTERVAL 天数内联为整数，避免部分环境下占位符绑定异常
            $sql = 'SELECT * FROM `' . $table . '`
                 WHERE `createtime` < DATE_SUB(NOW(), INTERVAL ' . (int) $hotDays . ' DAY)';
            if (!$deleteOnline) {
                $sql .= ' AND `id` > ' . (int) $mirrorAfterId;
            }
            $sql .= ' ORDER BY `id` ASC
                 LIMIT ' . (int) $limit;
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
            if (empty($rows)) {
                if ($release) {
                    self::releaseLock();
                }
                return array(
                    'ok'       => true,
                    'msg'      => $deleteOnline ? '没有需要归档的冷数据' : '没有需要缓存到本地的新日志',
                    'archived' => 0,
                    'days'     => array(),
                    'deleted'  => 0,
                );
            }

            $byDay = array();
            $maxId = 0;
            foreach ($rows as $row) {
                $day = self::rowDay($row);
                if ($day === '') {
                    continue;
                }
                if (!isset($byDay[$day])) {
                    $byDay[$day] = array();
                }
                $byDay[$day][] = $row;
                $rid = isset($row['id']) ? (int) $row['id'] : 0;
                if ($rid > $maxId) {
                    $maxId = $rid;
                }
            }

            // 按日写入冷库；archive 写成功后删在线库，mirror 只推进水位
            foreach ($byDay as $day => $dayRows) {
                $writtenIds = self::appendDayRows($day, $dayRows);
                $n = count($writtenIds);
                if ($n <= 0) {
                    throw new Exception('冷库写入失败：' . $day);
                }
                $archived += $n;
                $dayTouched[] = $day;
                if ($deleteOnline) {
                    $deleted += self::deleteIdsVerified($pdo, $table, $writtenIds);
                }
            }
            if (!$deleteOnline && $maxId > 0) {
                self::setMirrorLastId($maxId);
            }

            if ($archived > 0 && $deleteOnline && class_exists('RedisCache') && $release) {
                RedisCache::invalidateApiLog();
            }

            self::touchCatalogMeta();
            if ($release) {
                self::releaseLock();
            }

            return array(
                'ok'       => true,
                'msg'      => $deleteOnline ? '归档完成' : '本地缓存完成（在线库未删除）',
                'archived' => $archived,
                'days'     => $dayTouched,
                'deleted'  => $deleted,
            );
        } catch (Exception $e) {
            if ($release) {
                self::releaseLock();
            }
            if (function_exists('error_log')) {
                error_log('ApiLogArchive: ' . $e->getMessage());
            }
            $empty['msg'] = '归档失败，请稍后重试或查看服务器日志';
            $empty['archived'] = $archived;
            $empty['deleted'] = $deleted;
            $empty['days'] = $dayTouched;
            return $empty;
        }
    }

    /**
     * @param int $days
     * @return int
     */
    /**
     * 规范化 YYYY-MM-DD；非法返回空串
     *
     * @param string $day
     * @return string
     */
    public static function normalizeDay($day)
    {
        $day = self::safeDay($day);
        if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return '';
        }
        $ts = strtotime($day . ' 00:00:00');
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }

    /**
     * catalog 条数估算（可按日期 / apiid 剪枝；非精确 COUNT）
     *
     * @param int|array $daysOrOpts 兼容旧调用：int=近 N 天；array 见 listInQueryWindow
     * @return int
     */
    public static function countInQueryWindow($daysOrOpts)
    {
        $opts = is_array($daysOrOpts) ? $daysOrOpts : array('days' => (int) $daysOrOpts);
        $catalog = self::readCatalog();
        if (empty($catalog['days']) || !is_array($catalog['days'])) {
            return 0;
        }
        $range = self::resolveDayRange($opts);
        $pruneApiids = self::normalizeApiidList(isset($opts['prune_apiids']) ? $opts['prune_apiids'] : array());
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;
        if ($apiid > 0) {
            $pruneApiids[] = $apiid;
            $pruneApiids = array_values(array_unique($pruneApiids));
        }
        $total = 0;
        foreach ($catalog['days'] as $day => $meta) {
            if (!is_string($day) || !self::dayInRange($day, $range['from'], $range['to'])) {
                continue;
            }
            if ($pruneApiids !== array() && !self::dayMayContainApiids($meta, $pruneApiids)) {
                continue;
            }
            $total += isset($meta['count']) ? (int) $meta['count'] : 0;
        }
        return $total;
    }

    /**
     * 冷库查询窗内最大日志 ID（catalog 日 max_id；供页码跳转估算 before_id）
     *
     * @param array $opts 同 countInQueryWindow
     * @return int
     */
    public static function maxIdInQueryWindow(array $opts)
    {
        $catalog = self::readCatalog();
        if (empty($catalog['days']) || !is_array($catalog['days'])) {
            return 0;
        }
        $range = self::resolveDayRange($opts);
        $pruneApiids = self::normalizeApiidList(isset($opts['prune_apiids']) ? $opts['prune_apiids'] : array());
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;
        if ($apiid > 0) {
            $pruneApiids[] = $apiid;
            $pruneApiids = array_values(array_unique($pruneApiids));
        }
        $maxId = 0;
        foreach ($catalog['days'] as $day => $meta) {
            if (!is_string($day) || !self::dayInRange($day, $range['from'], $range['to'])) {
                continue;
            }
            if ($pruneApiids !== array() && !self::dayMayContainApiids($meta, $pruneApiids)) {
                continue;
            }
            $dayMax = isset($meta['max_id']) ? (int) $meta['max_id'] : 0;
            if ($dayMax > $maxId) {
                $maxId = $dayMax;
            }
        }
        return $maxId;
    }

    /**
     * @param array $opts days?, date_from?, date_to?, before_id, pagesize, q, q_field, ok, apiid, user_ids, prune_apiids?
     * @return array{list:array,has_more:bool,next_before_id:int}
     */
    public static function listInQueryWindow(array $opts)
    {
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $qField = 'id';
        if (class_exists('ApiLogManager')) {
            $qField = ApiLogManager::normalizeQField(isset($opts['q_field']) ? $opts['q_field'] : 'id');
        } else {
            $qf = strtolower(trim((string) (isset($opts['q_field']) ? $opts['q_field'] : 'id')));
            $allow = array('id', 'apiid', 'username', 'apiname', 'ip', 'apikey');
            $qField = in_array($qf, $allow, true) ? $qf : 'id';
        }
        // 用户名搜索：冷库无 username 列，只按已解析的 userid 过滤
        if ($qField === 'username') {
            $q = '';
        }
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;
        $userIds = array();
        if (!empty($opts['user_ids']) && is_array($opts['user_ids'])) {
            foreach ($opts['user_ids'] as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $userIds[] = $uid;
                }
            }
            $userIds = array_values(array_unique($userIds));
        }
        $pruneApiids = self::normalizeApiidList(isset($opts['prune_apiids']) ? $opts['prune_apiids'] : array());
        if ($apiid > 0) {
            $pruneApiids[] = $apiid;
            $pruneApiids = array_values(array_unique($pruneApiids));
        }
        // 按名称已解析出 apiid 时：冷库改走 apiid IN（走索引），不再 LIKE 扫名
        $nameApiids = self::normalizeApiidList(isset($opts['name_apiids']) ? $opts['name_apiids'] : array());
        if ($qField === 'apiname' && $nameApiids !== array()) {
            $pruneApiids = $nameApiids;
            $q = '';
            $qField = 'id';
            if ($apiid <= 0 && count($nameApiids) === 1) {
                $apiid = (int) $nameApiids[0];
            }
        }

        // 按日志编号精确查：catalog 日 ID 段剪枝（千万级/日时只打开含该 id 的日）
        $targetLogId = 0;
        if ($qField === 'id' && $q !== '') {
            $targetLogId = self::parseLogIdToken($q);
        }

        $range = self::resolveDayRange($opts);
        $catalog = self::readCatalog();
        $dayKeys = array();
        if (!empty($catalog['days']) && is_array($catalog['days'])) {
            foreach ($catalog['days'] as $day => $meta) {
                if (!is_string($day) || !self::dayInRange($day, $range['from'], $range['to'])) {
                    continue;
                }
                $min = isset($meta['min_id']) ? (int) $meta['min_id'] : 0;
                $max = isset($meta['max_id']) ? (int) $meta['max_id'] : 0;
                if ($beforeId > 0 && $min >= $beforeId) {
                    continue;
                }
                if ($targetLogId > 0 && $min > 0 && $max > 0
                    && ($targetLogId < $min || $targetLogId > $max)
                ) {
                    continue;
                }
                if ($pruneApiids !== array() && !self::dayMayContainApiids($meta, $pruneApiids)) {
                    continue;
                }
                $dayKeys[] = $day;
            }
        }
        rsort($dayKeys);

        $need = $pagesize + 1;
        $out = array();
        foreach ($dayKeys as $day) {
            if (count($out) >= $need) {
                break;
            }
            $chunk = self::readDayFiltered(
                $day,
                $beforeId,
                $need - count($out),
                $q,
                $qField,
                $ok,
                $apiid,
                $userIds,
                $nameApiids,
                $targetLogId
            );
            foreach ($chunk as $row) {
                $row['_storage'] = 'cold';
                $out[] = $row;
                if (count($out) >= $need) {
                    break;
                }
            }
        }

        $hasMore = count($out) > $pagesize;
        if ($hasMore) {
            $out = array_slice($out, 0, $pagesize);
        }
        $nextBefore = 0;
        if (!empty($out)) {
            $last = $out[count($out) - 1];
            $nextBefore = isset($last['id']) ? (int) $last['id'] : 0;
        }

        return array(
            'list'           => $out,
            'has_more'       => $hasMore,
            'next_before_id' => $nextBefore,
        );
    }

    /**
     * @param array $opts
     * @return array{from:?string,to:string}
     */
    private static function resolveDayRange(array $opts)
    {
        $to = self::normalizeDay(isset($opts['date_to']) ? $opts['date_to'] : '');
        $from = self::normalizeDay(isset($opts['date_from']) ? $opts['date_from'] : '');
        if ($to === '') {
            $to = date('Y-m-d');
        }
        $days = (int) (isset($opts['days']) ? $opts['days'] : 0);
        if ($from === '' && $days > 0) {
            $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        }
        if ($from !== '' && $from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }
        return array('from' => ($from !== '' ? $from : null), 'to' => $to);
    }

    /**
     * @param string      $day
     * @param string|null $from
     * @param string      $to
     * @return bool
     */
    private static function dayInRange($day, $from, $to)
    {
        if ($day > $to) {
            return false;
        }
        if ($from !== null && $day < $from) {
            return false;
        }
        return true;
    }

    /**
     * @param mixed $list
     * @return int[]
     */
    private static function normalizeApiidList($list)
    {
        $out = array();
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $aid) {
            $aid = (int) $aid;
            if ($aid > 0) {
                $out[$aid] = $aid;
            }
        }
        return array_values($out);
    }

    /**
     * catalog 日元数据是否可能含目标 apiid；无 apiids 字段则不剪枝（兼容旧归档）
     *
     * @param array $meta
     * @param int[] $apiids
     * @return bool
     */
    private static function dayMayContainApiids(array $meta, array $apiids)
    {
        if ($apiids === array()) {
            return true;
        }
        if (empty($meta['apiids']) || !is_array($meta['apiids'])) {
            return true;
        }
        $set = array();
        foreach ($meta['apiids'] as $aid) {
            $set[(int) $aid] = true;
        }
        foreach ($apiids as $aid) {
            if (isset($set[(int) $aid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function findById($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::ensureStorage()) {
            return null;
        }
        $catalog = self::readCatalog();
        if (empty($catalog['days']) || !is_array($catalog['days'])) {
            return null;
        }
        // 先按 catalog 日 ID 段剪枝，再只打开命中日的分片（秒级定位，避免扫全库）
        $candidates = array();
        foreach ($catalog['days'] as $day => $meta) {
            if (!is_string($day)) {
                continue;
            }
            $min = isset($meta['min_id']) ? (int) $meta['min_id'] : 0;
            $max = isset($meta['max_id']) ? (int) $meta['max_id'] : 0;
            if ($min > 0 && $max > 0 && ($id < $min || $id > $max)) {
                continue;
            }
            $candidates[] = $day;
        }
        // 新日优先（多数回查最近归档）
        rsort($candidates);
        foreach ($candidates as $day) {
            $row = self::findInDay($day, $id);
            if ($row !== null) {
                $row['_storage'] = 'cold';
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public static function readCatalog()
    {
        self::ensureStorage();
        $data = self::readJson(self::catalogPath());
        if (!is_array($data)) {
            return array('version' => self::CATALOG_VERSION, 'format' => 'sqlite', 'days' => array());
        }
        if (!isset($data['days']) || !is_array($data['days'])) {
            $data['days'] = array();
        }
        return $data;
    }

    /**
     * @param string $day
     * @return string
     */
    private static function safeDay($day)
    {
        $day = preg_replace('/[^0-9\-]/', '', (string) $day);
        return $day;
    }

    /**
     * @param array $row
     * @return string
     */
    private static function rowDay(array $row)
    {
        $t = isset($row['createtime']) ? (string) $row['createtime'] : '';
        if ($t === '') {
            return '';
        }
        $ts = strtotime($t);
        return $ts ? date('Y-m-d', $ts) : substr($t, 0, 10);
    }

    /**
     * @param string $day
     * @param array  $rows
     * @return int[]
     */
    private static function appendDayRows($day, array $rows)
    {
        $day = self::safeDay($day);
        if ($day === '' || empty($rows)) {
            return array();
        }

        $dayDir = self::rootDir() . '/days/' . $day;
        $shardDir = self::shardDir($day);
        if (!is_dir($dayDir) && !@mkdir($dayDir, 0755, true) && !is_dir($dayDir)) {
            return array();
        }
        if (!is_dir($shardDir) && !@mkdir($shardDir, 0755, true) && !is_dir($shardDir)) {
            return array();
        }

        $indexPath = self::dayIndexPath($day);
        $index = self::readJson($indexPath);
        if (!is_array($index)) {
            $index = array('day' => $day, 'shards' => array());
        }
        if (!isset($index['shards']) || !is_array($index['shards'])) {
            $index['shards'] = array();
        }

        $writtenIds = array();
        $buffer = array();
        $dayApiids = array();
        if (!empty($index['apiids']) && is_array($index['apiids'])) {
            foreach ($index['apiids'] as $aid) {
                $aid = (int) $aid;
                if ($aid > 0) {
                    $dayApiids[$aid] = true;
                }
            }
        }

        $flush = function () use (&$buffer, &$index, &$writtenIds, $shardDir) {
            if (empty($buffer)) {
                return;
            }
            $seq = count($index['shards']) + 1;
            $file = 's' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT) . '.db';
            $path = $shardDir . '/' . $file;
            $ids = self::writeSqliteShard($path, $buffer, true);
            $buffer = array();
            if (empty($ids)) {
                return;
            }
            $index['shards'][] = array(
                'file'   => $file,
                'min_id' => min($ids),
                'max_id' => max($ids),
                'count'  => count($ids),
            );
            foreach ($ids as $wid) {
                $writtenIds[] = (int) $wid;
            }
        };

        foreach ($rows as $row) {
            $pack = self::packRow($row);
            if ($pack === null) {
                continue;
            }
            $aid = isset($pack['apiid']) ? (int) $pack['apiid'] : 0;
            if ($aid > 0 && count($dayApiids) < self::MAX_DAY_APIIDS) {
                $dayApiids[$aid] = true;
            }
            $buffer[] = $pack;
            if (count($buffer) >= self::shardRows()) {
                $flush();
            }
        }
        $flush();

        if (empty($writtenIds)) {
            return array();
        }

        $index['day'] = $day;
        $index['updated'] = date('c');
        $minId = 0;
        $maxId = 0;
        $count = 0;
        foreach ($index['shards'] as $sh) {
            $count += isset($sh['count']) ? (int) $sh['count'] : 0;
            $a = isset($sh['min_id']) ? (int) $sh['min_id'] : 0;
            $b = isset($sh['max_id']) ? (int) $sh['max_id'] : 0;
            if ($minId === 0 || ($a > 0 && $a < $minId)) {
                $minId = $a;
            }
            if ($b > $maxId) {
                $maxId = $b;
            }
        }
        $index['min_id'] = $minId;
        $index['max_id'] = $maxId;
        $index['count'] = $count;
        $apiidList = array_keys($dayApiids);
        sort($apiidList, SORT_NUMERIC);
        $index['apiids'] = $apiidList;
        self::writeJson($indexPath, $index);

        $catalog = self::readCatalog();
        // 合并旧 catalog 同日 apiids（重复归档/补写）
        $prevApiids = array();
        if (!empty($catalog['days'][$day]['apiids']) && is_array($catalog['days'][$day]['apiids'])) {
            foreach ($catalog['days'][$day]['apiids'] as $paid) {
                $paid = (int) $paid;
                if ($paid > 0) {
                    $prevApiids[$paid] = true;
                }
            }
        }
        foreach ($apiidList as $aid) {
            $prevApiids[(int) $aid] = true;
        }
        $mergedApiids = array_keys($prevApiids);
        sort($mergedApiids, SORT_NUMERIC);
        if (count($mergedApiids) > self::MAX_DAY_APIIDS) {
            $mergedApiids = array_slice($mergedApiids, 0, self::MAX_DAY_APIIDS);
        }
        $catalog['version'] = self::CATALOG_VERSION;
        $catalog['format'] = 'sqlite';
        $catalog['days'][$day] = array(
            'min_id' => $minId,
            'max_id' => $maxId,
            'count'  => $count,
            'shards' => count($index['shards']),
            'apiids' => $mergedApiids,
        );
        $catalog['updated'] = date('c');
        self::writeJson(self::catalogPath(), $catalog);

        return $writtenIds;
    }

    /**
     * @param string $path
     * @param array  $rows
     * @param bool   $create
     * @return int[]
     */
    private static function writeSqliteShard($path, array $rows, $create)
    {
        if (empty($rows)) {
            return array();
        }
        if (strpos($path, '..') !== false) {
            return array();
        }
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($create || !self::sqliteTableReady($pdo)) {
                self::initSqliteSchema($pdo);
            }
            // 新装与旧分片均幂等补列（create=true 撞到旧库时也必须 ALTER）
            self::ensureSqliteEgressColumn($pdo);
            if (!self::ensureSqliteExtendedColumns($pdo)) {
                throw new Exception('冷库分片缺少转发头列 xfproto/xff/xri');
            }
            self::ensureSqliteSearchIndexes($pdo);
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->beginTransaction();
            $sql = 'INSERT OR REPLACE INTO `log` (
                `id`,`apiid`,`apiname`,`apitype`,`userid`,`apikey`,`method`,`ip`,`iploc`,`egress`,
                `host`,`path`,`url`,`referer`,`origin`,`domain`,`ua`,`ok`,`httpcode`,`charged`,`cost`,
                `xfproto`,`xff`,`xri`,`createtime`
            ) VALUES (
                :id,:apiid,:apiname,:apitype,:userid,:apikey,:method,:ip,:iploc,:egress,
                :host,:path,:url,:referer,:origin,:domain,:ua,:ok,:httpcode,:charged,:cost,
                :xfproto,:xff,:xri,:createtime
            )';
            $stmt = $pdo->prepare($sql);
            $ids = array();
            foreach ($rows as $row) {
                $pack = self::packRow($row);
                if ($pack === null) {
                    continue;
                }
                $id = (int) $pack['id'];
                $stmt->execute(array(
                    ':id'         => $id,
                    ':apiid'      => (int) $pack['apiid'],
                    ':apiname'    => (string) $pack['apiname'],
                    ':apitype'    => (int) $pack['apitype'],
                    ':userid'     => (int) $pack['userid'],
                    ':apikey'     => (string) $pack['apikey'],
                    ':method'     => (string) $pack['method'],
                    ':ip'         => (string) $pack['ip'],
                    ':iploc'      => (string) $pack['iploc'],
                    ':egress'     => (string) $pack['egress'],
                    ':host'       => (string) $pack['host'],
                    ':path'       => (string) $pack['path'],
                    ':url'        => (string) $pack['url'],
                    ':referer'    => (string) $pack['referer'],
                    ':origin'     => (string) $pack['origin'],
                    ':domain'     => (string) $pack['domain'],
                    ':ua'         => (string) $pack['ua'],
                    ':ok'         => (int) $pack['ok'],
                    ':httpcode'   => (int) $pack['httpcode'],
                    ':charged'    => (int) $pack['charged'],
                    ':cost'       => (string) $pack['cost'],
                    ':xfproto'    => (string) $pack['xfproto'],
                    ':xff'        => (string) $pack['xff'],
                    ':xri'        => (string) $pack['xri'],
                    ':createtime' => (string) $pack['createtime'],
                ));
                $ids[] = $id;
            }
            $pdo->commit();
            return $ids;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Exception $ignore) {
                }
            }
            if (function_exists('error_log')) {
                error_log('ApiLogArchive sqlite: ' . $e->getMessage());
            }
            return array();
        }
    }

    /**
     * @param PDO $pdo
     * @return bool
     */
    private static function sqliteTableReady(PDO $pdo)
    {
        try {
            $pdo->query('SELECT 1 FROM `log` LIMIT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 旧冷库分片补齐 egress 列（幂等）
     *
     * @param PDO $pdo
     * @return void
     */
    private static function ensureSqliteEgressColumn(PDO $pdo)
    {
        try {
            $has = false;
            $cols = $pdo->query('PRAGMA table_info(`log`)');
            if ($cols) {
                while ($c = $cols->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($c['name']) && (string) $c['name'] === 'egress') {
                        $has = true;
                        break;
                    }
                }
            }
            if (!$has) {
                $pdo->exec('ALTER TABLE `log` ADD COLUMN `egress` TEXT NOT NULL DEFAULT \'\'');
            }
        } catch (Exception $e) {
            // 忽略：读旧分片时无列则 packRow 默认空串
        }
    }

    /**
     * 旧冷库分片补齐转发链头列，并尽量 DROP 禁止列（幂等 · E423）
     *
     * @param PDO $pdo
     * @return bool 转发头三列是否齐全（写路径须 true）
     */
    private static function ensureSqliteExtendedColumns(PDO $pdo)
    {
        $need = array(
            'xfproto' => 'TEXT NOT NULL DEFAULT \'\'',
            'xff'     => 'TEXT NOT NULL DEFAULT \'\'',
            'xri'     => 'TEXT NOT NULL DEFAULT \'\'',
        );
        // 与热库 dropApilog* 对齐；SQLite 3.35+ 可 DROP，失败忽略
        $drop = array(
            'durationms', 'resptrunc', 'respbytes', 'respbody', 'resptype',
            'via', 'conn', 'reqctype', 'reqclen',
        );
        try {
            $have = self::sqliteColumnMap($pdo);
            foreach ($need as $name => $decl) {
                if (isset($have[$name])) {
                    continue;
                }
                $pdo->exec('ALTER TABLE `log` ADD COLUMN `' . $name . '` ' . $decl);
                $have[$name] = true;
            }
            foreach ($drop as $name) {
                if (!isset($have[$name])) {
                    continue;
                }
                try {
                    $pdo->exec('ALTER TABLE `log` DROP COLUMN `' . $name . '`');
                    unset($have[$name]);
                } catch (Exception $ignore) {
                }
            }
            return isset($have['xfproto'], $have['xff'], $have['xri']);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param PDO $pdo
     * @return array<string,bool>
     */
    private static function sqliteColumnMap(PDO $pdo)
    {
        $have = array();
        $cols = $pdo->query('PRAGMA table_info(`log`)');
        if ($cols) {
            while ($c = $cols->fetch(PDO::FETCH_ASSOC)) {
                if (isset($c['name'])) {
                    $have[(string) $c['name']] = true;
                }
            }
        }
        return $have;
    }

    /**
     * @param PDO $pdo
     * @return void
     */
    private static function initSqliteSchema(PDO $pdo)
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `log` (
                `id` INTEGER PRIMARY KEY NOT NULL,
                `apiid` INTEGER NOT NULL DEFAULT 0,
                `apiname` TEXT NOT NULL DEFAULT \'\',
                `apitype` INTEGER NOT NULL DEFAULT 0,
                `userid` INTEGER NOT NULL DEFAULT 0,
                `apikey` TEXT NOT NULL DEFAULT \'\',
                `method` TEXT NOT NULL DEFAULT \'\',
                `ip` TEXT NOT NULL DEFAULT \'\',
                `iploc` TEXT NOT NULL DEFAULT \'\',
                `egress` TEXT NOT NULL DEFAULT \'\',
                `host` TEXT NOT NULL DEFAULT \'\',
                `path` TEXT NOT NULL DEFAULT \'\',
                `url` TEXT NOT NULL DEFAULT \'\',
                `referer` TEXT NOT NULL DEFAULT \'\',
                `origin` TEXT NOT NULL DEFAULT \'\',
                `domain` TEXT NOT NULL DEFAULT \'\',
                `ua` TEXT NOT NULL DEFAULT \'\',
                `ok` INTEGER NOT NULL DEFAULT 1,
                `httpcode` INTEGER NOT NULL DEFAULT 200,
                `charged` INTEGER NOT NULL DEFAULT 0,
                `cost` TEXT NOT NULL DEFAULT \'0\',
                `xfproto` TEXT NOT NULL DEFAULT \'\',
                `xff` TEXT NOT NULL DEFAULT \'\',
                `xri` TEXT NOT NULL DEFAULT \'\',
                `createtime` TEXT NOT NULL DEFAULT \'\'
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_id` ON `log` (`id`)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_ok` ON `log` (`ok`)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_apiid` ON `log` (`apiid`)');
        self::ensureSqliteExtendedColumns($pdo);
        self::ensureSqliteSearchIndexes($pdo);
    }

    /**
     * 检索索引（幂等；存量分片打开时补建）
     *
     * @param PDO $pdo
     * @return void
     */
    private static function ensureSqliteSearchIndexes(PDO $pdo)
    {
        try {
            // 等值 / 前缀检索常用列（幂等；存量分片首次打开时补建）
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_apiname` ON `log` (`apiname`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_userid` ON `log` (`userid`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_ip` ON `log` (`ip`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_createtime` ON `log` (`createtime`)');
            // 组合：筛条件 + id DESC 翻页
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_apiid_id` ON `log` (`apiid`, `id`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_userid_id` ON `log` (`userid`, `id`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_ip_id` ON `log` (`ip`, `id`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_ok_id` ON `log` (`ok`, `id`)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_apiname_id` ON `log` (`apiname`, `id`)');
        } catch (Exception $e) {
            // 忽略
        }
    }

    /**
     * 只读查询连接优化（冷库多读；不改库文件）
     *
     * @param PDO $pdo
     * @return void
     */
    private static function applySqliteReadPragmas(PDO $pdo)
    {
        try {
            $pdo->exec('PRAGMA temp_store = MEMORY');
            $pdo->exec('PRAGMA cache_size = -8000');
        } catch (Exception $e) {
            // 忽略
        }
    }

    /**
     * 从搜索串解析正整数日志 ID（与热库 parse 口径对齐的轻量版）
     *
     * @param string $q
     * @return int
     */
    private static function parseLogIdToken($q)
    {
        $qTrim = trim((string) $q);
        if ($qTrim === '') {
            return 0;
        }
        if (preg_match('/^#(\d+)$/', $qTrim, $mId)) {
            return (int) $mId[1];
        }
        if (preg_match('/^(?:日志编号|编号|日志\s*id|日志\s*ID|id|ID)\s*[:：#]?\s*(\d+)$/u', $qTrim, $mId)) {
            return (int) $mId[1];
        }
        if (ctype_digit($qTrim)) {
            return (int) $qTrim;
        }
        return 0;
    }

    /**
     * @param array $row
     * @return array|null
     */
    private static function packRow(array $row)
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id <= 0) {
            return null;
        }
        return array(
            'id'         => $id,
            'apiid'      => isset($row['apiid']) ? (int) $row['apiid'] : 0,
            'apiname'    => isset($row['apiname']) ? (string) $row['apiname'] : '',
            'apitype'    => isset($row['apitype']) ? (int) $row['apitype'] : 0,
            'userid'     => isset($row['userid']) ? (int) $row['userid'] : 0,
            'apikey'     => isset($row['apikey']) ? (string) $row['apikey'] : '',
            'method'     => isset($row['method']) ? (string) $row['method'] : '',
            'ip'         => isset($row['ip']) ? (string) $row['ip'] : '',
            'iploc'      => isset($row['iploc']) ? (string) $row['iploc'] : '',
            'egress'     => isset($row['egress']) ? (string) $row['egress'] : '',
            'host'       => isset($row['host']) ? (string) $row['host'] : '',
            'path'       => isset($row['path']) ? (string) $row['path'] : '',
            'url'        => isset($row['url']) ? (string) $row['url'] : '',
            'referer'    => isset($row['referer']) ? (string) $row['referer'] : '',
            'origin'     => isset($row['origin']) ? (string) $row['origin'] : '',
            'domain'     => isset($row['domain']) ? (string) $row['domain'] : '',
            'ua'         => isset($row['ua']) ? (string) $row['ua'] : '',
            'ok'         => isset($row['ok']) ? (int) $row['ok'] : 1,
            'httpcode'   => isset($row['httpcode']) ? (int) $row['httpcode'] : 200,
            'charged'    => isset($row['charged']) ? (int) $row['charged'] : 0,
            'cost'       => isset($row['cost']) ? (string) $row['cost'] : '0',
            'xfproto'    => isset($row['xfproto']) ? (string) $row['xfproto'] : '',
            'xff'        => isset($row['xff']) ? (string) $row['xff'] : '',
            'xri'        => isset($row['xri']) ? (string) $row['xri'] : '',
            'createtime' => isset($row['createtime']) ? (string) $row['createtime'] : '',
        );
    }

    /**
     * 规范化日志 ID 列表（字符串数字，兼容 bigint）
     *
     * @param array $ids
     * @return string[]
     */
    private static function normalizeIdList(array $ids)
    {
        $out = array();
        foreach ($ids as $id) {
            if (is_int($id) || (is_float($id) && $id == (int) $id)) {
                $id = (string) (int) $id;
            } else {
                $id = preg_replace('/\D+/', '', (string) $id);
            }
            if ($id === '' || $id === '0') {
                continue;
            }
            $out[$id] = $id;
        }
        return array_values($out);
    }

    /**
     * 冷库写成功后删除热库行并校验无残留（失败抛异常，供上层回失败态）
     *
     * @param PDO    $pdo
     * @param string $table
     * @param array  $ids
     * @return int 应删除条数
     * @throws Exception
     */
    private static function deleteIdsVerified(PDO $pdo, $table, array $ids)
    {
        $ids = self::normalizeIdList($ids);
        if (empty($ids)) {
            return 0;
        }
        self::deleteIds($pdo, $table, $ids);
        $left = self::countIdsPresent($pdo, $table, $ids);
        if ($left > 0) {
            self::deleteIds($pdo, $table, $ids);
            $left = self::countIdsPresent($pdo, $table, $ids);
            if ($left > 0) {
                throw new Exception('冷库已写入但在线库仍残留 ' . $left . ' 条未删除');
            }
        }
        return count($ids);
    }

    /**
     * @param PDO    $pdo
     * @param string $table
     * @param array  $ids
     * @return int
     */
    private static function countIdsPresent(PDO $pdo, $table, array $ids)
    {
        $ids = self::normalizeIdList($ids);
        if (empty($ids)) {
            return 0;
        }
        $total = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $place = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `id` IN (' . $place . ')');
            $stmt->execute($chunk);
            $total += (int) $stmt->fetchColumn();
        }
        return $total;
    }

    /**
     * @param PDO    $pdo
     * @param string $table
     * @param array  $ids
     * @return int
     */
    private static function deleteIds(PDO $pdo, $table, array $ids)
    {
        $ids = self::normalizeIdList($ids);
        if (empty($ids)) {
            return 0;
        }
        $deleted = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $place = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `id` IN (' . $place . ')');
            $stmt->execute($chunk);
            $deleted += (int) $stmt->rowCount();
        }
        return $deleted;
    }

    /**
     * @param string $day
     * @param int    $beforeId
     * @param int    $limit
     * @param string $q
     * @param string $qField
     * @param mixed  $ok
     * @param int    $apiid
     * @param int[]  $userIds
     * @return array
     */
    private static function readDayFiltered(
        $day,
        $beforeId,
        $limit,
        $q,
        $qField,
        $ok,
        $apiid,
        array $userIds = array(),
        array $nameApiids = array(),
        $targetLogId = 0
    ) {
        if (!self::sqliteAvailable()) {
            return array();
        }
        $targetLogId = (int) $targetLogId;
        $index = self::readJson(self::dayIndexPath($day));
        if (!is_array($index) || empty($index['shards']) || !is_array($index['shards'])) {
            return array();
        }
        $shards = $index['shards'];
        usort($shards, function ($a, $b) {
            $ma = isset($a['max_id']) ? (int) $a['max_id'] : 0;
            $mb = isset($b['max_id']) ? (int) $b['max_id'] : 0;
            return $mb - $ma;
        });

        $out = array();
        $shardDir = self::shardDir($day);
        foreach ($shards as $sh) {
            if (count($out) >= $limit) {
                break;
            }
            $min = isset($sh['min_id']) ? (int) $sh['min_id'] : 0;
            $max = isset($sh['max_id']) ? (int) $sh['max_id'] : 0;
            if ($beforeId > 0 && $min >= $beforeId) {
                continue;
            }
            if ($targetLogId > 0 && $min > 0 && $max > 0
                && ($targetLogId < $min || $targetLogId > $max)
            ) {
                continue;
            }
            $file = isset($sh['file']) ? (string) $sh['file'] : '';
            if ($file === '' || strpos($file, '..') !== false || substr($file, -3) !== '.db') {
                continue;
            }
            $path = $shardDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $rows = self::querySqliteShard(
                $path,
                $beforeId,
                $limit - count($out),
                $q,
                $qField,
                $ok,
                $apiid,
                $userIds,
                $nameApiids
            );
            foreach ($rows as $r) {
                $out[] = $r;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * @param string $day
     * @param int    $id
     * @return array|null
     */
    private static function findInDay($day, $id)
    {
        if (!self::sqliteAvailable()) {
            return null;
        }
        $index = self::readJson(self::dayIndexPath($day));
        if (!is_array($index) || empty($index['shards'])) {
            return null;
        }
        $shardDir = self::shardDir($day);
        foreach ($index['shards'] as $sh) {
            $min = isset($sh['min_id']) ? (int) $sh['min_id'] : 0;
            $max = isset($sh['max_id']) ? (int) $sh['max_id'] : 0;
            if ($min > 0 && $max > 0 && ($id < $min || $id > $max)) {
                continue;
            }
            $file = isset($sh['file']) ? (string) $sh['file'] : '';
            if ($file === '' || strpos($file, '..') !== false || substr($file, -3) !== '.db') {
                continue;
            }
            $path = $shardDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            try {
                $pdo = new PDO('sqlite:' . $path);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // 读路径也补列/尽量 DROP 禁止列，避免旧分片长期残留 durationms 等
                self::ensureSqliteEgressColumn($pdo);
                self::ensureSqliteExtendedColumns($pdo);
                self::ensureSqliteSearchIndexes($pdo);
                self::applySqliteReadPragmas($pdo);
                $stmt = $pdo->prepare('SELECT * FROM `log` WHERE `id` = ? LIMIT 1');
                $stmt->execute(array($id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $row['username'] = '';
                    return $row;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * @param string $path
     * @param int    $beforeId
     * @param int    $limit
     * @param string $q
     * @param string $qField
     * @param mixed  $ok
     * @param int    $apiid
     * @param int[]  $userIds
     * @return array
     */
    private static function querySqliteShard(
        $path,
        $beforeId,
        $limit,
        $q,
        $qField,
        $ok,
        $apiid,
        array $userIds = array(),
        array $nameApiids = array()
    ) {
        $limit = max(1, (int) $limit);
        $qField = class_exists('ApiLogManager')
            ? ApiLogManager::normalizeQField($qField)
            : 'id';
        $nameApiids = self::normalizeApiidList($nameApiids);
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::ensureSqliteEgressColumn($pdo);
            self::ensureSqliteExtendedColumns($pdo);
            self::ensureSqliteSearchIndexes($pdo);
            self::applySqliteReadPragmas($pdo);
            $where = array('1=1');
            $bind = array();
            if ($beforeId > 0) {
                $where[] = '`id` < ?';
                $bind[] = $beforeId;
            }
            if ($nameApiids !== array()) {
                $ph = implode(',', array_fill(0, count($nameApiids), '?'));
                $where[] = '`apiid` IN (' . $ph . ')';
                foreach ($nameApiids as $aid) {
                    $bind[] = (int) $aid;
                }
            } elseif ($apiid > 0) {
                $where[] = '`apiid` = ?';
                $bind[] = $apiid;
            }
            if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
                $where[] = '`ok` = ?';
                $bind[] = (int) $ok;
            }
            if ($q !== '') {
                $qTrim = trim((string) $q);
                if ($qField === 'id') {
                    $logId = 0;
                    if (preg_match('/^#(\d+)$/', $qTrim, $mId)) {
                        $logId = (int) $mId[1];
                    } elseif (preg_match('/^(?:日志编号|编号|日志\s*id|日志\s*ID|id|ID)\s*[:：#]?\s*(\d+)$/u', $qTrim, $mId)) {
                        $logId = (int) $mId[1];
                    } elseif (ctype_digit($qTrim)) {
                        $logId = (int) $qTrim;
                    }
                    if ($logId > 0) {
                        $where[] = '`id` = ?';
                        $bind[] = $logId;
                    } else {
                        $where[] = '1=0';
                    }
                } elseif ($qField === 'apiid') {
                    $aid = 0;
                    if (preg_match('/^#(\d+)$/', $qTrim, $mId)) {
                        $aid = (int) $mId[1];
                    } elseif (ctype_digit($qTrim)) {
                        $aid = (int) $qTrim;
                    } elseif (preg_match('/^(?:接口\s*id|接口\s*ID|id|ID)\s*[:：#]?\s*(\d+)$/u', $qTrim, $mId)) {
                        $aid = (int) $mId[1];
                    }
                    if ($aid > 0) {
                        $where[] = '`apiid` = ?';
                        $bind[] = $aid;
                    } else {
                        $where[] = '1=0';
                    }
                } elseif ($qField === 'apiname') {
                    // 与热库一致：前缀匹配，可走 idx_log_apiname
                    $like = function_exists('vs_sql_like_prefix')
                        ? vs_sql_like_prefix($qTrim)
                        : (addcslashes($qTrim, "\\%_") . '%');
                    $where[] = '`apiname` LIKE ? ESCAPE \'\\\\\'';
                    $bind[] = $like;
                } elseif ($qField === 'ip') {
                    $like = function_exists('vs_sql_like_prefix')
                        ? vs_sql_like_prefix($qTrim)
                        : (addcslashes($qTrim, "\\%_") . '%');
                    $where[] = '`ip` LIKE ? ESCAPE \'\\\\\'';
                    $bind[] = $like;
                } elseif ($qField === 'apikey') {
                    $like = function_exists('vs_sql_like_prefix')
                        ? vs_sql_like_prefix($qTrim)
                        : (addcslashes($qTrim, "\\%_") . '%');
                    $where[] = '`apikey` LIKE ? ESCAPE \'\\\\\'';
                    $bind[] = $like;
                }
            }
            if ($userIds !== array()) {
                $ph = implode(',', array_fill(0, count($userIds), '?'));
                $where[] = '`userid` IN (' . $ph . ')';
                foreach ($userIds as $uid) {
                    $bind[] = (int) $uid;
                }
            }
            $sql = 'SELECT * FROM `log` WHERE ' . implode(' AND ', $where) . ' ORDER BY `id` DESC LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['username'] = '';
            }
            unset($r);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @return bool
     */
    private static function acquireLock()
    {
        self::ensureStorage();
        $lock = self::rootDir() . '/.archive.lock';
        if (is_file($lock)) {
            $age = time() - (int) @filemtime($lock);
            if ($age < self::LOCK_TTL) {
                return false;
            }
        }
        return @file_put_contents($lock, (string) time(), LOCK_EX) !== false;
    }

    /**
     * @return void
     */
    private static function releaseLock()
    {
        $lock = self::rootDir() . '/.archive.lock';
        if (is_file($lock)) {
            @unlink($lock);
        }
    }

    /**
     * @return void
     */
    private static function touchCatalogMeta()
    {
        $catalog = self::readCatalog();
        $catalog['updated'] = date('c');
        $catalog['hot_days'] = self::hotDays();
        $catalog['version'] = self::CATALOG_VERSION;
        $catalog['format'] = 'sqlite';
        self::writeJson(self::catalogPath(), $catalog);
    }

    /**
     * @param string $path
     * @return array|null
     */
    private static function readJson($path)
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param string $path
     * @param array  $data
     * @return bool
     */
    private static function writeJson($path, array $data)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }
        return @rename($tmp, $path);
    }
}
