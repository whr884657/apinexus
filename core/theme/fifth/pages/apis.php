<?php
/**
 * 主题 five · 全部接口（七牛云式：页头 + 分类目录 + 搜索 + 卡片网格）
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
window.TH5_HOME = {
  page: 'apis',
  previewLimit: 99999,
  apiCount: <?php echo (int) $apiCount; ?>,
  statsFormat: 'compact',
  vsBase: <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>
};
</script>

<section id="market" class="th5-page-q th5-apis-page">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal" style="text-align:left;margin-bottom:32px;">
      <span class="th5-section-head-q__eyebrow">API CATALOG</span>
      <h1 class="th5-section-head-q__title font-display">全部接口</h1>
      <p class="th5-section-head-q__sub" style="margin:0;">共 <span id="TH5ApiTotalLabel"><?php echo (int) $apiCount; ?></span> 个 API 接口，按分类浏览或搜索。</p>
    </div>

    <div class="th5-market-q__layout">
      <aside class="th5-cat-q reveal">
        <div class="th5-cat-q__title">接口分类</div>
        <div class="th5-cat-q__list" id="TH5CatScroll" role="tablist" aria-label="接口分类">
          <button class="cat-tab active" data-cat="all" type="button">全部接口</button>
          <?php foreach ($catTags as $tag): ?>
            <?php
              $cid = isset($tag['id']) ? (string) $tag['id'] : '';
              $clabel = isset($tag['name']) ? (string) $tag['name'] : '';
              if ($cid === '' || $cid === 'all' || $clabel === '') { continue; }
            ?>
            <button class="cat-tab" data-cat="<?php echo vs_e($cid); ?>" type="button"><?php echo vs_e($clabel); ?></button>
          <?php endforeach; ?>
        </div>
        <div class="th5-cat-q__foot">
          <span id="TH5ApiTotalLabel2"><?php echo (int) $apiCount; ?></span> endpoints available
        </div>
      </aside>

      <div class="th5-market-q__main reveal reveal-delay-1">
        <div class="th5-search-q">
          <span class="th5-search-q__icon" aria-hidden="true"><i data-lucide="search" style="width:17px;height:17px;"></i></span>
          <input id="apiSearch" class="input" type="search" enterkeyhint="search"
                 placeholder="搜索 API…" data-ph-tpl="搜索 {n} 个 API…" autocomplete="off">
        </div>
        <div id="apiGrid" class="th5-api-grid-q"></div>
      </div>
    </div>
  </div>
</section>
