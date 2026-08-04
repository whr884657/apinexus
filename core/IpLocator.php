<?php
/**
 * 文件：core/IpLocator.php
 * 作用：IP 归属地解析（系统内置或自定义接口），结果写入 apilog.iploc 供数据大屏飞线使用
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
    /** 热路径超时（秒）：内置接口宜短 */
    const TIMEOUT = 2;
    /** 自定义接口热路径超时（秒）：第三方常 >1s */
    const TIMEOUT_CUSTOM = 5;
    /** 设置页探测超时（秒） */
    const TIMEOUT_PROBE = 10;

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
     * 解析模式：builtin=系统内置；custom=自定义接口
     *
     * @return string
     */
    public static function provider()
    {
        if (!class_exists('Config')) {
            return 'builtin';
        }
        $mode = trim((string) Config::get('ip_loc_mode', ''));
        if ($mode === 'custom' || $mode === 'builtin') {
            return $mode;
        }
        // 旧配置：有自定义 URL 则视为 custom，否则内置
        return trim((string) Config::get('ip_loc_url', '')) !== '' ? 'custom' : 'builtin';
    }

    /**
     * 自定义接口请求方式：get（默认）| post
     *
     * @return string
     */
    public static function requestMethod()
    {
        if (!class_exists('Config')) {
            return 'get';
        }
        $m = strtolower(trim((string) Config::get('ip_loc_method', 'get')));
        return ($m === 'post') ? 'post' : 'get';
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

        $text = '';
        if (self::provider() === 'custom') {
            $text = self::lookupCustom($ip, $cacheKey, null, self::TIMEOUT_CUSTOM);
        } else {
            $text = self::lookupBuiltin($ip, $cacheKey, self::TIMEOUT);
        }
        return $text;
    }

    /**
     * 设置页探测：可用表单草稿；跳过负缓存；超时更长；返回可读错误
     *
     * @param string     $ip
     * @param array|null $draft enabled/mode/url/method/ip_param/auth/auth_name/auth_value/field/extras
     * @return array{ok:bool,msg:string,iploc?:string}
     */
    public static function probe($ip, array $draft = null)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return array('ok' => false, 'msg' => '请填写测试 IP');
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return array('ok' => false, 'msg' => 'IP 格式无效');
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return array('ok' => true, 'msg' => '内网地址无需外网解析', 'iploc' => '内网');
        }

        $mode = 'builtin';
        $cfg = null;
        if (is_array($draft) && $draft !== array()) {
            $mode = isset($draft['mode']) ? trim((string) $draft['mode']) : 'builtin';
            if ($mode !== 'custom') {
                $mode = 'builtin';
            }
            $cfg = $draft;
        } else {
            if (!self::enabled()) {
                return array('ok' => false, 'msg' => '请先启用并保存 IP 归属地解析');
            }
            $mode = self::provider();
        }

        // 内置不支持 IPv6
        if ($mode === 'builtin' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return array('ok' => false, 'msg' => '系统内置解析仅支持 IPv4，当前为 IPv6，请改用自定义接口');
        }

        $cacheKey = ''; // 探测不写缓存
        if ($mode === 'custom') {
            $text = self::lookupCustom($ip, $cacheKey, $cfg, self::TIMEOUT_PROBE);
            if ($text === '') {
                return array(
                    'ok'  => false,
                    'msg' => '自定义接口解析失败：超时、非 JSON、字段路径不对或 HTTP 非 2xx（IP：' . $ip . '）',
                );
            }
            return array('ok' => true, 'msg' => '解析成功（自定义接口）', 'iploc' => $text);
        }

        $text = self::lookupBuiltin($ip, $cacheKey, self::TIMEOUT_PROBE);
        if ($text === '') {
            return array('ok' => false, 'msg' => '内置解析失败或上游无结果（IP：' . $ip . '，仅支持 IPv4）');
        }
        return array('ok' => true, 'msg' => '解析成功（系统内置）', 'iploc' => $text);
    }

    /**
     * 系统内置归属地（端点按片段拼接，避免源码明文暴露上游标识）
     *
     * @param string $ip
     * @param string $cacheKey 空串表示不写缓存（探测）
     * @param int    $timeout
     * @return string
     */
    private static function lookupBuiltin($ip, $cacheKey, $timeout = null)
    {
        if ($timeout === null) {
            $timeout = self::TIMEOUT;
        }
        // 内置仅 IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            self::cacheMiss($cacheKey);
            return '';
        }
        $host = 'opendata.' . implode('', array_map('chr', array(98, 97, 105, 100, 117))) . '.com';
        $fullUrl = 'http://' . $host . '/api.php?query=' . rawurlencode($ip)
            . '&resource_id=6006&oe=utf8';
        $body = self::httpRequest($fullUrl, 'GET', array(), array('Accept: application/json'), (int) $timeout);
        if ($body === '') {
            self::cacheMiss($cacheKey);
            return '';
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            self::cacheMiss($cacheKey);
            return '';
        }
        $text = '';
        if (!empty($json['data'][0]['location']) && is_scalar($json['data'][0]['location'])) {
            $text = (string) $json['data'][0]['location'];
        }
        if ($text === '') {
            $text = self::extractField($json, 'data.0.location');
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            self::cacheMiss($cacheKey);
            return '';
        }
        $text = mb_substr($text, 0, 120, 'UTF-8');
        if ($cacheKey !== '' && class_exists('RedisCache') && RedisCache::enabled()) {
            RedisCache::put($cacheKey, $text, self::CACHE_TTL);
        }
        return $text;
    }

    /**
     * @param string     $ip
     * @param string     $cacheKey 空串表示不写缓存（探测）
     * @param array|null $override 草稿配置；null 读 Config
     * @param int|null   $timeout
     * @return string
     */
    private static function lookupCustom($ip, $cacheKey, array $override = null, $timeout = null)
    {
        if ($timeout === null) {
            $timeout = self::TIMEOUT_CUSTOM;
        }
        if (is_array($override)) {
            $url = trim(isset($override['url']) ? (string) $override['url'] : '');
            $ipParam = trim(isset($override['ip_param']) ? (string) $override['ip_param'] : 'ip');
            $auth = isset($override['auth']) ? (int) $override['auth'] : self::AUTH_NONE;
            $authName = trim(isset($override['auth_name']) ? (string) $override['auth_name'] : '');
            $authValue = isset($override['auth_value']) ? (string) $override['auth_value'] : '';
            $fieldPath = trim(isset($override['field']) ? (string) $override['field'] : '');
            $extras = self::parseExtras(isset($override['extras']) ? $override['extras'] : '[]');
            $method = isset($override['method']) ? strtolower(trim((string) $override['method'])) : 'get';
            if ($method !== 'post') {
                $method = 'get';
            }
        } else {
            $url = trim((string) Config::get('ip_loc_url', ''));
            $ipParam = trim((string) Config::get('ip_loc_ip_param', 'ip'));
            $auth = (int) Config::get('ip_loc_auth', (string) self::AUTH_NONE);
            $authName = trim((string) Config::get('ip_loc_auth_name', ''));
            $authValue = (string) Config::get('ip_loc_auth_value', '');
            $fieldPath = trim((string) Config::get('ip_loc_field', ''));
            $extras = self::parseExtras(Config::get('ip_loc_extras', '[]'));
            $method = self::requestMethod();
        }
        if ($ipParam === '') {
            $ipParam = 'ip';
        }
        if ($url === '' || !self::assertPublicHttpUrl($url)) {
            self::cacheMiss($cacheKey);
            return '';
        }

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

        if ($method === 'post') {
            $body = self::httpRequest($url, 'POST', $query, $headers, (int) $timeout);
        } else {
            $sep = (strpos($url, '?') !== false) ? '&' : '?';
            $fullUrl = $url . $sep . http_build_query($query);
            $body = self::httpRequest($fullUrl, 'GET', array(), $headers, (int) $timeout);
        }
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
        if ($text === '' && $fieldPath === '') {
            foreach (array('data.0.location', 'data.city', 'city', 'location', 'result.ad_info.city') as $try) {
                $text = self::extractField($json, $try);
                if ($text !== '') {
                    break;
                }
            }
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            self::cacheMiss($cacheKey);
            return '';
        }
        $text = mb_substr($text, 0, 120, 'UTF-8');

        if ($cacheKey !== '' && class_exists('RedisCache') && RedisCache::enabled()) {
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
        if ($cacheKey === '') {
            return;
        }
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
            if ($p === '' || !is_array($cur)) {
                return '';
            }
            // 支持 data.0.location 这类数字下标
            if (ctype_digit($p)) {
                $p = (int) $p;
            }
            if (!array_key_exists($p, $cur)) {
                return '';
            }
            $cur = $cur[$p];
        }
        if (is_scalar($cur)) {
            return (string) $cur;
        }
        if (is_array($cur)) {
            $parts = array();
            foreach (array('country', 'region', 'city', 'isp', 'org', 'location') as $k) {
                if (!empty($cur[$k]) && is_scalar($cur[$k])) {
                    $parts[] = (string) $cur[$k];
                }
            }
            return implode(' ', $parts);
        }
        return '';
    }

    /**
     * @param string               $url
     * @param string               $method GET|POST
     * @param array<string,mixed>  $formParams POST 时作 application/x-www-form-urlencoded；GET 时忽略（URL 已含查询串）
     * @param string[]             $headers
     * @return string
     */
    private static function httpRequest($url, $method, array $formParams, array $headers, $timeout = null)
    {
        if (!self::assertPublicHttpUrl($url)) {
            return '';
        }
        $timeout = $timeout === null ? self::TIMEOUT : (int) $timeout;
        if ($timeout < 1) {
            $timeout = 1;
        }
        if ($timeout > 30) {
            $timeout = 30;
        }
        $method = strtoupper(trim((string) $method));
        if ($method !== 'POST') {
            $method = 'GET';
        }
        $bodyStr = '';
        if ($method === 'POST') {
            $bodyStr = http_build_query($formParams);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // 禁止跟随跳转，避免 SSRF 经 302 打到内网
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ApiNexus-IpLocator/' . (defined('VS_VERSION') ? VS_VERSION : '1'));
            // 自定义上游可能证书不全；内置同路径兼容
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
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
        $httpOpts = array(
            'method'          => $method,
            'header'          => $hdr,
            'timeout'         => $timeout,
            'follow_location' => 0,
            'max_redirects'   => 0,
        );
        if ($method === 'POST') {
            $httpOpts['content'] = $bodyStr;
        }
        $ctx = stream_context_create(array('http' => $httpOpts));
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    }
}
