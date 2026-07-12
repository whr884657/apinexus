<?php
/**
 * 青绿平台 · 认证页布局
 */

/** @var string */
$GLOBALS['vs_slate_auth_layout_mode'] = 'center';

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
 * 认证页 shell
 *
 * @param string $headTitle
 * @param string $headSub
 * @param array  $options layout: center|login-right（仅登录页桌面端右侧表单）
 * @return void
 */
function vs_slate_auth_shell_start($headTitle, $headSub = '', array $options = array())
{
    $layout = isset($options['layout']) ? (string) $options['layout'] : 'center';
    if ($layout !== 'login-right') {
        $layout = 'center';
    }
    $GLOBALS['vs_slate_auth_layout_mode'] = $layout;

    $rootClass = 'st-auth' . ($layout === 'login-right' ? ' st-auth--login-right' : '');

    echo '<div class="' . $rootClass . '">' . "\n";
    echo '<div class="st-auth__bg" aria-hidden="true"><span class="st-auth__orb st-auth__orb--1"></span><span class="st-auth__orb st-auth__orb--2"></span></div>' . "\n";

    if ($layout === 'login-right') {
        echo '<div class="st-auth__stage">' . "\n";
        echo '<aside class="st-auth__visual" aria-hidden="true" data-st-login-visual>' . "\n";
        echo '<div class="st-auth__visual-inner">' . "\n";
        echo '<div class="st-auth__mesh"></div>' . "\n";
        echo '<div class="st-auth__ring st-auth__ring--1"></div>' . "\n";
        echo '<div class="st-auth__ring st-auth__ring--2"></div>' . "\n";
        echo '<div class="st-auth__float st-auth__float--1"></div>' . "\n";
        echo '<div class="st-auth__float st-auth__float--2"></div>' . "\n";
        echo '<div class="st-auth__float st-auth__float--3"></div>' . "\n";
        echo '<div class="st-auth__brand">' . vs_e(SiteContext::siteName()) . '</div>' . "\n";
        echo '</div></aside>' . "\n";
    }

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
    $layout = isset($GLOBALS['vs_slate_auth_layout_mode']) ? $GLOBALS['vs_slate_auth_layout_mode'] : 'center';
    echo '</div></div>';
    if ($layout === 'login-right') {
        echo '</div>';
    }
    echo '</div>' . "\n";
    $GLOBALS['vs_slate_auth_layout_mode'] = 'center';
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
