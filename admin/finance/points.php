<?php
/**
 * 文件：admin/finance/points.php
 * 作用：积分变动流水（已完成的加减记录；账户类 / 接口调用类分栏）
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
    $q = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
    $bucket = isset($_POST['ledger_bucket']) ? trim((string) $_POST['ledger_bucket']) : 'account';
    if ($bucket !== 'account' && $bucket !== 'api') {
        $bucket = 'account';
    }
    $data = OrderManager::listPaged(array(
        'page'           => $page,
        'pagesize'       => $pagesize,
        'scope'          => 'ledger',
        'ledger_bucket'  => $bucket,
        'before_id'      => $beforeId,
        'q'              => $q,
    ));
    AjaxResponse::success('ok', $data);
}

$tableReady = OrderManager::tableReady();
$headerActions = '';
if ($tableReady) {
    // 与订单管理一致：标题行仅刷新图标，手机端仍靠右上角
    $headerActions = '<div class="vs-finance-head-actions vs-finance-head-actions--simple">'
        . vs_admin_refresh_btn_html('pointsRefreshBtn')
        . '</div>';
}

vs_admin_layout_start('积分变动', 'points', $headerActions);
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先完成系统升级以同步订单数据。', array('compact' => true)); ?>
<?php else: ?>
<?php /* 财务工具栏结构对齐订单管理；筛选按钮样式对齐日志查询（vs-finance-filters） */ ?>
<div class="vs-finance-toolbar vs-points-toolbar" id="pointsToolbar">
    <div class="vs-finance-search">
        <input type="search" class="vs-input vs-finance-search__input" id="pointsSearchInput"
               placeholder="搜索用户 / 邮箱 / 类型 / 说明…" autocomplete="off">
        <button type="button" class="vs-btn vs-btn--primary" id="pointsSearchBtn">搜索</button>
    </div>
    <div class="vs-finance-filters" role="group" aria-label="积分变动大类">
        <button type="button" class="vs-btn vs-btn--primary vs-points-filter is-active" data-bucket="account">账户变动</button>
        <button type="button" class="vs-btn vs-btn--default vs-points-filter" data-bucket="api">接口调用</button>
    </div>
</div>

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
