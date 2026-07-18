<?php
/**
 * 文件：admin/finance/orders.php
 * 作用：订单 / 积分流水管理
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'list') {
        AjaxResponse::error('无效操作', 400);
    }
    if (!OrderManager::tableReady()) {
        AjaxResponse::error('订单表未就绪，请先执行数据库结构更新');
    }
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
    $status = array_key_exists('status', $_POST) && $_POST['status'] !== '' ? (int) $_POST['status'] : null;
    $data = OrderManager::listPaged(array(
        'page'     => $page,
        'pagesize' => $pagesize,
        'status'   => $status,
    ));
    AjaxResponse::success('ok', $data);
}

$tableReady = OrderManager::tableReady();
vs_admin_layout_start('订单管理', 'orders');
?>
<div class="vs-page-head">
    <div>
        <h1 class="vs-page-title">订单管理</h1>
        <p class="vs-page-desc">查看充值、API 扣费与管理员加减款等全部积分订单。</p>
    </div>
</div>

<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先在系统升级中执行数据库结构更新（含 orders 表）。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-panel">
    <div class="vs-panel__body" style="padding:12px 16px;">
        <div class="vs-form-row vs-form-row--2" style="margin:0;align-items:end;">
            <div>
                <label class="vs-label" for="orderStatusFilter">状态筛选</label>
                <select class="vs-input vs-select" id="orderStatusFilter" data-vs-pick>
                    <option value="">全部</option>
                    <option value="0">待支付</option>
                    <option value="1">已完成</option>
                    <option value="2">已取消</option>
                </select>
            </div>
            <div style="text-align:right;">
                <button type="button" class="vs-btn vs-btn--outline" id="orderRefreshBtn">刷新</button>
            </div>
        </div>
    </div>
</div>

<div class="vs-panel" style="margin-top:12px;">
    <div class="vs-api-list-table">
        <div class="vs-api-list-table__head vs-orders-head">
            <span>订单号</span><span>用户</span><span>类型</span><span>变动</span><span>余额</span><span>金额</span><span>状态</span><span>时间</span>
        </div>
        <div class="vs-api-list-table__body" id="ordersListBody">
            <p class="vs-empty" style="padding:24px;text-align:center;">加载中…</p>
        </div>
    </div>
</div>
<div class="vs-api-list-footer" id="ordersFooter" hidden>
    <div class="vs-api-pager" id="ordersPager"></div>
    <div class="vs-api-list-total" id="ordersTotal"></div>
</div>
<style>
.vs-orders-head{display:grid;grid-template-columns:1.4fr .8fr .9fr .7fr .7fr .6fr .6fr 1fr;gap:8px;padding:10px 14px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0}
.vs-orders-row{display:grid;grid-template-columns:1.4fr .8fr .9fr .7fr .7fr .6fr .6fr 1fr;gap:8px;padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;align-items:center}
.vs-orders-row .muted{color:#64748b;font-size:12px}
@media(max-width:900px){
.vs-orders-head{display:none}
.vs-orders-row{display:block;border:1px solid #e2e8f0;border-radius:10px;margin:0 0 8px;background:#fff}
.vs-orders-row>span{display:flex;justify-content:space-between;padding:4px 0}
.vs-orders-row>span::before{content:attr(data-label);color:#64748b;margin-right:8px}
}
</style>
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('finance-orders.js') : array()); ?>
