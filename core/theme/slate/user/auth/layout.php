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
