<?php if (!defined('VS_THEME_RENDER')) { exit; }

$apiData = FrontendApi::listForTheme();
$apiCount = count($apiData);
$catVisibleLimit = FrontendCategory::tagVisibleLimit();
$catIndex = 0;
?>
<main class="st-main" id="stApisPage">
<div class="st-wrap">
<section class="st-section st-apis-page">
    <h1 class="st-page-title">全部接口</h1>
    <p class="st-page-desc">共 <span id="stApiTotalCount"><?php echo (int) $apiCount; ?></span> 个 API 接口</p>

    <div class="st-search st-search--page">
        <span class="st-search__icon" aria-hidden="true">⌕</span>
        <input type="search" id="stApisSearchInput" class="st-search__input" placeholder="搜索接口名称、描述..." autocomplete="off">
        <button type="button" class="st-search__clear" id="stApisSearchClear" aria-label="清空搜索" hidden>×</button>
    </div>

    <div class="st-cats st-cats--page" id="stApisCatBar">
        <button type="button" class="st-cat-tag is-on" data-cat="<?php echo vs_e(FrontendCategory::ALL_ID); ?>"><?php echo vs_e(FrontendCategory::ALL_NAME); ?></button>
        <?php foreach (FrontendCategory::listTags() as $tag): ?>
            <?php
            $hiddenClass = $catIndex >= $catVisibleLimit ? ' st-cat-tag-hidden' : '';
            $catIndex++;
            ?>
            <button type="button" class="st-cat-tag<?php echo $hiddenClass; ?>" data-cat="<?php echo vs_e($tag['id']); ?>"><?php echo vs_e($tag['name']); ?></button>
        <?php endforeach; ?>
        <?php if ($catIndex > $catVisibleLimit): ?>
        <button type="button" class="st-cat-tag st-cat-tag-more" id="stApisCatMoreBtn" data-expanded="0">
            <span>更多</span>
            <svg class="st-cat-more-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6"></path></svg>
        </button>
        <?php endif; ?>
    </div>

    <div class="st-api-grid" id="stApisGrid">
        <?php include __DIR__ . '/../partials/api-cards-html.php'; ?>
    </div>
    <div id="stApisPagination" class="st-pagination" style="display:none;"></div>
</section>
</div>
</main>
<script>
window.stApiPayload = <?php echo json_encode(array(
    'categoryNames' => FrontendCategory::nameMap(),
    'apiData'       => $apiData,
), JSON_UNESCAPED_UNICODE); ?>;
</script>
