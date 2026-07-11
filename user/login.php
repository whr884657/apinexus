<?php
/**
 * 文件：user/login.php
 * 作用：用户登录页面
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    vs_auth_require_post();

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        vs_auth_json(array('code' => 0, 'msg' => '请输入账号和密码'));
    }

    $loginBlocked = AuthSecurity::checkLoginAllowed($username);
    if ($loginBlocked !== null) {
        vs_auth_json(array('code' => 0, 'msg' => $loginBlocked));
    }

    if (UserAuth::login($username, $password)) {
        vs_auth_json(array(
            'code' => 1,
            'msg'  => '登录成功',
            'url'  => $base . '/user/index',
        ));
    }

    if (UserAuth::isBannedAccount($username, $password)) {
        vs_auth_json(array('code' => 0, 'msg' => '账号已被封禁，请联系管理员'));
    }

    AuthSecurity::recordLoginFailure($username);
    vs_auth_json(array('code' => 0, 'msg' => '用户名/邮箱或密码错误'));
}

$expiredMsg = (isset($_GET['expired']) && $_GET['expired'] === '1') ? '登录已超时，请重新登录' : '';
$oauthError = isset($_GET['oauth_error']) ? trim((string) $_GET['oauth_error']) : '';
$oauthProviders = OAuthService::enabledProviders();

ThemeManager::renderAuthPage('login', '用户登录', array(
    'base'           => $base,
    'expiredMsg'     => $expiredMsg,
    'oauthError'     => $oauthError,
    'oauthProviders' => $oauthProviders,
));
