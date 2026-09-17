<?php
/**
 * 文件：admin/finance/cardkey.php
 * 作用：积分卡密管理（生成 / 列表 / 作废 / 导出；同页 POST）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if (!CardKeyManager::tableReady()) {
        AjaxResponse::error('卡密表未就绪，请先完成系统升级');
    }

    if ($action === 'list') {
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
        $beforeId = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
        $q = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        $status = isset($_POST['status']) ? trim((string) $_POST['status']) : 'all';
        if (!in_array($status, array('all', 'unused', 'used', 'void', 'issued'), true)) {
            $status = 'all';
        }
        $data = CardKeyManager::listPaged(array(
            'page'      => $page,
            'pagesize'  => $pagesize,
            'before_id' => $beforeId,
            'q'         => $q,
            'status'    => $status,
        ));
        AjaxResponse::success('ok', $data);
    }

    if ($action === 'stats') {
        AjaxResponse::success('ok', CardKeyManager::stats());
    }

    if ($action === 'generate') {
        $count = isset($_POST['count']) ? (int) $_POST['count'] : 0;
        $points = isset($_POST['points']) ? (int) $_POST['points'] : 0;
        $result = CardKeyManager::generate($count, $points, Auth::id());
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '生成失败');
        }
        AjaxResponse::success($result['msg'], array(
            'list'  => isset($result['list']) ? $result['list'] : array(),
            'count' => isset($result['count']) ? $result['count'] : 0,
        ));
    }

    if ($action === 'void') {
        $idsRaw = isset($_POST['ids']) ? $_POST['ids'] : '';
        $ids = array();
        if (is_array($idsRaw)) {
            $ids = $idsRaw;
        } elseif (is_string($idsRaw) && $idsRaw !== '') {
            $ids = preg_split('/\s*,\s*/', $idsRaw);
        }
        $result = CardKeyManager::voidUnused($ids);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '作废失败');
        }
        AjaxResponse::success($result['msg'], array(
            'count' => isset($result['count']) ? $result['count'] : 0,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = CardKeyManager::tableReady();
$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-finance-head-actions vs-finance-head-actions--with-search vs-cardkey-head-actions">
        <div class="vs-finance-search vs-points-head-search">
            <input type="search" class="vs-input vs-finance-search__input" id="cardkeySearchInput"
                   placeholder="搜索卡密 / 用户…" autocomplete="off" aria-label="搜索卡密">
            <button type="button" class="vs-btn vs-btn--primary" id="cardkeySearchBtn">搜索</button>
        </div>
        <button type="button" class="vs-btn vs-btn--primary" id="cardkeyAddBtn">添加</button>
        <?php echo vs_admin_refresh_btn_html('cardkeyRefreshBtn'); ?>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('卡密管理', 'cardkey', $headerActions);
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先完成系统升级以同步卡密数据表。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-finance-toolbar vs-points-toolbar vs-cardkey-toolbar" id="cardkeyToolbar">
    <div class="vs-points-seg" role="group" aria-label="卡密状态">
        <button type="button" class="vs-points-seg__btn is-active" data-status="all">全部</button>
        <button type="button" class="vs-points-seg__btn" data-status="unused">未使用</button>
        <button type="button" class="vs-points-seg__btn" data-status="issued">已发放</button>
        <button type="button" class="vs-points-seg__btn" data-status="used">已使用</button>
        <button type="button" class="vs-points-seg__btn" data-status="void">已作废</button>
    </div>
    <div class="vs-cardkey-stats" id="cardkeyStats" aria-live="polite">
        <span class="vs-cardkey-stat" data-stat="unused">未使用 <strong>0</strong></span>
        <span class="vs-cardkey-stat" data-stat="issued">已发放 <strong>0</strong></span>
        <span class="vs-cardkey-stat" data-stat="used">已使用 <strong>0</strong></span>
        <span class="vs-cardkey-stat" data-stat="points">库存积分 <strong>0</strong></span>
    </div>
    <div class="vs-cardkey-bulk" id="cardkeyBulkBar">
        <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="cardkeyExportBtn" disabled>一键导出 TXT</button>
        <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="cardkeyVoidBtn" disabled>作废所选</button>
    </div>
</div>

<div class="vs-panel vs-finance-panel" id="cardkeyPage">
    <div class="vs-finance-table" id="cardkeyListBody">
        <?php vs_render_loading('正在加载卡密'); ?>
    </div>
</div>
<div class="vs-api-list-footer" id="cardkeyFooter" hidden>
    <div class="vs-api-pager" id="cardkeyPager">
        <label class="vs-api-list-pagesize" for="cardkeyPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select" id="cardkeyPageSize" data-vs-pick="sheet">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </label>
        <button type="button" class="vs-api-pager__nav" id="cardkeyPrevBtn" aria-label="上一页">上一页</button>
        <div class="vs-api-pager__nums" id="cardkeyPagerNums" role="navigation" aria-label="页码"></div>
        <button type="button" class="vs-api-pager__nav" id="cardkeyNextBtn" aria-label="下一页">下一页</button>
    </div>
    <div class="vs-api-list-total" id="cardkeyTotal"></div>
</div>

<div class="vs-overlay vs-overlay--form" id="cardkeyFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="cardkeyFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="cardkeyFormTitle">生成卡密</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">×</button>
        </header>
        <div class="vs-overlay__body vs-form" id="cardkeyFormBody">
            <div class="vs-field">
                <label class="vs-label" for="cardkeyCount">生成数量</label>
                <input type="number" class="vs-input" id="cardkeyCount" min="1" max="100" value="10" required>
                <?php vs_render_notice('tip', '', '单次最多 100 张，卡密为 20 位字母数字大小写。', array('field' => true, 'compact' => true)); ?>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="cardkeyPoints">充值积分</label>
                <input type="number" class="vs-input" id="cardkeyPoints" min="1" max="1000000" value="100" required>
            </div>
            <div class="vs-cardkey-gen-result" id="cardkeyGenResult" hidden>
                <div class="vs-cardkey-gen-result__head">
                    <span>已生成 <strong id="cardkeyGenCount">0</strong> 张</span>
                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="cardkeyCopyAllBtn">一键复制全部</button>
                </div>
                <textarea class="vs-input vs-cardkey-gen-codes" id="cardkeyGenCodes" readonly rows="8" aria-label="生成的卡密"></textarea>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--outline" data-overlay-close="1">关闭</button>
            <button type="button" class="vs-btn vs-btn--primary" id="cardkeyGenerateBtn">生成</button>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('vs-pick.js', 'finance-cardkey.js') : array()); ?>
