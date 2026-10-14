<?php
/**
 * 文件：core/api/apirank.php
 * 作用：系统级接口调用排行榜 API（today / 7d / 30d 聚合日表 topjson；total 读接口累计 calls）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 失败码：12xxx（SystemApiError）
 *
 * 鉴权：系统密钥（Bearer / X-Api-Key / POST·JSON / Query key|apikey（三态））
 * 开关：sysapion.apirank（闸关与鉴权失败统一 UNAUTH）
 *
 * 参数：
 *   period  必填：today | 7d | 30d | total（也认 1d/week/month、all/alltime/sum）
 *   limit   可选：1～50，默认 20
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('apirank');

$input = SystemApiKey::mergeInput();

$period = isset($input['period']) ? (string) $input['period'] : '';
if ($period === '' && isset($input['action'])) {
    // 兼容误把 period 写成 action
    $period = (string) $input['action'];
}
$limit = isset($input['limit']) ? (int) $input['limit'] : 20;

$result = SystemApiPublic::rank($period, $limit);
if (empty($result['ok'])) {
    $msg = isset($result['msg']) ? (string) $result['msg'] : '查询失败';
    if (strpos($msg, '未就绪') !== false) {
        SystemApiKey::fail(SystemApiError::NOT_READY, $msg);
    }
    SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg);
}

SystemApiKey::ok('ok', array(
    'period' => isset($result['period']) ? (string) $result['period'] : $period,
    'days'   => isset($result['days']) ? (int) $result['days'] : 0,
    'limit'  => isset($result['limit']) ? (int) $result['limit'] : $limit,
    'list'   => isset($result['list']) ? $result['list'] : array(),
));
