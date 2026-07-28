<?php
/**
 * 文件：admin/screen.php
 * 作用：实时数据监控中心（ECharts 中国/世界地图飞线 + 四角面板 + 实时日志）
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'refresh' || $action === 'snapshot') {
        DashboardStats::assertAjaxRateLimit($action);
        try {
            AjaxResponse::success('ok', array(
                'snapshot' => DashboardStats::screenSnapshot($action === 'refresh'),
            ));
        } catch (Exception $e) {
            AjaxResponse::error('统计暂时不可用，请稍后重试');
        }
    }
    if ($action === 'live') {
        DashboardStats::assertAjaxRateLimit('live');
        try {
            AjaxResponse::success('ok', array(
                'live' => DashboardStats::screenLiveTick(),
            ));
        } catch (Exception $e) {
            AjaxResponse::error('实时数据暂时不可用');
        }
    }
    AjaxResponse::error('未知操作');
}

try {
    $boot = DashboardStats::screenSnapshot(false);
} catch (Exception $e) {
    $boot = array(
        'server_time'   => date('Y-m-d H:i:s'),
        'live_interval' => 5,
        'kpi'           => array(),
        'hourly'        => array('labels' => array(), 'series' => array()),
        'top_apis'      => array(),
        'geo'           => array(
            'china' => array('cities' => array(), 'flows' => array(), 'hub' => array()),
            'world' => array('cities' => array(), 'flows' => array(), 'hub' => array()),
        ),
        'recent'        => array(),
        'current_rpm'   => 0,
    );
}

vs_admin_layout_start(
    '数据大屏',
    'screen',
    '<button type="button" class="vs-btn vs-btn--default" id="dsFullscreenBtn">全屏</button>'
    . '<button type="button" class="vs-btn vs-btn--primary" id="dsRefreshBtn">刷新</button>'
);
?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/assets/css/admin-dashboard.css?v=<?php echo vs_e(VS_VERSION); ?>">
<script src="<?php echo vs_e($vsBase); ?>/assets/vendor/echarts/echarts.min.js?v=5.4.3"></script>

<div id="adminScreenPage"
     class="vs-datascreen vs-datascreen--light"
     data-boot="<?php echo DashboardStats::bootAttrJson($boot); ?>"
     data-map-china="<?php echo vs_e('https://cdn.jsdelivr.net/gh/apache/echarts@5.4.3/test/data/map/json/china.json'); ?>"
     data-map-world="<?php echo vs_e('https://cdn.jsdelivr.net/gh/apache/echarts@5.4.3/test/data/map/json/world.json'); ?>">

    <header class="ds-header">
        <div class="ds-header__brand">
            <h2 class="ds-header__title">实时数据监控中心</h2>
            <p class="ds-header__sub">调用量 · 地理分布 · 接口排行</p>
        </div>
        <div class="ds-header__actions">
            <span class="ds-header__clock" id="dsClock" data-ds-clock><?php echo vs_e($boot['server_time']); ?></span>
            <span class="ds-header__live"><span class="ds-live-dot" aria-hidden="true"></span>实时</span>
            <button type="button" class="ds-icon-btn" id="dsThemeBtn" title="切换深浅色" aria-label="切换深浅色"></button>
        </div>
    </header>

    <div class="ds-stage">
        <div class="ds-map-panel">
            <div class="ds-map-float-head">
                <h3 class="ds-card__title">地理调用分布</h3>
                <div class="ds-map-toggle" id="dsMapToggle" role="group" aria-label="地图切换">
                    <button type="button" class="ds-map-toggle__btn is-active" data-map="china">中国地图</button>
                    <button type="button" class="ds-map-toggle__btn" data-map="world">世界地图</button>
                </div>
            </div>
            <div class="ds-map-chart" id="dsMapChart" role="img" aria-label="调用飞线地图"></div>
            <div class="ds-map-status" id="dsMapStatus" hidden>地图加载中…</div>
        </div>

        <aside class="ds-corner ds-corner--tl" aria-label="核心指标">
            <div class="ds-card__head">
                <h3 class="ds-card__title">核心指标</h3>
            </div>
            <div class="ds-kpi-grid" id="dsKpi"></div>
        </aside>

        <aside class="ds-corner ds-corner--tr" aria-label="接口调用量 TOP">
            <div class="ds-card__head">
                <h3 class="ds-card__title">接口调用量 TOP</h3>
                <span class="ds-badge">今日</span>
            </div>
            <div class="ds-bar-list" id="dsTopBars"></div>
        </aside>

        <aside class="ds-corner ds-corner--bl" aria-label="实时调用量趋势">
            <div class="ds-card__head">
                <h3 class="ds-card__title">实时调用量趋势</h3>
                <div class="ds-card__extra">
                    <span class="ds-live-dot" aria-hidden="true"></span>
                    <span class="ds-chart-now" id="dsCurrentRpm">—</span>
                    <span class="ds-sub-title">次/分 · 近 24 小时</span>
                </div>
            </div>
            <div class="ds-chart-line" id="dsHourlyChart"></div>
        </aside>

        <aside class="ds-corner ds-corner--br" aria-label="实时调用日志">
            <div class="ds-card__head">
                <h3 class="ds-card__title">实时调用日志</h3>
                <span class="ds-sub-title"><span class="ds-live-dot" aria-hidden="true"></span>滚动更新</span>
            </div>
            <div class="ds-log-stream" id="dsLogStream" aria-live="polite"></div>
        </aside>
    </div>
</div>

<?php vs_admin_layout_end(array('admin-screen.js')); ?>
