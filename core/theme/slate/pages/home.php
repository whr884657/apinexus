<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$heroTitleRaw = trim((string) ThemeManager::themeSetting('hero_title', ''));
$heroTitle = $heroTitleRaw !== '' ? $heroTitleRaw : ('欢迎使用 ' . $siteName);
$heroLeadCustom = trim((string) ThemeManager::themeSetting('hero_lead', ''));
$heroDesc = $heroLeadCustom !== '' ? $heroLeadCustom : (isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '为开发者提供丰富、稳定、快速的 API 数据接口，一行代码即可调用'));
$showStats = ThemeManager::themeSetting('show_stats', true);
$showStats = $showStats === true || $showStats === 1 || $showStats === '1' || $showStats === 'true';
$apiCategories = array('全部', '生活服务', '图片相关', '查询工具', '内容生成', '便捷工具', '社交娱乐');
?>
<main class="st-main" id="stHome">
<div class="st-wrap">
<section class="st-hero">
    <h1 class="st-hero__title"><?php echo vs_e($heroTitle); ?></h1>
    <p class="st-hero__lead" id="stHeroLead" data-typewriter="<?php echo vs_e($heroDesc); ?>"><span class="st-hero__lead-text"></span><span class="st-hero__cursor" aria-hidden="true"></span></p>
    <?php if ($showStats): ?>
    <div class="st-stat-pill" role="group" aria-label="接口统计">
        <span class="st-stat-pill__item">收录 <strong class="st-stat-num" id="stStatTotal">0</strong> 个接口</span>
        <span class="st-stat-pill__sep" aria-hidden="true"></span>
        <span class="st-stat-pill__item">今日调用 <strong class="st-stat-num st-stat-pill__accent" id="stStatToday">0</strong> 次</span>
        <span class="st-stat-pill__sep" aria-hidden="true"></span>
        <span class="st-stat-pill__item">累计调用 <strong class="st-stat-num" id="stStatAll">0</strong> 次</span>
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
        <?php foreach ($apiCategories as $i => $cat): ?>
            <button type="button" class="st-cat-tag<?php echo $i === 0 ? ' is-on' : ''; ?>" data-cat="<?php echo vs_e($cat); ?>"><?php echo vs_e($cat); ?></button>
        <?php endforeach; ?>
    </div>
</section>

<section class="st-section st-api-section" id="stApiListWrap">
    <div class="st-api-empty">
        <div class="st-api-empty__icon" aria-hidden="true">⌕</div>
        <p class="st-api-empty__title">接口列表建设中</p>
        <p class="st-api-empty__desc">公开接口模块完善后将在此展示，支持搜索与分类筛选。</p>
        <a href="<?php echo vs_e($navItems[1]['url'] ?? ($vsBase . '/apis')); ?>" class="st-bar__login" style="display:inline-flex;margin-top:12px;">查看全部接口</a>
    </div>
</section>
</div>
</main>
