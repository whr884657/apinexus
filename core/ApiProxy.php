<?php
/**
 * 文件：core/ApiProxy.php
 * 作用：代理外链网关 —— 路径样式公开地址跳转上游（302）
 *
 * 出站（美观）：
 *   /apis/{proxyslug}?foo=1
 *
 * 入站短码来源（按序）：
 *   1) $_GET['_vs_slug'] —— Nginx/Apache 伪静态内部参数（面板兼容，勿当对外契约）
 *   2) PATH_INFO —— /apis.php/{短码}
 *   3) REQUEST_URI 形如 /apis/{短码} 且当前脚本为 apis.php
 *
 * 列表：/apis（无短码）
 */

class ApiProxy
{
    /** 伪静态内部短码参数名（仅 rewrite 注入，不出现在公开出站 URL） */
    const REWRITE_SLUG_PARAM = '_vs_slug';

    /** 对外公开路径前缀（去 .php，美观） */
    const PUBLIC_PREFIX = '/apis';

    /**
     * 按短码查代理接口行（不过滤状态/审核，供网关返回明确错误文案）
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug($slug)
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '' || !ApiManager::tableReady() || !ApiManager::hasProxyColumns()) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . ApiManager::table() . '`
                    WHERE `proxyslug` = ? AND `apitype` = ?
                    LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($slug, ApiManager::APITYPE_PROXY));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 根据短码查已通过且状态正常的代理接口（不含禁用/未审）
     *
     * @param string $slug
     * @return array|null
     */
    public static function findCallableBySlug($slug)
    {
        $row = self::findBySlug($slug);
        if (!$row) {
            return null;
        }

        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : 0);
        if ($status === ApiManager::STATUS_DISABLED) {
            return null;
        }
        if ($status === ApiManager::STATUS_MAINTENANCE) {
            return null;
        }
        if (ApiManager::hasAuditColumn()) {
            $audit = ApiManager::normalizeAuditStatus(isset($row['audit']) ? $row['audit'] : 1);
            if ($audit !== ApiManager::AUDIT_APPROVED) {
                return null;
            }
        }
        return $row;
    }

    /**
     * 当前请求的 PATH_INFO（如 /sjspks）
     * 部分环境未传 PATH_INFO 时，从 REQUEST_URI 相对 apis.php 还原
     *
     * @return string
     */
    public static function requestPathInfo()
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            return (string) $_SERVER['PATH_INFO'];
        }

        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
        if ($script === '') {
            return '';
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }

        $scriptBase = basename($script);
        $pos = strrpos($path, '/' . $scriptBase);
        if ($pos === false) {
            return '';
        }
        $after = substr($path, $pos + strlen('/' . $scriptBase));
        if ($after === '' || $after[0] !== '/') {
            return '';
        }
        return $after;
    }

    /**
     * 解析代理短码：有合法短码才算网关，否则为空（列表页）
     *
     * @return string
     */
    public static function resolveSlugFromRequest()
    {
        // 1) 伪静态内部参数（rewrite → /apis.php?_vs_slug=xxx）—— 宝塔等仅匹配 *.php 结尾时必用
        if (isset($_GET[self::REWRITE_SLUG_PARAM])) {
            $slug = self::normalizeSlug((string) $_GET[self::REWRITE_SLUG_PARAM]);
            if ($slug !== '') {
                return $slug;
            }
        }

        // 2) PATH_INFO：/apis.php/短码
        $info = self::requestPathInfo();
        if ($info !== '' && $info !== '/') {
            $parts = explode('/', trim($info, '/'));
            if (isset($parts[0]) && $parts[0] !== '') {
                $slug = self::normalizeSlug($parts[0]);
                if ($slug !== '') {
                    return $slug;
                }
            }
        }

        // 3) 当前已是 apis.php，URI 仍为美观路径 /apis/短码
        $script = isset($_SERVER['SCRIPT_NAME'])
            ? basename(str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']))
            : '';
        if (strcasecmp($script, 'apis.php') !== 0) {
            return '';
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        if (preg_match('#/apis/([A-Za-z0-9]{3,64})/?$#', $path, $m)) {
            return self::normalizeSlug($m[1]);
        }

        return '';
    }

    /**
     * @return bool
     */
    public static function isGatewayRequest()
    {
        return self::resolveSlugFromRequest() !== '';
    }

    /**
     * 处理 HTTP 请求：302 至上游（查询串原样附带）
     *
     * @param string|null $slug
     * @return void
     */
    public static function handleRequest($slug = null)
    {
        if ($slug === null) {
            $slug = self::resolveSlugFromRequest();
        } else {
            $slug = self::normalizeSlug($slug);
        }

        $row = self::findBySlug($slug);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('code' => 0, 'msg' => '接口不存在'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $gate = ApiStats::guardAccess($row);
        if ($gate !== true) {
            $http = isset($gate['http']) ? (int) $gate['http'] : 403;
            $msg = isset($gate['msg']) ? (string) $gate['msg'] : '接口不可用';
            ApiStats::hitProxy($row, false, $http);
            http_response_code($http);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('code' => 0, 'msg' => $msg), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $target = trim((string) (isset($row['targeturl']) ? $row['targeturl'] : ''));
        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            ApiStats::hitProxy($row, false, 500);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('code' => 0, 'msg' => '上游地址无效'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $params = $_GET;
        unset($params[self::REWRITE_SLUG_PARAM]);
        // 本站密钥参数不转给上游
        unset($params['key'], $params['api_key'], $params['apikey']);
        $url = self::mergeQuery($target, $params);

        ApiStats::hitProxy($row, true, 302);

        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * 公开访问路径（去 .php）
     *
     * @param string $slug
     * @return string
     */
    public static function publicPath($slug)
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return '';
        }
        return self::PUBLIC_PREFIX . '/' . $slug;
    }

    /**
     * 完整公开 URL
     *
     * @param string $slug
     * @return string
     */
    public static function publicUrl($slug)
    {
        $path = self::publicPath($slug);
        if ($path === '') {
            return '';
        }
        return rtrim(vs_base_url(), '/') . $path;
    }

    /**
     * @param string $slug
     * @return string
     */
    public static function normalizeSlug($slug)
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return '';
        }
        if (!preg_match('/^[a-z0-9]{3,64}$/', $slug)) {
            return '';
        }
        return $slug;
    }

    /**
     * @param int $len
     * @return string
     */
    public static function generateUniqueSlug($len = 6)
    {
        $len = max(4, min(16, (int) $len));
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $slug = '';
            for ($i = 0; $i < $len; $i++) {
                $slug .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            if (self::slugExists($slug)) {
                continue;
            }
            return $slug;
        }
        return substr(md5(uniqid((string) mt_rand(), true)), 0, $len);
    }

    /**
     * @param string   $slug
     * @param int|null $excludeId
     * @return bool
     */
    public static function slugExists($slug, $excludeId = null)
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '' || !ApiManager::hasProxyColumns()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            if ($excludeId !== null && (int) $excludeId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . ApiManager::table() . '`
                     WHERE `proxyslug` = ? AND `id` <> ? LIMIT 1'
                );
                $stmt->execute(array($slug, (int) $excludeId));
            } else {
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . ApiManager::table() . '` WHERE `proxyslug` = ? LIMIT 1'
                );
                $stmt->execute(array($slug));
            }
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $url
     * @param array  $params
     * @return string
     */
    private static function mergeQuery($url, array $params)
    {
        if (count($params) === 0) {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }
        $existing = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $existing);
        }
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $existing[$k] = $v;
        }
        $query = http_build_query($existing);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $scheme . $auth . $host . $port . $path . ($query !== '' ? '?' . $query : '') . $frag;
    }
}
