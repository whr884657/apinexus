<?php
/**
 * 主题 five · 首页（七牛云 AI Agent SaaS 风格）
 * 布局：企业导航 / 左文右控制台 Hero / 4 步接入大数字步骤 / 新用户福利横幅 /
 *       接口上新卡片 / 企业订阅三档套餐 / ONE PLATFORM 一站式能力 /
 *       开发文档 / FAQ 手风琴 / CTA / 企业页脚
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$siteName = SiteContext::siteName();
$navName = SiteContext::navName();
$siteLogo = SiteContext::siteLogo();
$hasSiteLogo = trim((string) $siteLogo) !== '';
$siteDesc = SiteContext::siteDescription();
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$userLoggedIn = !empty($userLoggedIn);

$apiCount = FrontendStats::approvedApiCount();
$totalCalls = FrontendStats::totalCallCount();
$todayCalls = FrontendStats::todayCallCount();
$catTags = FrontendCategory::listTags();

$statsNumFormat = ThemeManager::themeSetting('stats_num_format', 'compact');
$statsNumFormat = ($statsNumFormat === 'full') ? 'full' : 'compact';

$fmtInt = function ($n) {
    $n = (int) $n;
    return number_format($n, 0, '.', ',');
};
$apiCountLabel = $fmtInt($apiCount);
$todayCallsLabel = $fmtInt($todayCalls);

$homePreviewLimit = (int) ThemeManager::themeSetting('home_preview_limit', 12);
if ($homePreviewLimit < 4) {
    $homePreviewLimit = 4;
}
if ($homePreviewLimit > 24) {
    $homePreviewLimit = 24;
}

$rechargePackages = PayConfig::packages();
$rechargeFeatured = -1;
foreach ($rechargePackages as $i => $pkg) {
    if (!empty($pkg['hot'])) {
        $rechargeFeatured = (int) $i;
        break;
    }
}
if ($rechargeFeatured < 0 && count($rechargePackages) >= 2) {
    $rechargeFeatured = 1;
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

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
    $announceHtml = $rawBody !== '' ? TH5_md_render($rawBody) : (isset($first['body_html']) ? (string) $first['body_html'] : $announceHtml);
}
$announcePopupKey = '';
if (count($announcePopup) > 0) {
    $pop = $announcePopup[0];
    $announceTitle = $pop['title'];
    $rawBody = isset($pop['body']) ? (string) $pop['body'] : '';
    $announceHtml = $rawBody !== '' ? TH5_md_render($rawBody) : (isset($pop['body_html']) ? (string) $pop['body_html'] : $announceHtml);
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
<script>
window.TH5_HOME = {
  previewLimit: <?php echo (int) $homePreviewLimit; ?>,
  apiCount: <?php echo (int) $apiCount; ?>,
  statsFormat: <?php echo json_encode($statsNumFormat, JSON_UNESCAPED_UNICODE); ?>,
  vsBase: <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>,
  heroTerminalLines: 6
};
</script>

<p class="vs-seo-fallback-desc"><?php echo vs_e($siteDesc !== '' ? $siteDesc : ($siteName . ' API 聚合平台')); ?></p>

<?php if ($showAnnounce): ?>
<div class="th5-announce-bundle">
<section class="th5-announce-wrap th5-announce-wrap--pending" id="homeAnnouncementWrap">
  <button type="button" class="th5-announce-bar" id="homeAnnouncementBtn" aria-label="查看公告详情">
    <span class="th5-announce__label">公告</span>
    <span class="th5-announce__marquee"><span class="th5-announce__track"><?php echo vs_e($announceMarquee); ?></span></span>
    <span class="th5-announce__action">点击查看</span>
  </button>
</section>
<script type="application/json" id="feer-announcement-client-data"><?php echo json_encode(array(
    'home' => array(
        'title'     => $announceTitle,
        'html'      => $announceHtml,
        'autopopup' => count($announcePopup) > 0,
        'popup_key' => $announcePopupKey,
    ),
), JSON_UNESCAPED_UNICODE); ?></script>
<div class="th5-announce-modal" id="homeAnnouncementModal" data-modal-kind="home" aria-hidden="true" inert>
  <div class="th5-announce-modal__mask" data-close-announcement="1"></div>
  <div class="th5-announce-modal__card" role="dialog" aria-modal="true" aria-labelledby="TH5AnnounceModalTitle">
    <div class="th5-announce-modal__head">
      <h3 class="th5-announce-modal__title" id="TH5AnnounceModalTitle"><?php echo vs_e($announceTitle); ?></h3>
      <button type="button" class="th5-announce-modal__close" data-close-announcement="1">关闭</button>
    </div>
    <div class="th5-announce-modal__body markdown-body vs-md-body" data-announcement-body="home"></div>
    <div class="th5-announce-modal__footer">
      <button type="button" class="th5-announce-btn th5-announce-btn--ghost" data-announcement-dismiss="1">不再提示</button>
      <button type="button" class="th5-announce-btn th5-announce-btn--primary" data-close-announcement="1">我知道了</button>
    </div>
  </div>
</div>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
</div>
<?php endif; ?>

<!-- ============ Hero · 左文右控制台 ============ -->
<section class="th5-hero-q">
  <div class="th5-hero-q__bg" aria-hidden="true"></div>
  <div class="th5-hero-q__bg-grid" aria-hidden="true"></div>
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8 th5-hero-q__inner">
    <div class="th5-hero-q__copy reveal">
      <span class="th5-hero-q__eyebrow">
        <i data-lucide="zap" style="width:14px;height:14px;"></i>
        AI 大模型平台 · 已聚合 <?php echo vs_e($apiCountLabel); ?> 个接口
      </span>
      <h1 class="th5-hero-q__title font-display">
        全网接口，<span class="gradient-text">一站调用</span>
      </h1>
      <div class="th5-hero-q__tags">
        <span class="th5-hero-q__tag">稳定</span>
        <span class="th5-hero-q__tag">兼容</span>
        <span class="th5-hero-q__tag">透明</span>
        <span class="th5-hero-q__tag">安全</span>
      </div>
      <p class="th5-hero-q__lead">
        天气、IP、物流、翻译、识别、短信、汇率 —— 市面可见的接口，全部聚合到一个平台。
        统一鉴权、积分计费、统一监控，告别在数十家供应商间来回切换。
      </p>
      <div class="th5-hero-q__actions">
        <a href="#market" class="btn-primary justify-center lg:justify-start">
          浏览 API 市场
          <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
        </a>
        <a href="<?php echo vs_e($vsBase); ?>/articles" class="btn-ghost justify-center lg:justify-start">
          <i data-lucide="book-open" style="width:16px;height:16px;"></i>
          使用文档
        </a>
      </div>
      <div class="th5-hero-q__stats">
        <div class="th5-hero-q__stat">
          <div class="th5-hero-q__stat-num stat-num"><span data-count="<?php echo (int) $apiCount; ?>">0</span><span class="th5-hero-q__stat-plus">+</span></div>
          <div class="th5-hero-q__stat-label">API 接口</div>
        </div>
        <div class="th5-hero-q__stat">
          <div class="th5-hero-q__stat-num stat-num"><span data-count="<?php echo (int) $totalCalls; ?>" data-format="<?php echo vs_e($statsNumFormat); ?>">0</span><span class="stat-suffix"></span></div>
          <div class="th5-hero-q__stat-label">累计调用</div>
        </div>
        <div class="th5-hero-q__stat">
          <div class="th5-hero-q__stat-num stat-num"><span data-count="99.99" data-decimals="2">0</span><span>%</span></div>
          <div class="th5-hero-q__stat-label">服务可用率</div>
        </div>
      </div>
    </div>

    <div class="th5-hero-console reveal reveal-delay-1" aria-label="API 控制台示例">
      <div class="th5-hero-console__bar">
        <span class="th5-hero-console__dot"></span><span class="th5-hero-console__dot"></span><span class="th5-hero-console__dot"></span>
        <span class="th5-hero-console__title">API Console — Playground</span>
      </div>
      <div class="th5-hero-console__body">
        <div class="th5-hero-console__code" aria-hidden="true">
          <div class="th5-hero-console__line"><span class="c-prompt">$</span> <span class="c-kw">curl</span> <?php echo vs_e($vsBase)?:"https://xiaoapi.cn"; ?>/v1/zs_tq.php</div>
          <div class="th5-hero-console__line">  -H <span class="c-str">"Authorization: Bearer sk-••••••"</span></div>
          <div class="th5-hero-console__line">  -d <span class="c-str">'[{"msg":"宁波"},{"n":"1"}]'</span></div>
          <div class="th5-hero-console__line"><span class="c-kw">HTTP/1.1</span> <span class="c-ok">200 OK</span> · 34ms</div>
        </div>
        <div class="th5-hero-console__row">
          <span class="th5-hero-console__row-method">GET</span>
          <span class="th5-hero-console__row-path">/v1/zs_tts.php</span>
          <span class="th5-hero-console__row-status">200</span>
        </div>
        <div class="th5-hero-console__row">
          <span class="th5-hero-console__row-method">POST</span>
          <span class="th5-hero-console__row-path">/v1/zs_xw.php</span>
          <span class="th5-hero-console__row-status">200</span>
        </div>
        <div class="th5-hero-console__row">
          <span class="th5-hero-console__row-method">GET</span>
          <span class="th5-hero-console__row-path">/v1/zs_xzys.php</span>
          <span class="th5-hero-console__row-status">200</span>
        </div>
        <div class="th5-hero-console__foot">
          <span class="th5-hero-console__live">LIVE</span>
          <span class="th5-hero-console__meta font-mono">今日已累计· <?php echo vs_e($todayCallsLabel); ?> 次请求</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 4 步完成接入 ============ -->
<section class="th5-steps">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal">
      <span class="th5-section-head-q__eyebrow">4 步完成 API 接入</span>
      <h2 class="th5-section-head-q__title font-display">快速开始，即刻调用</h2>
      <p class="th5-section-head-q__sub">从注册到调用，最快 5 分钟完成第一次接口接入。</p>
    </div>
    <div class="th5-steps__grid">
      <div class="th5-step-card reveal">
        <div class="th5-step-card__num font-mono" aria-hidden="true">01</div>
        <div class="th5-step-card__icon"><i data-lucide="user-plus" style="width:20px;height:20px;"></i></div>
        <h3 class="th5-step-card__title">注册登录</h3>
        <p class="th5-step-card__desc">完成账号注册或登录，进入控制台准备开通服务。</p>
        <a href="<?php echo vs_e($vsBase); ?>/user/register" class="th5-step-card__link">立即注册/登录<i data-lucide="arrow-right" style="width:14px;height:14px;"></i></a>
      </div>
      <div class="th5-step-card reveal reveal-delay-1">
        <div class="th5-step-card__num font-mono" aria-hidden="true">02</div>
        <div class="th5-step-card__icon"><i data-lucide="key-round" style="width:20px;height:20px;"></i></div>
        <h3 class="th5-step-card__title">创建 API Key</h3>
        <p class="th5-step-card__desc">在控制台创建 API Key，用于后续服务鉴权和调用。</p>
        <a href="<?php echo vs_e($vsBase); ?>/user/keys" class="th5-step-card__link">创建 API Key<i data-lucide="arrow-right" style="width:14px;height:14px;"></i></a>
      </div>
      <div class="th5-step-card reveal reveal-delay-2">
        <div class="th5-step-card__num font-mono" aria-hidden="true">03</div>
        <div class="th5-step-card__icon"><i data-lucide="database" style="width:20px;height:20px;"></i></div>
        <h3 class="th5-step-card__title">选购接口</h3>
        <p class="th5-step-card__desc">浏览接口市场，按需选择需要的接口与计费方式。</p>
        <a href="#market" class="th5-step-card__link">浏览接口<i data-lucide="arrow-right" style="width:14px;height:14px;"></i></a>
      </div>
      <div class="th5-step-card reveal reveal-delay-3">
        <div class="th5-step-card__num font-mono" aria-hidden="true">04</div>
        <div class="th5-step-card__icon"><i data-lucide="terminal" style="width:20px;height:20px;"></i></div>
        <h3 class="th5-step-card__title">调用 API</h3>
        <p class="th5-step-card__desc">参考接入文档完成接口调用，快速接入业务应用。</p>
        <a href="<?php echo vs_e($vsBase); ?>/articles" class="th5-step-card__link">查看文档<i data-lucide="arrow-right" style="width:14px;height:14px;"></i></a>
      </div>
    </div>
  </div>
</section>

<!-- ============ 新用户福利横幅 ============ -->
<section class="th5-benefit">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-benefit__inner reveal">
      <div class="th5-benefit__grid" aria-hidden="true"></div>
      <div class="th5-benefit__left">
        <span class="th5-benefit__label">新用户专享</span>
        <h3 class="th5-benefit__title">注册即领 500 积分，企业认证再领 2000 积分</h3>
        <p class="th5-benefit__sub">快速体验平台全部接口能力，注册即送，积分永久有效。</p>
      </div>
      <div class="th5-benefit__right">
        <a href="<?php echo vs_e($vsBase); ?>/user/register" class="th5-benefit__cta">
          立即注册
          <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
        </a>
      </div>
    </div>
  </div>
</section>


<!-- ============ 首页广告位（后台主题设置 → 首页广告位，多条自动轮播） ============ -->
<?php
$homeAdEnabled = ThemeManager::themeSettingBool('home_ad_enabled', true);
$homeAdSlides = array();
$adInterval = 5000;
if ($homeAdEnabled) {
    $adInterval = (int) ThemeManager::themeSettingInt('home_ad_interval', 5000);
    if ($adInterval < 2000) {
        $adInterval = 5000;
    }
    if ($adInterval > 20000) {
        $adInterval = 20000;
    }

    for ($adSlot = 1; $adSlot <= 4; $adSlot++) {
        $sEnabled = ThemeManager::themeSettingBool('home_ad_' . $adSlot . '_enabled', $adSlot === 1);
        if (!$sEnabled) {
            continue;
        }
        $sLegacy = ($adSlot === 1);

        $sType = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_type', ''));
        if ($sLegacy && $sType === '') {
            $sType = trim((string) ThemeManager::themeSettingStr('home_ad_type', 'image'));
        }
        $sType = ($sType === 'text') ? 'text' : 'image';

        $sImage = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_image', ''));
        if ($sLegacy && $sImage === '') {
            $sImage = trim((string) ThemeManager::themeSettingStr('home_ad_image', ''));
        }
        $sBadge = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_badge', ''));
        if ($sLegacy && $sBadge === '') {
            $sBadge = trim((string) ThemeManager::themeSettingStr('home_ad_badge', ''));
        }
        $sTitle = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_title', ''));
        if ($sLegacy && $sTitle === '') {
            $sTitle = trim((string) ThemeManager::themeSettingStr('home_ad_title', ''));
        }
        $sDesc = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_desc', ''));
        if ($sLegacy && $sDesc === '') {
            $sDesc = trim((string) ThemeManager::themeSettingStr('home_ad_desc', ''));
        }
        $sLink = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_link', ''));
        if ($sLegacy && $sLink === '') {
            $sLink = trim((string) ThemeManager::themeSettingStr('home_ad_link', ''));
        }
        $sBtn = trim((string) ThemeManager::themeSettingStr('home_ad_' . $adSlot . '_button_text', ''));
        if ($sLegacy && $sBtn === '') {
            $sBtn = trim((string) ThemeManager::themeSettingStr('home_ad_button_text', ''));
        }

        $sImageSrc = '';
        if ($sImage !== '') {
            if (preg_match('#^(https?:)?//#i', $sImage) || strpos($sImage, '/') === 0) {
                $sImageSrc = $sImage;
            } elseif (strpos($sImage, 'assets/') === 0) {
                $sImageSrc = ThemeManager::assetUrl('muming', $sImage);
            } elseif (function_exists('vs_site_path')) {
                $sImageSrc = vs_site_path($sImage);
            } else {
                $sImageSrc = $vsBase . '/' . ltrim($sImage, '/');
            }
        }

        $sLinkHref = '';
        if ($sLink !== '') {
            if (preg_match('#^(https?:)?//#i', $sLink) || strpos($sLink, '/') === 0 || preg_match('#^mailto:#i', $sLink)) {
                $sLinkHref = $sLink;
            } else {
                $sLinkHref = 'https://' . ltrim($sLink, '/');
            }
        }

        $sHasText = ($sBadge !== '' || $sTitle !== '' || $sDesc !== '');
        $sHasMedia = ($sType === 'image' && $sImageSrc !== '');
        if ($sHasText || $sHasMedia) {
            $homeAdSlides[] = array(
                'type'  => $sType,
                'image' => $sImageSrc,
                'badge' => $sBadge,
                'title' => $sTitle,
                'desc'  => $sDesc,
                'link'  => $sLinkHref,
                'btn'   => $sBtn,
            );
        }
    }
}
?>
<?php if (count($homeAdSlides) > 0): ?>
<?php
$adSlideCount = count($homeAdSlides);
$adChevL = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
$adChevR = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
?>
<section class="th5-ad">
  <div class="th5-ad__wrap">
    <div class="th5-ad-carousel<?php echo $adSlideCount === 1 ? ' is-single' : ''; ?> reveal" data-th5-ad-carousel data-th5-ad-interval="<?php echo (int) $adInterval; ?>">
      <div class="th5-ad-carousel__track" data-th5-ad-track>
        <?php foreach ($homeAdSlides as $adSlide): ?>
        <?php
        $sTitleHtml = '';
        if ($adSlide['title'] !== '') {
            $sTitleLines = array();
            foreach (preg_split('#\s*/\s*#u', $adSlide['title']) as $sLine) {
                $sLine = trim((string) $sLine);
                if ($sLine !== '') {
                    $sTitleLines[] = vs_e($sLine);
                }
            }
            $sTitleHtml = implode('<br>', $sTitleLines);
        }
        $sHasImage = ($adSlide['type'] === 'image' && $adSlide['image'] !== '');
        $sBtns = array();
        if ($adSlide['link'] !== '' && $adSlide['btn'] !== '') {
            $sBtns[] = '<a class="th5-ad__btn th5-ad__btn--primary" href="' . vs_e($adSlide['link']) . '"' . (preg_match('#^mailto:#i', $adSlide['link']) ? '' : ' target="_blank" rel="noopener noreferrer"') . '>' . vs_e($adSlide['btn']) . '<i data-lucide="arrow-right" style="width:16px;height:16px;"></i></a>';
        }
        ?>
        <div class="th5-ad-carousel__slide">
          <div class="th5-ad__inner<?php echo $sHasImage ? '' : ' th5-ad__inner--text'; ?>">
            <div class="th5-ad__glow" aria-hidden="true"></div>
            <div class="th5-ad__body">
              <?php if ($adSlide['badge'] !== ''): ?>
              <span class="th5-ad__badge"><i data-lucide="shield-check" style="width:14px;height:14px;"></i><?php echo vs_e($adSlide['badge']); ?></span>
              <?php endif; ?>
              <?php if ($sTitleHtml !== ''): ?>
              <h2 class="th5-ad__title font-display"><?php echo $sTitleHtml; ?></h2>
              <?php endif; ?>
              <?php if ($adSlide['desc'] !== ''): ?>
              <p class="th5-ad__desc"><?php echo vs_e($adSlide['desc']); ?></p>
              <?php endif; ?>
              <?php if (count($sBtns) > 0): ?>
              <div class="th5-ad__actions"><?php echo implode('', $sBtns); ?></div>
              <?php endif; ?>
            </div>
            <?php if ($sHasImage): ?>
            <div class="th5-ad__media">
              <img class="th5-ad__img" src="<?php echo vs_e($adSlide['image']); ?>" alt="<?php echo vs_e($adSlide['badge'] !== '' ? $adSlide['badge'] : '广告'); ?>" loading="lazy" decoding="async">
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($adSlideCount > 1): ?>
      <button type="button" class="th5-ad-carousel__arrow th5-ad-carousel__arrow--prev" data-th5-ad-prev aria-label="上一张广告"><?php echo $adChevL; ?></button>
      <button type="button" class="th5-ad-carousel__arrow th5-ad-carousel__arrow--next" data-th5-ad-next aria-label="下一张广告"><?php echo $adChevR; ?></button>
      <?php endif; ?>
    </div>
    <?php if ($adSlideCount > 1): ?>
    <div class="th5-ad-carousel__dots" data-th5-ad-dots role="tablist" aria-label="广告切换"></div>
    <?php endif; ?>
  </div>
</section>
<?php if ($adSlideCount > 1): ?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/js/pages/home-ad-carousel.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
<?php endif; ?>
<!-- ============ 接口上新（模型上新式卡片） ============ -->
<section id="market" class="th5-new">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-new__head reveal">
      <div>
        <h2 class="th5-new__title font-display">接口上新，现已上架</h2>
        <p class="th5-new__sub">持续接入主流接口，通过统一 API 即刻调用。</p>
      </div>
      <a href="<?php echo vs_e($vsBase); ?>/apis" class="th5-new__more">
        查看全部接口
        <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
      </a>
    </div>

    <div class="th5-market-q__layout">
      <!-- 左：分类目录 -->
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
          <span id="TH5ApiTotalLabel"><?php echo (int) $apiCount; ?></span> endpoints available
        </div>
      </aside>

      <!-- 右：搜索 + 卡片网格 -->
      <div class="th5-market-q__main reveal reveal-delay-1">
        <div class="th5-search-q">
          <span class="th5-search-q__icon" aria-hidden="true"><i data-lucide="search" style="width:17px;height:17px;"></i></span>
          <input id="apiSearch" class="input" type="search" enterkeyhint="search"
                 placeholder="搜索 API…" data-ph-tpl="搜索 {n} 个 API…" autocomplete="off">
        </div>
        <div id="apiGrid" class="th5-api-grid-q"></div>
        <div class="th5-market-q__foot reveal">
          <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-ghost">
            查看全部接口
            <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 企业订阅推荐 ============ -->
<section id="pricing" class="th5-plans">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal">
      <span class="th5-section-head-q__eyebrow">Enterprise Subscription</span>
      <h2 class="th5-section-head-q__title font-display">积分订阅推荐</h2>
      <p class="th5-section-head-q__sub">适合有持续接口调用需求，希望统一管理成本、接口与团队用量的用户。按业务调用规模灵活选择，可先从轻量档位起步，业务增长后再升级。</p>
    </div>

    <div class="th5-plans__grid">
      <?php if (count($rechargePackages) > 0): ?>
        <?php foreach (array_slice($rechargePackages, 0, 3) as $i => $pkg): ?>
          <?php
            $isFeatured = ((int) $i === $rechargeFeatured);
            $money = isset($pkg['money']) ? (string) $pkg['money'] : '0';
            $points = isset($pkg['points']) ? (string) $pkg['points'] : '0';
            $pkgName = isset($pkg['name']) ? (string) $pkg['name'] : '套餐';
            $pkgDesc = isset($pkg['desc']) ? (string) $pkg['desc'] : '';
            $pkgDesc = $pkgDesc !== '' ? $pkgDesc : (($i === 0) ? '轻量起步 · 个人体验 / 内部工具' : (($i === 1) ? '最受欢迎 · 中频业务 / 日常调用' : '高性能 · 高频业务 / 团队共享'));
            $pointsLabel = number_format((float) $points, 0, '.', ',');
            $origPrice = ((int) $money > 0) ? (string) ((int) round((float) $money * 1.25)) : '';
            $featureList = isset($pkg['features']) && is_array($pkg['features']) ? $pkg['features'] : array();
            if (count($featureList) < 3) {
              $featureList = array('积分永久有效', '全部 API 可调用', '余额随时可查');
            }
            $indexLabel = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
          ?>
          <div class="reveal<?php echo $i > 0 ? ' reveal-delay-' . min($i, 3) : ''; ?> th5-plan-card<?php echo $isFeatured ? ' th5-plan-card--featured' : ''; ?>">
            <?php if ($isFeatured): ?><span class="th5-plan-card__badge">最受欢迎</span><?php endif; ?>
            <div class="th5-plan-card__index font-mono"><?php echo $indexLabel; ?> / 选择套餐</div>
            <h3 class="th5-plan-card__name"><?php echo vs_e($pkgName); ?></h3>
            <p class="th5-plan-card__desc"><?php echo vs_e($pkgDesc); ?></p>
            <span class="th5-plan-card__points"><i data-lucide="coins" style="width:15px;height:15px;"></i>到账 <b><?php echo vs_e($pointsLabel); ?></b> 积分</span>
            <div class="th5-plan-card__price">
              <span class="th5-plan-card__cur">¥</span>
              <span class="th5-plan-card__num"><?php echo vs_e($money); ?></span>
              <?php if ($origPrice !== ''): ?><span class="th5-plan-card__orig">¥<?php echo vs_e($origPrice); ?></span><?php endif; ?>
            </div>
            <div class="th5-plan-card__period">积分永久有效，用多少扣多少</div>
            <ul class="th5-plan-card__list">
              <?php foreach ($featureList as $feat): ?>
                <li><i data-lucide="check" style="width:15px;height:15px;"></i><?php echo vs_e((string) $feat); ?></li>
              <?php endforeach; ?>
            </ul>
            <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="th5-plan-card__cta<?php echo $isFeatured ? ' th5-plan-card__cta--primary' : ''; ?>">
              立即充值
              <?php if ($isFeatured): ?><i data-lucide="arrow-right" style="width:14px;height:14px;"></i><?php endif; ?>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="reveal th5-plan-card th5-plan-card--featured" style="grid-column:1/-1;text-align:center;align-items:center;">
          <p class="text-muted">充值套餐暂未配置，请前往用户中心充值页查看。</p>
          <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="btn-primary inline-flex mt-4">前往充值</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ ONE PLATFORM 一站式 ============ -->
<section id="features" class="th5-one">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal">
      <span class="th5-section-head-q__eyebrow">ONE PLATFORM</span>
      <h2 class="th5-section-head-q__title font-display">一站式接入接口能力</h2>
      <p class="th5-section-head-q__sub">聚合多类接口、兼容主流调用方式，并提供面向生产环境的企业级稳定服务质量。</p>
    </div>

    <div class="th5-one__features">
      <div class="th5-one-feat reveal">
        <div class="th5-one-feat__head">
          <span class="th5-one-feat__icon"><i data-lucide="database" style="width:22px;height:22px;"></i></span>
          <div>
            <span class="th5-one-feat__index font-mono">01 / 统一接口</span>
            <h3 class="th5-one-feat__title">一个入口接入全部接口</h3>
          </div>
        </div>
        <p class="th5-one-feat__desc">覆盖天气、AI、短信、物流等多场景能力，业务无需为不同接口重复适配调用链路。</p>
        <ul class="th5-one-feat__points">
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>统一模型目录</li>
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>多场景能力覆盖</li>
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>按需切换接口</li>
        </ul>
      </div>
      <div class="th5-one-feat reveal reveal-delay-1">
        <div class="th5-one-feat__head">
          <span class="th5-one-feat__icon"><i data-lucide="code-2" style="width:22px;height:22px;"></i></span>
          <div>
            <span class="th5-one-feat__index font-mono">02 / 全协议兼容</span>
            <h3 class="th5-one-feat__title">兼容常用调用协议</h3>
          </div>
        </div>
        <p class="th5-one-feat__desc">支持 RESTful 原生接口与常见兼容协议，既能快速迁移已有应用，也能沉淀统一工程规范。</p>
        <ul class="th5-one-feat__points">
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>主流协议兼容</li>
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>原生接口支持</li>
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>统一鉴权管理</li>
        </ul>
      </div>
      <div class="th5-one-feat reveal reveal-delay-2">
        <div class="th5-one-feat__head">
          <span class="th5-one-feat__icon"><i data-lucide="shield-check" style="width:22px;height:22px;"></i></span>
          <div>
            <span class="th5-one-feat__index font-mono">03 / 企业级稳定</span>
            <h3 class="th5-one-feat__title">面向生产的稳定服务</h3>
          </div>
        </div>
        <p class="th5-one-feat__desc">依托平台基础设施与调度能力，为企业级应用提供更可靠的调用、监控和资源保障。</p>
        <ul class="th5-one-feat__points">
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>稳定调用链路</li>
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>用量可观测</li>
          <li><i data-lucide="check" style="width:14px;height:14px;"></i>资源保障</li>
        </ul>
      </div>
    </div>

    <div class="th5-one__pool">
      <div class="th5-pool-card reveal">
        <span class="th5-pool-card__icon"><i data-lucide="layers" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-pool-card__title">接口能力池</div><div class="th5-pool-card__desc">聚合多场景、多供应商接口能力</div></div>
      </div>
      <div class="th5-pool-card reveal reveal-delay-1">
        <span class="th5-pool-card__icon"><i data-lucide="key-round" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-pool-card__title">统一 Key 管理</div><div class="th5-pool-card__desc">一套凭证贯穿接入、鉴权与治理</div></div>
      </div>
      <div class="th5-pool-card reveal reveal-delay-2">
        <span class="th5-pool-card__icon"><i data-lucide="rocket" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-pool-card__title">高效 API 构建</div><div class="th5-pool-card__desc">从试用到生产保持同一调用链路</div></div>
      </div>
      <div class="th5-pool-card reveal reveal-delay-3">
        <span class="th5-pool-card__icon"><i data-lucide="activity" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-pool-card__title">服务质量保障</div><div class="th5-pool-card__desc">稳定性、可观测与资源协同支撑</div></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 开发文档 ============ -->
<section class="th5-docs">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal">
      <span class="th5-section-head-q__eyebrow">DEVELOPER DOCS</span>
      <h2 class="th5-section-head-q__title font-display">开发文档</h2>
      <p class="th5-section-head-q__sub">选择分类快速找到所需文档，从账号注册到接口接入全流程指引。</p>
    </div>
    <div class="th5-docs__grid">
      <a href="<?php echo vs_e($vsBase); ?>/user/register" class="th5-doc-card reveal">
        <span class="th5-doc-card__icon"><i data-lucide="user-plus" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-doc-card__title">账号注册</div><div class="th5-doc-card__desc">注册与基础配置指南</div></div>
      </a>
      <a href="<?php echo vs_e($vsBase); ?>/user/keys" class="th5-doc-card reveal reveal-delay-1">
        <span class="th5-doc-card__icon"><i data-lucide="key-round" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-doc-card__title">创建 API Key</div><div class="th5-doc-card__desc">获取调用密钥的完整指南</div></div>
      </a>
      <a href="<?php echo vs_e($vsBase); ?>/articles" class="th5-doc-card reveal reveal-delay-2">
        <span class="th5-doc-card__icon"><i data-lucide="book-open" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-doc-card__title">使用指南</div><div class="th5-doc-card__desc">接入文档与常见问题</div></div>
      </a>
      <a href="<?php echo vs_e($vsBase); ?>/apis" class="th5-doc-card reveal reveal-delay-3">
        <span class="th5-doc-card__icon"><i data-lucide="server" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-doc-card__title">API 文档</div><div class="th5-doc-card__desc">接口参数与调用说明</div></div>
      </a>
      <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="th5-doc-card reveal">
        <span class="th5-doc-card__icon"><i data-lucide="credit-card" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-doc-card__title">计费说明</div><div class="th5-doc-card__desc">积分计费与充值说明</div></div>
      </a>
      <a href="<?php echo vs_e($vsBase); ?>/sponsor" class="th5-doc-card reveal reveal-delay-1">
        <span class="th5-doc-card__icon"><i data-lucide="heart" style="width:19px;height:19px;"></i></span>
        <div><div class="th5-doc-card__title">赞助支持</div><div class="th5-doc-card__desc">支持平台持续发展</div></div>
      </a>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="th5-faq">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-section-head-q reveal">
      <span class="th5-section-head-q__eyebrow">FAQ</span>
      <h2 class="th5-section-head-q__title font-display">常见问题</h2>
      <p class="th5-section-head-q__sub">快速了解平台的核心功能和使用方法。</p>
    </div>
    <div class="th5-faq__list">
      <div class="th5-faq-item reveal">
        <button type="button" class="th5-faq-item__q">平台支持哪些类型的接口？<i data-lucide="plus" style="width:17px;height:17px;"></i></button>
        <div class="th5-faq-item__a"><div class="th5-faq-item__a-inner">平台覆盖天气、IP 归属、OCR 识别、智能翻译、短信、汇率、物流等主流接口，并持续更新。覆盖文本处理、多模态识别等多场景应用。</div></div>
      </div>
      <div class="th5-faq-item reveal">
        <button type="button" class="th5-faq-item__q">如何开始使用？<i data-lucide="plus" style="width:17px;height:17px;"></i></button>
        <div class="th5-faq-item__a"><div class="th5-faq-item__a-inner">新用户注册即可免费体验。注册后进入控制台创建 API Key，选择需要的接口即可调用，我们还提供详细文档和示例代码帮助快速上手。</div></div>
      </div>
      <div class="th5-faq-item reveal">
        <button type="button" class="th5-faq-item__q">支持哪些接入方式？<i data-lucide="plus" style="width:17px;height:17px;"></i></button>
        <div class="th5-faq-item__a"><div class="th5-faq-item__a-inner">支持 RESTful API、常见 SDK 与在线控制台等多种接入方式。您可以通过 API 或控制台快速开始调用。</div></div>
      </div>
      <div class="th5-faq-item reveal">
        <button type="button" class="th5-faq-item__q">费用如何计算？<i data-lucide="plus" style="width:17px;height:17px;"></i></button>
        <div class="th5-faq-item__a"><div class="th5-faq-item__a-inner">采用积分按量计费，用多少扣多少。新用户享有免费体验积分，不同接口价格不同，积分永久有效，详情可查看定价页面。</div></div>
      </div>
      <div class="th5-faq-item reveal">
        <button type="button" class="th5-faq-item__q">如何保证数据安全和隐私？<i data-lucide="plus" style="width:17px;height:17px;"></i></button>
        <div class="th5-faq-item__a"><div class="th5-faq-item__a-inner">平台严格遵循数据安全标准，所有 API 调用通过 HTTPS 加密传输，提供完整的访问日志和审计功能，不会存储您的敏感数据。</div></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="th5-cta-q">
  <div class="max-w-5xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-cta-q__inner reveal">
      <h2 class="th5-cta-q__title font-display">准备好接入下一个 API 了吗？</h2>
      <p class="th5-cta-q__sub">注册即送体验积分，充值积分永久有效，5 分钟完成第一次接入。</p>
      <div class="th5-cta-q__actions">
        <a href="<?php echo vs_e($vsBase); ?>/user/login" class="btn-primary justify-center">
          立即登录
          <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
        </a>
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-ghost justify-center">
          <i data-lucide="book-open" style="width:16px;height:16px;"></i>
          浏览 API 市场
        </a>
      </div>
    </div>
  </div>
</section>

<?php if ($showAnnounce): ?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/js/pages/home-announcement.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
