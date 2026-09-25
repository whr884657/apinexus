<?php
/**
 * 主题 five · 前台顶栏
 * 布局：七牛云式企业导航（白底吸顶 + logo + 居中链接 + 登录/注册按钮 + 主题切换）
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
$ctaLabel = $userLoggedIn ? '控制台' : '登录 / 注册';

if (!empty($pageSeo) && is_array($pageSeo) && function_exists('vs_render_theme_seo_block')) {
    vs_render_theme_seo_block($pageSeo);
}
?>
<div class="th5-root" id="TH5Root">
<script>
(function () {
  try {
    var t = localStorage.getItem('th5-theme');
    if (t !== 'dark' && t !== 'light') {
      t = localStorage.getItem('theme');
    }
    if (t !== 'dark' && t !== 'light') {
      /* 主题5 浅色优先（七牛云式企业蓝），用户可手动切换暗色 */
      t = 'light';
    }
    var root = document.documentElement;
    root.classList.toggle('dark', t === 'dark');
    root.setAttribute('data-theme', t);
    root.style.colorScheme = t;
  } catch (e) { /* ignore */ }
})();
</script>

<header id="nav" class="th5-header">
  <div class="th5-header__inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <a href="<?php echo vs_e($vsBase); ?>/" class="th5-brand th5-header__brand">
      <span class="th5-brand-logo<?php echo $hasSiteLogo ? ' th5-brand-logo--img' : ' th5-brand-logo--fallback'; ?>">
        <?php if ($hasSiteLogo && function_exists('vs_theme_site_logo')): ?>
          <?php vs_theme_site_logo('th5-brand-img', 'th5-brand-fallback'); ?>
        <?php else: ?>
          <span class="th5-brand-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </span>
      <span class="th5-header__name font-display font-bold tracking-tight truncate"><?php echo vs_e($navName); ?></span>
    </a>

    <nav class="th5-header__nav" aria-label="主导航">
      <?php foreach ($navItems as $item): ?>
        <?php
          $itemId = isset($item['id']) ? (string) $item['id'] : '';
          $itemUrl = isset($item['url']) ? (string) $item['url'] : '#';
          $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
          if ($itemLabel === '') { continue; }
          $isActive = ($activeNav !== '' && $itemId === $activeNav) ? ' is-active' : '';
        ?>
        <a href="<?php echo vs_e($itemUrl); ?>" class="th5-header__link nav-link<?php echo $isActive; ?>">
          <?php echo vs_e($itemLabel); ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="th5-header__actions">
      <button id="themeToggle" class="th5-header__theme js-th5-theme-toggle" aria-label="切换主题" type="button">
        <i data-lucide="moon" style="width:17px;height:17px;"></i>
      </button>
      <a href="<?php echo vs_e($ctaUrl); ?>" class="th5-header__cta th5-auth-entry<?php echo ($userLoggedIn && $authAvatarUrl !== '') ? ' th5-auth-entry--user' : ''; ?>">
        <?php if ($userLoggedIn && $authAvatarUrl !== ''): ?>
          <img class="th5-auth-avatar" src="<?php echo vs_e($authAvatarUrl); ?>" alt="用户头像" width="26" height="26" loading="lazy" referrerpolicy="no-referrer" decoding="async">
        <?php endif; ?>
        <span><?php echo vs_e($ctaLabel); ?></span>
        <?php if (!$userLoggedIn): ?>
          <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
        <?php endif; ?>
      </a>
      <button id="menuToggle" class="th5-header__menu-btn th5-menu-toggle" type="button" aria-label="打开菜单" aria-expanded="false" aria-controls="mobileMenu">
        <i data-lucide="menu" style="width:19px;height:19px;color:var(--fg-2);"></i>
      </button>
    </div>
  </div>

  <div id="menuBackdrop" class="menu-backdrop lg:hidden" aria-hidden="true"></div>
  <div id="mobileMenu" class="mobile-menu th5-mobile-menu lg:hidden absolute left-0 right-0" aria-hidden="true" aria-label="站点菜单">
    <div class="px-5 py-6 flex flex-col gap-1">
      <?php foreach ($navItems as $item): ?>
        <?php
          $itemId = isset($item['id']) ? (string) $item['id'] : '';
          $itemUrl = isset($item['url']) ? (string) $item['url'] : '#';
          $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
          if ($itemLabel === '') { continue; }
          $isActive = ($activeNav !== '' && $itemId === $activeNav) ? ' is-active' : '';
        ?>
        <a href="<?php echo vs_e($itemUrl); ?>" class="nav-link text-base py-3 th5-mobile-nav-link<?php echo $isActive; ?>" style="color: var(--fg);">
          <?php echo vs_e($itemLabel); ?>
        </a>
      <?php endforeach; ?>
      <?php if ($userLoggedIn): ?>
        <div class="pt-4">
          <a href="<?php echo vs_e($authUrl); ?>" class="btn-primary w-full justify-center text-sm th5-mobile-cta">
            <?php if ($authAvatarUrl !== ''): ?>
              <img class="th5-auth-avatar" src="<?php echo vs_e($authAvatarUrl); ?>" alt="用户头像" width="24" height="24" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php endif; ?>
            <span>进入控制台</span>
          </a>
        </div>
      <?php else: ?>
        <div class="pt-4">
          <a href="<?php echo vs_e($loginUrl); ?>" class="btn-primary w-full justify-center text-sm th5-mobile-cta">
            登录 / 注册
            <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>
