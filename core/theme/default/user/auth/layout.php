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
    $toast = ThemeManager::shellUrl('toast.css', $themeId);
    if ($toast !== '') {
        echo '<link rel="stylesheet" href="' . vs_e($toast) . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . vs_e(ThemeManager::assetUrl($themeId, 'assets/auth.css')) . '?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e(ThemeManager::assetUrl($themeId, 'assets/auth-captcha.css')) . '?v=' . VS_VERSION . '">' . "\n";
    $pickerCss = ThemeManager::shellUrl('theme-picker.css', $themeId);
    if ($pickerCss !== '') {
        echo '<link rel="stylesheet" href="' . vs_e($pickerCss) . '">' . "\n";
    }
    $csrf = ThemeManager::shellUrl('auth-csrf.js', $themeId);
    if ($csrf !== '') {
        echo '<script src="' . vs_e($csrf) . '"></script>' . "\n";
    }
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
    foreach (array('common.js', 'theme-picker.js', 'auth-characters.js') as $shellJs) {
        $u = ThemeManager::shellUrl($shellJs, $themeId);
        if ($u !== '') {
            echo '<script src="' . vs_e($u) . '"></script>' . "\n";
        }
    }
    $authJs = ThemeManager::authScriptHref();
    if ($authJs !== '') {
        echo '<script src="' . vs_e($authJs) . '"></script>' . "\n";
    }
    if (function_exists('vs_captcha_js')) {
        vs_captcha_js(null);
    }
    echo '</body></html>';
}
