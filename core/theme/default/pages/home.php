<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$heroTitleSetting = ThemeManager::themeSetting('hero_title', '');
$heroLeadSetting = ThemeManager::themeSetting('hero_lead', '');
$heroTitle = $heroTitleSetting !== '' ? $heroTitleSetting : ('欢迎使用 ' . $siteName);
$heroDesc = $heroLeadSetting !== '' ? $heroLeadSetting : (isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '基于 PHP + MySQL 的轻量级 Web 管理系统，全面适配电脑端与手机端。'));
?>
<main class="dt-main">
<div class="dt-container">
<section class="dt-hero">
    <h1 class="dt-hero__title"><?php echo vs_e($heroTitle); ?></h1>
    <p class="dt-hero__desc"><?php echo vs_e($heroDesc); ?></p>
    <div class="dt-hero__actions">
        <a href="<?php echo vs_e($authUrl); ?>" class="dt-auth-btn dt-auth-btn--block" style="width:auto;min-width:160px;"><?php echo vs_e($authLabel); ?></a>
    </div>
</section>
<section class="dt-features">
    <div class="dt-feature"><div class="dt-feature__icon">IN</div><h3>一键安装</h3><p>访问 /install 即可完成 Web 安装向导</p></div>
    <div class="dt-feature"><div class="dt-feature__icon">SC</div><h3>安全加密</h3><p>密码加密存储，CSRF 防护</p></div>
    <div class="dt-feature"><div class="dt-feature__icon">RS</div><h3>响应式设计</h3><p>PC 与手机端自动适配</p></div>
</section>
<section class="dt-section">
    <h2 class="dt-section__title">快速入口</h2>
    <div class="dt-quick-grid">
        <?php foreach ($navItems as $item): ?>
            <?php if ($item['id'] === 'home') { continue; } ?>
            <a href="<?php echo vs_e($item['url']); ?>" class="dt-quick-card">
                <span><?php echo vs_e($item['label']); ?></span><span>→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
</div>
</main>
