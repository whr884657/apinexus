<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$footDesc = $siteDesc !== '' ? $siteDesc : '基于 PHP + MySQL 的轻量级 Web 管理系统，提供安装向导、后台管理与主题系统。';
?>
<footer class="dt-foot">
    <div class="dt-container dt-foot__grid">
        <div class="dt-foot__brand">
            <div class="dt-foot__logo-row">
                <?php vs_theme_site_logo('dt-foot__img', 'dt-foot__fallback'); ?>
                <strong><?php echo vs_e($siteName); ?></strong>
            </div>
            <p><?php echo vs_e($footDesc); ?></p>
        </div>
        <div class="dt-foot__col">
            <h4>资源</h4>
            <ul>
                <li><a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/articles">文章</a></li>
                <li><a href="https://gitee.com/xunjinlu/misc-api/releases" target="_blank" rel="noopener noreferrer">更新日志</a></li>
            </ul>
        </div>
        <div class="dt-foot__col">
            <h4>支持</h4>
            <ul>
                <li><a href="<?php echo vs_e($vsBase); ?>/about">关于我们</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/sponsor">赞助支持</a></li>
                <li><a href="<?php echo vs_e($vsBase); ?>/links">友情链接</a></li>
            </ul>
        </div>
        <div class="dt-foot__col">
            <h4>社区</h4>
            <ul>
                <li><a href="<?php echo vs_e($vsBase); ?>/contributors">贡献者</a></li>
                <li><a href="https://gitee.com/xunjinlu/misc-api" target="_blank" rel="noopener noreferrer">开源仓库</a></li>
                <li><a href="<?php echo vs_e($authUrl); ?>"><?php echo !empty($userLoggedIn) ? '用户中心' : vs_e($authLabel); ?></a></li>
            </ul>
        </div>
    </div>
    <div class="dt-foot__bottom">
        <div class="dt-container"><?php vs_render_site_footer($siteName); ?></div>
    </div>
</footer>
<button type="button" class="dt-back-top" id="dtBackTop" aria-label="返回顶部" hidden>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
</div>
