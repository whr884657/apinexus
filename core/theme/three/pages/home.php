<?php
/**
 * 主题 three · 首页（还原 api主题8）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$siteName = SiteContext::siteName();
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

<section class="relative pt-28 sm:pt-32 lg:pt-40 pb-14 sm:pb-20 overflow-hidden">
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
          天气、IP、物流、翻译、识别、短信、汇率 ——市面可见的接口，全部聚合到一个平台。统一鉴权、积分计费、统一监控，告别在数十家供应商间来回切换。
        </p>
        <div class="hero-actions reveal reveal-delay-3 mt-7 sm:mt-8 flex flex-col sm:flex-row flex-wrap gap-3">
          <a href="#market" class="btn-primary justify-center lg:justify-start">
            浏览 API 市场
            <i data-lucide="arrow-up-right" style="width:16px;height:16px;"></i>
          </a>
          <a href="#playground" class="btn-ghost justify-center lg:justify-start">
            <i data-lucide="terminal" style="width:16px;height:16px;"></i>
            立即试调用
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
        <div class="orbit-wrap" aria-hidden="true">
          <div class="orbit-shadow"></div>
          <div class="orbit-stage">
            <div class="orbit-ring r1" id="ring1"></div>
            <div class="orbit-ring r2" id="ring2"></div>
            <div class="orbit-ring r3" id="ring3"></div>
          </div>
          <div class="orbit-core"><?php echo vs_e($siteName); ?></div>
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
      <div class="reveal reveal-delay-1 flex items-center gap-3 w-full lg:w-auto">
        <div class="relative flex-1 lg:w-72 min-w-0">
          <i data-lucide="search" style="width:16px;height:16px;position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);"></i>
          <input id="apiSearch" class="input pl-10" type="search" enterkeyhint="search" placeholder="搜索 API…" data-ph-tpl="搜索 {n} 个 API…" autocomplete="off">
        </div>
        <button class="btn-ghost shrink-0" style="padding:12px;" type="button" aria-label="筛选">
          <i data-lucide="sliders-horizontal" style="width:16px;height:16px;"></i>
        </button>
      </div>
    </div>

    
    <div class="reveal cat-scroll -mx-4 px-4 sm:-mx-5 sm:px-5 lg:mx-0 lg:px-0" id="th3CatScroll" role="tablist" aria-label="接口分类">
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

<!-- ============ 实时演示 ============ -->
<section id="playground" class="py-16 sm:py-20 lg:py-28 bg-bg-2">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8" style="width:100%;max-width:80rem;">
    <div class="reveal text-center mb-10 sm:mb-12">
      <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 在线演示</div>
      <h2 class="font-display font-bold tracking-tight" style="font-size: clamp(1.75rem, 5vw, 3rem);">
        先看效果，<span class="gradient-text">再决定接入</span>
      </h2>
      <p class="mt-4 text-fg-2 max-w-2xl mx-auto text-sm sm:text-base px-1">在网页内即时试调接口：选中 API、填入参数、查看 JSON 响应。部分接口可直接体验，需密钥的请先注册获取后再调用。</p>
    </div>

    <div class="reveal reveal-delay-1 playground-grid grid lg:grid-cols-12 gap-4 lg:gap-6 w-full">
      <div class="lg:col-span-3 demo-panel min-w-0">
        <div class="card rounded-2xl p-3 w-full h-full demo-select-card">
          <div class="text-xs font-mono uppercase tracking-widest text-muted px-2 sm:px-3 py-2">选择 API</div>
          <div class="demo-api-search-wrap">
            <span class="demo-search-icon" aria-hidden="true"><i data-lucide="search"></i></span>
            <input id="demoApiSearch" class="input" type="search" enterkeyhint="search" placeholder="搜索接口名称 / 路径..." autocomplete="off">
          </div>
          <div id="demoApiList" class="demo-api-scroll" role="listbox" aria-label="演示 API 列表"></div>
        </div>
      </div>

      <div class="lg:col-span-4 demo-panel min-w-0">
        <div class="card rounded-2xl p-4 sm:p-6 h-full w-full">
          <div class="flex items-center gap-3 mb-4 sm:mb-5 min-w-0">
            <div id="demoIconBox" class="api-icon-box shrink-0"><i data-lucide="cloud-sun"></i></div>
            <div class="min-w-0 flex-1 overflow-hidden">
              <div id="demoName" class="font-display font-semibold text-base sm:text-lg truncate">天气查询</div>
              <div id="demoPath" class="font-mono text-[11px] sm:text-xs text-muted truncate">GET /v1/weather/now</div>
            </div>
          </div>
          <p id="demoDesc" class="text-sm text-fg-2 mb-4 sm:mb-5 leading-relaxed break-words">查询指定城市的实时天气状况。</p>
          <div id="demoForm" class="space-y-3 mb-4 sm:mb-5 w-full min-w-0"></div>
          <button id="demoRun" class="btn-primary w-full justify-center" type="button">
            <i data-lucide="play" style="width:14px;height:14px;flex-shrink:0;"></i>
            <span>发起调用</span>
          </button>
          <div id="demoLoading" class="hidden mt-3">
            <div class="loading-bar"></div>
            <div class="text-xs text-muted mt-2 font-mono">请求中...</div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-5 demo-panel min-w-0">
        <div class="card rounded-2xl overflow-hidden h-full flex flex-col w-full">
          <div class="flex items-center justify-between gap-2 px-3 sm:px-5 py-3" style="border-bottom: 1px solid var(--border);">
            <div class="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
              <div class="flex gap-1.5 shrink-0">
                <div class="w-2.5 h-2.5 rounded-full" style="background:#FF5F57;"></div>
                <div class="w-2.5 h-2.5 rounded-full" style="background:#FEBC2E;"></div>
                <div class="w-2.5 h-2.5 rounded-full" style="background:#28C840;"></div>
              </div>
              <span class="font-mono text-xs text-muted truncate">response.json</span>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
              <span id="demoStatus" class="tag">待调用</span>
              <span id="demoLatency" class="font-mono text-xs text-muted">—</span>
            </div>
          </div>
          <div class="flex-1 p-3 sm:p-5 demo-response-pane">
            <pre id="demoResponse" class="json-block json-comment">// 选择接口并填写参数后，点击「发起调用」查看结果</pre>
          </div>
        </div>
      </div>
    </div>

    <div class="reveal mt-6 w-full min-w-0">
      <div class="card rounded-2xl overflow-hidden w-full">
        <div class="flex flex-col gap-3 px-3 sm:px-5 py-3" style="border-bottom: 1px solid var(--border);">
          <div class="flex items-center gap-2">
            <i data-lucide="code-2" style="width:16px;height:16px;color:var(--accent);flex-shrink:0;"></i>
            <span class="text-sm font-medium">代码示例</span>
          </div>
          <div class="code-tabs w-full">
            <button class="code-tab active" data-lang="curl" type="button">cURL</button>
            <button class="code-tab" data-lang="js" type="button">JavaScript</button>
            <button class="code-tab" data-lang="python" type="button">Python</button>
            <button class="code-tab" data-lang="go" type="button">Go</button>
          </div>
        </div>
        <div class="p-3 sm:p-5 demo-code-pane">
          <pre id="codeBlock" class="json-block"></pre>
        </div>
      </div>
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
      <p class="mt-3 text-fg-2 text-base sm:text-lg">从鉴权到监控，从限流到容灾，ApiNexus 替你处理掉所有繁琐的细节。</p>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
      <div class="reveal feature-card">
        <div class="feature-card-head">
          <div class="api-icon-box"><i data-lucide="key-round"></i></div>
          <h3 class="font-display">统一鉴权</h3>
        </div>
        <p>一个 API Key 调用全部接口。支持 HMAC 签名、OAuth2、JWT 多种认证方式，权限粒度到单个接口。</p>
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
      <div class="reveal price-card">
        <div class="text-sm font-medium text-muted mb-1">体验包</div>
        <div class="flex items-baseline gap-1 mb-1">
          <span class="font-display font-bold text-4xl">¥10</span>
        </div>
        <div class="text-xs text-muted mb-1">到账 <span style="color: var(--accent-2); font-weight: 600;">1,000</span> 积分</div>
        <div class="text-xs text-muted price-desc">适合个人尝鲜与调试</div>
        <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="btn-ghost w-full justify-center price-cta">立即充值</a>
        <ul class="text-sm">
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-2);"></i>积分永久有效</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-2);"></i>全部 API 可调用</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-2);"></i>用多少扣多少</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-2);"></i>余额随时可查</li>
        </ul>
      </div>

      <div class="reveal reveal-delay-1 price-card featured">
        <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-xs font-medium" style="background: var(--accent); color: white;">最受欢迎</div>
        <div class="text-sm font-medium opacity-70 mb-1">常用包</div>
        <div class="flex items-baseline gap-1 mb-1">
          <span class="font-display font-bold text-4xl">¥50</span>
        </div>
        <div class="text-xs opacity-70 mb-1">到账 <span style="font-weight: 600;">6,000</span> 积分 · 多送 1,000</div>
        <div class="text-xs opacity-70 price-desc">适合日常开发与小规模上线</div>
        <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="w-full inline-flex justify-center items-center gap-2 py-3 rounded-full font-medium price-cta" style="background: var(--bg); color: var(--fg);">
          立即充值
          <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
        </a>
        <ul class="text-sm">
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent);"></i>积分永久有效</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent);"></i>全部 API 可调用</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent);"></i>充值越多越划算</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent);"></i>调用明细可追溯</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent);"></i>工单优先响应</li>
        </ul>
      </div>

      <div class="reveal reveal-delay-2 price-card">
        <div class="text-sm font-medium text-muted mb-1">畅享包</div>
        <div class="flex items-baseline gap-1 mb-1">
          <span class="font-display font-bold text-4xl">¥200</span>
        </div>
        <div class="text-xs text-muted mb-1">到账 <span style="color: var(--accent-3); font-weight: 600;">28,000</span> 积分 · 多送 8,000</div>
        <div class="text-xs text-muted price-desc">适合高频调用与团队使用</div>
        <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="btn-ghost w-full justify-center price-cta">立即充值</a>
        <ul class="text-sm">
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-3);"></i>积分永久有效</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-3);"></i>全部 API 可调用</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-3);"></i>大额加赠更优惠</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-3);"></i>专属技术支持</li>
          <li class="flex items-center gap-2"><i data-lucide="check" style="width:16px;height:16px;color:var(--accent-3);"></i>可开具发票</li>
        </ul>
      </div>
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
          <a href="#" class="inline-flex items-center justify-center gap-2 py-3 px-6 rounded-full font-medium" style="background: var(--bg); color: var(--fg);">
            创建免费账户
            <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
          </a>
          <a href="#" class="inline-flex items-center justify-center gap-2 py-3 px-6 rounded-full font-medium" style="border:1px solid color-mix(in srgb, var(--bg) 30%, transparent); color: var(--bg);">
            <i data-lucide="book-open" style="width:16px;height:16px;"></i>
            阅读文档
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
