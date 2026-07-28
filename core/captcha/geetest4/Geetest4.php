<?php
/**
 * 文件：core/captcha/geetest4/Geetest4.php
 * 作用：极验 4 代二次校验（自官方 gt4-php-demo 精简）
 *
 * @see https://github.com/GeeTeam/gt4-php-demo
 * @see https://docs.geetest.com/gt4/deploy/server
 */

class Geetest4
{
    const DEFAULT_API = 'http://gcaptcha4.geetest.com';
    const HTTP_TIMEOUT = 5;

    /**
     * @param string $captchaId
     * @param string $captchaKey
     * @param array  $payload lot_number / captcha_output / pass_token / gen_time
     * @param string $apiServer
     * @return array{ok:bool,reason:string}
     */
    public static function validate($captchaId, $captchaKey, array $payload, $apiServer = '')
    {
        $captchaId = trim((string) $captchaId);
        $captchaKey = trim((string) $captchaKey);
        $lot = isset($payload['lot_number']) ? trim((string) $payload['lot_number']) : '';
        $output = isset($payload['captcha_output']) ? trim((string) $payload['captcha_output']) : '';
        $pass = isset($payload['pass_token']) ? trim((string) $payload['pass_token']) : '';
        $gen = isset($payload['gen_time']) ? trim((string) $payload['gen_time']) : '';

        if ($captchaId === '' || $captchaKey === '' || $lot === '' || $output === '' || $pass === '' || $gen === '') {
            return array('ok' => false, 'reason' => '请完成行为验证');
        }

        $apiServer = rtrim(trim((string) $apiServer), '/');
        if ($apiServer === '') {
            $apiServer = self::DEFAULT_API;
        }
        // 强制 https 当站点为 https，避免混合内容；校验接口本身可用 http
        if (stripos($apiServer, 'http://') !== 0 && stripos($apiServer, 'https://') !== 0) {
            $apiServer = 'https://' . ltrim($apiServer, '/');
        }

        $sign = hash_hmac('sha256', $lot, $captchaKey);
        $query = array(
            'lot_number'     => $lot,
            'captcha_output' => $output,
            'pass_token'     => $pass,
            'gen_time'       => $gen,
            'sign_token'     => $sign,
        );
        $url = $apiServer . '/validate?captcha_id=' . rawurlencode($captchaId);
        $raw = self::httpPost($url, $query);
        if ($raw === '') {
            // 官方建议：接口异常时不阻断业务
            return array('ok' => true, 'reason' => 'request geetest api fail');
        }
        $obj = json_decode($raw, true);
        if (!is_array($obj)) {
            return array('ok' => true, 'reason' => 'request geetest api fail');
        }
        $result = isset($obj['result']) ? (string) $obj['result'] : '';
        $reason = isset($obj['reason']) ? (string) $obj['reason'] : '';
        if ($result === 'success') {
            return array('ok' => true, 'reason' => $reason);
        }
        return array('ok' => false, 'reason' => ($reason !== '' ? $reason : '行为验证未通过'));
    }

    /**
     * @param string $url
     * @param array  $postdata
     * @return string
     */
    private static function httpPost($url, array $postdata)
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
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res === false || $code !== 200) {
                return '';
            }
            return (string) $res;
        }
        $ctx = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $data,
                'timeout' => self::HTTP_TIMEOUT,
            ),
        ));
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? '' : (string) $res;
    }
}
