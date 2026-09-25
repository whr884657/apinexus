<?php
/**
 * 主题 five · 友情链接（列表 POST 异步，不灌 SSR）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$applyUrl = $vsBase . '/applylink';
?>
<section class="th5-page" data-vs-links-page="1" data-layout="muming">
  <div class="th5-page__inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
      <div>
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 友情链接</div>
        <h1 class="font-display font-bold tracking-tight th5-page__title">友情链接</h1>
        <p class="th5-page__lead text-fg-2">与优质站点互相推荐，共同成长</p>
      </div>
    </div>

    <p class="th5-notice text-sm text-fg-2 mb-6" data-vs-links-loading="1">正在加载友链…</p>
    <p class="th5-notice text-sm text-fg-2 mb-6" data-vs-links-truncated="1" hidden><span data-vs-links-trunc-text></span></p>

    <div class="th5-panel card" data-vs-links-empty="1" hidden>
      <h2 class="font-display font-semibold text-lg m-0 mb-2">暂无友情链接</h2>
      <p class="text-fg-2 m-0 mb-0">欢迎交换友链。请先在贵站添加本站信息，再通过下方入口提交申请。</p>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" data-vs-links-grid="1" hidden></div>

    <div class="th5-cta-band card mt-10">
      <div>
        <h2 class="font-display font-semibold text-lg m-0 mb-1">申请友链</h2>
        <p class="text-fg-2 text-sm m-0">请先在贵站添加本站信息，再提交申请。</p>
      </div>
      <a class="btn-primary" href="<?php echo vs_e($applyUrl); ?>">去申请</a>
    </div>
  </div>
</section>
