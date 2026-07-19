<?php
/**
 * 文件：core/ApiLogManager.php
 * 作用：API 调用日志查询（分页 / 搜索；列表结果走 Redis 轻量缓存）
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
        $keyMask = $apikey === '' ? '' : self::maskKey($apikey);

        return array(
            'id'           => (int) (isset($row['id']) ? $row['id'] : 0),
            'apiid'        => (int) (isset($row['apiid']) ? $row['apiid'] : 0),
            'apiname'      => isset($row['apiname']) ? (string) $row['apiname'] : '',
            'apitype'      => $apitype,
            'apitype_label'=> $apitype === 1 ? '代理' : '本地',
            'userid'       => (int) (isset($row['userid']) ? $row['userid'] : 0),
            'apikey_mask'  => $keyMask,
            'method'       => isset($row['method']) ? (string) $row['method'] : '',
            'ip'           => isset($row['ip']) ? (string) $row['ip'] : '',
            'iploc'        => isset($row['iploc']) ? (string) $row['iploc'] : '',
            'host'         => isset($row['host']) ? (string) $row['host'] : '',
            'path'         => isset($row['path']) ? (string) $row['path'] : '',
            'url'          => isset($row['url']) ? (string) $row['url'] : '',
            'referer'      => isset($row['referer']) ? (string) $row['referer'] : '',
            'origin'       => isset($row['origin']) ? (string) $row['origin'] : '',
            'domain'       => isset($row['domain']) ? (string) $row['domain'] : '',
            'ua'           => isset($row['ua']) ? (string) $row['ua'] : '',
            'ok'           => $ok ? 1 : 0,
            'ok_label'     => $ok ? '成功' : '失败',
            'ok_class'     => $ok ? 'is-ok' : 'is-fail',
            'httpcode'     => (int) (isset($row['httpcode']) ? $row['httpcode'] : 0),
            'charged'      => $charged ? 1 : 0,
            'charged_label'=> $charged ? '已扣费' : '未扣费',
            'cost'         => number_format((float) (isset($row['cost']) ? $row['cost'] : 0), 4, '.', ''),
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
        );
    }

    /**
     * @param string $key
     * @return string
     */
    public static function maskKey($key)
    {
        $key = (string) $key;
        $len = strlen($key);
        if ($len <= 8) {
            return $key === '' ? '' : str_repeat('*', $len);
        }
        return substr($key, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($key, -4);
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
            $stmt = $pdo->prepare('SELECT * FROM `' . self::table() . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? self::formatRow($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 分页列表（短 TTL Redis 缓存，降低大表反复扫库）
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
                        $where[] = '(`apiname` LIKE ? OR `path` LIKE ? OR `ip` LIKE ? OR `url` LIKE ? OR `apikey` LIKE ? OR `domain` LIKE ?)';
                        $bind[] = $like;
                        $bind[] = $like;
                        $bind[] = $like;
                        $bind[] = $like;
                        $bind[] = $like;
                        $bind[] = $like;
                    }
                    if ($ok === 0 || $ok === 1 || $ok === '0' || $ok === '1') {
                        $where[] = '`ok` = ?';
                        $bind[] = (int) $ok;
                    }
                    if ($apiid > 0) {
                        $where[] = '`apiid` = ?';
                        $bind[] = $apiid;
                    }

                    $whereSql = implode(' AND ', $where);
                    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . self::table() . '` WHERE ' . $whereSql);
                    $countStmt->execute($bind);
                    $total = (int) $countStmt->fetchColumn();

                    $offset = ($page - 1) * $pagesize;
                    $sql = 'SELECT * FROM `' . self::table() . '` WHERE ' . $whereSql
                        . ' ORDER BY `id` DESC LIMIT ' . (int) $pagesize . ' OFFSET ' . (int) $offset;
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
