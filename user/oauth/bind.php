<?php
/**
 * 文件：user/oauth/bind.php
 * 作用：将第三方账号绑定到已有用户（须先注册）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';
require_once VS_ROOT . '/admin/includes/auth_layout.php';

InstallChecker::requireInstalled();
UserAuth::redirectIfLoggedIn();

$base = vs_base_url();
$pending = OAuthService::getBindPending();

if ($pending === null) {
    vs_redirect_flash($base . '/user/login.php', 'error', '绑定会话已过期，请重新发起第三方登录');
}

$provider = $pending['provider'];
$identity = $pending['identity'];
$providerLabel = OAuthService::providerDisplayLabel($provider, is_array($identity) ? $identity : array());
$displayName = OAuthService::identityDisplayName($provider, is_array($identity) ? $identity : array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_auth_require_post();

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $result = OAuthService::bindPendingToAccount($username, $password);
    if ($result['ok']) {
        vs_auth_json(array(
            'code' => 1,
            'msg'  => $result['msg'],
            'url'  => $base . '/user/index.php',
        ));
    }

    vs_auth_json(array('code' => 0, 'msg' => $result['msg']));
}


ThemeManager::renderAuthPage('bind', '绑定' . $providerLabel, array(
    'base' => $base,
    'provider' => $provider,
    'providerLabel' => $providerLabel,
    'displayName' => $displayName,
    'registerOpen' => RegisterPolicy::isOpen(),
));
