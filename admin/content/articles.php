<?php
/**
 * 文件：admin/content/articles.php
 * 作用：文章管理（发布 / 编辑 / 删除；含封面）
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/content_helpers.php';

$kind = ContentManager::KIND_ARTICLE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create' || $action === 'update') {
        $publishUid = AdminUserBinding::publishUserId((int) Auth::id());
        if (!is_int($publishUid)) {
            AjaxResponse::error((string) $publishUid);
        }
        $payload = array(
            'kind'        => $kind,
            'title'       => isset($_POST['title']) ? (string) $_POST['title'] : '',
            'summary'     => isset($_POST['summary']) ? (string) $_POST['summary'] : '',
            'body'        => isset($_POST['body']) ? (string) $_POST['body'] : '',
            'cover'       => isset($_POST['cover']) ? (string) $_POST['cover'] : '',
            'coverlayout' => isset($_POST['coverlayout']) ? (int) $_POST['coverlayout'] : ContentManager::COVER_LEFT,
            'ispinned'    => 0,
            'ispopup'     => 0,
            'status'      => ContentManager::STATUS_PUBLISHED,
            'userid'      => $publishUid,
            'sort'        => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
        );
        if ($action === 'create') {
            $result = ContentManager::create($payload);
            if (!is_array($result)) {
                AjaxResponse::error($result);
            }
            AjaxResponse::success('文章已保存', array('item' => $result));
        }
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $rowCheck = ContentManager::findById($id);
        if (!$rowCheck || ContentManager::normalizeKind($rowCheck['kind']) !== $kind) {
            AjaxResponse::error('文章不存在');
        }
        $result = ContentManager::update($id, $payload);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ContentManager::findById($id);
        AjaxResponse::success('文章已保存', array(
            'item' => is_array($row) ? ContentManager::formatRow($row) : null,
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $row = ContentManager::findById($id);
        if (!$row || ContentManager::normalizeKind($row['kind']) !== $kind) {
            AjaxResponse::error('文章不存在');
        }
        $result = ContentManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('文章已删除', array('content_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ContentManager::tableReady();
$items = $tableReady ? ContentManager::listAll($kind) : array();

vs_admin_layout_start(
    '文章管理',
    'articles',
    $tableReady
        ? '<button type="button" class="vs-btn vs-btn--primary" id="contentAddBtn">发布文章</button>'
        : ''
);
echo Markdown::renderAssetsHtml();
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先在系统升级中执行数据库结构更新。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-panel" id="contentPage" data-kind="<?php echo (int) $kind; ?>" data-mode="article">
    <div class="vs-content-list" id="contentList">
        <?php if (count($items) === 0): ?>
            <p class="vs-empty" id="contentEmpty">暂无文章，点击右上角发布。</p>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php vs_render_content_row($item, false); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="vs-overlay vs-overlay--lg" id="contentOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="contentFormTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="contentFormTitle">发布文章</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form class="vs-overlay__body" id="contentForm" autocomplete="off">
            <input type="hidden" name="content_id" id="contentId" value="0">
            <div class="vs-field">
                <label class="vs-label" for="contentTitle">标题</label>
                <input class="vs-input" type="text" name="title" id="contentTitle" maxlength="200" required>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="contentCover">封面图链接</label>
                <input class="vs-input" type="url" name="cover" id="contentCover" maxlength="500" placeholder="https://">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="contentCoverLayout">封面布局</label>
                <select class="vs-input vs-select" name="coverlayout" id="contentCoverLayout" data-vs-pick>
                    <option value="0">左侧</option>
                    <option value="1">右侧</option>
                    <option value="2">背景</option>
                </select>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="contentSummary">摘要</label>
                <input class="vs-input" type="text" name="summary" id="contentSummary" maxlength="500">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="contentBody">正文（Markdown）</label>
                <textarea class="vs-input vs-textarea" name="body" id="contentBody" data-vs-md="desktop" rows="14"></textarea>
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
