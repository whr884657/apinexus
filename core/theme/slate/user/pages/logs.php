<?php
/**
 * Slate 主题 · 用户调用日志
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
<div class="uc-logs" id="userLogsPage">
    <div class="vs-panel">
        <div class="vs-panel__header uc-logs__head">
            <h2 class="vs-panel__title">我的调用日志</h2>
            <div class="uc-logs__filters" role="group" aria-label="状态筛选">
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline is-active" data-ok-filter="">全部</button>
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-ok-filter="1">成功</button>
                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-ok-filter="0">失败</button>
            </div>
        </div>
        <div class="vs-panel__body">
            <div class="uc-logs__list" id="userLogsBody">
                <?php vs_render_loading('正在加载日志'); ?>
            </div>
        </div>
    </div>
    <div class="vs-api-list-footer" id="userLogsFooter" hidden>
        <div class="vs-api-pager" id="userLogsPager">
            <label class="vs-api-list-pagesize" for="userLogsPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select" id="userLogsPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <div class="vs-api-pager__navs" id="userLogsPagerNav"></div>
        </div>
        <div class="vs-api-list-total" id="userLogsTotal"></div>
    </div>
</div>
<?php endif; ?>
