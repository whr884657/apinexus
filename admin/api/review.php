<?php
/**
 * 文件：admin/api/review.php
 * 作用：接口审核（待审核 / 已通过 / 已拒绝）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $apiId = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;

    if ($apiId <= 0) {
        AjaxResponse::error('无效接口');
    }

    if ($action === 'approve') {
        $result = ApiManager::setStatus($apiId, ApiManager::STATUS_APPROVED);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('已通过审核', array(
            'api_id' => $apiId,
            'status' => ApiManager::STATUS_APPROVED,
            'status_label' => ApiManager::statusLabel(ApiManager::STATUS_APPROVED),
        ));
    }

    if ($action === 'reject') {
        $reason = isset($_POST['reject_reason']) ? trim((string) $_POST['reject_reason']) : '';
        if ($reason === '') {
            AjaxResponse::error('请填写拒绝原因');
        }
        $result = ApiManager::setStatus($apiId, ApiManager::STATUS_REJECTED, $reason);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('已拒绝该接口', array(
            'api_id' => $apiId,
            'status' => ApiManager::STATUS_REJECTED,
            'status_label' => ApiManager::statusLabel(ApiManager::STATUS_REJECTED),
            'reject_reason' => $reason,
        ));
    }

    if ($action === 'offline') {
        $result = ApiManager::setStatus($apiId, ApiManager::STATUS_OFFLINE);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('接口已下线', array(
            'api_id' => $apiId,
            'status' => ApiManager::STATUS_OFFLINE,
            'status_label' => ApiManager::statusLabel(ApiManager::STATUS_OFFLINE),
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$filter = isset($_GET['status']) ? (string) $_GET['status'] : ApiManager::STATUS_PENDING;
$allowedFilters = array(
    ApiManager::STATUS_PENDING,
    ApiManager::STATUS_APPROVED,
    ApiManager::STATUS_REJECTED,
    'all',
);
if (!in_array($filter, $allowedFilters, true)) {
    $filter = ApiManager::STATUS_PENDING;
}

$listStatus = $filter === 'all' ? null : $filter;
$apis = ApiManager::listAll($listStatus);
$pendingCount = ApiManager::countPending();

/**
 * @param array $row
 * @return string
 */
function vs_api_review_status_badge(array $row)
{
    $status = isset($row['status']) ? (string) $row['status'] : '';
    $class = 'vs-api-status';
    if ($status === ApiManager::STATUS_PENDING) {
        $class .= ' vs-api-status--pending';
    } elseif ($status === ApiManager::STATUS_APPROVED) {
        $class .= ' vs-api-status--approved';
    } elseif ($status === ApiManager::STATUS_REJECTED) {
        $class .= ' vs-api-status--rejected';
    } else {
        $class .= ' vs-api-status--offline';
    }
    return '<span class="' . $class . '">' . vs_e(ApiManager::statusLabel($status)) . '</span>';
}

vs_admin_layout_start('接口审核', 'api-review');
?>

<div class="vs-panel" id="apiReviewPage">
    <div class="vs-panel__header">
        <div>
            <h2 class="vs-panel__title">接口审核</h2>
            <p class="vs-panel__desc">审核用户提交的 API 接口，通过后可在前台展示</p>
        </div>
    </div>

    <nav class="vs-api-review-tabs" aria-label="审核状态筛选">
        <a href="?status=pending" class="vs-api-review-tabs__btn<?php echo $filter === ApiManager::STATUS_PENDING ? ' is-active' : ''; ?>">
            待审核<?php if ($pendingCount > 0): ?><span class="vs-api-review-tabs__badge"><?php echo (int) $pendingCount; ?></span><?php endif; ?>
        </a>
        <a href="?status=approved" class="vs-api-review-tabs__btn<?php echo $filter === ApiManager::STATUS_APPROVED ? ' is-active' : ''; ?>">已通过</a>
        <a href="?status=rejected" class="vs-api-review-tabs__btn<?php echo $filter === ApiManager::STATUS_REJECTED ? ' is-active' : ''; ?>">已拒绝</a>
        <a href="?status=all" class="vs-api-review-tabs__btn<?php echo $filter === 'all' ? ' is-active' : ''; ?>">全部</a>
    </nav>

    <?php if (count($apis) === 0): ?>
        <?php vs_render_notice('info', '', '暂无' . ($filter === ApiManager::STATUS_PENDING ? '待审核' : '') . '接口记录', array('compact' => true)); ?>
    <?php else: ?>
        <div class="vs-table-wrap">
            <table class="vs-table vs-api-review-table">
                <thead>
                    <tr>
                        <th>接口</th>
                        <th>提交者</th>
                        <th>分类 / 方法</th>
                        <th>状态</th>
                        <th>提交时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apis as $row): ?>
                        <?php
                        $apiId = (int) $row['id'];
                        $status = isset($row['status']) ? (string) $row['status'] : '';
                        $username = trim((string) $row['username']);
                        $submitter = $username !== '' ? $username : ('用户 #' . (int) $row['user_id']);
                        ?>
                        <tr data-api-row="<?php echo $apiId; ?>" data-api-status="<?php echo vs_e($status); ?>">
                            <td>
                                <div class="vs-api-review-name"><?php echo vs_e($row['name']); ?></div>
                                <?php if (trim((string) $row['endpoint']) !== ''): ?>
                                    <div class="vs-api-review-endpoint"><?php echo vs_e($row['method']); ?> <?php echo vs_e($row['endpoint']); ?></div>
                                <?php endif; ?>
                                <?php if (trim((string) $row['description']) !== ''): ?>
                                    <div class="vs-api-review-desc"><?php echo vs_e($row['description']); ?></div>
                                <?php endif; ?>
                                <?php if ($status === ApiManager::STATUS_REJECTED && trim((string) $row['reject_reason']) !== ''): ?>
                                    <div class="vs-api-review-reject">拒绝原因：<?php echo vs_e($row['reject_reason']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo vs_e($submitter); ?></div>
                                <?php if (trim((string) $row['email']) !== ''): ?>
                                    <div class="vs-api-review-meta"><?php echo vs_e($row['email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo vs_e($row['category'] !== '' ? $row['category'] : '—'); ?></div>
                                <div class="vs-api-review-meta"><?php echo vs_e(strtoupper((string) $row['type'])); ?></div>
                            </td>
                            <td class="vs-api-review-status-cell"><?php echo vs_api_review_status_badge($row); ?></td>
                            <td><?php echo vs_e($row['created_at']); ?></td>
                            <td>
                                <div class="vs-api-review-actions">
                                    <?php if ($status === ApiManager::STATUS_PENDING): ?>
                                        <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-action-btn"
                                                data-api-action="approve" data-api-id="<?php echo $apiId; ?>">通过</button>
                                        <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger vs-api-action-btn"
                                                data-api-action="reject" data-api-id="<?php echo $apiId; ?>">拒绝</button>
                                    <?php elseif ($status === ApiManager::STATUS_APPROVED): ?>
                                        <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger vs-api-action-btn"
                                                data-api-action="offline" data-api-id="<?php echo $apiId; ?>">下线</button>
                                    <?php else: ?>
                                        <span class="vs-api-review-meta">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="vs-modal" id="apiRejectModal" hidden>
    <div class="vs-modal__backdrop" data-modal-close="1"></div>
    <div class="vs-modal__dialog" role="dialog" aria-labelledby="apiRejectModalTitle" aria-modal="true">
        <div class="vs-modal__header">
            <h3 class="vs-modal__title" id="apiRejectModalTitle">拒绝接口</h3>
            <button type="button" class="vs-modal__close" data-modal-close="1" aria-label="关闭">&times;</button>
        </div>
        <div class="vs-modal__body">
            <label class="vs-label" for="apiRejectReason">拒绝原因</label>
            <textarea class="vs-textarea" id="apiRejectReason" rows="3" placeholder="请说明拒绝原因，将展示给提交用户"></textarea>
        </div>
        <div class="vs-modal__footer">
            <button type="button" class="vs-btn" data-modal-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="apiRejectConfirmBtn">确认拒绝</button>
        </div>
    </div>
</div>

<?php vs_admin_layout_end(array('api-review.js')); ?>
