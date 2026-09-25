<?php
/**
 * 主题 three · 友情链接（自研 th3；列表 POST 异步，不灌 SSR）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$applyUrl = $vsBase . '/applylink';
?>
<section class="th3-page" data-vs-links-page="1" data-layout="three">
  <div class="th3-page__inner th3-page__inner--links mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 友情链接</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title">友情链接</h1>
    <p class="th3-page__lead text-fg-2">与优质站点互相推荐，共同成长</p>

    <p class="th3-notice text-sm text-fg-2 mt-6 mb-0" data-vs-links-loading="1">正在加载友链…</p>
    <p class="th3-notice text-sm text-fg-2 mt-6 mb-0" data-vs-links-truncated="1" hidden><span data-vs-links-trunc-text></span></p>

    <div class="th3-empty mt-8" data-vs-links-empty="1" hidden>
      <p class="m-0 mb-1 font-semibold">暂无友情链接</p>
      <p class="m-0 text-sm">欢迎交换友链。请先在贵站添加本站信息，再通过下方入口提交申请。</p>
    </div>
    <div class="th3-link-grid mt-8" data-vs-links-grid="1" hidden></div>

    <div class="th3-link-apply mt-10">
      <div>
        <h2 class="font-display font-semibold text-lg m-0 mb-1">申请友链</h2>
        <p class="text-fg-2 text-sm m-0">请先在贵站添加本站信息，再提交申请。</p>
      </div>
      <a class="btn-primary" href="<?php echo vs_e($applyUrl); ?>">去申请</a>
    </div>
  </div>
</section>
