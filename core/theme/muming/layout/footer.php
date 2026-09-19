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
          <li><a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a></li>
        </ul>
      </div>

      <!-- 资源 -->
      <div class="th5-footer-q__col">
        <h4 class="th5-footer-q__col-title">资源</h4>
        <ul class="th5-footer-q__col-list">
          <li><a href="<?php echo vs_e($vsBase); ?>/articles">文档与公告</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/links">友情链接</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/about">关于我们</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/sponsor">赞助支持</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/user/login">开发者控制台</a></li>
        </ul>
      </div>

      <!-- 快速开始 -->
      <div class="th5-footer-q__col">
        <h4 class="th5-footer-q__col-title">快速开始</h4>
        <ul class="th5-footer-q__col-list">
          <li><a href="<?php echo vs_e($vsBase); ?>/user/register">注册账号</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/user/login">登录控制台</a></li>
          <li><a href="<?php echo vs_e($vsBase); ?>/apis">浏览接口市场</a></li>
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
</div><!-- /.th5-root -->
