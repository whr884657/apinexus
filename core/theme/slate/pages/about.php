<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<?php
$siteName = isset($siteName) ? $siteName : (class_exists('SiteContext') ? SiteContext::siteName() : '本站');
$aboutArticle = isset($aboutArticle) && is_array($aboutArticle) ? $aboutArticle : null;
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title"><?php echo vs_e($aboutArticle ? $aboutArticle['title'] : '关于'); ?></h1>
    <?php if ($aboutArticle && !empty($aboutArticle['summary'])): ?>
        <p class="st-page-desc"><?php echo vs_e($aboutArticle['summary']); ?></p>
    <?php else: ?>
        <p class="st-page-desc">了解 <?php echo vs_e($siteName); ?></p>
    <?php endif; ?>
    <div class="st-card">
        <?php if ($aboutArticle && !empty($aboutArticle['body_html'])): ?>
            <div class="st-card__desc markdown-body" style="line-height:1.75;">
                <?php echo $aboutArticle['body_html']; ?>
            </div>
        <?php else: ?>
            <div class="st-card__title"><?php echo vs_e($siteName); ?></div>
            <div class="st-card__desc">关于页内容尚未配置。请在后台「文章管理」发布文章时选择绑定关于页。</div>
        <?php endif; ?>
    </div>
</section>
</div></main>
