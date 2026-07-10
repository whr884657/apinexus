<?php
/**
 * 文件：user/oauth/callback.php
 * 作用：OAuth 授权回调
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$code = isset($_GET['code']) ? $_GET['code'] : '';
$state = isset($_GET['state']) ? $_GET['state'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

$loginUrl = vs_base_url() . '/user/login.php';

if ($error !== '') {
    vs_redirect($loginUrl . '?oauth_error=' . rawurlencode('授权已取消或失败'));
}

$result = OAuthService::handleCallback($provider, $code, $state);

if ($result['status'] === 'login' || $result['status'] === 'bind' || $result['status'] === 'done') {
    vs_redirect(isset($result['redirect']) ? $result['redirect'] : $loginUrl);
}

$msg = isset($result['msg']) ? $result['msg'] : '第三方登录失败';
vs_redirect($loginUrl . '?oauth_error=' . rawurlencode($msg));
