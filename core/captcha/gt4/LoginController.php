<?php
/**
 * 文件：core/captcha/gt4/LoginController.php
 * 作用：极验 4 代二次校验（官方流程 + 本站安全加固：fail-closed / HTTPS / 域名白名单）
 *
 * @see https://github.com/GeeTeam/gt4-php-demo
 * @see https://docs.geetest.com/gt4/deploy/server
 */

class Geetest4Login
{
    const DEFAULT_API = 'https://gcaptcha4.geetest.com';
    const HTTP_TIMEOUT = 5;

    /**
     * 允许的二次校验主机（防 SSRF）
     *
     * @var array
     */
    private static $allowedHosts = array(
        'gcaptcha4.geetest.com',
        'gcaptcha4.geevisit.com',
    );

    /**
     * @param string $captchaId
     * @param string $captchaKey
     * @param array  $front
     * @param string $apiServer
     * @return array{result:string,reason:string}
     */
    public static function validate($captchaId, $captchaKey, array $front, $apiServer = '')
    {
        $captcha_id = trim((string) $captchaId);
        $captcha_key = trim((string) $captchaKey);
        $lot_number = isset($front['lot_number']) ? trim((string) $front['lot_number']) : '';
        $captcha_output = isset($front['captcha_output']) ? trim((string) $front['captcha_output']) : '';
        $pass_token = isset($front['pass_token']) ? trim((string) $front['pass_token']) : '';
        $gen_time = isset($front['gen_time']) ? trim((string) $front['gen_time']) : '';

        if ($captcha_id === '' || $captcha_key === '' || $lot_number === ''
            || $captcha_output === '' || $pass_token === '' || $gen_time === ''
        ) {
            return array('result' => 'fail', 'reason' => '请完成行为验证');
        }

        $api_server = self::normalizeApiServer($apiServer);
        if ($api_server === '') {
            return array('result' => 'fail', 'reason' => '行为验证服务地址无效');
        }

        $sign_token = hash_hmac('sha256', $lot_number, $captcha_key);
        $query = array(
            'lot_number'     => $lot_number,
            'captcha_output' => $captcha_output,
            'pass_token'     => $pass_token,
            'gen_time'       => $gen_time,
            'sign_token'     => $sign_token,
        );
        $url = $api_server . '/validate?captcha_id=' . rawurlencode($captcha_id);
        $res = self::post_request($url, $query);
        if ($res === null) {
            // 安全加固：通道异常一律拒绝（不再 fail-open）
            return array('result' => 'fail', 'reason' => '行为验证服务暂不可用，请稍后重试');
        }
        $obj = json_decode($res, true);
        if (!is_array($obj)) {
            return array('result' => 'fail', 'reason' => '行为验证服务响应异常');
        }
        return array(
            'result' => isset($obj['result']) ? (string) $obj['result'] : 'fail',
            'reason' => isset($obj['reason']) ? (string) $obj['reason'] : '',
        );
    }

    /**
     * @param string $apiServer
     * @return string 合法根地址或空串
     */
    public static function normalizeApiServer($apiServer)
    {
        $apiServer = trim((string) $apiServer);
        if ($apiServer === '') {
            return self::DEFAULT_API;
        }
        if (stripos($apiServer, 'http://') === 0) {
            $apiServer = 'https://' . substr($apiServer, 7);
        }
        if (stripos($apiServer, 'https://') !== 0) {
            $apiServer = 'https://' . ltrim($apiServer, '/');
        }
        $apiServer = rtrim($apiServer, '/');
        $parts = parse_url($apiServer);
        if (!is_array($parts) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return '';
        }
        $ok = false;
        foreach (self::$allowedHosts as $allow) {
            if ($host === $allow || substr($host, -strlen('.' . $allow)) === '.' . $allow) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return '';
        }
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        if ($path !== '' && $path !== '/') {
            return '';
        }
        return 'https://' . $host;
    }

    /**
     * @param string $url
     * @param array  $postdata
     * @return string|null 成功返回 body；失败返回 null（fail-closed）
     */
    private static function post_request($url, $postdata)
    {
        $data = http_build_query($postdata);
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT);
            if (defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $result = curl_exec($ch);
            $responsecode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($result === false || $responsecode !== 200) {
                return null;
            }
            return (string) $result;
        }

        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $data,
                'timeout' => self::HTTP_TIMEOUT,
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        $responsecode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $responsecode = (int) $m[1];
        }
        if ($result === false || $responsecode !== 200) {
            return null;
        }
        return (string) $result;
    }
}
