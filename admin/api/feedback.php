<?php
/**
 * 文件：admin/api/feedback.php
 * 作用：接口反馈（电脑表格 + 手机卡片；搜索/状态筛选）
 */

require_once dirname(__DIR__) . '/init.php';

$tableReady = ApiFeedbackManager::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!$tableReady) {
        AjaxResponse::error('反馈表尚未就绪，请先执行数据库结构更新');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $id = isset($_POST['feedback_id']) ? (int) $_POST['feedback_id'] : 0;

    if ($action === 'mark_done') {
        $result = ApiFeedbackManager::setStatus($id, ApiFeedbackManager::STATUS_DONE);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiFeedbackManager::formatRow(ApiFeedbackManager::findById($id));
        AjaxResponse::success('已标记为已处理', array('feedback' => $row));
    }

    if ($action === 'save_reply') {
        $reply = isset($_POST['reply']) ? (string) $_POST['reply'] : '';
        $result = ApiFeedbackManager::setReply($id, $reply);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $markDone = isset($_POST['mark_done']) && (string) $_POST['mark_done'] === '1';
        if ($markDone) {
            $st = ApiFeedbackManager::setStatus($id, ApiFeedbackManager::STATUS_DONE);
            if ($st !== true) {
                AjaxResponse::error($st);
            }
        }
        $row = ApiFeedbackManager::formatRow(ApiFeedbackManager::findById($id));
        AjaxResponse::success($markDone ? '已保存并标记已处理' : '回复已保存', array('feedback' => $row));
    }

    if ($action === 'delete') {
        $result = ApiFeedbackManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('反馈已删除', array('feedback_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$rows = $tableReady ? ApiFeedbackManager::listAll() : array();

/**
 * @param array $row
 * @return array
 */
function vs_admin_fb_row_ctx(array $row)
{
    $fb = ApiFeedbackManager::formatRow($row);
    if (!$fb) {
        return array();
    }
    $id = (int) $fb['id'];
    $pending = (int) $fb['status'] === ApiFeedbackManager::STATUS_PENDING;
    $username = $fb['username'] !== '' ? $fb['username'] : ('用户#' . $fb['userid']);
    $avatar = mb_substr($username, 0, 1, 'UTF-8');
    $apiName = $fb['api_name'] !== '' ? $fb['api_name'] : ($fb['apiid'] > 0 ? ('接口#' . $fb['apiid']) : '—');
    $time = $fb['createtime'];
    if ($time !== '' && strlen($time) >= 16) {
        $time = substr($time, 0, 16);
    }
    $content = $fb['content'];
    $search = mb_strtolower($content . ' ' . $apiName . ' ' . $username . ' #' . $id, 'UTF-8');

    return array(
        'id'       => $id,
        'pending'  => $pending,
        'status'   => (int) $fb['status'],
        'content'  => $content,
        'reply'    => $fb['reply'],
        'api_name' => $apiName,
        'username' => $username,
        'avatar'   => $avatar,
        'time'     => $time,
        'search'   => $search,
    );
}

/**
 * @param array $ctx
 * @return string
 */
function vs_admin_fb_actions_html(array $ctx)
{
    $id = (int) $ctx['id'];
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-fb-view" data-feedback-id="'
        . $id . '">查看</button>';
    if ($ctx['pending']) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-fb-mark" data-feedback-id="'
            . $id . '">标记已处理</button>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_admin_fb_desktop_row(array $ctx)
{
    if ($ctx === array()) {
        return;
    }
    $attrs = ' data-feedback-row="' . (int) $ctx['id'] . '"'
        . ' data-feedback-status="' . (int) $ctx['status'] . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"'
        . ' data-content="' . vs_e($ctx['content']) . '"'
        . ' data-reply="' . vs_e($ctx['reply']) . '"'
        . ' data-api-name="' . vs_e($ctx['api_name']) . '"'
        . ' data-username="' . vs_e($ctx['username']) . '"'
        . ' data-time="' . vs_e($ctx['time']) . '"';
    ?>
    <tr<?php echo $attrs; ?>>
        <td>
            <div class="fb-content-cell">
                <div class="fb-content-cell__text" data-field="content"><?php echo vs_e($ctx['content']); ?></div>
            </div>
        </td>
        <td><span class="fb-api-cell" data-field="api_name"><?php echo vs_e($ctx['api_name']); ?></span></td>
        <td>
            <div class="fb-user-cell">
                <div class="fb-user-cell__avatar" data-field="avatar"><?php echo vs_e($ctx['avatar']); ?></div>
                <span class="fb-user-cell__name" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
            </div>
        </td>
        <td><span class="fb-time-cell" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></td>
        <td>
            <span class="vs-badge <?php echo $ctx['pending'] ? 'vs-badge--warning' : 'vs-badge--success'; ?>" data-field="status_label">
                <?php echo $ctx['pending'] ? '待处理' : '已处理'; ?>
            </span>
        </td>
        <td class="vs-api-fb-actions-cell" data-field="actions">
            <?php echo vs_admin_fb_actions_html($ctx); ?>
        </td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_admin_fb_mobile_card(array $ctx)
{
    if ($ctx === array()) {
        return;
    }
    $attrs = ' data-feedback-row="' . (int) $ctx['id'] . '"'
        . ' data-feedback-status="' . (int) $ctx['status'] . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"'
        . ' data-content="' . vs_e($ctx['content']) . '"'
        . ' data-reply="' . vs_e($ctx['reply']) . '"'
        . ' data-api-name="' . vs_e($ctx['api_name']) . '"'
        . ' data-username="' . vs_e($ctx['username']) . '"'
        . ' data-time="' . vs_e($ctx['time']) . '"';
    ?>
    <div class="feedback-card"<?php echo $attrs; ?>>
        <div class="feedback-card__content" data-field="content"><?php echo vs_e($ctx['content']); ?></div>
        <div class="feedback-card__meta">
            <span class="feedback-card__meta-item">
                <span class="feedback-card__meta-label">关联接口</span>
                <span class="feedback-card__meta-value" data-field="api_name"><?php echo vs_e($ctx['api_name']); ?></span>
            </span>
            <span class="feedback-card__meta-item">
                <span class="feedback-card__meta-label">反馈用户</span>
                <span class="feedback-card__meta-value" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
            </span>
            <span class="feedback-card__meta-item">
                <span class="feedback-card__meta-label">提交时间</span>
                <span class="feedback-card__meta-value" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span>
            </span>
            <span class="feedback-card__meta-item">
                <span class="vs-badge <?php echo $ctx['pending'] ? 'vs-badge--warning' : 'vs-badge--success'; ?>" data-field="status_label">
                    <?php echo $ctx['pending'] ? '待处理' : '已处理'; ?>
                </span>
            </span>
        </div>
        <div class="feedback-card__actions" data-field="actions">
            <?php echo vs_admin_fb_actions_html($ctx); ?>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="adminFbSearchInput"
                   placeholder="搜索反馈内容、接口名或用户..." autocomplete="off">
        </div>
        <select class="vs-input vs-select" id="adminFbStatusFilter" data-vs-pick aria-label="状态筛选">
            <option value="">全部</option>
            <option value="0">待处理</option>
            <option value="1">已处理</option>
        </select>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口反馈', 'api-feedback', $headerActions);
?>

<div id="apiFeedbackPage" data-feedback-total="<?php echo (int) count($rows); ?>">
    <?php if (!$tableReady): ?>
        <div class="vs-panel">
            <?php vs_render_notice('warning', '', '请先在「系统升级」中执行数据库结构更新，以创建反馈表。', array('compact' => true)); ?>
        </div>
    <?php else: ?>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminFbEmpty"<?php echo count($rows) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无反馈</h3>
                <p class="vs-api-list-empty__desc">用户提交的接口反馈将显示在此，可查看并标记处理。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminFbSearchEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前搜索或状态下没有反馈，可清空条件重试。</p>
            </div>
        </div>

        <div class="vs-api-list-table-card vs-api-list-table-wrap" id="adminFbTableWrap"<?php echo count($rows) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-table-responsive">
                <table class="vs-table vs-api-fb-table">
                    <thead>
                        <tr>
                            <th>反馈内容</th>
                            <th>关联接口</th>
                            <th>反馈用户</th>
                            <th>提交时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="adminFbBody">
                        <?php foreach ($rows as $row): ?>
                            <?php vs_render_admin_fb_desktop_row(vs_admin_fb_row_ctx($row)); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-feedback-cards" id="adminFbMobile"<?php echo count($rows) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($rows as $row): ?>
                <?php vs_render_admin_fb_mobile_card(vs_admin_fb_row_ctx($row)); ?>
            <?php endforeach; ?>
        </div>

        <div class="vs-api-list-footer" id="adminFbFooter"<?php echo count($rows) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-pager" id="adminFbPager">
                <label class="vs-api-list-pagesize" for="adminFbPageSize">
                    <span class="vs-api-list-pagesize__label">每页</span>
                    <select class="vs-input vs-select vs-api-list-pagesize__select" id="adminFbPageSize" data-vs-pick="sheet">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <div class="vs-api-pager__navs" id="adminFbPagerNav">
                    <button type="button" class="vs-api-pager__nav" id="adminFbPrevBtn" aria-label="上一页">上一页</button>
                    <div class="vs-api-pager__nums" id="adminFbPagerNums" role="navigation" aria-label="页码"></div>
                    <button type="button" class="vs-api-pager__nav" id="adminFbNextBtn" aria-label="下一页">下一页</button>
                </div>
            </div>
            <p class="vs-api-list-stats" id="adminFbStats">共 <?php echo (int) count($rows); ?> 条</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--lg" id="adminFbDetailOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="adminFbDetailTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="adminFbDetailTitle">反馈详情</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <input type="hidden" id="adminFbDetailId" value="">
            <div class="fb-modal__meta">
                <span class="vs-badge vs-badge--warning" id="adminFbDetailStatus">待处理</span>
                <span class="fb-modal__id" id="adminFbDetailIdLabel"></span>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">关联接口</div>
                <div class="fb-modal__value" id="adminFbDetailApi">—</div>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">提交者</div>
                <div class="fb-modal__value" id="adminFbDetailUser">—</div>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">反馈内容</div>
                <div class="fb-modal__content" id="adminFbDetailContent"></div>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">处理回复</div>
                <textarea class="vs-input vs-textarea" id="adminFbDetailReply" placeholder="请输入处理回复内容..." rows="4"></textarea>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">关闭</button>
            <button type="button" class="vs-btn vs-btn--outline-danger" id="adminFbDetailDeleteBtn">删除</button>
            <button type="button" class="vs-btn vs-btn--outline" id="adminFbDetailSaveBtn">保存回复</button>
            <button type="button" class="vs-btn vs-btn--primary" id="adminFbDetailMarkBtn">标记已处理</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php
vs_admin_layout_end($tableReady ? array('vs-pick.js', 'api-feedback.js') : array());