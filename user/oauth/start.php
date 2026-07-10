<?php
/**
 * 文件：user/oauth/start.php
 * 作用：发起第三方 OAuth 授权
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$intent = isset($_GET['intent']) ? (string) $_GET['intent'] : 'login';
$base = vs_base_url();

$rateMsg = AuthSecurity::checkOAuthStartAllowed();
if ($rateMsg !== null) {
    $target = $intent === 'bind'
        ? $base . '/user/account.php?oauth_error=' . rawurlencode($rateMsg)
        : $base . '/user/login.php?oauth_error=' . rawurlencode($rateMsg);
    vs_redirect($target);
}
AuthSecurity::recordOAuthStart();

if ($intent === 'bind') {
    UserAuth::requireLogin();
    $msg = OAuthService::validateBindStart($provider, UserAuth::id());
    if ($msg !== null) {
        vs_redirect($base . '/user/account.php?oauth_error=' . rawurlencode($msg));
    }
    $url = OAuthService::authorizeUrl($provider, array(
        'intent'  => 'bind',
        'user_id' => UserAuth::id(),
    ));
} else {
    UserAuth::redirectIfLoggedIn();
    $url = OAuthService::authorizeUrl($provider, array('intent' => 'login'));
}

if ($url === null) {
    vs_redirect($base . '/user/login.php?oauth_error=' . rawurlencode('该登录方式未启用或配置不完整'));
}

header('Location: ' . $url);
exit;
