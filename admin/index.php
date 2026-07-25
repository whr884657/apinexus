<?php
/**
 * 文件：admin/index.php
 * 作用：管理员控制台（KPI / 趋势 / TOP10 / 系统概览 / 最近调用）
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'refresh' || $action === 'snapshot') {
        AjaxResponse::success('ok', array(
            'snapshot' => DashboardStats::consoleSnapshot($action === 'refresh'),
        ));
    }
    AjaxResponse::error('未知操作');
}

$mailEnabled = Config::isMailEnabled();
$boot = DashboardStats::consoleSnapshot(false);

vs_admin_layout_start(
    '控制台',
    'dashboard',
    '<button type="button" class="vs-btn vs-btn--primary" id="dashRefreshBtn">刷新</button>'
);
?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/assets/css/admin-dashboard.css?v=<?php echo vs_e(VS_VERSION); ?>">

<div id="adminDashPage"
     class="admin-dash-page"
     data-boot="<?php echo vs_e(json_encode($boot, JSON_UNESCAPED_UNICODE)); ?>"
     data-logs-url="<?php echo vs_e($vsBase); ?>/admin/system/logs">

    <?php if (!$mailEnabled): ?>
        <?php
        vs_render_notice(
            'warning',
            '邮箱发信尚未配置',
            '忘记密码功能将不可用。<a href="' . vs_e($vsBase) . '/admin/settings" class="vs-notice__link">前往系统设置</a>',
            array('allow_html' => true, 'compact' => true)
        );
        ?>
    <?php endif; ?>

    <header class="dash-header">
        <p class="dash-header__subtitle">欢迎回来，<?php echo vs_e($vsAdmin ? $vsAdmin['username'] : '管理员'); ?></p>
        <span class="dash-header__date" data-dash-date><?php echo vs_e($boot['server_time'] . ' · ' . $boot['weekday']); ?></span>
    </header>

    <section class="dash-kpi-grid" id="dashKpiGrid" aria-label="关键指标"></section>

    <section class="dash-grid dash-grid-trend" aria-label="趋势图表">
        <div class="vs-panel dash-panel">
            <div class="vs-panel__header dash-panel__head">
                <h2 class="vs-panel__title">调用类型趋势</h2>
                <div class="dash-chart-legend" id="dashTypeLegend"></div>
            </div>
            <div class="vs-panel__body">
                <div class="dash-chart-wrap" id="dashTypeChart"></div>
            </div>
        </div>
        <div class="vs-panel dash-panel">
            <div class="vs-panel__header dash-panel__head">
                <h2 class="vs-panel__title">调用成功 / 失败率</h2>
                <div class="dash-chart-legend" id="dashRateLegend"></div>
            </div>
            <div class="vs-panel__body">
                <div class="dash-chart-wrap" id="dashRateChart"></div>
            </div>
        </div>
    </section>

    <section class="dash-grid dash-grid-bars" aria-label="排行与概览">
        <div class="vs-panel dash-panel">
            <div class="vs-panel__header dash-panel__head">
                <h2 class="vs-panel__title">接口调用 TOP 10</h2>
                <span class="vs-badge vs-badge--info">今日</span>
            </div>
            <div class="vs-panel__body">
                <div class="dash-bars" id="dashTopBars"></div>
            </div>
        </div>
        <div class="vs-panel dash-panel">
            <div class="vs-panel__header">
                <h2 class="vs-panel__title">系统概览</h2>
            </div>
            <div class="vs-panel__body">
                <div class="dash-sys" id="dashSys"></div>
            </div>
        </div>
    </section>

    <section class="vs-panel dash-panel">
        <div class="vs-panel__header dash-panel__head dash-panel__head--stack">
            <div class="dash-records-head">
                <h2 class="vs-panel__title">最近调用记录</h2>
                <div class="dash-records-filters" id="dashRecentFilters">
                    <button type="button" class="dash-records-filter is-active" data-filter="all">全部</button>
                    <button type="button" class="dash-records-filter" data-filter="success">成功</button>
                    <button type="button" class="dash-records-filter" data-filter="error">调用错误</button>
                </div>
            </div>
            <a class="dash-panel-link" href="<?php echo vs_e($vsBase); ?>/admin/system/logs">查看全部日志</a>
        </div>
        <div class="vs-panel__body">
            <div class="dash-table-wrap" id="dashRecentTable"></div>
        </div>
    </section>
</div>

<?php vs_admin_layout_end(array('admin-dashboard.js')); ?>
