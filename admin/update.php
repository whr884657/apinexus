<?php
/**
 * 文件：admin/update.php
 * 作用：ApiNexus 在线更新 API（版本检测 / 执行更新）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if (in_array($action, array('check', 'history', 'apply', 'apply_step'), true)) {
    if (function_exists('ini_set')) {
        @ini_set('display_errors', '0');
    }
}

if ($action === 'check') {
    $result = Updater::checkForUpdate();
    $dismissed = isset($_SESSION['vs_update_dismiss']) ? (string) $_SESSION['vs_update_dismiss'] : '';
    $showModal = !empty($result['update_available'])
        && $dismissed !== (string) $result['remote_version'];

    AjaxResponse::success('ok', array_merge($result, array(
        'show_modal' => $showModal,
    )));
}

if ($action === 'history') {
    AjaxResponse::success('ok', array(
        'versions' => UpdateLog::payloadForApi(),
        'local_version' => VS_VERSION,
        'source' => UpdateLog::getSource(),
    ));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('无效请求', 405);
}

$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!AuthSecurity::validateCsrf($token)) {
    AjaxResponse::error('安全校验失败，请刷新页面后重试', 403);
}

if ($action === 'dismiss') {
    $version = isset($_POST['version']) ? trim($_POST['version']) : '';
    $_SESSION['vs_update_dismiss'] = $version;
    AjaxResponse::success('已稍后提醒');
}

if ($action === 'apply') {
    @set_time_limit(600);
    @ini_set('memory_limit', '256M');

    try {
        $result = Updater::applyUpdate();
    } catch (Throwable $e) {
        AjaxResponse::error('更新异常：' . $e->getMessage());
    }

    if (empty($result['ok'])) {
        AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '更新失败');
    }

    unset($_SESSION['vs_update_dismiss']);

    AjaxResponse::success($result['msg'], array(
        'version' => isset($result['version']) ? $result['version'] : '',
    ));
}

if ($action === 'apply_step') {
    @set_time_limit(600);
    @ini_set('memory_limit', '256M');

    $step = isset($_POST['step']) ? trim($_POST['step']) : '';

    try {
        $result = Updater::applyUpdateStep($step);
    } catch (Throwable $e) {
        AjaxResponse::error('更新异常：' . $e->getMessage());
    }

    if (empty($result['ok'])) {
        AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '更新失败');
    }

    if ($step === 'migrate') {
        unset($_SESSION['vs_update_dismiss']);
    }

    AjaxResponse::success($result['msg'], array(
        'step'    => isset($result['step']) ? $result['step'] : $step,
        'version' => isset($result['version']) ? $result['version'] : '',
    ));
}

AjaxResponse::error('未知操作', 400);
