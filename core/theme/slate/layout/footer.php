<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<footer class="st-foot">
    <div class="st-wrap st-foot__grid">
        <div class="st-foot__brand">
            <div class="st-foot__logo-row">
                <?php vs_theme_site_logo('st-foot__img', 'st-foot__fallback'); ?>
                <strong><?php echo vs_e($siteName); ?></strong>
            </div>
            <p><?php echo vs_e($siteDesc !== '' ? $siteDesc : '为开发者提供稳定、快速的 API 接口服务'); ?></p>
        </div>
        <div class="st-foot__col">
            <h4>导航</h4>
            <ul>
                <?php foreach (array_slice($navItems, 0, 4) as $item): ?>
                    <li><a href="<?php echo vs_e($item['url']); ?>"><?php echo vs_e($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="st-foot__col">
            <h4>更多</h4>
            <ul>
                <?php foreach (array_slice($navItems, 4) as $item): ?>
                    <li><a href="<?php echo vs_e($item['url']); ?>"><?php echo vs_e($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="st-wrap st-foot__copy">
        <?php vs_render_site_footer($siteName); ?>
    </div>
</footer>
</div>
