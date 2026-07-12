<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$footDesc = $siteDesc !== '' ? $siteDesc : '为开发者提供稳定、快速的 API 接口服务';
$stNavExpandMode = ThemeManager::themeSetting('nav_expand_mode', 'top_drawer');
$stNavUseFab = ($stNavExpandMode === 'fab_popup');
?>
<footer class="st-foot">
    <div class="st-wrap st-foot__grid">
        <div class="st-foot__brand">
            <div class="st-foot__logo-row">
                <?php vs_theme_site_logo('st-foot__img', 'st-foot__fallback'); ?>
                <strong><?php echo vs_e($siteName); ?></strong>
            </div>
            <p><?php echo vs_e($footDesc); ?></p>
        </div>
        <div class="st-foot__col">
            <h4>资源</h4>
            <ul>
                <li><a href="<?php echo vs_e($vsBase); ?>/apis">接口文档</a></li>
                <li><a href="https://gitee.com/xunjinlu/misc-api/releases" target="_blank" rel="noopener noreferrer">更新日志</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/about">服务状态</a></li>
            </ul>
        </div>
        <div class="st-foot__col">
            <h4>支持</h4>
            <ul>
                <li><a href="<?php echo vs_e($vsBase); ?>/about">常见问题</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/sponsor">反馈建议</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/links">联系站长</a></li>
            </ul>
        </div>
        <div class="st-foot__col">
            <h4>关于</h4>
            <ul>
                <li><a href="<?php echo vs_e($vsBase); ?>/about">关于我们</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/contributors">贡献者</a></li>
                <li><a href="https://gitee.com/xunjinlu/misc-api" target="_blank" rel="noopener noreferrer">开源仓库</a></li>
            </ul>
        </div>
    </div>
    <div class="st-wrap st-foot__bottom">
        <div class="st-foot__copy"><?php vs_render_site_footer($siteName); ?></div>
    </div>
</footer>
<button type="button" class="st-back-top" id="stBackTop" aria-label="返回顶部" hidden>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
<?php if ($stNavUseFab): ?>
<div class="st-nav-mask" id="stNavMask" hidden></div>
<div class="st-nav-fab-wrap" id="stNavFabWrap">
    <nav class="st-nav-pop" id="stNavPop" aria-label="站点菜单" hidden>
        <?php foreach ($navItems as $item): ?>
            <a href="<?php echo vs_e($item['url']); ?>"
               class="st-nav-pop__link<?php echo $activeNav === $item['id'] ? ' is-on' : ''; ?>">
                <?php echo vs_e($item['label']); ?>
            </a>
        <?php endforeach; ?>
        <a href="<?php echo vs_e($authUrl); ?>" class="st-nav-pop__link st-nav-pop__link--auth">
            <?php echo !empty($userLoggedIn) ? '用户中心' : vs_e($authLabel); ?>
        </a>
    </nav>
    <button type="button" class="st-nav-fab" id="stNavFab" aria-label="打开导航菜单" aria-expanded="false" aria-controls="stNavPop">
        <span class="st-nav-fab__lines" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>
</div>
<?php endif; ?>
</div>
