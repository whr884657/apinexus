<?php
/**
 * 文件：admin/finance/orders.php
 * 作用：充值订单管理（仅用户充值类订单）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'list') {
        AjaxResponse::error('无效操作', 400);
    }
    if (!OrderManager::tableReady()) {
        AjaxResponse::error('订单表未就绪');
    }
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
    $status = array_key_exists('status', $_POST) && $_POST['status'] !== '' ? (int) $_POST['status'] : null;
    $days = isset($_POST['days']) ? (int) $_POST['days'] : OrderManager::DEFAULT_QUERY_DAYS;
    $beforeId = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
    $data = OrderManager::listPaged(array(
        'page'      => $page,
        'pagesize'  => $pagesize,
        'status'    => $status,
        'scope'     => 'recharge',
        'days'      => $days,
        'before_id' => $beforeId,
    ));
    AjaxResponse::success('ok', $data);
}

$tableReady = OrderManager::tableReady();
$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-finance-head-actions" id="ordersToolbar">
        <label class="vs-api-list-pagesize" for="ordersDays">
            <span class="vs-api-list-pagesize__label">近</span>
            <select class="vs-input vs-select" id="ordersDays" data-vs-pick>
                <?php foreach (array(7, 14, 30, 90, 180, 365) as $d): ?>
                    <option value="<?php echo (int) $d; ?>"<?php echo (int) $d === (int) OrderManager::DEFAULT_QUERY_DAYS ? ' selected' : ''; ?>><?php echo (int) $d; ?> 天</option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="vs-finance-filters" role="group" aria-label="订单状态">
            <button type="button" class="vs-btn vs-btn--primary vs-finance-filter is-active" data-status="">全部</button>
            <button type="button" class="vs-btn vs-btn--default vs-finance-filter" data-status="0">待支付</button>
            <button type="button" class="vs-btn vs-btn--default vs-finance-filter" data-status="1">已完成</button>
            <button type="button" class="vs-btn vs-btn--default vs-finance-filter" data-status="2">已取消</button>
        </div>
        <button type="button" class="vs-btn vs-btn--outline vs-finance-refresh" id="orderRefreshBtn">刷新</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('订单管理', 'orders', $headerActions);
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先完成系统升级以同步 orders 表。', array('compact' => true)); ?>
<?php else: ?>
<?php vs_render_notice('tip', '', '默认只查近 ' . (int) OrderManager::DEFAULT_QUERY_DAYS . ' 天订单，按最新记录翻页，避免大表拖慢后台。', array('compact' => true)); ?>
<div class="vs-panel vs-finance-panel" id="ordersPage" data-default-days="<?php echo (int) OrderManager::DEFAULT_QUERY_DAYS; ?>">
    <div class="vs-finance-table" id="ordersListBody">
        <?php vs_render_loading('正在加载订单'); ?>
    </div>
</div>
<div class="vs-api-list-footer" id="ordersFooter" hidden>
    <div class="vs-api-pager" id="ordersPager">
        <label class="vs-api-list-pagesize" for="ordersPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select" id="ordersPageSize" data-vs-pick="sheet">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </label>
        <div class="vs-api-pager__navs" id="ordersPagerNav"></div>
    </div>
    <div class="vs-api-list-total" id="ordersTotal"></div>
</div>
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('finance-orders.js') : array()); ?>
