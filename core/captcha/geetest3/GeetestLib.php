<?php
/**
 * 文件：core/captcha/geetest3/GeetestLib.php
 * 作用：极验 3 代服务端 SDK（自官方 gt3-php demo 精简，无 Laravel 依赖）
 *
 * @see https://github.com/GeeTeam/gt3-server-php-laravel-bypass
 */

require_once __DIR__ . '/GeetestLibResult.php';

class GeetestLib
{
    const API_URL = 'http://api.geetest.com';
    const REGISTER_URL = '/register.php';
    const VALIDATE_URL = '/validate.php';
    const JSON_FORMAT = '1';
    const NEW_CAPTCHA = true;
    const HTTP_TIMEOUT_DEFAULT = 5;
    const VERSION = 'apinexus-php:3.1.0';
    const GEETEST_CHALLENGE = 'geetest_challenge';
    const GEETEST_VALIDATE = 'geetest_validate';
    const GEETEST_SECCODE = 'geetest_seccode';

    /** @var string */
    private $geetest_id;
    /** @var string */
    private $geetest_key;
    /** @var GeetestLibResult */
    private $libResult;

    public function __construct($geetest_id, $geetest_key)
    {
        $this->geetest_id = (string) $geetest_id;
        $this->geetest_key = (string) $geetest_key;
        $this->libResult = new GeetestLibResult();
    }

    /**
     * 宕机模式本地初始化
     *
     * @return GeetestLibResult
     */
    public function localInit()
    {
        $this->buildRegisterResult(null, null);
        return $this->libResult;
    }

    /**
     * 验证初始化
     *
     * @param string $digestmod
     * @param array  $params
     * @return GeetestLibResult
     */
    public function register($digestmod, $params)
    {
        $origin_challenge = $this->requestRegister(is_array($params) ? $params : array());
        $this->buildRegisterResult($origin_challenge, $digestmod);
        return $this->libResult;
    }

    /**
     * @param array $params
     * @return string|null
     */
    private function requestRegister($params)
    {
        $params = array_merge($params, array(
            'gt'          => $this->geetest_id,
            'sdk'         => self::VERSION,
            'json_format' => self::JSON_FORMAT,
        ));
        $register_url = self::API_URL . self::REGISTER_URL;
        $origin_challenge = null;
        try {
            $resBody = $this->httpGet($register_url, $params);
            $res_array = json_decode((string) $resBody, true);
            if (is_array($res_array) && isset($res_array['challenge'])) {
                $origin_challenge = $res_array['challenge'];
            }
        } catch (Exception $t) {
            $origin_challenge = '';
        }
        return $origin_challenge;
    }

    /**
     * @param string|null $origin_challenge
     * @param string|null $digestmod
     * @return void
     */
    private function buildRegisterResult($origin_challenge, $digestmod)
    {
        if ($origin_challenge === null || $origin_challenge === '' || $origin_challenge === '0') {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
            $challenge = '';
            for ($i = 0; $i < 32; $i++) {
                $challenge .= $characters[mt_rand(0, strlen($characters) - 1)];
            }
            $this->libResult->setAll(
                0,
                json_encode(array(
                    'success'     => 0,
                    'gt'          => $this->geetest_id,
                    'challenge'   => $challenge,
                    'new_captcha' => self::NEW_CAPTCHA,
                )),
                '请求极验 register 失败，走宕机模式'
            );
            return;
        }

        if ($digestmod === 'sha256') {
            $challenge = $this->sha256_encode($origin_challenge . $this->geetest_key);
        } elseif ($digestmod === 'hmac-sha256') {
            $challenge = $this->hmac_sha256_encode($origin_challenge, $this->geetest_key);
        } else {
            $challenge = $this->md5_encode($origin_challenge . $this->geetest_key);
        }

        $this->libResult->setAll(
            1,
            json_encode(array(
                'success'     => 1,
                'gt'          => $this->geetest_id,
                'challenge'   => $challenge,
                'new_captcha' => self::NEW_CAPTCHA,
            )),
            ''
        );
    }

    /**
     * @param string     $challenge
     * @param string     $validate
     * @param string     $seccode
     * @param array|null $params
     * @return GeetestLibResult
     */
    public function successValidate($challenge, $validate, $seccode, $params)
    {
        if (!$this->checkParam($challenge, $validate, $seccode)) {
            $this->libResult->setAll(0, '', '参数不完整');
            return $this->libResult;
        }
        $response_seccode = $this->requestValidate($challenge, $validate, $seccode);
        if ($response_seccode === '' || $response_seccode === null) {
            $this->libResult->setAll(0, '', '请求极验 validate 失败');
        } elseif ($response_seccode === 'false') {
            $this->libResult->setAll(0, '', '二次验证未通过');
        } else {
            $this->libResult->setAll(1, '', '');
        }
        return $this->libResult;
    }

    /**
     * @param string $challenge
     * @param string $validate
     * @param string $seccode
     * @return GeetestLibResult
     */
    public function failValidate($challenge, $validate, $seccode)
    {
        if (!$this->checkParam($challenge, $validate, $seccode)) {
            $this->libResult->setAll(0, '', '宕机模式参数不完整');
        } else {
            $this->libResult->setAll(1, '', '');
        }
        return $this->libResult;
    }

    /**
     * @param string $challenge
     * @param string $validate
     * @param string $seccode
     * @return string
     */
    private function requestValidate($challenge, $validate, $seccode)
    {
        $params = array(
            'seccode'     => $seccode,
            'json_format' => self::JSON_FORMAT,
            'challenge'   => $challenge,
            'sdk'         => self::VERSION,
            'captchaid'   => $this->geetest_id,
        );
        $validate_url = self::API_URL . self::VALIDATE_URL;
        try {
            $resBody = $this->httpPost($validate_url, $params);
            $res_array = json_decode((string) $resBody, true);
            if (is_array($res_array) && isset($res_array['seccode'])) {
                return (string) $res_array['seccode'];
            }
        } catch (Exception $t) {
            return '';
        }
        return '';
    }

    /**
     * @param string $challenge
     * @param string $validate
     * @param string $seccode
     * @return bool
     */
    private function checkParam($challenge, $validate, $seccode)
    {
        return !(
            $challenge === '' || ctype_space($challenge)
            || $validate === '' || ctype_space($validate)
            || $seccode === '' || ctype_space($seccode)
        );
    }

    /**
     * @param string $url
     * @param array  $params
     * @return string
     */
    private function httpGet($url, $params)
    {
        $url .= '?' . http_build_query($params);
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res === false ? '' : (string) $res;
        }
        $ctx = stream_context_create(array('http' => array('timeout' => self::HTTP_TIMEOUT_DEFAULT)));
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? '' : (string) $res;
    }

    /**
     * @param string $url
     * @param array  $param
     * @return string
     */
    private function httpPost($url, $param)
    {
        $data = http_build_query($param);
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type:application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res === false ? '' : (string) $res;
        }
        $ctx = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $data,
                'timeout' => self::HTTP_TIMEOUT_DEFAULT,
            ),
        ));
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? '' : (string) $res;
    }

    private function md5_encode($value)
    {
        return hash('md5', $value);
    }

    public function sha256_encode($value)
    {
        return hash('sha256', $value);
    }

    private function hmac_sha256_encode($value, $key)
    {
        return hash_hmac('sha256', $value, $key);
    }
}
