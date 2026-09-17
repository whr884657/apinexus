<?php
/**
 * 文件：core/api/cardkey.php
 * 作用：卡密对接 API（生成 / 库存 / 出库 / 作废 / 查询）；鉴权用系统密钥
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 *
 * 鉴权（任选其一，与归档任务相同）：
 *   Authorization: Bearer <系统密钥>
 *   Header X-Api-Key / Api-Key / Apikey: <系统密钥>
 *   Query/POST/JSON key= 或 apikey=
 *
 * 须后台开启 cardkey_api_enabled；未开启即使密钥正确也拒绝。
 *
 * 动作（action / GET·POST·JSON body）：
 *   generate  count=1～100  points=1～1000000     实时生成新卡密
 *   stock     [points=积分]                       查未使用库存（不含已作废/已使用/已发放）
 *   take      count=1～100  [points=积分]         从库存随机出库（标为已发放，防一卡两卖）
 *   void      codes=… 或 code=…                   作废未使用/已发放（不可作废已兑）
 *   query     code=单张卡密
 *
 * 响应：{ code:1|0, msg, … }；失败不暴露 SQL/堆栈
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::jsonExit(503, 0, '系统未安装');
}

SystemApiKey::requireAuthorized();

if (trim((string) Config::get('cardkey_api_enabled', '0')) !== '1') {
    SystemApiKey::jsonExit(403, 0, '卡密对接 API 未启用');
}

$input = array_merge($_GET, $_POST);
$raw = SystemApiKey::requestBody();
if ($raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if ($action === '') {
    SystemApiKey::jsonExit(400, 0, '缺少 action（generate / stock / take / void / query）');
}

if (!CardKeyManager::tableReady()) {
    SystemApiKey::jsonExit(503, 0, '卡密表未就绪，请先完成系统升级');
}

if ($action === 'generate') {
    SystemApiKey::requireRateLimit('cardkey_gen', 60, 30);
    $count = isset($input['count']) ? (int) $input['count'] : 0;
    $points = isset($input['points']) ? (int) $input['points'] : 0;
    $result = CardKeyManager::generate($count, $points, 0);
    if (empty($result['ok'])) {
        SystemApiKey::jsonExit(400, 0, isset($result['msg']) ? (string) $result['msg'] : '生成失败');
    }
    $codes = array();
    if (!empty($result['list']) && is_array($result['list'])) {
        foreach ($result['list'] as $row) {
            if (isset($row['code'])) {
                $codes[] = (string) $row['code'];
            }
        }
    }
    SystemApiKey::jsonExit(200, 1, isset($result['msg']) ? (string) $result['msg'] : 'ok', array(
        'count'  => isset($result['count']) ? (int) $result['count'] : count($codes),
        'points' => $points,
        'codes'  => $codes,
        'list'   => isset($result['list']) ? $result['list'] : array(),
    ));
}

if ($action === 'stock') {
    SystemApiKey::requireRateLimit('cardkey_stock', 60, 120);
    $points = isset($input['points']) ? (int) $input['points'] : 0;
    $result = CardKeyManager::stock($points);
    if (empty($result['ok'])) {
        SystemApiKey::jsonExit(400, 0, isset($result['msg']) ? (string) $result['msg'] : '查询失败');
    }
    SystemApiKey::jsonExit(200, 1, 'ok', array(
        'unused'        => isset($result['unused']) ? (int) $result['unused'] : 0,
        'unused_points' => isset($result['unused_points']) ? (int) $result['unused_points'] : 0,
        'by_points'     => isset($result['by_points']) ? $result['by_points'] : array(),
    ));
}

if ($action === 'take') {
    SystemApiKey::requireRateLimit('cardkey_take', 60, 30);
    $count = isset($input['count']) ? (int) $input['count'] : 0;
    $points = isset($input['points']) ? (int) $input['points'] : 0;
    $result = CardKeyManager::takeFromStock($count, $points);
    if (empty($result['ok'])) {
        SystemApiKey::jsonExit(400, 0, isset($result['msg']) ? (string) $result['msg'] : '出库失败');
    }
    $codes = array();
    if (!empty($result['list']) && is_array($result['list'])) {
        foreach ($result['list'] as $row) {
            if (isset($row['code'])) {
                $codes[] = (string) $row['code'];
            }
        }
    }
    SystemApiKey::jsonExit(200, 1, isset($result['msg']) ? (string) $result['msg'] : 'ok', array(
        'count'  => isset($result['count']) ? (int) $result['count'] : count($codes),
        'points' => $points,
        'codes'  => $codes,
        'list'   => isset($result['list']) ? $result['list'] : array(),
    ));
}

if ($action === 'void') {
    SystemApiKey::requireRateLimit('cardkey_void', 60, 60);
    $codes = array();
    if (isset($input['codes']) && is_array($input['codes'])) {
        $codes = $input['codes'];
    } elseif (isset($input['codes'])) {
        $rawCodes = trim((string) $input['codes']);
        if ($rawCodes !== '') {
            $codes = preg_split('/[\s,;]+/', $rawCodes);
        }
    } elseif (isset($input['code'])) {
        $codes = array($input['code']);
    }
    $result = CardKeyManager::voidUnusedByCodes($codes);
    if (empty($result['ok'])) {
        SystemApiKey::jsonExit(400, 0, isset($result['msg']) ? (string) $result['msg'] : '作废失败');
    }
    SystemApiKey::jsonExit(200, 1, isset($result['msg']) ? (string) $result['msg'] : 'ok', array(
        'count'   => isset($result['count']) ? (int) $result['count'] : 0,
        'voided'  => isset($result['voided']) ? $result['voided'] : array(),
        'skipped' => isset($result['skipped']) ? $result['skipped'] : array(),
    ));
}

if ($action === 'query') {
    SystemApiKey::requireRateLimit('cardkey_query', 60, 60);
    $code = isset($input['code']) ? trim((string) $input['code']) : '';
    $result = CardKeyManager::findByCode($code);
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '查询失败';
        // 格式非法与不存在统一 404 + 模糊文案（SEC-103）
        $http = ($msg === '卡密表未就绪' || $msg === '查询失败') ? 400 : 404;
        SystemApiKey::jsonExit($http, 0, $msg);
    }
    SystemApiKey::jsonExit(200, 1, 'ok', array(
        'exists' => true,
        'card'   => isset($result['card']) ? $result['card'] : array(),
    ));
}

SystemApiKey::jsonExit(400, 0, '不支持的 action');
