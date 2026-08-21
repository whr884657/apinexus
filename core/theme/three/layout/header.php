<?php
/**
 * 主题 three · 前台顶栏（首页导航）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$navName = isset($navName) ? (string) $navName : $siteName;
$authUrl = isset($authUrl) ? (string) $authUrl : ($vsBase . '/user/login');
$authLabel = isset($authLabel) ? (string) $authLabel : '登录';
$userLoggedIn = !empty($userLoggedIn);
if (!empty($pageSeo) && is_array($pageSeo) && function_exists('vs_render_theme_seo_block')) {
    vs_render_theme_seo_block($pageSeo);
}
$pageKeySafe = isset($pageKey) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $pageKey) : '';
$homeHash = ($pageKeySafe === 'home') ? '' : ($vsBase . '/');
?>
<div class="th3-root" id="th3Root">
<header id="nav" class="site-header fixed top-0 left-0 right-0 z-50 glass" style="border-bottom: 1px solid transparent;">
  <div class="nav-inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8 flex items-center justify-between">
    <a href="<?php echo vs_e($vsBase); ?>/" class="flex items-center gap-2.5 min-w-0">
      <span class="th3-brand-logo shrink-0 w-9 h-9 rounded-xl overflow-hidden flex items-center justify-center" style="background: var(--fg);">
        <?php if (function_exists('vs_theme_site_logo')) { ob_start(); vs_theme_site_logo('th3-brand-img', 'th3-brand-fallback'); $__logo = trim(ob_get_clean()); } else { $__logo = ''; }
        if ($__logo !== '' && strpos($__logo, '<img') !== false) { echo $__logo; } else { ?>
        <span class="w-2 h-2 rounded-full" style="background: var(--accent);"></span>
        <?php } ?>
      </span>
      <span class="font-display font-bold text-lg tracking-tight" style="color: var(--fg);"><?php echo vs_e($navName); ?></span>
    </a>

    <nav class="hidden lg:flex items-center gap-8">
      <a href="<?php echo vs_e($vsBase); ?>/apis" class="nav-link">API 市场</a>
      <a href="<?php echo vs_e($homeHash); ?>#playground" class="nav-link">在线演示</a>
      <a href="<?php echo vs_e($homeHash); ?>#features" class="nav-link">核心能力</a>
      <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="nav-link">积分充值</a>
      <a href="<?php echo vs_e($vsBase); ?>/articles" class="nav-link">文章</a>
    </nav>

    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <div class="hidden md:flex items-center gap-2 ticker">
        <span class="pulse"></span>
        <span>服务正常</span>
      </div>
      <button id="themeToggle" class="theme-toggle" aria-label="切换主题" type="button">
        <div class="knob"><i data-lucide="sun"></i></div>
      </button>
      <a href="<?php echo vs_e($authUrl); ?>" class="hidden sm:inline-flex btn-ghost text-sm" style="padding: 8px 18px;"><?php echo vs_e($authLabel); ?></a>
      <a href="<?php echo vs_e($userLoggedIn ? ($vsBase . '/user/') : ($vsBase . '/user/register')); ?>" class="hidden sm:inline-flex btn-primary text-sm" style="padding: 8px 18px;">
        <?php echo $userLoggedIn ? '用户中心' : '免费开始'; ?>
        <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
      </a>
      <button id="menuToggle" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center" style="border:1px solid var(--border);" type="button" aria-label="打开菜单" aria-expanded="false" aria-controls="mobileMenu">
        <i data-lucide="menu" style="width:18px;height:18px;color: var(--fg);"></i>
      </button>
    </div>
  </div>

  <div id="menuBackdrop" class="menu-backdrop lg:hidden" aria-hidden="true"></div>
  <div id="mobileMenu" class="mobile-menu lg:hidden absolute left-0 right-0 glass z-50" style="top: calc(4rem + env(safe-area-inset-top)); border-bottom: 1px solid var(--border);">
    <div class="px-5 py-6 flex flex-col gap-1">
      <a href="<?php echo vs_e($vsBase); ?>/apis" class="nav-link text-base py-3" style="color: var(--fg);">API 市场</a>
      <a href="<?php echo vs_e($homeHash); ?>#playground" class="nav-link text-base py-3" style="color: var(--fg);">在线演示</a>
      <a href="<?php echo vs_e($homeHash); ?>#features" class="nav-link text-base py-3" style="color: var(--fg);">核心能力</a>
      <a href="<?php echo vs_e($vsBase); ?>/user/recharge" class="nav-link text-base py-3" style="color: var(--fg);">积分充值</a>
      <a href="<?php echo vs_e($vsBase); ?>/articles" class="nav-link text-base py-3" style="color: var(--fg);">文章</a>
      <div class="flex gap-3 pt-4">
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-ghost flex-1 justify-center text-sm"><?php echo vs_e($authLabel); ?></a>
        <a href="<?php echo vs_e($userLoggedIn ? ($vsBase . '/user/') : ($vsBase . '/user/register')); ?>" class="btn-primary flex-1 justify-center text-sm"><?php echo $userLoggedIn ? '用户中心' : '免费开始'; ?></a>
      </div>
    </div>
  </div>
</header>
