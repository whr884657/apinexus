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
    vs_render_seo_meta(vs_seo_defaults(array(
        'title'       => vs_page_title($pageTitle, $siteName),
        'description' => vs_seo_site_description($siteName . ' 登录 / 注册'),
        'robots'      => 'noindex,nofollow',
        'site_name'   => $siteName,
    )));
    echo '<title>' . vs_e(vs_page_title($pageTitle, $siteName)) . '</title>' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">' . "\n";
    vs_render_site_icons($favicon, vs_seo_share_image());
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e(ThemeManager::assetUrl($themeId, 'assets/auth.css')) . '?v=' . VS_VERSION . '">' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/auth-csrf.js?v=' . VS_VERSION . '"></script>' . "\n";
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

/**
 * 主题二密码可见切换（眼睛图标，与默认主题同款）
 *
 * @return string
 */
function vs_slate_pw_toggle_html()
{
    return '<button type="button" class="st-auth__pw-toggle" data-st-pw-toggle aria-label="显示密码">'
        . '<svg class="st-auth__eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"></path>'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"></path>'
        . '</svg>'
        . '<svg class="st-auth__eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none" aria-hidden="true">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"></path>'
        . '</svg>'
        . '</button>';
}
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
