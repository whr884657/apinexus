<?php
/**
 * 文件：user/messages.php
 * 作用：用户中心 · 全部站内公告（搜索 + 横幅列表 + 底部分页 + 弹窗详情；不进侧栏菜单）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$tableReady = class_exists('ContentManager')
    && ContentManager::tableReady()
    && ContentManager::hasAudModeColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    if (!$tableReady) {
        AjaxResponse::error('站内公告尚未就绪，请联系管理员完成系统升级');
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'list') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $q = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
        $data = ContentManager::userListMessagesPaged($userId, $page, $pagesize, $q);
        AjaxResponse::success('ok', $data);
    }

    if ($action === 'detail') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
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
}

vs_user_render_page(
    'messages',
    '全部站内公告',
    '',
    array(
        'tableReady' => $tableReady,
    ),
    '',
    array('user-messages.js')
);
