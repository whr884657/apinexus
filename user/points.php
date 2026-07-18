<?php
/**
 * 文件：user/points.php
 * 作用：用户积分变动（订单流水）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$ready = OrderManager::tableReady() && PointsManager::hasPointsColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    if (!$ready) {
        AjaxResponse::error('积分系统未就绪');
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'list') {
        AjaxResponse::error('无效操作', 400);
    }
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $data = OrderManager::listPaged(array(
        'userid'   => $userId,
        'page'     => $page,
        'pagesize' => 20,
        'status'   => OrderManager::STATUS_DONE,
    ));
    $data['balance'] = PayConfig::fmtPoints(PointsManager::balance($userId));
    AjaxResponse::success('ok', $data);
}

$balance = $ready ? PointsManager::balance($userId) : 0;
vs_user_layout_start('积分变动', 'points');
?>
<div class="vs-page-head">
    <div>
        <h1 class="vs-page-title">积分变动</h1>
        <p class="vs-page-desc">查看充值到账、API 调用扣费与管理员调整记录。</p>
    </div>
    <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/user/recharge'); ?>">去充值</a>
</div>

<div class="vs-panel" style="margin-bottom:12px;">
    <div class="vs-panel__body" style="padding:16px 20px;">
        当前余额：<strong id="pointsBalance"><?php echo vs_e(PayConfig::fmtPoints($balance)); ?></strong>
    </div>
</div>

<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', '积分功能尚未就绪。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-panel">
    <div class="vs-api-list-table__body" id="pointsListBody" style="padding:8px;">
        <p class="vs-empty" style="padding:24px;text-align:center;">加载中…</p>
    </div>
</div>
<div class="vs-api-list-footer" id="pointsFooter" hidden>
    <div class="vs-api-pager" id="pointsPager"></div>
    <div class="vs-api-list-total" id="pointsTotal"></div>
</div>
<?php endif; ?>
<?php vs_user_layout_end($ready ? array('user-points.js') : array()); ?>
