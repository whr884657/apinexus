<?php
/**
 * 文件：user/oauth/start.php
 * 作用：发起第三方 OAuth 授权（qq / gitee / agg）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$intent = isset($_GET['intent']) ? (string) $_GET['intent'] : 'login';
$itemId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$base = vs_base_url();
$accountUrl = $base . '/user/account.php';
$loginUrl = $base . '/user/login.php';

$rateMsg = AuthSecurity::checkOAuthStartAllowed();
if ($rateMsg !== null) {
    vs_redirect_flash(
        $intent === 'bind' ? $accountUrl : $loginUrl,
        'error',
        $rateMsg
    );
}
AuthSecurity::recordOAuthStart();

$context = array('intent' => 'login');
if ($intent === 'bind') {
    UserAuth::requireLogin();
    $msg = OAuthService::validateBindStart($provider, UserAuth::id(), $itemId);
    if ($msg !== null) {
        vs_redirect_flash($accountUrl, 'error', $msg);
    }
    $context = array(
        'intent'  => 'bind',
        'user_id' => UserAuth::id(),
    );
} else {
    UserAuth::redirectIfLoggedIn();
}

if (strtolower(trim((string) $provider)) === 'agg') {
    $context['item_id'] = $itemId;
}

$url = OAuthService::authorizeUrl($provider, $context);

if ($url === null) {
    vs_redirect_flash(
        $intent === 'bind' ? $accountUrl : $loginUrl,
        'error',
        '该登录方式未启用或配置不完整'
    );
}

header('Location: ' . $url);
exit;
