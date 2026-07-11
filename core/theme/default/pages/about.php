<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<section class="vs-frontend-page-head">
    <h1 class="vs-frontend-page-head__title">关于</h1>
    <p class="vs-frontend-page-head__desc">了解 <?php echo vs_e($siteName); ?> 与 misc-api 系统</p>
</section>

<section class="vs-frontend-section">
    <div class="vs-frontend-about-card">
        <h2><?php echo vs_e($siteName); ?></h2>
        <?php if ($siteDesc !== ''): ?>
            <p><?php echo vs_e($siteDesc); ?></p>
        <?php else: ?>
            <p>misc-api 是基于 PHP + MySQL 的轻量级 Web 管理系统，提供安装向导、后台管理、用户中心、主题切换与在线更新。</p>
        <?php endif; ?>
        <ul class="vs-frontend-about-list">
            <li>当前系统版本：v<?php echo vs_e(VS_VERSION); ?></li>
            <li>当前前台主题：<?php echo vs_e($themeId); ?></li>
            <li>页面内容由主题包驱动，切换主题后访问地址不变</li>
        </ul>
    </div>
</section>
