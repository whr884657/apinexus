<?php
/**
 * 默认主题 · 认证页布局（资源仅来自本主题包）
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
    vs_render_seo_meta(vs_seo_defaults(array(
        'title'       => vs_page_title($pageTitle, $siteName),
        'description' => vs_seo_site_description($siteName . ' 登录 / 注册'),
        'robots'      => 'noindex,nofollow',
        'site_name'   => $siteName,
    )));
    echo '<title>' . vs_e(vs_page_title($pageTitle, $siteName)) . '</title>' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">' . "\n";
    vs_render_site_icons($favicon, vs_seo_share_image());
    vs_theme_bg_preload_script();
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e(ThemeManager::assetUrl($themeId, 'assets/auth.css')) . '?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/theme-picker.css?v=' . VS_VERSION . '">' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/auth-csrf.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
}

/**
 * @param string $inlineJs
 * @return void
 */
function vs_theme_auth_foot($inlineJs = '')
{
    $base = vs_base_url();
    $themeId = ThemeManager::activeId();
    if ($inlineJs !== '') {
        echo '<script>' . $inlineJs . '</script>' . "\n";
    }
    echo '<script src="' . vs_e($base) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/theme-picker.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/auth-characters.js?v=' . VS_VERSION . '"></script>' . "\n";
    $authJs = ThemeManager::authScriptHref();
    if ($authJs !== '') {
        echo '<script src="' . vs_e($authJs) . '"></script>' . "\n";
    }
    echo '</body></html>';
}
