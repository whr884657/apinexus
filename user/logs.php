<?php
/**
 * 文件：user/logs.php
 * 作用：用户中心 · 本人调用日志（无详情；字段白名单；强制 userid）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$detailEnabled = class_exists('ApiLogManager') && ApiLogManager::detailEnabled();
$tableReady = class_exists('ApiLogManager') && ApiLogManager::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    if (!$tableReady) {
        AjaxResponse::error('日志功能尚未就绪');
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'list') {
        AjaxResponse::error('无效操作', 400);
    }
    // 禁止客户端传入 userid；一律当前会话
    $okRaw = isset($_POST['ok']) ? $_POST['ok'] : '';
    $ok = null;
    if ($okRaw === '0' || $okRaw === '1' || $okRaw === 0 || $okRaw === 1) {
        $ok = (int) $okRaw;
    }
    $data = FrontendUser::myLogsPaged(array(
        'page'      => isset($_POST['page']) ? (int) $_POST['page'] : 1,
        'pagesize'  => isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20,
        'before_id' => isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0,
        'ok'        => $ok,
    ));
    // 二次校验：绝不回传非本人痕迹（listForUser 已强制；此处防御）
    if ((int) UserAuth::id() !== $userId) {
        AjaxResponse::error('会话已失效', 401);
    }
    AjaxResponse::success('ok', $data);
}

vs_user_render_page(
    'logs',
    '日志查询',
    'logs',
    array(
        'tableReady'     => $tableReady,
        'detailEnabled'  => $detailEnabled,
    ),
    '',
    ($tableReady && $detailEnabled) ? array('user-logs.js') : array()
);
