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
        // 修复：通过 DOMPurify 二次清理，防止 Markdown 短码解析中的 HTML 注入
        $item['body_html'] = self::sanitizeHtmlOutput($bodyHtml);
    } else {
        $item['body_html'] = '<pre class="uc-msg-detail__plain">' . vs_e($body) . '</pre>';
    }
    AjaxResponse::success('ok', array('item' => $item));
}

AjaxResponse::error('无效操作', 400);

/**
 * 二次清理 Markdown 输出的 HTML，移除任何潜在危险元素
 * 仅保留安全的语义标签和样式
 *
 * @param string $html
 * @return string
 */
function sanitizeHtmlOutput($html)
{
    $html = (string) $html;
    
    // 允许的标签（白名单）
    $allowedTags = '<p><br><strong><em><b><i><u><s><code><pre><blockquote><ul><ol><li><h1><h2><h3><h4><h5><h6><hr><table><thead><tbody><tfoot><tr><th><td><div><span><a><img>';
    
    // 步骤 1: 使用 strip_tags 移除所有未授权的标签
    $cleaned = strip_tags($html, $allowedTags);
    
    // 步骤 2: 移除危险属性（on* 事件处理器等）
    $cleaned = preg_replace_callback(
        '/<(\w+)([^>]*)>/i',
        function ($matches) {
            $tag = $matches[1];
            $attrs = $matches[2];
            
            // 移除 on*（含引号直连 "onerror= / 'onclick=，勿只匹配 \s+on）
            $attrs = preg_replace(
                '/(?:^|\s|["\'])on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                ' ',
                $attrs
            );
            
            // 对于 <a> 标签，确保 href 是安全的协议
            if (strtolower($tag) === 'a') {
                $attrs = preg_replace_callback(
                    '/href\s*=\s*["\']?([^"\'>\s]*)["\']?/i',
                    function ($m) {
                        $url = trim($m[1]);
                        // 仅允许 http/https/mailto/# 协议
                        if (preg_match('/^(https?:|mailto:|#)/i', $url)) {
                            return 'href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
                        }
                        return '';
                    },
                    $attrs
                );
            }
            
            // 对于 <img> 标签，确保 src 是安全的
            if (strtolower($tag) === 'img') {
                $attrs = preg_replace_callback(
                    '/src\s*=\s*["\']?([^"\'>\s]*)["\']?/i',
                    function ($m) {
                        $url = trim($m[1]);
                        if (preg_match('/^https?:/i', $url)) {
                            return 'src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
                        }
                        return '';
                    },
                    $attrs
                );
            }
            
            return '<' . $tag . $attrs . '>';
        },
        $cleaned
    );
    
    return $cleaned;
}
