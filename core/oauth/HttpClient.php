<?php
/**
 * 文件：core/oauth/HttpClient.php
 * 作用：OAuth 相关 HTTP 请求（PHP 7.4+）
 */

class OAuthHttpClient
{
    /**
     * @param string $url
     * @param int    $timeout
     * @return string|false
     */
    public static function get($url, $timeout = 20)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => class_exists('OutboundUa') ? OutboundUa::build('OAuth') : ('ApiNexus-OAuth/' . VS_VERSION),
            ));
            vs_curl_ssl_verify($ch);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            vs_curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return $body;
            }
            return false;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => (class_exists('OutboundUa') ? OutboundUa::headerLine('OAuth') : ('User-Agent: ApiNexus-OAuth/' . VS_VERSION)) . "\r\n",
            ),
            'ssl' => function_exists('vs_stream_ssl_options') ? vs_stream_ssl_options() : array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        $body = @file_get_contents($url, false, $context);
        return $body === false ? false : $body;
    }

    /**
     * @param string $url
     * @param array  $fields
     * @param int    $timeout
     * @return string|false
     */
    public static function postForm($url, array $fields, $timeout = 20)
    {
        $body = http_build_query($fields);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => class_exists('OutboundUa') ? OutboundUa::build('OAuth') : ('ApiNexus-OAuth/' . VS_VERSION),
            ));
            vs_curl_ssl_verify($ch);
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            vs_curl_close($ch);
            if ($response !== false && $code >= 200 && $code < 300) {
                return $response;
            }
            return false;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . (class_exists('OutboundUa') ? OutboundUa::headerLine('OAuth') : ('User-Agent: ApiNexus-OAuth/' . VS_VERSION)) . "\r\n"
                    . 'Content-Length: ' . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => $timeout,
            ),
            'ssl' => function_exists('vs_stream_ssl_options') ? vs_stream_ssl_options() : array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        $response = @file_get_contents($url, false, $context);
        return $response === false ? false : $response;
    }
}
