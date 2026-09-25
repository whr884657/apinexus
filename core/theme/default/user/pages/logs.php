<?php
/**
 * 用户中心 · 调用日志（DOM/交互对齐管理端 admin/system/logs.php；无用户名搜索）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$tableReady = !empty($tableReady);
$detailEnabled = !empty($detailEnabled);
?>

<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '', '日志功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
<?php elseif (!$detailEnabled): ?>
    <?php vs_render_notice('info', '', '管理员未开启调用明细，暂无法查看个人调用列表。近 7 日趋势仍可在控制台查看。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-log-toolbar" id="logsToolbar">
    <div class="vs-log-search">
        <label class="vs-log-search__field" for="logsSearchField">
            <select class="vs-input vs-select vs-log-search__field-select" id="logsSearchField" data-vs-pick aria-label="搜索字段">
                <option value="id" selected>日志编号</option>
                <option value="apiid">接口 ID</option>
                <option value="apiname">接口名称</option>
                <option value="ip">IP</option>
                <option value="apikey">密钥</option>
            </select>
        </label>
        <input type="search" class="vs-input vs-log-search__input" id="logsSearchInput"
               placeholder="输入日志编号…" autocomplete="off">
        <button type="button" class="vs-btn vs-btn--primary" id="logsSearchBtn">搜索</button>
    </div>
    <div class="vs-finance-filters" role="group" aria-label="调用结果">
        <button type="button" class="vs-btn vs-btn--primary vs-log-filter is-active" data-ok="">全部</button>
        <button type="button" class="vs-btn vs-btn--default vs-log-filter" data-ok="1">成功</button>
        <button type="button" class="vs-btn vs-btn--default vs-log-filter" data-ok="0">失败</button>
    </div>
</div>

<div class="vs-panel vs-log-panel" id="logsPage">
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
        <button type="button" class="vs-api-pager__nav" id="logsPrevBtn" aria-label="上一页">上一页</button>
        <div class="vs-api-pager__nums" id="logsPagerNums" role="navigation" aria-label="页码"></div>
        <button type="button" class="vs-api-pager__nav" id="logsNextBtn" aria-label="下一页">下一页</button>
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
