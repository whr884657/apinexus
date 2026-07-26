<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<?php
$siteName = isset($siteName) ? $siteName : (class_exists('SiteContext') ? SiteContext::siteName() : '本站');
$themeId = isset($themeId) ? $themeId : '';
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$aboutArticle = isset($aboutArticle) && is_array($aboutArticle) ? $aboutArticle : null;
$hasAbout = is_array($aboutArticle);
$aboutTitle = '关于我们';
$aboutBodyHtml = '';
if ($hasAbout) {
    if (isset($aboutArticle['title']) && (string) $aboutArticle['title'] !== '') {
        $aboutTitle = (string) $aboutArticle['title'];
    }
    if (isset($aboutArticle['body_html'])) {
        $aboutBodyHtml = (string) $aboutArticle['body_html'];
    }
}
?>
<main class="content-wrapper" style="padding-top:88px;">
    <h1 class="page-title"><?php echo vs_e($aboutTitle); ?></h1>
    <div class="page-content markdown-body vs-md-body" id="page-content" data-type="html">
        <?php if ($hasAbout): ?>
            <?php echo $aboutBodyHtml; ?>
        <?php else: ?>
            <p>关于页内容尚未配置。请在后台「文章管理」发布文章时选择绑定关于页。</p>
            <p>当前站点：<strong><?php echo vs_e($siteName); ?></strong><?php if ($themeId !== ''): ?> · 主题 <?php echo vs_e($themeId); ?><?php endif; ?></p>
        <?php endif; ?>
    </div>
</main>
<?php if ($hasAbout): ?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
<?php endif; ?>
