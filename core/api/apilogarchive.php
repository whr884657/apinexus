<?php
/**
 * 文件：core/api/apilogarchive.php
 * 作用：调用日志清理 HTTP API（须系统密钥 + 后台「调用日志清理 API」开关）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 失败码：12xxx（SystemApiError）
 *
 * 鉴权（任选其一）：
 *   Authorization: Bearer <系统密钥>
 *   Header X-Api-Key / Api-Key / Apikey: <系统密钥>
 *   Bearer / Header / POST·JSON / Query key= 或 apikey=（三态传钥）
 *
 * 参数 action（必填，GET / POST / JSON 均可）：
 *   archive — 超过保留天数的日志写入本机冷库，再从在线库删除
 *   purge   — 超过保留天数的日志只从在线库删除，不写本机
 *   mirror  — 超过保留天数的日志写入本机冷库，在线库不删
 *
 * 用法示例：
 *   curl -fsS -H "Authorization: Bearer 密钥" "https://域名/core/api/apilogarchive?action=archive"
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('apilogarchive');

$input = SystemApiKey::mergeInput();

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '缺少 action（archive / purge / mirror）');
}
if ($action !== 'archive' && $action !== 'purge' && $action !== 'mirror') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '缺少或错误的 action（须为 archive / purge / mirror）');
}

@set_time_limit(600);
$result = ApiLogArchive::runAction($action);
$msg = isset($result['msg']) ? (string) $result['msg'] : '执行失败';
$extra = array(
    'mode'     => isset($result['mode']) ? $result['mode'] : $action,
    'archived' => isset($result['archived']) ? (int) $result['archived'] : 0,
    'deleted'  => isset($result['deleted']) ? (int) $result['deleted'] : 0,
    'days'     => isset($result['days']) ? $result['days'] : array(),
);
if (!empty($result['ok'])) {
    SystemApiKey::ok($msg, $extra);
}
if (strpos($msg, '未启用') !== false) {
    SystemApiKey::fail(SystemApiError::API_OFF, $msg, $extra);
}
if (strpos($msg, '缺少或错误') !== false) {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg, $extra);
}
SystemApiKey::fail(SystemApiError::BUSINESS, $msg, $extra);
