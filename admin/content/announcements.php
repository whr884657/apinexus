<?php
/**
 * 文件：admin/content/announcements.php
 * 作用：公告管理（编辑 / 置顶 / 弹窗 / 删除）
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/content_helpers.php';

$kind = ContentManager::KIND_ANNOUNCEMENT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create' || $action === 'update') {
        $publishUid = AdminUserBinding::publishUserId((int) Auth::id());
        if (!is_int($publishUid)) {
            AjaxResponse::error((string) $publishUid);
        }
        $payload = array(
            'kind'     => $kind,
            'title'    => isset($_POST['title']) ? (string) $_POST['title'] : '',
            'summary'  => '',
            'body'     => isset($_POST['body']) ? (string) $_POST['body'] : '',
            'cover'    => '',
            'status'   => ContentManager::STATUS_PUBLISHED,
            'userid'   => $publishUid,
            'sort'     => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
        );
        if ($action === 'create') {
            $payload['ispinned'] = 0;
            $payload['ispopup'] = 0;
            $result = ContentManager::create($payload);
            if (!is_array($result)) {
                AjaxResponse::error($result);
            }
            AjaxResponse::success('公告已保存', array('item' => $result));
        }
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $rowCheck = ContentManager::findById($id);
        if (!$rowCheck || ContentManager::normalizeKind($rowCheck['kind']) !== $kind) {
            AjaxResponse::error('公告不存在');
        }
        $result = ContentManager::update($id, $payload);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ContentManager::findById($id);
        AjaxResponse::success('公告已保存', array(
            'item' => is_array($row) ? ContentManager::formatRow($row) : null,
        ));
    }

    if ($action === 'set_pinned') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $flag = isset($_POST['ispinned']) ? (int) $_POST['ispinned'] : 0;
        $result = ContentManager::setPinned($id, $flag);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success($flag ? '已置顶' : '已取消置顶', array(
            'content_id' => $id,
            'ispinned'   => ContentManager::normalizeFlag($flag),
        ));
    }

    if ($action === 'set_popup') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $flag = isset($_POST['ispopup']) ? (int) $_POST['ispopup'] : 0;
        $result = ContentManager::setPopup($id, $flag);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success($flag ? '已设为弹窗' : '已取消弹窗', array(
            'content_id' => $id,
            'ispopup'    => ContentManager::normalizeFlag($flag),
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $row = ContentManager::findById($id);
        if (!$row || ContentManager::normalizeKind($row['kind']) !== $kind) {
            AjaxResponse::error('公告不存在');
        }
        $result = ContentManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('公告已删除', array('content_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ContentManager::tableReady();
$items = $tableReady ? ContentManager::listAll($kind) : array();

vs_admin_layout_start(
    '公告管理',
    'announcements',
    $tableReady
        ? '<button type="button" class="vs-btn vs-btn--primary" id="contentAddBtn">发布公告</button>'
        : ''
);
echo Markdown::renderAssetsHtml();
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先在系统升级中执行数据库结构更新。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-panel" id="contentPage" data-kind="<?php echo (int) $kind; ?>" data-mode="announcement">
    <div class="vs-content-list" id="contentList">
        <?php if (count($items) === 0): ?>
            <p class="vs-empty" id="contentEmpty">暂无公告，点击右上角发布。</p>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php vs_render_content_row($item, true); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="vs-overlay vs-overlay--lg" id="contentOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="contentFormTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="contentFormTitle">发布公告</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form class="vs-overlay__body" id="contentForm" autocomplete="off">
            <input type="hidden" name="content_id" id="contentId" value="0">
            <div class="vs-field">
                <label class="vs-label" for="contentTitle">标题</label>
                <input class="vs-input" type="text" name="title" id="contentTitle" maxlength="200" required>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="contentBody">正文（Markdown）</label>
                <textarea class="vs-input vs-textarea" name="body" id="contentBody" data-vs-md="off" rows="12"></textarea>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="contentSaveBtn">保存</button>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('admin-content.js') : array()); ?>
