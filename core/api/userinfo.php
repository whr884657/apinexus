<?php
/**
 * 文件：core/api/userinfo.php
 * 作用：系统级用户信息查询 API（资料 / 积分 / 调用 / 签到 / 近7日排行 / 可选某日）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 失败码：12xxx（SystemApiError）
 *
 * 鉴权：系统密钥（Bearer / X-Api-Key / POST·JSON / Query key|apikey（三态））
 * 开关：sysapion.userinfo（闸关与鉴权失败统一 UNAUTH）
 *
 * action：
 *   get  必填用户标识；可选 date=Y-m-d（近7日内某一天调用摘要）
 * 不传 action 时默认 get
 *
 * 不含：密码、OAuth、系统密钥、上游机密。封禁用户仍可查（带 status）。
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('userinfo');

$input = SystemApiKey::mergeInput();

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    $action = 'get';
}

if ($action === 'get') {
    $result = SystemApiPublic::userInfo($input);
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '查询失败';
        $reason = isset($result['reason']) ? (string) $result['reason'] : '';
        if ($reason === 'not_registered') {
            SystemApiKey::fail(SystemApiError::USER_NOT_REGISTERED, $msg, array('reason' => 'not_registered'));
        }
        if (strpos($msg, '未就绪') !== false) {
            SystemApiKey::fail(SystemApiError::NOT_READY, $msg);
        }
        if (strpos($msg, 'date') !== false || strpos($msg, '日期') !== false) {
            SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg);
        }
        SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg);
    }
    SystemApiKey::ok('ok', array(
        'user' => isset($result['user']) ? $result['user'] : array(),
    ));
}

SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 action（仅 get）');
