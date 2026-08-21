<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : vs_site_base_path();
$articleId = function_exists('vs_resolve_path_id') ? (int) vs_resolve_path_id('id') : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$csrf = class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '';
$currentUser = class_exists('FrontendUser') ? FrontendUser::current() : null;
$prefillName = is_array($currentUser) && !empty($currentUser['username']) ? (string) $currentUser['username'] : '';
$prefillEmail = is_array($currentUser) && !empty($currentUser['email']) ? (string) $currentUser['email'] : '';

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

    $commentsReady = class_exists('FrontendComment') && FrontendComment::tableReady();
    $comments = $commentsReady ? FrontendComment::listByContentId($articleId) : array();
    $commentCount = count($comments);
    ?>
<main class="st-main st-article-detail"><div class="st-wrap">
<section class="st-section">
    <p class="st-article-back"><a class="st-btn st-btn--ghost" href="<?php echo vs_e($vsBase); ?>/articles">← 文章列表</a></p>
    <article class="st-article-body">
        <h1 class="st-page-title"><?php echo vs_e($article['title']); ?></h1>
        <p class="st-page-desc st-article-meta"><?php echo vs_e($article['createtime']); ?> · 阅读 <?php echo vs_e($article['views_label']); ?></p>
        <?php if (!empty($article['cover'])): ?>
            <img class="st-article-cover" src="<?php echo vs_e($article['cover']); ?>" alt="<?php echo vs_e($article['title']); ?>"
                 width="920" height="360" loading="lazy" decoding="async" referrerpolicy="no-referrer">
        <?php endif; ?>
        <div class="st-card st-article-content markdown-body vs-md-body st-md" data-vs-md="desktop">
            <?php
            require_once dirname(__DIR__) . '/lib/bootstrap.php';
            $rawArticleBody = isset($article['body']) ? (string) $article['body'] : '';
            echo $rawArticleBody !== '' ? slate_md_render($rawArticleBody) : (isset($article['body_html']) ? $article['body_html'] : '');
            ?>
        </div>
    </article>

    <section class="st-card st-article-comments article-comments" id="articleComments" data-content-id="<?php echo (int) $articleId; ?>">
        <div class="article-comments__head">
            <h2 class="article-comments__title st-card__title">
                <span class="article-comments__mark" aria-hidden="true">//</span>
                评论
                <span class="article-comments__count" id="articleCmtCount"><?php echo (int) $commentCount; ?></span>
            </h2>
            <p class="article-comments__hint st-page-desc">支持文字与基础表情；可引用回复。邮箱必填，名称与网址选填。</p>
        </div>

        <?php if (!$commentsReady): ?>
            <div class="article-comments__empty st-notice-box">评论功能尚未就绪，请站长完成数据库结构更新。</div>
        <?php else: ?>
            <div class="article-cmt-composer st-cmt-composer" id="articleCmtComposer">
                <div class="article-cmt-quote" id="articleCmtQuote" hidden>
                    <div class="article-cmt-quote__body">
                        <span class="article-cmt-quote__label">引用</span>
                        <span class="article-cmt-quote__name" id="articleCmtQuoteName"></span>
                        <span class="article-cmt-quote__text" id="articleCmtQuoteText"></span>
                    </div>
                    <button type="button" class="article-cmt-quote__clear" id="articleCmtQuoteClear" aria-label="取消引用">×</button>
                </div>

                <form id="articleCmtForm" method="post" action="<?php echo vs_e($vsBase); ?>/articles/<?php echo (int) $articleId; ?>" data-ajax="1">
                    <input type="hidden" name="csrf_token" value="<?php echo vs_e($csrf); ?>">
                    <input type="hidden" name="action" value="submit_comment">
                    <input type="hidden" name="contentid" value="<?php echo (int) $articleId; ?>">
                    <input type="hidden" name="parentid" id="articleCmtParentId" value="0">

                    <div class="article-cmt-fields st-form-row st-form-row--3">
                        <div class="article-cmt-field st-form-field">
                            <label class="st-label" for="articleCmtName">名称</label>
                            <input class="st-input" type="text" id="articleCmtName" name="nickname" maxlength="50" placeholder="选填" value="<?php echo vs_e($prefillName); ?>" autocomplete="nickname">
                        </div>
                        <div class="article-cmt-field st-form-field">
                            <label class="st-label" for="articleCmtEmail">邮箱 <span class="req">*</span></label>
                            <input class="st-input" type="email" id="articleCmtEmail" name="email" maxlength="100" placeholder="必填" required value="<?php echo vs_e($prefillEmail); ?>" autocomplete="email">
                        </div>
                        <div class="article-cmt-field st-form-field">
                            <label class="st-label" for="articleCmtSite">个人网址</label>
                            <input class="st-input" type="url" id="articleCmtSite" name="website" maxlength="255" placeholder="选填 https://" autocomplete="url">
                        </div>
                    </div>

                    <div class="article-cmt-editor st-form-field">
                        <textarea class="st-input st-input--area" id="articleCmtBody" name="body" rows="4" maxlength="1000" placeholder="说点什么…" required></textarea>
                        <div class="article-cmt-toolbar">
                            <div class="article-cmt-emoji-wrap">
                                <button type="button" class="article-cmt-emoji-btn st-btn st-btn--ghost" id="articleCmtEmojiBtn" aria-expanded="false" aria-controls="articleCmtEmojiPanel">表情</button>
                                <div class="article-cmt-emoji-panel" id="articleCmtEmojiPanel" hidden role="listbox" aria-label="基础表情"></div>
                            </div>
                            <span class="article-cmt-counter"><span id="articleCmtLen">0</span>/1000</span>
                            <button type="submit" class="st-btn article-cmt-submit" id="articleCmtSubmit">发送评论</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="article-cmt-list" id="articleCmtList" aria-live="polite">
                <?php if ($commentCount === 0): ?>
                    <div class="article-comments__empty st-notice-box" id="articleCmtEmpty">还没有评论，来抢沙发吧。</div>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                        <?php
                        $tag = !empty($c['website']) ? 'a' : 'div';
                        $attrs = !empty($c['website'])
                            ? ' href="' . vs_e($c['website']) . '" target="_blank" rel="noopener noreferrer"'
                            : '';
                        $bodyHtml = nl2br(vs_e($c['body']), false);
                        ?>
                        <div class="article-cmt-item st-cmt-item<?php echo !empty($c['ispinned']) ? ' is-pinned' : ''; ?>" data-cmt-id="<?php echo (int) $c['id']; ?>" id="cmt-<?php echo (int) $c['id']; ?>">
                            <<?php echo $tag; ?> class="article-cmt-avatar-wrap"<?php echo $attrs; ?>>
                                <?php if (!empty($c['avatar_url'])): ?>
                                    <img class="article-cmt-avatar" src="<?php echo vs_e($c['avatar_url']); ?>" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <span class="article-cmt-avatar article-cmt-avatar--letter"><?php
                                        echo vs_e(function_exists('mb_substr') ? mb_substr($c['nickname'], 0, 1, 'UTF-8') : substr($c['nickname'], 0, 1));
                                    ?></span>
                                <?php endif; ?>
                            </<?php echo $tag; ?>>
                            <div class="article-cmt-main">
                                <div class="article-cmt-meta">
                                    <?php if (!empty($c['website'])): ?>
                                        <a class="article-cmt-name" href="<?php echo vs_e($c['website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo vs_e($c['nickname']); ?></a>
                                    <?php else: ?>
                                        <span class="article-cmt-name"><?php echo vs_e($c['nickname']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($c['ispinned'])): ?>
                                        <span class="article-cmt-pin">置顶</span>
                                    <?php endif; ?>
                                    <span class="article-cmt-time"><?php echo vs_e($c['createtime_short']); ?></span>
                                </div>
                                <?php if (!empty($c['parent']) && is_array($c['parent'])): ?>
                                    <button type="button" class="article-cmt-ref" data-jump="<?php echo (int) $c['parent']['id']; ?>">
                                        <span class="article-cmt-ref__name"><?php echo vs_e($c['parent']['nickname']); ?></span>
                                        <span class="article-cmt-ref__text"><?php echo vs_e($c['parent']['excerpt']); ?></span>
                                    </button>
                                <?php endif; ?>
                                <div class="article-cmt-body"><?php echo $bodyHtml; ?></div>
                                <?php if (!empty($c['reply'])): ?>
                                    <div class="article-cmt-admin-reply">
                                        <span class="article-cmt-admin-reply__label">管理员回复</span>
                                        <?php echo nl2br(vs_e($c['reply']), false); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="article-cmt-actions">
                                    <button type="button" class="article-cmt-reply-btn"
                                        data-reply-id="<?php echo (int) $c['id']; ?>"
                                        data-reply-name="<?php echo vs_e($c['nickname']); ?>"
                                        data-reply-excerpt="<?php echo vs_e(CommentManager::excerptBody($c['body'])); ?>">引用回复</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
</div></main>
<link rel="stylesheet" href="<?php echo vs_e(vs_site_path('/core/markdown/assets/css/markdown-render.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">
<?php $vsSyntaxHref = ThemeManager::pageScriptUrl('vs-syntax.js'); if ($vsSyntaxHref !== ''): ?>
<script src="<?php echo vs_e($vsSyntaxHref); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo vs_e(vs_site_path('/core/markdown/assets/js/markdown-render.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/pages/slate-markdown.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script>
window.VS_CSRF_TOKEN = window.VS_CSRF_TOKEN || <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
window.VS_ARTICLE_COMMENT = {
    contentId: <?php echo (int) $articleId; ?>,
    postUrl: <?php echo json_encode($vsBase . '/articles/' . (int) $articleId, JSON_UNESCAPED_UNICODE); ?>,
    count: <?php echo (int) $commentCount; ?>
};
</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/pages/articles-page.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
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
        <?php foreach ($articles as $a):
            $coverlayout = isset($a['coverlayout'])
                ? ContentManager::normalizeCoverLayout($a['coverlayout'])
                : ContentManager::COVER_RIGHT;
            $hasCover = $a['cover'] !== '';
            $cardClass = 'st-article-card';
            if ($hasCover && $coverlayout === ContentManager::COVER_BG) {
                $cardClass .= ' st-article-card--bg';
            } elseif ($hasCover && $coverlayout === ContentManager::COVER_LEFT) {
                $cardClass .= ' st-article-card--left';
            } elseif ($hasCover && $coverlayout === ContentManager::COVER_RIGHT) {
                $cardClass .= ' st-article-card--right';
            }
        ?>
            <a class="<?php echo vs_e($cardClass); ?>" href="<?php echo vs_e(vs_path_resource_url('articles', $a['id'])); ?>">
                <?php if ($hasCover && $coverlayout === ContentManager::COVER_BG): ?>
                    <div class="st-article-card__bg" aria-hidden="true">
                        <img class="st-article-card__bg-img" src="<?php echo vs_e($a['cover']); ?>" alt="" width="920" height="360" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    </div>
                <?php endif; ?>
                <div class="st-article-card__inner">
                    <?php if ($hasCover && $coverlayout === ContentManager::COVER_LEFT): ?>
                        <img class="st-article-card__cover" src="<?php echo vs_e($a['cover']); ?>" alt="<?php echo vs_e($a['title']); ?>" width="280" height="160" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    <?php endif; ?>
                    <div class="st-article-card__body">
                        <div class="st-card__title"><?php echo vs_e($a['title']); ?></div>
                        <div class="st-card__meta"><?php echo vs_e($a['createtime']); ?> · 阅读 <?php echo vs_e($a['views_label']); ?></div>
                        <?php if ($a['summary'] !== ''): ?>
                            <div class="st-card__desc"><?php echo vs_e($a['summary']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasCover && $coverlayout === ContentManager::COVER_RIGHT): ?>
                        <img class="st-article-card__cover" src="<?php echo vs_e($a['cover']); ?>" alt="<?php echo vs_e($a['title']); ?>" width="280" height="160" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
</div></main>
