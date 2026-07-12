<?php
/**
 * 青绿平台 · 认证页布局（双栏：左装饰 / 右表单，结构对齐 default 主题）
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
 * 双栏认证页开始（左装饰区 + 右表单区）
 *
 * @return void
 */
function vs_slate_auth_page_start()
{
    $siteName = SiteContext::siteName();
    echo '<div class="st-auth-page">' . "\n";
    echo '<aside class="st-auth-page__left" aria-hidden="true">' . "\n";
    echo '<div class="st-auth-page__left-bg"></div>' . "\n";
    echo '<div class="st-auth-page__left-content">' . "\n";
    $initial = $siteName !== '' ? $siteName : 'A';
    if (function_exists('mb_substr')) {
        $initial = mb_substr($initial, 0, 1, 'UTF-8');
    } else {
        $initial = substr($initial, 0, 1);
    }
    echo '<div class="st-auth-page__logo">' . vs_e($initial) . '</div>' . "\n";
    echo '<p class="st-auth-page__site">' . vs_e($siteName) . '</p>' . "\n";
    echo '<p class="st-auth-page__tagline">安全 · 简洁 · 高效</p>' . "\n";
    echo '</div></aside>' . "\n";
    echo '<main class="st-auth-page__right">' . "\n";
    echo '<div class="st-auth-box">' . "\n";
}

/**
 * 表单区标题
 *
 * @param string $title
 * @param string $subtitle
 * @return void
 */
function vs_slate_auth_header($title, $subtitle = '')
{
    echo '<header class="st-auth-box__header">' . "\n";
    echo '<h1 class="st-auth-box__title">' . vs_e($title) . '</h1>' . "\n";
    if ($subtitle !== '') {
        echo '<p class="st-auth-box__sub">' . vs_e($subtitle) . '</p>' . "\n";
    }
    echo '</header>' . "\n";
}

/**
 * @return void
 */
function vs_slate_auth_page_end()
{
    echo '</div></main></div>' . "\n";
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
