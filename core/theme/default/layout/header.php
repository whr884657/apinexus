<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<header class="vs-header vs-frontend-header">
    <div class="vs-container vs-header-inner vs-frontend-header__inner">
        <button type="button" class="vs-frontend-menu-toggle" id="frontendMenuToggle" aria-label="打开菜单" aria-expanded="false" aria-controls="frontendSidebar">
            <i class="vs-icon vs-icon--menu" aria-hidden="true"></i>
        </button>
        <div class="vs-logo">
            <a href="<?php echo vs_e($vsBase); ?>/" class="vs-logo-link">
                <?php vs_render_site_logo('vs-logo-icon'); ?>
                <span class="vs-logo-text"><?php echo vs_e($siteName); ?></span>
            </a>
        </div>
        <nav class="vs-nav vs-frontend-nav" aria-label="主导航">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="vs-nav-link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>">
                    <?php echo vs_e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="vs-frontend-header__auth vs-frontend-header__auth--desktop">
            <a href="<?php echo vs_e($authUrl); ?>" class="vs-btn vs-btn--default vs-btn--sm"><?php echo vs_e($authLabel); ?></a>
        </div>
    </div>
</header>

<div class="vs-frontend-sidebar-mask" id="frontendSidebarMask" hidden aria-hidden="true"></div>
<aside class="vs-frontend-sidebar" id="frontendSidebar" aria-label="站点菜单" hidden>
    <div class="vs-frontend-sidebar__head">
        <div class="vs-frontend-sidebar__brand">
            <?php vs_render_site_logo('vs-logo-icon'); ?>
            <span class="vs-frontend-sidebar__name"><?php echo vs_e($siteName); ?></span>
        </div>
        <button type="button" class="vs-frontend-sidebar__close" id="frontendSidebarClose" aria-label="关闭菜单">&times;</button>
    </div>
    <nav class="vs-frontend-sidebar__nav">
        <?php foreach ($navItems as $item): ?>
            <a href="<?php echo vs_e($item['url']); ?>"
               class="vs-frontend-sidebar__link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>">
                <?php echo vs_e($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="vs-frontend-sidebar__foot">
        <a href="<?php echo vs_e($authUrl); ?>" class="vs-btn vs-btn--primary vs-btn--block"><?php echo vs_e($authLabel); ?></a>
        <?php if (!$userLoggedIn): ?>
            <a href="<?php echo vs_e($registerUrl); ?>" class="vs-btn vs-btn--default vs-btn--block vs-frontend-sidebar__register">注册账号</a>
        <?php endif; ?>
    </div>
</aside>
