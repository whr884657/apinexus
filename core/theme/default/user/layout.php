<?php
/**
 * 默认主题 · 用户中心布局
 */
if (!defined('VS_THEME_RENDER') && !function_exists('vs_theme_user_layout_start')) {
    // 由 ThemeManager 或 user/includes/layout.php 加载
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
    echo '<body class="vs-body vs-admin-body">' . "\n";
    echo '<div class="vs-admin-shell" id="vsAdminShell">' . "\n";

    echo '<aside class="vs-sidebar" id="vsSidebar">' . "\n";
    echo '<div class="vs-sidebar__head">' . "\n";
    vs_render_site_logo('vs-sidebar__logo');
    echo '<span class="vs-sidebar__name">' . vs_e($siteName) . '</span>' . "\n";
    echo '</div>' . "\n";
    echo '<nav class="vs-sidebar__nav">' . "\n";

    foreach ($menuGroups as $group) {
        $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
        echo '<a href="' . vs_e($group['url']) . '" class="vs-sidebar__link' . $linkActive . '">';
        echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
        echo '<span class="vs-sidebar__text">' . vs_e($group['title']) . '</span>';
        echo '</a>' . "\n";
    }

    echo '</nav>' . "\n";

    $logoutUrl = $base . '/user/login?action=logout';
    echo '<div class="vs-sidebar__foot">' . "\n";
    echo '<a href="' . vs_e($logoutUrl) . '" class="vs-sidebar__logout">' . "\n";
    echo '<i class="vs-icon vs-icon--logout"></i>' . "\n";
    echo '<span class="vs-sidebar__text">退出登录</span>' . "\n";
    echo '</a>' . "\n";
    echo '</div>' . "\n";
    echo '</aside>' . "\n";

    echo '<div class="vs-sidebar-mask" id="vsSidebarMask"></div>' . "\n";
    echo '<div class="vs-admin-main">' . "\n";
    echo '<header class="vs-topbar">' . "\n";
    echo '<div class="vs-topbar__left">' . "\n";
    echo '<button type="button" class="vs-topbar__toggle" id="vsSidebarToggle" aria-label="展开或收缩菜单">';
    echo '<i class="vs-icon vs-icon--menu"></i>';
    echo '</button>' . "\n";
    echo '<span class="vs-topbar__title">' . vs_e($siteName) . ' · 用户中心</span>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="vs-topbar__right">' . "\n";
    echo '<div class="vs-topbar__theme" id="vsThemePickerMount"></div>' . "\n";
    if ($user) {
        $avatarUrl = UserAvatar::resolve($user);
        echo '<a href="' . vs_e($base) . '/user/account" class="vs-topbar__avatar-link" title="账号设置">' . "\n";
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" class="vs-topbar__avatar" width="32" height="32">' . "\n";
        echo '</a>' . "\n";
    }
    echo '<a href="' . vs_e($logoutUrl) . '" class="vs-topbar__logout">退出</a>' . "\n";
    echo '</div>' . "\n";
    echo '</header>' . "\n";
    echo '<main class="vs-content">' . "\n";
    echo '<div class="vs-content__head">' . "\n";
    echo '<h1 class="vs-content__title">' . vs_e($pageTitle) . '</h1>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="vs-content__body">' . "\n";
}

/**
 * @param array $extraScripts
 * @return void
 */
function vs_theme_user_layout_end(array $extraScripts = array())
{
    global $vsBase;

    echo '</div>' . "\n";
    echo '</main>' . "\n";
    echo '</div>' . "\n";
    echo '</div>' . "\n";

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
