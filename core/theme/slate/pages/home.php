<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$heroDesc = isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '为开发者提供稳定、快速的 API 接口服务');
?>
<main class="st-main">
<div class="st-wrap">
<section class="st-hero">
    <h1 class="st-hero__title">欢迎使用 <?php echo vs_e($siteName); ?></h1>
    <p class="st-hero__lead"><?php echo vs_e($heroDesc); ?></p>
    <div class="st-stats">
        <span>接口 <strong class="st-stats__accent">建设中</strong></span>
        <span>文章 <strong>0</strong></span>
        <span>用户 <strong>开放注册</strong></span>
    </div>
</section>
<section class="st-section">
    <h2 class="st-page-title" style="font-size:18px;margin-bottom:12px;">快速入口</h2>
    <div class="st-card-list">
        <?php foreach ($navItems as $item): ?>
            <?php if ($item['id'] === 'home') { continue; } ?>
            <a href="<?php echo vs_e($item['url']); ?>" class="st-card st-card-link">
                <div>
                    <div class="st-card__title"><?php echo vs_e($item['label']); ?></div>
                    <div class="st-card__meta">进入 <?php echo vs_e($item['label']); ?> 页面</div>
                </div>
                <span class="st-tag">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
</div>
</main>
