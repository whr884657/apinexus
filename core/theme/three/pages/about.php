<?php
/**
 * 主题 three · 关于（自研 th3：流式正文）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}
require_once dirname(__DIR__) . '/lib/bootstrap.php';

$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$systemName = isset($systemName) ? (string) $systemName : (class_exists('SiteContext') ? SiteContext::systemName() : $siteName);
$themeId = isset($themeId) ? (string) $themeId : 'three';
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$aboutArticle = isset($aboutArticle) && is_array($aboutArticle) ? $aboutArticle : null;
$hasAbout = is_array($aboutArticle);
$aboutTitle = '关于';
$aboutSummary = '';
$aboutBodyHtml = '';
if ($hasAbout) {
    if (!empty($aboutArticle['title'])) {
        $aboutTitle = (string) $aboutArticle['title'];
    }
    if (!empty($aboutArticle['summary'])) {
        $aboutSummary = (string) $aboutArticle['summary'];
    }
    $raw = isset($aboutArticle['body']) ? (string) $aboutArticle['body'] : '';
    if ($raw !== '') {
        $aboutBodyHtml = th3_md_render($raw);
    } elseif (!empty($aboutArticle['body_html'])) {
        $aboutBodyHtml = (string) $aboutArticle['body_html'];
    }
}
?>
<section class="th3-page">
  <div class="th3-page__inner th3-page__inner--about mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 关于</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title"><?php echo vs_e($aboutTitle); ?></h1>
    <?php if ($hasAbout && $aboutSummary !== ''): ?>
      <p class="th3-page__lead text-fg-2"><?php echo vs_e($aboutSummary); ?></p>
    <?php elseif (!$hasAbout): ?>
      <p class="th3-page__lead text-fg-2">了解 <?php echo vs_e($siteName); ?></p>
    <?php endif; ?>

    <div class="th3-about-content markdown-body vs-md-body th3-md mt-8" id="page-content" data-type="html">
      <?php if ($hasAbout): ?>
        <?php echo $aboutBodyHtml; ?>
      <?php else: ?>
        <p>关于页内容尚未配置。请在后台「文章管理」发布文章时选择绑定关于页。</p>
        <p>当前系统：<strong><?php echo vs_e($systemName); ?></strong><?php if ($themeId !== ''): ?> · 主题 <?php echo vs_e($themeId); ?><?php endif; ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php if ($hasAbout): ?>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
<script src="<?php echo vs_e($vsBase); ?>/core/markdown/assets/js/markdown-render.js?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
