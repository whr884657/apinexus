<?php
/**
 * 文件：admin/about.php
 * 作用：ApiNexus 后台关于页面（产品信息 / 运行环境 / 链接 / 致谢）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

$systemInfo = SystemInfo::collect();
$updateCheck = Updater::checkForUpdate();
$infoMap = array();
foreach ($systemInfo as $row) {
    if (isset($row['label'])) {
        $infoMap[(string) $row['label']] = isset($row['value']) ? (string) $row['value'] : '';
    }
}

$siteName = SiteContext::siteName();
$siteDesc = trim(SiteContext::siteDescription());
if ($siteDesc === '') {
    $siteDesc = '可自部署的开放 API 接口平台：接口目录与调试、审核与分类、令牌与积分、双主题前台与云端在线更新。';
}
$siteLogo = trim(SiteContext::siteLogo());
$logoLetter = function_exists('mb_substr')
    ? mb_substr($siteName, 0, 1, 'UTF-8')
    : substr($siteName, 0, 1);

$envPairs = array(
    array(
        array('系统版本', 'version'),
        array('PHP 版本', isset($infoMap['PHP 版本']) ? $infoMap['PHP 版本'] : PHP_VERSION),
    ),
    array(
        array('数据库', isset($infoMap['MySQL 版本']) ? $infoMap['MySQL 版本'] : '—'),
        array('Web 服务器', isset($infoMap['服务器软件']) ? $infoMap['服务器软件'] : '—'),
    ),
    array(
        array('操作系统', isset($infoMap['操作系统']) ? $infoMap['操作系统'] : PHP_OS),
        array('Redis', isset($infoMap['Redis 版本']) ? $infoMap['Redis 版本'] : '—'),
    ),
    array(
        array('时区', isset($infoMap['时区']) ? $infoMap['时区'] : date_default_timezone_get()),
        array('服务器时间', isset($infoMap['服务器时间']) ? $infoMap['服务器时间'] : date('Y-m-d H:i:s')),
    ),
);

$links = array(
    array(
        'label' => 'Gitee 主仓库',
        'value' => 'gitee.com/xunjinlu/apinexus',
        'href'  => 'https://gitee.com/xunjinlu/apinexus',
        'icon'  => 'repo',
    ),
    array(
        'label' => 'GitCode 镜像',
        'value' => 'gitcode.com/xunjinlu/apinexus',
        'href'  => 'https://gitcode.com/xunjinlu/apinexus',
        'icon'  => 'repo',
    ),
    array(
        'label' => 'GitHub 镜像',
        'value' => 'github.com/whr884657/apinexus',
        'href'  => 'https://github.com/whr884657/apinexus',
        'icon'  => 'github',
    ),
    array(
        'label' => '发行版下载',
        'value' => 'Gitee Releases',
        'href'  => 'https://gitee.com/xunjinlu/apinexus/releases',
        'icon'  => 'download',
    ),
);

$techList = array(
    array('name' => 'PHP', 'href' => 'https://www.php.net'),
    array('name' => 'MySQL', 'href' => 'https://www.mysql.com'),
    array('name' => 'Redis', 'href' => 'https://redis.io'),
    array('name' => 'Parsedown', 'href' => 'https://parsedown.org'),
);

/**
 * @param string $type
 * @return string
 */
function vs_about_link_icon_svg($type)
{
    if ($type === 'github') {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>';
    }
    if ($type === 'download') {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    }
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
}

vs_admin_layout_start('关于', 'about');
?>

<div id="adminAboutPage">
    <div class="vs-panel about-section about-hero-panel">
        <div class="about-hero">
            <?php if ($siteLogo !== ''): ?>
                <img class="about-hero__logo-img" src="<?php echo vs_e($siteLogo); ?>" alt="" width="64" height="64" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php else: ?>
                <div class="about-hero__logo"><?php echo vs_e($logoLetter); ?></div>
            <?php endif; ?>
            <div class="about-hero__name"><?php echo vs_e($siteName); ?></div>
            <div class="about-hero__version"><?php echo vs_render_version_display($updateCheck); ?></div>
            <p class="about-hero__desc"><?php echo vs_e($siteDesc); ?></p>
        </div>
    </div>

    <div class="vs-panel about-section about-env-panel">
        <div class="about-panel-head">
            <span class="about-panel-head__title">运行环境</span>
            <span class="about-badge about-badge--ok">运行正常</span>
        </div>
        <div class="about-env-wrap">
            <div class="vs-table-responsive">
                <table class="vs-table about-env-table">
                    <thead>
                        <tr>
                            <th>项目</th>
                            <th>信息</th>
                            <th>项目</th>
                            <th>信息</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($envPairs as $pair): ?>
                            <tr>
                                <td><span class="about-env-label"><?php echo vs_e($pair[0][0]); ?></span></td>
                                <td>
                                    <span class="about-env-value">
                                        <?php
                                        if ($pair[0][1] === 'version') {
                                            echo vs_render_version_display($updateCheck);
                                        } else {
                                            echo vs_e($pair[0][1]);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><span class="about-env-label"><?php echo vs_e($pair[1][0]); ?></span></td>
                                <td><span class="about-env-value"><?php echo vs_e($pair[1][1]); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="about-dual about-section">
        <div class="vs-panel">
            <div class="about-panel-head">
                <span class="about-panel-head__title">开发与维护</span>
            </div>
            <div class="about-panel-body">
                <div class="about-team-grid">
                    <div class="about-team-item">
                        <div class="about-team-avatar">尋</div>
                        <div>
                            <div class="about-team-name">尋鯨錄</div>
                            <div class="about-team-role">项目作者 / 维护</div>
                        </div>
                    </div>
                    <div class="about-team-item">
                        <div class="about-team-avatar about-team-avatar--alt">A</div>
                        <div>
                            <div class="about-team-name">ApiNexus</div>
                            <div class="about-team-role">开放接口平台</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="vs-panel">
            <div class="about-panel-head">
                <span class="about-panel-head__title">相关链接</span>
            </div>
            <div class="about-panel-body">
                <div class="about-link-list">
                    <?php foreach ($links as $link): ?>
                        <a class="about-link-item" href="<?php echo vs_e($link['href']); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="about-link-item__icon"><?php echo vs_about_link_icon_svg($link['icon']); ?></span>
                            <span class="about-link-item__label"><?php echo vs_e($link['label']); ?></span>
                            <span class="about-link-item__value"><?php echo vs_e($link['value']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="vs-panel about-section">
        <div class="about-panel-head">
            <span class="about-panel-head__title">技术基础</span>
            <span class="about-badge about-badge--muted">开源组件</span>
        </div>
        <div class="about-panel-body">
            <div class="about-tech-list">
                <?php foreach ($techList as $tech): ?>
                    <a class="about-tech-badge" href="<?php echo vs_e($tech['href']); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="about-tech-badge__dot" aria-hidden="true"></span>
                        <?php echo vs_e($tech['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="about-tech-note">本系统基于以上开源基础能力构建，感谢相关项目与贡献者。</p>
        </div>
    </div>

    <div class="about-copyright about-section">
        <div class="about-copyright__main">&copy; <?php echo date('Y'); ?> ApiNexus · <?php echo vs_e($siteName); ?></div>
        <div class="about-copyright__license">采用 ApiNexus 开源许可协议 · v<?php echo vs_e(VS_VERSION); ?></div>
    </div>
</div>

<?php vs_admin_layout_end(); ?>
