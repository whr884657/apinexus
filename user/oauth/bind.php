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
    vs_redirect($base . '/user/login.php?oauth_error=' . rawurlencode('绑定会话已过期，请重新发起第三方登录'));
}

$provider = $pending['provider'];
$providerLabel = $provider === 'qq' ? 'QQ' : 'Gitee';
$identity = $pending['identity'];
$displayName = '';
if ($provider === 'qq') {
    $displayName = isset($identity['nickname']) ? trim((string) $identity['nickname']) : '';
} else {
    $displayName = isset($identity['name']) ? trim((string) $identity['name']) : '';
    if ($displayName === '' && isset($identity['login'])) {
        $displayName = (string) $identity['login'];
    }
}

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
));
