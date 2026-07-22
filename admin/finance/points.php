<?php
/**
 * 文件：admin/finance/points.php
 * 作用：积分变动流水（已完成的加减记录）
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
    $beforeId = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
    $data = OrderManager::listPaged(array(
        'page'      => $page,
        'pagesize'  => $pagesize,
        'scope'     => 'ledger',
        'before_id' => $beforeId,
    ));
    AjaxResponse::success('ok', $data);
}

$tableReady = OrderManager::tableReady();
$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-finance-head-actions vs-finance-head-actions--simple" id="pointsToolbar">
        <button type="button" class="vs-btn vs-btn--outline vs-finance-refresh" id="pointsRefreshBtn">刷新</button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('积分变动', 'points', $headerActions);
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先完成系统升级以同步订单数据。', array('compact' => true)); ?>
<?php else: ?>
<?php vs_render_notice('tip', '', '每次只加载当前每页条数的最新积分变动，翻页继续向更早记录取数。积分流水全部留在库内，不做冷热归档。', array('compact' => true)); ?>
<div class="vs-panel vs-finance-panel" id="pointsPage">
    <div class="vs-finance-table" id="pointsListBody">
        <?php vs_render_loading('正在加载积分变动'); ?>
    </div>
</div>
<div class="vs-api-list-footer" id="pointsFooter" hidden>
    <div class="vs-api-pager" id="pointsPager">
        <label class="vs-api-list-pagesize" for="pointsPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select" id="pointsPageSize" data-vs-pick="sheet">
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
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('finance-points.js') : array()); ?>
