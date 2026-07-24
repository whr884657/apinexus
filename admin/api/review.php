<?php
/**
 * 文件：admin/api/review.php
 * 作用：接口审核（待审 / 通过 / 不通过；可选填写不通过原因；邮件通知投稿用户）
 *
 * 说明：仅展示开发者投稿（userid>0）。管理员在「接口列表」发布的接口默认已通过，不进入本页。
 * 筛选用页面内 Tab + 搜索，不改 URL。数值编码仅存在于服务端常量与库表。
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
$listEditBase = rtrim(vs_base_url(), '/') . '/admin/api/list?edit=';

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

/**
 * @param int $audit
 * @return string
 */
function vs_api_review_badge_class($audit)
{
    $audit = ApiManager::normalizeAuditStatus($audit);
    if ($audit === ApiManager::AUDIT_APPROVED) {
        return 'vs-badge--success';
    }
    if ($audit === ApiManager::AUDIT_REJECTED) {
        return 'vs-badge--error';
    }
    return 'vs-badge--warning';
}

/**
 * 列表短文案（与 Tab 一致）
 *
 * @param int $audit
 * @return string
 */
function vs_api_review_badge_text($audit)
{
    $audit = ApiManager::normalizeAuditStatus($audit);
    if ($audit === ApiManager::AUDIT_APPROVED) {
        return '已通过';
    }
    if ($audit === ApiManager::AUDIT_REJECTED) {
        return '未通过';
    }
    return '待审核';
}

/**
 * @param string $time
 * @return string
 */
function vs_api_review_time_text($time)
{
    $time = trim((string) $time);
    if ($time === '') {
        return '—';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', $time, $m)) {
        return $m[0];
    }
    return $time;
}

/**
 * @param int $audit
 * @param int $apiId
 * @param string $listEditBase
 * @return string
 */
function vs_api_review_action_buttons_html($audit, $apiId, $listEditBase)
{
    $audit = ApiManager::normalizeAuditStatus($audit);
    $apiId = (int) $apiId;
    $html = '<div class="action-btns">';
    if ($listEditBase !== '') {
        $html .= '<a class="vs-btn vs-btn--sm vs-btn--outline" href="'
            . vs_e($listEditBase . $apiId) . '">编辑</a>';
    }
    if ($audit !== ApiManager::AUDIT_APPROVED) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-review-action" data-audit="1">通过</button>';
    }
    if ($audit !== ApiManager::AUDIT_REJECTED) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-review-deny" data-audit="2">不通过</button>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param array $row
 * @return array|null
 */
function vs_api_review_row_context(array $row)
{
    $api = ApiManager::formatRowSummary($row);
    if (!$api) {
        return null;
    }
    $apiId = (int) $api['id'];
    $audit = (int) $api['audit'];
    $callUrl = isset($api['call_url']) ? (string) $api['call_url'] : (string) $api['endpoint'];
    $username = isset($api['username']) ? trim((string) $api['username']) : '';
    if ($username === '') {
        $username = ((int) $api['userid'] > 0) ? ('用户#' . (int) $api['userid']) : '未知';
    }
    $createtime = isset($api['createtime']) ? (string) $api['createtime'] : '';
    $timeText = vs_api_review_time_text($createtime);
    $badgeClass = vs_api_review_badge_class($audit);
    $badgeText = vs_api_review_badge_text($audit);
    $reason = isset($api['rejectreason']) ? (string) $api['rejectreason'] : '';
    $searchHay = mb_strtolower(
        $api['name'] . ' ' . $callUrl . ' ' . $api['endpoint'] . ' ' . $username . ' #' . $apiId,
        'UTF-8'
    );
    $avatarChar = '';
    if (function_exists('mb_substr')) {
        $avatarChar = mb_substr($username, 0, 1, 'UTF-8');
    } else {
        $avatarChar = substr($username, 0, 1);
    }
    if ($avatarChar === '') {
        $avatarChar = '?';
    }

    return array(
        'api'         => $api,
        'apiId'       => $apiId,
        'audit'       => $audit,
        'callUrl'     => $callUrl,
        'username'    => $username,
        'avatarChar'  => $avatarChar,
        'createtime'  => $createtime,
        'timeText'    => $timeText,
        'badgeClass'  => $badgeClass,
        'badgeText'   => $badgeText,
        'reason'      => $reason,
        'searchHay'   => $searchHay,
    );
}

/**
 * @param array $ctx
 * @param string $listEditBase
 * @return void
 */
function vs_render_api_review_desktop_row(array $ctx, $listEditBase)
{
    $api = $ctx['api'];
    $attrs = ' data-api-row="' . (int) $ctx['apiId'] . '"'
        . ' data-api-id="' . (int) $ctx['apiId'] . '"'
        . ' data-audit="' . (int) $ctx['audit'] . '"'
        . ' data-search="' . vs_e($ctx['searchHay']) . '"';
    ?>
    <tr class="vs-api-review-row"<?php echo $attrs; ?>>
        <td>
            <div class="review-name-cell">
                <div class="review-icon">
                    <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
                </div>
                <div class="review-name-meta">
                    <span class="review-name-text" data-field="name"><?php echo vs_e($api['name']); ?></span>
                    <span class="review-path" data-field="call_url" title="<?php echo vs_e($ctx['callUrl']); ?>"><?php echo vs_e($ctx['callUrl']); ?></span>
                </div>
            </div>
        </td>
        <td>
            <div class="applicant-cell">
                <span class="applicant-avatar" aria-hidden="true"><?php echo vs_e($ctx['avatarChar']); ?></span>
                <span class="applicant-name" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
            </div>
        </td>
        <td><span class="time-cell" data-field="createtime"><?php echo vs_e($ctx['timeText']); ?></span></td>
        <td>
            <span class="vs-badge <?php echo vs_e($ctx['badgeClass']); ?>" data-field="audit_label"><?php echo vs_e($ctx['badgeText']); ?></span>
            <div class="vs-api-review-reason" data-field="rejectreason"<?php echo $ctx['reason'] === '' ? ' hidden' : ''; ?>>
                <?php echo vs_e($ctx['reason'] !== '' ? ('原因：' . $ctx['reason']) : ''); ?>
            </div>
        </td>
        <td class="vs-api-review-actions-cell" data-field="actions">
            <?php echo vs_api_review_action_buttons_html($ctx['audit'], $ctx['apiId'], $listEditBase); ?>
        </td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @param string $listEditBase
 * @return void
 */
function vs_render_api_review_mobile_card(array $ctx, $listEditBase)
{
    $api = $ctx['api'];
    $attrs = ' data-api-row="' . (int) $ctx['apiId'] . '"'
        . ' data-api-id="' . (int) $ctx['apiId'] . '"'
        . ' data-audit="' . (int) $ctx['audit'] . '"'
        . ' data-search="' . vs_e($ctx['searchHay']) . '"';
    ?>
    <div class="review-card vs-api-review-row"<?php echo $attrs; ?>>
        <div class="review-card__header">
            <div class="review-card__header-left">
                <span class="api-id" data-field="id">#<?php echo (int) $ctx['apiId']; ?></span>
                <div class="review-card__icon">
                    <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
                </div>
                <span class="review-card__name" data-field="name"><?php echo vs_e($api['name']); ?></span>
            </div>
            <div class="review-card__tags">
                <span class="vs-badge <?php echo vs_e($ctx['badgeClass']); ?>" data-field="audit_label"><?php echo vs_e($ctx['badgeText']); ?></span>
            </div>
        </div>
        <div class="review-card__info">
            <span class="review-card__info-item">
                <span class="review-card__info-label">开发者</span>
                <span class="review-card__info-value" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
            </span>
            <span class="review-card__info-item">
                <span class="review-card__info-label">提交时间</span>
                <span class="review-card__info-value" data-field="createtime"><?php echo vs_e($ctx['timeText']); ?></span>
            </span>
        </div>
        <div class="vs-api-review-reason" data-field="rejectreason"<?php echo $ctx['reason'] === '' ? ' hidden' : ''; ?>>
            <?php echo vs_e($ctx['reason'] !== '' ? ('原因：' . $ctx['reason']) : ''); ?>
        </div>
        <div class="review-card__actions" data-field="actions">
            <?php echo vs_api_review_action_buttons_html($ctx['audit'], $ctx['apiId'], $listEditBase); ?>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($hasAudit) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="apiReviewSearchInput"
                   placeholder="搜索接口名称或开发者..." autocomplete="off">
        </div>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口审核', 'api-review', $headerActions);
?>

<div id="apiReviewPage"
     data-list-edit-base="<?php echo vs_e($listEditBase); ?>"
     data-has-table="<?php echo $hasAudit && count($apis) > 0 ? '1' : '0'; ?>">

    <?php if (!$tableReady): ?>
        <div class="vs-panel vs-api-review-panel">
            <?php vs_render_notice('warning', '', '接口管理功能尚未就绪，请先前往「系统升级」完成更新。', array('compact' => true)); ?>
            <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade'); ?>">前往系统升级</a>
        </div>
    <?php elseif (!$hasAudit): ?>
        <div class="vs-panel vs-api-review-panel">
            <?php vs_render_notice('warning', '', '当前系统尚未具备审核功能，请先前往「系统升级」完成结构更新。', array('compact' => true)); ?>
            <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade'); ?>">前往系统升级</a>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip">
            <?php vs_render_notice('info', '', '本页仅显示开发者投稿的接口。管理员在「接口列表」直接发布的接口默认已通过审核，不会出现在这里。不通过时可填写原因（选填），系统将邮件通知投稿用户。', array('compact' => true)); ?>
        </div>

        <div class="vs-tabs vs-api-review-tabs" id="apiReviewFilters" role="tablist" aria-label="审核筛选">
            <button type="button" class="vs-tabs__btn vs-api-review-filter is-active" data-filter="0" role="tab" aria-selected="true">
                待审核<span class="vs-badge vs-badge--warning vs-api-review-tabs__badge" data-count="0"><?php echo (int) $counts['0']; ?></span>
            </button>
            <button type="button" class="vs-tabs__btn vs-api-review-filter" data-filter="1" role="tab" aria-selected="false">
                已通过<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="1"><?php echo (int) $counts['1']; ?></span>
            </button>
            <button type="button" class="vs-tabs__btn vs-api-review-filter" data-filter="2" role="tab" aria-selected="false">
                未通过<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="2"><?php echo (int) $counts['2']; ?></span>
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
                <p class="vs-api-list-empty__desc">当前筛选或搜索下没有接口，可切换上方「待审核 / 已通过 / 未通过」或清空搜索。</p>
            </div>
        </div>

        <div class="vs-api-list-table-card vs-api-list-table-wrap" id="apiReviewTableWrap"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-table-responsive">
                <table class="vs-table vs-api-review-table">
                    <thead>
                        <tr>
                            <th>接口名称</th>
                            <th>开发者</th>
                            <th>提交时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="apiReviewBody">
                        <?php foreach ($apis as $row): ?>
                            <?php
                            $reviewCtx = vs_api_review_row_context($row);
                            if ($reviewCtx) {
                                vs_render_api_review_desktop_row($reviewCtx, $listEditBase);
                            }
                            ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-review-cards" id="apiReviewMobile"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($apis as $row): ?>
                <?php
                $reviewCtx = vs_api_review_row_context($row);
                if ($reviewCtx) {
                    vs_render_api_review_mobile_card($reviewCtx, $listEditBase);
                }
                ?>
            <?php endforeach; ?>
        </div>

        <div class="vs-api-list-footer" id="apiReviewFooter"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-pager" id="apiReviewPager">
                <label class="vs-api-list-pagesize" for="apiReviewPageSize">
                    <span class="vs-api-list-pagesize__label">每页</span>
                    <select class="vs-input vs-select vs-api-list-pagesize__select" id="apiReviewPageSize" data-vs-pick="sheet">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <button type="button" class="vs-api-pager__nav" id="apiReviewPrevBtn" aria-label="上一页">上一页</button>
                <div class="vs-api-pager__nums" id="apiReviewPagerNums" role="navigation" aria-label="页码"></div>
                <button type="button" class="vs-api-pager__nav" id="apiReviewNextBtn" aria-label="下一页">下一页</button>
            </div>
            <p class="vs-api-list-stats" id="apiReviewStats">共 <?php echo (int) count($apis); ?> 条</p>
        </div>
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
