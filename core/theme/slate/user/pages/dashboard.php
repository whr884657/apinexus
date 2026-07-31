<?php
/**
 * Slate 主题 · 用户中心控制台（青绿配色由 user.css 负责）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$dash = isset($dash) && is_array($dash) ? $dash : array();
$checkinBanner = isset($checkinBanner) && is_array($checkinBanner) ? $checkinBanner : array();
$avatarPreview = isset($avatarPreview) ? (string) $avatarPreview : '';
$displayName = isset($displayName) ? (string) $displayName : '用户';
$isDeveloper = !empty($isDeveloper);
$email = isset($dash['email']) && $dash['email'] !== '' ? (string) $dash['email'] : '—';
$created = isset($dash['createtime']) && $dash['createtime'] !== '' ? (string) $dash['createtime'] : '—';
$lastLogin = isset($dash['lastlogin']) && $dash['lastlogin'] !== '' ? (string) $dash['lastlogin'] : '暂无记录';
?>

<?php if (!empty($checkinBanner['show_banner'])): ?>
<div class="uc-checkin-banner uc-motion" id="ucCheckinBanner" role="region" aria-label="每日签到">
    <span class="uc-checkin-banner__label">每日签到</span>
    <button type="button" class="vs-btn vs-btn--primary vs-btn--sm uc-checkin-banner__btn" id="ucCheckinBtn">签到</button>
</div>
<?php endif; ?>

<section class="uc-dash" id="ucDashboard" data-theme="slate">
    <header class="uc-dash__hero uc-motion">
        <div class="uc-dash__hero-main">
            <img class="uc-dash__avatar" src="<?php echo vs_e($avatarPreview); ?>" alt="" width="56" height="56" loading="lazy" referrerpolicy="no-referrer">
            <div class="uc-dash__hero-text">
                <h2 class="uc-dash__hello">欢迎回来，<?php echo vs_e($displayName); ?></h2>
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

    <div class="vs-panel uc-dash__panel uc-motion">
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
</section>
