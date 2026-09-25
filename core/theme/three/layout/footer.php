<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
if (!function_exists('th3_render_footer_social')) {
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

$showFriendLinks = ThemeManager::themeSettingBool('show_footer_friend_links', false);
$footerLinksDisplay = ThemeManager::themeSettingStr('footer_friend_links_display', 'limit8');
$footerLinksLimit = 8;
if ($footerLinksDisplay === 'all') {
    $footerLinksLimit = 0;
} elseif (preg_match('/^limit(\d+)$/', $footerLinksDisplay, $mFooterLim)) {
    $footerLinksLimit = (int) $mFooterLim[1];
    if ($footerLinksLimit < 1) {
        $footerLinksLimit = 1;
    }
    if ($footerLinksLimit > 10) {
        $footerLinksLimit = 10;
    }
}
$applyUrl = $vsBase . '/applylink';
$linksPageUrl = $vsBase . '/links';
$isApplyPage = (isset($pageKey) && $pageKey === 'applylink');
$isLinksPage = (isset($pageKey) && $pageKey === 'links');

$hasSocial = function_exists('th3_footer_social_items') && count(th3_footer_social_items()) > 0;
$hasFooterQr = ThemeManager::themeSettingBool('show_footer_qr', true)
    && function_exists('vs_footer_enabled_qrs')
    && vs_footer_enabled_qrs() !== array();
$showDock = $hasSocial || $hasFooterQr;
?>
<footer class="site-footer th3-footer" style="border-top: 1px solid var(--border);">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8 th3-footer__inner">
    <div class="th3-footer__brand-block">
      <?php if ($showFriendLinks): ?>
      <div class="th3-footer__links">
        <h4 class="th3-footer__links-title">友情链接</h4>
        <div class="th3-footer__links-list footer-links" id="friendLinks"
             data-vs-footer-links="1"
             data-limit="<?php echo (int) $footerLinksLimit; ?>"
             data-link-class="th3-footer__link"
             data-more-class="th3-footer__link th3-footer__link--more"
             data-links-url="<?php echo vs_e($linksPageUrl); ?>"
             data-on-links="<?php echo $isLinksPage ? '1' : '0'; ?>">
          <?php if ($isApplyPage): ?>
            <a href="<?php echo vs_e($linksPageUrl); ?>" class="th3-footer__link" data-footer-links-anchor="1">友情链接</a>
          <?php else: ?>
            <a href="<?php echo vs_e($applyUrl); ?>" class="th3-footer__link th3-footer__link--apply" data-footer-links-anchor="1">申请友链</a>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="th3-footer__brand">
        <span class="th3-footer__logo<?php echo $hasSiteLogo ? ' th3-footer__logo--img' : ' th3-footer__logo--fallback'; ?>">
          <?php if ($hasSiteLogo && function_exists('vs_theme_site_logo')): ?>
            <?php vs_theme_site_logo('th3-footer__logo-img', 'th3-footer__logo-dot'); ?>
          <?php else: ?>
            <span class="th3-footer__logo-dot" aria-hidden="true"></span>
          <?php endif; ?>
        </span>
        <span class="font-display font-bold text-base th3-footer__name"><?php echo vs_e($navName); ?></span>
      </div>
      <p class="text-sm text-fg-2 th3-footer__desc"><?php echo vs_e($siteDesc !== '' ? $siteDesc : '全网 API 一站式聚合平台，让接口调用变得轻盈、可靠、可观测。'); ?></p>
      <?php endif; ?>

      <?php if ($showDock): ?>
      <div class="th3-footer__dock">
        <?php th3_render_footer_social(); ?>
        <?php if ($hasFooterQr): ?>
        <div class="vs-foot-qr-wrap th3-footer__qr">
          <?php vs_render_footer_qrs(); ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="th3-footer__meta">
      <?php vs_render_footer_custom_bar(); ?>
      <?php if ($showRuntime && $hasRuntime): ?>
      <div class="th3-footer__runtime-wrap">
        <span id="runtime-display" class="th3-footer__runtime font-mono"></span>
      </div>
      <?php endif; ?>
      <div class="th3-footer__bottom">
        <div class="text-xs text-muted th3-footer__copy">
          <?php echo function_exists('vs_copyright_html') ? vs_copyright_html() : ('© ' . $year . ' ' . vs_e($siteName)); ?>
        </div>
        <?php if ($beian['icp_number'] !== ''): ?>
          <a href="<?php echo vs_e($beian['icp_link']); ?>" target="_blank" rel="noopener noreferrer" class="th3-footer__beian th3-footer__beian--icp"><?php echo vs_e($beian['icp_number']); ?></a>
        <?php endif; ?>
        <?php if ($beian['gongan_number'] !== ''): ?>
          <a href="<?php echo vs_e($beian['gongan_link']); ?>" target="_blank" rel="noopener noreferrer" class="th3-footer__beian th3-footer__beian--gongan">
            <img src="<?php echo vs_e(class_exists('SiteMedia') ? SiteMedia::imgUrl('gov.png') : ($vsBase . '/assets/img/gov.png')); ?>" alt="公安备案" width="16" height="16" loading="lazy" decoding="async">
            <span><?php echo vs_e($beian['gongan_number']); ?></span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</footer>
<?php if ($showRuntime && $hasRuntime): ?>
<script>var runtimeStartDate = new Date(<?php echo json_encode($runtimeStart); ?>).getTime();</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('three', 'assets/js/front-runtime.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
<?php
th3_emit_console_brand_script();
?>
</div><!-- /.th3-root -->
