<?php
/**
 * 文件：core/api/apilogarchive.php
 * 作用：调用日志清理 HTTP API（冷热归档 或 过期直接删除；须系统密钥 + 后台开关）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 *
 * 鉴权（任选其一）：
 *   Query/POST  ?key= 或 ?apikey=
 *   Header      Authorization: Bearer <密钥>
 *               X-Api-Key / Api-Key / Apikey: <密钥>
 *
 * 后台未启用「冷热归档」或「过期删除」时，即使密钥正确也返回错误（防密钥泄露误跑）。
 *
 * 用法（crontab 建议每日 02:30）：
 *   curl -fsS "https://域名/core/api/apilogarchive.php?key=密钥"
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!InstallChecker::isInstalled()) {
    http_response_code(503);
    echo json_encode(array('code' => 0, 'msg' => '系统未安装'), JSON_UNESCAPED_UNICODE);
    exit;
}

SystemApiKey::requireAuthorized();
SystemApiKey::requireRateLimit('archive', 60, 6);

@set_time_limit(600);
$result = ApiLogArchive::runScheduled();
$code = !empty($result['ok']) ? 1 : 0;
if ($code !== 1) {
    $msg = isset($result['msg']) ? (string) $result['msg'] : '执行失败';
    $http = (strpos($msg, '未启用') !== false) ? 403 : 500;
    http_response_code($http);
}
echo json_encode(array(
    'code'     => $code,
    'msg'      => isset($result['msg']) ? $result['msg'] : '',
    'mode'     => isset($result['mode']) ? $result['mode'] : '',
    'archived' => isset($result['archived']) ? (int) $result['archived'] : 0,
    'deleted'  => isset($result['deleted']) ? (int) $result['deleted'] : 0,
    'days'     => isset($result['days']) ? $result['days'] : array(),
), JSON_UNESCAPED_UNICODE);
