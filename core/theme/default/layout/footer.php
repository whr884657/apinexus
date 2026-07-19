<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$year = date('Y');
$beian = SiteContext::beianInfo();
$showRuntime = ThemeManager::themeSettingBool('show_runtime', true);
$hasRuntime = vs_site_has_runtime();
$runtimeStart = vs_site_runtime_start();
$footerLinks = class_exists('FrontendLink') ? FrontendLink::listForTheme() : array();
$applyUrl = rtrim($vsBase, '/') . '/applylink';
$isApplyPage = (isset($pageKey) && $pageKey === 'applylink');
?>
<footer class="mt-12 feer-footer">
    <div class="container mx-auto px-6">
        <div class="py-8 border-b" style="border-color: var(--border-color);">
            <div class="flex flex-col md:flex-row gap-6 md:items-start md:justify-between">
                <div class="flex-1" style="min-width: 0;">
                    <h4 class="font-bold text-sm mb-4 font-mono" style="color: var(--accent-primary);">// 友情链接</h4>
                    <div class="flex flex-wrap gap-3 footer-links text-sm" id="friendLinks">
                        <?php foreach ($footerLinks as $item): ?>
                            <a href="<?php echo vs_e($item['siteurl']); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="footer-link-item"
                               data-friend-link="1"><?php echo vs_e($item['name']); ?></a>
                        <?php endforeach; ?>
                        <?php if ($isApplyPage): ?>
                            <a href="<?php echo vs_e(rtrim($vsBase, '/')); ?>/links" class="footer-link-item">友情链接</a>
                        <?php else: ?>
                            <a href="<?php echo vs_e($applyUrl); ?>" class="footer-link-item footer-link-item--apply">申请友链</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="vs-foot-qr-wrap">
                    <?php vs_render_footer_qrs(); ?>
                </div>
            </div>
        </div>
        <div class="py-6 flex flex-col gap-4 text-xs" style="color: var(--text-muted);">
            <?php vs_render_footer_custom_bar(); ?>
            <?php if ($showRuntime && $hasRuntime): ?>
            <div style="width: 100%; display: flex; justify-content: center;">
                <span id="runtime-display" class="runtime-text font-mono"></span>
            </div>
            <?php endif; ?>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex flex-col md:flex-row items-center gap-4 text-center md:text-left">
                    <span><?php echo vs_e($siteName); ?> &copy; <?php echo vs_e($year); ?></span>
                </div>
                <div class="flex flex-col md:flex-row items-center gap-4 text-center md:text-right">
                    <?php if ($beian['icp_number'] !== ''): ?>
                        <a href="<?php echo vs_e($beian['icp_link']); ?>" target="_blank" rel="noopener noreferrer" class="beian-link"><?php echo vs_e($beian['icp_number']); ?></a>
                    <?php endif; ?>
                    <?php if ($beian['gongan_number'] !== ''): ?>
                        <a href="<?php echo vs_e($beian['gongan_link']); ?>" target="_blank" rel="noopener noreferrer" class="beian-link" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <img src="<?php echo vs_e($vsBase); ?>/assets/img/gov.png" alt="" style="width: 16px; height: 16px; display: inline-block;">
                            <?php echo vs_e($beian['gongan_number']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</footer>
<script>var SYSTEM_VERSION = <?php echo json_encode(VS_VERSION); ?>;</script>
<?php if (!isset($GLOBALS['vs_front_csrf_injected'])): $GLOBALS['vs_front_csrf_injected'] = true; ?>
<script>
window.VS_BASE_URL = <?php echo json_encode(rtrim($vsBase, '/')); ?>;
window.VS_CSRF_TOKEN = window.VS_CSRF_TOKEN || <?php echo json_encode(AuthSecurity::csrfToken()); ?>;
window.VS_PLAY_URL = window.VS_PLAY_URL || <?php echo json_encode(rtrim($vsBase, '/') . '/core/playground/relay.php'); ?>;
</script>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/assets/css/toast.css?v=<?php echo vs_e(VS_VERSION); ?>">
<script src="<?php echo vs_e($vsBase); ?>/assets/js/common.js?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
<?php if ($showRuntime && $hasRuntime): ?>
<script>var runtimeStartDate = new Date(<?php echo json_encode($runtimeStart); ?>).getTime();</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('default', 'assets/js/front-runtime.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>"></script>
<?php endif; ?>
