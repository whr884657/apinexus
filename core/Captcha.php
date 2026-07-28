<?php
/**
 * 文件：core/Captcha.php
 * 作用：系统级行为验证（极验 3/4）配置与二次校验门面
 */

require_once VS_ROOT . '/core/captcha/geetest3/GeetestLib.php';
require_once VS_ROOT . '/core/captcha/geetest4/Geetest4.php';

class Captcha
{
    const VERSION_3 = '3';
    const VERSION_4 = '4';

    const SCENE_ADMIN_LOGIN = 'admin_login';
    const SCENE_ADMIN_FORGOT = 'admin_forgot';
    const SCENE_USER_LOGIN = 'user_login';
    const SCENE_USER_REGISTER = 'user_register';
    const SCENE_USER_FORGOT = 'user_forgot';

    const SESSION_GT3_SERVER = 'vs_gt3_server';

    /**
     * @return string
     */
    public static function version()
    {
        $v = trim((string) Config::get('geetest_version', self::VERSION_4));
        return $v === self::VERSION_3 ? self::VERSION_3 : self::VERSION_4;
    }

    /**
     * 凭证齐全才视为可启用
     *
     * @return bool
     */
    public static function credentialsReady()
    {
        return trim((string) Config::get('geetest_id', '')) !== ''
            && trim((string) Config::get('geetest_key', '')) !== '';
    }

    /**
     * 某场景是否需要人机验证
     *
     * @param string $scene
     * @return bool
     */
    public static function sceneEnabled($scene)
    {
        $scene = preg_replace('/[^a-z_]/', '', (string) $scene);
        if ($scene === '' || !self::credentialsReady()) {
            return false;
        }
        $key = 'geetest_on_' . $scene;
        return Config::get($key, '0') === '1';
    }

    /**
     * 前端初始化用公开配置（不含密钥）
     *
     * @param string $scene
     * @return array
     */
    public static function publicBoot($scene)
    {
        $enabled = self::sceneEnabled($scene);
        $out = array(
            'enabled'  => $enabled ? 1 : 0,
            'version'  => self::version(),
            'scene'    => (string) $scene,
            'captchaId'=> '',
            'register' => '',
            'product'  => 'float',
        );
        if (!$enabled) {
            return $out;
        }
        $out['captchaId'] = trim((string) Config::get('geetest_id', ''));
        $base = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
        $out['register'] = $base . '/captcha/register.php';
        return $out;
    }

    /**
     * GT3 初始化（写入 session 宕机标记）
     *
     * @return string JSON
     */
    public static function registerGt3()
    {
        if (!self::credentialsReady() || self::version() !== self::VERSION_3) {
            return json_encode(array(
                'success'     => 0,
                'gt'          => '',
                'challenge'   => '',
                'new_captcha' => true,
            ));
        }
        $lib = new GeetestLib(
            trim((string) Config::get('geetest_id', '')),
            trim((string) Config::get('geetest_key', ''))
        );
        $params = array(
            'digestmod'   => 'md5',
            'user_id'     => 'apinexus',
            'client_type' => 'web',
            'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
        );
        $result = $lib->register('md5', $params);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_GT3_SERVER] = ((int) $result->getStatus() === 1) ? 1 : 0;
        }
        return (string) $result->getData();
    }

    /**
     * 若场景开启则校验 POST 中的验证参数；未开启直接通过
     *
     * @param string $scene
     * @param array  $post
     * @return true|string true 或错误文案
     */
    public static function requireValid($scene, array $post)
    {
        if (!self::sceneEnabled($scene)) {
            return true;
        }
        if (self::version() === self::VERSION_3) {
            return self::validateGt3($post);
        }
        return self::validateGt4($post);
    }

    /**
     * @param array $post
     * @return true|string
     */
    private static function validateGt4(array $post)
    {
        $payload = array(
            'lot_number'     => isset($post['lot_number']) ? $post['lot_number'] : '',
            'captcha_output' => isset($post['captcha_output']) ? $post['captcha_output'] : '',
            'pass_token'     => isset($post['pass_token']) ? $post['pass_token'] : '',
            'gen_time'       => isset($post['gen_time']) ? $post['gen_time'] : '',
        );
        $api = trim((string) Config::get('geetest_api_server', ''));
        $res = Geetest4::validate(
            trim((string) Config::get('geetest_id', '')),
            trim((string) Config::get('geetest_key', '')),
            $payload,
            $api
        );
        if (!empty($res['ok'])) {
            return true;
        }
        return isset($res['reason']) && $res['reason'] !== '' ? (string) $res['reason'] : '请完成行为验证';
    }

    /**
     * @param array $post
     * @return true|string
     */
    private static function validateGt3(array $post)
    {
        $challenge = isset($post[GeetestLib::GEETEST_CHALLENGE]) ? trim((string) $post[GeetestLib::GEETEST_CHALLENGE]) : '';
        $validate = isset($post[GeetestLib::GEETEST_VALIDATE]) ? trim((string) $post[GeetestLib::GEETEST_VALIDATE]) : '';
        $seccode = isset($post[GeetestLib::GEETEST_SECCODE]) ? trim((string) $post[GeetestLib::GEETEST_SECCODE]) : '';
        if ($challenge === '' || $validate === '' || $seccode === '') {
            return '请完成行为验证';
        }
        $lib = new GeetestLib(
            trim((string) Config::get('geetest_id', '')),
            trim((string) Config::get('geetest_key', ''))
        );
        $serverOk = 0;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[self::SESSION_GT3_SERVER])) {
            $serverOk = (int) $_SESSION[self::SESSION_GT3_SERVER];
        }
        if ($serverOk === 1) {
            $result = $lib->successValidate($challenge, $validate, $seccode, null);
        } else {
            $result = $lib->failValidate($challenge, $validate, $seccode);
        }
        if ((int) $result->getStatus() === 1) {
            return true;
        }
        $msg = trim((string) $result->getMsg());
        return $msg !== '' ? $msg : '行为验证未通过';
    }

    /**
     * 管理端表单回显
     *
     * @return array
     */
    public static function forAdminForm()
    {
        $key = (string) Config::get('geetest_key', '');
        return array(
            'version'          => self::version(),
            'id'               => (string) Config::get('geetest_id', ''),
            'key'              => $key,
            'api_server'       => (string) Config::get('geetest_api_server', ''),
            'admin_login'      => Config::get('geetest_on_admin_login', '0') === '1',
            'admin_forgot'     => Config::get('geetest_on_admin_forgot', '0') === '1',
            'user_login'       => Config::get('geetest_on_user_login', '0') === '1',
            'user_register'    => Config::get('geetest_on_user_register', '0') === '1',
            'user_forgot'      => Config::get('geetest_on_user_forgot', '0') === '1',
        );
    }
}
