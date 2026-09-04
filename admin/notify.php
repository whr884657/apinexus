<?php
/**
 * 文件：admin/notify.php
 * 作用：管理后台顶栏待办通知 AJAX
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('请使用 POST', 405);
}

vs_require_secure_post();

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

if ($action === 'inbox') {
    // 须在 session_write_close 前执行，以便 Updater 写入检测缓存
    $pack = AdminNotify::inbox();
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    AjaxResponse::success('ok', array('data' => $pack));
}

if (function_exists('session_write_close')) {
    @session_write_close();
}
AjaxResponse::error('无效操作', 400);
