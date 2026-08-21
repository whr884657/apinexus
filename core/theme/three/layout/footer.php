<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$siteDesc = isset($siteDesc) ? (string) $siteDesc : SiteContext::siteDescription();
?>
<footer class="site-footer" style="border-top: 1px solid var(--border);">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="flex items-center gap-2.5 footer-brand">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--fg);">
        <div class="w-2 h-2 rounded-full" style="background: var(--accent);"></div>
      </div>
      <span class="font-display font-bold text-base"><?php echo vs_e($siteName); ?></span>
    </div>
    <p class="text-sm text-fg-2 max-w-md footer-desc"><?php echo vs_e($siteDesc !== '' ? $siteDesc : '全网 API 一站式聚合平台，让接口调用变得轻盈、可靠、可观测。'); ?></p>
    <div class="flex footer-social">
      <a href="#" class="w-8 h-8 rounded-lg flex items-center justify-center" style="border:1px solid var(--border);" aria-label="GitHub"><i data-lucide="github" style="width:15px;height:15px;"></i></a>
      <a href="#" class="w-8 h-8 rounded-lg flex items-center justify-center" style="border:1px solid var(--border);" aria-label="Twitter"><i data-lucide="twitter" style="width:15px;height:15px;"></i></a>
      <a href="#" class="w-8 h-8 rounded-lg flex items-center justify-center" style="border:1px solid var(--border);" aria-label="联系"><i data-lucide="message-circle" style="width:15px;height:15px;"></i></a>
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-fg-2 footer-links" style="margin: 1.5rem 0;">
      <a href="<?php echo vs_e($vsBase); ?>/about">关于</a>
      <a href="<?php echo vs_e($vsBase); ?>/articles">文章</a>
      <a href="<?php echo vs_e($vsBase); ?>/links">友情链接</a>
      <a href="<?php echo vs_e($vsBase); ?>/sponsor">赞助</a>
      <a href="<?php echo vs_e($vsBase); ?>/contributors">贡献者</a>
      <a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a>
    </div>
    <div class="footer-bottom">
      <div class="text-xs text-muted">© <?php echo (int) date('Y'); ?> <?php echo vs_e($siteName); ?></div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2 ticker">
          <span class="pulse"></span>
          <span>服务正常</span>
        </div>
        <span class="text-xs text-muted font-mono">uptime 99.992%</span>
      </div>
    </div>
  </div>
</footer>
</div><!-- /.th3-root -->
