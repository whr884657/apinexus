<?php
/**
 * 文件：admin/about.php
 * 作用：ApiNexus 后台关于页面（产品信息 / 运行环境 / 开发与维护 / 链接 / 技术栈）
 *
 * 说明：
 * - 系统版本以 core/version.php 中 VS_VERSION 为准。
 * - 「开发与维护」（相关人员 team）、「相关链接」、「技术栈」均由 AboutCatalog::load() 提供。
 * - 数据文件：core/vx/seed/r9/m2/catalog.json（有本地则用本地；仅缺失时才从仓库 raw 拉取）。
 * - 详解见 core/AboutCatalog.php 文件头注释。
 * - 技术栈 / 链接图标：一律 assets/img/ 根目录（本页 vs_about_icon_src）。
 */

require_once __DIR__ . '/init.php';

$systemInfo = SystemInfo::collect();
$updateCheck = Updater::checkForUpdate();
$catalog = AboutCatalog::load();
$infoMap = array();
foreach ($systemInfo as $row) {
    if (isset($row['label'])) {
        $infoMap[(string) $row['label']] = isset($row['value']) ? (string) $row['value'] : '';
    }
}

$systemName = SiteContext::systemName();
$siteDesc = trim(SiteContext::siteDescription());
if ($siteDesc === '') {
    $siteDesc = '可自部署的开放 API 接口平台：接口目录与调试、审核与分类、令牌与积分、双主题前台与云端在线更新。';
}
$siteLogo = trim(SiteContext::siteLogo());
$logoLetter = function_exists('mb_substr')
    ? mb_substr($systemName, 0, 1, 'UTF-8')
    : substr($systemName, 0, 1);

$envItems = array(
    array('label' => '系统版本', 'value' => 'version'),
    array('label' => 'PHP 版本', 'value' => isset($infoMap['PHP 版本']) ? $infoMap['PHP 版本'] : PHP_VERSION),
    array('label' => '数据库', 'value' => isset($infoMap['MySQL 版本']) ? $infoMap['MySQL 版本'] : '—'),
    array('label' => 'Web 服务器', 'value' => isset($infoMap['服务器软件']) ? $infoMap['服务器软件'] : '—'),
    array('label' => '操作系统', 'value' => isset($infoMap['操作系统']) ? $infoMap['操作系统'] : PHP_OS),
    array('label' => 'Redis', 'value' => isset($infoMap['Redis 版本']) ? $infoMap['Redis 版本'] : '—'),
    array('label' => '时区', 'value' => isset($infoMap['时区']) ? $infoMap['时区'] : date_default_timezone_get()),
    array('label' => '服务器时间', 'value' => isset($infoMap['服务器时间']) ? $infoMap['服务器时间'] : date('Y-m-d H:i:s')),
);

$assetBase = rtrim(vs_base_url(), '/');
$imgBase = $assetBase . '/assets/img';

/**
 * 关于页图标：只认 assets/img/ 根目录下的文件（与 MySQL.svg / Redis.svg 同级）。
 * icon 键 → 文件名；文件不存在则返回空（页面显示圆点占位）。
 *
 * @param string $icon catalog 里的 icon 字段，如 php / MySQL / javascript
 * @return string 可访问的 URL，或空串
 */
function vs_about_icon_src($icon)
{
    global $imgBase;
    $map = array(
        'gitee'      => 'gitee.svg',
        'gitcode'    => 'gitcode.svg',
        'github'     => 'github.svg',
        'php'        => 'php.svg',
        'mysql'      => 'MySQL.svg',
        'MySQL'      => 'MySQL.svg',
        'redis'      => 'Redis.svg',
        'Redis'      => 'Redis.svg',
        'nginx'      => 'nginx.svg',
        'javascript' => 'JavaScript.svg',
        'JavaScript' => 'JavaScript.svg',
        'echarts'    => 'echarts.svg',
    );
    $file = '';
    if ($icon !== '' && isset($map[$icon])) {
        $file = $map[$icon];
    } elseif ($icon !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $icon)) {
        $file = $icon . '.svg';
    }
    if ($file === '') {
        return '';
    }
    $abs = dirname(__DIR__) . '/assets/img/' . $file;
    if (!is_file($abs)) {
        return '';
    }
    return $imgBase . '/' . $file;
}

vs_admin_layout_start('关于', 'about');
?>

<div id="adminAboutPage">
    <div class="vs-panel about-section about-hero-panel about-reveal" style="--about-delay:0">
        <div class="about-hero">
            <?php if ($siteLogo !== ''): ?>
                <img class="about-hero__logo-img" src="<?php echo vs_e($siteLogo); ?>" alt="" width="64" height="64" loading="lazy" referrerpolicy="no-referrer" decoding="async">
            <?php else: ?>
                <div class="about-hero__logo"><?php echo vs_e($logoLetter); ?></div>
            <?php endif; ?>
            <div class="about-hero__name"><?php echo vs_e($systemName); ?></div>
            <div class="about-hero__version"><?php echo vs_render_version_display($updateCheck); ?></div>
            <p class="about-hero__desc"><?php echo vs_e($siteDesc); ?></p>
        </div>
    </div>

    <div class="vs-panel about-section about-env-panel about-reveal" style="--about-delay:1">
        <div class="about-panel-head">
            <span class="about-panel-head__title">运行环境</span>
            <span class="about-badge about-badge--ok">运行正常</span>
        </div>
        <div class="about-env-grid">
            <?php foreach ($envItems as $i => $item): ?>
                <div class="about-env-card" style="--env-i:<?php echo (int) $i; ?>">
                    <div class="about-env-card__label"><?php echo vs_e($item['label']); ?></div>
                    <div class="about-env-card__value">
                        <?php
                        if ($item['value'] === 'version') {
                            echo vs_render_version_display($updateCheck);
                        } else {
                            echo vs_e($item['value']);
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="about-dual about-section">
        <div class="vs-panel about-reveal" style="--about-delay:2">
            <div class="about-panel-head">
                <span class="about-panel-head__title">开发与维护</span>
            </div>
            <div class="about-panel-body">
                <div class="about-team-grid">
                    <?php foreach ($catalog['team'] as $member): ?>
                        <?php
                        $hasSite = !empty($member['site']);
                        $teamTag = $hasSite ? 'a' : 'div';
                        $teamAttrs = $hasSite
                            ? ' href="' . vs_e($member['site']) . '" target="_blank" rel="noopener noreferrer" title="' . vs_e($member['name']) . '"'
                            : '';
                        ?>
                        <<?php echo $teamTag; ?> class="about-team-item<?php echo $hasSite ? ' about-team-item--link' : ''; ?>"<?php echo $teamAttrs; ?>>
                            <?php if (!empty($member['avatar'])): ?>
                                <img class="about-team-avatar about-team-avatar--img" src="<?php echo vs_e($member['avatar']); ?>" alt="" width="44" height="44" loading="lazy" referrerpolicy="no-referrer" decoding="async">
                            <?php else: ?>
                                <div class="about-team-avatar"><?php
                                    echo vs_e(function_exists('mb_substr')
                                        ? mb_substr($member['name'], 0, 1, 'UTF-8')
                                        : substr($member['name'], 0, 1));
                                ?></div>
                            <?php endif; ?>
                            <div class="about-team-meta">
                                <div class="about-team-name"><?php echo vs_e($member['name']); ?></div>
                                <?php if (!empty($member['role'])): ?>
                                    <div class="about-team-role"><?php echo vs_e($member['role']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($hasSite): ?>
                                <span class="about-team-arrow" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M8 7h9v9"/></svg>
                                </span>
                            <?php endif; ?>
                        </<?php echo $teamTag; ?>>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="vs-panel about-reveal" style="--about-delay:3">
            <div class="about-panel-head">
                <span class="about-panel-head__title">相关链接</span>
            </div>
            <div class="about-panel-body">
                <div class="about-link-list">
                    <?php foreach ($catalog['links'] as $link): ?>
                        <?php $iconSrc = vs_about_icon_src($link['icon']); ?>
                        <a class="about-link-item" href="<?php echo vs_e($link['href']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo vs_e($link['name']); ?>">
                            <span class="about-link-item__icon<?php echo $iconSrc !== '' ? ' about-link-item__icon--brand' : ''; ?>">
                                <?php if ($iconSrc !== ''): ?>
                                    <img src="<?php echo vs_e($iconSrc); ?>" alt="" width="18" height="18" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="about-link-item__dot" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <span class="about-link-item__label"><?php echo vs_e($link['name']); ?></span>
                            <span class="about-link-item__arrow" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M8 7h9v9"/></svg>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="vs-panel about-section about-reveal" style="--about-delay:4">
        <div class="about-panel-head">
            <span class="about-panel-head__title">技术栈</span>
            <span class="about-badge about-badge--tech">开源组件</span>
        </div>
        <div class="about-panel-body">
            <div class="about-tech-list">
                <?php foreach ($catalog['tech'] as $ti => $tech): ?>
                    <?php
                    $tone = preg_replace('/[^a-z0-9_-]/i', '', $tech['tone']);
                    if ($tone === '') {
                        $tone = 'default';
                    }
                    $iconSrc = vs_about_icon_src($tech['icon']);
                    $tag = $tech['href'] !== '' ? 'a' : 'span';
                    $hrefAttr = $tech['href'] !== ''
                        ? ' href="' . vs_e($tech['href']) . '" target="_blank" rel="noopener noreferrer"'
                        : '';
                    ?>
                    <<?php echo $tag; ?> class="about-tech-badge about-tech-badge--<?php echo vs_e($tone); ?>"<?php echo $hrefAttr; ?> style="--tech-i:<?php echo (int) $ti; ?>">
                        <?php if ($iconSrc !== ''): ?>
                            <img class="about-tech-badge__icon" src="<?php echo vs_e($iconSrc); ?>" alt="" width="16" height="16" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="about-tech-badge__dot" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo vs_e($tech['name']); ?>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
            <p class="about-tech-note"><?php echo vs_e($catalog['note']); ?></p>
        </div>
    </div>

    <div class="about-copyright about-section about-reveal" style="--about-delay:5">
        <div class="about-copyright__main">&copy; <?php echo date('Y'); ?> ApiNexus · <?php echo vs_e($systemName); ?></div>
        <div class="about-copyright__license">采用 ApiNexus 开源许可协议 · v<?php echo vs_e(VS_VERSION); ?></div>
    </div>
</div>

<?php vs_admin_layout_end(array('admin-about.js')); ?>
