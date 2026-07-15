<?php
/**
 * 文件：admin/api/review.php
 * 作用：接口审核（待审 / 通过 / 不通过；可选填写不通过原因；邮件通知投稿用户）
 *
 * 说明：仅展示开发者投稿（userid>0）。管理员在「接口列表」发布的接口默认已通过，不进入本页。
 * 筛选用页面内按钮，不改 URL。数值编码仅存在于服务端常量与库表。
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
        if ((int) (isset($before['userid']) ? $before['userid'] : 0) <= 0) {
            AjaxResponse::error('管理员发布的接口无需在本页审核，请前往「接口列表」管理');
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
$apis = $hasAudit ? ApiManager::listForReview() : array();

$counts = array(
    'all' => 0,
    '0'   => 0,
    '1'   => 0,
    '2'   => 0,
);
foreach ($apis as $row) {
    $a = isset($row['audit']) ? (string) (int) $row['audit'] : '0';
    $counts['all'] += 1;
    if (isset($counts[$a])) {
        $counts[$a] += 1;
    }
}

vs_admin_layout_start('接口审核', 'api-review');
?>

<div class="vs-panel vs-api-review-panel" id="apiReviewPage"
     data-has-table="<?php echo $hasAudit && count($apis) > 0 ? '1' : '0'; ?>">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '接口管理功能尚未就绪，请先前往「系统升级」完成更新。', array('compact' => true)); ?>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
    <?php elseif (!$hasAudit): ?>
        <?php vs_render_notice('warning', '', '当前系统尚未具备审核功能，请先前往「系统升级」完成结构更新。', array('compact' => true)); ?>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
    <?php else: ?>
        <?php vs_render_notice('info', '', '本页仅显示开发者投稿的接口。管理员在「接口列表」直接发布的接口默认已通过审核，不会出现在这里。不通过时可填写原因（选填），系统将邮件通知投稿用户。', array('compact' => true)); ?>

        <div class="vs-api-review-tabs" id="apiReviewFilters" role="tablist" aria-label="审核筛选">
            <button type="button" class="vs-btn vs-btn--default vs-api-review-filter vs-api-review-tabs__btn" data-filter="all">
                全部<span class="vs-api-review-tabs__badge"><?php echo (int) $counts['all']; ?></span>
            </button>
            <button type="button" class="vs-btn vs-btn--primary vs-api-review-filter vs-api-review-tabs__btn is-active" data-filter="0">
                待审核<span class="vs-api-review-tabs__badge"><?php echo (int) $counts['0']; ?></span>
            </button>
            <button type="button" class="vs-btn vs-btn--default vs-api-review-filter vs-api-review-tabs__btn" data-filter="1">
                已通过<span class="vs-api-review-tabs__badge"><?php echo (int) $counts['1']; ?></span>
            </button>
            <button type="button" class="vs-btn vs-btn--default vs-api-review-filter vs-api-review-tabs__btn" data-filter="2">
                未通过<span class="vs-api-review-tabs__badge"><?php echo (int) $counts['2']; ?></span>
            </button>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiReviewEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无投稿待审</h3>
                <p class="vs-api-list-empty__desc">开发者在用户中心提交接口后，会出现在本页。管理员自行发布的接口请在「接口列表」管理。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiReviewFilterEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前筛选项下没有接口，可切换上方「全部 / 待审核 / 已通过 / 未通过」查看。</p>
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
                    $rowHidden = $audit !== ApiManager::AUDIT_PENDING ? ' hidden' : '';
                    ?>
                    <div class="vs-api-review-row" data-api-id="<?php echo (int) $api['id']; ?>" data-audit="<?php echo $audit; ?>"<?php echo $rowHidden; ?>>
                        <div class="vs-api-review-row__main">
                            <strong class="vs-api-review-name"><?php echo vs_e($api['name']); ?></strong>
                            <span class="vs-api-review-endpoint"><?php echo vs_e($api['endpoint']); ?></span>
                            <?php if (!empty($api['username'])): ?>
                                <span class="vs-api-review-meta">投稿者：<?php echo vs_e($api['username']); ?></span>
                            <?php else: ?>
                                <span class="vs-api-review-meta">投稿者：用户 #<?php echo (int) $api['userid']; ?></span>
                            <?php endif; ?>
                            <span class="vs-api-review-reason" data-field="rejectreason"<?php echo $reason === '' ? ' hidden' : ''; ?>>
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
                        <div class="vs-api-review-row__actions vs-api-review-actions">
                            <?php if ($audit !== ApiManager::AUDIT_APPROVED): ?>
                                <button type="button" class="vs-btn vs-btn--primary vs-api-review-action"
                                        data-audit="1">通过</button>
                            <?php endif; ?>
                            <?php if ($audit !== ApiManager::AUDIT_REJECTED): ?>
                                <button type="button" class="vs-btn vs-btn--danger vs-api-review-deny"
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
.vs-api-review-reason { font-size: 12px; color: #b45309; word-break: break-word; }
.vs-api-review-row__actions { display: flex; gap: 8px; flex-wrap: wrap; }
@media (max-width: 768px) {
    .vs-api-review-table__head { display: none; }
    .vs-api-review-row { grid-template-columns: 1fr; }
    .vs-api-review-row__actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
    }
    .vs-api-review-row__actions .vs-btn { width: 100%; }
}
</style>

<?php
vs_admin_layout_end(array('api-review.js'));
