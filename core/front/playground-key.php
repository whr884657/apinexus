<?php
/**
 * 文件：core/front/playground-key.php
 * 作用：前台在线测试按需拉取当前用户启用 KEY（POST + CSRF；禁止 SSR 明文进 HTML）
 *
 * 公网地址：{站点根}/core/front/playground-key.php（带 .php 直访）
 */

if (!defined('VS_ROOT')) {
    define('VS_ROOT', dirname(dirname(__DIR__)));
}
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    AjaxResponse::error('系统未安装', 503);
}

vs_require_secure_post();

if (!class_exists('UserAuth') || !UserAuth::check()) {
    AjaxResponse::json(array(
        'code'       => 0,
        'msg'        => '请先登录',
        'need_login' => 1,
        'csrf'       => class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '',
    ), 401);
}

$uid = (int) UserAuth::id();
$ip = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
if (!AuthSecurity::rateLimitAllow('front_playground_key_uid:' . $uid, 30, 60, true)) {
    AjaxResponse::error('请求过于频繁，请稍后再试', 429);
}
if (!AuthSecurity::rateLimitAllow('front_playground_key_ip:' . $ip, 60, 60, true)) {
    AjaxResponse::error('请求过于频繁，请稍后再试', 429);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
if ($action !== '' && $action !== 'get') {
    AjaxResponse::error('无效操作', 400);
}

$apiKey = '';
$apiKeyCount = 0;
if (class_exists('ApiKeyManager') && ApiKeyManager::tableReady()) {
    foreach (ApiKeyManager::listByUser($uid) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $enabled = isset($row['status'])
            ? ((int) $row['status'] === ApiKeyManager::STATUS_ENABLED)
            : true;
        if (!$enabled) {
            continue;
        }
        $apiKeyCount++;
        if ($apiKey === '' && !empty($row['secret'])) {
            $apiKey = (string) $row['secret'];
        }
    }
}

AjaxResponse::success('ok', array(
    'apiKey'      => $apiKey,
    'apiKeyCount' => $apiKeyCount,
));
