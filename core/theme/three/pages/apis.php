<?php
/**
 * 主题 three · 全部接口
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$siteName = SiteContext::siteName();
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$apiCount = FrontendStats::approvedApiCount();
$catTags = FrontendCategory::listTags();
?>
<script>
window.TH3_HOME = {
  page: 'apis',
  previewLimit: 99999,
  apiCount: <?php echo (int) $apiCount; ?>,
  statsFormat: 'compact',
  vsBase: <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>
};
</script>

<section id="market" class="th3-apis-page py-16 sm:py-20 lg:py-28" style="padding-top:6.5rem;">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8 sm:mb-10">
      <div class="reveal visible">
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 全部接口</div>
        <h1 class="font-display font-bold tracking-tight" style="font-size: clamp(1.75rem, 5vw, 3rem);">
          全部接口
        </h1>
        <p class="text-fg-2 mt-3 text-sm sm:text-base">
          共 <span id="th3ApiTotalLabel"><?php echo (int) $apiCount; ?></span> 个 API 接口
        </p>
      </div>
      <div class="reveal visible reveal-delay-1 flex items-center gap-3 w-full lg:w-auto">
        <div class="relative flex-1 lg:w-72 min-w-0">
          <i data-lucide="search" style="width:16px;height:16px;position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);"></i>
          <input id="apiSearch" class="input pl-10" type="search" enterkeyhint="search"
                 placeholder="搜索 API…" data-ph-tpl="搜索 {n} 个 API…" autocomplete="off">
        </div>
      </div>
    </div>

    <div class="reveal visible cat-scroll -mx-4 px-4 sm:-mx-5 sm:px-5 lg:mx-0 lg:px-0" id="th3CatScroll" role="tablist" aria-label="接口分类">
      <button class="cat-tab active" data-cat="all" type="button">全部</button>
      <?php foreach ($catTags as $tag): ?>
        <?php
          $cid = isset($tag['id']) ? (string) $tag['id'] : '';
          $clabel = isset($tag['name']) ? (string) $tag['name'] : '';
          if ($cid === '' || $cid === 'all' || $clabel === '') {
              continue;
          }
        ?>
        <button class="cat-tab" data-cat="<?php echo vs_e($cid); ?>" type="button"><?php echo vs_e($clabel); ?></button>
      <?php endforeach; ?>
    </div>

    <div id="apiGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"></div>
  </div>
</section>
