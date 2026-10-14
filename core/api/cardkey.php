<?php
/**
 * 文件：core/api/cardkey.php
 * 作用：卡密对接 API（生成 / 库存 / 出库 / 作废 / 查询）；鉴权用系统密钥
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 失败码：12xxx（SystemApiError）
 *
 * 鉴权（任选其一）：
 *   Authorization: Bearer <系统密钥>
 *   Header X-Api-Key / Api-Key / Apikey: <系统密钥>
 *   Bearer / Header / POST·JSON / Query key= 或 apikey=（三态传钥）
 *
 * 须后台开启 sysapion.cardkey；闸关与鉴权失败统一 UNAUTH。
 *
 * 动作（action / GET·POST·JSON body）：
 *   generate  count=1～100  points=1～1000000     实时生成新卡密
 *   stock     [points=积分]                       查未使用库存（不含已作废/已使用/已发放）
 *   take      count=1～100  [points=积分]         从库存随机出库（标为已发放，防一卡两卖）
 *   void      codes=… 或 code=…                   作废未使用/已发放（不可作废已兑）
 *   query     code=单张卡密
 *
 * 响应：{ code:1|0, msg, errcode?, … }；失败不暴露 SQL/堆栈
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('cardkey');

$input = SystemApiKey::mergeInput();

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    SystemApiKey::fail(SystemApiError::BAD_PARAM, '缺少 action（generate / stock / take / void / query）');
}

if (!CardKeyManager::tableReady()) {
    SystemApiKey::fail(SystemApiError::NOT_READY, '卡密表未就绪，请先完成系统升级');
}

if ($action === 'generate') {
    $count = isset($input['count']) ? (int) $input['count'] : 0;
    $points = isset($input['points']) ? (int) $input['points'] : 0;
    $result = CardKeyManager::generate($count, $points, 0);
    if (empty($result['ok'])) {
        SystemApiKey::fail(SystemApiError::BUSINESS, isset($result['msg']) ? (string) $result['msg'] : '生成失败');
    }
    $codes = array();
    if (!empty($result['list']) && is_array($result['list'])) {
        foreach ($result['list'] as $row) {
            if (isset($row['code'])) {
                $codes[] = (string) $row['code'];
            }
        }
    }
    SystemApiKey::ok(isset($result['msg']) ? (string) $result['msg'] : 'ok', array(
        'count'  => isset($result['count']) ? (int) $result['count'] : count($codes),
        'points' => $points,
        'codes'  => $codes,
        'list'   => isset($result['list']) ? $result['list'] : array(),
    ));
}

if ($action === 'stock') {
    $points = isset($input['points']) ? (int) $input['points'] : 0;
    $result = CardKeyManager::stock($points);
    if (empty($result['ok'])) {
        SystemApiKey::fail(SystemApiError::BUSINESS, isset($result['msg']) ? (string) $result['msg'] : '查询失败');
    }
    SystemApiKey::ok('ok', array(
        'unused'        => isset($result['unused']) ? (int) $result['unused'] : 0,
        'unused_points' => isset($result['unused_points']) ? (int) $result['unused_points'] : 0,
        'by_points'     => isset($result['by_points']) ? $result['by_points'] : array(),
    ));
}

if ($action === 'take') {
    $count = isset($input['count']) ? (int) $input['count'] : 0;
    $points = isset($input['points']) ? (int) $input['points'] : 0;
    $result = CardKeyManager::takeFromStock($count, $points);
    if (empty($result['ok'])) {
        SystemApiKey::fail(SystemApiError::BUSINESS, isset($result['msg']) ? (string) $result['msg'] : '出库失败');
    }
    $codes = array();
    if (!empty($result['list']) && is_array($result['list'])) {
        foreach ($result['list'] as $row) {
            if (isset($row['code'])) {
                $codes[] = (string) $row['code'];
            }
        }
    }
    SystemApiKey::ok(isset($result['msg']) ? (string) $result['msg'] : 'ok', array(
        'count'  => isset($result['count']) ? (int) $result['count'] : count($codes),
        'points' => $points,
        'codes'  => $codes,
        'list'   => isset($result['list']) ? $result['list'] : array(),
    ));
}

if ($action === 'void') {
    $codes = array();
    if (isset($input['codes']) && is_array($input['codes'])) {
        if (count($input['codes']) > 200) {
            SystemApiKey::fail(SystemApiError::BAD_PARAM, '单次最多作废 200 张');
        }
        $codes = $input['codes'];
    } elseif (isset($input['codes'])) {
        $rawCodes = trim((string) $input['codes']);
        if (strlen($rawCodes) > 8192) {
            SystemApiKey::fail(SystemApiError::BAD_PARAM, 'codes 过长');
        }
        if ($rawCodes !== '') {
            $codes = preg_split('/[\s,;]+/', $rawCodes);
            if (is_array($codes) && count($codes) > 200) {
                SystemApiKey::fail(SystemApiError::BAD_PARAM, '单次最多作废 200 张');
            }
        }
    } elseif (isset($input['code'])) {
        $codes = array($input['code']);
    }
    $result = CardKeyManager::voidUnusedByCodes($codes);
    if (empty($result['ok'])) {
        SystemApiKey::fail(SystemApiError::BUSINESS, isset($result['msg']) ? (string) $result['msg'] : '作废失败');
    }
    SystemApiKey::ok(isset($result['msg']) ? (string) $result['msg'] : 'ok', array(
        'count'   => isset($result['count']) ? (int) $result['count'] : 0,
        'voided'  => isset($result['voided']) ? $result['voided'] : array(),
        'skipped' => isset($result['skipped']) ? $result['skipped'] : array(),
    ));
}

if ($action === 'query') {
    $code = isset($input['code']) ? trim((string) $input['code']) : '';
    $result = CardKeyManager::findByCode($code);
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '查询失败';
        if ($msg === '卡密表未就绪') {
            SystemApiKey::fail(SystemApiError::NOT_READY, $msg);
        }
        if ($msg === '查询失败') {
            SystemApiKey::fail(SystemApiError::BUSINESS, $msg);
        }
        SystemApiKey::fail(SystemApiError::NOT_FOUND, $msg);
    }
    SystemApiKey::ok('ok', array(
        'exists' => true,
        'card'   => isset($result['card']) ? $result['card'] : array(),
    ));
}

SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 action');
