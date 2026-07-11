<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<div class="st-root">
<header class="st-bar">
    <div class="st-wrap st-bar__inner">
        <a href="<?php echo vs_e($vsBase); ?>/" class="st-brand">
            <?php vs_theme_site_logo('st-brand__img', 'st-brand__fallback'); ?>
            <span class="st-brand__name"><?php echo vs_e($siteName); ?></span>
        </a>
        <nav class="st-bar__nav" aria-label="主导航">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="st-bar__link<?php echo $activeNav === $item['id'] ? ' is-on' : ''; ?>">
                    <?php echo vs_e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <a href="<?php echo vs_e($authUrl); ?>" class="st-bar__login"><?php echo vs_e($authLabel); ?></a>
        <button type="button" class="st-bar__menu" id="stMenuBtn" aria-label="打开菜单" aria-expanded="false" aria-controls="stDrawer">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<div class="st-mask" id="stMask" hidden></div>
<aside class="st-drawer" id="stDrawer" aria-label="站点菜单" hidden>
    <div class="st-drawer__head">
        <?php vs_theme_site_logo('st-drawer__img', 'st-drawer__fallback'); ?>
        <span class="st-drawer__name"><?php echo vs_e($siteName); ?></span>
    </div>
    <nav class="st-drawer__nav">
        <?php foreach ($navItems as $item): ?>
            <a href="<?php echo vs_e($item['url']); ?>"
               class="st-drawer__link<?php echo $activeNav === $item['id'] ? ' is-on' : ''; ?>">
                <?php echo vs_e($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="st-drawer__foot">
        <a href="<?php echo vs_e($authUrl); ?>" class="st-bar__login st-bar__login--block"><?php echo vs_e($authLabel); ?></a>
    </div>
</aside>
