<?php
/**
 * 文件：core/ApiLogManager.php
 * 作用：API 调用日志查询（分页 / 搜索 / 详情；查询结果走 Redis 短 TTL 缓存，供后台与前台统计复用）
 */

class ApiLogManager
{
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
        if ($id <= 0 || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT l.*, u.`username`
                FROM `' . self::table() . '` l
                LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`
                WHERE l.`id` = ? LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? self::formatRow($row) : null;
        } catch (Exception $e) {
            return null;
        }
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
     * 分页列表（短 TTL Redis 缓存；后台列表、后续图表等凡读 apilog 聚合/列表均可走本层）
     *
     * @param array $opts page, pagesize, q, ok(null|0|1), apiid
     * @return array{list:array,total:int,page:int,pagesize:int}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $ok = array_key_exists('ok', $opts) ? $opts['ok'] : null;
        $apiid = isset($opts['apiid']) ? (int) $opts['apiid'] : 0;

        $empty = array('list' => array(), 'total' => 0, 'page' => $page, 'pagesize' => $pagesize);
        if (!self::tableReady()) {
            return $empty;
        }

        $cacheKey = RedisCache::apilogPageKey(array(
            'page'     => $page,
            'pagesize' => $pagesize,
            'q'        => $q,
            'ok'       => $ok,
            'apiid'    => $apiid,
        ));

        return RedisCache::remember(
            $cacheKey,
            RedisCache::TTL_APILOG_PAGE,
            function () use ($page, $pagesize, $q, $ok, $apiid, $empty) {
                try {
                    $pdo = Database::connect();
                    $where = array('1=1');
                    $bind = array();

                    if ($q !== '') {
                        $like = '%' . $q . '%';
                        $where[] = '(l.`apiname` LIKE ? OR l.`path` LIKE ? OR l.`ip` LIKE ? OR l.`url` LIKE ? OR l.`apikey` LIKE ? OR l.`domain` LIKE ? OR l.`iploc` LIKE ? OR u.`username` LIKE ?)';
                        for ($i = 0; $i < 8; $i++) {
                            $bind[] = $like;
                        }
                    }
                    if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
                        $where[] = 'l.`ok` = ?';
                        $bind[] = (int) $ok;
                    }
                    if ($apiid > 0) {
                        $where[] = 'l.`apiid` = ?';
                        $bind[] = $apiid;
                    }

                    $whereSql = implode(' AND ', $where);
                    $from = '`' . self::table() . '` l LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`';

                    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $whereSql);
                    $countStmt->execute($bind);
                    $total = (int) $countStmt->fetchColumn();

                    $offset = ($page - 1) * $pagesize;
                    $sql = 'SELECT l.*, u.`username` FROM ' . $from . ' WHERE ' . $whereSql
                        . ' ORDER BY l.`id` DESC LIMIT ' . (int) $pagesize . ' OFFSET ' . (int) $offset;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($bind);
                    $list = array();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $item = self::formatRow($row);
                        if ($item !== null) {
                            $list[] = $item;
                        }
                    }

                    return array(
                        'list'     => $list,
                        'total'    => $total,
                        'page'     => $page,
                        'pagesize' => $pagesize,
                    );
                } catch (Exception $e) {
                    return $empty;
                }
            }
        );
    }
}
