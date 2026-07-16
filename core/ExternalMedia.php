<?php
/**
 * 文件：core/ExternalMedia.php
 * 作用：外链图片安全代理（解决跨域、部分站点防盗链；仅服务端拉取后回传）
 */

class ExternalMedia
{
    const MAX_BYTES = 2097152; // 2MB
    const TIMEOUT = 8;

    /**
     * 处理代理请求并直接输出
     *
     * @return void
     */
    public static function handleRequest()
    {
        $raw = isset($_GET['u']) ? (string) $_GET['u'] : '';
        $url = '';
        if ($raw !== '') {
            $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
            if (is_string($decoded) && $decoded !== '') {
                $url = $decoded;
            } else {
                $url = rawurldecode($raw);
            }
        }

        $url = trim($url);
        if ($url === '' || !vs_is_external_http_url($url)) {
            self::fail(400, '无效的媒体地址');
        }

        if (!self::isSafeRemoteHost($url)) {
            self::fail(403, '不允许的目标地址');
        }

        $result = self::fetch($url);
        if ($result === null) {
            self::fail(502, '无法获取远程媒体');
        }

        header('Content-Type: ' . $result['type']);
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo $result['body'];
        exit;
    }

    /**
     * @param string $url
     * @return bool
     */
    public static function isSafeRemoteHost($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }
        if (preg_match('/^(10\.|192\.168\.|169\.254\.)/', $host)) {
            return false;
        }
        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host)) {
            return false;
        }
        $ips = @gethostbynamel($host);
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param string $url
     * @return array{body:string,type:string}|null
     */
    public static function fetch($url)
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(array(
                'http' => array(
                    'timeout' => self::TIMEOUT,
                    'header'  => "User-Agent: misc-api-media-proxy\r\n",
                ),
                'ssl' => array(
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ),
            ));
            $body = @file_get_contents($url, false, $ctx);
            if (!is_string($body) || $body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }
            $type = 'application/octet-stream';
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (stripos($h, 'Content-Type:') === 0) {
                        $type = trim(substr($h, 13));
                        break;
                    }
                }
            }
            if (!self::isAllowedImageType($type, $body)) {
                return null;
            }
            return array('body' => $body, 'type' => self::normalizeImageType($type, $body));
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'misc-api-media-proxy',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code < 200 || $code >= 300 || strlen($body) > self::MAX_BYTES) {
            return null;
        }
        if (!self::isAllowedImageType($type, $body)) {
            return null;
        }
        return array('body' => $body, 'type' => self::normalizeImageType($type, $body));
    }

    /**
     * @param string $type
     * @param string $body
     * @return bool
     */
    private static function isAllowedImageType($type, $body)
    {
        $type = strtolower(trim(explode(';', $type)[0]));
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon');
        if (in_array($type, $allowed, true)) {
            return true;
        }
        // 部分源站不带 Content-Type，按魔数兜底
        if (strncmp($body, "\xFF\xD8\xFF", 3) === 0) {
            return true;
        }
        if (strncmp($body, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return true;
        }
        if (strncmp($body, 'GIF87a', 6) === 0 || strncmp($body, 'GIF89a', 6) === 0) {
            return true;
        }
        if (strncmp($body, 'RIFF', 4) === 0 && substr($body, 8, 4) === 'WEBP') {
            return true;
        }
        if (stripos($body, '<svg') !== false) {
            return true;
        }
        return false;
    }

    /**
     * @param string $type
     * @param string $body
     * @return string
     */
    private static function normalizeImageType($type, $body)
    {
        $type = strtolower(trim(explode(';', $type)[0]));
        if (strpos($type, 'image/') === 0) {
            return $type;
        }
        if (strncmp($body, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }
        if (strncmp($body, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'image/png';
        }
        if (strncmp($body, 'GIF8', 4) === 0) {
            return 'image/gif';
        }
        if (strncmp($body, 'RIFF', 4) === 0) {
            return 'image/webp';
        }
        if (stripos($body, '<svg') !== false) {
            return 'image/svg+xml';
        }
        return 'application/octet-stream';
    }

    /**
     * @param int    $code
     * @param string $msg
     * @return void
     */
    private static function fail($code, $msg)
    {
        http_response_code((int) $code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $msg;
        exit;
    }
}
