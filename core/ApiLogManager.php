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

    /** @var bool|null 请求内缓存：apilog.egress 是否已落地 */
    private static $hasEgressCol = null;

    /** @var bool|null 请求内缓存：apilog 转发头扩列是否已落地 */
    private static $hasExtendedCol = null;

    /**
     * apilog.egress 是否存在（升级窗口缺列时写/读降级，避免整页失败）
     *
     * @return bool
     */
    public static function hasEgressColumn()
    {
        if (self::$hasEgressCol !== null) {
            return self::$hasEgressCol;
        }
        self::$hasEgressCol = false;
        try {
            if (class_exists('DatabaseMigrator')
                && method_exists('DatabaseMigrator', 'tableColumnExists')) {
                self::$hasEgressCol = DatabaseMigrator::tableColumnExists('apilog', 'egress');
            }
        } catch (Exception $e) {
            self::$hasEgressCol = false;
        }
        return self::$hasEgressCol;
    }

    /**
     * 转发链请求头列是否已落地（xfproto/xff/xri 三者齐全；不含耗时/响应正文）
     *
     * @return bool
     */
    public static function hasExtendedLogColumns()
    {
        if (self::$hasExtendedCol !== null) {
            return self::$hasExtendedCol;
        }
        self::$hasExtendedCol = false;
        try {
            if (class_exists('DatabaseMigrator')
                && method_exists('DatabaseMigrator', 'tableColumnExists')) {
                self::$hasExtendedCol = DatabaseMigrator::tableColumnExists('apilog', 'xfproto')
                    && DatabaseMigrator::tableColumnExists('apilog', 'xff')
                    && DatabaseMigrator::tableColumnExists('apilog', 'xri');
            }
        } catch (Exception $e) {
            self::$hasExtendedCol = false;
        }
        return self::$hasExtendedCol;
    }

    /**
     * 列表查询列
     *
     * @param string $alias 表别名
     * @return string
     */
    public static function sqlListColumns($alias = 'l')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        if ($a === '') {
            $a = 'l';
        }
        $cols = array(
            'id', 'apiid', 'apiname', 'apitype', 'userid', 'apikey', 'method',
            'ip', 'iploc', 'egress', 'host', 'path', 'url',
            'referer', 'origin', 'domain', 'ua',
            'ok', 'httpcode', 'charged', 'cost', 'createtime',
        );
        if (self::hasExtendedLogColumns()) {
            $cols = array_merge($cols, array(
                'xfproto', 'xff', 'xri',
            ));
        }
        $out = array();
        foreach ($cols as $c) {
            if ($c === 'egress' && !self::hasEgressColumn()) {
                continue;
            }
            $out[] = '`' . $a . '`.`' . $c . '`';
        }
        return implode(', ', $out);
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
        // 优先业务错误码（ApiError 11xxx），再兼容上游透传的 HTTP 码与历史 401/403
        if (class_exists('ApiError') && ApiError::isKnown($code)) {
            return ApiError::label($code);
        }
        $map = array(
            200 => '调用成功',
            201 => '已创建',
            204 => '无内容',
            301 => '永久重定向',
            302 => '代理跳转成功',
            304 => '未修改',
            400 => '请求参数有误',
            404 => '接口不存在',
            405 => '请求方法不允许',
            408 => '请求超时',
            500 => '服务器内部错误',
            502 => '上游网关错误',
            504 => '上游网关超时',
            // 历史守卫码（升级前日志）
            401 => '未提供密钥或密钥错误（旧）',
            402 => '积分余额不足（旧）',
            403 => '接口不可用或密钥已禁用（旧）',
            429 => '请求过于频繁（旧）',
            503 => '服务暂不可用（旧）',
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
        $egress = isset($row['egress']) ? trim((string) $row['egress']) : '';
        $httpLabel = self::httpcodeLabel($httpcode);
        // 列表角标：失败时带上原因（如「失败-上游网关错误」）
        $okLabel = '成功';
        if (!$ok) {
            $okLabel = ($httpLabel !== '') ? ('失败-' . $httpLabel) : '失败';
        }

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
                : '游客',
            // apikey=完整（管理员可点眼睛查看）；apikey_masked=默认展示
            'apikey'         => $apikey,
            'apikey_masked'  => self::maskApikey($apikey),
            'method'         => $method,
            'method_class'   => self::methodClass($method),
            'ip'             => isset($row['ip']) ? (string) $row['ip'] : '',
            'iploc'          => $iploc,
            'egress'         => $egress,
            'host'           => isset($row['host']) ? (string) $row['host'] : '',
            'path'           => isset($row['path']) ? (string) $row['path'] : '',
            'url'            => isset($row['url']) ? (string) $row['url'] : '',
            'referer'        => isset($row['referer']) ? (string) $row['referer'] : '',
            'origin'         => isset($row['origin']) ? (string) $row['origin'] : '',
            'domain'         => isset($row['domain']) ? (string) $row['domain'] : '',
            'ua'             => isset($row['ua']) ? (string) $row['ua'] : '',
            'ok'             => $ok ? 1 : 0,
            'ok_label'       => $okLabel,
            'ok_class'       => $ok ? 'is-ok' : 'is-fail',
            'httpcode'       => $httpcode,
            'httpcode_label' => $httpLabel,
            'http_class'     => self::httpClass($httpcode),
            'charged'        => $charged ? 1 : 0,
            'charged_label'  => $charged ? '已扣费' : '未扣费',
            'cost'           => number_format((float) (isset($row['cost']) ? $row['cost'] : 0), 4, '.', ''),
            'xfproto'        => isset($row['xfproto']) ? (string) $row['xfproto'] : '',
            'xff'            => isset($row['xff']) ? (string) $row['xff'] : '',
            'xri'            => isset($row['xri']) ? (string) $row['xri'] : '',
            'createtime'     => isset($row['createtime']) ? (string) $row['createtime'] : '',
            // hot|cold：列表热冷合并时前端角标（兼容原始 _storage 与已 format 的 storage）
            'storage'        => (
                (isset($row['_storage']) && (string) $row['_storage'] === 'cold')
                || (isset($row['storage']) && (string) $row['storage'] === 'cold')
            ) ? 'cold' : 'hot',
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

    public static function countToday()
    {
        $cacheKey = RedisCache::apilogTodayKey();
        $ttl = (int) RedisCache::TTL_APILOG_TODAY;
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            return self::rememberRawTodayCount($cacheKey, $ttl, function () {
                return StatDayManager::todayCalls();
            });
        }
        if (!self::tableReady()) {
            return 0;
        }
        return self::rememberRawTodayCount($cacheKey, $ttl, function () {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);
                $stmt = $pdo->query(
                    'SELECT COUNT(*) FROM `' . self::table() . '` '
                    . 'WHERE `createtime` >= CURDATE() AND `createtime` < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
                );
                return max(0, (int) $stmt->fetchColumn());
            } catch (Exception $e) {
                return 0;
            }
        });
    }

    /**
     * 今日计数使用 Redis 原始十进制整数，和 INCRBY 共用同一数据类型。
     * 旧的序列化值会在冷启时转换，避免写路径在短 TTL 内持续 INCRBY 失败。
     *
     * @param string   $logicalKey
     * @param int      $ttl
     * @param callable $factory
     * @return int
     */
    private static function rememberRawTodayCount($logicalKey, $ttl, $factory)
    {
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return max(0, (int) call_user_func($factory));
        }
        try {
            return (int) RedisService::withClient(function ($redis) use ($logicalKey, $ttl, $factory) {
                $fullKey = RedisService::buildKey($logicalKey);
                $raw = $redis->get($fullKey);
                if ($raw !== false && preg_match('/^\d+$/', (string) $raw)) {
                    return max(0, (int) $raw);
                }
                $value = max(0, (int) call_user_func($factory));
                $redis->setex($fullKey, max(1, (int) $ttl), (string) $value);
                return $value;
            });
        } catch (Exception $e) {
            return max(0, (int) call_user_func($factory));
        }
    }

    /**
     * 分页列表：底部「每页条数」翻页。
     * 热冷合并一律 keyset（id < before_id）；点第 N 页且 before_id=0 时按「筛选下最大 ID」估算跳页游标（禁 OFFSET 混冷库）。
     * 禁止「近 N 天」时间窗；筛选总数短 TTL 缓存后供底栏「共 N 条」。
     *
     * @param array $opts page, pagesize, q, ok(null|0|1), apiid, before_id
     * @return array{list:array,total:int,page:int,pagesize:int,before_id:int,next_before_id:int,has_more:bool,total_approx:bool}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 128, 'UTF-8');
        } elseif (strlen($q) > 128) {
            $q = substr($q, 0, 128);
        }
        $qField = self::normalizeQField(isset($opts['q_field']) ? $opts['q_field'] : 'id');
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;
        $userid = isset($opts['userid']) ? (int) $opts['userid'] : 0;
        if ($userid < 0) {
            $userid = 0;
        }
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        if ($beforeId < 0) {
            $beforeId = 0;
        }
        $skipTotal = !empty($opts['skip_total']);
        $dateFrom = class_exists('ApiLogArchive')
            ? ApiLogArchive::normalizeDay(isset($opts['date_from']) ? $opts['date_from'] : '')
            : '';
        $dateTo = class_exists('ApiLogArchive')
            ? ApiLogArchive::normalizeDay(isset($opts['date_to']) ? $opts['date_to'] : '')
            : '';
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            $tmp = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $tmp;
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
            'include_cold'   => false,
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
        );
        if (!self::tableReady()) {
            return $empty;
        }

        // 模糊字段无日期时只查热库，避免 1800 万冷库全扫
        $fuzzyNeedDate = ($q !== '' && in_array($qField, array('apiname', 'ip', 'apikey'), true));
        $hasDate = ($dateFrom !== '' || $dateTo !== '');
        $includeCold = class_exists('ApiLogArchive')
            && (!$fuzzyNeedDate || $hasDate);

        // 点第 N 页且未带游标：用最大 ID 估算 before_id，允许任意跳页（仍走 keyset，不 OFFSET）
        if ($page > 1 && $beforeId <= 0) {
            $nameApiidsJump = array();
            if ($q !== '' && $qField === 'apiname') {
                $nameApiidsJump = self::resolveSearchApiIds($q);
            }
            $maxId = self::maxIdUnderFilters(
                $q,
                $qField,
                $ok,
                $apiid,
                $userid,
                $dateFrom,
                $dateTo,
                $includeCold,
                $nameApiidsJump
            );
            if ($maxId <= 0) {
                return $empty;
            }
            $beforeId = $maxId - (($page - 1) * $pagesize) + 1;
            if ($beforeId < 1) {
                $beforeId = 1;
            }
        }

        $skipCache = !empty($opts['skip_cache']);
        $cacheKey = RedisCache::apilogPageKey(array(
            'page'       => $page,
            'pagesize'   => $pagesize,
            'q'          => $q,
            'q_field'    => $qField,
            'ok'         => $ok,
            'apiid'      => $apiid,
            'userid'     => $userid,
            'before_id'  => $beforeId,
            'skip_total' => $skipTotal,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'inc_cold'   => $includeCold ? 1 : 0,
        ));

        $loader = function () use (
            $page,
            $pagesize,
            $q,
            $qField,
            $ok,
            $apiid,
            $userid,
            $beforeId,
            $skipTotal,
            $empty,
            $dateFrom,
            $dateTo,
            $includeCold
        ) {
                try {
                    $pdo = Database::connect();
                    self::applyQueryTimeout($pdo);

                    // 热冷合并一律 keyset（页码跳转已在入口把 page 折成 before_id）
                    $filters = self::buildFilters(
                        $q,
                        $qField,
                        $ok,
                        $apiid,
                        $beforeId,
                        $userid,
                        $dateFrom,
                        $dateTo
                    );
                    $userIdsForCold = isset($filters['userIds']) ? $filters['userIds'] : array();
                    if ($userid > 0) {
                        $userIdsForCold = array($userid);
                    }

                    $nameApiids = array();
                    if ($q !== '' && $qField === 'apiname') {
                        $nameApiids = self::resolveSearchApiIds($q);
                    }

                    $from = '`' . self::table() . '` l'
                        . ' LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`';
                    $sql = 'SELECT ' . self::sqlListColumns('l') . ', u.`username` FROM ' . $from
                        . ' WHERE ' . $filters['whereSql']
                        . ' ORDER BY l.`id` DESC LIMIT ' . ((int) $pagesize + 1);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($filters['bind']);
                    $hotRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $merged = array();
                    foreach ($hotRows as $row) {
                        $row['_storage'] = 'hot';
                        $item = self::formatRow($row);
                        if ($item !== null) {
                            $merged[] = $item;
                        }
                    }

                    $needMore = ($pagesize + 1) - count($merged);
                    if ($needMore > 0 && $includeCold && class_exists('ApiLogArchive')) {
                        $coldOpts = array(
                            'date_from'    => $dateFrom,
                            'date_to'      => $dateTo,
                            'before_id'    => $beforeId,
                            'pagesize'     => $needMore,
                            'q'            => $q,
                            'q_field'      => $qField,
                            'ok'           => $ok,
                            'apiid'        => $apiid,
                            'user_ids'     => $userIdsForCold,
                            'name_apiids'  => $nameApiids,
                            'prune_apiids' => $nameApiids,
                        );
                        $cold = ApiLogArchive::listInQueryWindow($coldOpts);
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

                    $totalApprox = false;
                    if ($skipTotal) {
                        $hasMore = count($merged) > $pagesize;
                        if ($hasMore) {
                            $merged = array_slice($merged, 0, $pagesize);
                        }
                        $total = count($merged) + ($hasMore ? 1 : 0);
                        $totalApprox = true;
                    } else {
                        $hotTotal = self::countFilteredCached(
                            $q,
                            $qField,
                            $ok,
                            $apiid,
                            $userid,
                            $dateFrom,
                            $dateTo
                        );
                        $coldTotal = 0;
                        if ($includeCold && class_exists('ApiLogArchive')) {
                            $coldTotal = ApiLogArchive::countInQueryWindow(array(
                                'date_from'    => $dateFrom,
                                'date_to'      => $dateTo,
                                'apiid'        => $apiid,
                                'prune_apiids' => $nameApiids,
                            ));
                            if ($coldTotal > 0) {
                                $totalApprox = true;
                            }
                        }
                        $total = $hotTotal + $coldTotal;
                        $hasMore = count($merged) > $pagesize;
                        if ($hasMore) {
                            $merged = array_slice($merged, 0, $pagesize);
                        }
                    }

                    self::hydrateUsernames($merged);

                    $nextBefore = 0;
                    if (!empty($merged)) {
                        $nextBefore = (int) $merged[count($merged) - 1]['id'];
                    }

                    return array(
                        'list'           => $merged,
                        'total'          => $total,
                        'page'           => $page,
                        'pagesize'       => $pagesize,
                        'before_id'      => $beforeId,
                        'next_before_id' => $nextBefore,
                        'has_more'       => $hasMore,
                        'total_approx'   => $totalApprox,
                        'include_cold'   => $includeCold,
                        'date_from'      => $dateFrom,
                        'date_to'        => $dateTo,
                    );
                } catch (Exception $e) {
                    return $empty;
                }
            };
        if ($skipCache) {
            return $loader();
        }
        return RedisCache::remember(
            $cacheKey,
            RedisCache::TTL_APILOG_PAGE,
            $loader
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
     * 当前筛选下最大日志 ID（热库 MAX + 冷库 catalog max_id；供页码跳转估算 before_id）
     *
     * @param string $q
     * @param string $qField
     * @param mixed  $ok
     * @param int    $apiid
     * @param int    $userid
     * @param string $dateFrom
     * @param string $dateTo
     * @param bool   $includeCold
     * @param int[]  $nameApiids
     * @return int
     */
    private static function maxIdUnderFilters(
        $q,
        $qField,
        $ok,
        $apiid,
        $userid,
        $dateFrom,
        $dateTo,
        $includeCold,
        array $nameApiids = array()
    ) {
        $maxId = 0;
        try {
            $pdo = Database::connect();
            self::applyQueryTimeout($pdo);
            $filters = self::buildFilters($q, $qField, $ok, $apiid, 0, $userid, $dateFrom, $dateTo);
            $sql = 'SELECT MAX(l.`id`) FROM `' . self::table() . '` l WHERE ' . $filters['whereSql'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($filters['bind']);
            $maxId = max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            $maxId = 0;
        }
        if ($includeCold && class_exists('ApiLogArchive')) {
            $coldMax = ApiLogArchive::maxIdInQueryWindow(array(
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
                'apiid'        => $apiid,
                'prune_apiids' => $nameApiids,
            ));
            if ($coldMax > $maxId) {
                $maxId = $coldMax;
            }
        }
        return $maxId;
    }

    /**
     * 当前筛选条件下热库总数（短 TTL 缓存；不含 before_id）
     *
     * @param string $q
     * @param string $qField
     * @param mixed  $ok
     * @param int    $apiid
     * @param int    $userid
     * @param string $dateFrom
     * @param string $dateTo
     * @return int
     */
    private static function countFilteredCached($q, $qField, $ok, $apiid, $userid = 0, $dateFrom = '', $dateTo = '')
    {
        $qField = self::normalizeQField($qField);
        $factory = function () use ($q, $qField, $ok, $apiid, $userid, $dateFrom, $dateTo) {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);
                $filters = self::buildFilters($q, $qField, $ok, $apiid, 0, $userid, $dateFrom, $dateTo);
                // COUNT 禁止 JOIN user（见《数据统计与性能规范》）；搜索用户走 userid IN / EXISTS
                $sql = 'SELECT COUNT(*) FROM `' . self::table() . '` l WHERE ' . $filters['whereSql'];
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
                    'q'         => $q,
                    'q_field'   => $qField,
                    'userid'    => $userid,
                    'ok'        => $ok,
                    'apiid'     => $apiid,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                )),
                RedisCache::TTL_APILOG_RANGE_TOTAL,
                $factory
            );
        }
        return (int) call_user_func($factory);
    }

    /**
     * 接口名称前缀 → api.id 列表（冷库剪枝 / 改走 apiid IN）
     *
     * @param string $q
     * @return int[]
     */
    public static function resolveSearchApiIds($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array();
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('api');
            $like = function_exists('vs_sql_like_prefix')
                ? vs_sql_like_prefix($q)
                : (addcslashes($q, "\\%_") . '%');
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '` WHERE `name` LIKE ? ESCAPE \'\\\\\' ORDER BY `id` DESC LIMIT 50'
            );
            $stmt->execute(array($like));
            $ids = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) $row['id'];
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            return $ids;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 为列表补全 username / user_label（冷库无用户名、JOIN 失败兜底）
     *
     * @param array $list formatRow 结果（引用修改）
     * @return void
     */
    private static function hydrateUsernames(array &$list)
    {
        $need = array();
        foreach ($list as $row) {
            $uid = isset($row['userid']) ? (int) $row['userid'] : 0;
            $name = isset($row['username']) ? trim((string) $row['username']) : '';
            if ($uid > 0 && $name === '') {
                $need[$uid] = true;
            }
        }
        if ($need === array()) {
            return;
        }
        $ids = array_keys($need);
        $map = array();
        try {
            $pdo = Database::connect();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                'SELECT `id`, `username` FROM `' . Database::table('user') . '` WHERE `id` IN (' . $ph . ')'
            );
            $stmt->execute(array_values($ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $map[(int) $u['id']] = trim((string) $u['username']);
            }
        } catch (Exception $e) {
            return;
        }
        foreach ($list as &$row) {
            $uid = isset($row['userid']) ? (int) $row['userid'] : 0;
            if ($uid <= 0) {
                continue;
            }
            $name = isset($row['username']) ? trim((string) $row['username']) : '';
            if ($name === '' && isset($map[$uid])) {
                $name = $map[$uid];
                $row['username'] = $name;
            }
            if ($name !== '') {
                $row['user_label'] = $name;
            } elseif ($uid > 0) {
                $row['user_label'] = '用户 #' . $uid;
            }
        }
        unset($row);
    }

    /**
     * 先查用户表拿 ID，再过滤 apilog.userid（与 OrderManager 同思路，避免 JOIN 别名搜索失效）
     *
     * @param string $q
     * @return int[]
     */
    private static function resolveSearchUserIds($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array();
        }
        static $memo = array();
        if (isset($memo[$q])) {
            return $memo[$q];
        }
        $ids = array();
        if (ctype_digit($q)) {
            $n = (int) $q;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
        try {
            $pdo = Database::connect();
            self::applyQueryTimeout($pdo);
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '` WHERE `username` = ? OR `email` = ? LIMIT 30'
            );
            $stmt->execute(array($q, $q));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[] = (int) $id;
            }
            if ($ids === array() || !ctype_digit($q)) {
                $prefix = function_exists('vs_sql_like_prefix')
                    ? vs_sql_like_prefix($q)
                    : (addcslashes($q, "\\%_") . '%');
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . $table . '` WHERE `username` LIKE ? ESCAPE \'\\\\\' OR `email` LIKE ? ESCAPE \'\\\\\' ORDER BY `id` DESC LIMIT 50'
                );
                $stmt->execute(array($prefix, $prefix));
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    $ids[] = (int) $id;
                }
            }
            if ($ids === array() || !ctype_digit($q)) {
                $qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
                if ($qLen >= 2 && $qLen <= 64) {
                    $fuzzy = function_exists('vs_sql_like_contains')
                        ? vs_sql_like_contains($q)
                        : ('%' . addcslashes($q, "\\%_") . '%');
                    $stmt = $pdo->prepare(
                        'SELECT `id` FROM `' . $table . '` WHERE `username` LIKE ? ESCAPE \'\\\\\' OR `email` LIKE ? ESCAPE \'\\\\\' ORDER BY `id` DESC LIMIT 50'
                    );
                    $stmt->execute(array($fuzzy, $fuzzy));
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                        $ids[] = (int) $id;
                    }
                }
            }
        } catch (Exception $e) {
            /* 忽略 */
        }
        $ids = array_values(array_unique(array_filter($ids)));
        return $memo[$q] = $ids;
    }

    /**
     * 搜索字段白名单（默认日志编号）
     *
     * @param mixed $field
     * @return string id|apiid|username|apiname|ip|apikey
     */
    public static function normalizeQField($field)
    {
        $field = strtolower(trim((string) $field));
        $allow = array('id', 'apiid', 'username', 'apiname', 'ip', 'apikey');
        if (!in_array($field, $allow, true)) {
            return 'id';
        }
        return $field;
    }

    /**
     * 用户侧搜索字段（禁止 username：仅本人日志，无跨用户检索）
     *
     * @param mixed $field
     * @return string id|apiid|apiname|ip|apikey
     */
    public static function normalizeQFieldForUser($field)
    {
        $field = self::normalizeQField($field);
        if ($field === 'username') {
            return 'id';
        }
        return $field;
    }

    /**
     * 从输入解析正整数（支持 #123 / 编号:123）
     *
     * @param string $q
     * @return int 0=无效
     */
    private static function parsePositiveIntToken($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return 0;
        }
        if (preg_match('/^#(\d+)$/', $q, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^(?:日志编号|编号|日志\s*id|日志\s*ID|接口\s*id|接口\s*ID|id|ID)\s*[:：#]?\s*(\d+)$/u', $q, $m)) {
            return (int) $m[1];
        }
        if (ctype_digit($q)) {
            return (int) $q;
        }
        return 0;
    }

    /**
     * @param string $q
     * @param string $qField
     * @param mixed  $ok
     * @param int    $apiid
     * @param int    $beforeId
     * @param int    $userid 指定用户（管理员按用户筛日志）
     * @param string $dateFrom YYYY-MM-DD
     * @param string $dateTo YYYY-MM-DD
     * @return array{whereSql:string,bind:array,hasExtra:bool,userIds:int[]}
     */
    private static function buildFilters(
        $q,
        $qField,
        $ok,
        $apiid,
        $beforeId,
        $userid = 0,
        $dateFrom = '',
        $dateTo = ''
    ) {
        $where = array('1=1');
        $bind = array();
        $hasExtra = false;
        $userIds = array();
        $userid = (int) $userid;
        $qField = self::normalizeQField($qField);

        if ($userid > 0) {
            $where[] = 'l.`userid` = ?';
            $bind[] = $userid;
            $userIds = array($userid);
            $hasExtra = true;
        }

        if ($q !== '') {
            $qTrim = trim($q);
            if ($qField === 'id') {
                $logId = self::parsePositiveIntToken($qTrim);
                if ($logId > 0) {
                    $where[] = 'l.`id` = ?';
                    $bind[] = $logId;
                } else {
                    // 非数字：无匹配
                    $where[] = '1=0';
                }
                $hasExtra = true;
            } elseif ($qField === 'apiid') {
                $aid = self::parsePositiveIntToken($qTrim);
                if ($aid > 0) {
                    $where[] = 'l.`apiid` = ?';
                    $bind[] = $aid;
                } else {
                    $where[] = '1=0';
                }
                $hasExtra = true;
            } elseif ($qField === 'username') {
                if ($userid > 0) {
                    // 已限定用户时再搜用户名无意义，忽略 q
                } else {
                    $userIds = self::resolveSearchUserIds($qTrim);
                    if ($userIds === array()) {
                        $where[] = '1=0';
                    } else {
                        $ph = implode(',', array_fill(0, count($userIds), '?'));
                        $where[] = 'l.`userid` IN (' . $ph . ')';
                        foreach ($userIds as $uid) {
                            $bind[] = (int) $uid;
                        }
                    }
                    $hasExtra = true;
                }
            } elseif ($qField === 'apiname') {
                // 前缀匹配以走 idx_apiname / idx_userid_apiname（禁止前置 %）
                $like = function_exists('vs_sql_like_prefix')
                    ? vs_sql_like_prefix($qTrim)
                    : (addcslashes($qTrim, "\\%_") . '%');
                $where[] = 'l.`apiname` LIKE ? ESCAPE \'\\\\\'';
                $bind[] = $like;
                $hasExtra = true;
            } elseif ($qField === 'ip') {
                $like = function_exists('vs_sql_like_prefix')
                    ? vs_sql_like_prefix($qTrim)
                    : (addcslashes($qTrim, "\\%_") . '%');
                $where[] = 'l.`ip` LIKE ? ESCAPE \'\\\\\'';
                $bind[] = $like;
                $hasExtra = true;
            } elseif ($qField === 'apikey') {
                // 前缀匹配以走 idx_apikey（禁止前置 %）
                $like = function_exists('vs_sql_like_prefix')
                    ? vs_sql_like_prefix($qTrim)
                    : (addcslashes($qTrim, "\\%_") . '%');
                $where[] = 'l.`apikey` LIKE ? ESCAPE \'\\\\\'';
                $bind[] = $like;
                $hasExtra = true;
            }
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
        if ($dateFrom !== '') {
            $where[] = 'l.`createtime` >= ?';
            $bind[] = $dateFrom . ' 00:00:00';
            $hasExtra = true;
        }
        if ($dateTo !== '') {
            $where[] = 'l.`createtime` <= ?';
            $bind[] = $dateTo . ' 23:59:59';
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
            'userIds'  => $userIds,
        );
    }

    /**
     * 用户侧列表安全字段（禁止 UA/Referer/密钥全文等）
     *
     * @param array $row 原始行或 formatRow 结果
     * @return array|null
     */
    public static function formatUserSafeRow($row)
    {
        $full = self::formatRow($row);
        if ($full === null) {
            return null;
        }
        return array(
            'id'             => (int) $full['id'],
            'apiid'          => (int) $full['apiid'],
            'apiname'        => (string) $full['apiname'],
            'path'           => (string) $full['path'],
            'method'         => (string) $full['method'],
            'method_class'   => (string) $full['method_class'],
            'ip'             => (string) $full['ip'],
            'iploc'          => (string) $full['iploc'],
            'egress'         => (string) $full['egress'],
            'ok'             => (int) $full['ok'],
            'ok_label'       => (string) $full['ok_label'],
            'ok_class'       => (string) $full['ok_class'],
            'httpcode'       => (int) $full['httpcode'],
            'httpcode_label' => (string) $full['httpcode_label'],
            'http_class'     => (string) $full['http_class'],
            'createtime'     => (string) $full['createtime'],
            'storage'        => (isset($full['storage']) && (string) $full['storage'] === 'cold') ? 'cold' : 'hot',
        );
    }

    /**
     * 用户侧详情白名单（相对管理端精简：无类型/用户/Referer/Origin/UA/来源域名/转发头/耗时；含 path/url/host/出口节点）
     *
     * @param array $row 原始行或 formatRow 结果
     * @return array|null
     */
    public static function formatUserDetailRow($row)
    {
        $full = self::formatRow($row);
        if ($full === null) {
            return null;
        }
        return array(
            'id'             => (int) $full['id'],
            'apiid'          => (int) $full['apiid'],
            'apiname'        => (string) $full['apiname'],
            'method'         => (string) $full['method'],
            'method_class'   => (string) $full['method_class'],
            'apikey'         => (string) $full['apikey'],
            'apikey_masked'  => (string) $full['apikey_masked'],
            'ip'             => (string) $full['ip'],
            'iploc'          => (string) $full['iploc'],
            'egress'         => (string) $full['egress'],
            'host'           => (string) $full['host'],
            'path'           => (string) $full['path'],
            // 完整 URL 原文回传；密钥段由前端默认模糊、悬停明码（勿再 *** 替换）
            'url'            => (string) $full['url'],
            'ok'             => (int) $full['ok'],
            'ok_label'       => (string) $full['ok_label'],
            'ok_class'       => (string) $full['ok_class'],
            'httpcode'       => (int) $full['httpcode'],
            'httpcode_label' => (string) $full['httpcode_label'],
            'http_class'     => (string) $full['http_class'],
            'charged'        => (int) $full['charged'],
            'charged_label'  => (string) $full['charged_label'],
            'cost'           => (string) $full['cost'],
            'createtime'     => (string) $full['createtime'],
        );
    }

    /**
     * @deprecated v13.26.34 起用户详情改由前端对密钥段模糊悬停，不再 *** 替换整段参数值
     * 用户可见完整路径：query 中常见密钥参数打码（旧逻辑保留，勿再接入新出口）
     *
     * @param string $url
     * @return string
     */
    private static function scrubUserVisibleUrl($url)
    {
        $url = (string) $url;
        if ($url === '' || strpos($url, '?') === false) {
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['query'])) {
            return $url;
        }
        $q = array();
        parse_str((string) $parts['query'], $q);
        if (!is_array($q) || $q === array()) {
            return $url;
        }
        $sensitive = array('key', 'apikey', 'api_key', 'token', 'access_token', 'secret');
        $changed = false;
        foreach ($sensitive as $name) {
            if (isset($q[$name]) && (string) $q[$name] !== '') {
                $q[$name] = '***';
                $changed = true;
            }
        }
        if (!$changed) {
            return $url;
        }
        $scheme = isset($parts['scheme']) ? ($parts['scheme'] . '://') : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $frag = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';
        $query = http_build_query($q);
        // 无 host 时保留相对形态（仅 path?query）
        if ($scheme === '' && $host === '') {
            return $path . ($query !== '' ? ('?' . $query) : '') . $frag;
        }
        return $scheme . $host . $port . $path . ($query !== '' ? ('?' . $query) : '') . $frag;
    }

    /**
     * 用户侧按 ID 取详情（强制本人 userid；冷热库）
     *
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public static function findByIdForUser($id, $userId)
    {
        $id = (int) $id;
        $userId = (int) $userId;
        if ($id <= 0 || $userId <= 0 || !self::detailEnabled()) {
            return null;
        }
        $row = self::findById($id);
        if ($row === null || (int) $row['userid'] !== $userId) {
            return null;
        }
        return self::formatUserDetailRow($row);
    }

    /**
     * 当前用户近期调用（控制台；强制 userid；短 TTL）
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function recentForUser($userId, $limit = 20)
    {
        $userId = (int) $userId;
        $limit = max(1, min(30, (int) $limit));
        if ($userId <= 0 || !self::tableReady() || !self::detailEnabled()) {
            return array();
        }
        // 与日志页同路径（热库+冷库补齐），避免首页空、日志页有数
        $paged = self::listForUser($userId, array(
            'page'      => 1,
            'pagesize'  => $limit,
            'before_id' => 0,
            'ok'        => null,
        ));
        return isset($paged['list']) && is_array($paged['list']) ? $paged['list'] : array();
    }

    /**
     * 用户侧日志分页（强制本人 userid；复用 listPaged 热冷 keyset + 日期缩窗）
     *
     * @param int   $userId 必须为正且与会话一致（由调用方保证）
     * @param array $opts   page, pagesize, before_id, ok, q, q_field, date_from, date_to
     * @return array{list:array,total:int,page:int,pagesize:int,before_id:int,next_before_id:int,has_more:bool,detail_enabled:bool}
     */
    public static function listForUser($userId, array $opts = array())
    {
        $userId = (int) $userId;
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
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
            'detail_enabled' => self::detailEnabled(),
            'total_approx'   => false,
            'include_cold'   => false,
            'date_from'      => '',
            'date_to'        => '',
        );
        if ($userId <= 0 || !self::tableReady() || !self::detailEnabled()) {
            return $empty;
        }

        $qField = self::normalizeQFieldForUser(isset($opts['q_field']) ? $opts['q_field'] : 'id');
        $paged = self::listPaged(array(
            'page'       => $page,
            'pagesize'   => $pagesize,
            'before_id'  => $beforeId,
            'ok'         => array_key_exists('ok', $opts) ? $opts['ok'] : null,
            'q'          => isset($opts['q']) ? $opts['q'] : '',
            'q_field'    => $qField,
            'userid'     => $userId,
            'date_from'  => isset($opts['date_from']) ? $opts['date_from'] : '',
            'date_to'    => isset($opts['date_to']) ? $opts['date_to'] : '',
            'skip_cache' => !empty($opts['skip_cache']),
        ));

        $list = array();
        if (!empty($paged['list']) && is_array($paged['list'])) {
            foreach ($paged['list'] as $row) {
                $safe = self::formatUserSafeRow($row);
                if ($safe !== null) {
                    $list[] = $safe;
                }
            }
        }

        return array(
            'list'           => $list,
            'total'          => isset($paged['total']) ? (int) $paged['total'] : 0,
            'page'           => isset($paged['page']) ? (int) $paged['page'] : $page,
            'pagesize'       => isset($paged['pagesize']) ? (int) $paged['pagesize'] : $pagesize,
            'before_id'      => isset($paged['before_id']) ? (int) $paged['before_id'] : $beforeId,
            'next_before_id' => isset($paged['next_before_id']) ? (int) $paged['next_before_id'] : 0,
            'has_more'       => !empty($paged['has_more']),
            'detail_enabled' => true,
            'total_approx'   => !empty($paged['total_approx']),
            'include_cold'   => !empty($paged['include_cold']),
            'date_from'      => isset($paged['date_from']) ? (string) $paged['date_from'] : '',
            'date_to'        => isset($paged['date_to']) ? (string) $paged['date_to'] : '',
        );
    }
}
