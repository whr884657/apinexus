<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$siteDesc = isset($siteDesc) ? (string) $siteDesc : SiteContext::siteDescription();
if ($siteDesc === '' && class_exists('SiteContext')) {
    $siteDesc = SiteContext::siteDescription();
}

$list = class_exists('FrontendApi') ? FrontendApi::listForTheme() : array();
if (!is_array($list)) {
    $list = array();
}
$grouped = array();
$firstDetail = '';
foreach ($list as $item) {
    if (!is_array($item) || empty($item['id']) || empty($item['name'])) {
        continue;
    }
    $cat = isset($item['category_name']) ? trim((string) $item['category_name']) : '';
    if ($cat === '') {
        $cat = '未分类';
    }
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = array();
    }
    $grouped[$cat][] = $item;
    if ($firstDetail === '' && function_exists('vs_api_detail_url')) {
        $firstDetail = vs_api_detail_url((int) $item['id']);
    }
}
if (isset($grouped['未分类'])) {
    $uncat = $grouped['未分类'];
    unset($grouped['未分类']);
    $grouped['未分类'] = $uncat;
}

$apiCount = class_exists('FrontendStats') ? (int) FrontendStats::approvedApiCount() : count($list);
$catCount = class_exists('FrontendCategory') ? (int) FrontendCategory::countEnabled() : count($grouped);
if ($catCount <= 0) {
    $catCount = count($grouped);
}

$showAnnounce = ThemeManager::themeSettingBool('show_home_announce', true);
$announceList = ($showAnnounce && class_exists('FrontendAnnouncement')) ? FrontendAnnouncement::listForTheme() : array();
$announcePopup = ($showAnnounce && class_exists('FrontendAnnouncement')) ? FrontendAnnouncement::listPopups() : array();
if (!is_array($announceList)) {
    $announceList = array();
}
if (!is_array($announcePopup)) {
    $announcePopup = array();
}
$hasAnnounce = count($announceList) > 0;
$announceMarquee = '';
$announceTitle = '公告';
$announceHtml = '';
$announcePopupKey = '';
if ($hasAnnounce) {
    $firstAnn = $announceList[0];
    $announceMarquee = isset($firstAnn['preview']) && $firstAnn['preview'] !== '' ? $firstAnn['preview'] : $firstAnn['title'];
    $announceTitle = $firstAnn['title'];
    $announceHtml = isset($firstAnn['body_html']) ? (string) $firstAnn['body_html'] : '';
}
if ($hasAnnounce && count($announcePopup) > 0) {
    $pop = $announcePopup[0];
    $announceTitle = $pop['title'];
    if (isset($pop['body_html'])) {
        $announceHtml = (string) $pop['body_html'];
    }
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
?>
<link rel="stylesheet" href="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/css/docs-home.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">
<link rel="stylesheet" href="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/css/docs-browser.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">

<div class="d4-home">
<?php if ($hasAnnounce): ?>
<div class="home-announcement-bundle">
<section class="home-announcement-wrap home-announcement-wrap--ready" id="homeAnnouncementWrap">
    <button type="button" class="home-announcement-bar" id="homeAnnouncementBtn" aria-label="查看公告详情">
        <span class="home-announcement-label">公告</span>
        <span class="home-announcement-marquee"><span class="home-announcement-track is-ready"><?php echo vs_e($announceMarquee); ?></span></span>
        <span class="home-announcement-action">点击查看</span>
    </button>
</section>
<script type="application/json" id="feer-announcement-client-data"><?php echo json_encode(array(
    'home' => array(
        'title' => $announceTitle,
        'html' => $announceHtml,
        'autopopup' => count($announcePopup) > 0,
        'popup_key' => $announcePopupKey,
    ),
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<div class="home-announcement-modal" id="homeAnnouncementModal" data-modal-kind="home" aria-hidden="true" inert>
    <div class="home-announcement-modal__mask" data-close-announcement="1"></div>
    <div class="home-announcement-modal__card" role="dialog" aria-modal="true">
        <div class="home-announcement-modal__head"><h3 class="home-announcement-modal__title"><?php echo vs_e($announceTitle); ?></h3><button type="button" class="home-announcement-modal__close" data-close-announcement="1">关闭</button></div>
        <div class="home-announcement-modal__body markdown-body" data-announcement-body="home"></div>
        <div class="home-announcement-modal__footer">
            <button type="button" class="home-announcement-btn-secondary" data-announcement-dismiss="1">不再提示</button>
            <button type="button" class="home-announcement-btn-ok" data-close-announcement="1">我知道了</button>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

    <section class="d4-home__hero">
        <p class="d4-home__eyebrow">接口文档</p>
        <h1><?php echo vs_e($siteName); ?></h1>
        <p class="d4-home__lead"><?php echo $siteDesc !== '' ? vs_e($siteDesc) : '这里是接口说明首页。先看平台介绍和分类，再进入具体接口。'; ?></p>
        <div class="d4-home__actions">
            <a class="d4-home__btn" href="<?php echo vs_e($vsBase); ?>/apis">浏览全部接口</a>
            <?php if ($firstDetail !== ''): ?>
            <a class="d4-home__btn d4-home__btn--ghost" href="<?php echo vs_e($firstDetail); ?>">打开接口文档</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="d4-home__stats" aria-label="概览">
        <div class="d4-home__stat"><b><?php echo (int) $apiCount; ?></b><span>可用接口</span></div>
        <div class="d4-home__stat"><b><?php echo (int) $catCount; ?></b><span>接口分类</span></div>
        <div class="d4-home__stat"><b><?php echo (int) count($list); ?></b><span>文档已收录</span></div>
    </section>

    <section class="d4-home__cats">
        <h2>按分类浏览</h2>
        <?php if (count($grouped) === 0): ?>
        <p class="d4-home__empty">还没有可阅读的接口。审核通过后会出现在这里。</p>
        <?php else: ?>
        <div class="d4-home__grid">
            <?php foreach ($grouped as $catName => $items): ?>
            <article class="d4-home__card">
                <header>
                    <h3><?php echo vs_e($catName); ?></h3>
                    <span><?php echo count($items); ?> 个接口</span>
                </header>
                <ul>
                    <?php foreach (array_slice($items, 0, 6) as $apiItem): ?>
                    <li>
                        <a href="<?php echo vs_e(function_exists('vs_api_detail_url') ? vs_api_detail_url((int) $apiItem['id']) : ($vsBase . '/detail/' . (int) $apiItem['id'])); ?>">
                            <?php echo vs_e($apiItem['name']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($items) > 6): ?>
                <p class="d4-home__more">还有 <?php echo count($items) - 6; ?> 个，见全部接口。</p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php if ($hasAnnounce): ?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/js/pages/home-announcement.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
