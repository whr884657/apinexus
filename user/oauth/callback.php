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
// 聚合网关常不回传 state：用 redirect_uri 上的 ost 兜底（短 token）
if ($state === '' && isset($_GET['ost'])) {
    $state = (string) $_GET['ost'];
}
$error = isset($_GET['error']) ? $_GET['error'] : '';
$aggType = isset($_GET['type']) ? trim((string) $_GET['type']) : '';

$base = vs_base_url();
$loginUrl = $base . '/user/login.php';
$accountUrl = $base . '/user/account.php';

/**
 * @param string $msg
 * @param string $fallbackUrl
 * @return never
 */
function vs_oauth_redirect_error($msg, $fallbackUrl)
{
    vs_redirect_flash($fallbackUrl, 'error', $msg);
}

$peek = OAuthState::peek($provider, $state);
$errorFallback = ($peek !== false && isset($peek['intent']) && $peek['intent'] === 'bind')
    ? $accountUrl
    : $loginUrl;

if ($error !== '') {
    vs_oauth_redirect_error('授权已取消或失败', $errorFallback);
}

$result = OAuthService::handleCallback($provider, $code, $state, $aggType);

if ($result['status'] === 'login' || $result['status'] === 'bind' || $result['status'] === 'done') {
    vs_redirect(isset($result['redirect']) ? $result['redirect'] : $loginUrl);
}

$msg = isset($result['msg']) ? $result['msg'] : '第三方登录失败';
$redirect = isset($result['redirect']) ? $result['redirect'] : $errorFallback;
// 去掉历史拼进 URL 的 oauth_*，统一走 Flash
$redirect = preg_replace('/([?&])oauth_(?:error|success)=[^&]*/', '$1', $redirect);
$redirect = rtrim(str_replace(array('?&', '&&'), array('?', '&'), $redirect), '?&');
if ($redirect === '' || $redirect === null) {
    $redirect = $errorFallback;
}
vs_redirect_flash($redirect, 'error', $msg);
