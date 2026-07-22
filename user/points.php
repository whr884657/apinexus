<?php
/**
 * 文件：user/points.php
 * 作用：用户积分变动
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
    $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
    $days = isset($_POST['days']) ? (int) $_POST['days'] : OrderManager::DEFAULT_QUERY_DAYS;
    $beforeId = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
    $data = OrderManager::listPaged(array(
        'userid'    => $userId,
        'page'      => $page,
        'pagesize'  => $pagesize,
        'scope'     => 'ledger',
        'days'      => $days,
        'before_id' => $beforeId,
    ));
    $data['balance'] = PayConfig::fmtPoints(PointsManager::balance($userId));
    AjaxResponse::success('ok', $data);
}

$balance = $ready ? PointsManager::balance($userId) : 0;
vs_user_layout_start('积分变动', 'points');
?>
<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', '积分功能尚未就绪。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-points">
    <div class="vs-points-hero">
        <div class="vs-points-hero__main">
            <div class="vs-points-hero__label">当前余额</div>
            <div class="vs-points-hero__value" id="pointsBalance"><?php echo vs_e(PayConfig::fmtPoints($balance)); ?></div>
        </div>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/user/recharge'); ?>">去充值</a>
    </div>

    <div class="vs-panel vs-finance-panel">
        <div class="vs-finance-head-actions" style="margin-bottom:12px">
            <label class="vs-api-list-pagesize" for="userPointsDays">
                <span class="vs-api-list-pagesize__label">近</span>
                <select class="vs-input vs-select" id="userPointsDays" data-vs-pick>
                    <?php foreach (array(7, 14, 30, 90, 180, 365) as $d): ?>
                        <option value="<?php echo (int) $d; ?>"<?php echo (int) $d === (int) OrderManager::DEFAULT_QUERY_DAYS ? ' selected' : ''; ?>><?php echo (int) $d; ?> 天</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="vs-finance-table vs-points-list" id="pointsListBody"
             data-default-days="<?php echo (int) OrderManager::DEFAULT_QUERY_DAYS; ?>">
            <?php vs_render_loading('正在加载积分变动'); ?>
        </div>
    </div>
    <div class="vs-api-list-footer" id="pointsFooter" hidden>
        <div class="vs-api-pager" id="pointsPager">
            <label class="vs-api-list-pagesize" for="userPointsPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select" id="userPointsPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <div class="vs-api-pager__navs" id="pointsPagerNav"></div>
        </div>
        <div class="vs-api-list-total" id="pointsTotal"></div>
    </div>
</div>
<?php endif; ?>
<?php vs_user_layout_end($ready ? array('user-points.js') : array()); ?>
