<?php
/**
 * 青绿平台 · 认证页布局
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
 * 认证页双栏 shell 开始
 *
 * @param string $headTitle
 * @param string $headSub
 * @param string $visualTip
 * @return void
 */
function vs_slate_auth_shell_start($headTitle, $headSub, $visualTip = '')
{
    $siteName = SiteContext::siteName();
    if ($visualTip === '') {
        $visualTip = '安全、稳定、简洁的账号服务';
    }

    echo '<div class="st-auth">' . "\n";
    echo '<div class="st-auth__shell">' . "\n";
    echo '<div class="st-auth__visual" aria-hidden="true">' . "\n";
    echo '<div class="st-auth__visual-blob st-auth__visual-blob--1"></div>' . "\n";
    echo '<div class="st-auth__visual-blob st-auth__visual-blob--2"></div>' . "\n";
    echo '<div class="st-auth__visual-brand">' . "\n";
    vs_theme_site_logo('st-auth__logo', 'st-auth__logo-fb');
    echo '<strong>' . vs_e($siteName) . '</strong>' . "\n";
    echo '<p>' . vs_e($visualTip) . '</p>' . "\n";
    echo '</div></div>' . "\n";
    echo '<div class="st-auth__panel">' . "\n";
    echo '<div class="st-auth__card">' . "\n";
    echo '<header class="st-auth__head">' . "\n";
    vs_theme_site_logo('st-auth__logo st-auth__logo--sm', 'st-auth__logo-fb');
    echo '<h1>' . vs_e($headTitle) . '</h1>' . "\n";
    echo '<p>' . vs_e($headSub) . '</p>' . "\n";
    echo '</header>' . "\n";
}

/**
 * 认证页 shell 结束
 *
 * @return void
 */
function vs_slate_auth_shell_end()
{
    echo '</div>' . "\n";
    echo '<p class="st-auth__copy">&copy; ' . vs_e(date('Y')) . ' ' . vs_e(SiteContext::siteName()) . '</p>' . "\n";
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
