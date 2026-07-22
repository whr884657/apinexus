<?php
/**
 * 文件：core/ApiLogManager.php
 * 作用：API 调用日志查询（时间窗 / 热冷合并 / keyset / 短 TTL；冷数据见 ApiLogArchive）
 */

class ApiLogManager
{
    /** 默认查询最近天数 */
    const DEFAULT_QUERY_DAYS = 7;
    /** 查询天数上限（可覆盖冷归档） */
    const MAX_QUERY_DAYS = 365;
    /** SELECT 会话超时（毫秒，MySQL 8+；不支持则忽略） */
    const QUERY_TIMEOUT_MS = 5000;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('apilog');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        return class_exists('ApiStats') && ApiStats::tableReady();
    }

    /**
     * 是否记录详细调用日志（关闭时仅累加 api.calls，不写 apilog）
     *
     * @return bool
     */
    public static function detailEnabled()
    {
        try {
            return trim((string) Config::get('apilog_detail', '1')) !== '0';
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * 后台列表默认查询天数
     *
     * @return int
     */
    public static function queryDaysDefault()
    {
        try {
            $n = (int) Config::get('apilog_query_days', (string) self::DEFAULT_QUERY_DAYS);
        } catch (Exception $e) {
            $n = self::DEFAULT_QUERY_DAYS;
        }
        return self::clampQueryDays($n);
    }

    /**
     * 热数据天数（超出由归档任务迁到本地冷库）
     *
     * @return int
     */
    public static function keepDays()
    {
        return ApiLogArchive::hotDays();
    }

    /**
     * @param int $days
     * @return int
     */
    public static function clampQueryDays($days)
    {
        $days = (int) $days;
        if ($days < 1) {
            $days = self::DEFAULT_QUERY_DAYS;
        }
        if ($days > self::MAX_QUERY_DAYS) {
            $days = self::MAX_QUERY_DAYS;
        }
        return $days;
    }

    /**
     * @param string $method
     * @return string
     */
    public static function methodClass($method)
    {
        $m = strtoupper(trim((string) $method));
        if ($m === 'GET') {
            return 'is-get';
        }
        if ($m === 'POST') {
            return 'is-post';
        }
        if ($m === 'PUT') {
            return 'is-put';
        }
        if ($m === 'DELETE') {
            return 'is-delete';
        }
        if ($m === 'PATCH') {
            return 'is-patch';
        }
        return 'is-other';
    }

    /**
     * @param int $code
     * @return string
     */
    public static function httpClass($code)
    {
        $code = (int) $code;
        if ($code >= 200 && $code < 300) {
            return 'is-2xx';
        }
        if ($code >= 300 && $code < 400) {
            return 'is-3xx';
        }
        if ($code >= 400 && $code < 500) {
            return 'is-4xx';
        }
        if ($code >= 500) {
            return 'is-5xx';
        }
        return '';
    }

    /**
     * @param array $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row)) {
            return null;
        }
        $ok = (int) (isset($row['ok']) ? $row['ok'] : 0) === 1;
        $apitype = (int) (isset($row['apitype']) ? $row['apitype'] : 0);
        $charged = (int) (isset($row['charged']) ? $row['charged'] : 0) === 1;
        $apikey = isset($row['apikey']) ? (string) $row['apikey'] : '';
        $method = isset($row['method']) ? (string) $row['method'] : '';
        $httpcode = (int) (isset($row['httpcode']) ? $row['httpcode'] : 0);
        $userid = (int) (isset($row['userid']) ? $row['userid'] : 0);
        $username = isset($row['username']) ? trim((string) $row['username']) : '';
        $iploc = isset($row['iploc']) ? trim((string) $row['iploc']) : '';

        return array(
            'id'            => (int) (isset($row['id']) ? $row['id'] : 0),
            'apiid'         => (int) (isset($row['apiid']) ? $row['apiid'] : 0),
            'apiname'       => isset($row['apiname']) ? (string) $row['apiname'] : '',
            'apitype'       => $apitype,
            'apitype_label' => $apitype === 1 ? '代理' : '本地',
            'userid'        => $userid,
            'username'      => $username,
            'user_label'    => $userid > 0
                ? ($username !== '' ? $username : ('用户 #' . $userid))
                : '匿名',
            'apikey'        => $apikey,
            'method'        => $method,
            'method_class'  => self::methodClass($method),
            'ip'            => isset($row['ip']) ? (string) $row['ip'] : '',
            'iploc'         => $iploc,
            'host'          => isset($row['host']) ? (string) $row['host'] : '',
            'path'          => isset($row['path']) ? (string) $row['path'] : '',
            'url'           => isset($row['url']) ? (string) $row['url'] : '',
            'referer'       => isset($row['referer']) ? (string) $row['referer'] : '',
            'origin'        => isset($row['origin']) ? (string) $row['origin'] : '',
            'domain'        => isset($row['domain']) ? (string) $row['domain'] : '',
            'ua'            => isset($row['ua']) ? (string) $row['ua'] : '',
            'ok'            => $ok ? 1 : 0,
            'ok_label'      => $ok ? '成功' : '失败',
            'ok_class'      => $ok ? 'is-ok' : 'is-fail',
            'httpcode'      => $httpcode,
            'http_class'    => self::httpClass($httpcode),
            'charged'       => $charged ? 1 : 0,
            'charged_label' => $charged ? '已扣费' : '未扣费',
            'cost'          => number_format((float) (isset($row['cost']) ? $row['cost'] : 0), 4, '.', ''),
            'createtime'    => isset($row['createtime']) ? (string) $row['createtime'] : '',
        );
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function findById($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        if (self::tableReady()) {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);
                $sql = 'SELECT l.*, u.`username`
                    FROM `' . self::table() . '` l
                    LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`
                    WHERE l.`id` = ? LIMIT 1';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return self::formatRow($row);
                }
            } catch (Exception $e) {
                // fall through to cold
            }
        }
        if (class_exists('ApiLogArchive')) {
            $cold = ApiLogArchive::findById($id);
            if ($cold) {
                return self::formatRow($cold);
            }
        }
        return null;
    }

    /**
     * 今日调用次数（短 TTL 缓存，供首页统计等复用）
     *
     * @return int
     */
    public static function countToday()
    {
        if (!self::tableReady()) {
            return 0;
        }
        return (int) RedisCache::remember(
            RedisCache::KEY_APILOG_TODAY,
            RedisCache::TTL_APILOG_STATS,
            function () {
                try {
                    $pdo = Database::connect();
                    self::applyQueryTimeout($pdo);
                    $stmt = $pdo->query(
                        'SELECT COUNT(*) FROM `' . self::table() . '`
                         WHERE `createtime` >= CURDATE() AND `createtime` < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
                    );
                    return max(0, (int) $stmt->fetchColumn());
                } catch (Exception $e) {
                    return 0;
                }
            }
        );
    }

    /**
     * 分页列表（强制时间窗 + COUNT 无 JOIN + keyset + 短 TTL）
     *
     * @param array $opts page, pagesize, q, ok(null|0|1), apiid, days, before_id
     * @return array{list:array,total:int,page:int,pagesize:int,days:int,before_id:int,next_before_id:int,has_more:bool,total_approx:bool}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;
        $days = isset($opts['days']) ? self::clampQueryDays((int) $opts['days']) : self::queryDaysDefault();
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $empty = array(
            'list'           => array(),
            'total'          => 0,
            'page'           => $page,
            'pagesize'       => $pagesize,
            'days'           => $days,
            'before_id'      => $beforeId,
            'next_before_id' => 0,
            'has_more'       => false,
            'total_approx'   => false,
        );
        if (!self::tableReady()) {
            return $empty;
        }

        $cacheKey = RedisCache::apilogPageKey(array(
            'page'      => $page,
            'pagesize'  => $pagesize,
            'q'         => $q,
            'ok'        => $ok,
            'apiid'     => $apiid,
            'days'      => $days,
            'before_id' => $beforeId,
        ));

        return RedisCache::remember(
            $cacheKey,
            RedisCache::TTL_APILOG_PAGE,
            function () use ($page, $pagesize, $q, $ok, $apiid, $days, $beforeId, $empty) {
                try {
                    $pdo = Database::connect();
                    self::applyQueryTimeout($pdo);

                    $filters = self::buildFilters($q, $ok, $apiid, $days, $beforeId);
                    $needUserJoin = ($q !== '');

                    $totalMeta = self::resolveTotal($pdo, $filters, $q, $ok, $apiid, $days);
                    $total = (int) $totalMeta['total'];
                    $totalApprox = !empty($totalMeta['approx']);

                    $from = '`' . self::table() . '` l';
                    if ($needUserJoin) {
                        $from .= ' LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`';
                    }

                    $select = $needUserJoin ? 'SELECT l.*, u.`username`' : 'SELECT l.*, \'\' AS `username`';
                    $sql = $select . ' FROM ' . $from
                        . ' WHERE ' . $filters['whereSql']
                        . ' ORDER BY l.`id` DESC LIMIT ' . ((int) $pagesize + 1);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($filters['bind']);
                    $hotRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $merged = array();
                    foreach ($hotRows as $row) {
                        $item = self::formatRow($row);
                        if ($item !== null) {
                            $merged[] = $item;
                        }
                    }

                    $needMore = ($pagesize + 1) - count($merged);
                    if ($needMore > 0 && class_exists('ApiLogArchive')) {
                        $cold = ApiLogArchive::listInQueryWindow(array(
                            'days'      => $days,
                            'before_id' => $beforeId,
                            'pagesize'  => $needMore,
                            'q'         => $q,
                            'ok'        => $ok,
                            'apiid'     => $apiid,
                        ));
                        foreach ($cold['list'] as $crow) {
                            $item = self::formatRow($crow);
                            if ($item === null) {
                                continue;
                            }
                            $dup = false;
                            foreach ($merged as $m) {
                                if ((int) $m['id'] === (int) $item['id']) {
                                    $dup = true;
                                    break;
                                }
                            }
                            if (!$dup) {
                                $merged[] = $item;
                            }
                        }
                    }

                    usort($merged, function ($a, $b) {
                        return ((int) $b['id']) - ((int) $a['id']);
                    });

                    $hasMore = count($merged) > $pagesize;
                    if ($hasMore) {
                        $merged = array_slice($merged, 0, $pagesize);
                    }

                    $nextBefore = 0;
                    if (!empty($merged)) {
                        $nextBefore = (int) $merged[count($merged) - 1]['id'];
                    }

                    return array(
                        'list'           => $merged,
                        'total'          => $total,
                        'page'           => $page,
                        'pagesize'       => $pagesize,
                        'days'           => $days,
                        'before_id'      => $beforeId,
                        'next_before_id' => $nextBefore,
                        'has_more'       => $hasMore,
                        'total_approx'   => $totalApprox,
                    );
                } catch (Exception $e) {
                    return $empty;
                }
            }
        );
    }

    /**
     * @param PDO $pdo
     * @return void
     */
    private static function applyQueryTimeout($pdo)
    {
        try {
            $pdo->exec('SET SESSION MAX_EXECUTION_TIME=' . (int) self::QUERY_TIMEOUT_MS);
        } catch (Exception $e) {
            // MySQL 5.7 / MariaDB 等不支持则忽略
        }
    }

    /**
     * @param string     $q
     * @param mixed      $ok
     * @param int        $apiid
     * @param int        $days
     * @param int        $beforeId
     * @return array{whereSql:string,bind:array,hasExtra:bool}
     */
    private static function buildFilters($q, $ok, $apiid, $days, $beforeId)
    {
        $where = array('l.`createtime` >= DATE_SUB(NOW(), INTERVAL ? DAY)');
        $bind = array((int) $days);
        $hasExtra = false;

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(l.`apiname` LIKE ? OR l.`path` LIKE ? OR l.`ip` LIKE ? OR l.`url` LIKE ? OR l.`apikey` LIKE ? OR l.`domain` LIKE ? OR l.`iploc` LIKE ? OR u.`username` LIKE ?)';
            for ($i = 0; $i < 8; $i++) {
                $bind[] = $like;
            }
            $hasExtra = true;
        }
        if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
            $where[] = 'l.`ok` = ?';
            $bind[] = (int) $ok;
            $hasExtra = true;
        }
        if ($apiid > 0) {
            $where[] = 'l.`apiid` = ?';
            $bind[] = $apiid;
            $hasExtra = true;
        }
        if ($beforeId > 0) {
            $where[] = 'l.`id` < ?';
            $bind[] = $beforeId;
        }

        return array(
            'whereSql' => implode(' AND ', $where),
            'bind'     => $bind,
            'hasExtra' => $hasExtra,
        );
    }

    /**
     * COUNT 永不 JOIN；无额外筛选时走独立较长 TTL 缓存
     *
     * @param PDO    $pdo
     * @param array  $filters
     * @param string $q
     * @param mixed  $ok
     * @param int    $apiid
     * @param int    $days
     * @return array{total:int,approx:bool}
     */
    private static function resolveTotal($pdo, array $filters, $q, $ok, $apiid, $days)
    {
        $hasExtra = !empty($filters['hasExtra']);

        if (!$hasExtra) {
            $cacheKey = RedisCache::apilogRangeTotalKey($days);
            $cached = RedisCache::remember(
                $cacheKey,
                RedisCache::TTL_APILOG_RANGE_TOTAL,
                function () use ($pdo, $days) {
                    $stmt = $pdo->prepare(
                        'SELECT COUNT(*) FROM `' . self::table() . '`
                         WHERE `createtime` >= DATE_SUB(NOW(), INTERVAL ? DAY)'
                    );
                    $stmt->execute(array((int) $days));
                    $hot = max(0, (int) $stmt->fetchColumn());
                    $cold = class_exists('ApiLogArchive') ? ApiLogArchive::countInQueryWindow($days) : 0;
                    return $hot + $cold;
                }
            );
            return array('total' => (int) $cached, 'approx' => true);
        }

        // 有筛选：热库精确 COUNT；冷库在列表侧合并（总数为热库结果，允许近似）
        $where = array('`createtime` >= DATE_SUB(NOW(), INTERVAL ? DAY)');
        $bind = array((int) $days);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(`apiname` LIKE ? OR `path` LIKE ? OR `ip` LIKE ? OR `url` LIKE ? OR `apikey` LIKE ? OR `domain` LIKE ? OR `iploc` LIKE ?
                OR EXISTS (SELECT 1 FROM `' . Database::table('user') . '` u WHERE u.`id` = `' . self::table() . '`.`userid` AND u.`username` LIKE ?))';
            for ($i = 0; $i < 8; $i++) {
                $bind[] = $like;
            }
        }
        if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
            $where[] = '`ok` = ?';
            $bind[] = (int) $ok;
        }
        if ($apiid > 0) {
            $where[] = '`apiid` = ?';
            $bind[] = $apiid;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . self::table() . '` WHERE ' . implode(' AND ', $where));
        $stmt->execute($bind);
        $hot = max(0, (int) $stmt->fetchColumn());
        $cold = class_exists('ApiLogArchive') ? ApiLogArchive::countInQueryWindow($days) : 0;
        return array('total' => $hot + $cold, 'approx' => $cold > 0);
    }
}