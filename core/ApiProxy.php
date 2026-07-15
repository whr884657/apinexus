<?php
/**
 * 文件：core/ApiProxy.php
 * 作用：代理外链网关 —— PATH_INFO 路径样式公开地址跳转上游（302）
 *
 * 出站公开地址（不依赖伪静态）：
 *   /apis.php/{proxyslug}
 * 辅参仍用查询串：
 *   /apis.php/{短码}?foo=1
 *
 * 入站只认脚本后的路径段（PATH_INFO）；无路径段时不走网关。
 * 列表页：/apis 或 /apis.php（无 PATH_INFO）
 */

class ApiProxy
{
    /**
     * 根据短码查已通过且可访问的代理接口
     *
     * @param string $slug
     * @return array|null
     */
    public static function findCallableBySlug($slug)
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
            if (!$row) {
                return null;
            }

            $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : 0);
            if ($status === ApiManager::STATUS_DISABLED) {
                return null;
            }
            if (ApiManager::hasAuditColumn()) {
                $audit = ApiManager::normalizeAuditStatus(isset($row['audit']) ? $row['audit'] : 1);
                if ($audit !== ApiManager::AUDIT_APPROVED) {
                    return null;
                }
            }
            return $row;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 当前请求的 PATH_INFO（如 /sjspks）
     * 部分环境未传 PATH_INFO 时，从 REQUEST_URI 相对 SCRIPT_NAME 还原（仍是路径式，不是查询串）
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

        // 兼容子目录：SCRIPT_NAME=/sub/apis.php，URI=/sub/apis.php/abc
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
     * 从 PATH_INFO 首段解析短码；无路径则返回空（走列表页）
     *
     * @return string
     */
    public static function resolveSlugFromRequest()
    {
        $info = self::requestPathInfo();
        if ($info === '' || $info === '/') {
            return '';
        }
        $parts = explode('/', trim($info, '/'));
        if (!isset($parts[0]) || $parts[0] === '') {
            return '';
        }
        return self::normalizeSlug($parts[0]);
    }

    /**
     * 当前请求是否应走代理网关（脚本后带有合法短码路径）
     *
     * @return bool
     */
    public static function isGatewayRequest()
    {
        return self::resolveSlugFromRequest() !== '';
    }

    /**
     * 处理 HTTP 请求：302 至上游（查询串全部原样附带）
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

        $row = self::findCallableBySlug($slug);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo '接口不存在或不可用';
            exit;
        }

        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : 0);
        if ($status === ApiManager::STATUS_MAINTENANCE) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo '维护中';
            exit;
        }

        $target = trim((string) (isset($row['targeturl']) ? $row['targeturl'] : ''));
        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo '上游地址无效';
            exit;
        }

        $url = self::mergeQuery($target, $_GET);

        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * 公开访问路径（站点内相对路径，不含域名）
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
        return '/apis.php/' . $slug;
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
     * 生成未占用短码
     *
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
