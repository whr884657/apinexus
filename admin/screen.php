<?php
/**
 * 文件：admin/screen.php
 * 作用：数据大屏（实时 KPI / 小时趋势 / 飞线地图 / TOP / 滚动日志）
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'refresh' || $action === 'snapshot') {
        AjaxResponse::success('ok', array(
            'snapshot' => DashboardStats::screenSnapshot($action === 'refresh'),
        ));
    }
    if ($action === 'live') {
        AjaxResponse::success('ok', array(
            'live' => DashboardStats::screenLiveTick(),
        ));
    }
    AjaxResponse::error('未知操作');
}

$boot = DashboardStats::screenSnapshot(false);

vs_admin_layout_start(
    '数据大屏',
    'screen',
    '<button type="button" class="vs-btn vs-btn--default" id="dsFullscreenBtn">全屏</button>'
    . '<button type="button" class="vs-btn vs-btn--primary" id="dsRefreshBtn">刷新</button>'
);
?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/assets/css/admin-dashboard.css?v=<?php echo vs_e(VS_VERSION); ?>">

<div id="adminScreenPage"
     class="vs-datascreen vs-datascreen--light"
     data-boot="<?php echo vs_e(json_encode($boot, JSON_UNESCAPED_UNICODE)); ?>">

    <header class="ds-header">
        <div>
            <h2 class="ds-header__title">实时数据监控中心</h2>
            <p class="ds-header__sub">调用量 · 地理分布 · 接口排行</p>
        </div>
        <div class="ds-header__actions">
            <span class="ds-header__clock" id="dsClock" data-ds-clock><?php echo vs_e($boot['server_time']); ?></span>
            <button type="button" class="ds-theme-btn" id="dsThemeBtn" title="切换深浅色">深色</button>
        </div>
    </header>

    <section class="ds-stat-strip" id="dsKpi" aria-label="关键指标"></section>

    <section class="ds-main">
        <div class="ds-card">
            <div class="ds-card__head">
                <h3 class="ds-card__title">实时调用量趋势</h3>
                <div class="ds-card__extra">
                    <span class="ds-live-dot" aria-hidden="true"></span>
                    <span class="ds-chart-now" id="dsCurrentRpm">—</span>
                    <span class="ds-sub-title">次/分 · 近 24 小时</span>
                </div>
            </div>
            <div class="ds-chart-line" id="dsHourlyChart"></div>
        </div>

        <div class="ds-card ds-card--map">
            <div class="ds-card__head ds-map-head">
                <h3 class="ds-card__title">地理调用分布</h3>
                <div class="ds-map-toggle" id="dsMapToggle" role="group" aria-label="地图切换">
                    <button type="button" class="ds-map-toggle__btn is-active" data-map="china">中国地图</button>
                    <button type="button" class="ds-map-toggle__btn" data-map="world">世界地图</button>
                </div>
            </div>
            <div class="ds-map" id="dsMap" aria-label="飞线地图">
                <div class="ds-map-tip" id="dsMapTip" hidden></div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card__head">
                <h3 class="ds-card__title">接口调用量 TOP</h3>
                <span class="vs-badge vs-badge--info">今日</span>
            </div>
            <div class="ds-bar-list" id="dsTopBars"></div>
        </div>
    </section>

    <section class="ds-card ds-card--logs">
        <div class="ds-card__head">
            <h3 class="ds-card__title">实时调用日志</h3>
            <span class="ds-sub-title"><span class="ds-live-dot" aria-hidden="true"></span>滚动更新</span>
        </div>
        <div class="ds-log-stream" id="dsLogStream" aria-live="polite"></div>
    </section>
</div>

<?php vs_admin_layout_end(array('admin-screen.js')); ?>
