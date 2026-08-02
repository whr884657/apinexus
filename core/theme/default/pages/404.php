<?php
/**
 * 默认主题 · 404（独立整页，不走前台 layout）
 * 变量由 vs_render_404_page 注入；此处全部兜底，避免静态分析误报未定义变量
 *
 * @var string $base
 * @var string $siteName
 * @var string $heading
 * @var string $lead
 * @var string $mark
 * @var string $cssHref
 * @var string $jsHref
 * @var string $pageTitle
 * @var string $favicon
 * @var string $pathHint
 * @var array<int,string> $legal
 */
if (!isset($base) || !is_string($base) || $base === '') {
    exit;
}
$base = rtrim((string) $base, '/');
$siteName = isset($siteName) ? (string) $siteName : 'ApiNexus';
$heading = isset($heading) ? (string) $heading : '页面不存在';
$lead = isset($lead) ? (string) $lead : '您访问的地址不存在，或内容已下架、未公开。';
$mark = isset($mark) ? (string) $mark : 'A';
$pageTitle = isset($pageTitle) && (string) $pageTitle !== '' ? (string) $pageTitle : $heading;
$cssHref = isset($cssHref) ? (string) $cssHref : '';
$jsHref = isset($jsHref) ? (string) $jsHref : '';
$favicon = isset($favicon) ? (string) $favicon : '';
$pathHint = isset($pathHint) ? (string) $pathHint : '';
$legal = isset($legal) && is_array($legal) ? $legal : array();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?php echo vs_e($pageTitle); ?></title>
<?php if (!empty($favicon) && function_exists('vs_render_site_icons')) { vs_render_site_icons($favicon, ''); } ?>
<?php if (!empty($cssHref)): ?>
<link rel="stylesheet" href="<?php echo vs_e($cssHref); ?>">
<?php endif; ?>
<script>(function(){try{var t=localStorage.getItem("theme");if(t!=="dark"&&t!=="light"){t="light"}document.documentElement.setAttribute("data-theme",t);}catch(e){document.documentElement.setAttribute("data-theme","light");}})();</script>
</head>
<body class="df404">
<div class="df404__grid" aria-hidden="true"></div>
<div class="df404__scan" aria-hidden="true"></div>

<div class="df404__shell">
    <header class="df404__top">
        <a class="df404__brand" href="<?php echo vs_e($base); ?>/">
            <span class="df404__mark" aria-hidden="true"><?php echo vs_e($mark); ?></span>
            <span><?php echo vs_e($siteName); ?></span>
        </a>
        <span class="df404__chip" data-df404-status>ROUTE_MISS</span>
    </header>

    <main class="df404__main" role="main">
        <div class="df404__hero">
            <div class="df404__code" aria-hidden="true">
                <span class="df404__glitch" data-text="404">404</span>
            </div>
            <svg class="df404__ring" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="df404__ring-track" cx="60" cy="60" r="52"></circle>
                <circle class="df404__ring-arc" cx="60" cy="60" r="52"></circle>
                <path class="df404__icon" d="M38 48h44v8H38zm0 16h28v8H38zm0 16h36v8H38z"></path>
            </svg>
        </div>

        <section class="df404__copy">
            <p class="df404__kicker">HTTP 404 · Not Found</p>
            <h1 class="df404__title" data-df404-type><?php echo vs_e($heading); ?></h1>
            <p class="df404__lead"><?php echo vs_e($lead); ?></p>
            <?php if ($pathHint !== ''): ?>
            <p class="df404__path"><span>requested</span> <code><?php echo vs_e($pathHint); ?></code></p>
            <?php endif; ?>
        </section>

        <aside class="df404__term" aria-label="诊断终端">
            <div class="df404__term-bar">
                <span></span><span></span><span></span>
                <em>diagnostics.sh</em>
            </div>
            <pre class="df404__term-body" id="df404Term" aria-live="polite"></pre>
        </aside>

        <section class="df404__legal">
            <h2 class="df404__legal-title">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2l8 3v6c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V5l8-3zm0 2.2L6 6.1v4.9c0 3.8 2.5 7.2 6 8.6 3.5-1.4 6-4.8 6-8.6V6.1l-6-1.9zM11 8h2v5h-2V8zm0 7h2v2h-2v-2z"/></svg>
                安全与法律提示
            </h2>
            <ul>
                <?php foreach ($legal as $item): ?>
                <li><?php echo vs_e($item); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <div class="df404__actions">
            <a class="df404__btn df404__btn--primary" href="<?php echo vs_e($base); ?>/">返回首页</a>
            <button type="button" class="df404__btn" id="df404Back">返回上一页</button>
            <a class="df404__btn" href="<?php echo vs_e($base); ?>/apis">浏览接口</a>
        </div>
    </main>
</div>

<script>
window.__DF404__ = {
    home: <?php echo json_encode($base . '/'); ?>,
    path: <?php echo json_encode($pathHint); ?>,
    heading: <?php echo json_encode($heading); ?>
};
</script>
<?php if (!empty($jsHref)): ?>
<script src="<?php echo vs_e($jsHref); ?>" defer></script>
<?php endif; ?>
</body>
</html>
