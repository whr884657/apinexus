<?php
/**
 * 文件：user/login.php
 * 作用：用户登录页面（账号密码 / 邮箱验证码）
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';
require_once VS_ROOT . '/admin/includes/auth_layout.php';

InstallChecker::requireInstalled();

$base = vs_base_url();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    UserAuth::logout();
    vs_redirect($base . '/user/login.php');
}

UserAuth::redirectIfLoggedIn();

$siteName = SiteContext::siteName();
$mailEnabled = Config::isMailEnabled();
$codeTtl = 300;
$loginRedirect = vs_safe_login_redirect(
    isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : '')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    vs_auth_require_post();
    $action = (string) $_POST['action'];
    $loginRedirect = vs_safe_login_redirect(
        isset($_POST['redirect']) ? $_POST['redirect'] : $loginRedirect
    );

    if ($action === 'send_code') {
        $mailPurpose = AuthSecurity::MAIL_PURPOSE_USER_LOGIN;

        if (!$mailEnabled) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '邮箱发信功能尚未配置，请联系管理员在后台「系统设置」中配置邮箱'));
        }

        $ticket = isset($_POST['mail_ticket']) ? (string) $_POST['mail_ticket'] : '';
        if (!AuthSecurity::validateAndConsumeMailTicket($mailPurpose, $ticket)) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请求无效，请刷新页面后重试'));
        }

        $captchaErr = Captcha::requireValid(Captcha::SCENE_USER_LOGIN, $_POST);
        if ($captchaErr !== true) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => $captchaErr));
        }

        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        if ($email === '') {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请输入邮箱'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请输入有效的邮箱地址'));
        }
        $email = vs_normalize_email($email);

        $mailLimitMsg = AuthSecurity::checkMailCodeAllowed($email);
        if ($mailLimitMsg !== null) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => $mailLimitMsg));
        }

        AuthSecurity::recordMailCodeAttempt($email);

        try {
            $user = UserAuth::findByEmail($email);
            if (!$user) {
                vs_auth_json_mail($mailPurpose, array(
                    'code' => 0,
                    'msg'  => '该邮箱未在本站注册，无法发送验证码',
                ));
            }

            $code = (string) random_int(100000, 999999);
            $emailCanonical = vs_normalize_email(isset($user['email']) ? $user['email'] : $email);
            $_SESSION['user_login_id'] = (int) $user['id'];
            $_SESSION['user_login_email'] = $emailCanonical;
            $_SESSION['user_login_code'] = $code;
            $_SESSION['user_login_code_expires'] = time() + $codeTtl;
            AuthSecurity::resetOtpFailCount('user_login');

            $body = Mailer::otpMailBody(
                isset($user['username']) ? $user['username'] : $emailCanonical,
                $siteName,
                '登录',
                $code,
                $codeTtl
            );
            Mailer::send($emailCanonical, $siteName . ' 登录验证码', $body);

            vs_auth_json_mail($mailPurpose, array(
                'code' => 1,
                'msg'  => '验证码已发送，请查收邮箱（含垃圾箱）',
            ));
        } catch (Exception $e) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '发送失败，请稍后重试'));
        }
    }

    if ($action === 'login_code') {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $code = trim(isset($_POST['code']) ? $_POST['code'] : '');

        if ($email === '' || $code === '') {
            vs_auth_json(array('code' => 0, 'msg' => '请输入邮箱和验证码'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            vs_auth_json(array('code' => 0, 'msg' => '请输入有效的邮箱地址'));
        }
        $email = vs_normalize_email($email);

        $loginBlocked = AuthSecurity::checkLoginAllowed($email);
        if ($loginBlocked !== null) {
            vs_auth_json(array('code' => 0, 'msg' => $loginBlocked));
        }

        $savedEmail = isset($_SESSION['user_login_email']) ? vs_normalize_email($_SESSION['user_login_email']) : '';
        $savedCode = isset($_SESSION['user_login_code']) ? (string) $_SESSION['user_login_code'] : '';
        $expires = isset($_SESSION['user_login_code_expires']) ? (int) $_SESSION['user_login_code_expires'] : 0;
        $userId = isset($_SESSION['user_login_id']) ? (int) $_SESSION['user_login_id'] : 0;

        if ($savedEmail === '' || $savedCode === '' || $expires < time() || $userId <= 0) {
            vs_auth_json(array('code' => 0, 'msg' => '验证码已过期，请重新获取'));
        }
        if ($email !== $savedEmail || !hash_equals($savedCode, $code)) {
            AuthSecurity::recordLoginFailure($email);
            vs_auth_json(array('code' => 0, 'msg' => AuthSecurity::recordOtpFailure('user_login')));
        }

        if (!UserAuth::loginById($userId)) {
            AuthSecurity::recordLoginFailure($email);
            vs_auth_json(array('code' => 0, 'msg' => '登录失败，请稍后重试'));
        }

        AuthSecurity::clearOtpSession('user_login');
        $go = $loginRedirect !== '' ? $loginRedirect : ($base . '/user/index');
        vs_auth_json(array(
            'code' => 1,
            'msg'  => '登录成功',
            'url'  => $go,
        ));
    }

    if ($action === 'login') {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($username === '' || $password === '') {
            vs_auth_json(array('code' => 0, 'msg' => '请输入账号和密码'));
        }

        $loginBlocked = AuthSecurity::checkLoginAllowed($username);
        if ($loginBlocked !== null) {
            vs_auth_json(array('code' => 0, 'msg' => $loginBlocked));
        }

        $captchaErr = Captcha::requireValid(Captcha::SCENE_USER_LOGIN, $_POST);
        if ($captchaErr !== true) {
            vs_auth_json(array('code' => 0, 'msg' => $captchaErr));
        }

        if (UserAuth::login($username, $password)) {
            $go = $loginRedirect !== '' ? $loginRedirect : ($base . '/user/index');
            vs_auth_json(array(
                'code' => 1,
                'msg'  => '登录成功',
                'url'  => $go,
            ));
        }

        if (UserAuth::isBannedAccount($username, $password)) {
            vs_auth_json(array('code' => 0, 'msg' => '账号已被封禁，请联系管理员'));
        }

        AuthSecurity::recordLoginFailure($username);
        vs_auth_json(array('code' => 0, 'msg' => '用户名/邮箱或密码错误'));
    }

    vs_auth_json(array('code' => 0, 'msg' => '未知操作'), 400);
}

$expiredMsg = (isset($_GET['expired']) && $_GET['expired'] === '1') ? '登录已超时，请重新登录' : '';
$oauthError = isset($_GET['oauth_error']) ? trim((string) $_GET['oauth_error']) : '';
$oauthProviders = OAuthService::enabledProviders();

ThemeManager::renderAuthPage('login', '用户登录', array(
    'base'           => $base,
    'expiredMsg'     => $expiredMsg,
    'oauthError'     => $oauthError,
    'oauthProviders' => $oauthProviders,
    'loginRedirect'  => $loginRedirect,
    'registerOpen'   => RegisterPolicy::isOpen(),
    'mailEnabled'    => $mailEnabled,
));
