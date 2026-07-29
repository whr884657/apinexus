<?php
/**
 * 文件：admin/index.php
 * 作用：管理员控制台（KPI / 趋势 / TOP / 运营速览 / 最近调用）
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    // 面板 HTTP 可能较慢：尽早释放会话锁，避免 live/snapshot 互相堵死导致整页空白
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'refresh' || $action === 'snapshot') {
        DashboardStats::assertAjaxRateLimit($action);
        try {
            AjaxResponse::success('ok', array(
                'snapshot' => DashboardStats::consoleSnapshot($action === 'refresh'),
            ));
        } catch (Exception $e) {
            AjaxResponse::error('统计暂时不可用，请稍后重试');
        } catch (Throwable $e) {
            AjaxResponse::error('统计暂时不可用，请稍后重试');
        }
    }
    if ($action === 'live') {
        DashboardStats::assertAjaxRateLimit('live');
        try {
            AjaxResponse::success('ok', array(
                'live' => DashboardStats::consoleLiveTick(),
            ));
        } catch (Exception $e) {
            AjaxResponse::error('实时数据暂时不可用');
        } catch (Throwable $e) {
            AjaxResponse::error('实时数据暂时不可用');
        }
    }
    AjaxResponse::error('未知操作');
}

$mailEnabled = Config::isMailEnabled();
$boot = DashboardStats::consoleBootShell();
$liveInterval = DashboardStats::liveIntervalSeconds();

$refreshBtn = '<button type="button" class="vs-btn vs-btn--outline vs-btn--icon" id="dashRefreshBtn" title="刷新" aria-label="刷新">'
    . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<polyline points="23 4 23 10 17 10"></polyline>'
    . '<polyline points="1 20 1 14 7 14"></polyline>'
    . '<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>'
    . '</svg></button>';

vs_admin_layout_start('控制台', 'dashboard', $refreshBtn);
?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/assets/css/admin-dashboard.css?v=<?php echo vs_e(VS_VERSION); ?>">

<div id="adminDashPage"
     class="admin-dash-page"
     data-boot="<?php echo DashboardStats::bootAttrJson($boot); ?>"
     data-live-interval="<?php echo (int) $liveInterval; ?>">

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

    <section class="dash-grid dash-grid-bars" aria-label="排行与运营速览">
        <div class="vs-panel dash-panel dash-panel--top">
            <div class="vs-panel__header dash-panel__head">
                <h2 class="vs-panel__title">接口调用 TOP</h2>
                <span class="vs-badge vs-badge--info">今日</span>
            </div>
            <div class="vs-panel__body dash-panel__body--top">
                <div class="dash-bars-viewport" id="dashTopViewport">
                    <div class="dash-bars" id="dashTopBars"></div>
                </div>
            </div>
        </div>
        <div class="vs-panel dash-panel dash-panel--sys">
            <div class="vs-panel__header">
                <h2 class="vs-panel__title">运营速览</h2>
            </div>
            <div class="vs-panel__body">
                <div class="dash-sys" id="dashSys"></div>
            </div>
        </div>
    </section>

    <section class="dash-grid dash-grid-bottom" aria-label="最近调用与服务器">
        <div class="vs-panel dash-panel dash-panel--recent">
            <div class="vs-panel__header">
                <h2 class="vs-panel__title">最近调用记录</h2>
            </div>
            <div class="vs-panel__body">
                <div class="dash-recent-wrap" id="dashRecentTable"></div>
            </div>
        </div>
        <div class="vs-panel dash-panel dash-panel--server">
            <div class="vs-panel__header dash-panel__head">
                <h2 class="vs-panel__title">服务器</h2>
                <span class="vs-badge vs-badge--info" id="dashServerBadge">未配置</span>
            </div>
            <div class="vs-panel__body">
                <div class="dash-server" id="dashServer"></div>
            </div>
        </div>
    </section>
</div>

<?php vs_admin_layout_end(array('admin-dashboard.js')); ?>
