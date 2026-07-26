<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<?php
$siteName = isset($siteName) ? $siteName : (class_exists('SiteContext') ? SiteContext::siteName() : '本站');
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$aboutArticle = isset($aboutArticle) && is_array($aboutArticle) ? $aboutArticle : null;
$hasAbout = is_array($aboutArticle);
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title"><?php echo vs_e($hasAbout ? $aboutArticle['title'] : '关于'); ?></h1>
    <?php if ($hasAbout && !empty($aboutArticle['summary'])): ?>
        <p class="st-page-desc"><?php echo vs_e($aboutArticle['summary']); ?></p>
    <?php elseif (!$hasAbout): ?>
        <p class="st-page-desc">了解 <?php echo vs_e($siteName); ?></p>
    <?php endif; ?>

    <?php if ($hasAbout): ?>
        <div class="st-card markdown-body vs-md-body" style="padding:18px;line-height:1.75;">
            <?php echo isset($aboutArticle['body_html']) ? $aboutArticle['body_html'] : ''; ?>
        </div>
    <?php else: ?>
        <div class="st-card">
            <div class="st-card__title"><?php echo vs_e($siteName); ?></div>
            <div class="st-card__desc">关于页内容尚未配置。请在后台「文章管理」发布文章时选择绑定关于页。</div>
        </div>
    <?php endif; ?>
</section>
</div></main>
<?php if ($hasAbout): ?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
<?php endif; ?>
