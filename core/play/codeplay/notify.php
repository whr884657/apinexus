<?php
/**
 * 文件：core/play/codeplay/notify.php
 * 作用：码支付异步回调（无需登录；先验签再履约）
 */

if (!defined('VS_ROOT')) {
    define('VS_ROOT', dirname(dirname(dirname(__DIR__))));
}
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

if (!InstallChecker::isInstalled()) {
    echo 'fail';
    exit;
}

$cfg = PayConfig::all();
$key = isset($cfg['key']) ? (string) $cfg['key'] : '';
if ($key === '' || !CodePayClient::verify($_REQUEST, $key)) {
    echo 'fail';
    exit;
}

$tradeStatus = '';
if (isset($_REQUEST['trade_status'])) {
    $tradeStatus = (string) $_REQUEST['trade_status'];
} elseif (isset($_REQUEST['status'])) {
    $tradeStatus = (string) $_REQUEST['status'];
}
if ($tradeStatus !== 'TRADE_SUCCESS' && $tradeStatus !== 'success') {
    echo 'fail';
    exit;
}

$orderno = isset($_REQUEST['out_trade_no']) ? trim((string) $_REQUEST['out_trade_no']) : '';
$tradeno = isset($_REQUEST['trade_no']) ? trim((string) $_REQUEST['trade_no']) : '';
$money = isset($_REQUEST['money']) ? (string) $_REQUEST['money'] : '';
if ($orderno === '') {
    echo 'fail';
    exit;
}

$ok = PointsManager::completeRecharge($orderno, $tradeno, $money);
echo $ok ? 'success' : 'fail';
