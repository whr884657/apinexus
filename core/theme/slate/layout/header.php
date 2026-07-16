<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$stNavExpandMode = ThemeManager::themeSettingStr('nav_expand_mode', 'top_drawer');
$stNavUseFab = ($stNavExpandMode === 'fab_popup');
$stColorPreset = ThemeManager::themeSettingStr('color_preset', 'green');
$allowedTint = array('green', 'rose', 'orange', 'yellow', 'mint', 'sky', 'violet', 'pink', 'cyan');
if (!in_array($stColorPreset, $allowedTint, true)) {
    $stColorPreset = 'green';
}
$stTintSwatches = array(
    array('id' => 'green', 'hex' => '#eef6f1', 'label' => '浅绿'),
    array('id' => 'rose', 'hex' => '#fef2f2', 'label' => '浅玫瑰'),
    array('id' => 'orange', 'hex' => '#fff7ed', 'label' => '浅橙'),
    array('id' => 'yellow', 'hex' => '#fefce8', 'label' => '浅黄'),
    array('id' => 'mint', 'hex' => '#f0fdf4', 'label' => '薄荷'),
    array('id' => 'sky', 'hex' => '#eff6ff', 'label' => '天空蓝'),
    array('id' => 'violet', 'hex' => '#f5f3ff', 'label' => '浅紫'),
    array('id' => 'pink', 'hex' => '#fdf4ff', 'label' => '浅粉'),
    array('id' => 'cyan', 'hex' => '#ecfeff', 'label' => '浅青'),
);
?>
<div class="st-root<?php echo $stNavUseFab ? ' st-root--nav-fab' : ''; ?>" data-nav-mode="<?php echo vs_e($stNavExpandMode); ?>" data-st-default-tint="<?php echo vs_e($stColorPreset); ?>">
<header class="st-bar">
    <div class="st-wrap st-bar__inner">
        <div class="st-brand">
            <span class="st-brand__logo"><?php vs_theme_site_logo('st-brand__img', 'st-brand__fallback'); ?></span>
            <a href="<?php echo vs_e($vsBase); ?>/" class="st-brand__name"><?php echo vs_e($siteName); ?></a>
        </div>
        <nav class="st-bar__nav" aria-label="主导航">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo vs_e($item['url']); ?>"
                   class="st-bar__link<?php echo $activeNav === $item['id'] ? ' is-on' : ''; ?>">
                    <?php echo vs_e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="st-bar__actions">
            <div class="st-tint" id="stTint">
                <button type="button" class="st-tint__btn" id="stTintBtn" aria-label="选择主题色" aria-expanded="false" aria-controls="stTintPanel" title="主题色">
                    <span class="st-tint__disc" aria-hidden="true"></span>
                </button>
                <div class="st-tint__panel" id="stTintPanel" hidden role="listbox" aria-label="浅色主题">
                    <?php foreach ($stTintSwatches as $sw): ?>
                        <?php if ($sw['id'] === 'green') { continue; } /* 前台圆盘仅 5～12 淡色 */ ?>
                        <button type="button" class="st-tint__swatch" data-tint="<?php echo vs_e($sw['id']); ?>" style="--swatch:<?php echo vs_e($sw['hex']); ?>" title="<?php echo vs_e($sw['label']); ?>" aria-label="<?php echo vs_e($sw['label']); ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="<?php echo vs_e($authUrl); ?>" class="st-bar__login<?php echo (!empty($userLoggedIn) && !empty($authAvatarUrl)) ? ' st-bar__login--user' : ''; ?>">
                <?php if (!empty($userLoggedIn) && !empty($authAvatarUrl)): ?>
                    <img src="<?php echo vs_e($authAvatarUrl); ?>" alt="" class="st-bar__login-avatar" width="28" height="28">
                    <span>用户中心</span>
                <?php else: ?>
                    <?php echo vs_e($authLabel); ?>
                <?php endif; ?>
            </a>
            <button type="button" class="st-bar__menu" id="stMenuBtn" aria-label="打开菜单" aria-expanded="false" aria-controls="stDrawer"<?php echo $stNavUseFab ? ' hidden' : ''; ?>>
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<div class="st-mask" id="stMask" hidden<?php echo $stNavUseFab ? ' data-nav-disabled="1"' : ''; ?>></div>
<aside class="st-drawer" id="stDrawer" aria-label="站点菜单" hidden<?php echo $stNavUseFab ? ' data-nav-disabled="1"' : ''; ?>>
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
        <a href="<?php echo vs_e($authUrl); ?>" class="st-bar__login st-bar__login--block<?php echo (!empty($userLoggedIn) && !empty($authAvatarUrl)) ? ' st-bar__login--user' : ''; ?>">
            <?php if (!empty($userLoggedIn) && !empty($authAvatarUrl)): ?>
                <img src="<?php echo vs_e($authAvatarUrl); ?>" alt="" class="st-bar__login-avatar" width="28" height="28">
                <span>用户中心</span>
            <?php else: ?>
                <?php echo vs_e($authLabel); ?>
            <?php endif; ?>
        </a>
    </div>
</aside>
