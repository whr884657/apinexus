<?php
/**
 * 青绿平台 · 认证页布局（无卡片、无站点图标、居中）
 */

/**
 * @param string $pageTitle
 * @return void
 */
function vs_theme_auth_head($pageTitle)
{
    $base = vs_base_url();
    $siteName = SiteContext::siteName();
    $favicon = SiteContext::siteFavicon();
    $themeId = ThemeManager::activeId();

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<title>' . vs_e(vs_page_title($pageTitle, $siteName)) . '</title>' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">' . "\n";
    if ($favicon !== '') {
        echo '<link rel="icon" href="' . vs_e(vs_favicon_href($favicon)) . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e(ThemeManager::assetUrl($themeId, 'assets/auth.css')) . '?v=' . VS_VERSION . '">' . "\n";
    echo '</head>' . "\n";
    echo '<body class="st-auth-body">' . "\n";
}

/**
 * 认证页居中 shell（无卡片）
 *
 * @param string $headTitle
 * @param string $headSub
 * @return void
 */
function vs_slate_auth_shell_start($headTitle, $headSub = '')
{
    echo '<div class="st-auth">' . "\n";
    echo '<div class="st-auth__bg" aria-hidden="true"><span class="st-auth__orb st-auth__orb--1"></span><span class="st-auth__orb st-auth__orb--2"></span></div>' . "\n";
    echo '<div class="st-auth__center">' . "\n";
    echo '<div class="st-auth__form">' . "\n";
    echo '<h1 class="st-auth__title">' . vs_e($headTitle) . '</h1>' . "\n";
    if ($headSub !== '') {
        echo '<p class="st-auth__sub">' . vs_e($headSub) . '</p>' . "\n";
    }
}

/**
 * @return void
 */
function vs_slate_auth_shell_end()
{
    echo '</div></div></div>' . "\n";
}

/**
 * @param string $inlineJs
 * @return void
 */
function vs_theme_auth_foot($inlineJs = '')
{
    $base = vs_base_url();
    if ($inlineJs !== '') {
        echo '<script>' . $inlineJs . '</script>' . "\n";
    }
    echo '<script src="' . vs_e($base) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    $authJs = ThemeManager::authScriptHref();
    if ($authJs !== '') {
        echo '<script src="' . vs_e($authJs) . '"></script>' . "\n";
    }
    echo '</body></html>';
}
