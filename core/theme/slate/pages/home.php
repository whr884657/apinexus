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
$catCount = FrontendCategory::countEnabled();
$userCount = FrontendStats::userCount();
$todayCalls = FrontendStats::todayCallCount();
$catVisibleLimit = FrontendCategory::tagVisibleLimit();
$catBtnIndex = 0;

$heroTitleRaw = trim((string) ThemeManager::themeSetting('hero_title', ''));
$heroTitle = $heroTitleRaw !== '' ? $heroTitleRaw : ('欢迎使用 ' . $siteName);
$heroLeadCustom = trim((string) ThemeManager::themeSetting('hero_lead', ''));
$heroDesc = $heroLeadCustom !== '' ? $heroLeadCustom : (isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '为开发者提供丰富、稳定、快速的 API 数据接口，一行代码即可调用'));

$showStats = ThemeManager::themeSetting('show_stats', true);
$showStats = $showStats === true || $showStats === 1 || $showStats === '1' || $showStats === 'true';

$statOn = function ($key, $default = true) {
    $v = ThemeManager::themeSetting($key, $default);
    return $v === true || $v === 1 || $v === '1' || $v === 'true';
};

$showStatApis = $statOn('show_stat_apis', true);
$showStatCats = $statOn('show_stat_cats', true);
$showStatUsers = $statOn('show_stat_users', false);
$showStatToday = $statOn('show_stat_today', true);
$showStatCalls = $statOn('show_stat_calls', true);
$statsNumFormat = ThemeManager::themeSetting('stats_num_format', 'compact');
$statsNumFormat = ($statsNumFormat === 'full') ? 'full' : 'compact';

$statItems = array();
if ($showStatApis) {
    $statItems[] = array('label' => '收录', 'suffix' => '个接口', 'id' => 'stStatTotal', 'target' => $apiCount, 'format' => 'full');
}
if ($showStatCats) {
    $statItems[] = array('label' => '分类', 'suffix' => '个', 'id' => 'stStatCats', 'target' => $catCount, 'format' => 'full');
}
if ($showStatUsers) {
    $statItems[] = array('label' => '用户', 'suffix' => '人', 'id' => 'stStatUsers', 'target' => $userCount, 'format' => 'full');
}
if ($showStatToday) {
    $statItems[] = array('label' => '今日调用', 'suffix' => '次', 'id' => 'stStatToday', 'target' => $todayCalls, 'format' => 'full');
}
if ($showStatCalls) {
    $statItems[] = array('label' => '累计调用', 'suffix' => '次', 'id' => 'stStatAll', 'target' => $totalCalls, 'format' => $statsNumFormat);
}
$showStats = $showStats && count($statItems) > 0;
?>
<main class="st-main" id="stHome">
<div class="st-wrap">
<section class="st-hero">
    <h1 class="st-hero__title"><?php echo vs_e($heroTitle); ?></h1>
    <p class="st-hero__lead" id="stHeroLead" data-typewriter="<?php echo vs_e($heroDesc); ?>"><span class="st-hero__lead-text"></span><span class="st-hero__cursor" aria-hidden="true"></span></p>
    <?php if ($showStats): ?>
    <div class="st-stat-pill" role="group" aria-label="接口统计">
        <?php foreach ($statItems as $i => $item): ?>
            <?php if ($i > 0): ?><span class="st-stat-pill__sep" aria-hidden="true"></span><?php endif; ?>
            <span class="st-stat-pill__item"><?php echo vs_e($item['label']); ?> <strong class="st-stat-num" id="<?php echo vs_e($item['id']); ?>" data-target="<?php echo (int) $item['target']; ?>" data-format="<?php echo vs_e(isset($item['format']) ? $item['format'] : 'full'); ?>">0</strong> <?php echo vs_e($item['suffix']); ?></span>
        <?php endforeach; ?>
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
