<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">关于</h1>
    <p class="st-page-desc">了解 <?php echo vs_e($siteName); ?></p>
    <div class="st-card">
        <div class="st-card__title"><?php echo vs_e($siteName); ?></div>
        <div class="st-card__desc"><?php echo vs_e($siteDesc !== '' ? $siteDesc : 'ApiNexus 是基于 PHP + MySQL 的轻量级 Web 管理系统。'); ?></div>
        <div class="st-card__meta" style="margin-top:12px;line-height:1.8;">
            系统版本：v<?php echo vs_e(VS_VERSION); ?><br>
            当前主题：主题二（<?php echo vs_e($themeId); ?>）<br>
            仓库：<a href="https://gitee.com/xunjinlu/apinexus" target="_blank" rel="noopener noreferrer">Gitee</a>
            · <a href="https://gitcode.com/xunjinlu/apinexus" target="_blank" rel="noopener noreferrer">GitCode</a>
            · <a href="https://github.com/whr884657/apinexus" target="_blank" rel="noopener noreferrer">GitHub</a>
        </div>
    </div>
</section>
</div></main>
