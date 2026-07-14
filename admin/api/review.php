<?php
/**
 * 文件：admin/api/review.php
 * 作用：接口审核（待审 / 通过 / 不通过；可选填写不通过原因；邮件通知投稿用户）
 *
 * 说明：管理员在「接口列表」发布的接口默认审核通过；
 * 开发者在用户中心提交的接口为待审核。筛选用页面内按钮，不改 URL。
 * 数值编码仅存在于服务端常量与库表，勿写入页面可见文案。
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'set_audit') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $audit = ApiManager::normalizeAuditStatus(isset($_POST['audit']) ? $_POST['audit'] : '');
        $reason = isset($_POST['rejectreason']) ? (string) $_POST['rejectreason'] : '';

        if ($audit === ApiManager::AUDIT_PENDING) {
            AjaxResponse::error('请选择通过或不通过');
        }

        $before = ApiManager::findById($id);
        if (!$before) {
            AjaxResponse::error('接口不存在');
        }

        $result = ApiManager::setAuditStatus($id, $audit, $reason);
        if ($result !== true) {
            AjaxResponse::error($result);
        }

        $row = ApiManager::findById($id);
        $formatted = ApiManager::formatRow($row);
        $mailNote = '';
        if ($formatted && (int) $formatted['userid'] > 0) {
            $mail = ApiNotify::notifyUserAuditResult(
                $formatted,
                $audit,
                isset($formatted['rejectreason']) ? $formatted['rejectreason'] : ''
            );
            if (!$mail['ok']) {
                $mailNote = $mail['error'] !== '' ? ('（邮件未送达：' . $mail['error'] . '）') : '（邮件未送达）';
            }
        }

        AjaxResponse::success('审核状态已更新' . $mailNote, array(
            'api_id'       => $id,
            'audit'        => $audit,
            'audit_label'  => ApiManager::auditStatusLabel($audit),
            'audit_class'  => ApiManager::auditStatusClass($audit),
            'rejectreason' => isset($formatted['rejectreason']) ? $formatted['rejectreason'] : '',
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
        <button type="button" class="vs-btn vs-btn--default vs-api-review-filter" data-filter="0">待审核</button>
        <button type="button" class="vs-btn vs-btn--default vs-api-review-filter" data-filter="1">已通过</button>
        <button type="button" class="vs-btn vs-btn--default vs-api-review-filter" data-filter="2">未通过</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口审核', 'api-review', $headerActions);
?>

<div class="vs-panel vs-api-review-panel" id="apiReviewPage">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '接口管理功能尚未就绪，请先前往「系统升级」完成更新。', array('compact' => true)); ?>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
    <?php elseif (!$hasAudit): ?>
        <?php vs_render_notice('warning', '', '当前系统尚未具备审核功能，请先前往「系统升级」完成结构更新。', array('compact' => true)); ?>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
    <?php else: ?>
        <?php vs_render_notice('info', '', '可在此处理开发者提交的接口。未通过与待审核的接口不会出现在站点前台；在「接口列表」由管理员直接发布时默认审核通过。不通过时可填写原因（选填），系统将邮件通知投稿用户。', array('compact' => true)); ?>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiReviewEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">还没有可审核的接口。开发者在用户中心提交后，会出现在本页。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiReviewFilterEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前筛选项下没有接口，可切换上方筛选查看其它状态。</p>
            </div>
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
                    $audit = (int) $api['audit'];
                    $reason = isset($api['rejectreason']) ? (string) $api['rejectreason'] : '';
                    ?>
                    <div class="vs-api-review-row" data-api-id="<?php echo (int) $api['id']; ?>" data-audit="<?php echo $audit; ?>">
                        <div class="vs-api-review-row__main">
                            <strong><?php echo vs_e($api['name']); ?></strong>
                            <span class="vs-api-review-row__meta"><?php echo vs_e($api['endpoint']); ?></span>
                            <?php if (!empty($api['username'])): ?>
                                <span class="vs-api-review-row__meta">创建者：<?php echo vs_e($api['username']); ?></span>
                            <?php elseif ((int) $api['userid'] > 0): ?>
                                <span class="vs-api-review-row__meta">创建者：已关联前台用户</span>
                            <?php else: ?>
                                <span class="vs-api-review-row__meta">创建者：未绑定前台用户</span>
                            <?php endif; ?>
                            <span class="vs-api-review-row__reason" data-field="rejectreason"<?php echo $reason === '' ? ' hidden' : ''; ?>>
                                原因：<?php echo vs_e($reason); ?>
                            </span>
                        </div>
                        <div>
                            <span class="vs-api-list-status <?php echo $api['status'] === ApiManager::STATUS_DISABLED ? 'is-disabled' : ($api['status'] === ApiManager::STATUS_MAINTENANCE ? 'is-maintenance' : 'is-normal'); ?>">
                                <?php echo vs_e($api['status_label']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="vs-api-list-audit <?php echo vs_e($api['audit_class']); ?>" data-field="audit_label">
                                <?php echo vs_e($api['audit_label']); ?>
                            </span>
                        </div>
                        <div class="vs-api-review-row__actions">
                            <?php if ($audit !== ApiManager::AUDIT_APPROVED): ?>
                                <button type="button" class="vs-btn vs-btn--primary vs-api-review-action"
                                        data-audit="1">通过</button>
                            <?php endif; ?>
                            <?php if ($audit !== ApiManager::AUDIT_REJECTED): ?>
                                <button type="button" class="vs-btn vs-btn--danger vs-api-review-reject"
                                        data-audit="2">不通过</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="vs-overlay" id="apiReviewRejectOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="apiReviewRejectTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="apiReviewRejectTitle">审核不通过</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body vs-form">
            <p class="vs-form-hint" style="margin-top:0;">可填写不通过原因（选填），将通过邮件告知投稿用户。</p>
            <div class="vs-form-row">
                <label class="vs-label" for="apiReviewRejectReason">原因说明</label>
                <textarea class="vs-input vs-textarea" id="apiReviewRejectReason" rows="4" maxlength="500"
                          placeholder="例如：接口地址无法访问、文档不完整…"></textarea>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--danger" id="apiReviewRejectConfirm">确认不通过</button>
        </footer>
    </div>
</div>

<style>
.vs-api-review-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.vs-api-review-table { margin-top: 12px; border: 1px solid var(--vs-border, #e2e8f0); border-radius: 12px; overflow: hidden; }
.vs-api-review-table__head,
.vs-api-review-row {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) 90px 110px 180px;
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
.vs-api-review-row__reason { font-size: 12px; color: #b45309; word-break: break-word; }
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
    var overlay = document.getElementById('apiReviewRejectOverlay');
    var reasonInput = document.getElementById('apiReviewRejectReason');
    var confirmBtn = document.getElementById('apiReviewRejectConfirm');
    var currentFilter = 'all';
    var rejectApiId = '';

    function openOverlay() {
        if (!overlay) { return; }
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        if (reasonInput) {
            reasonInput.value = '';
            reasonInput.focus();
        }
    }

    function closeOverlay() {
        if (!overlay) { return; }
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        rejectApiId = '';
    }

    function applyFilter(filter) {
        currentFilter = filter;
        document.querySelectorAll('.vs-api-review-filter').forEach(function (btn) {
            var on = btn.getAttribute('data-filter') === filter;
            btn.classList.toggle('vs-btn--primary', on);
            btn.classList.toggle('vs-btn--default', !on);
        });
        if (!table) {
            if (emptyAll) { emptyAll.hidden = false; }
            if (emptyFilter) { emptyFilter.hidden = true; }
            return;
        }
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
        table.hidden = total === 0 || visible === 0;
        if (emptyAll) {
            emptyAll.hidden = total > 0;
        }
    }

    function renderActions(actions, audit) {
        if (!actions) { return; }
        var html = '';
        if (audit !== '1') {
            html += '<button type="button" class="vs-btn vs-btn--primary vs-api-review-action" data-audit="1">通过</button>';
        }
        if (audit !== '2') {
            html += '<button type="button" class="vs-btn vs-btn--danger vs-api-review-reject" data-audit="2">不通过</button>';
        }
        actions.innerHTML = html;
    }

    function postAudit(apiId, audit, reason) {
        var fd = new FormData();
        fd.append('action', 'set_audit');
        fd.append('api_id', apiId);
        fd.append('audit', audit);
        if (typeof reason === 'string') {
            fd.append('rejectreason', reason);
        }
        return window.VS.postForm(fd, window.location.pathname).then(function (data) {
            if (!data || data.code !== 1) {
                window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                return null;
            }
            window.VS.showMessage(data.msg || '已更新', 'success');
            var row = table ? table.querySelector('.vs-api-review-row[data-api-id="' + apiId + '"]') : null;
            if (!row) { return data; }
            row.setAttribute('data-audit', String(audit));
            var label = row.querySelector('[data-field="audit_label"]');
            if (label) {
                label.textContent = data.audit_label || '';
                label.className = 'vs-api-list-audit ' + (data.audit_class || '');
            }
            var reasonEl = row.querySelector('[data-field="rejectreason"]');
            if (reasonEl) {
                var r = data.rejectreason || '';
                if (r) {
                    reasonEl.hidden = false;
                    reasonEl.textContent = '原因：' + r;
                } else {
                    reasonEl.hidden = true;
                    reasonEl.textContent = '';
                }
            }
            renderActions(row.querySelector('.vs-api-review-row__actions'), String(audit));
            applyFilter(currentFilter);
            return data;
        }).catch(function () {
            window.VS.showMessage('网络异常，请稍后重试', 'error');
            return null;
        });
    }

    document.addEventListener('click', function (e) {
        var closeEl = e.target.closest('[data-overlay-close]');
        if (closeEl && overlay && overlay.contains(closeEl)) {
            closeOverlay();
            return;
        }

        var filterBtn = e.target.closest('.vs-api-review-filter');
        if (filterBtn) {
            applyFilter(filterBtn.getAttribute('data-filter') || 'all');
            return;
        }

        var rejectBtn = e.target.closest('.vs-api-review-reject');
        if (rejectBtn && page.contains(rejectBtn)) {
            var row = rejectBtn.closest('.vs-api-review-row');
            if (!row) { return; }
            rejectApiId = row.getAttribute('data-api-id') || '';
            openOverlay();
            return;
        }

        var btn = e.target.closest('.vs-api-review-action');
        if (btn && page.contains(btn)) {
            var rowPass = btn.closest('.vs-api-review-row');
            if (!rowPass) { return; }
            postAudit(rowPass.getAttribute('data-api-id'), btn.getAttribute('data-audit'), '');
        }
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!rejectApiId) { return; }
            var reason = reasonInput ? reasonInput.value : '';
            postAudit(rejectApiId, '2', reason).then(function (data) {
                if (data) { closeOverlay(); }
            });
        });
    }

    applyFilter('all');
})();
</script>

<?php
vs_admin_layout_end();
