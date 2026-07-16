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
$listEditBase = rtrim(vs_base_url(), '/') . '/admin/api/list.php?edit=';

$counts = array(
    '0' => 0,
    '1' => 0,
    '2' => 0,
);
foreach ($apis as $row) {
    $a = isset($row['audit']) ? (string) (int) $row['audit'] : '0';
    if (isset($counts[$a])) {
        $counts[$a] += 1;
    }
}

vs_admin_layout_start('接口审核', 'api-review');
?>

<div class="vs-panel vs-api-review-panel" id="apiReviewPage"
     data-list-edit-base="<?php echo vs_e($listEditBase); ?>"
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
                <h3 class="vs-api-list-empty__title">暂无投稿</h3>
                <p class="vs-api-list-empty__desc">开发者在用户中心提交接口后，会出现在本页。管理员自行发布的接口请在「接口列表」管理。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiReviewFilterEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前筛选项下没有接口，可切换上方「待审核 / 已通过 / 未通过」查看。</p>
            </div>
        </div>

        <?php if (count($apis) > 0): ?>
            <div class="vs-api-review-list" id="apiReviewTable">
                <?php foreach ($apis as $row): ?>
                    <?php
                    $api = ApiManager::formatRowSummary($row);
                    if (!$api) {
                        continue;
                    }
                    $audit = (int) $api['audit'];
                    $reason = isset($api['rejectreason']) ? (string) $api['rejectreason'] : '';
                    $callUrl = isset($api['call_url']) ? (string) $api['call_url'] : (string) $api['endpoint'];
                    $rowHidden = $audit !== ApiManager::AUDIT_PENDING ? ' hidden' : '';
                    $statusClass = 'is-normal';
                    if ((int) $api['status'] === ApiManager::STATUS_DISABLED) {
                        $statusClass = 'is-disabled';
                    } elseif ((int) $api['status'] === ApiManager::STATUS_MAINTENANCE) {
                        $statusClass = 'is-maintenance';
                    }
                    $methodSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $api['method']));
                    if ($methodSlug === '') {
                        $methodSlug = 'get';
                    }
                    $typeBadge = isset($api['apitype_badge']) ? (string) $api['apitype_badge'] : ApiManager::apiTypeBadge(isset($api['apitype']) ? $api['apitype'] : 0);
                    $keyBadge = isset($api['needkey_badge']) ? (string) $api['needkey_badge'] : ApiManager::requireKeyBadge(isset($api['needkey']) ? $api['needkey'] : 0);
                    $category = isset($api['category']) ? trim((string) $api['category']) : '';
                    ?>
                    <div class="vs-api-item vs-api-review-row" data-api-id="<?php echo (int) $api['id']; ?>" data-audit="<?php echo $audit; ?>"<?php echo $rowHidden; ?>>
                        <div class="vs-api-item__icon">
                            <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy">
                        </div>
                        <div class="vs-api-item__title">
                            <span class="vs-api-item__id">#<?php echo (int) $api['id']; ?></span>
                            <span class="vs-api-item__name"><?php echo vs_e($api['name']); ?></span>
                        </div>
                        <div class="vs-api-item__tags">
                            <?php if ($category !== ''): ?>
                                <span class="vs-api-tag vs-api-tag--cat"><?php echo vs_e($category); ?></span>
                            <?php endif; ?>
                            <span class="vs-api-tag vs-api-tag--free">免费</span>
                            <?php if ($keyBadge !== ''): ?>
                                <span class="vs-api-tag vs-api-tag--key"><?php echo vs_e($keyBadge); ?></span>
                            <?php endif; ?>
                            <?php if ($typeBadge !== ''): ?>
                                <span class="vs-api-tag vs-api-tag--proxy"><?php echo vs_e($typeBadge); ?></span>
                            <?php endif; ?>
                            <span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>"><?php echo vs_e($api['status_label']); ?></span>
                            <span class="vs-api-tag vs-api-tag--audit <?php echo vs_e($api['audit_class']); ?>" data-field="audit_label"><?php echo vs_e($api['audit_label']); ?></span>
                        </div>
                        <div class="vs-api-item__endpoint">
                            <span class="vs-api-list-method vs-api-list-method--<?php echo vs_e($methodSlug); ?>"><?php echo vs_e($api['method']); ?></span>
                            <span class="vs-api-item__url" title="<?php echo vs_e($callUrl); ?>"><?php echo vs_e($callUrl); ?></span>
                        </div>
                        <div class="vs-api-item__calls"><span><?php echo (int) $api['calls']; ?></span></div>
                        <div class="vs-api-item__actions vs-api-review-row__actions">
                            <a class="vs-btn vs-btn--outline" href="<?php echo vs_e($listEditBase . (int) $api['id']); ?>">编辑</a>
                            <?php if ($audit !== ApiManager::AUDIT_APPROVED): ?>
                                <button type="button" class="vs-btn vs-btn--outline vs-api-review-action" data-audit="1">通过</button>
                            <?php endif; ?>
                            <?php if ($audit !== ApiManager::AUDIT_REJECTED): ?>
                                <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-api-review-deny" data-audit="2">不通过</button>
                            <?php endif; ?>
                        </div>
                        <div class="vs-api-review-meta vs-api-item__meta">
                            <?php if (!empty($api['username'])): ?>
                                投稿者：<?php echo vs_e($api['username']); ?>
                            <?php else: ?>
                                投稿者：用户 #<?php echo (int) $api['userid']; ?>
                            <?php endif; ?>
                        </div>
                        <div class="vs-api-review-reason" data-field="rejectreason"<?php echo $reason === '' ? ' hidden' : ''; ?>>
                            原因：<?php echo vs_e($reason); ?>
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

<?php
vs_admin_layout_end(array('api-review.js'));
