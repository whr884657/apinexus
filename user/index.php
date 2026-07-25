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

<div class="vs-panel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">欢迎回来，<?php echo vs_e($vsUser ? $vsUser['username'] : '用户'); ?></h2>
        <p class="vs-panel__desc">这是您的用户中心，可在侧边栏进入账号设置修改资料与密码。</p>
    </div>

    <div class="vs-stat-grid">
        <div class="vs-stat-card">
            <span class="vs-stat-card__label">用户名</span>
            <span class="vs-stat-card__value"><?php echo vs_e($vsUser ? $vsUser['username'] : '-'); ?></span>
        </div>
        <div class="vs-stat-card">
            <span class="vs-stat-card__label">邮箱</span>
            <span class="vs-stat-card__value"><?php echo vs_e($vsUser ? $vsUser['email'] : '-'); ?></span>
        </div>
        <div class="vs-stat-card">
            <span class="vs-stat-card__label">注册时间</span>
            <span class="vs-stat-card__value"><?php echo vs_e($vsUser && !empty($vsUser['createtime']) ? $vsUser['createtime'] : '-'); ?></span>
        </div>
        <div class="vs-stat-card">
            <span class="vs-stat-card__label">最后登录</span>
            <span class="vs-stat-card__value"><?php echo vs_e($vsUser && !empty($vsUser['lastlogin']) ? $vsUser['lastlogin'] : '-'); ?></span>
        </div>
    </div>
</div>

<?php
vs_user_layout_end(!empty($checkinBanner['show_banner'])
    ? array('user-checkin.js')
    : array());
?>
