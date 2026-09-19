<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$navName = isset($navName) ? (string) $navName : $siteName;
$navItems = isset($navItems) && is_array($navItems) ? $navItems : array();
$activeNav = isset($activeNav) ? (string) $activeNav : '';
$authUrl = isset($authUrl) ? (string) $authUrl : ($vsBase . '/user/login');
$authLabel = isset($authLabel) ? (string) $authLabel : '登录';
$userLoggedIn = !empty($userLoggedIn);
$authAvatarUrl = isset($authAvatarUrl) ? (string) $authAvatarUrl : '';
$authBtnLabel = $userLoggedIn ? '用户中心' : $authLabel;
$siteLogo = class_exists('SiteContext') ? trim(SiteContext::siteLogo()) : '';
$avatarUrl = ($userLoggedIn && $authAvatarUrl !== '') ? $authAvatarUrl : '';
if ($userLoggedIn && $avatarUrl === '' && class_exists('UserAvatar') && class_exists('UserAuth')) {
    $authUser = UserAuth::user();
    if (is_array($authUser)) {
        $avatarUrl = UserAvatar::resolve($authUser);
    }
}
?>
<link rel="stylesheet" href="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/css/docs-nav.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">
<?php
$docs4PageKey = isset($pageKey) ? (string) $pageKey : '';
$docs4FeerCss = array(
    'apis' => 'apis.css',
    'articles' => 'articles.css',
    'about' => 'about.css',
    'links' => 'links.css',
    'applylink' => 'applylink.css',
    'contributors' => 'contributors.css',
    'profile' => 'profile.css',
    'sponsor' => 'donate.css',
);
if (isset($docs4FeerCss[$docs4PageKey])) {
    foreach (array('front-common.css', 'markdown-content.css', 'theme-tokens.css', 'feer-compat.css', $docs4FeerCss[$docs4PageKey]) as $docs4CssFile) {
        echo '<link rel="stylesheet" href="' . vs_e(ThemeManager::assetUrl('docs', 'assets/css/feer/' . $docs4CssFile)) . '?v=' . vs_e(VS_VERSION) . '">' . "\n";
    }
    echo '<style>body.vs-body.feer-front{padding-top:0}</style>' . "\n";
    echo '<script>document.body.classList.add("feer-front");</script>' . "\n";
}
?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/js/docs-nav.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>"></script>
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
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek w-full text-center auth-entry-btn<?php echo ($avatarUrl !== '' && !empty($userLoggedIn)) ? ' auth-entry-btn--user' : ''; ?>" onclick="closeSidebarNow()">
            <?php if ($avatarUrl !== '' && !empty($userLoggedIn)): ?>
                <img class="auth-entry-avatar" src="<?php echo vs_e($avatarUrl); ?>" alt="用户头像" width="22" height="22" loading="lazy" referrerpolicy="no-referrer" decoding="async">
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
        <span class="font-mono text-base font-bold truncate"><?php echo vs_e(isset($navName) ? $navName : $siteName); ?></span>
    </a>
    <div class="flex items-center gap-3">
        <div class="hidden md:flex items-center gap-6 font-mono text-xs">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="feer-nav-link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>"><?php echo vs_e($item['label']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php
        // 未登录：电脑端单一按钮「登录 / 注册」，href 仅指向登录页（注册在登录页内链；勿拆成两钮）
        // 已登录：顶栏「用户中心」；手机端汉堡进侧栏
        ?>
        <?php if (empty($userLoggedIn)): ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek text-xs py-2 px-4 auth-entry-btn auth-entry-btn--nav">
            <span><?php echo vs_e($authBtnLabel); ?></span>
        </a>
        <button type="button" class="menu-btn nav-menu-mobile p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php else: ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek text-xs py-2 px-4 auth-entry-btn auth-entry-btn--nav<?php echo ($avatarUrl !== '') ? ' auth-entry-btn--user' : ''; ?>">
            <?php if ($avatarUrl !== ''): ?>
                <img class="auth-entry-avatar" src="<?php echo vs_e($avatarUrl); ?>" alt="用户头像" width="20" height="20" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php endif; ?>
            <span><?php echo vs_e($authBtnLabel); ?></span>
        </a>
        <button type="button" class="menu-btn nav-menu-mobile p-1" style="color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 6px;" onclick="toggleMobile()" aria-label="打开菜单">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php endif; ?>
    </div>
</nav>
