<?php
/**
 * 文件：admin/api/review.php
 * 作用：接口审核（0 审核不通过 / 1 审核通过）
 *
 * 说明：管理员在「接口列表」发布的接口默认审核通过；
 * 本页用于查看与调整审核状态（用户投稿上线后也将走此流程）。
 * 筛选用页面内按钮 + DOM 显隐，不改 URL。
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'set_audit') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $audit = ApiManager::normalizeAuditStatus(isset($_POST['audit_status']) ? $_POST['audit_status'] : '');
        $result = ApiManager::setAuditStatus($id, $audit);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('审核状态已更新', array(
            'api_id'             => $id,
            'audit_status'       => $audit,
            'audit_status_label' => ApiManager::auditStatusLabel($audit),
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiManager::tableReady();
$hasAudit = $tableReady && ApiManager::hasAuditColumn();
$apis = $hasAudit ? ApiManager::listAll() : array();

$headerActions = '';
if ($hasAudit) {
    ob_start();
    ?>
    <div class="vs-api-review-filters" id="apiReviewFilters">
        <button type="button" class="vs-btn vs-btn--primary vs-api-review-filter" data-filter="all">全部</button>
        <button type="button" class="vs-btn vs-btn--default vs-api-review-filter" data-filter="1">已通过</button>
        <button type="button" class="vs-btn vs-btn--default vs-api-review-filter" data-filter="0">未通过</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口审核', 'api-review', $headerActions);
?>

<div class="vs-panel vs-api-review-panel" id="apiReviewPage">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '接口数据表未就绪，请先执行数据库结构更新。', array('compact' => true)); ?>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
    <?php elseif (!$hasAudit): ?>
        <?php vs_render_notice('warning', '', '尚未包含审核字段，请执行数据库结构更新至 3.8.0。', array('compact' => true)); ?>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
    <?php else: ?>
        <?php vs_render_notice('info', '', '审核状态：0=审核不通过（前台不展示），1=审核通过。管理员在接口列表发布的接口默认通过。', array('compact' => true)); ?>

        <div class="vs-api-list-empty" id="apiReviewEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <p>暂无接口。</p>
        </div>
        <div class="vs-api-list-empty" id="apiReviewFilterEmpty" hidden>
            <p>当前筛选下暂无接口。</p>
        </div>

        <?php if (count($apis) > 0): ?>
            <div class="vs-api-review-table" id="apiReviewTable">
                <div class="vs-api-review-table__head">
                    <span>接口</span>
                    <span>状态</span>
                    <span>审核</span>
                    <span>操作</span>
                </div>
                <?php foreach ($apis as $row): ?>
                    <?php
                    $api = ApiManager::formatRowSummary($row);
                    if (!$api) {
                        continue;
                    }
                    $audit = (int) $api['audit_status'];
                    ?>
                    <div class="vs-api-review-row" data-api-id="<?php echo (int) $api['id']; ?>" data-audit="<?php echo $audit; ?>">
                        <div class="vs-api-review-row__main">
                            <strong><?php echo vs_e($api['name']); ?></strong>
                            <span class="vs-api-review-row__meta"><?php echo vs_e($api['endpoint']); ?></span>
                            <?php if (!empty($api['username'])): ?>
                                <span class="vs-api-review-row__meta">创建者：<?php echo vs_e($api['username']); ?>（UID <?php echo (int) $api['user_id']; ?>）</span>
                            <?php elseif ((int) $api['user_id'] > 0): ?>
                                <span class="vs-api-review-row__meta">创建者 UID：<?php echo (int) $api['user_id']; ?></span>
                            <?php else: ?>
                                <span class="vs-api-review-row__meta">创建者：未绑定前台用户</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="vs-api-list-status <?php echo $api['status'] === ApiManager::STATUS_DISABLED ? 'is-disabled' : ($api['status'] === ApiManager::STATUS_MAINTENANCE ? 'is-maintenance' : 'is-normal'); ?>">
                                <?php echo vs_e($api['status_label']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="vs-api-list-audit <?php echo $audit === ApiManager::AUDIT_APPROVED ? 'is-approved' : 'is-rejected'; ?>" data-field="audit_label">
                                <?php echo vs_e($api['audit_status_label']); ?>
                            </span>
                        </div>
                        <div class="vs-api-review-row__actions">
                            <?php if ($audit !== ApiManager::AUDIT_APPROVED): ?>
                                <button type="button" class="vs-btn vs-btn--primary vs-api-review-action"
                                        data-audit="1">通过</button>
                            <?php endif; ?>
                            <?php if ($audit !== ApiManager::AUDIT_REJECTED): ?>
                                <button type="button" class="vs-btn vs-btn--danger vs-api-review-action"
                                        data-audit="0">不通过</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.vs-api-review-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.vs-api-review-table { margin-top: 12px; border: 1px solid var(--vs-border, #e2e8f0); border-radius: 12px; overflow: hidden; }
.vs-api-review-table__head,
.vs-api-review-row {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) 90px 110px 160px;
    gap: 12px;
    align-items: center;
    padding: 12px 16px;
}
.vs-api-review-table__head {
    background: #f8fafc;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.vs-api-review-row { border-top: 1px solid var(--vs-border, #e2e8f0); }
.vs-api-review-row__main { display: flex; flex-direction: column; gap: 4px; }
.vs-api-review-row__meta { font-size: 12px; color: #64748b; word-break: break-all; }
.vs-api-review-row__actions { display: flex; gap: 8px; flex-wrap: wrap; }
@media (max-width: 768px) {
    .vs-api-review-table__head { display: none; }
    .vs-api-review-row { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
    var page = document.getElementById('apiReviewPage');
    if (!page || !window.VS) { return; }

    var table = document.getElementById('apiReviewTable');
    var emptyAll = document.getElementById('apiReviewEmpty');
    var emptyFilter = document.getElementById('apiReviewFilterEmpty');
    var currentFilter = 'all';

    function applyFilter(filter) {
        currentFilter = filter;
        document.querySelectorAll('.vs-api-review-filter').forEach(function (btn) {
            var on = btn.getAttribute('data-filter') === filter;
            btn.classList.toggle('vs-btn--primary', on);
            btn.classList.toggle('vs-btn--default', !on);
        });
        if (!table) { return; }
        var visible = 0;
        var total = 0;
        table.querySelectorAll('.vs-api-review-row').forEach(function (row) {
            total += 1;
            var audit = row.getAttribute('data-audit');
            var show = filter === 'all' || audit === filter;
            row.hidden = !show;
            if (show) { visible += 1; }
        });
        if (emptyFilter) {
            emptyFilter.hidden = !(total > 0 && visible === 0);
        }
        table.hidden = total > 0 && visible === 0;
        if (emptyAll) {
            emptyAll.hidden = total > 0;
        }
    }

    document.addEventListener('click', function (e) {
        var filterBtn = e.target.closest('.vs-api-review-filter');
        if (filterBtn) {
            applyFilter(filterBtn.getAttribute('data-filter') || 'all');
            return;
        }

        var btn = e.target.closest('.vs-api-review-action');
        if (!btn || !page.contains(btn)) { return; }
        var row = btn.closest('.vs-api-review-row');
        if (!row) { return; }
        var apiId = row.getAttribute('data-api-id');
        var audit = btn.getAttribute('data-audit');
        var fd = new FormData();
        fd.append('action', 'set_audit');
        fd.append('api_id', apiId);
        fd.append('audit_status', audit);
        window.VS.postForm(fd, window.location.pathname).then(function (data) {
            if (!data || data.code !== 1) {
                window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                return;
            }
            window.VS.showMessage(data.msg || '已更新', 'success');
            row.setAttribute('data-audit', String(audit));
            var label = row.querySelector('[data-field="audit_label"]');
            if (label) {
                label.textContent = data.audit_status_label || (audit === '1' ? '审核通过' : '审核不通过');
                label.className = 'vs-api-list-audit ' + (audit === '1' ? 'is-approved' : 'is-rejected');
            }
            var actions = row.querySelector('.vs-api-review-row__actions');
            if (actions) {
                var html = '';
                if (audit !== '1') {
                    html += '<button type="button" class="vs-btn vs-btn--primary vs-api-review-action" data-audit="1">通过</button>';
                }
                if (audit !== '0') {
                    html += '<button type="button" class="vs-btn vs-btn--danger vs-api-review-action" data-audit="0">不通过</button>';
                }
                actions.innerHTML = html;
            }
            applyFilter(currentFilter);
        }).catch(function () {
            window.VS.showMessage('网络异常，请稍后重试', 'error');
        });
    });
})();
</script>

<?php
vs_admin_layout_end();
