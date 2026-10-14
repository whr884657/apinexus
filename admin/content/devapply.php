<?php
/**
 * 文件：admin/content/devapply.php
 * 作用：普通用户 → 开发者申请审核（feedback.kind=1；Tab：待审核 / 已通过 / 已拒绝）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'list') {
        $tab = isset($_POST['tab']) ? (string) $_POST['tab'] : 'pending';
        if ($tab !== 'pending' && $tab !== 'approved' && $tab !== 'rejected') {
            $tab = 'pending';
        }
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
        $paged = ApiFeedbackManager::listDevApplyPaged(array(
            'tab'      => $tab,
            'page'     => $page,
            'pagesize' => $pagesize,
        ));
        AjaxResponse::success('ok', array(
            'list'     => $paged['list'],
            'total'    => $paged['total'],
            'page'     => $paged['page'],
            'pagesize' => $paged['pagesize'],
            'counts'   => $paged['counts'],
        ));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        if ($id <= 0 && isset($_POST['feedback_id'])) {
            $id = (int) $_POST['feedback_id'];
        }
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        if (!in_array($status, array(
            ApiFeedbackManager::STATUS_APPROVED,
            ApiFeedbackManager::STATUS_REJECTED,
        ), true)) {
            AjaxResponse::error('无效状态');
        }
        $replyArg = null;
        if (array_key_exists('reply', $_POST)) {
            $replyArg = (string) $_POST['reply'];
        }
        $result = ApiFeedbackManager::setDevApplyStatus($id, $status, $replyArg);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $label = ApiFeedbackManager::statusLabel($status, ApiFeedbackManager::KIND_DEVAPPLY);
        $row = ApiFeedbackManager::formatDevApplyRow(
            ApiFeedbackManager::findById($id) ?: array('id' => $id, 'status' => $status)
        );
        AjaxResponse::success($label, array(
            'link_id'      => $id,
            'status'       => $status,
            'status_label' => $label,
            'apply'        => $row,
        ));
    }

    if ($action === 'delete') {
        AjaxResponse::error('不支持删除开发者申请，请使用通过/拒绝处理');
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiFeedbackManager::tableReady() && ApiFeedbackManager::hasKindColumn();
$paged = $tableReady ? ApiFeedbackManager::listDevApplyPaged(array(
    'tab'      => 'pending',
    'page'     => 1,
    'pagesize' => 20,
)) : array(
    'list'   => array(),
    'total'  => 0,
    'counts' => array('pending' => 0, 'approved' => 0, 'rejected' => 0),
);
$items = $paged['list'];
$counts = $paged['counts'];

/**
 * @return void
 */
function vs_render_devapply_table_head()
{
    ?>
    <div class="vs-link-row vs-link-row--head" aria-hidden="true">
        <div class="vs-link-cell">用户</div>
        <div class="vs-link-cell">联系 / 时间</div>
        <div class="vs-link-cell">申请理由</div>
        <div class="vs-link-cell">状态</div>
        <div class="vs-link-cell">操作</div>
    </div>
    <?php
}

/**
 * @param array $row formatDevApplyRow
 * @return void
 */
function vs_render_devapply_item(array $row)
{
    $id = (int) $row['id'];
    $status = (int) $row['status'];
    $userid = isset($row['userid']) ? (int) $row['userid'] : 0;
    $name = isset($row['name']) ? (string) $row['name'] : '';
    if ($name === '' && isset($row['username'])) {
        $name = (string) $row['username'];
    }
    $contact = isset($row['contact']) ? (string) $row['contact'] : '';
    if ($contact === '' && isset($row['email'])) {
        $contact = (string) $row['email'];
    }
    $desc = isset($row['description']) ? (string) $row['description'] : '';
    if ($desc === '' && isset($row['content'])) {
        $desc = (string) $row['content'];
    }
    $reply = isset($row['reply']) ? (string) $row['reply'] : '';
    $avatarUrl = isset($row['avatar_url']) ? (string) $row['avatar_url'] : '';
    $label = (string) $row['status_label'];
    $time = isset($row['createtime']) ? (string) $row['createtime'] : '';
    if ($time !== '' && strlen($time) >= 16) {
        $time = substr($time, 0, 16);
    }
    $statusClass = 'is-pending';
    if ($status === ApiFeedbackManager::STATUS_APPROVED) {
        $statusClass = 'is-on';
    } elseif ($status === ApiFeedbackManager::STATUS_REJECTED) {
        $statusClass = 'is-off';
    }
    $tab = 'pending';
    if ($status === ApiFeedbackManager::STATUS_APPROVED) {
        $tab = 'approved';
    } elseif ($status === ApiFeedbackManager::STATUS_REJECTED) {
        $tab = 'rejected';
    }
    $displayName = $name !== '' ? $name : ('用户 #' . $userid);
    $initial = function_exists('mb_substr')
        ? mb_substr($displayName, 0, 1, 'UTF-8')
        : substr($displayName, 0, 1);
    if ($initial === '') {
        $initial = 'U';
    }
    $metaParts = array('ID ' . $userid);
    if ($contact !== '') {
        $metaParts[] = $contact;
    }
    if ($time !== '') {
        $metaParts[] = $time;
    }
    $meta = implode(' · ', $metaParts);
    ?>
    <div class="vs-link-row"
         data-link-row="<?php echo $id; ?>"
         data-link-tab="<?php echo vs_e($tab); ?>"
         data-link-status="<?php echo $status; ?>"
         data-username="<?php echo vs_e($displayName); ?>"
         data-email="<?php echo vs_e($contact); ?>"
         data-content="<?php echo vs_e($desc); ?>"
         data-reply="<?php echo vs_e($reply); ?>"
         data-time="<?php echo vs_e($time); ?>"
         data-avatar-url="<?php echo vs_e($avatarUrl); ?>">
        <div class="vs-link-cell vs-link-c-site">
            <div class="vs-link-row__icon">
                <?php if ($avatarUrl !== ''): ?>
                    <img src="<?php echo vs_e($avatarUrl); ?>" alt="" width="36" height="36" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">
                <?php else: ?>
                    <span class="vs-link-row__initial"><?php echo vs_e($initial); ?></span>
                <?php endif; ?>
            </div>
            <div class="vs-link-row__name"><?php echo vs_e($displayName); ?></div>
        </div>
        <div class="vs-link-cell vs-link-c-url">
            <div class="vs-link-row__url"><?php echo vs_e($meta); ?></div>
        </div>
        <div class="vs-link-cell vs-link-c-desc">
            <div class="vs-link-row__desc vs-link-row__desc--box"><?php echo $desc !== '' ? vs_e($desc) : '—'; ?></div>
        </div>
        <div class="vs-link-cell vs-link-c-status">
            <span class="vs-link-status <?php echo vs_e($statusClass); ?>"><?php echo vs_e($label); ?></span>
        </div>
        <div class="vs-link-cell vs-link-c-actions">
            <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-dev-action="view" data-link-id="<?php echo $id; ?>">查看</button>
            <?php if ($status === ApiFeedbackManager::STATUS_PENDING): ?>
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success" data-dev-action="approve" data-link-id="<?php echo $id; ?>">通过</button>
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-dev-action="reject" data-link-id="<?php echo $id; ?>">拒绝</button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

vs_admin_layout_start('开发审核', 'devapply');
?>

<div class="vs-link-page" id="adminDevApplyPage" data-active-tab="pending">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '开发者申请功能尚未就绪，请前往「系统管理 → 系统升级」完成数据库结构更新。', array('compact' => true)); ?>
    <?php else: ?>
        <?php /* 仅 Tab 条小白板；电脑端表格对齐积分变动；禁整页 .vs-panel（E406/E425/E427） */ ?>
        <div class="vs-link-page__tabs-shell">
            <div class="vs-tabs vs-api-review-tabs" id="devApplyFilters" role="tablist" aria-label="开发审核筛选">
                <button type="button" class="vs-tabs__btn vs-dev-filter is-active" data-filter="pending" role="tab" aria-selected="true">
                    待审核<span class="vs-badge vs-badge--warning vs-api-review-tabs__badge" data-count="pending"><?php echo (int) $counts['pending']; ?></span>
                </button>
                <button type="button" class="vs-tabs__btn vs-dev-filter" data-filter="approved" role="tab" aria-selected="false">
                    已通过<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="approved"><?php echo (int) $counts['approved']; ?></span>
                </button>
                <button type="button" class="vs-tabs__btn vs-dev-filter" data-filter="rejected" role="tab" aria-selected="false">
                    已拒绝<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="rejected"><?php echo (int) $counts['rejected']; ?></span>
                </button>
            </div>
        </div>

        <?php vs_render_notice('tip', '', '通过后将用户设为开发者；拒绝不会取消其已有的开发者身份（请到「用户管理」改角色）。申请记录不支持删除；已通过/已拒绝仅可查看。查看时可填写处理回复（选填）。', array('compact' => true)); ?>
        <div class="vs-link-empty" id="devApplyEmpty"<?php echo $counts['pending'] > 0 ? ' hidden' : ''; ?>>
            <?php vs_render_notice('info', '', '当前 Tab 暂无申请。普通用户可在用户中心「账号设置 → 开发者申请」提交。', array('compact' => true)); ?>
        </div>
        <div class="vs-link-list" id="devApplyList"<?php echo count($items) === 0 ? ' hidden' : ''; ?>>
            <?php vs_render_devapply_table_head(); ?>
            <?php foreach ($items as $row): ?>
                <?php vs_render_devapply_item($row); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($tableReady): ?>
<?php vs_render_list_pager(array('id' => 'devApply', 'hidden' => $counts['pending'] === 0, 'total' => $counts['pending'])); ?>

<div class="vs-overlay vs-overlay--lg" id="adminDevApplyDetailOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="adminDevApplyDetailTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="adminDevApplyDetailTitle">申请详情</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <input type="hidden" id="adminDevApplyDetailId" value="">
            <div class="fb-modal__meta">
                <span class="vs-badge vs-badge--warning" id="adminDevApplyDetailStatus">待审核</span>
                <span class="fb-modal__id" id="adminDevApplyDetailIdLabel"></span>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">申请人</div>
                <div class="fb-modal__value" id="adminDevApplyDetailUser">—</div>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">注册邮箱</div>
                <div class="fb-modal__value" id="adminDevApplyDetailEmail">—</div>
            </div>
            <div class="fb-modal__field">
                <div class="fb-modal__label">申请理由</div>
                <div class="fb-modal__content" id="adminDevApplyDetailContent"></div>
            </div>
            <div class="fb-modal__field" id="adminDevApplyDetailReplyEditWrap">
                <div class="fb-modal__label">处理回复（选填）</div>
                <textarea class="vs-input vs-textarea" id="adminDevApplyDetailReply" placeholder="可填写处理说明，点击「通过」或「拒绝」一并发送给用户；也可留空直接审核..." rows="4"></textarea>
            </div>
            <div class="fb-modal__field" id="adminDevApplyDetailReplyViewWrap" hidden>
                <div class="fb-modal__label">处理回复</div>
                <div class="fb-modal__content" id="adminDevApplyDetailReplyView">—</div>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">关闭</button>
            <button type="button" class="vs-btn vs-btn--outline" id="adminDevApplyDetailRejectBtn">拒绝</button>
            <button type="button" class="vs-btn vs-btn--primary" id="adminDevApplyDetailApproveBtn">通过</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php
vs_admin_layout_end(array('vs-pick.js', 'admin-devapply.js'));
