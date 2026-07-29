<?php
/**
 * 文件：user/index.php
 * 作用：用户中心首页（控制台）；默认主题支持每日签到横幅
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
$isDefaultTheme = class_exists('ThemeManager') && ThemeManager::activeId() === 'default';
$checkinBanner = ($isDefaultTheme && class_exists('FrontendUser'))
    ? FrontendUser::checkinBanner()
    : array('show_banner' => false);
$dash = FrontendUser::dashboardStats();
$base = rtrim(vs_base_url(), '/');
$displayName = $vsUser ? (string) $vsUser['username'] : '用户';

vs_user_layout_start('控制台', 'dashboard');
?>

<?php if (!empty($checkinBanner['show_banner'])): ?>
<div class="uc-checkin-banner" id="ucCheckinBanner" role="region" aria-label="每日签到">
    <div class="uc-checkin-banner__text">
        <strong class="uc-checkin-banner__title">今日尚未签到</strong>
        <span class="uc-checkin-banner__desc">签到可随机获得 <?php echo (int) $checkinBanner['min']; ?>～<?php echo (int) $checkinBanner['max']; ?> 积分</span>
    </div>
    <button type="button" class="vs-btn vs-btn--primary uc-checkin-banner__btn" id="ucCheckinBtn">立即签到</button>
</div>
<?php endif; ?>

<section class="uc-dash" id="ucDashboard" data-theme="<?php echo vs_e(class_exists('ThemeManager') ? ThemeManager::activeId() : 'default'); ?>">
    <header class="uc-dash__hero">
        <div class="uc-dash__hero-main">
            <img class="uc-dash__avatar" src="<?php echo vs_e($avatarPreview); ?>" alt="" width="56" height="56" loading="lazy" referrerpolicy="no-referrer">
            <div class="uc-dash__hero-text">
                <h2 class="uc-dash__hello">欢迎回来，<?php echo vs_e($displayName); ?></h2>
                <p class="uc-dash__sub">
                    <?php echo vs_e($dash['role_label'] !== '' ? $dash['role_label'] : '用户'); ?>
                    <?php if (!empty($dash['bound_admin'])): ?>
                        <span class="uc-dash__chip uc-dash__chip--bound">已绑定管理员同权</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="uc-dash__hero-points">
            <span class="uc-dash__points-label">积分余额</span>
            <strong class="uc-dash__points-value" data-field="points"><?php echo vs_e($dash['points']); ?></strong>
            <a class="uc-dash__points-link" href="<?php echo vs_e($base . '/user/recharge'); ?>">去充值</a>
        </div>
    </header>

    <div class="uc-dash__stats">
        <div class="uc-dash__stat">
            <span class="uc-dash__stat-label">接口总数</span>
            <strong class="uc-dash__stat-value" data-field="api_total"><?php echo (int) $dash['api_total']; ?></strong>
            <span class="uc-dash__stat-hint">已通过 <?php echo (int) $dash['api_approved']; ?> · 待审 <?php echo (int) $dash['api_pending']; ?></span>
        </div>
        <div class="uc-dash__stat">
            <span class="uc-dash__stat-label">接口调用</span>
            <strong class="uc-dash__stat-value" data-field="api_calls"><?php echo (int) $dash['api_calls']; ?></strong>
            <span class="uc-dash__stat-hint">各接口累计请求次数</span>
        </div>
        <div class="uc-dash__stat">
            <span class="uc-dash__stat-label">API 令牌</span>
            <strong class="uc-dash__stat-value" data-field="key_total"><?php echo (int) $dash['key_total']; ?></strong>
            <span class="uc-dash__stat-hint">令牌累计调用 <?php echo (int) $dash['key_calls']; ?></span>
        </div>
        <div class="uc-dash__stat">
            <span class="uc-dash__stat-label">账号</span>
            <strong class="uc-dash__stat-value uc-dash__stat-value--sm"><?php echo vs_e($vsUser && !empty($vsUser['email']) ? $vsUser['email'] : '-'); ?></strong>
            <span class="uc-dash__stat-hint">注册于 <?php echo vs_e($vsUser && !empty($vsUser['createtime']) ? $vsUser['createtime'] : '-'); ?></span>
        </div>
    </div>

    <div class="uc-dash__grid">
        <div class="uc-dash__card">
            <h3 class="uc-dash__card-title">快捷入口</h3>
            <div class="uc-dash__links">
                <a class="uc-dash__link" href="<?php echo vs_e($base . '/user/account'); ?>">账号设置</a>
                <a class="uc-dash__link" href="<?php echo vs_e($base . '/user/points'); ?>">积分明细</a>
                <a class="uc-dash__link" href="<?php echo vs_e($base . '/user/keys'); ?>">令牌管理</a>
                <?php if (!empty($dash['can_publish_api'])): ?>
                    <a class="uc-dash__link" href="<?php echo vs_e($base . '/user/api-manage'); ?>">API 管理</a>
                <?php endif; ?>
                <a class="uc-dash__link" href="<?php echo vs_e($base . '/user/apis'); ?>">接口广场</a>
            </div>
        </div>
        <div class="uc-dash__card">
            <h3 class="uc-dash__card-title">最近动态</h3>
            <ul class="uc-dash__meta">
                <li>最后登录：<?php echo vs_e($vsUser && !empty($vsUser['lastlogin']) ? $vsUser['lastlogin'] : '暂无记录'); ?></li>
                <?php if (!empty($dash['checkin_enabled'])): ?>
                    <li>今日签到：<?php echo !empty($dash['checked_today']) ? '已签到' : '未签到'; ?></li>
                <?php endif; ?>
                <?php if ((int) $dash['api_rejected'] > 0): ?>
                    <li>有 <?php echo (int) $dash['api_rejected']; ?> 个接口未通过审核，可在 API 管理中查看原因。</li>
                <?php elseif ((int) $dash['api_pending'] > 0): ?>
                    <li>有 <?php echo (int) $dash['api_pending']; ?> 个接口等待管理员审核。</li>
                <?php elseif (!empty($dash['can_publish_api'])): ?>
                    <li>接口投稿状态正常，可继续维护文档与代码示例。</li>
                <?php else: ?>
                    <li>当前身份不可投稿接口；可使用令牌调用已开放的 API。</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<?php
vs_user_layout_end(!empty($checkinBanner['show_banner'])
    ? array('user-checkin.js')
    : array());
?>
