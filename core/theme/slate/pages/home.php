<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$categoryNames = FrontendCategory::nameMap();
$apiData = FrontendApi::listForTheme();
$payload = array(
    'categoryNames' => $categoryNames,
    'apiData'       => $apiData,
);
$apiCount = count($apiData);
$totalCalls = ApiManager::totalCallCount();
$catVisibleLimit = FrontendCategory::tagVisibleLimit();
$catBtnIndex = 0;

$heroTitleRaw = trim((string) ThemeManager::themeSetting('hero_title', ''));
$heroTitle = $heroTitleRaw !== '' ? $heroTitleRaw : ('欢迎使用 ' . $siteName);
$heroLeadCustom = trim((string) ThemeManager::themeSetting('hero_lead', ''));
$heroDesc = $heroLeadCustom !== '' ? $heroLeadCustom : (isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '为开发者提供丰富、稳定、快速的 API 数据接口，一行代码即可调用'));
$showStats = ThemeManager::themeSetting('show_stats', true);
$showStats = $showStats === true || $showStats === 1 || $showStats === '1' || $showStats === 'true';
?>
<main class="st-main" id="stHome">
<div class="st-wrap">
<section class="st-hero">
    <h1 class="st-hero__title"><?php echo vs_e($heroTitle); ?></h1>
    <p class="st-hero__lead" id="stHeroLead" data-typewriter="<?php echo vs_e($heroDesc); ?>"><span class="st-hero__lead-text"></span><span class="st-hero__cursor" aria-hidden="true"></span></p>
    <?php if ($showStats): ?>
    <div class="st-stat-pill" role="group" aria-label="接口统计">
        <span class="st-stat-pill__item">收录 <strong class="st-stat-num" id="stStatTotal" data-target="<?php echo (int) $apiCount; ?>">0</strong> 个接口</span>
        <span class="st-stat-pill__sep" aria-hidden="true"></span>
        <span class="st-stat-pill__item">分类 <strong class="st-stat-num" id="stStatCats" data-target="<?php echo (int) FrontendCategory::countEnabled(); ?>">0</strong> 个</span>
        <span class="st-stat-pill__sep" aria-hidden="true"></span>
        <span class="st-stat-pill__item">累计调用 <strong class="st-stat-num" id="stStatAll" data-target="<?php echo (int) $totalCalls; ?>">0</strong> 次</span>
    </div>
    <?php endif; ?>
</section>

<section class="st-section st-home-tools">
    <div class="st-search">
        <span class="st-search__icon" aria-hidden="true">⌕</span>
        <input type="search" id="stSearchInput" class="st-search__input" placeholder="搜索接口名称、描述..." autocomplete="off">
        <button type="button" class="st-search__clear" id="stSearchClear" aria-label="清空搜索" hidden>×</button>
    </div>
    <div class="st-cats" id="stCatBar">
        <button type="button" class="st-cat-tag is-on" data-cat="<?php echo vs_e(FrontendCategory::ALL_ID); ?>"><?php echo vs_e(FrontendCategory::ALL_NAME); ?></button>
        <?php foreach (FrontendCategory::listTags() as $tag): ?>
            <?php
            $hiddenClass = $catBtnIndex >= $catVisibleLimit ? ' st-cat-tag-hidden' : '';
            $catBtnIndex++;
            ?>
            <button type="button" class="st-cat-tag<?php echo $hiddenClass; ?>" data-cat="<?php echo vs_e($tag['id']); ?>"><?php echo vs_e($tag['name']); ?></button>
        <?php endforeach; ?>
        <?php if ($catBtnIndex > $catVisibleLimit): ?>
        <button type="button" class="st-cat-tag st-cat-tag-more" id="stCatMoreBtn" data-expanded="0">
            <span>更多</span>
            <svg class="st-cat-more-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6"></path></svg>
        </button>
        <?php endif; ?>
    </div>
</section>

<section class="st-section st-api-section" id="stApiListWrap">
    <div class="st-api-grid" id="stApiGrid"></div>
    <?php if ($apiCount > 8): ?>
    <div class="st-api-more-wrap">
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="st-bar__login st-api-more-link">查看全部接口</a>
    </div>
    <?php endif; ?>
</section>
</div>
</main>
<script>
window.stApiPayload = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE); ?>;
window.stHomePreviewLimit = 8;
</script>
