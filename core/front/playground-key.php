<?php
/**
 * 文件：core/front/playground-key.php
 * 作用：前台在线测试按需拉取当前用户启用 KEY（POST + CSRF；禁止 SSR 明文进 HTML）
 *       可选 apiid：优先返回对该接口有范围权限的密钥；全无则 scopeBlocked=1
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

$apiId = isset($_POST['apiid']) ? (int) $_POST['apiid'] : 0;
$apiRow = null;
if ($apiId > 0 && class_exists('ApiManager') && ApiManager::tableReady()) {
    $apiRow = ApiManager::findById($apiId);
    if (!is_array($apiRow)) {
        $apiRow = null;
    }
}

$apiKey = '';
$apiKeyCount = 0;
$scopeBlocked = 0;
$inScopeKey = '';
$anyEnabledKey = '';

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
        if (ApiKeyManager::isExpired($row)) {
            continue;
        }
        $apiKeyCount++;
        $secret = !empty($row['secret']) ? (string) $row['secret'] : '';
        if ($secret === '') {
            continue;
        }
        if ($anyEnabledKey === '') {
            $anyEnabledKey = $secret;
        }
        if ($apiRow === null) {
            if ($apiKey === '') {
                $apiKey = $secret;
            }
            continue;
        }
        if (ApiKeyManager::allowsApi($row, $apiRow)) {
            if ($inScopeKey === '') {
                $inScopeKey = $secret;
            }
        }
    }
}

if ($apiRow !== null) {
    if ($inScopeKey !== '') {
        $apiKey = $inScopeKey;
    } elseif ($apiKeyCount > 0) {
        $apiKey = '';
        $scopeBlocked = 1;
    } else {
        $apiKey = '';
    }
} elseif ($apiKey === '' && $anyEnabledKey !== '') {
    $apiKey = $anyEnabledKey;
}

$payload = array(
    'apiKey'      => $apiKey,
    'apiKeyCount' => $apiKeyCount,
);
if ($scopeBlocked === 1) {
    $payload['scopeBlocked'] = 1;
    $payload['scopeHint'] = '当前启用令牌均无权调用本接口，请到令牌页调整接口范围后再试';
}

AjaxResponse::success('ok', $payload);
