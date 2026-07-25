<?php
/**
 * 文件：admin/content/comments.php
 * 作用：文章评论管理（双 DOM；回复 / 置顶 / 审核 / 删除；邮箱必填）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!CommentManager::tableReady()) {
        AjaxResponse::error('评论功能尚未就绪，请先执行数据库结构更新');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

    if ($action === 'create') {
        $result = CommentManager::create(array(
            'contentid' => isset($_POST['contentid']) ? (int) $_POST['contentid'] : 0,
            'nickname'  => isset($_POST['nickname']) ? (string) $_POST['nickname'] : '',
            'email'     => isset($_POST['email']) ? (string) $_POST['email'] : '',
            'body'      => isset($_POST['body']) ? (string) $_POST['body'] : '',
            'userid'    => 0,
            'status'    => CommentManager::STATUS_APPROVED,
        ));
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('评论已添加', array('comment' => $result));
    }

    if ($action === 'set_reply') {
        $reply = isset($_POST['reply']) ? (string) $_POST['reply'] : '';
        $result = CommentManager::setReply($id, $reply);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = CommentManager::formatRow(CommentManager::findById($id));
        AjaxResponse::success('回复已保存', array('comment' => $row));
    }

    if ($action === 'set_pinned') {
        $flag = isset($_POST['ispinned']) ? (int) $_POST['ispinned'] : 0;
        $result = CommentManager::setPinned($id, $flag);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success($flag ? '已置顶' : '已取消置顶', array(
            'comment_id' => $id,
            'ispinned'   => CommentManager::normalizeFlag($flag),
        ));
    }

    if ($action === 'set_status') {
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        if (!in_array($status, array(
            CommentManager::STATUS_PENDING,
            CommentManager::STATUS_APPROVED,
            CommentManager::STATUS_REJECTED,
        ), true)) {
            AjaxResponse::error('无效状态');
        }
        $result = CommentManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('状态已更新', array(
            'comment_id'   => $id,
            'status'       => $status,
            'status_label' => CommentManager::statusLabel($status),
        ));
    }

    if ($action === 'delete') {
        $result = CommentManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('评论已删除', array('comment_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = CommentManager::tableReady();
$rows = $tableReady ? CommentManager::listAll() : array();
$articles = ContentManager::tableReady()
    ? ContentManager::listAll(ContentManager::KIND_ARTICLE)
    : array();

/**
 * @param array $fb
 * @return array
 */
function vs_admin_cmt_ctx(array $fb)
{
    $id = (int) $fb['id'];
    $search = mb_strtolower(
        $fb['body'] . ' ' . $fb['nickname'] . ' ' . $fb['email'] . ' '
        . $fb['content_title'] . ' #' . $id,
        'UTF-8'
    );
    return array_merge($fb, array('search' => $search));
}

/**
 * @param array $ctx
 * @return string
 */
function vs_admin_cmt_attrs(array $ctx)
{
    return ' data-comment-row="' . (int) $ctx['id'] . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"'
        . ' data-contentid="' . (int) $ctx['contentid'] . '"'
        . ' data-content-title="' . vs_e($ctx['content_title']) . '"'
        . ' data-nickname="' . vs_e($ctx['nickname']) . '"'
        . ' data-email="' . vs_e($ctx['email']) . '"'
        . ' data-body="' . vs_e($ctx['body']) . '"'
        . ' data-reply="' . vs_e($ctx['reply']) . '"'
        . ' data-ispinned="' . (int) $ctx['ispinned'] . '"'
        . ' data-status="' . (int) $ctx['status'] . '"'
        . ' data-status-label="' . vs_e($ctx['status_label']) . '"'
        . ' data-avatar-url="' . vs_e($ctx['avatar_url']) . '"'
        . ' data-createtime="' . vs_e($ctx['createtime_short']) . '"';
}

/**
 * @param array $ctx
 * @return string
 */
function vs_admin_cmt_actions_html(array $ctx)
{
    $id = (int) $ctx['id'];
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cmt-act" data-act="reply" data-comment-id="'
        . $id . '">回复</button>';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cmt-act" data-act="pin" data-comment-id="'
        . $id . '">' . ((int) $ctx['ispinned'] === 1 ? '取消置顶' : '置顶') . '</button>';
    if ((int) $ctx['status'] === CommentManager::STATUS_PENDING) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-cmt-act" data-act="approve" data-comment-id="'
            . $id . '">通过</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-cmt-act" data-act="reject" data-comment-id="'
            . $id . '">拒绝</button>';
    }
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-cmt-act" data-act="delete" data-comment-id="'
        . $id . '">删除</button>';
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_cmt_desktop_row(array $ctx)
{
    $attrs = vs_admin_cmt_attrs($ctx);
    $statusClass = 'vs-badge--warning';
    if ((int) $ctx['status'] === CommentManager::STATUS_APPROVED) {
        $statusClass = 'vs-badge--success';
    } elseif ((int) $ctx['status'] === CommentManager::STATUS_REJECTED) {
        $statusClass = 'vs-badge--danger';
    }
    ?>
    <tr<?php echo $attrs; ?>>
        <td>
            <div class="cmt-body-cell">
                <?php if ((int) $ctx['ispinned'] === 1): ?>
                    <span class="cmt-pin-mark" title="置顶">[顶]</span>
                <?php endif; ?>
                <span data-field="body"><?php echo vs_e($ctx['body']); ?></span>
            </div>
        </td>
        <td><span class="cmt-target-cell" data-field="content_title"><?php echo vs_e($ctx['content_title'] !== '' ? $ctx['content_title'] : ('文章#' . $ctx['contentid'])); ?></span></td>
        <td>
            <div class="cmt-author-cell">
                <?php if ($ctx['avatar_url'] !== ''): ?>
                    <img class="cmt-author-cell__avatar" src="<?php echo vs_e($ctx['avatar_url']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="avatar_url">
                <?php endif; ?>
                <div class="cmt-author-cell__meta">
                    <span class="cmt-author-cell__name" data-field="nickname"><?php echo vs_e($ctx['nickname']); ?></span>
                    <span class="cmt-author-cell__email" data-field="email"><?php echo vs_e($ctx['email']); ?></span>
                </div>
            </div>
        </td>
        <td><span data-field="createtime"><?php echo vs_e($ctx['createtime_short'] !== '' ? $ctx['createtime_short'] : '—'); ?></span></td>
        <td><span class="vs-badge <?php echo $statusClass; ?>" data-field="status_label"><?php echo vs_e($ctx['status_label']); ?></span></td>
        <td class="vs-cmt-actions-cell" data-field="actions"><?php echo vs_admin_cmt_actions_html($ctx); ?></td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_cmt_mobile_card(array $ctx)
{
    $attrs = vs_admin_cmt_attrs($ctx);
    $prefix = ((int) $ctx['ispinned'] === 1) ? '[顶] ' : '';
    ?>
    <div class="cmt-card"<?php echo $attrs; ?>>
        <div class="cmt-card__text" data-field="body"><?php echo vs_e($prefix . $ctx['body']); ?></div>
        <div class="cmt-card__meta">
            <span class="cmt-card__meta-item">关联：<span data-field="content_title"><?php echo vs_e($ctx['content_title'] !== '' ? $ctx['content_title'] : ('文章#' . $ctx['contentid'])); ?></span></span>
            <span class="cmt-card__meta-item" data-field="nickname"><?php echo vs_e($ctx['nickname']); ?></span>
            <span class="cmt-card__meta-item" data-field="email"><?php echo vs_e($ctx['email']); ?></span>
            <span class="cmt-card__meta-item" data-field="createtime"><?php echo vs_e($ctx['createtime_short']); ?></span>
            <span class="vs-badge vs-badge--default" data-field="status_label"><?php echo vs_e($ctx['status_label']); ?></span>
        </div>
        <div class="cmt-card__actions" data-field="actions"><?php echo vs_admin_cmt_actions_html($ctx); ?></div>
    </div>
    <?php
}

$hasItems = count($rows) > 0;
$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="adminCmtSearchInput" placeholder="搜索评论内容、邮箱或文章..." autocomplete="off">
        </div>
        <button type="button" class="vs-btn vs-btn--primary" id="adminCmtAddBtn">添加评论</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('评论管理', 'comments', $headerActions);
?>

<div id="adminCommentsPage">
<?php if (!$tableReady): ?>
    <div class="vs-panel">
        <?php vs_render_notice('warning', '', '评论功能尚未就绪，请前往「系统管理 → 系统升级」完成更新。', array('compact' => true)); ?>
    </div>
<?php else: ?>
    <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminCmtEmpty"<?php echo $hasItems ? ' hidden' : ''; ?>>
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无评论</h3>
            <p class="vs-api-list-empty__desc">用户提交或管理员添加的评论将显示在此。评论须填写邮箱。</p>
        </div>
    </div>
    <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminCmtSearchEmpty" hidden>
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
            <p class="vs-api-list-empty__desc">当前搜索下没有评论，可清空关键词重试。</p>
        </div>
    </div>

    <div class="vs-api-list-table-card vs-api-list-table-wrap" id="adminCmtTableWrap"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <div class="vs-table-responsive">
            <table class="vs-table">
                <thead>
                    <tr>
                        <th>评论内容</th>
                        <th>关联文章</th>
                        <th>评论者</th>
                        <th>时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="adminCmtBody">
                    <?php foreach ($rows as $row): ?>
                        <?php vs_render_cmt_desktop_row(vs_admin_cmt_ctx($row)); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mobile-cmt-cards" id="adminCmtMobile"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <?php foreach ($rows as $row): ?>
            <?php vs_render_cmt_mobile_card(vs_admin_cmt_ctx($row)); ?>
        <?php endforeach; ?>
    </div>

    <div class="vs-api-list-footer" id="adminCmtFooter"<?php echo $hasItems ? '' : ' hidden'; ?>>
        <div class="vs-api-pager">
            <label class="vs-api-list-pagesize" for="adminCmtPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select vs-api-list-pagesize__select" id="adminCmtPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <div class="vs-api-pager__navs">
                <button type="button" class="vs-api-pager__nav" id="adminCmtPrevBtn">上一页</button>
                <div class="vs-api-pager__nums" id="adminCmtPagerNums"></div>
                <button type="button" class="vs-api-pager__nav" id="adminCmtNextBtn">下一页</button>
            </div>
        </div>
        <p class="vs-api-list-stats" id="adminCmtStats">共 <?php echo (int) count($rows); ?> 条</p>
    </div>
<?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--form" id="adminCmtReplyOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="adminCmtReplyTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="adminCmtReplyTitle">回复评论</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <input type="hidden" id="adminCmtReplyId" value="0">
            <div class="fb-modal__field">
                <div class="fb-modal__label">评论内容</div>
                <div class="fb-modal__content" id="adminCmtReplyBodyView">—</div>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">评论者邮箱</div>
                <div class="fb-modal__value" id="adminCmtReplyEmailView">—</div>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="adminCmtReplyText">管理员回复</label>
                <textarea class="vs-input vs-textarea" id="adminCmtReplyText" rows="4" maxlength="2000" placeholder="填写回复内容..."></textarea>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="adminCmtReplySaveBtn">保存回复</button>
        </footer>
    </div>
</div>

<div class="vs-overlay vs-overlay--form" id="adminCmtAddOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="adminCmtAddTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="adminCmtAddTitle">添加评论</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form class="vs-overlay__body" id="adminCmtAddForm" autocomplete="off">
            <div class="vs-field">
                <label class="vs-label" for="adminCmtAddArticle">关联文章</label>
                <select class="vs-input vs-select" id="adminCmtAddArticle" name="contentid" data-vs-pick required>
                    <option value="">请选择文章</option>
                    <?php foreach ($articles as $art): ?>
                        <option value="<?php echo (int) $art['id']; ?>"><?php echo vs_e($art['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="adminCmtAddNickname">昵称（选填）</label>
                <input class="vs-input" type="text" id="adminCmtAddNickname" name="nickname" maxlength="50">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="adminCmtAddEmail">邮箱（必填）</label>
                <input class="vs-input" type="email" id="adminCmtAddEmail" name="email" maxlength="100" required placeholder="user@example.com">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="adminCmtAddBody">评论内容</label>
                <textarea class="vs-input vs-textarea" id="adminCmtAddBody" name="body" rows="4" maxlength="2000" required></textarea>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="adminCmtAddForm" class="vs-btn vs-btn--primary" id="adminCmtAddSaveBtn">添加</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php
vs_admin_layout_end($tableReady ? array('vs-pick.js', 'admin-comments.js') : array());
