<?php
/**
 * 文件：core/ApiLogManager.php
 * 作用：API 调用日志查询（每页条数 + keyset / 热冷合并 / 短 TTL；冷数据见 ApiLogArchive）
 */

class ApiLogManager
{
    /** @deprecated 列表已取消「近 N 天」；保留常量以免旧配置报错 */
    const DEFAULT_QUERY_DAYS = 7;
    /** @deprecated */
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
     * 日志展示用密钥脱敏（保留首尾，中间打码）
     *
     * @param string $apikey
     * @return string
     */
    public static function maskApikey($apikey)
    {
        $apikey = trim((string) $apikey);
        $len = strlen($apikey);
        if ($len <= 0) {
            return '';
        }
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($apikey, 0, 2) . str_repeat('*', max(4, $len - 6)) . substr($apikey, -4);
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
     * 状态码可读说明（与 ApiStats 守卫文案对齐；未知码给区间提示）
     *
     * @param int $code
     * @return string
     */
    public static function httpcodeLabel($code)
    {
        $code = (int) $code;
        $map = array(
            200 => '调用成功',
            201 => '已创建',
            204 => '无内容',
            301 => '永久重定向',
            302 => '代理跳转成功',
            304 => '未修改',
            400 => '请求参数有误',
            401 => '未提供密钥或密钥错误',
            402 => '积分余额不足',
            403 => '接口不可用或密钥已禁用',
            404 => '接口不存在',
            405 => '请求方法不允许',
            408 => '请求超时',
            429 => '请求过于频繁（QPM 限制）',
            500 => '服务器内部错误',
            502 => '上游网关错误',
            503 => '服务暂不可用',
            504 => '上游网关超时',
        );
        if (isset($map[$code])) {
            return $map[$code];
        }
        if ($code <= 0) {
            return '未记录状态码';
        }
        if ($code >= 200 && $code < 300) {
            return '成功类响应';
        }
        if ($code >= 300 && $code < 400) {
            return '重定向类响应';
        }
        if ($code >= 400 && $code < 500) {
            return '客户端错误';
        }
        if ($code >= 500 && $code < 600) {
            return '服务端错误';
        }
        return 'HTTP ' . $code;
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
        $httpLabel = self::httpcodeLabel($httpcode);

        return array(
            'id'             => (int) (isset($row['id']) ? $row['id'] : 0),
            'apiid'          => (int) (isset($row['apiid']) ? $row['apiid'] : 0),
            'apiname'        => isset($row['apiname']) ? (string) $row['apiname'] : '',
            'apitype'        => $apitype,
            'apitype_label'  => $apitype === 1 ? '代理' : '本地',
            'userid'         => $userid,
            'username'       => $username,
            'user_label'     => $userid > 0
                ? ($username !== '' ? $username : ('用户 #' . $userid))
                : '匿名',
            // apikey=完整（管理员可点眼睛查看）；apikey_masked=默认展示
            'apikey'         => $apikey,
            'apikey_masked'  => self::maskApikey($apikey),
            'method'         => $method,
            'method_class'   => self::methodClass($method),
            'ip'             => isset($row['ip']) ? (string) $row['ip'] : '',
            'iploc'          => $iploc,
            'host'           => isset($row['host']) ? (string) $row['host'] : '',
            'path'           => isset($row['path']) ? (string) $row['path'] : '',
            'url'            => isset($row['url']) ? (string) $row['url'] : '',
            'referer'        => isset($row['referer']) ? (string) $row['referer'] : '',
            'origin'         => isset($row['origin']) ? (string) $row['origin'] : '',
            'domain'         => isset($row['domain']) ? (string) $row['domain'] : '',
            'ua'             => isset($row['ua']) ? (string) $row['ua'] : '',
            'ok'             => $ok ? 1 : 0,
            'ok_label'       => $ok ? '成功' : '失败',
            'ok_class'       => $ok ? 'is-ok' : 'is-fail',
            'httpcode'       => $httpcode,
            'httpcode_label' => $httpLabel,
            'http_class'     => self::httpClass($httpcode),
            'charged'        => $charged ? 1 : 0,
            'charged_label'  => $charged ? '已扣费' : '未扣费',
            'cost'           => number_format((float) (isset($row['cost']) ? $row['cost'] : 0), 4, '.', ''),
            'createtime'     => isset($row['createtime']) ? (string) $row['createtime'] : '',
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
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            return (int) RedisCache::remember(
                RedisCache::KEY_APILOG_TODAY,
                RedisCache::TTL_APILOG_STATS,
                function () {
                    return StatDayManager::todayCalls();
                }
            );
        }
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
     * 分页列表：仅按底部「每页条数」+ before_id keyset 取最新记录。
     * 禁止「近 N 天」时间窗、禁止深页 OFFSET；筛选总数短 TTL 缓存后供底栏「共 N 条」。
     *
     * @param array $opts page, pagesize, q, ok(null|0|1), apiid, before_id
     * @return array{list:array,total:int,page:int,pagesize:int,before_id:int,next_before_id:int,has_more:bool,total_approx:bool}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $empty = array(
            'list'           => array(),
            'total'          => 0,
            'page'           => $page,
            'pagesize'       => $pagesize,
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
            'before_id' => $beforeId,
        ));

        return RedisCache::remember(
            $cacheKey,
            RedisCache::TTL_APILOG_PAGE,
            function () use ($page, $pagesize, $q, $ok, $apiid, $beforeId, $empty) {
                try {
                    $pdo = Database::connect();
                    self::applyQueryTimeout($pdo);

                    $filters = self::buildFilters($q, $ok, $apiid, $beforeId);
                    $needUserJoin = ($q !== '');

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
                            'days'      => 0,
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

                    $total = self::countFilteredCached($q, $ok, $apiid);

                    return array(
                        'list'           => $merged,
                        'total'          => $total,
                        'page'           => $page,
                        'pagesize'       => $pagesize,
                        'before_id'      => $beforeId,
                        'next_before_id' => $nextBefore,
                        'has_more'       => $hasMore,
                        'total_approx'   => false,
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
     * 当前筛选条件下热库总数（短 TTL 缓存；不含 before_id）
     *
     * @param string $q
     * @param mixed  $ok
     * @param int    $apiid
     * @return int
     */
    private static function countFilteredCached($q, $ok, $apiid)
    {
        $factory = function () use ($q, $ok, $apiid) {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);
                $filters = self::buildFilters($q, $ok, $apiid, 0);
                $from = '`' . self::table() . '` l';
                if ($q !== '') {
                    $from .= ' LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`';
                }
                $sql = 'SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $filters['whereSql'];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($filters['bind']);
                return max(0, (int) $stmt->fetchColumn());
            } catch (Exception $e) {
                return 0;
            }
        };

        if (class_exists('RedisCache')) {
            return (int) RedisCache::remember(
                RedisCache::apilogFilterTotalKey(array(
                    'q'     => $q,
                    'ok'    => $ok,
                    'apiid' => $apiid,
                )),
                RedisCache::TTL_APILOG_RANGE_TOTAL,
                $factory
            );
        }
        return (int) call_user_func($factory);
    }

    /**
     * @param string $q
     * @param mixed  $ok
     * @param int    $apiid
     * @param int    $beforeId
     * @return array{whereSql:string,bind:array,hasExtra:bool}
     */
    private static function buildFilters($q, $ok, $apiid, $beforeId)
    {
        $where = array('1=1');
        $bind = array();
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
}
