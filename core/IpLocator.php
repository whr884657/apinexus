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
            if (is_string($cached) && $cached !== '') {
                return mb_substr($cached, 0, 120, 'UTF-8');
            }
        }

        $url = trim((string) Config::get('ip_loc_url', ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
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
            return '';
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return '';
        }
        $text = self::extractField($json, $fieldPath);
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            return '';
        }
        $text = mb_substr($text, 0, 120, 'UTF-8');

        if (class_exists('RedisCache') && RedisCache::enabled()) {
            RedisCache::put($cacheKey, $text, self::CACHE_TTL);
        }
        return $text;
    }

    /**
     * @param string $raw
     * @return array<int, array{name:string,value:string,via:string}>
     */
    public static function parseExtras($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array();
        }
        $out = array();
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name === '') {
                continue;
            }
            $via = isset($row['via']) ? strtolower(trim((string) $row['via'])) : 'query';
            if ($via !== 'header') {
                $via = 'query';
            }
            $out[] = array(
                'name'  => mb_substr($name, 0, 64, 'UTF-8'),
                'value' => isset($row['value']) ? (string) $row['value'] : '',
                'via'   => $via,
            );
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array  $data
     * @param string $path 点分路径，如 data.city 或 result.ad_info.city
     * @return string
     */
    public static function extractField(array $data, $path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            // 常见兜底字段
            foreach (array('addr', 'address', 'location', 'region', 'city', 'data', 'result') as $k) {
                if (!isset($data[$k])) {
                    continue;
                }
                if (is_string($data[$k]) && $data[$k] !== '') {
                    return $data[$k];
                }
                if (is_array($data[$k])) {
                    $nested = self::extractField($data[$k], '');
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }
            return '';
        }
        $cur = $data;
        foreach (explode('.', $path) as $seg) {
            $seg = trim($seg);
            if ($seg === '' || !is_array($cur) || !array_key_exists($seg, $cur)) {
                return '';
            }
            $cur = $cur[$seg];
        }
        if (is_scalar($cur)) {
            return (string) $cur;
        }
        if (is_array($cur)) {
            // 数组则尝试拼常见子字段
            $parts = array();
            foreach (array('country', 'province', 'city', 'district', 'isp') as $k) {
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
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
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
                'method'  => 'GET',
                'header'  => $hdr,
                'timeout' => self::TIMEOUT,
            ),
        ));
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    }
}
