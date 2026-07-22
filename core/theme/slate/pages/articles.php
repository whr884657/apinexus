<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$articleId = function_exists('vs_resolve_path_id') ? (int) vs_resolve_path_id('id') : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($articleId > 0) {
    $article = FrontendArticle::findById($articleId, true);
    if ($article === null) {
        http_response_code(404);
        ?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">文章不存在</h1>
    <p class="st-page-desc"><a href="<?php echo vs_e($vsBase); ?>/articles">返回文章列表</a></p>
</section>
</div></main>
        <?php
        return;
    }
    ?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <p style="margin-bottom:12px;"><a class="st-btn st-btn--ghost" href="<?php echo vs_e($vsBase); ?>/articles">← 文章列表</a></p>
    <h1 class="st-page-title"><?php echo vs_e($article['title']); ?></h1>
    <p class="st-page-desc"><?php echo vs_e($article['createtime']); ?> · 阅读 <?php echo vs_e($article['views_label']); ?></p>
    <?php if (!empty($article['cover'])): ?>
        <img src="<?php echo vs_e($article['cover']); ?>" alt="" style="width:100%;border-radius:12px;margin:12px 0 18px;max-height:360px;object-fit:cover;" loading="lazy" referrerpolicy="no-referrer">
    <?php endif; ?>
    <div class="st-card markdown-body vs-md-body" style="padding:18px;">
        <?php echo $article['body_html']; ?>
    </div>
</section>
</div></main>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
    <?php
    return;
}

$articles = FrontendArticle::listForTheme(30);
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">文章</h1>
    <p class="st-page-desc">资讯与教程</p>
    <?php if (count($articles) === 0): ?>
        <p class="st-notice-box">暂无已发布文章。</p>
    <?php else: ?>
    <div class="st-card-list">
        <?php foreach ($articles as $a): ?>
            <a class="st-card" href="<?php echo vs_e(vs_path_resource_url('articles', $a['id'])); ?>" style="text-decoration:none;color:inherit;display:block;">
                <div class="st-card__title"><?php echo vs_e($a['title']); ?></div>
                <div class="st-card__meta"><?php echo vs_e($a['createtime']); ?> · 阅读 <?php echo vs_e($a['views_label']); ?></div>
                <?php if ($a['summary'] !== ''): ?>
                    <div class="st-card__desc"><?php echo vs_e($a['summary']); ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
</div></main>
