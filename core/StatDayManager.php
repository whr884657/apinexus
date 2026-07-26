<?php
/**
 * 文件：core/StatDayManager.php
 * 作用：控制台按日调用聚合（statday，滚动固定 30 天）
 *
 * 写入：ApiStats 每次记账成功后 recordHit（与 apilog / api.calls 三写）
 * 读取：DashboardStats 今日 KPI、趋势、TOP 优先读本表
 */

class StatDayManager
{
    const KEEP_DAYS = 30;
    const TOP_LIMIT = 10;
    /** Redis：当日各接口计数 Hash 前缀 */
    const KEY_TOPMAP_PREFIX = 'cache:statday:topmap:';

    /** @var bool|null */
    private static $ready = null;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('statday');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $pdo = Database::connect();
            $t = self::table();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
            self::$ready = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            self::$ready = false;
        }
        return self::$ready;
    }

    /**
     * 强制刷新 tableReady 探测（迁移后）
     *
     * @return void
     */
    public static function resetReadyCache()
    {
        self::$ready = null;
    }

    /**
     * 单次调用写入日表（失败静默，不影响业务）
     *
     * @param int  $apiId
     * @param bool $ok
     * @param int  $charged 1=积分调用
     * @param bool $hasKey  是否带有效/明文密钥（与趋势口径一致：非空 apikey）
     * @return void
     */
    public static function recordHit($apiId, $ok, $charged, $hasKey)
    {
        if (!self::tableReady()) {
            return;
        }
        $apiId = (int) $apiId;
        if ($apiId <= 0) {
            return;
        }
        try {
            $day = date('Y-m-d');
            self::ensureDay($day);
            $okInc = $ok ? 1 : 0;
            $failInc = $ok ? 0 : 1;
            $guestInc = 0;
            $keyInc = 0;
            $pointsInc = 0;
            if ((int) $charged === 1) {
                $pointsInc = 1;
            } elseif ($hasKey) {
                $keyInc = 1;
            } else {
                $guestInc = 1;
            }
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET
                    `calls` = `calls` + 1,
                    `okcount` = `okcount` + ?,
                    `failcount` = `failcount` + ?,
                    `guestcalls` = `guestcalls` + ?,
                    `keycalls` = `keycalls` + ?,
                    `pointscalls` = `pointscalls` + ?,
                    `updatetime` = NOW()
                 WHERE `statdate` = ?'
            );
            $stmt->execute(array($okInc, $failInc, $guestInc, $keyInc, $pointsInc, $day));
            self::bumpTopMap($day, $apiId);
            // TOP JSON 不必每次刷库：约 1/8 请求刷一次，降低写放大
            if (mt_rand(1, 8) === 1) {
                self::flushTopJson($day);
            }
            if (class_exists('RedisCache')) {
                RedisCache::forget(RedisCache::KEY_APILOG_TODAY);
            }
            if (mt_rand(1, 40) === 1) {
                self::pruneOld();
            }
        } catch (Exception $e) {
            // 聚合失败不影响业务
        }
    }

    /**
     * 确保某日行存在；新建时顺带滚动清理
     *
     * @param string $day Y-m-d
     * @return void
     */
    public static function ensureDay($day)
    {
        $day = self::normalizeDay($day);
        if ($day === '') {
            return;
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO `' . self::table() . '`
                (`statdate`, `calls`, `okcount`, `failcount`, `guestcalls`, `keycalls`, `pointscalls`, `topjson`, `updatetime`)
             VALUES (?, 0, 0, 0, 0, 0, 0, ?, NOW())'
        );
        $stmt->execute(array($day, '[]'));
        if ($stmt->rowCount() > 0) {
            self::pruneOld();
        }
    }

    /**
     * 删除超过滚动窗口的最早天（保留含今天共 KEEP_DAYS 天）
     *
     * @return int
     */
    public static function pruneOld()
    {
        if (!self::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $cutoff = date('Y-m-d', strtotime('-' . (self::KEEP_DAYS - 1) . ' day'));
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `statdate` < ?');
            $stmt->execute(array($cutoff));
            return (int) $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 从 apilog 回填近 N 天（上线空洞修补；幂等覆盖）
     *
     * @param int $days
     * @return array{ok:bool,days:int,msg:string}
     */
    public static function backfillLastDays($days = null)
    {
        $days = $days === null ? self::KEEP_DAYS : max(1, min(90, (int) $days));
        self::resetReadyCache();
        if (!self::tableReady()) {
            return array('ok' => false, 'days' => 0, 'msg' => 'statday 表不存在');
        }
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            self::ensureDay(date('Y-m-d'));
            self::pruneOld();
            return array('ok' => true, 'days' => 0, 'msg' => '无 apilog，已建今日空行');
        }
        $filled = 0;
        try {
            $pdo = Database::connect();
            $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day'));
            $agg = $pdo->prepare(
                'SELECT DATE(`createtime`) AS d,
                    COUNT(*) AS calls,
                    SUM(CASE WHEN `ok` = 1 THEN 1 ELSE 0 END) AS ok_c,
                    SUM(CASE WHEN `ok` = 0 THEN 1 ELSE 0 END) AS fail_c,
                    SUM(CASE WHEN `charged` = 1 THEN 1 ELSE 0 END) AS points_c,
                    SUM(CASE WHEN `charged` = 0 AND `apikey` <> \'\' THEN 1 ELSE 0 END) AS key_c,
                    SUM(CASE WHEN `charged` = 0 AND (`apikey` = \'\' OR `apikey` IS NULL) THEN 1 ELSE 0 END) AS guest_c
                 FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ?
                 GROUP BY DATE(`createtime`)'
            );
            $agg->execute(array($start));
            $byDay = array();
            foreach ($agg->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = (string) $r['d'];
                $byDay[$d] = array(
                    'calls'       => (int) $r['calls'],
                    'okcount'     => (int) $r['ok_c'],
                    'failcount'   => (int) $r['fail_c'],
                    'guestcalls'  => (int) $r['guest_c'],
                    'keycalls'    => (int) $r['key_c'],
                    'pointscalls' => (int) $r['points_c'],
                );
            }
            $topStmt = $pdo->prepare(
                'SELECT `apiid`, COUNT(*) AS c
                 FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ? AND `createtime` <= ?
                 GROUP BY `apiid`
                 ORDER BY c DESC
                 LIMIT ' . (int) self::TOP_LIMIT
            );
            for ($i = $days - 1; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime('-' . $i . ' day'));
                $row = isset($byDay[$day]) ? $byDay[$day] : array(
                    'calls' => 0, 'okcount' => 0, 'failcount' => 0,
                    'guestcalls' => 0, 'keycalls' => 0, 'pointscalls' => 0,
                );
                $topStmt->execute(array($day . ' 00:00:00', $day . ' 23:59:59'));
                $top = array();
                $rank = 1;
                foreach ($topStmt->fetchAll(PDO::FETCH_ASSOC) as $tr) {
                    $aid = (int) $tr['apiid'];
                    if ($aid <= 0) {
                        continue;
                    }
                    $top[] = array(
                        'apiid' => $aid,
                        'rank'  => $rank,
                        'calls' => (int) $tr['c'],
                    );
                    $rank++;
                }
                $json = json_encode($top, JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    $json = '[]';
                }
                $up = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                        (`statdate`, `calls`, `okcount`, `failcount`, `guestcalls`, `keycalls`, `pointscalls`, `topjson`, `updatetime`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                        `calls` = VALUES(`calls`),
                        `okcount` = VALUES(`okcount`),
                        `failcount` = VALUES(`failcount`),
                        `guestcalls` = VALUES(`guestcalls`),
                        `keycalls` = VALUES(`keycalls`),
                        `pointscalls` = VALUES(`pointscalls`),
                        `topjson` = VALUES(`topjson`),
                        `updatetime` = NOW()'
                );
                $up->execute(array(
                    $day,
                    $row['calls'],
                    $row['okcount'],
                    $row['failcount'],
                    $row['guestcalls'],
                    $row['keycalls'],
                    $row['pointscalls'],
                    $json,
                ));
                $filled++;
            }
            self::pruneOld();
            if (class_exists('RedisCache')) {
                RedisCache::forget(RedisCache::KEY_APILOG_TODAY);
            }
            return array('ok' => true, 'days' => $filled, 'msg' => '已回填 ' . $filled . ' 天');
        } catch (Exception $e) {
            return array('ok' => false, 'days' => $filled, 'msg' => '回填失败');
        }
    }

    /**
     * @param string $day
     * @return array|null
     */
    public static function getDay($day)
    {
        if (!self::tableReady()) {
            return null;
        }
        $day = self::normalizeDay($day);
        if ($day === '') {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('SELECT * FROM `' . self::table() . '` WHERE `statdate` = ? LIMIT 1');
            $stmt->execute(array($day));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return array|null
     */
    public static function todayRow()
    {
        return self::getDay(date('Y-m-d'));
    }

    /**
     * @return int
     */
    public static function todayCalls()
    {
        $row = self::todayRow();
        return $row ? (int) $row['calls'] : 0;
    }

    /**
     * @return array{ok:int,fail:int}
     */
    public static function todayOkFail()
    {
        $row = self::todayRow();
        if (!$row) {
            return array('ok' => 0, 'fail' => 0);
        }
        return array(
            'ok'   => (int) $row['okcount'],
            'fail' => (int) $row['failcount'],
        );
    }

    /**
     * 近 N 天行（按日期升序；缺日补零结构）
     *
     * @param int $days
     * @return array<string,array>
     */
    public static function mapLastDays($days)
    {
        $days = max(1, min(self::KEEP_DAYS, (int) $days));
        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' day'));
            $out[$d] = array(
                'calls'       => 0,
                'okcount'     => 0,
                'failcount'   => 0,
                'guestcalls'  => 0,
                'keycalls'    => 0,
                'pointscalls' => 0,
                'topjson'     => '[]',
            );
        }
        if (!self::tableReady()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . self::table() . '` WHERE `statdate` >= ? ORDER BY `statdate` ASC'
            );
            $stmt->execute(array($start));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = (string) $r['statdate'];
                if (!isset($out[$d])) {
                    continue;
                }
                $out[$d] = array(
                    'calls'       => (int) $r['calls'],
                    'okcount'     => (int) $r['okcount'],
                    'failcount'   => (int) $r['failcount'],
                    'guestcalls'  => (int) $r['guestcalls'],
                    'keycalls'    => (int) $r['keycalls'],
                    'pointscalls' => (int) $r['pointscalls'],
                    'topjson'     => isset($r['topjson']) ? (string) $r['topjson'] : '[]',
                );
            }
        } catch (Exception $e) {
            return $out;
        }
        return $out;
    }

    /**
     * 区间总调用
     *
     * @param string $startDay
     * @param string $endDay
     * @return int
     */
    public static function sumCallsBetween($startDay, $endDay)
    {
        if (!self::tableReady()) {
            return 0;
        }
        $startDay = self::normalizeDay($startDay);
        $endDay = self::normalizeDay($endDay);
        if ($startDay === '' || $endDay === '') {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(`calls`), 0) FROM `' . self::table() . '`
                 WHERE `statdate` >= ? AND `statdate` <= ?'
            );
            $stmt->execute(array($startDay, $endDay));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 解析 topjson 为控制台 TOP 列表（含接口名）
     *
     * @param string|null $json
     * @param int         $limit
     * @return array
     */
    public static function topListFromJson($json, $limit = 10)
    {
        $limit = max(1, min(20, (int) $limit));
        $list = array();
        $raw = json_decode((string) $json, true);
        if (!is_array($raw)) {
            return $list;
        }
        $ids = array();
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $aid = (int) (isset($item['apiid']) ? $item['apiid'] : 0);
            if ($aid > 0) {
                $ids[] = $aid;
            }
        }
        $names = self::apiNamesByIds($ids);
        $max = 0;
        $tmp = array();
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $aid = (int) (isset($item['apiid']) ? $item['apiid'] : 0);
            $c = (int) (isset($item['calls']) ? $item['calls'] : 0);
            if ($aid <= 0 || $c <= 0) {
                continue;
            }
            if ($c > $max) {
                $max = $c;
            }
            $name = isset($names[$aid]) ? $names[$aid] : ('接口#' . $aid);
            $tmp[] = array(
                'apiid' => $aid,
                'name'  => $name,
                'count' => $c,
            );
            if (count($tmp) >= $limit) {
                break;
            }
        }
        foreach ($tmp as $row) {
            $row['pct'] = $max > 0 ? round($row['count'] * 100 / $max, 1) : 0;
            $list[] = $row;
        }
        return $list;
    }

    /**
     * @param string $day
     * @param int    $apiId
     * @return void
     */
    private static function bumpTopMap($day, $apiId)
    {
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            self::bumpTopJsonSql($day, $apiId);
            return;
        }
        try {
            RedisService::withClient(function (Redis $redis) use ($day, $apiId) {
                $key = RedisService::buildKey(self::KEY_TOPMAP_PREFIX . $day);
                $redis->hIncrBy($key, (string) (int) $apiId, 1);
                $redis->expire($key, 86400 * 2);
            });
        } catch (Exception $e) {
            self::bumpTopJsonSql($day, $apiId);
        }
    }

    /**
     * 无 Redis 时：读改写 topjson（允许偶发竞态）
     *
     * @param string $day
     * @param int    $apiId
     * @return void
     */
    private static function bumpTopJsonSql($day, $apiId)
    {
        $row = self::getDay($day);
        $map = array();
        if ($row && !empty($row['topjson'])) {
            $decoded = json_decode((string) $row['topjson'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $aid = (int) (isset($item['apiid']) ? $item['apiid'] : 0);
                    $c = (int) (isset($item['calls']) ? $item['calls'] : 0);
                    if ($aid > 0) {
                        $map[$aid] = $c;
                    }
                }
            }
        }
        if (!isset($map[$apiId])) {
            $map[$apiId] = 0;
        }
        $map[$apiId]++;
        arsort($map, SORT_NUMERIC);
        $top = array();
        $rank = 1;
        foreach ($map as $aid => $c) {
            $top[] = array('apiid' => (int) $aid, 'rank' => $rank, 'calls' => (int) $c);
            $rank++;
            if ($rank > self::TOP_LIMIT) {
                break;
            }
        }
        self::saveTopJson($day, $top);
    }

    /**
     * 将 Redis Hash 刷入 topjson
     *
     * @param string $day
     * @return void
     */
    private static function flushTopJson($day)
    {
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        try {
            $map = RedisService::withClient(function (Redis $redis) use ($day) {
                $key = RedisService::buildKey(self::KEY_TOPMAP_PREFIX . $day);
                $all = $redis->hGetAll($key);
                return is_array($all) ? $all : array();
            });
            if (!is_array($map) || count($map) === 0) {
                return;
            }
            $nums = array();
            foreach ($map as $aid => $c) {
                $aid = (int) $aid;
                $c = (int) $c;
                if ($aid > 0 && $c > 0) {
                    $nums[$aid] = $c;
                }
            }
            arsort($nums, SORT_NUMERIC);
            $top = array();
            $rank = 1;
            foreach ($nums as $aid => $c) {
                $top[] = array('apiid' => (int) $aid, 'rank' => $rank, 'calls' => (int) $c);
                $rank++;
                if ($rank > self::TOP_LIMIT) {
                    break;
                }
            }
            self::saveTopJson($day, $top);
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * @param string $day
     * @param array  $top
     * @return void
     */
    private static function saveTopJson($day, array $top)
    {
        $json = json_encode($top, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '[]';
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'UPDATE `' . self::table() . '` SET `topjson` = ?, `updatetime` = NOW() WHERE `statdate` = ?'
        );
        $stmt->execute(array($json, $day));
    }

    /**
     * @param int[] $ids
     * @return array<int,string>
     */
    private static function apiNamesByIds(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) === 0 || !class_exists('ApiManager') || !ApiManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $place = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                'SELECT `id`, `name` FROM `' . ApiManager::table() . '` WHERE `id` IN (' . $place . ')'
            );
            $stmt->execute($ids);
            $out = array();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $name = trim((string) $r['name']);
                $out[(int) $r['id']] = $name !== '' ? $name : ('接口#' . (int) $r['id']);
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @param string $day
     * @return string
     */
    private static function normalizeDay($day)
    {
        $day = trim((string) $day);
        if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return '';
        }
        return $day;
    }
}
