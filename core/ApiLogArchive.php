<?php
/**
 * 文件：core/ApiLogArchive.php
 * 作用：调用日志冷热分层——热数据留 MySQL；冷数据三层索引 + SQLite 分片（多读少写）
 *
 * 目录结构（三层分离）：
 *   data/apilog/catalog/catalog.json     ← 总索引（日期 / ID 段 → 哪一天）
 *   data/apilog/days/YYYY-MM-DD/index.json ← 日索引（ID 段 → 哪个 .db）
 *   data/apilog/shards/YYYY-MM-DD/s0001.db ← 日志正文（SQLite，约 1000 条/片）
 */

class ApiLogArchive
{
    const DEFAULT_HOT_DAYS = 30;
    const MAX_HOT_DAYS = 365;
    const SHARD_ROWS = 1000;
    const BATCH_ROWS = 5000;
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
     * 是否启用冷热归档（关闭后计划任务不归档，日志全留库）
     *
     * @return bool
     */
    public static function isEnabled()
    {
        try {
            return trim((string) Config::get('apilog_archive_enabled', '1')) !== '0';
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * @return bool
     */
    public static function sqliteAvailable()
    {
        return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
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
        try {
            return trim((string) Config::get('apilog_cron_key', ''));
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @return string
     */
    public static function generateCronKey()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            return sha1(uniqid((string) mt_rand(), true) . microtime(true));
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function validateCronKey($key)
    {
        $expected = self::cronKey();
        if ($expected === '' || $key === '') {
            return false;
        }
        return hash_equals($expected, (string) $key);
    }

    /**
     * @return string
     */
    public static function cronUrl()
    {
        $base = rtrim(vs_base_url(), '/');
        $key = self::cronKey();
        $url = $base . '/core/cron/apilogarchive.php';
        if ($key !== '') {
            $url .= '?key=' . rawurlencode($key);
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
     * @param int|null $limit
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function runOnce($limit = null)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
        if (!self::isEnabled()) {
            $empty['ok'] = true;
            $empty['msg'] = '冷热归档未开启，跳过';
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
        if (!self::acquireLock()) {
            $empty['msg'] = '已有归档任务在执行';
            return $empty;
        }

        $limit = $limit === null ? self::BATCH_ROWS : max(100, min(20000, (int) $limit));
        $hotDays = self::hotDays();
        $archived = 0;
        $deleted = 0;
        $dayTouched = array();

        try {
            $pdo = Database::connect();
            $table = Database::table('apilog');
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . $table . '`
                 WHERE `createtime` < DATE_SUB(NOW(), INTERVAL ? DAY)
                 ORDER BY `createtime` ASC, `id` ASC
                 LIMIT ' . (int) $limit
            );
            $stmt->execute(array($hotDays));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                self::releaseLock();
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

            $idsToDelete = array();
            foreach ($byDay as $day => $dayRows) {
                $writtenIds = self::appendDayRows($day, $dayRows);
                $n = count($writtenIds);
                if ($n <= 0) {
                    continue;
                }
                $archived += $n;
                $dayTouched[] = $day;
                foreach ($writtenIds as $wid) {
                    $idsToDelete[] = (int) $wid;
                }
            }

            if (!empty($idsToDelete)) {
                $deleted = self::deleteIds($pdo, $table, $idsToDelete);
                if (class_exists('RedisCache')) {
                    RedisCache::invalidateApiLog();
                }
            }

            self::touchCatalogMeta();
            self::releaseLock();

            return array(
                'ok'       => true,
                'msg'      => '归档完成',
                'archived' => $archived,
                'days'     => $dayTouched,
                'deleted'  => $deleted,
            );
        } catch (Exception $e) {
            self::releaseLock();
            $empty['msg'] = '归档失败：' . $e->getMessage();
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
        $days = max(1, (int) (isset($opts['days']) ? $opts['days'] : 7));
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;

        $catalog = self::readCatalog();
        $dayKeys = array();
        if (!empty($catalog['days']) && is_array($catalog['days'])) {
            $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
            $to = date('Y-m-d');
            foreach ($catalog['days'] as $day => $meta) {
                if (!is_string($day) || $day < $from || $day > $to) {
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
            $chunk = self::readDayFiltered($day, $beforeId, $need - count($out), $q, $ok, $apiid);
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
            if (count($buffer) >= self::SHARD_ROWS) {
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
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->beginTransaction();
            $sql = 'INSERT OR REPLACE INTO `log` (
                `id`,`apiid`,`apiname`,`apitype`,`userid`,`apikey`,`method`,`ip`,`iploc`,
                `host`,`path`,`url`,`referer`,`origin`,`domain`,`ua`,`ok`,`httpcode`,`charged`,`cost`,`createtime`
            ) VALUES (
                :id,:apiid,:apiname,:apitype,:userid,:apikey,:method,:ip,:iploc,
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
     * @param PDO    $pdo
     * @param string $table
     * @param array  $ids
     * @return int
     */
    private static function deleteIds(PDO $pdo, $table, array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
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
     * @return array
     */
    private static function readDayFiltered($day, $beforeId, $limit, $q, $ok, $apiid)
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
            $rows = self::querySqliteShard($path, $beforeId, $limit - count($out), $q, $ok, $apiid);
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
     * @return array
     */
    private static function querySqliteShard($path, $beforeId, $limit, $q, $ok, $apiid)
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
            if ($q !== '') {
                $where[] = '(
                    `apiname` LIKE ? OR `path` LIKE ? OR `ip` LIKE ? OR `url` LIKE ?
                    OR `apikey` LIKE ? OR `domain` LIKE ? OR `iploc` LIKE ?
                )';
                $like = '%' . $q . '%';
                for ($i = 0; $i < 7; $i++) {
                    $bind[] = $like;
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
