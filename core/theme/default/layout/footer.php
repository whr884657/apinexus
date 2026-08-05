<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
// 渲染上下文由 ThemeManager::renderBody extract 注入；此处兜底避免静态分析/异常路径未定义
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : rtrim(vs_base_url(), '/');
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$year = date('Y');
$beian = SiteContext::beianInfo();
$showRuntime = ThemeManager::themeSettingBool('show_runtime', true);
$hasRuntime = vs_site_has_runtime();
$runtimeStart = vs_site_runtime_start();
$showFriendLinks = Config::get('home_footer_links', '1') !== '0';
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
$footerLinksPick = ($showFriendLinks && class_exists('FrontendLink'))
    ? FrontendLink::pickForFooter($footerLinksLimit)
    : array('items' => array(), 'has_more' => false, 'total' => 0, 'limit' => $footerLinksLimit);
$footerLinks = isset($footerLinksPick['items']) && is_array($footerLinksPick['items'])
    ? $footerLinksPick['items']
    : array();
$footerLinksHasMore = !empty($footerLinksPick['has_more']);
$applyUrl = $vsBase . '/applylink';
$linksPageUrl = $vsBase . '/links';
$isApplyPage = (isset($pageKey) && $pageKey === 'applylink');
$isLinksPage = (isset($pageKey) && $pageKey === 'links');
?>
<footer class="mt-12 feer-footer">
    <div class="container mx-auto px-6">
        <?php if ($showFriendLinks): ?>
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
                        <?php if ($footerLinksHasMore && !$isLinksPage): ?>
                            <a href="<?php echo vs_e($linksPageUrl); ?>" class="footer-link-item footer-link-item--more">查看更多</a>
                        <?php endif; ?>
                        <?php if ($isApplyPage): ?>
                            <a href="<?php echo vs_e($linksPageUrl); ?>" class="footer-link-item">友情链接</a>
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
        <?php else: ?>
        <div class="py-6 border-b" style="border-color: var(--border-color);">
            <div class="vs-foot-qr-wrap" style="justify-content:center;">
                <?php vs_render_footer_qrs(); ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="py-6 flex flex-col gap-4 text-xs" style="color: var(--text-muted);">
            <?php vs_render_footer_custom_bar(); ?>
            <?php if ($showRuntime && $hasRuntime): ?>
            <div style="width: 100%; display: flex; justify-content: center;">
                <span id="runtime-display" class="runtime-text font-mono"></span>
            </div>
            <?php endif; ?>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex flex-col md:flex-row items-center gap-4 text-center md:text-left">
                    <span><?php echo function_exists('vs_copyright_html') ? vs_copyright_html() : (vs_e($siteName) . ' &copy; ' . vs_e($year)); ?></span>
                </div>
                <div class="flex flex-col md:flex-row items-center gap-4 text-center md:text-right">
                    <?php if ($beian['icp_number'] !== ''): ?>
                        <a href="<?php echo vs_e($beian['icp_link']); ?>" target="_blank" rel="noopener noreferrer" class="beian-link"><?php echo vs_e($beian['icp_number']); ?></a>
                    <?php endif; ?>
                    <?php if ($beian['gongan_number'] !== ''): ?>
                        <a href="<?php echo vs_e($beian['gongan_link']); ?>" target="_blank" rel="noopener noreferrer" class="beian-link" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <img src="<?php echo vs_e(class_exists('SiteMedia') ? SiteMedia::imgUrl('gov.png') : ($vsBase . '/assets/img/gov.png')); ?>" alt="公安备案" width="16" height="16" loading="lazy" decoding="async" style="width: 16px; height: 16px; display: inline-block;">
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
<?php
// 壳 toast/common 已由 vs_frontend_page 逐文件加载，页脚不再重复拉取
?>
<?php endif; ?>
<?php if ($showRuntime && $hasRuntime): ?>
<script>var runtimeStartDate = new Date(<?php echo json_encode($runtimeStart); ?>).getTime();</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('default', 'assets/js/front-runtime.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
