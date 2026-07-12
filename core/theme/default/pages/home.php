<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

require_once __DIR__ . '/../includes/api-payload.php';
$payload = vs_theme_api_payload();
$apiCount = ApiManager::countApproved();
$catCount = max(1, count($payload['categoryNames']) - 1);

$heroTitleSetting = ThemeManager::themeSetting('hero_title', '');
$heroLeadSetting = ThemeManager::themeSetting('hero_lead', '');
$heroGlitch = $heroTitleSetting !== '' ? strtoupper($heroTitleSetting) : strtoupper(preg_replace('/\s+/', ' ', $siteName));
$heroDesc = $heroLeadSetting !== '' ? $heroLeadSetting : ($siteDesc !== '' ? $siteDesc : '面向开发者的免费接口公共服务。无需密钥，低延迟。直接调用，即刻响应。');
$heroDescHtml = nl2br(vs_e($heroDesc));

$logoSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '', $siteName));
if ($logoSlug === '') {
    $logoSlug = 'miscapi';
}

$homeHeroConfig = array(
    'tagFree'       => '完全免费',
    'tagKey'        => '轻量部署',
    'glitchLine'    => $heroGlitch,
    'startDelayMs'  => 500,
    'glitchPauseMs' => 1500,
    'line2Plain'    => '开发者的',
    'line2Accent'   => '开放 API',
    'line2Rest'     => '接口平台',
);

$announceHtml = '<p>欢迎使用 <strong>' . vs_e($siteName) . '</strong>！</p><p>系统版本 v' . vs_e(VS_VERSION) . ' 已上线，欢迎体验。</p>';
?>
<div class="home-announcement-bundle">
<section class="home-announcement-wrap home-announcement-wrap--ready container mx-auto px-4" id="homeAnnouncementWrap">
    <button type="button" class="home-announcement-bar" id="homeAnnouncementBtn" aria-label="查看公告详情">
        <span class="home-announcement-label">公告</span>
        <span class="home-announcement-marquee"><span class="home-announcement-track is-ready" style="--notice-start:600px;--notice-end:-400px;--notice-duration:24s">欢迎使用 <?php echo vs_e($siteName); ?>，当前版本 v<?php echo vs_e(VS_VERSION); ?> 已上线！</span></span>
        <span class="home-announcement-action">点击查看</span>
    </button>
</section>
<script type="application/json" id="feer-announcement-client-data"><?php echo json_encode(array('home' => array('title' => '网站公告', 'html' => $announceHtml)), JSON_UNESCAPED_UNICODE); ?></script>
<div class="home-announcement-modal" id="homeAnnouncementModal" data-modal-kind="home" aria-hidden="true">
    <div class="home-announcement-modal__mask" data-close-announcement="1"></div>
    <div class="home-announcement-modal__card" role="dialog" aria-modal="true">
        <div class="home-announcement-modal__head"><h3 class="home-announcement-modal__title">网站公告</h3><button type="button" class="home-announcement-modal__close" data-close-announcement="1">关闭</button></div>
        <div class="home-announcement-modal__body markdown-body" data-announcement-body="home"></div>
        <div class="home-announcement-modal__footer"><button type="button" class="home-announcement-btn-ok" data-close-announcement="1">我知道了</button></div>
    </div>
</div>
</div>
<div class="home-page-body-stack">
<main class="main-wrapper container mx-auto px-4">
    <section class="hero-section">
        <div class="flex flex-col lg:flex-row gap-8 lg:gap-12 items-stretch">
            <div class="hero-left-content flex flex-col justify-center">
                <div class="flex flex-wrap gap-3 mb-6">
                    <span class="tag-free px-3 py-1 rounded text-xs font-bold font-mono">完全免费</span>
                    <span class="tag-new px-3 py-1 rounded text-xs font-bold font-mono">无需密钥</span>
                </div>
                <h1 class="font-mono glitch-text mb-4" id="hero-title" data-text="<?php echo vs_e($heroGlitch); ?>"></h1>
                <p class="text-lg sm:text-xl mb-8 max-w-2xl font-light" style="color: var(--text-muted); line-height: 1.8;"><?php echo $heroDescHtml; ?></p>
                <div class="flex flex-wrap gap-4">
                    <button type="button" class="btn-geek" onclick="document.getElementById('apis').scrollIntoView({behavior: 'smooth'})">查看接口</button>
                    <button type="button" class="btn-geek" style="border-color: #52525b; color: #a1a1aa;" onclick="document.getElementById('playground').scrollIntoView({behavior: 'smooth'})">在线测试</button>
                </div>
            </div>
            <div id="hero-terminal-mount" class="w-full lg:flex-shrink-0 lg:self-end" data-logo-text="<?php echo vs_e($logoSlug); ?>"></div>
        </div>
    </section>
    <section id="stats-section" class="py-20 grid grid-cols-2 md:grid-cols-4 gap-8 border-b" style="border-color: var(--border-color);">
        <div class="text-center"><div class="stat-value stat-green"><span class="counter" data-target="<?php echo (int) $apiCount; ?>">0</span><span class="counter-suffix"></span></div><div class="text-xs mt-2 uppercase tracking-widest font-mono" style="color: var(--text-muted)">API 接口</div></div>
        <div class="text-center"><div class="stat-value stat-cyan"><span class="counter" data-target="<?php echo (int) min(99, max(1, $catCount)); ?>">0</span><span class="counter-suffix"></span></div><div class="text-xs mt-2 uppercase tracking-widest font-mono" style="color: var(--text-muted)">接口分类</div></div>
        <div class="text-center"><div class="stat-value stat-green"><span class="counter" data-target="<?php echo (int) preg_replace('/\D/', '', VS_VERSION); ?>">0</span><span class="counter-suffix"></span></div><div class="text-xs mt-2 uppercase tracking-widest font-mono" style="color: var(--text-muted)">系统版本</div></div>
        <div class="text-center"><div class="stat-value stat-cyan"><span class="counter" data-target="99">0</span><span class="counter-suffix">%</span></div><div class="text-xs mt-2 uppercase tracking-widest font-mono" style="color: var(--text-muted)">可用性</div></div>
    </section>
    <section id="apis" class="py-24">
        <h2 class="section-title">接口目录</h2>
        <div class="mb-8 flex flex-col gap-4">
            <div class="relative w-full md:w-1/3">
                <input type="text" id="search-input" class="search-input w-full pl-8 font-mono text-sm" placeholder="搜索接口名称或描述...">
                <div class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none" style="color: var(--text-muted)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></div>
            </div>
            <div class="flex flex-wrap gap-2 font-mono text-xs items-center" id="category-btns">
                <button type="button" class="cat-btn active" onclick="filterAPI('all', this)">全部</button>
                <?php
                $catBtnIndex = 0;
                foreach ($payload['categoryNames'] as $catId => $catName):
                    if ($catId === 'all') {
                        continue;
                    }
                    $hiddenClass = $catBtnIndex >= 10 ? ' cat-btn-hidden' : '';
                    $catBtnIndex++;
                ?>
                <button type="button" class="cat-btn<?php echo $hiddenClass; ?>" onclick="filterAPI('<?php echo vs_e($catId); ?>', this)"><?php echo vs_e($catName); ?></button>
                <?php endforeach; ?>
                <?php if ($catBtnIndex > 10): ?>
                <button type="button" class="cat-btn-more" id="catMoreBtn" onclick="toggleCategoryExpand()">
                    <span>更多分类</span>
                    <svg class="expand-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"></path></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-container" id="api-list"></div>
        <?php if ($apiCount > 8): ?>
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="view-more-link font-mono text-sm"><span>点击查看更多接口</span><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></a>
        <?php endif; ?>
    </section>
    <section id="playground" class="py-24 border-t" style="border-color: var(--border-color);">
        <h2 class="section-title">在线调试终端</h2>
        <div class="playground-grid">
            <div class="api-card p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center font-mono min-w-0 gap-1.5"><span class="flex-shrink-0" style="color: var(--accent-primary)">&gt;</span><span id="playground-config-title-text" class="min-w-0 truncate">请求配置</span></h3>
                <div class="mb-4"><label class="text-xs uppercase tracking-wider block mb-2 font-mono" style="color: var(--text-muted)">选择接口</label>
                    <div id="api-select-trigger" class="search-input api-select-trigger font-mono text-sm" onclick="openSelectModal()"><span id="selected-api-text">请选择接口...</span><svg class="w-4 h-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></div>
                    <input type="hidden" id="api-select" value="">
                </div>
                <div class="mb-4" id="method-selector-container" style="display: none;"><label class="text-xs uppercase tracking-wider block mb-2 font-mono" style="color: var(--text-muted)">请求方式</label><div id="method-selector" class="flex gap-2 flex-wrap"></div></div>
                <div id="params-container" class="params-container"></div>
                <button type="button" class="btn-geek w-full mt-4" onclick="sendRequest()">发送请求</button>
            </div>
            <div class="api-card p-6">
                <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-bold flex items-center font-mono"><span style="color: var(--accent-secondary)">&lt;</span> 响应结果</h3><span id="status-badge" class="text-xs px-2 py-1 rounded font-mono" style="background:var(--code-bg);color:var(--text-muted);">等待中</span></div>
                <div class="response-container"><pre id="response-body" class="response-pre">// 结果将在此处显示...</pre></div>
            </div>
        </div>
    </section>
    <section id="home-partners" class="py-24 border-t" style="border-color: var(--border-color);">
        <div class="partners-section-header"><h2 class="section-title">合作伙伴</h2></div>
        <div class="partners-grid">
            <a class="partner-tile" href="https://gitee.com/xunjinlu/misc-api" target="_blank" rel="noopener noreferrer" title="Gitee">
                <img src="https://gitee.com/static/images/logo_themecolor.png" alt="Gitee" loading="lazy">
                <span class="partner-tile-name">Gitee</span>
            </a>
            <a class="partner-tile" href="https://github.com" target="_blank" rel="noopener noreferrer" title="GitHub">
                <img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png" alt="GitHub" loading="lazy">
                <span class="partner-tile-name">GitHub</span>
            </a>
            <div class="partner-tile partner-tile--static" title="PHP">
                <img src="https://www.php.net/favicon.ico" alt="PHP" loading="lazy">
                <span class="partner-tile-name">PHP</span>
            </div>
        </div>
    </section>
</main>
</div>
<div id="api-modal" class="modal-overlay" onclick="closeSelectModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header"><h3 class="font-bold font-mono">选择接口</h3><button type="button" onclick="closeSelectModal()" style="color: var(--text-muted);border:none;background:transparent;cursor:pointer;"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <div class="modal-body">
            <div class="modal-toolbar"><div class="relative w-full"><input type="text" id="modal-search" class="search-input w-full pl-8 font-mono text-sm" placeholder="搜索接口名称或描述..." oninput="renderModalList()"><div class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none" style="color: var(--text-muted)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></div></div></div>
            <div id="modal-list" class="modal-list-grid"></div>
        </div>
    </div>
</div>
<script>
var apiData = <?php echo json_encode($payload['apiData'], JSON_UNESCAPED_UNICODE); ?>;
var categoryNames = <?php echo json_encode($payload['categoryNames'], JSON_UNESCAPED_UNICODE); ?>;
var API_PROXY_URL = <?php echo json_encode($vsBase . '/api-proxy.php'); ?>;
var statsDisplayMode = 1;
var homeHeroConfig = <?php echo json_encode($homeHeroConfig, JSON_UNESCAPED_UNICODE); ?>;
window.playgroundUserApiKey = null;
window.playgroundKeyContext = <?php echo json_encode(array(
    'loggedIn' => !empty($userLoggedIn),
    'apiKeyCount' => 0,
    'userCenterUrl' => $authUrl,
    'loginUrl' => $vsBase . '/user/login',
), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('default', 'assets/js/pages/home-announcement.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
