<?php
/**
 * 主题 three · 首页（还原 api主题8）
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

$heroLeadDefault = '天气、IP、物流、翻译、识别、短信、汇率 ——市面可见的接口，全部聚合到一个平台。统一鉴权、积分计费、统一监控，告别在数十家供应商间来回切换。';
$heroLead = trim((string) ThemeManager::themeSettingStr('hero_lead', ''));
if ($heroLead === '') {
    $heroLead = $heroLeadDefault;
}
$heroLeadHtml = nl2br(vs_e($heroLead));

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
$hasAnnounce = count($announceList) > 0;
$announceMarquee = '';
$announceTitle = '公告';
$announceHtml = '';
if (count($announceList) > 0) {
    $first = $announceList[0];
    $announceMarquee = isset($first['preview']) && $first['preview'] !== '' ? $first['preview'] : $first['title'];
    $announceTitle = $first['title'];
    $rawBody = isset($first['body']) ? (string) $first['body'] : '';
    $announceHtml = $rawBody !== '' ? th3_md_render($rawBody) : (isset($first['body_html']) ? (string) $first['body_html'] : $announceHtml);
}
$announcePopupKey = '';
if (count($announcePopup) > 0) {
    $pop = $announcePopup[0];
    $announceTitle = $pop['title'];
    $rawBody = isset($pop['body']) ? (string) $pop['body'] : '';
    $announceHtml = $rawBody !== '' ? th3_md_render($rawBody) : (isset($pop['body_html']) ? (string) $pop['body_html'] : $announceHtml);
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
window.TH3_HOME = {
  previewLimit: <?php echo (int) $homePreviewLimit; ?>,
  apiCount: <?php echo (int) $apiCount; ?>,
  statsFormat: <?php echo json_encode($statsNumFormat, JSON_UNESCAPED_UNICODE); ?>,
  vsBase: <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>
};
</script>
<!-- ============ Hero ============ -->

<p class="vs-seo-fallback-desc"><?php echo vs_e($siteDesc !== '' ? $siteDesc : ($siteName . ' API 聚合平台')); ?></p>

<?php if ($hasAnnounce): ?>
<div class="th3-announce-bundle">
<section class="th3-announce-wrap th3-announce-wrap--pending" id="homeAnnouncementWrap">
  <button type="button" class="th3-announce-bar" id="homeAnnouncementBtn" aria-label="查看公告详情">
    <span class="th3-announce__label">公告</span>
    <span class="th3-announce__marquee"><span class="th3-announce__track"><?php echo vs_e($announceMarquee); ?></span></span>
    <span class="th3-announce__action">点击查看</span>
  </button>
</section>
<script type="application/json" id="feer-announcement-client-data"><?php echo json_encode(array(
    'home' => array(
        'title'     => $announceTitle,
        'html'      => $announceHtml,
        'autopopup' => count($announcePopup) > 0,
        'popup_key' => $announcePopupKey,
    ),
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<div class="th3-announce-modal" id="homeAnnouncementModal" data-modal-kind="home" aria-hidden="true" inert>
  <div class="th3-announce-modal__mask" data-close-announcement="1"></div>
  <div class="th3-announce-modal__card" role="dialog" aria-modal="true" aria-labelledby="th3AnnounceModalTitle">
    <div class="th3-announce-modal__head">
      <h3 class="th3-announce-modal__title" id="th3AnnounceModalTitle"><?php echo vs_e($announceTitle); ?></h3>
      <button type="button" class="th3-announce-modal__close" data-close-announcement="1">关闭</button>
    </div>
    <div class="th3-announce-modal__body markdown-body vs-md-body" data-announcement-body="home"></div>
    <div class="th3-announce-modal__footer">
      <button type="button" class="th3-announce-btn th3-announce-btn--ghost" data-announcement-dismiss="1">不再提示</button>
      <button type="button" class="th3-announce-btn th3-announce-btn--primary" data-close-announcement="1">我知道了</button>
    </div>
  </div>
</div>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
</div>
<?php endif; ?>

<section class="th3-hero-section relative pt-28 sm:pt-32 lg:pt-40 pb-14 sm:pb-20 overflow-hidden<?php echo $hasAnnounce ? ' th3-hero--with-announce' : ''; ?>">
  <div class="hero-bg">
    <div class="hero-grid"></div>
    <div class="hero-blob blob-1"></div>
    <div class="hero-blob blob-2"></div>
    <div class="hero-blob blob-3"></div>
  </div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="grid lg:grid-cols-2 gap-10 lg:gap-8 items-center">
      <div class="hero-copy order-1">
        <div class="reveal inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium max-w-full" style="background: var(--card); border:1px solid var(--border);">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background: var(--accent-2);"></span>
          <span class="text-fg-2 truncate">现已聚合 <?php echo vs_e($apiCountLabel); ?> 个 API 接口</span>
          <i data-lucide="arrow-right" style="width:12px;height:12px;color:var(--muted);flex-shrink:0;"></i>
        </div>
        <h1 class="reveal reveal-delay-1 font-display font-bold mt-6 leading-[0.95] tracking-tight" style="font-size: clamp(2rem, 4.2vw + 0.6rem, 3.25rem);">
          全网接口<span class="gradient-text">一站调用</span>
        </h1>
        <p class="hero-lead reveal reveal-delay-2 mt-5 sm:mt-6 text-base sm:text-lg text-fg-2 leading-relaxed max-w-xl">
          <?php echo $heroLeadHtml; ?>
        </p>
        <div class="hero-actions reveal reveal-delay-3 mt-7 sm:mt-8 flex flex-col sm:flex-row flex-wrap gap-3">
          <a href="#market" class="btn-primary justify-center lg:justify-start">
            浏览 API 市场
            <i data-lucide="arrow-up-right" style="width:16px;height:16px;"></i>
          </a>
          <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-ghost justify-center lg:justify-start">
            <i data-lucide="terminal" style="width:16px;height:16px;"></i>
            浏览全部接口
          </a>
        </div>
        <div class="hero-trust reveal reveal-delay-4 mt-10 flex flex-wrap items-center gap-x-6 gap-y-3">
          <div class="flex items-center gap-2 text-sm text-muted">
            <i data-lucide="shield-check" style="width:16px;height:16px;color:var(--accent-2);"></i>
            <span>积分永久有效</span>
          </div>
          <div class="flex items-center gap-2 text-sm text-muted">
            <i data-lucide="zap" style="width:16px;height:16px;color:var(--accent);"></i>
            <span>5 分钟完成接入</span>
          </div>
          <div class="flex items-center gap-2 text-sm text-muted">
            <i data-lucide="globe" style="width:16px;height:16px;color:var(--accent-3);"></i>
            <span>全球边缘节点</span>
          </div>
        </div>
      </div>

      <div class="reveal reveal-delay-2 relative order-2 mt-2 lg:mt-0 hero-visual">
        <div class="orbit-wrap<?php echo $hasSiteLogo ? ' orbit-wrap--logo' : ''; ?>" aria-hidden="true">
          <div class="orbit-shadow"></div>
          <div class="orbit-stage">
            <div class="orbit-ring r1" id="ring1"></div>
            <div class="orbit-ring r2" id="ring2"></div>
            <div class="orbit-ring r3" id="ring3"></div>
          </div>
          <div class="orbit-core<?php echo $hasSiteLogo ? ' orbit-core--logo' : ''; ?>">
            <?php if ($hasSiteLogo): ?>
              <?php vs_render_site_logo('orbit-core__logo'); ?>
            <?php else: ?>
              <?php echo vs_e($navName); ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="reveal mt-14 sm:mt-20 lg:mt-24">
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-4">
        <span class="text-xs font-mono uppercase tracking-widest text-muted">实时调用流</span>
        <div class="flex-1 h-px hidden sm:block" style="background: var(--border);"></div>
        <span class="text-xs font-mono text-muted">过去 24h · <?php echo vs_e($todayCallsLabel); ?> 次请求</span>
      </div>
      <div class="marquee-viewport" aria-label="实时调用流">
        <div class="marquee" id="callStreamMarquee">
          <div class="api-chip"><i data-lucide="cloud-sun"></i>weather.now · 北京 23°C</div>
          <div class="api-chip s"><i data-lucide="map-pin"></i>ip.locate · 116.40,39.90</div>
          <div class="api-chip t"><i data-lucide="smartphone"></i>phone.attribution · 138****</div>
          <div class="api-chip"><i data-lucide="package"></i>express.track · YT**** 顺丰</div>
          <div class="api-chip s"><i data-lucide="quote"></i>hitokoto · 不期而遇</div>
          <div class="api-chip t"><i data-lucide="languages"></i>translate · zh→en</div>
          <div class="api-chip"><i data-lucide="qr-code"></i>qrcode.generate</div>
          <div class="api-chip s"><i data-lucide="dollar-sign"></i>exchange.rate · USD/CNY</div>
          <div class="api-chip t"><i data-lucide="scan-text"></i>ocr.recognize</div>
          <div class="api-chip"><i data-lucide="link"></i>shorturl.create</div>
          <div class="api-chip s"><i data-lucide="credit-card"></i>idcard.verify</div>
          <div class="api-chip t"><i data-lucide="calendar"></i>almanac.query</div>

          <div class="api-chip"><i data-lucide="cloud-sun"></i>weather.now · 上海 26°C</div>
          <div class="api-chip s"><i data-lucide="map-pin"></i>ip.locate · 121.47,31.23</div>
          <div class="api-chip t"><i data-lucide="smartphone"></i>phone.attribution · 186****</div>
          <div class="api-chip"><i data-lucide="package"></i>express.track · SF****</div>
          <div class="api-chip s"><i data-lucide="quote"></i>hitokoto · 山有木兮</div>
          <div class="api-chip t"><i data-lucide="languages"></i>translate · en→ja</div>
          <div class="api-chip"><i data-lucide="qr-code"></i>qrcode.generate</div>
          <div class="api-chip s"><i data-lucide="dollar-sign"></i>exchange.rate · EUR/CNY</div>
          <div class="api-chip t"><i data-lucide="scan-text"></i>ocr.recognize</div>
          <div class="api-chip"><i data-lucide="link"></i>shorturl.create</div>
          <div class="api-chip s"><i data-lucide="credit-card"></i>idcard.verify</div>
          <div class="api-chip t"><i data-lucide="calendar"></i>almanac.query</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 统计 ============ -->
<section id="stats" class="py-12 sm:py-16 lg:py-20" style="border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8 lg:gap-4">
      <div class="reveal">
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-2">API 数量</div>
        <div class="font-display font-bold stat-num" style="font-size: clamp(2rem, 4vw, 3rem);">
          <span data-count="<?php echo (int) $apiCount; ?>">0</span><span style="color: var(--accent);">+</span>
        </div>
      </div>
      <div class="reveal reveal-delay-1">
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-2">累计调用</div>
        <div class="font-display font-bold stat-num" style="font-size: clamp(2rem, 4vw, 3rem);">
          <span data-count="<?php echo (int) $totalCalls; ?>" data-format="<?php echo vs_e($statsNumFormat); ?>">0</span><span class="stat-suffix" style="color: var(--accent);"></span>
        </div>
      </div>
      <div class="reveal reveal-delay-2">
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-2">服务可用率</div>
        <div class="font-display font-bold stat-num" style="font-size: clamp(2rem, 4vw, 3rem);">
          <span data-count="99.99" data-decimals="2">0</span><span style="color: var(--accent-2);">%</span>
        </div>
      </div>
      <div class="reveal reveal-delay-3">
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-2">平均响应</div>
        <div class="font-display font-bold stat-num" style="font-size: clamp(2rem, 4vw, 3rem);">
          <span data-count="35">0</span><span style="color: var(--accent-3);">ms</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ API 市场 ============ -->
<section id="market" class="py-16 sm:py-20 lg:py-28">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8 sm:mb-10">
      <div class="reveal">
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ API 市场</div>
        <h2 class="font-display font-bold tracking-tight" style="font-size: clamp(1.75rem, 5vw, 3rem);">
          海量接口，<span class="gradient-text-2">按需接入</span>
        </h2>
      </div>
      <div class="reveal reveal-delay-1 w-full lg:w-auto">
        <div class="th3-api-search lg:w-72">
          <span class="th3-api-search__icon" aria-hidden="true"><i data-lucide="search"></i></span>
          <input id="apiSearch" class="input" type="search" enterkeyhint="search" placeholder="搜索 API…" data-ph-tpl="搜索 {n} 个 API…" autocomplete="off">
        </div>
      </div>
    </div>

    
    <div class="reveal cat-scroll" id="th3CatScroll" role="tablist" aria-label="接口分类">
      <button class="cat-tab active" data-cat="all" type="button">全部</button>
      <?php foreach ($catTags as $tag): ?>
        <?php
          $cid = isset($tag['id']) ? (string) $tag['id'] : '';
          $clabel = isset($tag['name']) ? (string) $tag['name'] : '';
          if ($cid === '' || $cid === 'all' || $clabel === '') { continue; }
        ?>
        <button class="cat-tab" data-cat="<?php echo vs_e($cid); ?>" type="button"><?php echo vs_e($clabel); ?></button>
      <?php endforeach; ?>
    </div>

    <div id="apiGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"></div>

    <div class="reveal mt-10 text-center">
      <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-ghost" id="th3ViewAllApis">
        查看全部 <span id="th3ApiTotalLabel"><?php echo (int) $apiCount; ?></span> 个 API
        <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
      </a>
    </div>
  </div>
</section>

<!-- ============ 核心能力 ============ -->
<section id="features" class="py-16 sm:py-20 lg:py-28">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="reveal mb-8 sm:mb-10 max-w-2xl">
      <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 核心能力</div>
      <h2 class="font-display font-bold tracking-tight" style="font-size: clamp(1.75rem, 5vw, 3rem);">
        不仅是聚合，<br>更是<span class="gradient-text">工程化升级</span>
      </h2>
      <p class="mt-3 text-fg-2 text-base sm:text-lg">从鉴权到监控，从限流到容灾，替你处理掉所有繁琐的细节。</p>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
      <div class="reveal feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box"><i data-lucide="key-round"></i></div>
          <h3 class="font-display">统一鉴权</h3>
        </div>
        <p>统一 API Key 鉴权，支持多种传参方式，权限可按接口配置。</p>
      </div>
      <div class="reveal reveal-delay-1 feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box green"><i data-lucide="route"></i></div>
          <h3 class="font-display">智能路由</h3>
        </div>
        <p>自动选择最快、最稳定的供应商。当某家接口异常时，0.5 秒内切换至备用通道，业务无感。</p>
      </div>
      <div class="reveal reveal-delay-2 feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box yellow"><i data-lucide="activity"></i></div>
          <h3 class="font-display">实时监控</h3>
        </div>
        <p>每个请求的延迟、成功率、错因分布全可视化。支持设置告警阈值，异常时即时通知。</p>
      </div>
      <div class="reveal feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box green"><i data-lucide="gauge"></i></div>
          <h3 class="font-display">弹性限流</h3>
        </div>
        <p>按接口、按用户、按 IP 多维度限流。突发流量自动排队，保障核心业务稳定。</p>
      </div>
      <div class="reveal reveal-delay-1 feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box yellow"><i data-lucide="coins"></i></div>
          <h3 class="font-display">积分计费</h3>
        </div>
        <p>充值积分即可调用，用多少扣多少。积分永久有效、不过期，余额随时可查、随时可用。</p>
      </div>
      <div class="reveal reveal-delay-2 feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box"><i data-lucide="server"></i></div>
          <h3 class="font-display">私有部署</h3>
        </div>
        <p>企业版支持全量私有化部署，数据不出内网。提供 SLA 99.99% 服务保障与专属技术支持。</p>
      </div>
    </div>
  </div>
</section>

<!-- ============ 积分充值 ============ -->
<section id="pricing" class="py-16 sm:py-20 lg:py-28 bg-bg-2">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="reveal text-center mb-10 sm:mb-12">
      <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 积分充值</div>
      <h2 class="font-display font-bold tracking-tight" style="font-size: clamp(1.75rem, 5vw, 3rem);">
        积分充值，<span class="gradient-text">用多少扣多少</span>
      </h2>
      <p class="mt-4 text-fg-2 max-w-2xl mx-auto text-sm sm:text-base">无包月、无订阅。一次充值，积分永久有效，随时调用全部 API。</p>
    </div>

    <div class="grid md:grid-cols-3 gap-4 max-w-5xl mx-auto">
      <?php if (count($rechargePackages) > 0): ?>
        <?php foreach (array_slice($rechargePackages, 0, 3) as $i => $pkg): ?>
          <?php
            $isFeatured = ((int) $i === $rechargeFeatured);
            $money = isset($pkg['money']) ? (string) $pkg['money'] : '0';
            $points = isset($pkg['points']) ? (string) $pkg['points'] : '0';
            $pkgName = isset($pkg['name']) ? (string) $pkg['name'] : '套餐';
          ?>
          <div class="reveal<?php echo $i > 0 ? ' reveal-delay-' . min($i, 3) : ''; ?> price-card<?php echo $isFeatured ? ' featured' : ''; ?>">
            <div class="text-sm font-medium<?php echo $isFeatured ? ' opacity-70' : ' text-muted'; ?> mb-1"><?php echo vs_e($pkgName); ?></div>
            <div class="flex items-baseline gap-1 mb-1">
              <span class="font-display font-bold text-4xl">¥<?php echo vs_e($money); ?></span>
            </div>
            <div class="text-xs<?php echo $isFeatured ? ' opacity-70' : ' text-muted'; ?> mb-1">到账 <span style="font-weight: 600;<?php echo $isFeatured ? '' : ' color: var(--accent-2);'; ?>"><?php echo vs_e($points); ?></span> 积分</div>
            <div class="text-xs<?php echo $isFeatured ? ' opacity-70' : ' text-muted'; ?> price-desc">积分永久有效，用多少扣多少</div>
            <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="<?php echo $isFeatured ? 'w-full inline-flex justify-center items-center gap-2 py-3 rounded-full font-medium price-cta' : 'btn-ghost w-full justify-center price-cta'; ?>"<?php echo $isFeatured ? ' style="background: var(--bg); color: var(--fg);"' : ''; ?>>
              立即充值
              <?php if ($isFeatured): ?>
                <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
              <?php endif; ?>
            </a>
            <ul class="text-sm">
              <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent<?php echo $isFeatured ? '' : '-2'; ?>);"></i>积分永久有效</li>
              <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent<?php echo $isFeatured ? '' : '-2'; ?>);"></i>全部 API 可调用</li>
              <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent<?php echo $isFeatured ? '' : '-2'; ?>);"></i>余额随时可查</li>
            </ul>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="reveal price-card md:col-span-3 text-center">
          <p class="text-muted">充值套餐暂未配置，请前往用户中心充值页查看。</p>
          <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="btn-primary inline-flex mt-4">前往充值</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="py-16 sm:py-20 lg:py-28">
  <div class="max-w-5xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="reveal relative rounded-3xl overflow-hidden p-8 sm:p-10 lg:p-16" style="background: var(--fg); color: var(--bg);">
      <div class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at 20% 30%, var(--accent), transparent 50%), radial-gradient(circle at 80% 70%, var(--accent-2), transparent 50%);"></div>
      <div class="relative">
        <h2 class="font-display font-bold tracking-tight leading-tight" style="font-size: clamp(1.5rem, 5vw, 3rem);">
          准备好接入<br>下一个 API 了吗？
        </h2>
        <p class="mt-4 opacity-70 max-w-md text-sm sm:text-base">注册即送体验积分，充值积分永久有效，5 分钟完成第一次接入。</p>
        <div class="mt-8 flex flex-col sm:flex-row flex-wrap gap-3">
          <a href="<?php echo vs_e($vsBase); ?>/user/login" class="inline-flex items-center justify-center gap-2 py-3 px-6 rounded-full font-medium" style="background: var(--bg); color: var(--fg);">
            立即登录
            <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
          </a>
          <a href="<?php echo vs_e($vsBase); ?>/apis" class="inline-flex items-center justify-center gap-2 py-3 px-6 rounded-full font-medium" style="border:1px solid color-mix(in srgb, var(--bg) 30%, transparent); color: var(--bg);">
            <i data-lucide="book-open" style="width:16px;height:16px;"></i>
            浏览 API 市场
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($hasAnnounce): ?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('three', 'assets/js/pages/home-announcement.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
