<?php
/**
 * 文件：user/index.php
 * 作用：用户中心控制台（仪表盘）；默认主题支持每日签到横幅
 *
 * 无快捷入口；浏览公开接口请走前台首页 / 全部接口页。
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
$email = $vsUser && !empty($vsUser['email']) ? (string) $vsUser['email'] : '-';
$created = $vsUser && !empty($vsUser['createtime']) ? (string) $vsUser['createtime'] : '-';
$lastLogin = $vsUser && !empty($vsUser['lastlogin']) ? (string) $vsUser['lastlogin'] : '暂无记录';

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
        </div>
    </header>

    <div class="uc-dash__kpi" aria-label="关键指标">
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">接口总数</span>
            <strong class="uc-dash__kpi-value" data-field="api_total"><?php echo (int) $dash['api_total']; ?></strong>
            <span class="uc-dash__kpi-meta">已通过 <?php echo (int) $dash['api_approved']; ?> · 待审 <?php echo (int) $dash['api_pending']; ?></span>
        </div>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">接口调用</span>
            <strong class="uc-dash__kpi-value" data-field="api_calls"><?php echo (int) $dash['api_calls']; ?></strong>
            <span class="uc-dash__kpi-meta">各接口累计请求</span>
        </div>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">API 令牌</span>
            <strong class="uc-dash__kpi-value" data-field="key_total"><?php echo (int) $dash['key_total']; ?></strong>
            <span class="uc-dash__kpi-meta">累计调用 <?php echo (int) $dash['key_calls']; ?></span>
        </div>
        <div class="uc-dash__kpi-card">
            <span class="uc-dash__kpi-label">积分</span>
            <strong class="uc-dash__kpi-value" data-field="points_kpi"><?php echo vs_e($dash['points']); ?></strong>
            <span class="uc-dash__kpi-meta">当前可用余额</span>
        </div>
    </div>

    <div class="uc-dash__row">
        <div class="vs-panel uc-dash__panel">
            <div class="vs-panel__header">
                <h2 class="vs-panel__title">账户概览</h2>
            </div>
            <div class="vs-panel__body">
                <div class="uc-dash__sys" role="list">
                    <div class="uc-dash__sys-item" role="listitem">
                        <span class="uc-dash__sys-dot is-info"></span>
                        <span class="uc-dash__sys-name">邮箱</span>
                        <span class="uc-dash__sys-num uc-dash__sys-num--text"><?php echo vs_e($email); ?></span>
                    </div>
                    <div class="uc-dash__sys-item" role="listitem">
                        <span class="uc-dash__sys-dot is-neutral"></span>
                        <span class="uc-dash__sys-name">注册时间</span>
                        <span class="uc-dash__sys-num uc-dash__sys-num--text"><?php echo vs_e($created); ?></span>
                    </div>
                    <div class="uc-dash__sys-item" role="listitem">
                        <span class="uc-dash__sys-dot is-success"></span>
                        <span class="uc-dash__sys-name">最后登录</span>
                        <span class="uc-dash__sys-num uc-dash__sys-num--text"><?php echo vs_e($lastLogin); ?></span>
                    </div>
                    <?php if (!empty($dash['checkin_enabled'])): ?>
                    <div class="uc-dash__sys-item" role="listitem">
                        <span class="uc-dash__sys-dot <?php echo !empty($dash['checked_today']) ? 'is-success' : 'is-warn'; ?>"></span>
                        <span class="uc-dash__sys-name">今日签到</span>
                        <span class="uc-dash__sys-num"><?php echo !empty($dash['checked_today']) ? '已签到' : '未签到'; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vs-panel uc-dash__panel">
            <div class="vs-panel__header">
                <h2 class="vs-panel__title">状态提示</h2>
            </div>
            <div class="vs-panel__body">
                <ul class="uc-dash__tips">
                    <?php if ((int) $dash['api_rejected'] > 0): ?>
                        <li>有 <?php echo (int) $dash['api_rejected']; ?> 个接口未通过审核，请到「API 管理」查看原因。</li>
                    <?php elseif ((int) $dash['api_pending'] > 0): ?>
                        <li>有 <?php echo (int) $dash['api_pending']; ?> 个接口等待管理员审核。</li>
                    <?php elseif (!empty($dash['can_publish_api'])): ?>
                        <li>接口投稿状态正常，可在「API 管理」维护文档与代码示例。</li>
                    <?php else: ?>
                        <li>当前身份不可投稿接口；可使用令牌调用已开放的 API。</li>
                    <?php endif; ?>
                    <?php if (!empty($dash['bound_admin'])): ?>
                        <li>本账号已与管理员绑定同权，可提交本地或代理接口。</li>
                    <?php endif; ?>
                    <li>浏览公开接口请访问站点首页或「全部接口」页面。</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php
vs_user_layout_end(!empty($checkinBanner['show_banner'])
    ? array('user-checkin.js')
    : array());
?>
