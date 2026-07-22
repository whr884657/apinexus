<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$siteName = isset($siteName) ? $siteName : SiteContext::siteName();
$articleId = function_exists('vs_resolve_path_id') ? (int) vs_resolve_path_id('id') : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($articleId > 0) {
    $article = FrontendArticle::findById($articleId, true);
    if ($article === null) {
        http_response_code(404);
        ?>
<main class="content-wrapper" style="padding-top:88px;">
    <h1 class="page-title">文章不存在</h1>
    <p style="color:var(--text-muted);"><a href="<?php echo vs_e($vsBase); ?>/articles">返回文章列表</a></p>
</main>
        <?php
        return;
    }
    ?>
<main class="content-wrapper" style="padding-top:88px;">
    <p class="text-sm" style="margin-bottom:1rem;"><a href="<?php echo vs_e($vsBase); ?>/articles" style="color:var(--accent-primary);">← 文章列表</a></p>
    <article class="article-detail">
        <h1 class="page-title" style="margin-bottom:0.5rem;"><?php echo vs_e($article['title']); ?></h1>
        <div class="article-card-meta" style="margin-bottom:1.25rem;">
            <span><?php echo vs_e($article['createtime']); ?></span>
            <span>阅读 <?php echo vs_e($article['views_label']); ?></span>
        </div>
        <?php if (!empty($article['cover'])): ?>
            <img class="article-cover" src="<?php echo vs_e($article['cover']); ?>" alt=""
                 style="width:100%;max-height:360px;object-fit:cover;border-radius:12px;margin-bottom:1.25rem;"
                 loading="lazy" referrerpolicy="no-referrer">
        <?php endif; ?>
        <div class="markdown-body vs-md-body article-content">
            <?php echo $article['body_html']; ?>
        </div>
    </article>
</main>
<link rel="stylesheet" href="<?php echo vs_e(rtrim(vs_base_url(), '/')); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
    <?php
    return;
}

$articles = FrontendArticle::listForTheme(30);
?>
<main class="content-wrapper" style="padding-top:88px;">
    <h1 class="page-title">文章</h1>
    <?php if (count($articles) === 0): ?>
        <p style="color:var(--text-muted);padding:2rem 0;">暂无已发布文章。</p>
    <?php else: ?>
        <?php foreach ($articles as $a):
            $coverlayout = isset($a['coverlayout'])
                ? ContentManager::normalizeCoverLayout($a['coverlayout'])
                : ContentManager::COVER_RIGHT;
            $hasCover = $a['cover'] !== '';
            $innerClass = 'none-image';
            $cardClass = 'article-card';
            if ($hasCover) {
                if ($coverlayout === ContentManager::COVER_LEFT) {
                    $innerClass = 'left-image';
                } elseif ($coverlayout === ContentManager::COVER_BG) {
                    $innerClass = 'background-image';
                    $cardClass .= ' has-bg';
                } else {
                    $innerClass = 'right-image';
                }
            }
        ?>
            <article class="<?php echo vs_e($cardClass); ?>">
                <?php if ($hasCover && $coverlayout === ContentManager::COVER_BG): ?>
                    <div class="article-card-bg" style="background-image:url('<?php echo vs_e($a['cover']); ?>');"></div>
                <?php endif; ?>
                <div class="article-card-inner <?php echo vs_e($innerClass); ?>">
                    <?php if ($hasCover && $coverlayout === ContentManager::COVER_LEFT): ?>
                        <img class="article-card-cover left" src="<?php echo vs_e($a['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                    <?php endif; ?>
                    <div class="article-card-content">
                        <a href="<?php echo vs_e(vs_path_resource_url('articles', $a['id'])); ?>" class="article-card-title"><?php echo vs_e($a['title']); ?></a>
                        <?php if ($a['summary'] !== ''): ?>
                            <p class="article-card-excerpt"><?php echo vs_e($a['summary']); ?></p>
                        <?php endif; ?>
                        <div class="article-card-meta">
                            <span><?php echo vs_e($a['createtime']); ?></span>
                            <span>阅读 <?php echo vs_e($a['views_label']); ?></span>
                        </div>
                    </div>
                    <?php if ($hasCover && $coverlayout === ContentManager::COVER_RIGHT): ?>
                        <img class="article-card-cover right" src="<?php echo vs_e($a['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
