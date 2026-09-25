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

/* 接口列表排序（default=系统 apiorder / time=按上架时间 / calls=按调用量） */
$apiSort = trim((string) ThemeManager::themeSetting('api_sort', 'default'));
if (!in_array($apiSort, array('default', 'time', 'calls'), true)) {
    $apiSort = 'default';
}

/* 「上新」标签天数：接口创建 N 天内显示上新 */
$apiNewDays = (int) ThemeManager::themeSetting('api_new_days', 7);
if ($apiNewDays < 1) {
    $apiNewDays = 7;
}
?>
<script>
window.TH5_HOME = {
  page: 'apis',
  previewLimit: 99999,
  apiCount: <?php echo (int) $apiCount; ?>,
  statsFormat: 'compact',
  vsBase: <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>,
  apiSort: <?php echo json_encode($apiSort, JSON_UNESCAPED_UNICODE); ?>,
  newDays: <?php echo (int) $apiNewDays; ?>
};
</script>

<section id="market" class="th5-page-q th5-apis-page">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal" style="text-align:left;margin-bottom:32px;">
      <span class="th5-section-head-q__eyebrow">API CATALOG</span>
      <h1 class="th5-section-head-q__title font-display">全部接口</h1>
      <p class="th5-section-head-q__sub" style="margin:0;">共 <span id="TH5ApiTotalLabel"><?php echo (int) $apiCount; ?></span> 个 API 接口，按分类浏览或搜索。</p>
    </div>

    <?php if (count($catTags) > 0): ?>
    <!-- 分类横条（横向滚动，与左侧分类目录联动） -->
    <div class="th5-catbar reveal" data-th5-catbar>
      <button type="button" class="th5-catbar__arrow" data-th5-catbar-prev aria-label="向前滚动分类"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
      <div class="th5-catbar__scroll" data-th5-catbar-scroll role="tablist" aria-label="接口分类（横向）">
        <button class="th5-catbar__chip is-active" data-cat="all" type="button">全部接口</button>
        <button class="th5-catbar__chip" data-cat="new" type="button">最近上新</button>
        <?php foreach ($catTags as $tag): ?>
          <?php
            $cid = isset($tag['id']) ? (string) $tag['id'] : '';
            $clabel = isset($tag['name']) ? (string) $tag['name'] : '';
            if ($cid === '' || $cid === 'all' || $clabel === '') { continue; }
          ?>
          <button class="th5-catbar__chip" data-cat="<?php echo vs_e($cid); ?>" type="button"><?php echo vs_e($clabel); ?></button>
        <?php endforeach; ?>
      </div>
      <button type="button" class="th5-catbar__arrow" data-th5-catbar-next aria-label="向后滚动分类"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
    </div>
    <?php endif; ?>

    <div class="th5-market-q__layout">
      <aside class="th5-cat-q reveal">
        <div class="th5-cat-q__title">接口分类</div>
        <div class="th5-cat-q__list" id="TH5CatScroll" role="tablist" aria-label="接口分类">
          <button class="cat-tab active" data-cat="all" type="button">全部接口</button>
          <button class="cat-tab" data-cat="new" type="button">最近上新</button>
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
