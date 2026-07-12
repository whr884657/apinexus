<?php
/**
 * 青绿平台 · 用户中心（st-dash 顶栏导航，与 default 侧边栏完全不同）
 */
if (!defined('VS_THEME_RENDER') && !function_exists('vs_theme_user_layout_start')) {
    // 由 ThemeManager 加载
}

/**
 * @param string $pageTitle
 * @param string $activeMenu
 * @return void
 */
function vs_theme_user_layout_start($pageTitle, $activeMenu = '')
{
    global $vsBase, $vsUser, $vsSiteName;

    $base = $vsBase;
    $siteName = $vsSiteName;
    $user = $vsUser;
    $favicon = SiteContext::siteFavicon();
    $menuGroups = ThemeManager::userMenuGroups();
    $avatarUrl = $user ? UserAvatar::resolve($user) : '';
    $logoutUrl = $base . '/user/login?action=logout';

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    echo '<title>' . vs_e(vs_page_title($pageTitle, $siteName)) . '</title>' . "\n";
    if ($favicon !== '') {
        echo '<link rel="icon" href="' . vs_e(vs_favicon_href($favicon)) . '">' . "\n";
    }
    vs_theme_bg_preload_script();
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/common.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/modal.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/icons.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/theme-picker.css?v=' . VS_VERSION . '">' . "\n";
    foreach (ThemeManager::userStylesheetHrefs() as $href) {
        echo '<link rel="stylesheet" href="' . vs_e($href) . '">' . "\n";
    }
    echo '</head>' . "\n";
    echo '<body class="st-dash-body vs-admin-body">' . "\n";
    echo '<div class="st-dash" id="stDashShell">' . "\n";

    echo '<header class="st-dash-header">' . "\n";
    echo '<a href="' . vs_e($base) . '/" class="st-dash-brand">' . "\n";
    vs_render_site_logo('st-dash-brand__logo');
    echo '<span class="st-dash-brand__name">' . vs_e($siteName) . '</span></a>' . "\n";

    echo '<nav class="st-dash-nav" aria-label="用户中心导航">' . "\n";
    foreach ($menuGroups as $group) {
        $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
        echo '<a href="' . vs_e($group['url']) . '" class="st-dash-nav__link' . $linkActive . '">';
        echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
        echo '<span>' . vs_e($group['title']) . '</span></a>' . "\n";
    }
    echo '</nav>' . "\n";

    echo '<div class="st-dash-actions">' . "\n";
    echo '<div class="st-dash-theme" id="vsThemePickerMount"></div>' . "\n";
    if ($user) {
        echo '<a href="' . vs_e($base) . '/user/account" class="st-dash-user" title="账号设置">';
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" width="32" height="32">';
        echo '<span>' . vs_e($user['username']) . '</span></a>' . "\n";
    }
    echo '<a href="' . vs_e($logoutUrl) . '" class="st-dash-exit">退出</a>' . "\n";
    echo '<button type="button" class="st-dash-menu-btn" id="stDashMenuBtn" aria-label="打开菜单" aria-expanded="false">';
    echo '<span></span><span></span><span></span></button>' . "\n";
    echo '</div></header>' . "\n";

    echo '<div class="st-dash-mask" id="stDashMask" hidden></div>' . "\n";
    echo '<aside class="st-dash-sheet" id="stDashSheet" aria-label="移动端菜单" hidden>' . "\n";
    echo '<nav class="st-dash-sheet__nav">' . "\n";
    foreach ($menuGroups as $group) {
        $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
        echo '<a href="' . vs_e($group['url']) . '" class="st-dash-sheet__link' . $linkActive . '">';
        echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
        echo '<span>' . vs_e($group['title']) . '</span></a>' . "\n";
    }
    echo '</nav></aside>' . "\n";

    echo '<main class="st-dash-main">' . "\n";
    echo '<h1 class="st-dash-heading">' . vs_e($pageTitle) . '</h1>' . "\n";
    echo '<div class="st-dash-content">' . "\n";
}

/**
 * @param array $extraScripts
 * @return void
 */
function vs_theme_user_layout_end(array $extraScripts = array())
{
    global $vsBase;

    echo '</div></main></div>' . "\n";

    echo '<script>window.VS_BASE_URL = ' . json_encode($vsBase) . ';</script>' . "\n";
    echo '<script>window.VS_CSRF_TOKEN = ' . json_encode(AuthSecurity::csrfToken()) . ';</script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/theme-picker.js?v=' . VS_VERSION . '"></script>' . "\n";
    $userJs = ThemeManager::userScriptHref();
    if ($userJs !== '') {
        echo '<script src="' . vs_e($userJs) . '"></script>' . "\n";
    }
    foreach ($extraScripts as $js) {
        echo '<script src="' . vs_e($vsBase) . '/assets/js/' . vs_e($js) . '?v=' . VS_VERSION . '"></script>' . "\n";
    }
    echo '</body></html>';
}
