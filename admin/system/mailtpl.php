<?php
/**
 * 文件：admin/system/mailtpl.php
 * 作用：系统管理 · 邮箱模板（content.kind=3；HTML + bindpage 场景）
 */

require_once dirname(__DIR__) . '/init.php';

$kind = ContentManager::KIND_MAILTPL;

/**
 * @param array $item
 * @return array
 */
function vs_mailtpl_row_ctx(array $item)
{
    $bind = isset($item['bindpage']) ? (int) $item['bindpage'] : 0;
    $status = isset($item['status']) ? (int) $item['status'] : ContentManager::STATUS_PUBLISHED;
    return array(
        'id'             => (int) (isset($item['id']) ? $item['id'] : 0),
        'title'          => isset($item['title']) ? (string) $item['title'] : '',
        'summary'        => isset($item['summary']) ? (string) $item['summary'] : '',
        'body'           => isset($item['body']) ? (string) $item['body'] : '',
        'bindpage'       => $bind,
        'bindpage_label' => ContentManager::bindPageLabel($bind, $kind = ContentManager::KIND_MAILTPL),
        'status'         => $status,
        'status_label'   => ($status === ContentManager::STATUS_OFF)
            ? '已停用'
            : ContentManager::statusLabel($status),
        'time'           => isset($item['updatetime']) && $item['updatetime']
            ? (string) $item['updatetime']
            : (isset($item['createtime']) ? (string) $item['createtime'] : ''),
    );
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_mailtpl_desktop_row(array $ctx)
{
    $bindClass = ((int) $ctx['bindpage'] === 0) ? 'vs-badge--default' : 'vs-badge--info';
    $stClass = ((int) $ctx['status'] === ContentManager::STATUS_OFF)
        ? 'vs-badge--warning'
        : 'vs-badge--success';
    if ((int) $ctx['status'] === ContentManager::STATUS_DRAFT) {
        $stClass = 'vs-badge--default';
    }
    ?>
    <tr data-content-row="<?php echo (int) $ctx['id']; ?>"
        data-title="<?php echo vs_e($ctx['title']); ?>"
        data-summary="<?php echo vs_e($ctx['summary']); ?>"
        data-bindpage="<?php echo (int) $ctx['bindpage']; ?>"
        data-status="<?php echo (int) $ctx['status']; ?>">
        <td>
            <span class="content-title-cell__text"><?php echo vs_e($ctx['title']); ?></span>
        </td>
        <td><span class="vs-badge <?php echo $bindClass; ?>"><?php echo vs_e($ctx['bindpage_label']); ?></span></td>
        <td><span class="vs-badge <?php echo $stClass; ?>"><?php echo vs_e($ctx['status_label']); ?></span></td>
        <td><span class="content-time-cell"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></td>
        <td class="vs-col-actions">
            <div class="action-btns">
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-mailtpl-act" data-act="edit">编辑</button>
                <?php if ((int) $ctx['status'] === ContentManager::STATUS_PUBLISHED): ?>
                    <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-mailtpl-act" data-act="off">停用</button>
                <?php else: ?>
                    <button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-mailtpl-act" data-act="on">启用</button>
                <?php endif; ?>
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-mailtpl-act" data-act="delete">删除</button>
            </div>
        </td>
    </tr>
    <?php
}

/**
 * 手机卡：对齐文章管理 art-card（名称 + 角标 + 时间 + 右下操作；不展示发信标题）
 *
 * @param array $ctx
 * @return void
 */
function vs_render_mailtpl_mobile_card(array $ctx)
{
    $bindClass = ((int) $ctx['bindpage'] === 0) ? 'vs-badge--default' : 'vs-badge--info';
    $stClass = ((int) $ctx['status'] === ContentManager::STATUS_OFF)
        ? 'vs-badge--warning'
        : 'vs-badge--success';
    if ((int) $ctx['status'] === ContentManager::STATUS_DRAFT) {
        $stClass = 'vs-badge--default';
    }
    ?>
    <div class="art-card"
         data-content-row="<?php echo (int) $ctx['id']; ?>"
         data-title="<?php echo vs_e($ctx['title']); ?>"
         data-summary="<?php echo vs_e($ctx['summary']); ?>"
         data-bindpage="<?php echo (int) $ctx['bindpage']; ?>"
         data-status="<?php echo (int) $ctx['status']; ?>">
        <div class="art-card__header">
            <span class="art-card__title"><?php echo vs_e($ctx['title']); ?></span>
            <div class="art-card__tags">
                <span class="vs-badge <?php echo $bindClass; ?>"><?php echo vs_e($ctx['bindpage_label']); ?></span>
                <span class="vs-badge <?php echo $stClass; ?>"><?php echo vs_e($ctx['status_label']); ?></span>
            </div>
        </div>
        <div class="art-card__info">
            <span class="art-card__info-item">
                <span class="art-card__info-label">时间</span>
                <span class="art-card__info-value"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span>
            </span>
        </div>
        <div class="art-card__actions">
            <div class="action-btns">
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-mailtpl-act" data-act="edit">编辑</button>
                <?php if ((int) $ctx['status'] === ContentManager::STATUS_PUBLISHED): ?>
                    <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-mailtpl-act" data-act="off">停用</button>
                <?php else: ?>
                    <button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-mailtpl-act" data-act="on">启用</button>
                <?php endif; ?>
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-mailtpl-act" data-act="delete">删除</button>
            </div>
        </div>
    </div>
    <?php
}

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
            'summary'  => isset($_POST['summary']) ? (string) $_POST['summary'] : '',
            'body'     => isset($_POST['body']) ? (string) $_POST['body'] : '',
            'bindpage' => isset($_POST['bindpage']) ? (int) $_POST['bindpage'] : 0,
            'status'   => isset($_POST['status'])
                ? (int) $_POST['status']
                : ContentManager::STATUS_PUBLISHED,
            'userid'   => $publishUid,
            'ispopup'  => 0,
            'ispinned' => 0,
        );
        if ($action === 'create') {
            $result = ContentManager::create($payload);
            if (!is_array($result)) {
                AjaxResponse::error($result);
            }
            AjaxResponse::success('模板已保存', array('item' => $result));
        }
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $rowCheck = ContentManager::findById($id);
        if (!$rowCheck || ContentManager::normalizeKind($rowCheck['kind']) !== $kind) {
            AjaxResponse::error('模板不存在');
        }
        $result = ContentManager::update($id, $payload);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ContentManager::findById($id);
        AjaxResponse::success('模板已保存', array(
            'item' => is_array($row) ? ContentManager::formatRow($row) : null,
        ));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        if (!in_array($status, array(
            ContentManager::STATUS_DRAFT,
            ContentManager::STATUS_PUBLISHED,
            ContentManager::STATUS_OFF,
        ), true)) {
            AjaxResponse::error('无效状态');
        }
        $row = ContentManager::findById($id);
        if (!$row || ContentManager::normalizeKind($row['kind']) !== $kind) {
            AjaxResponse::error('模板不存在');
        }
        $result = ContentManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ContentManager::findById($id);
        AjaxResponse::success(
            $status === ContentManager::STATUS_PUBLISHED ? '已启用' : '已停用',
            array('item' => is_array($row) ? ContentManager::formatRow($row) : null)
        );
    }

    if ($action === 'delete') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $row = ContentManager::findById($id);
        if (!$row || ContentManager::normalizeKind($row['kind']) !== $kind) {
            AjaxResponse::error('模板不存在');
        }
        $result = ContentManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('模板已删除', array('content_id' => $id));
    }

    if ($action === 'list') {
        $paged = ContentManager::listAdminPaged(array(
            'kind'     => $kind,
            'q'        => isset($_POST['q']) ? (string) $_POST['q'] : '',
            'page'     => isset($_POST['page']) ? (int) $_POST['page'] : 1,
            'pagesize' => isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20,
        ));
        ob_start();
        foreach ($paged['list'] as $item) {
            vs_render_mailtpl_desktop_row(vs_mailtpl_row_ctx($item));
        }
        $desk = ob_get_clean();
        ob_start();
        foreach ($paged['list'] as $item) {
            vs_render_mailtpl_mobile_card(vs_mailtpl_row_ctx($item));
        }
        $mobile = ob_get_clean();
        AjaxResponse::success('ok', array(
            'desktop_html' => $desk,
            'mobile_html'  => $mobile,
            'total'        => (int) $paged['total'],
            'page'         => (int) $paged['page'],
            'pagesize'     => (int) $paged['pagesize'],
        ));
    }

    if ($action === 'get') {
        $id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
        $row = ContentManager::findById($id);
        if (!$row || ContentManager::normalizeKind($row['kind']) !== $kind) {
            AjaxResponse::error('模板不存在');
        }
        AjaxResponse::success('ok', array(
            'item' => ContentManager::formatRow($row),
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ContentManager::tableReady();
$paged = $tableReady ? ContentManager::listAdminPaged(array(
    'kind'     => $kind,
    'page'     => 1,
    'pagesize' => 20,
)) : array('list' => array(), 'total' => 0);
$items = $paged['list'];
$contentTotal = (int) $paged['total'];
$hasItems = $contentTotal > 0;
$bindOptions = class_exists('MailChannels') ? MailChannels::optionsForSelect() : array(0 => '自定义（群发可用）');
$phCatalog = class_exists('MailChannels')
    ? MailChannels::placeholderCatalogForUi()
    : array(
        'common' => array('用户名', '站点名', '站点名称', '站点链接', '日期', '时间', '余额', '用户头像'),
        'byBind' => array('0' => array()),
    );

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="mailtplSearchInput" placeholder="搜索模板名称..." autocomplete="off">
        </div>
        <button type="button" class="vs-btn vs-btn--primary" id="mailtplAddBtn">新建模板</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('邮箱模板', 'sys-mailtpl', $headerActions);
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先在系统升级中执行数据库结构更新。', array('compact' => true)); ?>
<?php else: ?>
<div id="mailtplPage" data-content-total="<?php echo (int) $contentTotal; ?>">
    <?php vs_render_notice('tip', '', '发信标题与正文支持占位符如 {{用户名}}、{{站点名}}、{{站点名称}}、{{站点链接}}、{{日期}}。绑定系统场景后由业务通知自动使用；自定义模板可供「邮件群发」选用。', array('compact' => true)); ?>

    <div class="vs-api-list-empty vs-api-list-empty--hero" id="mailtplEmpty"<?php echo $hasItems ? ' hidden' : ''; ?>>
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无邮箱模板</h3>
            <p class="vs-api-list-empty__desc">点击右上角「新建模板」创建。无绑定模板时业务通知仍回落系统内置文案。</p>
        </div>
    </div>
    <div class="vs-api-list-empty vs-api-list-empty--hero" id="mailtplSearchEmpty" hidden>
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
            <p class="vs-api-list-empty__desc">当前搜索下没有模板，可清空关键词重试。</p>
        </div>
    </div>

    <div class="vs-api-list-table-card vs-api-list-table-wrap" id="mailtplTableWrap"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <div class="vs-table-responsive">
            <table class="vs-table vs-content-table">
                <thead>
                    <tr>
                        <th>模板名称</th>
                        <th>绑定场景</th>
                        <th>状态</th>
                        <th>时间</th>
                        <th class="vs-col-actions">操作</th>
                    </tr>
                </thead>
                <tbody id="mailtplBody">
                    <?php foreach ($items as $item): ?>
                        <?php vs_render_mailtpl_desktop_row(vs_mailtpl_row_ctx($item)); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mobile-art-cards" id="mailtplMobile"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <?php foreach ($items as $item): ?>
            <?php vs_render_mailtpl_mobile_card(vs_mailtpl_row_ctx($item)); ?>
        <?php endforeach; ?>
    </div>

    <div class="vs-api-list-footer" id="mailtplFooter"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <div class="vs-api-pager" id="mailtplPager">
            <label class="vs-api-list-pagesize" for="mailtplPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select vs-api-list-pagesize__select" id="mailtplPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <div class="vs-api-pager__navs">
                <button type="button" class="vs-api-pager__nav" id="mailtplPrevBtn">上一页</button>
                <div class="vs-api-pager__nums" id="mailtplPagerNums"></div>
                <button type="button" class="vs-api-pager__nav" id="mailtplNextBtn">下一页</button>
            </div>
        </div>
        <p class="vs-api-list-stats" id="mailtplStats">共 <?php echo (int) $contentTotal; ?> 条</p>
    </div>
</div>

<div class="vs-overlay vs-overlay--lg" id="mailtplOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="mailtplFormTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="mailtplFormTitle">新建模板</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form class="vs-overlay__body" id="mailtplForm" autocomplete="off">
            <input type="hidden" name="content_id" id="mailtplId" value="0">
            <script type="application/json" id="mailtplPhCatalog"><?php echo json_encode($phCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
            <?php
            $aiLines = array();
            $aiLines[] = '请按以下约定编写一封 HTML 邮件模板（发信标题 + 正文）：';
            $aiLines[] = '';
            $aiLines[] = '【可用占位符 · 公共】';
            foreach ($phCatalog['common'] as $k) {
                $aiLines[] = '{{' . $k . '}}';
            }
            $aiLines[] = '';
            $aiLines[] = '【可用占位符 · 按绑定场景】（只使用当前绑定场景下列出的键）';
            if (class_exists('MailChannels')) {
                foreach (MailChannels::catalog() as $code => $row) {
                    $keys = MailChannels::scenarioPlaceholderKeys($code);
                    if (!$keys) {
                        continue;
                    }
                    $aiLines[] = '· ' . $row['label'] . '：' . implode(' ', array_map(function ($x) {
                        return '{{' . $x . '}}';
                    }, $keys));
                }
            }
            $aiLines[] = '';
            $aiLines[] = '【安全注意 · 必须遵守】';
            $aiLines[] = '1. 禁止 <script>、<iframe>、<object>、<embed>、<form>、<svg>、<math>、<link>、<meta>、<base>、<style> 标签。';
            $aiLines[] = '2. 禁止任何 on* 事件属性（如 onclick）与 javascript: / data: 伪协议链接。';
            $aiLines[] = '3. 可用安全行内 style；图片仅用 https 或站内路径；占位符写成 {{键名}}。';
            $aiLines[] = '4. 系统通知场景发信时会剥站内可点击链接；自定义/群发模板可使用 {{站点链接}}。';
            $aiLines[] = '5. 不要编造未列出的占位符；缺值时该占位会替换为空。';
            $aiPromptText = implode("\n", $aiLines);
            ?>
            <div class="vs-mailtpl-editor" id="mailtplEditor" data-mode="edit">
                <div class="vs-mailtpl-editor__mobilebar">
                    <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" id="mailtplPreviewToggle" aria-pressed="false">在线预览</button>
                    <span class="vs-form-hint">手机：点此在编辑 / 预览间切换</span>
                </div>
                <div class="vs-mailtpl-editor__grid">
                    <div class="vs-mailtpl-editor__input" id="mailtplEditorInput">
                        <div class="vs-field">
                            <label class="vs-label" for="mailtplTitle">模板名称</label>
                            <input class="vs-input" type="text" name="title" id="mailtplTitle" maxlength="200" required>
                        </div>
                        <div class="vs-field">
                            <label class="vs-label" for="mailtplSummary">发信标题</label>
                            <input class="vs-input" type="text" name="summary" id="mailtplSummary" maxlength="500" required placeholder="支持 {{站点名}} {{用户名}} 等">
                        </div>
                        <div class="vs-field">
                            <label class="vs-label" for="mailtplBind">绑定场景</label>
                            <select class="vs-input vs-select" name="bindpage" id="mailtplBind" data-vs-pick>
                                <?php foreach ($bindOptions as $code => $label): ?>
                                    <option value="<?php echo (int) $code; ?>"><?php echo vs_e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="vs-form-hint">自定义可供群发；系统场景每个只能有一条已发布模板。</p>
                        </div>
                        <div class="vs-field">
                            <label class="vs-label" for="mailtplBodyText">邮件正文（HTML）</label>
                            <div class="vs-mailtpl-phbar" id="mailtplPhBar" data-ph-targets="mailtplSummary,mailtplBodyText"></div>
                            <p class="vs-form-hint" id="mailtplPhHint">占位符随「绑定场景」切换。电脑右侧为实时预览。</p>
                            <textarea class="vs-input vs-textarea vs-mailtpl-body" name="body" id="mailtplBodyText" rows="32" placeholder="HTML；可插入 {{用户名}} 等"></textarea>
                        </div>
                    </div>
                    <div class="vs-mailtpl-editor__preview" id="mailtplEditorPreview" aria-live="polite">
                        <div class="vs-mailtpl-preview-card">
                            <p class="vs-label">发信标题预览</p>
                            <p class="vs-mailtpl-preview-card__subject" id="mailtplPreviewSubject">—</p>
                            <p class="vs-label">正文预览</p>
                            <div class="vs-mailtpl-preview-card__body" id="mailtplPreviewBody"></div>
                            <p class="vs-form-hint">预览用示例值替换常见占位符；真实发信按收件人/场景赋值。</p>
                        </div>
                    </div>
                </div>
                <div class="vs-field">
                    <label class="vs-label" for="mailtplStatus">状态</label>
                    <select class="vs-input vs-select" name="status" id="mailtplStatus" data-vs-pick>
                        <option value="1">已发布</option>
                        <option value="0">草稿</option>
                        <option value="2">停用</option>
                    </select>
                </div>
                <div class="vs-settings-api-fold vs-mailtpl-ai-fold" id="mailtplAiFold">
                    <button type="button" class="vs-settings-api-fold__bar" id="mailtplAiFoldBar" aria-expanded="false" aria-controls="mailtplAiFoldBody">
                        <span class="vs-settings-api-fold__title">AI / 占位符说明（点击展开，可复制）</span>
                        <i class="vs-icon vs-icon--chevron" aria-hidden="true"></i>
                    </button>
                    <div class="vs-settings-api-fold__body" id="mailtplAiFoldBody" hidden>
                        <p class="vs-form-hint">给 AI 写模板时可整段复制；已含当前全部公共与场景占位符及安全约束。</p>
                        <pre class="vs-mailtpl-ai-block" id="mailtplAiBlock"><code><?php echo vs_e($aiPromptText); ?></code></pre>
                        <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" id="mailtplAiCopyBtn">复制说明</button>
                    </div>
                </div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="mailtplSaveBtn">保存</button>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('vs-pick.js', 'admin-mailtpl.js') : array()); ?>
