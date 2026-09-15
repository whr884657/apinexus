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
    // 标题行：小搜索 + 刷新同排靠右（v13.26.41）
    ob_start();
    ?>
    <div class="vs-finance-head-actions vs-finance-head-actions--with-search">
        <div class="vs-finance-search vs-points-head-search">
            <input type="search" class="vs-input vs-finance-search__input" id="pointsSearchInput"
                   placeholder="搜索用户 / 邮箱 / 类型…" autocomplete="off" aria-label="搜索积分变动">
            <button type="button" class="vs-btn vs-btn--primary" id="pointsSearchBtn">搜索</button>
        </div>
        <?php echo vs_admin_refresh_btn_html('pointsRefreshBtn'); ?>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('积分变动', 'points', $headerActions);
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先完成系统升级以同步订单数据。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-finance-toolbar vs-points-toolbar" id="pointsToolbar">
    <div class="vs-points-seg" role="group" aria-label="积分变动大类">
        <button type="button" class="vs-points-seg__btn is-active" data-bucket="account">账户变动</button>
        <button type="button" class="vs-points-seg__btn" data-bucket="api">接口调用</button>
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
<?php vs_admin_layout_end($tableReady ? array('vs-pick.js', 'finance-points.js') : array()); ?>
