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
<?php
if (!empty($pageSeo) && is_array($pageSeo) && function_exists('vs_render_theme_seo_block')) {
    vs_render_theme_seo_block($pageSeo);
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
        <?php if (empty($userLoggedIn)): ?>
        <a href="<?php echo vs_e(rtrim($vsBase, '/') . '/user/login'); ?>" class="btn-geek w-full text-center auth-entry-btn" onclick="closeSidebarNow()"><span>登录</span></a>
        <a href="<?php echo vs_e(rtrim($vsBase, '/') . '/user/register'); ?>" class="btn-geek w-full text-center auth-entry-btn" style="margin-top:0.5rem;" onclick="closeSidebarNow()"><span>注册</span></a>
        <?php else: ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek w-full text-center auth-entry-btn<?php echo ($avatarUrl !== '') ? ' auth-entry-btn--user' : ''; ?>" onclick="closeSidebarNow()">
            <?php if ($avatarUrl !== ''): ?>
                <img class="auth-entry-avatar" src="<?php echo vs_e($avatarUrl); ?>" alt="" width="22" height="22" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php endif; ?>
            <span><?php echo vs_e($authBtnLabel); ?></span>
        </a>
        <?php endif; ?>
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
        <?php
        // 未登录：电脑端顶栏显示「登录」「注册」（勿用 .hidden，feer-compat 的 .hidden 带 !important 会永久隐藏）
        // 已登录：顶栏显示「用户中心」；手机端统一用汉堡进侧栏
        $loginUrl = rtrim($vsBase, '/') . '/user/login';
        $registerUrl = rtrim($vsBase, '/') . '/user/register';
        ?>
        <?php if (empty($userLoggedIn)): ?>
        <div class="nav-auth-desktop">
            <a href="<?php echo vs_e($loginUrl); ?>" class="btn-geek text-xs py-2 px-4 auth-entry-btn auth-entry-btn--nav">登录</a>
            <a href="<?php echo vs_e($registerUrl); ?>" class="btn-geek text-xs py-2 px-4 auth-entry-btn auth-entry-btn--nav auth-entry-btn--register">注册</a>
        </div>
        <button type="button" class="menu-btn nav-menu-mobile p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php else: ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek text-xs py-2 px-4 auth-entry-btn auth-entry-btn--nav auth-entry-btn--user-nav<?php echo ($avatarUrl !== '') ? ' auth-entry-btn--user' : ''; ?>">
            <?php if ($avatarUrl !== ''): ?>
                <img class="auth-entry-avatar" src="<?php echo vs_e($avatarUrl); ?>" alt="" width="20" height="20" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php endif; ?>
            <span><?php echo vs_e($authBtnLabel); ?></span>
        </a>
        <button type="button" class="menu-btn nav-menu-mobile p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php endif; ?>
    </div>
</nav>
