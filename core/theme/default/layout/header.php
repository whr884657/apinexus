<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<div class="dt-page">
<header class="dt-header">
    <div class="dt-container dt-header__inner">
        <a href="<?php echo vs_e($vsBase); ?>/" class="dt-logo">
            <?php vs_theme_site_logo('dt-logo__icon', 'dt-logo__icon-fallback'); ?>
            <span class="dt-logo__text"><?php echo vs_e($siteName); ?></span>
        </a>
        <nav class="dt-nav" aria-label="主导航">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="dt-nav__link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>">
                    <?php echo vs_e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <a href="<?php echo vs_e($authUrl); ?>" class="dt-auth-btn dt-auth-btn--desktop"><?php echo vs_e($authLabel); ?></a>
        <button type="button" class="dt-menu-btn" id="dtMenuBtn" aria-label="打开菜单" aria-expanded="false" aria-controls="dtDrawer">
            <span class="dt-menu-btn__bar"></span>
            <span class="dt-menu-btn__bar"></span>
            <span class="dt-menu-btn__bar"></span>
        </button>
    </div>
</header>

<div class="dt-drawer-mask" id="dtDrawerMask" hidden></div>
<aside class="dt-drawer" id="dtDrawer" aria-label="站点菜单" hidden>
    <div class="dt-drawer__head">
        <div class="dt-drawer__brand">
            <?php vs_theme_site_logo('dt-drawer__icon', 'dt-drawer__icon-fallback'); ?>
            <span class="dt-drawer__title"><?php echo vs_e($siteName); ?></span>
        </div>
    </div>
    <nav class="dt-drawer__nav">
        <?php foreach ($navItems as $item): ?>
            <a href="<?php echo vs_e($item['url']); ?>"
               class="dt-drawer__link<?php echo $activeNav === $item['id'] ? ' is-active' : ''; ?>">
                <?php echo vs_e($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="dt-drawer__foot">
        <a href="<?php echo vs_e($authUrl); ?>" class="dt-auth-btn dt-auth-btn--block"><?php echo vs_e($authLabel); ?></a>
    </div>
</aside>
