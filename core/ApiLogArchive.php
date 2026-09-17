<?php
/**
 * 文件：core/ApiLogArchive.php
 * 作用：调用日志冷热分层——热数据留 MySQL；冷数据三层索引 + SQLite 分片（多读少写）
 *
 * 归档成功后：对应行从 MySQL 删除（不可逆），仅保留本机冷库；禁止冷热双留。
 *
 * 目录结构（三层分离）：
 *   data/apilog/catalog/catalog.json     ← 总索引（日期 / ID 段 → 哪一天）
 *   data/apilog/days/YYYY-MM-DD/index.json ← 日索引（ID 段 → 哪个 .db）
 *   data/apilog/shards/YYYY-MM-DD/s0001.db ← 日志正文（SQLite；每片条数见后台「每个分片条数」，默认 5000）
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
    const CATALOG_VERSION = 2;

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
     * 是否启用冷热归档（与「直接删除」互斥；关闭后计划任务拒跑）
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return self::isArchiveEnabled();
    }

    /**
     * @return bool
     */
    public static function isArchiveEnabled()
    {
        try {
            return trim((string) Config::get('apilog_archive_enabled', '1')) === '1';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 是否启用过期日志直接删除（不写冷库；与归档互斥）
     *
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
     * 当前清理模式：archive | purge | off
     *
     * @return string
     */
    public static function cleanupMode()
    {
        $archive = self::isArchiveEnabled();
        $purge = self::isPurgeEnabled();
        if ($archive && !$purge) {
            return 'archive';
        }
        if ($purge && !$archive) {
            return 'purge';
        }
        return 'off';
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
     * 计划任务 URL（默认不含密钥，避免进访问日志；需要 Query 传钥时传 $withKey=true）
     *
     * @param bool $withKey
     * @return string
     */
    public static function cronUrl($withKey = false)
    {
        $url = rtrim(vs_base_url(), '/') . '/core/api/apilogarchive.php';
        if ($withKey) {
            $key = SystemApiKey::get();
            if ($key !== '') {
                $url .= '?key=' . rawurlencode($key);
            }
        }
        return $url;
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
     * 计划任务统一入口（按后台开关分流；未启用则失败）
     *
     * @param int $maxRounds
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int,mode?:string}
     */
    public static function runScheduled($maxRounds = 50)
    {
        $mode = self::cleanupMode();
        if ($mode === 'off') {
            return array(
                'ok'       => false,
                'msg'      => '日志清理未启用（请先在系统设置开启冷热归档或过期删除）',
                'archived' => 0,
                'days'     => array(),
                'deleted'  => 0,
                'mode'     => 'off',
            );
        }
        if ($mode === 'purge') {
            $result = self::runPurge($maxRounds);
            $result['mode'] = 'purge';
            return $result;
        }
        $result = self::run($maxRounds);
        $result['mode'] = 'archive';
        return $result;
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
        if (!self::isPurgeEnabled() || self::isArchiveEnabled()) {
            $empty['msg'] = '过期日志删除未启用或与归档冲突';
            return $empty;
        }
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
     * 计划任务入口：持锁多轮跑到本轮无冷数据或达上限（单次 runOnce 有条数上限）
     *
     * @param int $maxRounds
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function run($maxRounds = 50)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
        if (!self::isArchiveEnabled() || self::isPurgeEnabled()) {
            $empty['msg'] = '冷热归档未启用或与过期删除冲突';
            return $empty;
        }
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
                $result = self::runOnce(null, true);
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

        if ($archived > 0 && class_exists('RedisCache')) {
            RedisCache::invalidateApiLog();
        }

        return array(
            'ok'       => true,
            'msg'      => $archived > 0 ? '归档完成' : $lastMsg,
            'archived' => $archived,
            'days'     => array_values(array_unique($days)),
            'deleted'  => $deleted,
        );
    }

    /**
     * 单批归档：冷库写入成功后必须从 MySQL 删除对应行（不可逆）；删除后校验残留
     *
     * @param int|null $limit
     * @param bool     $alreadyLocked 多轮 run() 时首轮已持锁则传 true
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function runOnce($limit = null, $alreadyLocked = false)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
        if (!self::isArchiveEnabled() || self::isPurgeEnabled()) {
            $empty['ok'] = false;
            $empty['msg'] = '冷热归档未启用或与过期删除冲突';
            return $empty;
        }
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

        try {
            $pdo = Database::connect();
            $table = Database::table('apilog');
            // INTERVAL 天数内联为整数，避免部分环境下占位符绑定异常
            $sql = 'SELECT * FROM `' . $table . '`
                 WHERE `createtime` < DATE_SUB(NOW(), INTERVAL ' . (int) $hotDays . ' DAY)
                 ORDER BY `createtime` ASC, `id` ASC
                 LIMIT ' . (int) $limit;
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
            if (empty($rows)) {
                if ($release) {
                    self::releaseLock();
                }
                return array(
                    'ok'       => true,
                    'msg'      => '没有需要归档的冷数据',
                    'archived' => 0,
                    'days'     => array(),
                    'deleted'  => 0,
                );
            }

            $byDay = array();
            foreach ($rows as $row) {
                $day = self::rowDay($row);
                if ($day === '') {
                    continue;
                }
                if (!isset($byDay[$day])) {
                    $byDay[$day] = array();
                }
                $byDay[$day][] = $row;
            }

            // 按日写入冷库，写成功立即从热库删除并校验（禁止冷热双留）
            foreach ($byDay as $day => $dayRows) {
                $writtenIds = self::appendDayRows($day, $dayRows);
                $n = count($writtenIds);
                if ($n <= 0) {
                    throw new Exception('冷库写入失败：' . $day);
                }
                $archived += $n;
                $dayTouched[] = $day;
                $deleted += self::deleteIdsVerified($pdo, $table, $writtenIds);
            }

            if ($archived > 0 && class_exists('RedisCache') && $release) {
                RedisCache::invalidateApiLog();
            }

            self::touchCatalogMeta();
            if ($release) {
                self::releaseLock();
            }

            return array(
                'ok'       => true,
                'msg'      => '归档完成',
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
    public static function countInQueryWindow($days)
    {
        $days = max(1, (int) $days);
        $catalog = self::readCatalog();
        if (empty($catalog['days']) || !is_array($catalog['days'])) {
            return 0;
        }
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to = date('Y-m-d');
        $total = 0;
        foreach ($catalog['days'] as $day => $meta) {
            if (!is_string($day) || $day < $from || $day > $to) {
                continue;
            }
            $total += isset($meta['count']) ? (int) $meta['count'] : 0;
        }
        return $total;
    }

    /**
     * @param array $opts
     * @return array{list:array,has_more:bool,next_before_id:int}
     */
    public static function listInQueryWindow(array $opts)
    {
        $days = (int) (isset($opts['days']) ? $opts['days'] : 0);
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
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

        $catalog = self::readCatalog();
        $dayKeys = array();
        if (!empty($catalog['days']) && is_array($catalog['days'])) {
            // days<=0：不按时间窗过滤，按 id keyset 取冷库（与列表「仅每页条数」一致）
            $from = null;
            $to = date('Y-m-d');
            if ($days > 0) {
                $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
            }
            foreach ($catalog['days'] as $day => $meta) {
                if (!is_string($day)) {
                    continue;
                }
                if ($from !== null && ($day < $from || $day > $to)) {
                    continue;
                }
                if ($beforeId > 0) {
                    $min = isset($meta['min_id']) ? (int) $meta['min_id'] : 0;
                    if ($min >= $beforeId) {
                        continue;
                    }
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
            $chunk = self::readDayFiltered($day, $beforeId, $need - count($out), $q, $ok, $apiid, $userIds);
            foreach ($chunk as $row) {
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
        foreach ($catalog['days'] as $day => $meta) {
            $min = isset($meta['min_id']) ? (int) $meta['min_id'] : 0;
            $max = isset($meta['max_id']) ? (int) $meta['max_id'] : 0;
            if ($min > 0 && $max > 0 && ($id < $min || $id > $max)) {
                continue;
            }
            $row = self::findInDay((string) $day, $id);
            if ($row !== null) {
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
        self::writeJson($indexPath, $index);

        $catalog = self::readCatalog();
        $catalog['version'] = self::CATALOG_VERSION;
        $catalog['format'] = 'sqlite';
        $catalog['days'][$day] = array(
            'min_id' => $minId,
            'max_id' => $maxId,
            'count'  => $count,
            'shards' => count($index['shards']),
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
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->beginTransaction();
            $sql = 'INSERT OR REPLACE INTO `log` (
                `id`,`apiid`,`apiname`,`apitype`,`userid`,`apikey`,`method`,`ip`,`iploc`,`egress`,
                `host`,`path`,`url`,`referer`,`origin`,`domain`,`ua`,`ok`,`httpcode`,`charged`,`cost`,`createtime`
            ) VALUES (
                :id,:apiid,:apiname,:apitype,:userid,:apikey,:method,:ip,:iploc,:egress,
                :host,:path,:url,:referer,:origin,:domain,:ua,:ok,:httpcode,:charged,:cost,:createtime
            )';
            $stmt = $pdo->prepare($sql);
            $ids = array();
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $stmt->execute(array(
                    ':id'         => $id,
                    ':apiid'      => (int) $row['apiid'],
                    ':apiname'    => (string) $row['apiname'],
                    ':apitype'    => (int) $row['apitype'],
                    ':userid'     => (int) $row['userid'],
                    ':apikey'     => (string) $row['apikey'],
                    ':method'     => (string) $row['method'],
                    ':ip'         => (string) $row['ip'],
                    ':iploc'      => (string) $row['iploc'],
                    ':egress'     => isset($row['egress']) ? (string) $row['egress'] : '',
                    ':host'       => (string) $row['host'],
                    ':path'       => (string) $row['path'],
                    ':url'        => (string) $row['url'],
                    ':referer'    => (string) $row['referer'],
                    ':origin'     => (string) $row['origin'],
                    ':domain'     => (string) $row['domain'],
                    ':ua'         => (string) $row['ua'],
                    ':ok'         => (int) $row['ok'],
                    ':httpcode'   => (int) $row['httpcode'],
                    ':charged'    => (int) $row['charged'],
                    ':cost'       => (string) $row['cost'],
                    ':createtime' => (string) $row['createtime'],
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
                `createtime` TEXT NOT NULL DEFAULT \'\'
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_id` ON `log` (`id`)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_ok` ON `log` (`ok`)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS `idx_log_apiid` ON `log` (`apiid`)');
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
     * @param mixed  $ok
     * @param int    $apiid
     * @param int[]  $userIds
     * @return array
     */
    private static function readDayFiltered($day, $beforeId, $limit, $q, $ok, $apiid, array $userIds = array())
    {
        if (!self::sqliteAvailable()) {
            return array();
        }
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
            $file = isset($sh['file']) ? (string) $sh['file'] : '';
            if ($file === '' || strpos($file, '..') !== false || substr($file, -3) !== '.db') {
                continue;
            }
            $path = $shardDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $rows = self::querySqliteShard($path, $beforeId, $limit - count($out), $q, $ok, $apiid, $userIds);
            foreach ($rows as $r) {
                $out[] = $r;
                if (count($out) >= $limit) {
                    break;
                }
            }
            unset($max);
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
     * @param mixed  $ok
     * @param int    $apiid
     * @param int[]  $userIds
     * @return array
     */
    private static function querySqliteShard($path, $beforeId, $limit, $q, $ok, $apiid, array $userIds = array())
    {
        $limit = max(1, (int) $limit);
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $where = array('1=1');
            $bind = array();
            if ($beforeId > 0) {
                $where[] = '`id` < ?';
                $bind[] = $beforeId;
            }
            if ($apiid > 0) {
                $where[] = '`apiid` = ?';
                $bind[] = $apiid;
            }
            if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
                $where[] = '`ok` = ?';
                $bind[] = (int) $ok;
            }
            if ($q !== '' || $userIds !== array()) {
                // q 与 userid 必须 AND：禁止 OR 导致「搜到别的用户」串读
                if ($q !== '') {
                    $qTrim = trim((string) $q);
                    $logIdOnly = 0;
                    if (preg_match('/^#(\d+)$/', $qTrim, $mId)) {
                        $logIdOnly = (int) $mId[1];
                    } elseif (preg_match('/^(?:日志编号|编号|日志\s*id|日志\s*ID|id|ID)\s*[:：#]?\s*(\d+)$/u', $qTrim, $mId)) {
                        $logIdOnly = (int) $mId[1];
                    }
                    if ($logIdOnly > 0) {
                        $where[] = '`id` = ?';
                        $bind[] = $logIdOnly;
                    } else {
                        $qParts = array(
                            '`apiname` LIKE ? ESCAPE \'\\\\\'',
                            '`path` LIKE ? ESCAPE \'\\\\\'',
                            '`ip` LIKE ? ESCAPE \'\\\\\'',
                            '`url` LIKE ? ESCAPE \'\\\\\'',
                            '`apikey` LIKE ? ESCAPE \'\\\\\'',
                            '`domain` LIKE ? ESCAPE \'\\\\\'',
                            '`iploc` LIKE ? ESCAPE \'\\\\\'',
                        );
                        $like = function_exists('vs_sql_like_contains')
                            ? vs_sql_like_contains($q)
                            : ('%' . addcslashes($q, "\\%_") . '%');
                        for ($i = 0; $i < 7; $i++) {
                            $bind[] = $like;
                        }
                        if (ctype_digit($qTrim)) {
                            $qParts[] = '`id` = ?';
                            $bind[] = (int) $qTrim;
                        }
                        $where[] = '(' . implode(' OR ', $qParts) . ')';
                    }
                }
                if ($userIds !== array()) {
                    $ph = implode(',', array_fill(0, count($userIds), '?'));
                    $where[] = '`userid` IN (' . $ph . ')';
                    foreach ($userIds as $uid) {
                        $bind[] = (int) $uid;
                    }
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
