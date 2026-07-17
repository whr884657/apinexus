<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$footDesc = $siteDesc !== '' ? $siteDesc : '为开发者提供稳定、快速的 API 接口服务';
$stNavExpandMode = ThemeManager::themeSettingStr('nav_expand_mode', 'top_drawer');
$stNavUseFab = ($stNavExpandMode === 'fab_popup');
$showRuntime = ThemeManager::themeSettingBool('show_runtime', true);
$hasRuntime = vs_site_has_runtime();
$runtimeStart = vs_site_runtime_start();
$year = date('Y');
$beian = SiteContext::beianInfo();
?>
<footer class="st-foot">
    <div class="st-wrap st-foot__grid">
        <div class="st-foot__brand">
            <div class="st-foot__logo-row">
                <?php vs_theme_site_logo('st-foot__img', 'st-foot__fallback'); ?>
                <strong><?php echo vs_e($siteName); ?></strong>
            </div>
            <p><?php echo vs_e($footDesc); ?></p>
        </div>
        <div class="st-foot__qrs">
            <?php vs_render_footer_qrs('st-foot__qr-list'); ?>
        </div>
    </div>
    <div class="st-wrap st-foot__bottom">
        <?php vs_render_footer_custom_bar(); ?>
        <?php if ($showRuntime && $hasRuntime): ?>
        <div class="st-foot__runtime">
            <span id="runtime-display" class="st-foot__runtime-text"></span>
        </div>
        <?php endif; ?>
        <div class="st-foot__copy">
            <span><?php echo vs_e($siteName); ?> &copy; <?php echo vs_e($year); ?></span>
            <?php if ($beian['icp_number'] !== ''): ?>
                <a href="<?php echo vs_e($beian['icp_link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo vs_e($beian['icp_number']); ?></a>
            <?php endif; ?>
            <?php if ($beian['gongan_number'] !== ''): ?>
                <a href="<?php echo vs_e($beian['gongan_link']); ?>" target="_blank" rel="noopener noreferrer" class="st-foot__gongan">
                    <img src="<?php echo vs_e($vsBase); ?>/assets/img/gov.png" alt="" width="16" height="16">
                    <span><?php echo vs_e($beian['gongan_number']); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php if ($showRuntime && $hasRuntime): ?>
<script>var runtimeStartDate = new Date(<?php echo json_encode($runtimeStart); ?>).getTime();</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/front-runtime.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>"></script>
<?php endif; ?>
<button type="button" class="st-back-top" id="stBackTop" aria-label="返回顶部" hidden>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
<?php if ($stNavUseFab): ?>
<div class="st-nav-mask" id="stNavMask" hidden></div>
<div class="st-nav-fab-wrap" id="stNavFabWrap">
    <nav class="st-nav-pop" id="stNavPop" aria-label="站点菜单" hidden>
        <?php foreach ($navItems as $item): ?>
            <a href="<?php echo vs_e($item['url']); ?>"
               class="st-nav-pop__link<?php echo $activeNav === $item['id'] ? ' is-on' : ''; ?>">
                <?php echo vs_e($item['label']); ?>
            </a>
        <?php endforeach; ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="st-nav-pop__link st-nav-pop__link--auth">
            <?php echo !empty($userLoggedIn) ? '用户中心' : vs_e($authLabel); ?>
        </a>
    </nav>
    <button type="button" class="st-nav-fab" id="stNavFab" aria-label="打开导航菜单" aria-expanded="false" aria-controls="stNavPop">
        <span class="st-nav-fab__lines" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>
</div>
<?php endif; ?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/st-tint.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
</div>
