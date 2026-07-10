<?php
/**
 * misc-api 前台首页
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$base = vs_base_url();
$siteName = SiteContext::siteName();
$siteDesc = SiteContext::siteDescription();
$heroDesc = $siteDesc !== '' ? $siteDesc : '基于 PHP + MySQL 的轻量级 Web 管理系统，全面适配电脑端与手机端。';

$userEntryUrl = UserAuth::check() ? ($base . '/user/index.php') : ($base . '/user/login.php');
$userEntryLabel = UserAuth::check() ? '进入用户中心' : '登录 / 注册';

vs_render_head('首页', array('index.css'));
?>

<div class="vs-page vs-home-page">
    <header class="vs-header">
        <div class="vs-container vs-header-inner">
            <div class="vs-logo">
                <?php vs_render_site_logo('vs-logo-icon'); ?>
                <span class="vs-logo-text"><?php echo vs_e($siteName); ?></span>
            </div>
            <nav class="vs-nav">
                <a href="<?php echo vs_e($base); ?>/" class="vs-nav-link is-active">首页</a>
                <a href="<?php echo vs_e($userEntryUrl); ?>" class="vs-nav-link">用户中心</a>
            </nav>
        </div>
    </header>

    <main class="vs-main">
        <div class="vs-container">
            <section class="vs-hero">
                <h1 class="vs-hero-title">欢迎使用 <?php echo vs_e($siteName); ?></h1>
                <p class="vs-hero-desc"><?php echo vs_e($heroDesc); ?></p>
                <div class="vs-hero-actions">
                    <a href="<?php echo vs_e($userEntryUrl); ?>" class="vs-btn vs-btn--primary"><?php echo vs_e($userEntryLabel); ?></a>
                    <?php if (!UserAuth::check()): ?>
                        <a href="<?php echo vs_e($base); ?>/user/register.php" class="vs-btn vs-btn--default">注册账号</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="vs-features">
                <div class="vs-feature-card">
                    <div class="vs-feature-icon">IN</div>
                    <h3>一键安装</h3>
                    <p>访问 /install 即可完成 Web 安装向导</p>
                </div>
                <div class="vs-feature-card">
                    <div class="vs-feature-icon">SC</div>
                    <h3>安全加密</h3>
                    <p>密码加密存储，CSRF 防护</p>
                </div>
                <div class="vs-feature-card">
                    <div class="vs-feature-icon">RS</div>
                    <h3>响应式设计</h3>
                    <p>PC 与手机端自动适配</p>
                </div>
            </section>
        </div>
    </main>

    <footer class="vs-footer">
        <?php vs_render_site_footer($siteName); ?>
    </footer>
</div>

<?php vs_render_foot(); ?>
