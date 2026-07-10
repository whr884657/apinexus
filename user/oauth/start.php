<?php
/**
 * 文件：user/oauth/start.php
 * 作用：发起第三方 OAuth 授权
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();
UserAuth::redirectIfLoggedIn();

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$url = OAuthService::authorizeUrl($provider);

if ($url === null) {
    vs_redirect(vs_base_url() . '/user/login.php?oauth_error=' . rawurlencode('该登录方式未启用或配置不完整'));
}

header('Location: ' . $url);
exit;
