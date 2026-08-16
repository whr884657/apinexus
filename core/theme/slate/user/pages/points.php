<?php
/**
 * Slate 主题 · 用户积分变动页视图
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$ready = !empty($ready);
$balance = isset($balance) ? $balance : 0;
?>
<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', '积分功能尚未就绪。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-points">
    <div class="vs-points-hero">
        <div class="vs-points-hero__main">
            <div class="vs-points-hero__label">当前余额</div>
            <div class="vs-points-hero__value" id="pointsBalance"><?php echo vs_e(PayConfig::fmtPoints($balance)); ?></div>
        </div>
        <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_site_path('/user/recharge')); ?>">去充值</a>
    </div>

    <div class="vs-panel vs-finance-panel">
        <div class="vs-finance-table vs-points-list" id="pointsListBody">
            <?php vs_render_loading('正在加载积分变动'); ?>
        </div>
    </div>
    <div class="vs-api-list-footer" id="pointsFooter" hidden>
        <div class="vs-api-pager" id="pointsPager">
            <label class="vs-api-list-pagesize" for="userPointsPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select" id="userPointsPageSize" data-vs-pick="sheet">
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
</div>
<?php endif; ?>
