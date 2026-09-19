<?php
/**
 * 主题 five · 文章列表 / 详情（自研 TH5 视觉）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
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
<section class="th5-page">
  <div class="th5-page__inner max-w-3xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 文章</div>
    <h1 class="font-display font-bold tracking-tight th5-page__title">文章不存在</h1>
    <p class="th5-page__lead text-fg-2">该文章已下架或不存在。</p>
    <a class="btn-primary inline-flex mt-6" href="<?php echo vs_e($vsBase); ?>/articles">返回文章列表</a>
  </div>
</section>
        <?php
        return;
    }

    require_once dirname(__DIR__) . '/lib/bootstrap.php';
    $rawArticleBody = isset($article['body']) ? (string) $article['body'] : '';
    $articleBodyHtml = $rawArticleBody !== ''
        ? TH5_md_render($rawArticleBody)
        : (isset($article['body_html']) ? (string) $article['body_html'] : '');

    $commentsReady = class_exists('FrontendComment') && FrontendComment::tableReady();
    $comments = $commentsReady ? FrontendComment::listByContentId($articleId) : array();
    $commentCount = count($comments);
    ?>
<section class="th5-page th5-article-detail">
  <div class="th5-page__inner max-w-3xl mx-auto px-4 sm:px-5 lg:px-8">
    <a class="btn-ghost inline-flex text-sm mb-6" href="<?php echo vs_e($vsBase); ?>/articles">← 文章列表</a>
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 文章</div>
    <h1 class="font-display font-bold tracking-tight th5-page__title"><?php echo vs_e($article['title']); ?></h1>
    <p class="th5-page__lead text-fg-2">
      <?php echo vs_e($article['createtime']); ?> · 阅读 <?php echo vs_e($article['views_label']); ?>
    </p>

    <?php if (!empty($article['cover'])): ?>
      <img class="th5-article-cover card mt-6" src="<?php echo vs_e($article['cover']); ?>"
           alt="<?php echo vs_e($article['title']); ?>"
           width="920" height="360" loading="lazy" decoding="async" referrerpolicy="no-referrer">
    <?php endif; ?>

    <article class="th5-panel card mt-8">
      <div class="markdown-body vs-md-body th5-md" data-vs-md="desktop">
        <?php echo $articleBodyHtml; ?>
      </div>
    </article>

    <section class="th5-panel card mt-8 th5-cmt" id="articleComments" data-content-id="<?php echo (int) $articleId; ?>">
      <div class="th5-cmt__head">
        <h2 class="font-display font-semibold text-xl m-0">
          评论
          <span class="th5-cmt__count" id="articleCmtCount"><?php echo (int) $commentCount; ?></span>
        </h2>
        <p class="text-sm text-fg-2 mt-2 mb-0">支持文字与基础表情；可引用回复。邮箱必填，名称与网址选填。</p>
      </div>

      <?php if (!$commentsReady): ?>
        <div class="th5-notice mt-4">评论功能尚未就绪，请站长完成数据库结构更新。</div>
      <?php else: ?>
        <div class="th5-cmt-composer mt-6" id="articleCmtComposer">
          <div class="th5-cmt-quote" id="articleCmtQuote" hidden>
            <div class="th5-cmt-quote__body">
              <span class="th5-cmt-quote__label">引用</span>
              <span class="th5-cmt-quote__name" id="articleCmtQuoteName"></span>
              <span class="th5-cmt-quote__text" id="articleCmtQuoteText"></span>
            </div>
            <button type="button" class="th5-cmt-quote__clear" id="articleCmtQuoteClear" aria-label="取消引用">×</button>
          </div>

          <form id="articleCmtForm" method="post" action="<?php echo vs_e($vsBase); ?>/articles/<?php echo (int) $articleId; ?>" data-ajax="1">
            <input type="hidden" name="csrf_token" value="<?php echo vs_e($csrf); ?>">
            <input type="hidden" name="action" value="submit_comment">
            <input type="hidden" name="contentid" value="<?php echo (int) $articleId; ?>">
            <input type="hidden" name="parentid" id="articleCmtParentId" value="0">

            <div class="th5-cmt-fields">
              <label class="th5-field">
                <span>名称</span>
                <input class="input" type="text" id="articleCmtName" name="nickname" maxlength="50" placeholder="选填" value="<?php echo vs_e($prefillName); ?>" autocomplete="nickname">
              </label>
              <label class="th5-field">
                <span>邮箱 *</span>
                <input class="input" type="email" id="articleCmtEmail" name="email" maxlength="100" placeholder="必填" required value="<?php echo vs_e($prefillEmail); ?>" autocomplete="email">
              </label>
              <label class="th5-field">
                <span>个人网址</span>
                <input class="input" type="url" id="articleCmtSite" name="website" maxlength="255" placeholder="选填 https://" autocomplete="url">
              </label>
            </div>

            <label class="th5-field mt-4">
              <span>评论内容</span>
              <textarea class="input th5-textarea" id="articleCmtBody" name="body" rows="4" maxlength="1000" placeholder="说点什么…" required></textarea>
            </label>
            <div class="th5-cmt-toolbar">
              <div class="th5-cmt-emoji-wrap">
                <button type="button" class="btn-ghost text-sm" id="articleCmtEmojiBtn" aria-expanded="false" aria-controls="articleCmtEmojiPanel">表情</button>
                <div class="th5-cmt-emoji-panel" id="articleCmtEmojiPanel" hidden role="listbox" aria-label="基础表情"></div>
              </div>
              <span class="th5-cmt-counter text-sm text-muted"><span id="articleCmtLen">0</span>/1000</span>
              <button type="submit" class="btn-primary" id="articleCmtSubmit">发送评论</button>
            </div>
          </form>
        </div>

        <div class="th5-cmt-list mt-8" id="articleCmtList" aria-live="polite">
          <?php if ($commentCount === 0): ?>
            <div class="th5-notice" id="articleCmtEmpty">还没有评论，来抢沙发吧。</div>
          <?php else: ?>
            <?php foreach ($comments as $c): ?>
              <?php
              $tag = !empty($c['website']) ? 'a' : 'div';
              $attrs = !empty($c['website'])
                  ? ' href="' . vs_e($c['website']) . '" target="_blank" rel="noopener noreferrer"'
                  : '';
              $bodyHtml = nl2br(vs_e($c['body']), false);
              ?>
              <div class="th5-cmt-item<?php echo !empty($c['ispinned']) ? ' is-pinned' : ''; ?>" data-cmt-id="<?php echo (int) $c['id']; ?>" id="cmt-<?php echo (int) $c['id']; ?>">
                <<?php echo $tag; ?> class="th5-cmt-avatar-wrap"<?php echo $attrs; ?>>
                  <?php if (!empty($c['avatar_url'])): ?>
                    <img class="th5-cmt-avatar" src="<?php echo vs_e($c['avatar_url']); ?>" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">
                  <?php else: ?>
                    <span class="th5-cmt-avatar th5-cmt-avatar--letter"><?php
                        echo vs_e(function_exists('mb_substr') ? mb_substr($c['nickname'], 0, 1, 'UTF-8') : substr($c['nickname'], 0, 1));
                    ?></span>
                  <?php endif; ?>
                </<?php echo $tag; ?>>
                <div class="th5-cmt-main">
                  <div class="th5-cmt-meta">
                    <?php if (!empty($c['website'])): ?>
                      <a class="th5-cmt-name" href="<?php echo vs_e($c['website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo vs_e($c['nickname']); ?></a>
                    <?php else: ?>
                      <span class="th5-cmt-name"><?php echo vs_e($c['nickname']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($c['ispinned'])): ?>
                      <span class="tag hot">置顶</span>
                    <?php endif; ?>
                    <span class="th5-cmt-time text-xs text-muted"><?php echo vs_e($c['createtime_short']); ?></span>
                  </div>
                  <?php if (!empty($c['parent']) && is_array($c['parent'])): ?>
                    <button type="button" class="th5-cmt-ref" data-jump="<?php echo (int) $c['parent']['id']; ?>">
                      <span class="th5-cmt-ref__name"><?php echo vs_e($c['parent']['nickname']); ?></span>
                      <span class="th5-cmt-ref__text"><?php echo vs_e($c['parent']['excerpt']); ?></span>
                    </button>
                  <?php endif; ?>
                  <div class="th5-cmt-body"><?php echo $bodyHtml; ?></div>
                  <?php if (!empty($c['reply'])): ?>
                    <div class="th5-cmt-admin-reply">
                      <span class="th5-cmt-admin-reply__label">管理员回复</span>
                      <?php echo nl2br(vs_e($c['reply']), false); ?>
                    </div>
                  <?php endif; ?>
                  <div class="th5-cmt-actions">
                    <button type="button" class="btn-ghost text-sm article-cmt-reply-btn"
                      data-reply-id="<?php echo (int) $c['id']; ?>"
                      data-reply-name="<?php echo vs_e($c['nickname']); ?>"
                      data-reply-excerpt="<?php echo vs_e(class_exists('CommentManager') ? CommentManager::excerptBody($c['body']) : ''); ?>">引用回复</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
<link rel="stylesheet" href="<?php echo vs_e(vs_site_path('/core/markdown/assets/css/markdown-render.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">
<?php $vsSyntaxHref = ThemeManager::pageScriptUrl('vs-syntax.js'); if ($vsSyntaxHref !== ''): ?>
<script src="<?php echo vs_e($vsSyntaxHref); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo vs_e(vs_site_path('/core/markdown/assets/js/markdown-render.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script>
window.VS_CSRF_TOKEN = window.VS_CSRF_TOKEN || <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
window.VS_ARTICLE_COMMENT = {
  contentId: <?php echo (int) $articleId; ?>,
  postUrl: <?php echo json_encode($vsBase . '/articles/' . (int) $articleId, JSON_UNESCAPED_UNICODE); ?>,
  count: <?php echo (int) $commentCount; ?>
};
</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/js/pages/articles-page.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
    <?php
    return;
}

$articles = FrontendArticle::listForTheme(30);
?>
<section class="th5-page">
  <div class="th5-page__inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 文章</div>
    <h1 class="font-display font-bold tracking-tight th5-page__title">文章</h1>
    <p class="th5-page__lead text-fg-2">资讯与教程</p>

    <?php if (count($articles) === 0): ?>
      <div class="th5-panel card mt-8 text-fg-2">暂无已发布文章。</div>
    <?php else: ?>
      <div class="th5-article-grid grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-8">
        <?php foreach ($articles as $a): ?>
          <a class="th5-article-card card" href="<?php echo vs_e(vs_path_resource_url('articles', $a['id'])); ?>">
            <?php if (!empty($a['cover'])): ?>
              <img class="th5-article-card__cover" src="<?php echo vs_e($a['cover']); ?>" alt="" width="480" height="200" loading="lazy" decoding="async" referrerpolicy="no-referrer">
            <?php endif; ?>
            <div class="th5-article-card__body">
              <h2 class="font-display font-semibold text-lg m-0"><?php echo vs_e($a['title']); ?></h2>
              <p class="text-xs text-muted mt-2 mb-0"><?php echo vs_e($a['createtime']); ?> · 阅读 <?php echo vs_e($a['views_label']); ?></p>
              <?php if (!empty($a['summary'])): ?>
                <p class="text-sm text-fg-2 mt-3 mb-0"><?php echo vs_e($a['summary']); ?></p>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
