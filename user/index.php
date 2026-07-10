<?php
/**
 * 文件：user/index.php
 * 作用：用户中心首页（控制台）
 */

require_once __DIR__ . '/init.php';

$avatarPreview = UserAvatar::resolve($vsUser);

vs_user_layout_start('控制台', 'dashboard');
?>

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
            <span class="vs-stat-card__value"><?php echo vs_e($vsUser && !empty($vsUser['created_at']) ? $vsUser['created_at'] : '-'); ?></span>
        </div>
        <div class="vs-stat-card">
            <span class="vs-stat-card__label">最后登录</span>
            <span class="vs-stat-card__value"><?php echo vs_e($vsUser && !empty($vsUser['last_login_at']) ? $vsUser['last_login_at'] : '-'); ?></span>
        </div>
    </div>
</div>

<?php vs_user_layout_end(); ?>
