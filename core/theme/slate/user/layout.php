<?php
/**
 * 青绿平台 · 用户中心（无侧栏，右下角 FAB 向上弹出导航）
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
    $logoutUrl = $base . '/user/login?action=logout';
    $GLOBALS['stUcActiveMenu'] = $activeMenu;

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    echo '<title>' . vs_e(vs_page_title($pageTitle, $siteName)) . '</title>' . "\n";
    if ($favicon !== '') {
        echo '<link rel="icon" href="' . vs_e(vs_favicon_href($favicon)) . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/common.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/modal.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/icons.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/admin.css?v=' . VS_VERSION . '">' . "\n";
    foreach (ThemeManager::userStylesheetHrefs() as $href) {
        echo '<link rel="stylesheet" href="' . vs_e($href) . '">' . "\n";
    }
    echo '</head>' . "\n";
    echo '<body class="vs-body st-uc-body" data-theme-picker="off">' . "\n";
    echo '<div class="st-uc-shell" id="stUcShell">' . "\n";
    echo '<div class="st-uc-main">' . "\n";
    echo '<header class="st-uc-topbar">' . "\n";
    echo '<div class="st-uc-topbar__left">' . "\n";
    echo '<a href="' . vs_e($base) . '/" class="st-uc-topbar__brand">' . "\n";
    vs_render_site_logo('st-uc-topbar__logo');
    echo '<span>' . vs_e($siteName) . '</span></a>' . "\n";
    echo '<span class="st-uc-topbar__sep">·</span>' . "\n";
    echo '<span class="st-uc-topbar__title">用户中心</span>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="st-uc-topbar__right">' . "\n";
    if ($user) {
        $avatarUrl = UserAvatar::resolve($user);
        echo '<a href="' . vs_e($base) . '/user/account" class="st-uc-topbar__avatar" title="账号设置">';
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" width="32" height="32"></a>' . "\n";
    }
    echo '</div></header>' . "\n";
    echo '<main class="st-uc-content">' . "\n";
    echo '<div class="st-uc-content__head">' . "\n";
    echo '<h1 class="st-uc-content__title">' . vs_e($pageTitle) . '</h1>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="st-uc-content__body">' . "\n";
}

/**
 * @param array $extraScripts
 * @return void
 */
function vs_theme_user_layout_end(array $extraScripts = array())
{
    global $vsBase;

    $menuGroups = ThemeManager::userMenuGroups();
    $logoutUrl = $vsBase . '/user/login?action=logout';
    $activeMenu = isset($GLOBALS['stUcActiveMenu']) ? (string) $GLOBALS['stUcActiveMenu'] : '';

    echo '</div></main></div></div>' . "\n";

    echo '<div class="st-uc-mask" id="stUcMask" hidden></div>' . "\n";
    echo '<div class="st-uc-fab-wrap" id="stUcFabWrap">' . "\n";
    echo '<nav class="st-uc-pop" id="stUcPop" aria-label="用户中心导航" hidden>' . "\n";

    foreach ($menuGroups as $group) {
        $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
        echo '<a href="' . vs_e($group['url']) . '" class="st-uc-pop__link' . $linkActive . '">';
        echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
        echo '<span>' . vs_e($group['title']) . '</span></a>' . "\n";
    }

    echo '<a href="' . vs_e($logoutUrl) . '" class="st-uc-pop__link st-uc-pop__link--exit">';
    echo '<i class="vs-icon vs-icon--logout"></i>';
    echo '<span>退出登录</span></a>' . "\n";
    echo '</nav>' . "\n";
    echo '<button type="button" class="st-uc-fab" id="stUcFab" aria-label="打开导航菜单" aria-expanded="false" aria-controls="stUcPop">';
    echo '<span class="st-uc-fab__lines" aria-hidden="true"><i></i><i></i><i></i></span>';
    echo '</button></div>' . "\n";

    echo '<script>window.VS_BASE_URL = ' . json_encode($vsBase) . ';</script>' . "\n";
    echo '<script>window.VS_CSRF_TOKEN = ' . json_encode(AuthSecurity::csrfToken()) . ';</script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    $userJs = ThemeManager::userScriptHref();
    if ($userJs !== '') {
        echo '<script src="' . vs_e($userJs) . '"></script>' . "\n";
    }
    foreach ($extraScripts as $js) {
        echo '<script src="' . vs_e($vsBase) . '/assets/js/' . vs_e($js) . '?v=' . VS_VERSION . '"></script>' . "\n";
    }
    echo '</body></html>';
}
