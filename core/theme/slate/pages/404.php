<?php
/**
 * 主题二 slate · 404（独立整页，青绿氛围）
 * 变量由 vs_render_404_page 注入；此处全部兜底，避免静态分析误报未定义变量
 *
 * @var string $base
 * @var string $siteName
 * @var string $heading
 * @var string $lead
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
</head>
<body class="st404">
<div class="st404__orb st404__orb--a" aria-hidden="true"></div>
<div class="st404__orb st404__orb--b" aria-hidden="true"></div>
<svg class="st404__noise" aria-hidden="true"><filter id="st404Noise"><feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"/><feColorMatrix type="saturate" values="0"/></filter><rect width="100%" height="100%" filter="url(#st404Noise)" opacity="0.035"></rect></svg>

<div class="st404__wrap">
    <a class="st404__brand" href="<?php echo vs_e($base); ?>/">
        <span class="st404__leaf" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M17 8C8 10 5.9 16.2 4 20c4.5-1 9-3.5 12-7 1.7-2 2.5-3.8 3-5H17zM7.5 10c2.2-2.8 5.2-4.6 9.5-5.5C14 8 11 11 9 15c-.8-1.8-1.3-3.5-1.5-5z"/></svg>
        </span>
        <span><?php echo vs_e($siteName); ?></span>
    </a>

    <main class="st404__card" role="main">
        <div class="st404__visual" aria-hidden="true">
            <svg class="st404__svg" viewBox="0 0 280 140">
                <defs>
                    <linearGradient id="st404Grad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#24a66a"/>
                        <stop offset="100%" stop-color="#168855"/>
                    </linearGradient>
                </defs>
                <text class="st404__num" x="50%" y="58%" text-anchor="middle" dominant-baseline="middle">404</text>
                <circle class="st404__pulse" cx="140" cy="70" r="54"></circle>
                <circle class="st404__pulse st404__pulse--delay" cx="140" cy="70" r="54"></circle>
            </svg>
            <div class="st404__float">
                <span></span><span></span><span></span>
            </div>
        </div>

        <p class="st404__eyebrow">找不到页面</p>
        <h1 class="st404__title" id="st404Title"><?php echo vs_e($heading); ?></h1>
        <p class="st404__lead"><?php echo vs_e($lead); ?></p>
        <?php if ($pathHint !== ''): ?>
        <p class="st404__path">访问路径 <code><?php echo vs_e($pathHint); ?></code></p>
        <?php endif; ?>

        <div class="st404__tips">
            <div class="st404__tip">
                <strong>可能只是手误</strong>
                <span>链接过期、ID 输错或内容已下架，都很常见。</span>
            </div>
            <div class="st404__tip">
                <strong>请走正常入口</strong>
                <span>从首页、接口目录或文章列表进入，体验更稳妥。</span>
            </div>
        </div>

        <section class="st404__legal">
            <h2>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 1a9 9 0 00-9 9v4.5A2.5 2.5 0 005.5 17H7v-6H5a7 7 0 0114 0h-2v6h1.5a2.5 2.5 0 002.5-2.5V10a9 9 0 00-9-9zm-1 18h2a2 2 0 11-2 0z"/></svg>
                安全与法律提示
            </h2>
            <ol>
                <?php foreach ($legal as $item): ?>
                <li><?php echo vs_e($item); ?></li>
                <?php endforeach; ?>
            </ol>
        </section>

        <div class="st404__actions">
            <a class="st404__btn st404__btn--primary" href="<?php echo vs_e($base); ?>/">回到首页</a>
            <button type="button" class="st404__btn" id="st404Back">返回上一页</button>
            <a class="st404__btn" href="<?php echo vs_e($base); ?>/articles">看看文章</a>
        </div>
    </main>
</div>

<script>
window.__ST404__ = { home: <?php echo json_encode($base . '/'); ?> };
</script>
<?php if (!empty($jsHref)): ?>
<script src="<?php echo vs_e($jsHref); ?>" defer></script>
<?php endif; ?>
</body>
</html>
