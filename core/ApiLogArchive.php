<?php
/**
 * 文件：core/ApiLogArchive.php
 * 作用：调用日志冷热分层——热数据留 MySQL，冷数据按日归档到 data/apilog（目录索引 + ID 段分片）
 *
 * 目录结构：
 *   data/apilog/catalog.json
 *   data/apilog/YYYY-MM-DD/index.json
 *   data/apilog/YYYY-MM-DD/s0001.jsonl …
 */

class ApiLogArchive
{
    const DEFAULT_HOT_DAYS = 30;
    const MAX_HOT_DAYS = 365;
    const SHARD_ROWS = 1000;
    const BATCH_ROWS = 5000;
    const LOCK_TTL = 1800;

    /**
     * @return string
     */
    public static function rootDir()
    {
        return rtrim(str_replace('\\', '/', VS_ROOT), '/') . '/data/apilog';
    }

    /**
     * MySQL 热数据天数（超出则归档到本地，不删除业务含义上的日志）
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
     * 生成计划任务密钥（64 位十六进制）
     *
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
     * 计划任务入口 URL（相对站点根）
     *
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
     * 确保目录与拒访文件存在
     *
     * @return bool
     */
    public static function ensureStorage()
    {
        $root = self::rootDir();
        if (!is_dir($root)) {
            if (!@mkdir($root, 0755, true) && !is_dir($root)) {
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
        if (!is_file($root . '/catalog.json')) {
            self::writeJson($root . '/catalog.json', array(
                'version'  => 1,
                'updated'  => date('c'),
                'days'     => array(),
            ));
        }
        return is_dir($root) && is_writable($root);
    }

    /**
     * 执行一轮归档：把热数据窗口之外的行写入本地分片，成功后再从 MySQL 删除
     *
     * @param int|null $limit
     * @return array{ok:bool,msg:string,archived:int,days:array,deleted:int}
     */
    public static function runOnce($limit = null)
    {
        $empty = array('ok' => false, 'msg' => '', 'archived' => 0, 'days' => array(), 'deleted' => 0);
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
     * 冷库：按时间窗统计条数
     *
     * @param int $days 近 N 天（相对今天）
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
     * 冷库：查询列表（id DESC，支持 before_id / 简单筛选）
     *
     * @param array $opts days, before_id, pagesize, q, ok, apiid
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
     * @return array|null 原始行（含 username 空串）
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
        $path = self::rootDir() . '/catalog.json';
        $data = self::readJson($path);
        if (!is_array($data)) {
            return array('version' => 1, 'days' => array());
        }
        if (!isset($data['days']) || !is_array($data['days'])) {
            $data['days'] = array();
        }
        return $data;
    }

    /**
     * @param array $row
     * @return string Y-m-d
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
     * @return int[] 成功写入的 id 列表（仅这些可从 MySQL 删除）
     */
    private static function appendDayRows($day, array $rows)
    {
        $day = preg_replace('/[^0-9\-]/', '', (string) $day);
        if ($day === '' || empty($rows)) {
            return array();
        }
        $dir = self::rootDir() . '/' . $day;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return array();
        }
        $indexPath = $dir . '/index.json';
        $index = self::readJson($indexPath);
        if (!is_array($index)) {
            $index = array('day' => $day, 'shards' => array());
        }
        if (!isset($index['shards']) || !is_array($index['shards'])) {
            $index['shards'] = array();
        }

        $writtenIds = array();
        $bufferLines = array();
        $bufferIds = array();
        $bufMin = 0;
        $bufMax = 0;

        $flush = function () use (&$bufferLines, &$bufferIds, &$bufMin, &$bufMax, &$index, &$writtenIds, $dir) {
            if (empty($bufferLines)) {
                return;
            }
            $seq = count($index['shards']) + 1;
            $file = 's' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT) . '.jsonl';
            $path = $dir . '/' . $file;
            $fh = fopen($path, 'ab');
            if (!$fh) {
                $bufferLines = array();
                $bufferIds = array();
                $bufMin = 0;
                $bufMax = 0;
                return;
            }
            foreach ($bufferLines as $line) {
                fwrite($fh, $line . "\n");
            }
            fclose($fh);
            $index['shards'][] = array(
                'file'   => $file,
                'min_id' => $bufMin,
                'max_id' => $bufMax,
                'count'  => count($bufferLines),
            );
            foreach ($bufferIds as $wid) {
                $writtenIds[] = (int) $wid;
            }
            $bufferLines = array();
            $bufferIds = array();
            $bufMin = 0;
            $bufMax = 0;
        };

        foreach ($rows as $row) {
            $pack = self::packRow($row);
            if ($pack === null) {
                continue;
            }
            $id = (int) $pack['id'];
            $line = json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                continue;
            }
            if (empty($bufferLines)) {
                $bufMin = $id;
                $bufMax = $id;
            } else {
                if ($id < $bufMin) {
                    $bufMin = $id;
                }
                if ($id > $bufMax) {
                    $bufMax = $id;
                }
            }
            $bufferLines[] = $line;
            $bufferIds[] = $id;
            if (count($bufferLines) >= self::SHARD_ROWS) {
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
        $catalog['days'][$day] = array(
            'min_id' => $minId,
            'max_id' => $maxId,
            'count'  => $count,
            'shards' => count($index['shards']),
        );
        $catalog['updated'] = date('c');
        self::writeJson(self::rootDir() . '/catalog.json', $catalog);

        return $writtenIds;
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
        unset($row['username']);
        $row['id'] = $id;
        return $row;
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
        $dir = self::rootDir() . '/' . $day;
        $index = self::readJson($dir . '/index.json');
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
        foreach ($shards as $sh) {
            if (count($out) >= $limit) {
                break;
            }
            $min = isset($sh['min_id']) ? (int) $sh['min_id'] : 0;
            $max = isset($sh['max_id']) ? (int) $sh['max_id'] : 0;
            if ($beforeId > 0 && $max > 0 && $max < $beforeId && $min < $beforeId) {
                // shard may still contain ids < beforeId
            }
            if ($beforeId > 0 && $min >= $beforeId) {
                continue;
            }
            $file = isset($sh['file']) ? (string) $sh['file'] : '';
            if ($file === '' || strpos($file, '..') !== false) {
                continue;
            }
            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            $rows = array();
            foreach ($lines as $line) {
                $obj = json_decode($line, true);
                if (!is_array($obj)) {
                    continue;
                }
                $id = isset($obj['id']) ? (int) $obj['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                if ($beforeId > 0 && $id >= $beforeId) {
                    continue;
                }
                if (!self::rowMatches($obj, $q, $ok, $apiid)) {
                    continue;
                }
                $obj['username'] = '';
                $rows[] = $obj;
            }
            usort($rows, function ($a, $b) {
                return ((int) $b['id']) - ((int) $a['id']);
            });
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
        $dir = self::rootDir() . '/' . $day;
        $index = self::readJson($dir . '/index.json');
        if (!is_array($index) || empty($index['shards'])) {
            return null;
        }
        foreach ($index['shards'] as $sh) {
            $min = isset($sh['min_id']) ? (int) $sh['min_id'] : 0;
            $max = isset($sh['max_id']) ? (int) $sh['max_id'] : 0;
            if ($min > 0 && $max > 0 && ($id < $min || $id > $max)) {
                continue;
            }
            $file = isset($sh['file']) ? (string) $sh['file'] : '';
            if ($file === '' || strpos($file, '..') !== false) {
                continue;
            }
            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $fh = fopen($path, 'rb');
            if (!$fh) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                $obj = json_decode(trim($line), true);
                if (!is_array($obj)) {
                    continue;
                }
                if ((int) (isset($obj['id']) ? $obj['id'] : 0) === $id) {
                    fclose($fh);
                    $obj['username'] = '';
                    return $obj;
                }
            }
            fclose($fh);
        }
        return null;
    }

    /**
     * @param array  $row
     * @param string $q
     * @param mixed  $ok
     * @param int    $apiid
     * @return bool
     */
    private static function rowMatches(array $row, $q, $ok, $apiid)
    {
        if ($apiid > 0 && (int) (isset($row['apiid']) ? $row['apiid'] : 0) !== $apiid) {
            return false;
        }
        if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
            if ((int) (isset($row['ok']) ? $row['ok'] : 0) !== (int) $ok) {
                return false;
            }
        }
        if ($q === '') {
            return true;
        }
        $hay = strtolower(
            (isset($row['apiname']) ? $row['apiname'] : '') . ' ' .
            (isset($row['path']) ? $row['path'] : '') . ' ' .
            (isset($row['ip']) ? $row['ip'] : '') . ' ' .
            (isset($row['url']) ? $row['url'] : '') . ' ' .
            (isset($row['apikey']) ? $row['apikey'] : '') . ' ' .
            (isset($row['domain']) ? $row['domain'] : '') . ' ' .
            (isset($row['iploc']) ? $row['iploc'] : '')
        );
        return strpos($hay, strtolower($q)) !== false;
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
        self::writeJson(self::rootDir() . '/catalog.json', $catalog);
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
