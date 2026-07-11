<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<section class="vs-frontend-page-head">
    <h1 class="vs-frontend-page-head__title">文章</h1>
    <p class="vs-frontend-page-head__desc">站点资讯、教程与公告（功能完善中）</p>
</section>

<section class="vs-frontend-section">
    <?php vs_render_notice('info', '', '文章模块正在建设中，后续可在后台内容运营中发布与管理。', array('compact' => true)); ?>
    <div class="vs-frontend-article-list">
        <article class="vs-frontend-article-card">
            <h3 class="vs-frontend-article-card__title">欢迎使用 misc-api</h3>
            <p class="vs-frontend-article-card__meta">系统 · 示例内容</p>
            <p class="vs-frontend-article-card__excerpt">前台主题系统已启用，文章内容将随主题包切换而变更，页面路由保持不变。</p>
        </article>
    </div>
</section>
