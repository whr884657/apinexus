<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$heroDesc = isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '基于 PHP + MySQL 的轻量级 Web 管理系统，全面适配电脑端与手机端。');
?>
<section class="vs-hero">
    <h1 class="vs-hero-title">欢迎使用 <?php echo vs_e($siteName); ?></h1>
    <p class="vs-hero-desc"><?php echo vs_e($heroDesc); ?></p>
    <div class="vs-hero-actions">
        <a href="<?php echo vs_e($authUrl); ?>" class="vs-btn vs-btn--primary"><?php echo vs_e($authLabel); ?></a>
        <?php if (!$userLoggedIn): ?>
            <a href="<?php echo vs_e($registerUrl); ?>" class="vs-btn vs-btn--default">注册账号</a>
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

<section class="vs-frontend-section">
    <h2 class="vs-frontend-section__title">快速入口</h2>
    <div class="vs-frontend-quick-grid">
        <?php foreach ($navItems as $item): ?>
            <?php if ($item['id'] === 'home') { continue; } ?>
            <a href="<?php echo vs_e($item['url']); ?>" class="vs-frontend-quick-card">
                <span class="vs-frontend-quick-card__label"><?php echo vs_e($item['label']); ?></span>
                <span class="vs-frontend-quick-card__arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
