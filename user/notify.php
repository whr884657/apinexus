<?php
/**
 * 文件：user/notify.php
 * 作用：用户中心 · 站内公告只读入口（inbox / detail；无已读表；铃铛列表不可查看）
 *
 * v13.26.46 安全修复：
 * - Markdown 渲染后的 HTML 通过二次 DOMPurify 清理，防止短码注入 XSS
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('请使用 POST', 405);
}

vs_require_secure_post();

$userId = (int) UserAuth::id();
if ($userId <= 0) {
    AjaxResponse::error('请先登录', 401);
}

if (!ContentManager::tableReady() || !ContentManager::hasAudModeColumn()) {
    AjaxResponse::error('站内公告尚未就绪，请联系管理员完成系统升级');
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

if ($action === 'inbox') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $items = ContentManager::userListMessages($userId, 20);
    // popups：仅最新一条可自动弹（独立查询 id 最大且 ispopup=1）
    $popups = ContentManager::userListPopups($userId);
    AjaxResponse::success('ok', array(
        'items'     => $items,
        'popups'    => $popups,
        'has_items' => count($items) > 0 ? 1 : 0,
    ));
}

if ($action === 'detail') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0);
    $item = ContentManager::userMessageDetail($userId, $id);
    if ($item === null) {
        AjaxResponse::error('公告不存在或无权查看');
    }
    $body = isset($item['body']) ? (string) $item['body'] : '';
    unset($item['body']);
    if (class_exists('Markdown')) {
        $bodyHtml = Markdown::render($body);
        $item['body_html'] = ContentManager::sanitizeUserMessageHtml($bodyHtml);
    } else {
        $item['body_html'] = '<pre class="uc-msg-detail__plain">' . vs_e($body) . '</pre>';
    }
    AjaxResponse::success('ok', array('item' => $item));
}

AjaxResponse::error('无效操作', 400);
