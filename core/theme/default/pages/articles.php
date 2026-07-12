<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<main class="dt-main">
<div class="dt-container">
<section class="dt-page-head">
    <h1 class="dt-page-head__title">文章</h1>
    <p class="dt-page-head__desc">站点资讯、教程与公告</p>
</section>
<section class="dt-section">
    <?php vs_render_notice('info', '', '文章模块正在建设中，后续可在后台内容运营中发布。', array('compact' => true)); ?>
    <div class="dt-article-grid">
        <article class="dt-article-card">
            <span class="dt-article-card__tag">公告</span>
            <h3>欢迎使用 <?php echo vs_e($siteName); ?></h3>
            <p>前台主题系统已启用，文章内容将随主题包切换而变更。</p>
            <time class="dt-article-card__time">敬请期待</time>
        </article>
        <article class="dt-article-card dt-article-card--muted">
            <span class="dt-article-card__tag">教程</span>
            <h3>如何提交 API 接口</h3>
            <p>注册并登录用户中心，在接口列表中提交您的接口，等待管理员审核通过。</p>
            <time class="dt-article-card__time">即将上线</time>
        </article>
        <article class="dt-article-card dt-article-card--muted">
            <span class="dt-article-card__tag">动态</span>
            <h3>主题一前台 UI 升级</h3>
            <p>默认主题前台已全面重设计，与用户中心、管理员后台保持统一的浅色卡片风格。</p>
            <time class="dt-article-card__time">v<?php echo vs_e(VS_VERSION); ?></time>
        </article>
    </div>
</section>
</div>
</main>
