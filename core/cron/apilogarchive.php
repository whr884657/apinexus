<?php
/**
 * 文件：core/cron/apilogarchive.php
 * 作用：调用日志冷热归档计划任务入口（须携带系统设置中生成的密钥）
 *
 * 用法（crontab 建议每日 02:30）：
 *   curl -fsS "https://域名/core/cron/apilogarchive.php?key=密钥"
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!InstallChecker::isInstalled()) {
    http_response_code(503);
    echo json_encode(array('code' => 0, 'msg' => '系统未安装'), JSON_UNESCAPED_UNICODE);
    exit;
}

$key = '';
if (isset($_GET['key'])) {
    $key = trim((string) $_GET['key']);
} elseif (isset($_POST['key'])) {
    $key = trim((string) $_POST['key']);
}

if (!ApiLogArchive::validateCronKey($key)) {
    http_response_code(403);
    echo json_encode(array('code' => 0, 'msg' => '无权执行'), JSON_UNESCAPED_UNICODE);
    exit;
}

@set_time_limit(300);
$result = ApiLogArchive::runOnce();
$code = !empty($result['ok']) ? 1 : 0;
if ($code !== 1) {
    http_response_code(500);
}
echo json_encode(array(
    'code'     => $code,
    'msg'      => isset($result['msg']) ? $result['msg'] : '',
    'archived' => isset($result['archived']) ? (int) $result['archived'] : 0,
    'deleted'  => isset($result['deleted']) ? (int) $result['deleted'] : 0,
    'days'     => isset($result['days']) ? $result['days'] : array(),
), JSON_UNESCAPED_UNICODE);
