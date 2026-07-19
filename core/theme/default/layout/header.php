<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$authBtnLabel = !empty($userLoggedIn) ? '用户中心' : $authLabel;
$siteLogo = class_exists('SiteContext') ? trim(SiteContext::siteLogo()) : '';
$avatarUrl = (!empty($userLoggedIn) && !empty($authAvatarUrl)) ? (string) $authAvatarUrl : '';
if (!empty($userLoggedIn) && $avatarUrl === '' && class_exists('UserAvatar') && class_exists('UserAuth')) {
    $authUser = UserAuth::user();
    if (is_array($authUser)) {
        $avatarUrl = UserAvatar::resolve($authUser);
    }
}
?>
<canvas id="shader-canvas"></canvas>
<div class="grid-overlay"></div>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleMobile()"></div>
<aside class="mobile-sidebar" id="mobile-sidebar">
    <button type="button" onclick="toggleMobile()" class="absolute top-3 right-3 p-1" style="color: var(--text-muted); border:none;background:transparent;cursor:pointer;">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="flex flex-col gap-4 mt-8">
        <?php foreach ($navItems as $item): ?>
            <a href="<?php echo vs_e($item['url']); ?>"
               class="feer-nav-link font-bold<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>"
               onclick="closeSidebarNow()"><?php echo vs_e($item['label']); ?></a>
        <?php endforeach; ?>
    </div>
    <div class="mt-auto sidebar-auth-slot">
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek w-full text-center auth-entry-btn<?php echo ($avatarUrl !== '' && !empty($userLoggedIn)) ? ' auth-entry-btn--user' : ''; ?>" onclick="closeSidebarNow()">
            <?php if ($avatarUrl !== '' && !empty($userLoggedIn)): ?>
                <img class="auth-entry-avatar" src="<?php echo vs_e($avatarUrl); ?>" alt="" width="22" height="22" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php endif; ?>
            <span><?php echo vs_e($authBtnLabel); ?></span>
        </a>
    </div>
</aside>
<nav class="nav-bar">
    <a href="<?php echo vs_e($vsBase); ?>/" class="feer-brand flex items-center gap-2">
        <?php if ($siteLogo !== ''): ?>
            <?php vs_render_site_logo('feer-brand__img'); ?>
        <?php else: ?>
            <span class="feer-brand__fallback" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="font-mono text-base font-bold truncate"><?php echo vs_e($siteName); ?></span>
    </a>
    <div class="flex items-center gap-3">
        <div class="hidden md:flex items-center gap-6 font-mono text-xs">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="feer-nav-link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>"><?php echo vs_e($item['label']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php /* 登录态入口仅放侧栏底部；顶栏不展示用户中心/头像。已登录时桌面也显示菜单以便打开侧栏 */ ?>
        <?php if (empty($userLoggedIn)): ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek text-xs py-2 px-4 hidden md:inline-flex auth-entry-btn">
            <span><?php echo vs_e($authBtnLabel); ?></span>
        </a>
        <button type="button" class="menu-btn md:hidden p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php else: ?>
        <button type="button" class="menu-btn p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php endif; ?>
    </div>
</nav>
