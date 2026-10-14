<?php
/**
 * 文件：core/api/sitestats.php
 * 作用：系统级全站概况 API（累计调用 / 今日调用 / 累计积分消耗 / 注册用户数）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 失败码：12xxx（SystemApiError）
 *
 * 鉴权：系统密钥（Bearer / X-Api-Key / POST·JSON / Query key|apikey（三态））
 * 开关：sysapion.sitestats（闸关与鉴权失败统一 UNAUTH）
 *
 * action：
 *   get  查询概况（不传默认 get）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('sitestats');

$input = SystemApiKey::mergeInput();

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    $action = 'get';
}

if ($action === 'get') {
    $result = SystemApiPublic::siteStats();
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '查询失败';
        SystemApiKey::fail(SystemApiError::BUSINESS, $msg);
    }
    SystemApiKey::ok('ok', array(
        'stats' => isset($result['stats']) ? $result['stats'] : array(),
    ));
}

SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 action（仅 get）');
