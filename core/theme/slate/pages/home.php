<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

// ThemeManager::renderBody() 也会注入同名变量；此处显式读取，避免静态分析误报、并与 default 主题一致
$siteName = SiteContext::siteName();
$siteDesc = SiteContext::siteDescription();
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();

$apiCount = FrontendStats::approvedApiCount();
$totalCalls = FrontendStats::totalCallCount();
$catCount = FrontendCategory::countEnabled();
$userCount = FrontendStats::userCount();
$todayCalls = FrontendStats::todayCallCount();
$catVisibleLimit = FrontendCategory::tagVisibleLimit();
$catBtnIndex = 0;

$heroTitleRaw = trim((string) ThemeManager::themeSetting('hero_title', ''));
$heroTitle = $heroTitleRaw !== '' ? $heroTitleRaw : ('欢迎使用 ' . $siteName);
$heroLeadCustom = trim((string) ThemeManager::themeSetting('hero_lead', ''));
$heroDesc = $heroLeadCustom !== ''
    ? $heroLeadCustom
    : ($siteDesc !== '' ? $siteDesc : '为开发者提供丰富、稳定、快速的 API 数据接口，一行代码即可调用');

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

$showAnnounce = ThemeManager::themeSettingBool('show_home_announce', true);
$announceList = ($showAnnounce && class_exists('FrontendAnnouncement')) ? FrontendAnnouncement::listForTheme() : array();
$announcePopup = ($showAnnounce && class_exists('FrontendAnnouncement')) ? FrontendAnnouncement::listPopups() : array();
$announceMarquee = '欢迎使用 ' . $siteName . '，当前版本 v' . VS_VERSION . ' 已上线！';
$announceTitle = '网站公告';
$announceHtml = '<p>欢迎使用 <strong>' . vs_e($siteName) . '</strong>！</p><p>系统版本 v' . vs_e(VS_VERSION) . ' 已上线，欢迎体验。</p>';
if (count($announceList) > 0) {
    $first = $announceList[0];
    $announceMarquee = isset($first['preview']) && $first['preview'] !== '' ? $first['preview'] : $first['title'];
    $announceTitle = $first['title'];
    $rawBody = isset($first['body']) ? (string) $first['body'] : '';
    $announceHtml = $rawBody !== '' ? slate_md_render($rawBody) : (isset($first['body_html']) ? (string) $first['body_html'] : $announceHtml);
}
$announcePopupKey = '';
if (count($announcePopup) > 0) {
    $pop = $announcePopup[0];
    $announceTitle = $pop['title'];
    $rawBody = isset($pop['body']) ? (string) $pop['body'] : '';
    $announceHtml = $rawBody !== '' ? slate_md_render($rawBody) : (isset($pop['body_html']) ? (string) $pop['body_html'] : $announceHtml);
    if (isset($pop['preview']) && $pop['preview'] !== '') {
        $announceMarquee = $pop['preview'];
    }
    $ids = array();
    foreach ($announcePopup as $p) {
        if (isset($p['id'])) {
            $ids[] = (int) $p['id'];
        }
    }
    sort($ids);
    $announcePopupKey = implode('-', $ids);
}

$showHomePlay = ThemeManager::themeSettingBool('show_home_playground', true);
?>
<main class="st-main" id="stHome">
<div class="st-wrap">
<?php
$seoFallbackDesc = $siteDesc !== '' ? $siteDesc : $siteName;
?>
<p class="vs-seo-fallback-desc"><?php echo vs_e($seoFallbackDesc); ?></p>

<?php if ($showAnnounce): ?>
<div class="st-announce-bundle">
<section class="st-announce-wrap st-announce-wrap--ready" id="homeAnnouncementWrap">
    <button type="button" class="st-announce-bar" id="homeAnnouncementBtn" aria-label="查看公告详情">
        <span class="st-announce__label">公告</span>
        <span class="st-announce__marquee"><span class="st-announce__track is-ready" style="--notice-start:600px;--notice-end:-400px;--notice-duration:24s"><?php echo vs_e($announceMarquee); ?></span></span>
        <span class="st-announce__action">点击查看</span>
    </button>
</section>
<script type="application/json" id="feer-announcement-client-data"><?php echo json_encode(array(
    'home' => array(
        'title'     => $announceTitle,
        'autopopup' => count($announcePopup) > 0,
        'popup_key' => $announcePopupKey,
    ),
), JSON_UNESCAPED_UNICODE); ?></script>
<template id="stAnnounceBodyTpl"><?php echo $announceHtml; ?></template>
<div class="st-announce-modal" id="homeAnnouncementModal" data-modal-kind="home" aria-hidden="true">
    <div class="st-announce-modal__mask" data-close-announcement="1"></div>
    <div class="st-announce-modal__card" role="dialog" aria-modal="true" aria-labelledby="stAnnounceModalTitle">
        <div class="st-announce-modal__head">
            <h3 class="st-announce-modal__title" id="stAnnounceModalTitle"><?php echo vs_e($announceTitle); ?></h3>
            <button type="button" class="st-announce-modal__close" data-close-announcement="1">关闭</button>
        </div>
        <div class="st-announce-modal__body markdown-body vs-md-body st-md" data-announcement-body="home"></div>
        <div class="st-announce-modal__footer">
            <button type="button" class="st-announce-btn st-announce-btn--ghost" data-announcement-dismiss="1">不再提示</button>
            <button type="button" class="st-announce-btn st-announce-btn--primary" data-close-announcement="1">我知道了</button>
        </div>
    </div>
</div>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
</div>
<?php endif; ?>

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
    <div class="st-hero__actions">
        <a class="st-bar__login st-hero__cta" href="#stApiListWrap">浏览接口</a>
        <?php if ($showHomePlay): ?>
        <a class="st-hero__cta st-hero__cta--ghost" href="#stHomePlay">在线调试</a>
        <?php endif; ?>
    </div>
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
    <div class="st-section__head">
        <h2 class="st-section__title">接口预览</h2>
        <p class="st-section__desc">精选展示，完整目录见全部接口</p>
    </div>
    <div class="st-api-grid" id="stApiGrid" aria-busy="true"></div>
    <?php if ($apiCount > 8): ?>
    <div class="st-api-more-wrap">
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="st-bar__login st-api-more-link">查看全部接口</a>
    </div>
    <?php endif; ?>
</section>

<?php if ($showHomePlay): ?>
<section class="st-section st-home-play" id="stHomePlay">
    <div class="st-section__head">
        <h2 class="st-section__title">在线调试</h2>
        <p class="st-section__desc">选择接口后进入详情页「在线测试」标签，完整发送请求与查看响应</p>
    </div>
    <div class="st-home-play__card">
        <label class="st-home-play__label" for="stHomePlaySelect">选择接口</label>
        <div class="st-home-play__row">
            <select id="stHomePlaySelect" class="st-input st-home-play__select" aria-label="选择要调试的接口">
                <option value="">加载中…</option>
            </select>
            <button type="button" class="st-bar__login st-home-play__go" id="stHomePlayGo" disabled>打开在线测试</button>
        </div>
        <p class="st-home-play__hint" id="stHomePlayHint">将跳转到详情页并自动打开「在线测试」标签。</p>
    </div>
</section>
<?php endif; ?>

</div>
</main>
<script>
window.stHomePreviewLimit = 8;
</script>
<?php if ($showAnnounce): ?>
<script src="<?php echo vs_e($vsBase); ?>/core/markdown/assets/js/markdown-render.js?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/pages/slate-markdown.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/pages/home-announcement.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
