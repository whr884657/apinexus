<?php
/**
 * 文件：admin/system/logs.php
 * 作用：API 调用日志查询（时间窗 / keyset 翻页 / 搜索 / 抽屉详情）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'list') {
        if (!ApiLogManager::tableReady()) {
            AjaxResponse::error('日志表未就绪');
        }
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
        $q = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        $ok = array_key_exists('ok', $_POST) && $_POST['ok'] !== '' ? (int) $_POST['ok'] : null;
        $apiid = isset($_POST['apiid']) ? (int) $_POST['apiid'] : 0;
        $days = isset($_POST['days']) ? (int) $_POST['days'] : ApiLogManager::queryDaysDefault();
        $beforeId = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
        $data = ApiLogManager::listPaged(array(
            'page'      => $page,
            'pagesize'  => $pagesize,
            'q'         => $q,
            'ok'        => $ok,
            'apiid'     => $apiid,
            'days'      => $days,
            'before_id' => $beforeId,
        ));
        AjaxResponse::success('ok', $data);
    }

    if ($action === 'detail') {
        if (!ApiLogManager::tableReady()) {
            AjaxResponse::error('日志表未就绪');
        }
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $row = ApiLogManager::findById($id);
        if (!$row) {
            AjaxResponse::error('记录不存在');
        }
        AjaxResponse::success('ok', array('row' => $row));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiLogManager::tableReady();
$queryDays = ApiLogManager::queryDaysDefault();
$boot = array(
    'list'           => array(),
    'total'          => 0,
    'page'           => 1,
    'pagesize'       => 20,
    'days'           => $queryDays,
    'before_id'      => 0,
    'next_before_id' => 0,
    'has_more'       => false,
    'total_approx'   => false,
);
if ($tableReady) {
    $boot = ApiLogManager::listPaged(array(
        'page'     => 1,
        'pagesize' => 20,
        'days'     => $queryDays,
    ));
}

vs_admin_layout_start('日志查询', 'logs');
?>
<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '尚未就绪', '请先完成系统升级以同步调用日志表。', array('compact' => true)); ?>
<?php else: ?>
<?php
vs_render_notice(
    'tip',
    '',
    '默认仅查询近 ' . (int) $queryDays . ' 天日志。若已启用冷热归档，超出热数据天数的记录从本机归档合并读出；相关开关与计划任务在系统设置 → API 日志。',
    array('compact' => true)
);
?>
<div class="vs-log-toolbar" id="logsToolbar">
    <div class="vs-log-search">
        <input type="search" class="vs-input vs-log-search__input" id="logsSearchInput"
               placeholder="搜索接口名 / IP / 路径 / 密钥 / 用户…" autocomplete="off">
        <button type="button" class="vs-btn vs-btn--primary" id="logsSearchBtn">搜索</button>
    </div>
    <label class="vs-api-list-pagesize" for="logsDays">
        <span class="vs-api-list-pagesize__label">近</span>
        <select class="vs-input vs-select" id="logsDays" data-vs-pick>
            <?php
            $dayOpts = array(1, 3, 7, 14, 30, 90, 180, 365);
            foreach ($dayOpts as $d):
                $sel = ((int) $queryDays === (int) $d) ? ' selected' : '';
            ?>
                <option value="<?php echo (int) $d; ?>"<?php echo $sel; ?>><?php echo (int) $d; ?> 天</option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="vs-finance-filters" role="group" aria-label="调用结果">
        <button type="button" class="vs-btn vs-btn--primary vs-log-filter is-active" data-ok="">全部</button>
        <button type="button" class="vs-btn vs-btn--default vs-log-filter" data-ok="1">成功</button>
        <button type="button" class="vs-btn vs-btn--default vs-log-filter" data-ok="0">失败</button>
    </div>
    <button type="button" class="vs-btn vs-btn--outline vs-finance-refresh" id="logsRefreshBtn">刷新</button>
</div>

<div class="vs-panel vs-log-panel" id="logsPage"
     data-boot="<?php echo vs_e(json_encode($boot, JSON_UNESCAPED_UNICODE)); ?>"
     data-default-days="<?php echo (int) $queryDays; ?>">
    <div class="vs-log-list" id="logsListBody">
        <?php vs_render_loading('正在加载日志'); ?>
    </div>
</div>
<div class="vs-api-list-footer" id="logsFooter" hidden>
    <div class="vs-api-pager" id="logsPager">
        <label class="vs-api-list-pagesize" for="logsPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select" id="logsPageSize" data-vs-pick="sheet">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </label>
        <div class="vs-api-pager__navs" id="logsPagerNav"></div>
    </div>
    <div class="vs-api-list-total" id="logsTotal"></div>
</div>

<div class="vs-overlay vs-overlay--lg" id="logsDetailOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="logsDetailTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="logsDetailTitle">调用详情</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body" id="logsDetailBody">
            <?php vs_render_loading('正在加载详情', array('compact' => true)); ?>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">关闭</button>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php vs_admin_layout_end($tableReady ? array('system-logs.js') : array()); ?>
