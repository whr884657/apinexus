<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$authBtnLabel = !empty($userLoggedIn) ? '用户中心' : $authLabel;
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
    <div class="mt-auto">
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek w-full text-center block" onclick="closeSidebarNow()"><?php echo vs_e($authBtnLabel); ?></a>
    </div>
</aside>
<nav class="nav-bar">
    <a href="<?php echo vs_e($vsBase); ?>/" class="flex items-center gap-2" style="text-decoration: none; color: inherit; min-width:0;">
        <svg class="w-6 h-6 flex-shrink-0" style="color: var(--accent-primary)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        <span class="font-mono text-base font-bold truncate"><?php echo vs_e($siteName); ?></span>
    </a>
    <div class="flex items-center gap-3">
        <div class="hidden md:flex items-center gap-6 font-mono text-xs">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="feer-nav-link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>"><?php echo vs_e($item['label']); ?></a>
            <?php endforeach; ?>
        </div>
        <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="切换主题">
            <svg class="w-4 h-4 sun-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <svg class="w-4 h-4 moon-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        </button>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek text-xs py-2 px-4 hidden md:inline-block"><?php echo vs_e($authBtnLabel); ?></a>
        <button type="button" class="menu-btn md:hidden p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
</nav>
