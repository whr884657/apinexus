<?php
/**
 * 文件：core/captcha/gt4/LoginController.php
 * 作用：极验 4 代二次校验（官方 gt4-php-demo LoginController 同款逻辑）
 *
 * @see https://github.com/GeeTeam/gt4-php-demo
 * @see https://docs.geetest.com/gt4/deploy/server
 */

class Geetest4Login
{
    const DEFAULT_API = 'http://gcaptcha4.geetest.com';

    /**
     * 官方二次校验流程（HMAC-SHA256 + /validate）
     *
     * @param string $captchaId
     * @param string $captchaKey
     * @param array  $front lot_number / captcha_output / pass_token / gen_time
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

        $api_server = rtrim(trim((string) $apiServer), '/');
        if ($api_server === '') {
            $api_server = self::DEFAULT_API;
        }
        if (stripos($api_server, 'http://') !== 0 && stripos($api_server, 'https://') !== 0) {
            $api_server = 'http://' . ltrim($api_server, '/');
        }

        // 官方：hmac_sha256(lot_number, captcha_key)
        $sign_token = hash_hmac('sha256', $lot_number, $captcha_key);
        $query = array(
            'lot_number'     => $lot_number,
            'captcha_output' => $captcha_output,
            'pass_token'     => $pass_token,
            'gen_time'       => $gen_time,
            'sign_token'     => $sign_token,
        );
        $url = sprintf($api_server . '/validate' . '?captcha_id=%s', rawurlencode($captcha_id));
        $res = self::post_request($url, $query);
        $obj = json_decode($res, true);
        if (!is_array($obj)) {
            return array('result' => 'success', 'reason' => 'request geetest api fail');
        }
        return array(
            'result' => isset($obj['result']) ? (string) $obj['result'] : 'fail',
            'reason' => isset($obj['reason']) ? (string) $obj['reason'] : '',
        );
    }

    /**
     * 官方 post_request：非 200 时 fail-open 返回 success
     *
     * @param string $url
     * @param array  $postdata
     * @return string
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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $result = curl_exec($ch);
            $responsecode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($result === false || $responsecode !== 200) {
                return json_encode(array(
                    'result' => 'success',
                    'reason' => 'request geetest api fail',
                ));
            }
            return (string) $result;
        }

        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'content' => $data,
                'timeout' => 5,
            ),
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        $responsecode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $responsecode = (int) $m[1];
        }
        if ($result === false || $responsecode !== 200) {
            return json_encode(array(
                'result' => 'success',
                'reason' => 'request geetest api fail',
            ));
        }
        return (string) $result;
    }
}
