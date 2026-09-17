<?php
/**
 * 文件：user/recharge.php
 * 作用：用户充值中心（含卡密兑换）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$ready = OrderManager::tableReady() && PointsManager::hasPointsColumn();
$payReady = PayConfig::isReady();
$cardkeyReady = CardKeyManager::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'redeem') {
        if (!$ready || !$cardkeyReady) {
            AjaxResponse::error('积分系统未就绪');
        }
        $code = isset($_POST['code']) ? trim((string) $_POST['code']) : '';
        $result = CardKeyManager::redeem($userId, $code);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '兑换失败');
        }
        AjaxResponse::success($result['msg'], array(
            'points'  => isset($result['points']) ? (int) $result['points'] : 0,
            'balance' => isset($result['balance'])
                ? PayConfig::fmtPoints($result['balance'])
                : PayConfig::fmtPoints(PointsManager::balance($userId)),
        ));
    }

    if ($action === 'create') {
        if (!$ready) {
            AjaxResponse::error('积分系统未就绪');
        }
        if (!$payReady) {
            AjaxResponse::error('充值暂未开放');
        }
        $payType = isset($_POST['paytype']) ? (string) $_POST['paytype'] : '';
        $packageId = isset($_POST['package_id']) ? (string) $_POST['package_id'] : '';
        $money = isset($_POST['money']) ? (float) $_POST['money'] : 0;
        $result = PointsManager::createRecharge($userId, $payType, $packageId, $money);
        if (!$result['ok']) {
            AjaxResponse::error($result['msg']);
        }
        AjaxResponse::success($result['msg'], $result['data']);
    }

    if ($action === 'status') {
        $orderno = isset($_POST['orderno']) ? trim((string) $_POST['orderno']) : '';
        if (class_exists('PayPendingWatch')) {
            PayPendingWatch::expireIfDue($orderno);
        }
        $row = OrderManager::findByOrderNo($orderno);
        if (!$row || (int) $row['userid'] !== $userId) {
            AjaxResponse::error('订单不存在');
        }
        AjaxResponse::success('ok', array(
            'orderno' => (string) $row['orderno'],
            'status'  => (int) $row['status'],
            'label'   => OrderManager::statusLabel($row['status']),
            'points'  => PayConfig::fmtPoints($row['amount']),
            'balance' => PayConfig::fmtPoints(PointsManager::balance($userId)),
            'money'   => number_format((float) $row['money'], 2, '.', ''),
        ));
    }

    if ($action === 'cancel') {
        $orderno = isset($_POST['orderno']) ? trim((string) $_POST['orderno']) : '';
        $row = OrderManager::findByOrderNo($orderno);
        if (!$row || (int) $row['userid'] !== $userId) {
            AjaxResponse::error('订单不存在');
        }
        if ((int) $row['status'] !== OrderManager::STATUS_PENDING) {
            AjaxResponse::error('当前订单不可取消');
        }
        PointsManager::cancelPending($orderno);
        AjaxResponse::success('已取消');
    }

    AjaxResponse::error('无效操作', 400);
}

$balance = $ready ? PointsManager::balance($userId) : 0;
$cfg = PayConfig::all();
$packages = $cfg['packages'];
$methods = $cfg['methods'];
$rate = PayConfig::fmtPoints($cfg['rate']);

$payIcons = array(
    'alipay' => PayConfig::iconHtml('alipay'),
    'wxpay'  => PayConfig::iconHtml('wxpay'),
    'qqpay'  => PayConfig::iconHtml('qqpay'),
);

$scripts = array();
if ($ready) {
    $scripts[] = 'user-recharge.js';
}

vs_user_render_page(
    'recharge',
    '充值中心',
    'recharge',
    array(
        'ready'        => $ready,
        'payReady'     => $payReady,
        'cardkeyReady' => $cardkeyReady,
        'balance'      => $balance,
        'packages'     => $packages,
        'methods'      => $methods,
        'rate'         => $rate,
        'payIcons'     => $payIcons,
    ),
    '',
    $scripts
);
