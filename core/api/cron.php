<?php
/**
 * 文件：core/api/cron.php
 * 作用：系统级计划任务入口。后续任务都走本文件，用 job 区分。
 *
 * 鉴权：系统密钥（Bearer / X-Api-Key / POST·JSON / Query key|apikey）
 * 开关：sysapion.cron
 * 失败码：12xxx
 *
 * action=run
 * job=apistatus  发送接口上架、下架、维护订阅邮件
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('cron');

$input = SystemApiKey::mergeInput();
$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '缺少 action（run）');
}
if ($action !== 'run') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 action（仅 run）');
}

$job = isset($input['job']) ? strtolower(trim((string) $input['job'])) : '';
if ($job === '') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '缺少 job');
}

if ($job !== 'apistatus') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 job');
}

$result = ApiManager::flushStatusSubscribe();
if (empty($result['ok'])) {
    $msg = isset($result['msg']) ? (string) $result['msg'] : '计划任务失败';
    SystemApiKey::fail(SystemApiError::BUSINESS, $msg, array(
        'job'     => $job,
        'events'  => isset($result['events']) ? (int) $result['events'] : 0,
        'mailed'  => isset($result['mailed']) ? (int) $result['mailed'] : 0,
        'skipped' => isset($result['skipped']) ? (string) $result['skipped'] : '',
    ));
}

SystemApiKey::ok('ok', array(
    'job'     => $job,
    'events'  => isset($result['events']) ? (int) $result['events'] : 0,
    'mailed'  => isset($result['mailed']) ? (int) $result['mailed'] : 0,
    'skipped' => isset($result['skipped']) ? (string) $result['skipped'] : '',
));
