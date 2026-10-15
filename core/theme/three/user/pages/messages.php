<?php
/**
 * 主题 · 全部站内公告（不进侧栏；搜索 + 横幅列表 + 底部分页）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$tableReady = !empty($tableReady);
$endpoint = vs_site_path('/user/messages');
?>

<?php if (!$tableReady): ?>
    <?php vs_render_notice('warning', '', '站内公告尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
<?php else: ?>
<div class="uc-msg-page" id="ucMsgPage" data-endpoint="<?php echo vs_e($endpoint); ?>">
    <div class="uc-msg-toolbar">
        <div class="uc-msg-search">
            <input type="search" class="vs-input uc-msg-search__input" id="ucMsgSearchInput"
                   placeholder="搜索公告标题…" autocomplete="off" maxlength="80">
            <button type="button" class="vs-btn vs-btn--primary" id="ucMsgSearchBtn">搜索</button>
        </div>
    </div>
    <div class="uc-msg-list" id="ucMsgList" role="list"></div>
    <p class="uc-dash__empty" id="ucMsgEmpty" hidden>暂无站内公告</p>
    <div class="vs-api-list-footer" id="ucMsgFooter" hidden>
        <div class="vs-api-pager" id="ucMsgPager">
            <label class="vs-api-list-pagesize" for="ucMsgPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select" id="ucMsgPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <div class="vs-api-pager__navs">
                <button type="button" class="vs-api-pager__nav" id="ucMsgPrevBtn" aria-label="上一页">上一页</button>
                <div class="vs-api-pager__nums" id="ucMsgPagerNums" role="navigation" aria-label="页码"></div>
                <button type="button" class="vs-api-pager__nav" id="ucMsgNextBtn" aria-label="下一页">下一页</button>
            </div>
        </div>
        <div class="vs-api-list-total" id="ucMsgTotal"></div>
    </div>
</div>
<?php endif; ?>
