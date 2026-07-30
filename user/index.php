<?php
/**
 * 文件：user/index.php
 * 作用：用户中心控制台（仪表盘）；双主题共用结构，每日签到细条横幅
 *
 * 无快捷入口；浏览公开接口请走前台首页 / 全部接口页。
 * 开发者 5 KPI（含「发布被调用」）；普通用户 4 KPI。
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'checkin') {
        $result = FrontendUser::doCheckin();
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '签到失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : '签到成功',
            array(
                'amount' => isset($result['amount']) ? $result['amount'] : 0,
                'points' => isset($result['points']) ? $result['points'] : '',
                'banner' => FrontendUser::checkinBanner(),
                'stats'  => FrontendUser::dashboardStats(),
            )
        );
    }
    AjaxResponse::error('无效操作', 400);
}

$avatarPreview = UserAvatar::resolve($vsUser);
$checkinBanner = class_exists('FrontendUser')
    ? FrontendUser::checkinBanner()
    : array('show_banner' => false);
$dash = FrontendUser::dashboardStats();
$displayName = $vsUser ? (string) $vsUser['username'] : '用户';
$isDeveloper = !empty($dash['can_publish_api']);

vs_user_layout_start('控制台', 'dashboard');
?>

<?php if (!empty($checkinBanner['show_banner'])): ?>
<div class="uc-checkin-banner" id="ucCheckinBanner" role="region" aria-label="每日签到">
    <span class="uc-checkin-banner__label">每日签到</span>
    <button type="button" class="vs-btn vs-btn--primary vs-btn--sm uc-checkin-banner__btn" id="ucCheckinBtn">签到</button>
</div>
<?php endif; ?>

<section class="uc-dash" id="ucDashboard" data-theme="<?php echo vs_e(class_exists('ThemeManager') ? ThemeManager::activeId() : 'default'); ?>">
    <header class="uc-dash__hero">
        <div class="uc-dash__hero-main">
            <img class="uc-dash__avatar" src="<?php echo vs_e($avatarPreview); ?>" alt="" width="56" height="56" loading="lazy" referrerpolicy="no-referrer">
            <div class="uc-dash__hero-text">
                <h2 class="uc-dash__hello">欢迎回来，<?php echo vs_e($displayName); ?></h2>
                <?php if (!empty($dash['bound_admin'])): ?>
                    <p class="uc-dash__sub">
                        <span class="uc-dash__chip uc-dash__chip--bound">与管理员同权绑定</span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="uc-dash__kpi<?php echo $isDeveloper ? ' is-five' : ''; ?>" aria-label="关键指标">
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">接口总数</span>
            <strong class="uc-dash__kpi-value" data-field="api_total"><?php echo (int) $dash['api_total']; ?></strong>
            <span class="uc-dash__kpi-meta">已通过 <?php echo (int) $dash['api_approved']; ?> · 待审 <?php echo (int) $dash['api_pending']; ?></span>
        </div>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">API 令牌</span>
            <strong class="uc-dash__kpi-value" data-field="key_total"><?php echo (int) $dash['key_total']; ?></strong>
            <span class="uc-dash__kpi-meta">当前密钥数量</span>
        </div>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">积分</span>
            <strong class="uc-dash__kpi-value" data-field="points_kpi"><?php echo vs_e($dash['points']); ?></strong>
            <span class="uc-dash__kpi-meta">当前可用余额</span>
        </div>
        <?php if ($isDeveloper): ?>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">发布被调用</span>
            <strong class="uc-dash__kpi-value" data-field="api_calls"><?php echo (int) $dash['api_calls']; ?></strong>
            <span class="uc-dash__kpi-meta">我发布的接口累计</span>
        </div>
        <?php endif; ?>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">我的调用</span>
            <strong class="uc-dash__kpi-value" data-field="key_calls"><?php echo (int) $dash['key_calls']; ?></strong>
            <span class="uc-dash__kpi-meta">令牌累计调用次数</span>
        </div>
    </div>
</section>

<?php
vs_user_layout_end(!empty($checkinBanner['show_banner'])
    ? array('user-checkin.js')
    : array());
?>
