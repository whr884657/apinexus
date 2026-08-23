<?php
/**
 * 主题 three · 前台顶栏
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$navName = isset($navName) ? (string) $navName : $siteName;
$navItems = isset($navItems) && is_array($navItems) ? $navItems : ThemeManager::navItems();
$activeNav = isset($activeNav) ? (string) $activeNav : (isset($pageKey) ? (string) $pageKey : '');
$authUrl = isset($authUrl) ? (string) $authUrl : ($vsBase . '/user/login');
$userLoggedIn = !empty($userLoggedIn);
$authAvatarUrl = isset($authAvatarUrl) ? trim((string) $authAvatarUrl) : '';
if ($userLoggedIn && $authAvatarUrl === '' && class_exists('UserAvatar') && class_exists('UserAuth')) {
    $authUser = UserAuth::user();
    if (is_array($authUser)) {
        $authAvatarUrl = UserAvatar::resolve($authUser);
    }
}
$siteLogo = class_exists('SiteContext') ? trim(SiteContext::siteLogo()) : '';
$hasSiteLogo = $siteLogo !== '';
$loginUrl = $vsBase . '/user/login';
$ctaUrl = $userLoggedIn ? $authUrl : $loginUrl;
$ctaLabel = $userLoggedIn ? '用户中心' : '免费开始';

if (!empty($pageSeo) && is_array($pageSeo) && function_exists('vs_render_theme_seo_block')) {
    vs_render_theme_seo_block($pageSeo);
}
?>
<div class="th3-root" id="th3Root">
<script>
(function () {
  try {
    var t = localStorage.getItem('th3-theme');
    if (t !== 'dark' && t !== 'light') {
      t = localStorage.getItem('theme');
    }
    if (t !== 'dark' && t !== 'light') {
      t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }
    var root = document.documentElement;
    root.classList.toggle('dark', t === 'dark');
    root.setAttribute('data-theme', t);
    root.style.colorScheme = t;
  } catch (e) { /* ignore */ }
})();
</script>
<header id="nav" class="site-header th3-header">
  <div class="th3-header__inner nav-inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <a href="<?php echo vs_e($vsBase); ?>/" class="th3-brand th3-header__brand">
      <span class="th3-brand-logo<?php echo $hasSiteLogo ? ' th3-brand-logo--img' : ' th3-brand-logo--fallback'; ?>">
        <?php if ($hasSiteLogo && function_exists('vs_theme_site_logo')): ?>
          <?php vs_theme_site_logo('th3-brand-img', 'th3-brand-fallback'); ?>
        <?php else: ?>
          <span class="th3-brand-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </span>
      <span class="th3-header__name font-display font-bold tracking-tight truncate"><?php echo vs_e($navName); ?></span>
    </a>

    <nav class="th3-header__nav" aria-label="主导航">
      <?php foreach ($navItems as $item): ?>
        <?php
          $itemId = isset($item['id']) ? (string) $item['id'] : '';
          $itemUrl = isset($item['url']) ? (string) $item['url'] : '#';
          $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
          if ($itemLabel === '') { continue; }
          $isActive = ($activeNav !== '' && $itemId === $activeNav) ? ' is-active' : '';
        ?>
        <a href="<?php echo vs_e($itemUrl); ?>" class="th3-header__link nav-link<?php echo $isActive; ?>"><?php echo vs_e($itemLabel); ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="th3-header__actions">
      <button id="themeToggle" class="theme-toggle js-th3-theme-toggle th3-header__theme" aria-label="切换主题" type="button">
        <div class="knob"><i data-lucide="sun"></i></div>
      </button>
      <a href="<?php echo vs_e($ctaUrl); ?>" class="th3-header__cta th3-auth-entry<?php echo ($userLoggedIn && $authAvatarUrl !== '') ? ' th3-auth-entry--user' : ''; ?>">
        <?php if ($userLoggedIn && $authAvatarUrl !== ''): ?>
          <img class="th3-auth-avatar" src="<?php echo vs_e($authAvatarUrl); ?>" alt="用户头像" width="28" height="28" loading="lazy" referrerpolicy="no-referrer" decoding="async">
        <?php endif; ?>
        <span><?php echo vs_e($ctaLabel); ?></span>
        <?php if (!$userLoggedIn): ?>
          <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
        <?php endif; ?>
      </a>
      <button id="menuToggle" class="th3-header__menu-btn th3-menu-toggle" type="button" aria-label="打开菜单" aria-expanded="false" aria-controls="mobileMenu">
        <i data-lucide="menu" style="width:18px;height:18px;color: var(--fg);"></i>
      </button>
    </div>
  </div>

  <div id="menuBackdrop" class="menu-backdrop lg:hidden" aria-hidden="true"></div>
  <div id="mobileMenu" class="mobile-menu th3-mobile-menu lg:hidden absolute left-0 right-0 z-50" aria-hidden="true" aria-label="站点菜单">
    <div class="px-5 py-6 flex flex-col gap-1">
      <?php foreach ($navItems as $item): ?>
        <?php
          $itemId = isset($item['id']) ? (string) $item['id'] : '';
          $itemUrl = isset($item['url']) ? (string) $item['url'] : '#';
          $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
          if ($itemLabel === '') { continue; }
          $isActive = ($activeNav !== '' && $itemId === $activeNav) ? ' is-active' : '';
        ?>
        <a href="<?php echo vs_e($itemUrl); ?>" class="nav-link text-base py-3 th3-mobile-nav-link<?php echo $isActive; ?>" style="color: var(--fg);"><?php echo vs_e($itemLabel); ?></a>
      <?php endforeach; ?>
      <?php if ($userLoggedIn): ?>
        <div class="pt-4">
          <a href="<?php echo vs_e($authUrl); ?>" class="btn-primary w-full justify-center text-sm th3-mobile-cta">
            <?php if ($authAvatarUrl !== ''): ?>
              <img class="th3-auth-avatar" src="<?php echo vs_e($authAvatarUrl); ?>" alt="用户头像" width="24" height="24" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php endif; ?>
            <span>用户中心</span>
          </a>
        </div>
      <?php else: ?>
        <div class="pt-4">
          <a href="<?php echo vs_e($loginUrl); ?>" class="btn-primary w-full justify-center text-sm th3-mobile-cta">
            免费开始
            <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>
