<?php
/**
 * 文件：admin/content/announcements.php
 * 作用：公告管理（桌面表格 + 手机卡片；编辑 / 置顶 / 弹窗 / 删除）
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
            'body'     => isset($_POST['body']) ? vs_decode_transport_field((string) $_POST['body']) : '',
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
$hasItems = count($items) > 0;

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="contentSearchInput" placeholder="搜索公告标题..." autocomplete="off">
        </div>
        <button type="button" class="vs-btn vs-btn--primary" id="contentAddBtn">发布公告</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('公告管理', 'announcements', $headerActions);
echo Markdown::renderAssetsHtml();
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先在系统升级中执行数据库结构更新。', array('compact' => true)); ?>
<?php else: ?>
<div id="contentPage" data-kind="<?php echo (int) $kind; ?>" data-mode="announcement" data-content-total="<?php echo (int) count($items); ?>">
    <div class="vs-api-list-empty vs-api-list-empty--hero" id="contentEmpty"<?php echo $hasItems ? ' hidden' : ''; ?>>
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无公告</h3>
            <p class="vs-api-list-empty__desc">点击右上角「发布公告」创建。保存后即可在前台展示。</p>
        </div>
    </div>
    <div class="vs-api-list-empty vs-api-list-empty--hero" id="contentSearchEmpty" hidden>
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
            <p class="vs-api-list-empty__desc">当前搜索下没有公告，可清空关键词重试。</p>
        </div>
    </div>

    <div class="vs-api-list-table-card vs-api-list-table-wrap" id="contentTableWrap"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <div class="vs-table-responsive">
            <table class="vs-table vs-content-table">
                <thead>
                    <tr>
                        <th>标题</th>
                        <th>发布时间</th>
                        <th>置顶</th>
                        <th>弹窗</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="contentBody">
                    <?php foreach ($items as $item): ?>
                        <?php vs_render_content_desktop_row(vs_content_row_ctx($item, true), true); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mobile-ann-cards" id="contentMobile"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <?php foreach ($items as $item): ?>
            <?php vs_render_content_mobile_card(vs_content_row_ctx($item, true), true); ?>
        <?php endforeach; ?>
    </div>

    <div class="vs-api-list-footer" id="contentFooter"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <div class="vs-api-pager" id="contentPager">
            <label class="vs-api-list-pagesize" for="contentPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select vs-api-list-pagesize__select" id="contentPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <div class="vs-api-pager__navs">
                <button type="button" class="vs-api-pager__nav" id="contentPrevBtn" aria-label="上一页">上一页</button>
                <div class="vs-api-pager__nums" id="contentPagerNums" role="navigation" aria-label="页码"></div>
                <button type="button" class="vs-api-pager__nav" id="contentNextBtn" aria-label="下一页">下一页</button>
            </div>
        </div>
        <p class="vs-api-list-stats" id="contentStats">共 <?php echo (int) count($items); ?> 条</p>
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
<?php vs_admin_layout_end($tableReady ? array('vs-pick.js', 'admin-content.js') : array()); ?>
