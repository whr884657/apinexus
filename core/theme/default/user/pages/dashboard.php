<?php
/**
 * 默认主题 · 用户中心控制台
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$dash = isset($dash) && is_array($dash) ? $dash : array();
$checkinBanner = isset($checkinBanner) && is_array($checkinBanner) ? $checkinBanner : array();
$avatarPreview = isset($avatarPreview) ? (string) $avatarPreview : '';
$displayName = isset($displayName) ? (string) $displayName : '用户';
$isDeveloper = !empty($isDeveloper);
$helloLine = isset($helloLine) ? (string) $helloLine : ('欢迎回来，' . $displayName);
$helloHint = isset($helloHint) ? (string) $helloHint : '';
$helloSlot = isset($helloSlot) ? (string) $helloSlot : '';

$stat7 = isset($dash['stat7']) && is_array($dash['stat7']) ? $dash['stat7'] : array();
$days = isset($stat7['days']) && is_array($stat7['days']) ? $stat7['days'] : array();
$labels = isset($days['labels']) && is_array($days['labels']) ? $days['labels'] : array();
$callsSeries = isset($days['calls']) && is_array($days['calls']) ? $days['calls'] : array();
$costSeries = isset($days['cost']) && is_array($days['cost']) ? $days['cost'] : array();
$labelShort = array();
foreach ($labels as $lb) {
    $lb = (string) $lb;
    $labelShort[] = strlen($lb) >= 10 ? substr($lb, 5) : $lb;
}
$todayCalls = isset($stat7['today_calls']) ? (int) $stat7['today_calls'] : 0;
$todayCostFmt = isset($stat7['today_cost_fmt']) ? (string) $stat7['today_cost_fmt'] : '0';
$avgCalls = isset($stat7['avg_calls']) ? $stat7['avg_calls'] : 0;
$topList = isset($stat7['top']) && is_array($stat7['top']) ? $stat7['top'] : array();
$recent = isset($dash['recent']) && is_array($dash['recent']) ? $dash['recent'] : array();
$detailEnabled = !empty($dash['detail_enabled']);
$maxTopCalls = 1;
foreach ($topList as $t) {
    $c = isset($t['calls']) ? (int) $t['calls'] : 0;
    if ($c > $maxTopCalls) {
        $maxTopCalls = $c;
    }
}

$chartBoot = array(
    'labels' => $labelShort,
    'calls'  => array_map('intval', $callsSeries),
    'cost'   => array_map(function ($v) { return round((float) $v, 4); }, $costSeries),
);
$bootAttr = htmlspecialchars(json_encode($chartBoot, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<?php if (!empty($checkinBanner['show_banner'])): ?>
<div class="uc-checkin-banner uc-motion" id="ucCheckinBanner" role="region" aria-label="每日签到">
    <span class="uc-checkin-banner__label">每日签到</span>
    <button type="button" class="vs-btn vs-btn--primary vs-btn--sm uc-checkin-banner__btn" id="ucCheckinBtn">签到</button>
</div>
<?php endif; ?>

<section class="uc-dash" id="ucDashboard" data-theme="default" data-chart-boot="<?php echo $bootAttr; ?>"<?php echo $helloSlot !== '' ? ' data-hello-slot="' . vs_e($helloSlot) . '"' : ''; ?>>
    <header class="uc-dash__hero uc-motion">
        <div class="uc-dash__hero-main">
            <img class="uc-dash__avatar" src="<?php echo vs_e($avatarPreview); ?>" alt="用户头像" width="56" height="56" loading="lazy" referrerpolicy="no-referrer">
            <div class="uc-dash__hero-text">
                <h2 class="uc-dash__hello" data-uc-hello><?php echo vs_e($helloLine); ?></h2>
                <?php if ($helloHint !== ''): ?>
                    <p class="uc-dash__hint" data-uc-hello-hint data-text="<?php echo vs_e($helloHint); ?>"></p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="uc-dash__kpi<?php echo $isDeveloper ? ' is-six' : ' is-five'; ?>" aria-label="关键指标">
        <div class="uc-dash__kpi-card uc-motion" data-uc-press>
            <span class="uc-dash__kpi-label">接口总数</span>
            <strong class="uc-dash__kpi-value" data-field="api_total"><?php echo (int) (isset($dash['api_total']) ? $dash['api_total'] : 0); ?></strong>
            <span class="uc-dash__kpi-meta">已通过 <?php echo (int) (isset($dash['api_approved']) ? $dash['api_approved'] : 0); ?> · 待审 <?php echo (int) (isset($dash['api_pending']) ? $dash['api_pending'] : 0); ?></span>
        </div>
        <div class="uc-dash__kpi-card uc-motion" data-uc-press>
            <span class="uc-dash__kpi-label">API 令牌</span>
            <strong class="uc-dash__kpi-value" data-field="key_total"><?php echo (int) (isset($dash['key_total']) ? $dash['key_total'] : 0); ?></strong>
            <span class="uc-dash__kpi-meta">当前密钥数量</span>
        </div>
        <div class="uc-dash__kpi-card uc-motion" data-uc-press>
            <span class="uc-dash__kpi-label">积分余额</span>
            <strong class="uc-dash__kpi-value" data-field="points_kpi"><?php echo vs_e(isset($dash['points']) ? $dash['points'] : '0'); ?></strong>
            <span class="uc-dash__kpi-meta">当前可用余额</span>
        </div>
        <div class="uc-dash__kpi-card uc-motion" data-uc-press>
            <span class="uc-dash__kpi-label">累计消耗</span>
            <strong class="uc-dash__kpi-value" data-field="points_spent"><?php echo vs_e(isset($dash['points_spent']) ? $dash['points_spent'] : '0'); ?></strong>
            <span class="uc-dash__kpi-meta">历史扣减合计</span>
        </div>
        <?php if ($isDeveloper): ?>
        <div class="uc-dash__kpi-card uc-motion" data-uc-press>
            <span class="uc-dash__kpi-label">发布被调用</span>
            <strong class="uc-dash__kpi-value" data-field="api_calls"><?php echo (int) (isset($dash['api_calls']) ? $dash['api_calls'] : 0); ?></strong>
            <span class="uc-dash__kpi-meta">我发布的接口累计</span>
        </div>
        <?php endif; ?>
        <div class="uc-dash__kpi-card uc-motion" data-uc-press>
            <span class="uc-dash__kpi-label">我的调用</span>
            <strong class="uc-dash__kpi-value" data-field="key_calls"><?php echo (int) (isset($dash['key_calls']) ? $dash['key_calls'] : 0); ?></strong>
            <span class="uc-dash__kpi-meta">令牌累计调用次数</span>
        </div>
    </div>

    <div class="uc-dash__daykpi uc-motion" aria-label="近七日概览">
        <div class="uc-dash__daykpi-card">
            <span class="uc-dash__daykpi-label">今日请求</span>
            <strong class="uc-dash__daykpi-value"><?php echo (int) $todayCalls; ?></strong>
        </div>
        <div class="uc-dash__daykpi-card">
            <span class="uc-dash__daykpi-label">今日积分消耗</span>
            <strong class="uc-dash__daykpi-value"><?php echo vs_e($todayCostFmt); ?></strong>
        </div>
        <div class="uc-dash__daykpi-card">
            <span class="uc-dash__daykpi-label">日均请求</span>
            <strong class="uc-dash__daykpi-value"><?php echo vs_e((string) $avgCalls); ?></strong>
        </div>
    </div>

    <section class="uc-dash__charts uc-motion" aria-label="近七日趋势">
        <div class="vs-panel uc-dash__panel">
            <div class="vs-panel__header uc-dash__panel-head">
                <h2 class="vs-panel__title">近 7 日调用量</h2>
                <div class="uc-dash__legend">
                    <span class="uc-dash__legend-item"><i class="uc-dash__legend-line uc-dash__legend-line--calls"></i>调用次数</span>
                </div>
            </div>
            <div class="vs-panel__body">
                <div class="uc-dash__chart-wrap" id="ucDashCallsChart"></div>
            </div>
        </div>
        <div class="vs-panel uc-dash__panel">
            <div class="vs-panel__header uc-dash__panel-head">
                <h2 class="vs-panel__title">近 7 日积分消耗</h2>
                <div class="uc-dash__legend">
                    <span class="uc-dash__legend-item"><i class="uc-dash__legend-line uc-dash__legend-line--cost"></i>积分</span>
                </div>
            </div>
            <div class="vs-panel__body">
                <div class="uc-dash__chart-wrap" id="ucDashCostChart"></div>
            </div>
        </div>
    </section>

    <section class="uc-dash__bottom uc-motion" aria-label="近期与热门">
        <div class="vs-panel uc-dash__panel">
            <div class="vs-panel__header uc-dash__panel-head">
                <h2 class="vs-panel__title">近期调用</h2>
                <a class="uc-dash__more" href="<?php echo vs_e(vs_base_url() . '/user/logs'); ?>">全部日志</a>
            </div>
            <div class="vs-panel__body">
                <?php if (!$detailEnabled): ?>
                    <p class="uc-dash__empty">管理员未开启调用明细，列表暂不可用；上方趋势仍可统计。</p>
                <?php elseif (empty($recent)): ?>
                    <p class="uc-dash__empty">暂无调用记录</p>
                <?php else: ?>
                    <div class="uc-dash__recent" role="list">
                        <div class="uc-dash__recent-row uc-dash__recent-row--head" role="presentation">
                            <span>接口</span><span>时间</span><span>IP</span><span>状态</span>
                        </div>
                        <?php foreach ($recent as $row): ?>
                            <div class="uc-dash__recent-row" role="listitem">
                                <span class="uc-dash__recent-name" title="<?php echo vs_e(isset($row['apiname']) ? $row['apiname'] : ''); ?>"><?php echo vs_e(isset($row['apiname']) && $row['apiname'] !== '' ? $row['apiname'] : '—'); ?></span>
                                <span class="uc-dash__recent-time"><?php echo vs_e(isset($row['createtime']) ? $row['createtime'] : ''); ?></span>
                                <span class="uc-dash__recent-ip"><?php echo vs_e(isset($row['ip']) && $row['ip'] !== '' ? $row['ip'] : '—'); ?></span>
                                <span class="uc-dash__recent-ok <?php echo vs_e(isset($row['ok_class']) ? $row['ok_class'] : ''); ?>"><?php echo vs_e(isset($row['ok_label']) ? $row['ok_label'] : ''); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="vs-panel uc-dash__panel">
            <div class="vs-panel__header">
                <h2 class="vs-panel__title">热门接口</h2>
            </div>
            <div class="vs-panel__body">
                <?php if (empty($topList)): ?>
                    <p class="uc-dash__empty">近 7 日暂无调用排行</p>
                <?php else: ?>
                    <div class="uc-dash__bars">
                        <?php foreach ($topList as $i => $row):
                            $rank = $i + 1;
                            $calls = isset($row['calls']) ? (int) $row['calls'] : 0;
                            $pct = max(4, (int) round(($calls / $maxTopCalls) * 100));
                            $name = isset($row['name']) ? (string) $row['name'] : ('接口 #' . (int) (isset($row['apiid']) ? $row['apiid'] : 0));
                            $rankCls = $rank <= 3 ? (' is-' . $rank) : '';
                            ?>
                            <div class="uc-dash__bar">
                                <div class="uc-dash__bar-meta">
                                    <span class="uc-dash__bar-rank<?php echo $rankCls; ?>"><?php echo (int) $rank; ?></span>
                                    <span class="uc-dash__bar-name" title="<?php echo vs_e($name); ?>"><?php echo vs_e($name); ?></span>
                                    <span class="uc-dash__bar-count"><?php echo (int) $calls; ?></span>
                                </div>
                                <div class="uc-dash__bar-track"><div class="uc-dash__bar-fill" style="width:<?php echo (int) $pct; ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</section>
