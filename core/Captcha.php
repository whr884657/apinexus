<?php
/**
 * 文件：core/Captcha.php
 * 作用：系统级验证码门面（本地图 / 极验3 / 极验4，仅启用其一）
 */

require_once VS_ROOT . '/core/captcha/gt3/GeetestLib.php';
require_once VS_ROOT . '/core/captcha/gt3/CheckGeetestStatus.php';
require_once VS_ROOT . '/core/captcha/gt4/LoginController.php';
require_once VS_ROOT . '/core/captcha/local.php';
require_once VS_ROOT . '/core/captcha/helper.php';

class Captcha
{
    const MODE_LOCAL = 'local';
    const MODE_GT3 = 'gt3';
    const MODE_GT4 = 'gt4';

    const SCENE_ADMIN_LOGIN = 'admin_login';
    const SCENE_ADMIN_FORGOT = 'admin_forgot';
    const SCENE_USER_LOGIN = 'user_login';
    const SCENE_USER_REGISTER = 'user_register';
    const SCENE_USER_FORGOT = 'user_forgot';

    const SESSION_GT3_SERVER = 'vs_gt3_server';

    /**
     * @return string local|gt3|gt4
     */
    public static function mode()
    {
        $m = strtolower(trim((string) Config::get('captcha_mode', self::MODE_LOCAL)));
        if ($m === self::MODE_GT3 || $m === self::MODE_GT4 || $m === self::MODE_LOCAL) {
            return $m;
        }
        // 旧键兼容读一次后仍归一到合法值
        $legacy = trim((string) Config::get('geetest_version', ''));
        if ($legacy === '3') {
            return self::MODE_GT3;
        }
        if ($legacy === '4') {
            return self::MODE_GT4;
        }
        return self::MODE_LOCAL;
    }

    /**
     * 当前模式凭证是否齐全（本地无需凭证）
     *
     * @return bool
     */
    public static function credentialsReady()
    {
        $mode = self::mode();
        if ($mode === self::MODE_LOCAL) {
            return function_exists('imagecreatetruecolor');
        }
        if ($mode === self::MODE_GT3) {
            return self::gt3Id() !== '' && self::gt3Key() !== '';
        }
        return self::gt4Id() !== '' && self::gt4Key() !== '';
    }

    /**
     * @param string $scene
     * @return bool
     */
    public static function sceneEnabled($scene)
    {
        $scene = preg_replace('/[^a-z_]/', '', (string) $scene);
        if ($scene === '' || !self::credentialsReady()) {
            return false;
        }
        return Config::get('captcha_on_' . $scene, Config::get('geetest_on_' . $scene, '0')) === '1';
    }

    /**
     * @return string
     */
    public static function gt3Id()
    {
        $v = trim((string) Config::get('gt3_id', ''));
        if ($v !== '') {
            return $v;
        }
        return trim((string) Config::get('geetest_id', ''));
    }

    /**
     * @return string
     */
    public static function gt3Key()
    {
        $v = trim((string) Config::get('gt3_key', ''));
        if ($v !== '') {
            return $v;
        }
        return trim((string) Config::get('geetest_key', ''));
    }

    /**
     * @return string
     */
    public static function gt4Id()
    {
        $v = trim((string) Config::get('gt4_id', ''));
        if ($v !== '') {
            return $v;
        }
        return trim((string) Config::get('geetest_id', ''));
    }

    /**
     * @return string
     */
    public static function gt4Key()
    {
        $v = trim((string) Config::get('gt4_key', ''));
        if ($v !== '') {
            return $v;
        }
        return trim((string) Config::get('geetest_key', ''));
    }

    /**
     * @return string
     */
    public static function gt4Api()
    {
        return trim((string) Config::get('gt4_api', Config::get('geetest_api_server', '')));
    }

    /**
     * 前端公开配置（不含密钥）
     *
     * @param string $scene
     * @return array
     */
    public static function publicBoot($scene)
    {
        $enabled = self::sceneEnabled($scene);
        $mode = self::mode();
        $base = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
        $out = array(
            'enabled'   => $enabled ? 1 : 0,
            'mode'      => $mode,
            'scene'     => (string) $scene,
            'captchaId' => '',
            'register'  => '',
            'image'     => '',
            'product'   => 'float',
        );
        if (!$enabled) {
            return $out;
        }
        if ($mode === self::MODE_LOCAL) {
            $out['image'] = $base . '/core/captcha/image.php';
            return $out;
        }
        if ($mode === self::MODE_GT3) {
            $out['captchaId'] = self::gt3Id();
            $out['register'] = $base . '/core/captcha/register.php';
            return $out;
        }
        $out['captchaId'] = self::gt4Id();
        return $out;
    }

    /**
     * 极验 3 初始化 JSON（官方 first_register 流程）
     *
     * @return string
     */
    public static function registerGt3()
    {
        if (self::mode() !== self::MODE_GT3 || !self::credentialsReady()) {
            return json_encode(array(
                'success'     => 0,
                'gt'          => '',
                'challenge'   => '',
                'new_captcha' => true,
            ));
        }
        $id = self::gt3Id();
        $key = self::gt3Key();
        $gtLib = new GeetestLib($id, $key);
        $digestmod = 'md5';
        $params = array(
            'digestmod'   => $digestmod,
            'user_id'     => 'apinexus',
            'client_type' => 'web',
            'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
        );
        if (CheckGeetestStatus::getGeetestStatus($id)) {
            $result = $gtLib->register($digestmod, $params);
        } else {
            $result = $gtLib->localInit();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_GT3_SERVER] = ((int) $result->getStatus() === 1) ? 1 : 0;
        }
        return (string) $result->getData();
    }

    /**
     * @param string $scene
     * @param array  $post
     * @return true|string
     */
    public static function requireValid($scene, array $post)
    {
        if (!self::sceneEnabled($scene)) {
            return true;
        }
        $mode = self::mode();
        if ($mode === self::MODE_LOCAL) {
            $code = isset($post['captcha_code']) ? $post['captcha_code'] : '';
            if (!CaptchaLocal::verify($code)) {
                return '验证码错误或已过期';
            }
            return true;
        }
        if ($mode === self::MODE_GT3) {
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
        $obj = Geetest4Login::validate(
            self::gt4Id(),
            self::gt4Key(),
            array(
                'lot_number'     => isset($post['lot_number']) ? $post['lot_number'] : '',
                'captcha_output' => isset($post['captcha_output']) ? $post['captcha_output'] : '',
                'pass_token'     => isset($post['pass_token']) ? $post['pass_token'] : '',
                'gen_time'       => isset($post['gen_time']) ? $post['gen_time'] : '',
            ),
            self::gt4Api()
        );
        if (isset($obj['result']) && $obj['result'] === 'success') {
            return true;
        }
        $reason = isset($obj['reason']) ? trim((string) $obj['reason']) : '';
        return $reason !== '' ? $reason : '行为验证未通过';
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
        $gtLib = new GeetestLib(self::gt3Id(), self::gt3Key());
        $serverOk = 0;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[self::SESSION_GT3_SERVER])) {
            $serverOk = (int) $_SESSION[self::SESSION_GT3_SERVER];
        }
        if ($serverOk === 1) {
            $result = $gtLib->successValidate($challenge, $validate, $seccode, null);
        } else {
            $result = $gtLib->failValidate($challenge, $validate, $seccode);
        }
        if ((int) $result->getStatus() === 1) {
            return true;
        }
        $msg = trim((string) $result->getMsg());
        return $msg !== '' ? $msg : '行为验证未通过';
    }

    /**
     * 管理端表单
     *
     * @return array
     */
    public static function forAdminForm()
    {
        return array(
            'mode'          => self::mode(),
            'gt3_id'        => self::gt3Id(),
            'gt3_has_key'   => self::gt3Key() !== '',
            'gt4_id'        => self::gt4Id(),
            'gt4_has_key'   => self::gt4Key() !== '',
            'gt4_api'       => self::gt4Api(),
            'admin_login'   => Config::get('captcha_on_admin_login', Config::get('geetest_on_admin_login', '0')) === '1',
            'admin_forgot'  => Config::get('captcha_on_admin_forgot', Config::get('geetest_on_admin_forgot', '0')) === '1',
            'user_login'    => Config::get('captcha_on_user_login', Config::get('geetest_on_user_login', '0')) === '1',
            'user_register' => Config::get('captcha_on_user_register', Config::get('geetest_on_user_register', '0')) === '1',
            'user_forgot'   => Config::get('captcha_on_user_forgot', Config::get('geetest_on_user_forgot', '0')) === '1',
        );
    }
}
