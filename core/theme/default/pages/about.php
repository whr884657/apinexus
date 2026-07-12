<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<main class="dt-main">
<div class="dt-container">
<section class="dt-page-head">
    <h1 class="dt-page-head__title">关于</h1>
    <p class="dt-page-head__desc">了解 <?php echo vs_e($siteName); ?> 与 misc-api 平台</p>
</section>
<section class="dt-section">
    <div class="dt-about-grid">
        <div class="dt-card dt-card--lift">
            <h2><?php echo vs_e($siteName); ?></h2>
            <p><?php echo vs_e($siteDesc !== '' ? $siteDesc : 'misc-api 是基于 PHP + MySQL 的轻量级 Web 管理系统，提供安装向导、后台管理、用户中心与主题系统。'); ?></p>
            <ul class="dt-about-list">
                <li><span>系统版本</span><strong>v<?php echo vs_e(VS_VERSION); ?></strong></li>
                <li><span>当前主题</span><strong><?php echo vs_e($themeId); ?></strong></li>
                <li><span>开源协议</span><strong>详见 LICENSE</strong></li>
            </ul>
        </div>
        <div class="dt-card">
            <h3>我们的目标</h3>
            <ul class="dt-bullet-list">
                <li>提供稳定、易用的 API 接口托管与展示能力</li>
                <li>降低开发者接入与站点部署成本</li>
                <li>保持前台、用户中心与后台 UI 风格一致</li>
            </ul>
        </div>
        <div class="dt-card">
            <h3>快速链接</h3>
            <ul class="dt-link-list">
                <li><a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a></li>
                <li><a href="https://gitee.com/xunjinlu/misc-api" target="_blank" rel="noopener noreferrer">开源仓库</a></li>
                <li><a href="https://gitee.com/xunjinlu/misc-api/releases" target="_blank" rel="noopener noreferrer">更新日志</a></li>
                <li><a href="<?php echo vs_e($authUrl); ?>"><?php echo vs_e($authLabel); ?></a></li>
            </ul>
        </div>
    </div>
</section>
</div>
</main>
