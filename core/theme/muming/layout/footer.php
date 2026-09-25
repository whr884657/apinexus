<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
if (!function_exists('TH5_render_footer_social')) {
    require_once dirname(__DIR__) . '/lib/bootstrap.php';
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$navName = isset($navName) ? (string) $navName : $siteName;
$siteDesc = isset($siteDesc) ? (string) $siteDesc : SiteContext::siteDescription();
$siteLogo = class_exists('SiteContext') ? trim(SiteContext::siteLogo()) : '';
$hasSiteLogo = $siteLogo !== '';
$year = (int) date('Y');
$beian = SiteContext::beianInfo();
$showRuntime = ThemeManager::themeSettingBool('show_runtime', true);
$hasRuntime = vs_site_has_runtime();
$runtimeStart = vs_site_runtime_start();
$navItems = isset($navItems) && is_array($navItems) ? $navItems : ThemeManager::navItems();
?>
<footer class="site-footer th5-footer-q">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="th5-footer-q__grid">

      <!-- 品牌 + 社交 -->
      <div class="th5-footer-q__brand">
        <div class="th5-footer-q__brand-name">
          <span class="th5-footer-q__logo<?php echo $hasSiteLogo ? ' th5-footer-q__logo--img' : ''; ?>">
            <?php if ($hasSiteLogo && function_exists('vs_theme_site_logo')): ?>
              <?php vs_theme_site_logo('th5-footer-q__logo-img', 'th5-footer-q__logo-dot'); ?>
            <?php else: ?>
              <i data-lucide="zap" style="width:16px;height:16px;"></i>
            <?php endif; ?>
          </span>
          <span class="font-display font-bold"><?php echo vs_e($navName); ?></span>
        </div>
        <p class="th5-footer-q__desc"><?php echo vs_e($siteDesc !== '' ? $siteDesc : '全网 API 一站式聚合平台，让接口调用变得轻盈、可靠、可观测。'); ?></p>
        <?php TH5_render_footer_social(); ?>
      </div>

      <!-- 导航 -->
      <div class="th5-footer-q__col">
        <h4 class="th5-footer-q__col-title">导航</h4>
        <ul class="th5-footer-q__col-list">
          <?php foreach ($navItems as $item): ?>
            <?php
              $itemUrl = isset($item['url']) ? (string) $item['url'] : '#';
              $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
              if ($itemLabel === '') { continue; }
            ?>
            <li><a href="<?php echo vs_e($itemUrl); ?>"><?php echo vs_e($itemLabel); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- 资源（与主导航显隐一致；关闭入口不删 URL，SEO 不受影响） -->
      <div class="th5-footer-q__col">
        <h4 class="th5-footer-q__col-title">资源</h4>
        <ul class="th5-footer-q__col-list">
          <?php if (ThemeManager::themeSettingBool('nav_show_articles', true)): ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/articles">文档与公告</a></li>
          <?php endif; ?>
          <?php if (ThemeManager::themeSettingBool('nav_show_links', true)): ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/links">友情链接</a></li>
          <?php endif; ?>
          <?php if (ThemeManager::themeSettingBool('nav_show_about', true)): ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/about">关于我们</a></li>
          <?php endif; ?>
          <?php if (ThemeManager::themeSettingBool('nav_show_sponsor', true)): ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/sponsor">赞助支持</a></li>
          <?php endif; ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/user/login">开发者控制台</a></li>
        </ul>
      </div>

      <!-- 快速开始 -->
      <div class="th5-footer-q__col">
        <h4 class="th5-footer-q__col-title">快速开始</h4>
        <ul class="th5-footer-q__col-list">
          <li><a href="<?php echo vs_e($vsBase); ?>/user/register">注册账号</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/user/login">登录控制台</a></li>
          <?php if (ThemeManager::themeSettingBool('nav_show_apis', true)): ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/apis">浏览接口市场</a></li>
          <?php endif; ?>
          <li><a href="<?php echo vs_e($vsBase); ?>/user/recharge">积分充值</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/user/keys">创建 API Key</a></li>
        </ul>
      </div>
    </div>

    <div class="th5-footer-q__bottom">
      <div class="th5-footer-q__copy">
        <?php echo function_exists('vs_copyright_html') ? vs_copyright_html() : ('© ' . $year . ' ' . vs_e($siteName)); ?>
        <?php if ($showRuntime && $hasRuntime): ?>
          <span id="runtime-display" class="th5-footer__runtime-wrap" style="display:inline-flex;align-items:center;gap:4px;"></span>
        <?php endif; ?>
      </div>
      <div class="th5-footer-q__beian" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <?php if ($beian['icp_number'] !== ''): ?>
          <a href="<?php echo vs_e($beian['icp_link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo vs_e($beian['icp_number']); ?></a>
        <?php endif; ?>
        <?php if ($beian['gongan_number'] !== ''): ?>
          <a href="<?php echo vs_e($beian['gongan_link']); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:5px;">
            <img src="<?php echo vs_e(class_exists('SiteMedia') ? SiteMedia::imgUrl('gov.png') : ($vsBase . '/assets/img/gov.png')); ?>" alt="公安备案" width="14" height="14" loading="lazy" decoding="async">
            <span><?php echo vs_e($beian['gongan_number']); ?></span>
          </a>
        <?php endif; ?>
        <?php vs_render_footer_custom_bar(); ?>
      </div>
    </div>
  </div>
</footer>
<?php if ($showRuntime && $hasRuntime): ?>
<script>var runtimeStartDate = new Date(<?php echo json_encode($runtimeStart); ?>).getTime();</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/js/front-runtime.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
<?php
TH5_emit_console_brand_script();
?>
<!-- 看板娘助手：二次元形象 + 互动气泡 + 随机推荐接口（后台「功能开关」可关闭） -->
<?php if (ThemeManager::themeSettingBool('mascot_enabled', true)): ?>
<div class="th5-mascot" id="th5Mascot" data-th5-mascot
     data-vs-base="<?php echo vs_e($vsBase); ?>"
     data-idle="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/img/mascot/mascot-idle.png')); ?>"
     data-happy="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/img/mascot/mascot-happy.png')); ?>"
     data-site-name="<?php echo vs_e($siteName); ?>">
  <div class="th5-mascot__bubble" role="status" aria-live="polite">
    <span class="th5-mascot__bubble-text"></span>
    <a class="th5-mascot__bubble-link" target="_blank" rel="noopener noreferrer" hidden>去看看</a>
  </div>
  <div class="th5-mascot__stage">
    <img class="th5-mascot__img" src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/img/mascot/mascot-idle.png')); ?>" alt="看板娘小慕" width="217" height="442" loading="lazy" decoding="async">
    <div class="th5-mascot__actions">
      <button type="button" class="th5-mascot__btn" data-th5-mascot-recommend>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M12 3l2.2 6.8L21 12l-6.8 2.2L12 21l-2.2-6.8L3 12l6.8-2.2z"/></svg>
        <span>推荐接口</span>
      </button>
      <button type="button" class="th5-mascot__close" data-th5-mascot-close aria-label="最小化看板娘">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
  </div>
</div>
<button type="button" class="th5-mascot__recall" data-th5-mascot-recall aria-label="召唤看板娘小慕" title="召唤看板娘">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1.2" fill="currentColor" stroke="none"/><path d="M8.6 14.2a3.5 3.5 0 0 0 6.8 0"/></svg>
</button>
<script src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/js/mascot.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
</div><!-- /.th5-root -->
