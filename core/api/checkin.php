<?php
/**
 * 文件：core/api/checkin.php
 * 作用：系统级每日签到 API（代用户签到 / 查状态 / 读配置）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 *
 * 鉴权：系统密钥（Bearer / X-Api-Key / POST·JSON / Query key|apikey（三态））
 * 开关：sysapion.checkin（闸关与鉴权失败统一 UNAUTH；须另开 checkin_enabled 才能领积分）
 * 失败码：12xxx（SystemApiError）
 *
 * action：
 *   do / checkin  代指定用户签到（userid / username / email 三选一）
 *   status        查该用户今日是否已签 + 奖励区间
 *   config        只读功能配置（无需用户）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('checkin');

$input = SystemApiKey::mergeInput();

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '缺少 action（do / status / config）');
}
if ($action === 'checkin') {
    $action = 'do';
}

if ($action === 'config') {
    $cfg = CheckinManager::systemApiConfig();
    SystemApiKey::ok('ok', $cfg);
}

if ($action === 'status' || $action === 'do') {
    $resolved = CheckinManager::resolveUserForSystemApi($input);
    if (empty($resolved['ok'])) {
        $msg = isset($resolved['msg']) ? (string) $resolved['msg'] : '用户无效';
        $reason = isset($resolved['reason']) ? (string) $resolved['reason'] : '';
        if ($reason === 'not_registered') {
            SystemApiKey::fail(SystemApiError::USER_NOT_REGISTERED, $msg, array('reason' => 'not_registered'));
        }
        if ($reason === 'banned') {
            SystemApiKey::fail(SystemApiError::USER_BANNED, $msg, array('reason' => 'banned'));
        }
        SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg);
    }
    $userId = (int) $resolved['userid'];

    if ($action === 'status') {
        $st = CheckinManager::statusForSystemApi($userId);
        SystemApiKey::ok('ok', $st);
    }

    // do：流水备注记「每日签到奖励（API）」，与用户中心「每日签到奖励」区分
    $result = PointsManager::checkin($userId, 'api');
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '签到失败';
        if ($msg === '请先登录') {
            $msg = '用户无效';
        }
        if (strpos($msg, '已签到') !== false) {
            SystemApiKey::fail(SystemApiError::ALREADY_DONE, $msg);
        }
        if (strpos($msg, '未开启') !== false || strpos($msg, '未启用') !== false) {
            SystemApiKey::fail(SystemApiError::FEATURE_OFF, $msg);
        }
        SystemApiKey::fail(SystemApiError::BUSINESS, $msg);
    }

    $st = CheckinManager::statusForSystemApi($userId);
    SystemApiKey::ok(isset($result['msg']) ? (string) $result['msg'] : '签到成功', array(
        'userid'         => $userId,
        'amount'         => isset($result['amount']) ? (float) $result['amount'] : 0,
        'base'           => isset($result['base']) ? (float) $result['base'] : 0,
        'bonus'          => isset($result['bonus']) ? (int) $result['bonus'] : 0,
        'bonus_applied'  => !empty($result['bonus_applied']),
        'streak_days'    => isset($result['streak_days']) ? (int) $result['streak_days'] : 0,
        'streak_full'    => !empty($result['streak_full']),
        'checkin_days'   => isset($result['checkin_days']) && is_array($result['checkin_days']) ? $result['checkin_days'] : array(),
        'lastcheckin'    => isset($result['lastcheckin']) ? (string) $result['lastcheckin'] : '',
        'balance'        => isset($result['balance']) ? (float) $result['balance'] : null,
        'checked_today'  => true,
        'min'            => isset($st['min']) ? (int) $st['min'] : 0,
        'max'            => isset($st['max']) ? (int) $st['max'] : 0,
    ));
}

SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 action（仅 do / status / config）');
