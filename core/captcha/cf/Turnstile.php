<?php
/**
 * 文件：core/captcha/cf/Turnstile.php
 * 作用：Cloudflare Turnstile Siteverify 二次校验（fail-closed）
 */

class CaptchaTurnstile
{
    const SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    const TOKEN_MAX = 2048;

    /**
     * @param string $secret  cfkey
     * @param string $token   前端 cf-turnstile-response
     * @param string $remoteIp 可选客户端 IP
     * @return array{ok:bool,success:bool,errors:array,raw?:array}
     */
    public static function validate($secret, $token, $remoteIp = '')
    {
        $secret = trim((string) $secret);
        $token = trim((string) $token);
        $remoteIp = trim((string) $remoteIp);
        if ($secret === '') {
            return array('ok' => false, 'success' => false, 'errors' => array('missing-input-secret'));
        }
        if ($token === '' || strlen($token) > self::TOKEN_MAX) {
            return array('ok' => false, 'success' => false, 'errors' => array('missing-input-response'));
        }

        $fields = array(
            'secret'   => $secret,
            'response' => $token,
        );
        if ($remoteIp !== '' && $remoteIp !== '0.0.0.0') {
            $fields['remoteip'] = $remoteIp;
        }

        $body = http_build_query($fields);
        $raw = self::postForm(self::SITEVERIFY, $body);
        if ($raw === null) {
            return array('ok' => false, 'success' => false, 'errors' => array('internal-error'));
        }
        $obj = json_decode($raw, true);
        if (!is_array($obj)) {
            return array('ok' => false, 'success' => false, 'errors' => array('internal-error'));
        }
        $success = !empty($obj['success']);
        $errors = array();
        if (isset($obj['error-codes']) && is_array($obj['error-codes'])) {
            foreach ($obj['error-codes'] as $e) {
                if (is_string($e) || is_numeric($e)) {
                    $errors[] = (string) $e;
                }
            }
        }
        // 成功时必须带回 hostname，且须落在本站可信主机白名单（纵深：防同 Site Key 其它域名 token）
        if ($success) {
            $hostname = isset($obj['hostname']) ? strtolower(trim((string) $obj['hostname'])) : '';
            if ($hostname === '' || !self::hostnameAllowed($hostname)) {
                $success = false;
                $errors[] = 'hostname-mismatch';
            }
        }
        return array(
            'ok'      => $success,
            'success' => $success,
            'errors'  => $errors,
            'raw'     => $obj,
        );
    }

    /**
     * Turnstile 返回的 hostname 是否属于本站
     *
     * @param string $hostname
     * @return bool
     */
    private static function hostnameAllowed($hostname)
    {
        $hostname = strtolower(trim((string) $hostname));
        if ($hostname === '') {
            return false;
        }
        if (function_exists('vs_is_trusted_host')) {
            return vs_is_trusted_host($hostname);
        }
        return false;
    }

    /**
     * @param string $url
     * @param string $body
     * @return string|null
     */
    private static function postForm($url, $body)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            if ($ch === false) {
                return null;
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-type: application/x-www-form-urlencoded',
                class_exists('OutboundUa')
                    ? OutboundUa::headerLine('Captcha')
                    : ('User-Agent: ApiNexus-Captcha/' . (defined('VS_VERSION') ? VS_VERSION : '1')),
            ));
            curl_setopt($ch, CURLOPT_USERAGENT, class_exists('OutboundUa')
                ? OutboundUa::build('Captcha')
                : ('ApiNexus-Captcha/' . (defined('VS_VERSION') ? VS_VERSION : '1')));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            if (defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }
            if (function_exists('vs_curl_ssl_verify')) {
                vs_curl_ssl_verify($ch);
            }
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (function_exists('vs_curl_close')) {
                vs_curl_close($ch);
            } else {
                curl_close($ch);
            }
            if ($res === false || $code !== 200 || !is_string($res)) {
                return null;
            }
            return $res;
        }

        $ctx = stream_context_create(array(
            'http' => array(
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $body,
                'timeout'       => 8,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));
        $res = @file_get_contents($url, false, $ctx);
        if (!is_string($res)) {
            return null;
        }
        // 与 cURL 分支一致：仅接受 HTTP 2xx（ignore_errors 时非 2xx 仍可能有 body）
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hline) {
                if (is_string($hline) && preg_match('/^HTTP\/\S+\s+(\d{3})/i', $hline, $hm)) {
                    $status = (int) $hm[1];
                    break;
                }
            }
        }
        if ($status < 200 || $status > 299) {
            return null;
        }
        return $res;
    }
}
