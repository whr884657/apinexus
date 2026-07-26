<?php
/**
 * 文件：core/IpLocator.php
 * 作用：按系统设置调用外部 IP 归属地 API，提取自定义 JSON 字段写入 apilog.iploc
 */

class IpLocator
{
    const AUTH_NONE = 0;
    const AUTH_BEARER = 1;
    const AUTH_HEADER = 2;
    const AUTH_QUERY = 3;

    const CACHE_TTL = 86400;
    /** 解析失败负缓存，避免热路径反复打外网 */
    const MISS_TTL = 300;
    const MISS_SENTINEL = '__IPLOC_MISS__';
    const TIMEOUT = 1;

    /**
     * @return bool
     */
    public static function enabled()
    {
        if (!class_exists('Config')) {
            return false;
        }
        return Config::get('ip_loc_enabled', '0') === '1';
    }

    /**
     * 解析 IP 归属地文案（失败返回空串）
     *
     * @param string $ip
     * @return string
     */
    public static function lookup($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '' || !self::enabled()) {
            return '';
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }
        // 内网/环回不请求外网
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '内网';
        }

        $cacheKey = RedisCache::KEY_IPLOC_PREFIX . md5($ip);
        if (class_exists('RedisCache') && RedisCache::enabled()) {
            $cached = RedisCache::get($cacheKey);
            if (is_string($cached) && $cached === self::MISS_SENTINEL) {
                return '';
            }
            if (is_string($cached) && $cached !== '') {
                return mb_substr($cached, 0, 120, 'UTF-8');
            }
        }

        $url = trim((string) Config::get('ip_loc_url', ''));
        if ($url === '' || !self::assertPublicHttpUrl($url)) {
            self::cacheMiss($cacheKey);
            return '';
        }

        $ipParam = trim((string) Config::get('ip_loc_ip_param', 'ip'));
        if ($ipParam === '') {
            $ipParam = 'ip';
        }
        $auth = (int) Config::get('ip_loc_auth', (string) self::AUTH_NONE);
        $authName = trim((string) Config::get('ip_loc_auth_name', ''));
        $authValue = (string) Config::get('ip_loc_auth_value', '');
        $fieldPath = trim((string) Config::get('ip_loc_field', ''));
        $extras = self::parseExtras(Config::get('ip_loc_extras', '[]'));

        $query = array();
        $headers = array('Accept: application/json');
        foreach ($extras as $ex) {
            $name = isset($ex['name']) ? trim((string) $ex['name']) : '';
            $val = isset($ex['value']) ? (string) $ex['value'] : '';
            $via = isset($ex['via']) ? (string) $ex['via'] : 'query';
            if ($name === '') {
                continue;
            }
            if ($via === 'header') {
                $headers[] = $name . ': ' . $val;
            } else {
                $query[$name] = $val;
            }
        }
        $query[$ipParam] = $ip;

        if ($auth === self::AUTH_BEARER && $authValue !== '') {
            $headers[] = 'Authorization: Bearer ' . $authValue;
        } elseif ($auth === self::AUTH_HEADER && $authName !== '' && $authValue !== '') {
            $headers[] = $authName . ': ' . $authValue;
        } elseif ($auth === self::AUTH_QUERY && $authName !== '' && $authValue !== '') {
            $query[$authName] = $authValue;
        }

        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $fullUrl = $url . $sep . http_build_query($query);

        $body = self::httpGet($fullUrl, $headers);
        if ($body === '') {
            self::cacheMiss($cacheKey);
            return '';
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            self::cacheMiss($cacheKey);
            return '';
        }
        $text = self::extractField($json, $fieldPath);
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            self::cacheMiss($cacheKey);
            return '';
        }
        $text = mb_substr($text, 0, 120, 'UTF-8');

        if (class_exists('RedisCache') && RedisCache::enabled()) {
            RedisCache::put($cacheKey, $text, self::CACHE_TTL);
        }
        return $text;
    }

    /**
     * @param string $cacheKey
     * @return void
     */
    private static function cacheMiss($cacheKey)
    {
        if (class_exists('RedisCache') && RedisCache::enabled()) {
            RedisCache::put($cacheKey, self::MISS_SENTINEL, self::MISS_TTL);
        }
    }

    /**
     * 仅允许公网 http(s) 主机，防 SSRF（禁止私网/环回/未解析主机）
     *
     * @param string $url
     * @return bool
     */
    public static function assertPublicHttpUrl($url)
    {
        $url = trim((string) $url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if ($host === 'localhost' || $host === '0' || $host === '::1'
            || substr($host, -6) === '.local' || substr($host, -4) === '.lan'
            || substr($host, -6) === '.onion'
        ) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            if ($ip === $host || !filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
        }
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * @param string $raw
     * @return array
     */
    public static function parseExtras($raw)
    {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return array();
        }
        $out = array();
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array  $json
     * @param string $path
     * @return string
     */
    private static function extractField(array $json, $path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        $parts = explode('.', $path);
        $cur = $json;
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || !is_array($cur) || !array_key_exists($p, $cur)) {
                return '';
            }
            $cur = $cur[$p];
        }
        if (is_scalar($cur)) {
            return (string) $cur;
        }
        if (is_array($cur)) {
            $parts = array();
            foreach (array('country', 'region', 'city', 'isp', 'org') as $k) {
                if (!empty($cur[$k]) && is_scalar($cur[$k])) {
                    $parts[] = (string) $cur[$k];
                }
            }
            return implode(' ', $parts);
        }
        return '';
    }

    /**
     * @param string   $url
     * @param string[] $headers
     * @return string
     */
    private static function httpGet($url, array $headers)
    {
        if (!self::assertPublicHttpUrl($url)) {
            return '';
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // 禁止跟随跳转，避免 SSRF 经 302 打到内网
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ApiNexus-IpLocator/' . (defined('VS_VERSION') ? VS_VERSION : '1'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) {
                return '';
            }
            return (string) $body;
        }
        $hdr = '';
        foreach ($headers as $h) {
            $hdr .= $h . "\r\n";
        }
        $ctx = stream_context_create(array(
            'http' => array(
                'method'        => 'GET',
                'header'        => $hdr,
                'timeout'       => self::TIMEOUT,
                'follow_location' => 0,
                'max_redirects' => 0,
            ),
        ));
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    }
}
