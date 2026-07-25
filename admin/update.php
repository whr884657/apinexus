<?php
/**
 * 文件：admin/update.php
 * 作用：ApiNexus 在线更新 API（版本检测 / 执行更新）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

if (in_array($action, array('check', 'history', 'apply', 'apply_step', 'migrate_schema', 'dismiss'), true)) {
    if (function_exists('ini_set')) {
        @ini_set('display_errors', '0');
    }
}

/**
 * 更新接口限流（按管理员）
 *
 * @param string $action
 * @param int    $max
 * @param int    $window
 * @return void
 */
function vs_update_rate_limit($action, $max, $window)
{
    $admin = Auth::user();
    $aid = ($admin && isset($admin['id'])) ? (int) $admin['id'] : 0;
    $bucket = 'admin:update:' . $action . ':' . $aid;
    if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, $window, $max, true)) {
        AjaxResponse::error('操作过于频繁，请稍后再试', 429);
    }
}

// 只读探测：须登录（init 已保证）+ 同源 + 限流，避免被跨站刷云端检测
if ($action === 'check' || $action === 'history') {
    if (!AuthSecurity::validateSameOrigin()) {
        AjaxResponse::error('请求来源无效', 403);
    }
    vs_update_rate_limit($action, 12, 60);
    if ($action === 'check') {
        $result = Updater::checkForUpdate();
        $dismissed = isset($_SESSION['vs_update_dismiss']) ? (string) $_SESSION['vs_update_dismiss'] : '';
        $showModal = !empty($result['update_available'])
            && $dismissed !== (string) $result['remote_version'];
        AjaxResponse::success('ok', array_merge($result, array(
            'show_modal' => $showModal,
        )));
    }
    AjaxResponse::success('ok', array(
        'versions' => UpdateLog::payloadForApi(),
        'local_version' => VS_VERSION,
        'source' => UpdateLog::getSource(),
    ));
}

vs_require_secure_post();
vs_update_rate_limit($action !== '' ? $action : 'post', 20, 60);

if ($action === 'dismiss') {
    $version = isset($_POST['version']) ? trim((string) $_POST['version']) : '';
    $_SESSION['vs_update_dismiss'] = $version;
    AjaxResponse::success('已稍后提醒');
}

if ($action === 'apply') {
    @set_time_limit(600);
    @ini_set('memory_limit', '256M');
    vs_update_rate_limit('apply', 3, 600);

    try {
        $result = Updater::applyUpdate();
    } catch (Throwable $e) {
        AjaxResponse::error('更新异常，请稍后重试或查看服务器日志');
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
    vs_update_rate_limit('apply_step', 30, 600);

    $step = isset($_POST['step']) ? trim((string) $_POST['step']) : '';

    try {
        $result = Updater::applyUpdateStep($step);
    } catch (Throwable $e) {
        AjaxResponse::error('更新异常，请稍后重试或查看服务器日志');
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

if ($action === 'migrate_schema') {
    @set_time_limit(300);
    vs_update_rate_limit('migrate_schema', 5, 600);
    try {
        $result = Updater::runSchemaMigrateNow();
    } catch (Throwable $e) {
        AjaxResponse::error('结构更新异常，请稍后重试或查看服务器日志');
    }
    if (empty($result['ok'])) {
        AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '结构更新失败');
    }
    AjaxResponse::success($result['msg'], array(
        'applied' => isset($result['applied']) ? $result['applied'] : array(),
    ));
}

AjaxResponse::error('未知操作', 400);
