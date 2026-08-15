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
            'ok_label'       => $okLabel,
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
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 128, 'UTF-8');
        } elseif (strlen($q) > 128) {
            $q = substr($q, 0, 128);
        }
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

        $skipCache = !empty($opts['skip_cache']);
        $cacheKey = RedisCache::apilogPageKey(array(
            'page'      => $page,
            'pagesize'  => $pagesize,
            'q'         => $q,
            'ok'        => $ok,
            'apiid'     => $apiid,
            'before_id' => $beforeId,
        ));

        $loader = function () use ($page, $pagesize, $q, $ok, $apiid, $beforeId, $empty) {
                try {
                    $pdo = Database::connect();
                    self::applyQueryTimeout($pdo);

                    $filters = self::buildFilters($q, $ok, $apiid, $beforeId);
                    $userIdsForCold = isset($filters['userIds']) ? $filters['userIds'] : array();

                    // 列表始终 LEFT JOIN user，保证卡片/表格能显示用户名（禁止仅搜索时才带 username）
                    $from = '`' . self::table() . '` l'
                        . ' LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`';
                    $sql = 'SELECT l.*, u.`username` FROM ' . $from
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
                            'user_ids'  => $userIdsForCold,
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

                    self::hydrateUsernames($merged);

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
     * @param string $q
     * @param mixed  $ok
     * @param int    $apiid
     * @param int    $beforeId
     * @return array{whereSql:string,bind:array,hasExtra:bool,userIds:int[]}
     */
    private static function buildFilters($q, $ok, $apiid, $beforeId)
    {
        $where = array('1=1');
        $bind = array();
        $hasExtra = false;
        $userIds = array();

        if ($q !== '') {
            $like = function_exists('vs_sql_like_contains')
                ? vs_sql_like_contains($q)
                : ('%' . addcslashes($q, "\\%_") . '%');
            $userIds = self::resolveSearchUserIds($q);
            $parts = array(
                'l.`apiname` LIKE ? ESCAPE \'\\\\\'',
                'l.`path` LIKE ? ESCAPE \'\\\\\'',
                'l.`ip` LIKE ? ESCAPE \'\\\\\'',
                'l.`url` LIKE ? ESCAPE \'\\\\\'',
                'l.`apikey` LIKE ? ESCAPE \'\\\\\'',
                'l.`domain` LIKE ? ESCAPE \'\\\\\'',
                'l.`iploc` LIKE ? ESCAPE \'\\\\\'',
            );
            $bind = array_merge($bind, array($like, $like, $like, $like, $like, $like, $like));
            // 记录 ID 精确匹配（搜 #123 或纯数字时也能命中日志主键）
            if (ctype_digit($q)) {
                $parts[] = 'l.`id` = ?';
                $bind[] = (int) $q;
            }
            if ($userIds !== array()) {
                $ph = implode(',', array_fill(0, count($userIds), '?'));
                $parts[] = 'l.`userid` IN (' . $ph . ')';
                foreach ($userIds as $uid) {
                    $bind[] = (int) $uid;
                }
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
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
            'userIds'  => $userIds,
        );
    }

    /**
     * 用户侧安全字段（仅接口名/时间/IP/成败；禁止 UA/Referer/参数/密钥全文）
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
            'id'         => (int) $full['id'],
            'apiname'    => (string) $full['apiname'],
            'ip'         => (string) $full['ip'],
            'ok'         => (int) $full['ok'],
            'ok_label'   => (string) $full['ok_label'],
            'ok_class'   => (string) $full['ok_class'],
            'createtime' => (string) $full['createtime'],
        );
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
     * 用户侧日志分页（强制本人 userid；字段白名单；无详情）
     *
     * @param int   $userId 必须为正且与会话一致（由调用方保证）
     * @param array $opts   pagesize, before_id, ok(null|0|1)
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
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;

        $empty = array(
            'list'            => array(),
            'total'           => 0,
            'page'            => $page,
            'pagesize'        => $pagesize,
            'before_id'       => $beforeId,
            'next_before_id'  => 0,
            'has_more'        => false,
            'detail_enabled'  => self::detailEnabled(),
            'total_approx'    => false,
        );
        if ($userId <= 0 || !self::tableReady()) {
            return $empty;
        }
        if (!self::detailEnabled()) {
            return $empty;
        }

        $cacheKey = 'cache:userapilog:page:' . md5(json_encode(array(
            'uid'       => $userId,
            'page'      => $page,
            'pagesize'  => $pagesize,
            'ok'        => $ok,
            'before_id' => $beforeId,
        )));

        $loader = function () use ($userId, $page, $pagesize, $beforeId, $ok, $empty) {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);

                $where = array('l.`userid` = ?');
                $bind = array($userId);
                if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
                    $where[] = 'l.`ok` = ?';
                    $bind[] = (int) $ok;
                }
                if ($beforeId > 0) {
                    $where[] = 'l.`id` < ?';
                    $bind[] = $beforeId;
                }
                $whereSql = implode(' AND ', $where);

                $sql = 'SELECT l.`id`, l.`apiname`, l.`ip`, l.`ok`, l.`httpcode`, l.`createtime`
                    FROM `' . self::table() . '` l
                    WHERE ' . $whereSql . '
                    ORDER BY l.`id` DESC
                    LIMIT ' . ((int) $pagesize + 1);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bind);
                $hotRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $merged = array();
                foreach ($hotRows as $row) {
                    $item = self::formatUserSafeRow($row);
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
                        'q'         => '',
                        'ok'        => $ok,
                        'apiid'     => 0,
                        'user_ids'  => array($userId),
                    ));
                    foreach ($cold['list'] as $crow) {
                        $item = self::formatUserSafeRow($crow);
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

                $total = self::countForUserCached($userId, $ok);
                // 热库 COUNT 不含冷库；冷补后若总数偏小，至少不低于本页可见规模（避免「共 0 条」却有列表）
                $listCount = count($merged);
                $totalApprox = false;
                if ($total < $listCount) {
                    $total = $listCount + ($hasMore ? 1 : 0);
                    $totalApprox = true;
                }

                return array(
                    'list'           => $merged,
                    'total'          => $total,
                    'page'           => $page,
                    'pagesize'       => $pagesize,
                    'before_id'      => $beforeId,
                    'next_before_id' => $nextBefore,
                    'has_more'       => $hasMore,
                    'detail_enabled' => true,
                    'total_approx'   => $totalApprox,
                );
            } catch (Exception $e) {
                return $empty;
            }
        };

        if (class_exists('RedisCache')) {
            $data = RedisCache::remember($cacheKey, RedisCache::TTL_APILOG_PAGE, $loader);
            if (!is_array($data)) {
                return $empty;
            }
            $data['detail_enabled'] = true;
            return $data;
        }
        return $loader();
    }

    /**
     * @param int   $userId
     * @param mixed $ok
     * @return int
     */
    private static function countForUserCached($userId, $ok)
    {
        $userId = (int) $userId;
        $factory = function () use ($userId, $ok) {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);
                $where = array('`userid` = ?');
                $bind = array($userId);
                if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
                    $where[] = '`ok` = ?';
                    $bind[] = (int) $ok;
                }
                $sql = 'SELECT COUNT(*) FROM `' . self::table() . '` WHERE ' . implode(' AND ', $where);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bind);
                return max(0, (int) $stmt->fetchColumn());
            } catch (Exception $e) {
                return 0;
            }
        };
        if (class_exists('RedisCache')) {
            $key = 'cache:userapilog:total:' . md5(json_encode(array('uid' => $userId, 'ok' => $ok)));
            return (int) RedisCache::remember($key, RedisCache::TTL_APILOG_RANGE_TOTAL, $factory);
        }
        return (int) call_user_func($factory);
    }
}
