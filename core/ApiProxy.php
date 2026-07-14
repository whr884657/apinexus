<?php
/**
 * 文件：core/ApiProxy.php
 * 作用：代理外链接口的纯 PHP 302 跳转（不依赖 Nginx/.htaccess 伪静态）
 *
 * 访问：/proxy.php?s={proxyslug}&其它参数…
 * 行为：将查询参数拼到上游 targeturl 后，302 跳转至上游。
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
     * 处理 HTTP 请求：302 至上游（保留除 s 以外的查询参数）
     *
     * @return void
     */
    public static function handleRequest()
    {
        $slug = isset($_GET['s']) ? (string) $_GET['s'] : '';
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

        $params = $_GET;
        unset($params['s']);
        $url = self::mergeQuery($target, $params);

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
        return '/proxy.php?s=' . rawurlencode($slug);
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
