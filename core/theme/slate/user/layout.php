<?php
/**
 * 青绿平台 · 用户中心布局（st-uc 独立视觉，与默认主题差异化）
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
    echo '<body class="st-uc-body">' . "\n";
    echo '<div class="st-uc-shell" id="stUcShell">' . "\n";

    echo '<aside class="st-uc-sidebar" id="stUcSidebar">' . "\n";
    echo '<div class="st-uc-sidebar__brand">' . "\n";
    vs_render_site_logo('st-uc-sidebar__logo');
    echo '<span class="st-uc-sidebar__name">' . vs_e($siteName) . '</span>' . "\n";
    echo '</div>' . "\n";
    echo '<nav class="st-uc-nav">' . "\n";
    foreach ($menuGroups as $group) {
        $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
        echo '<a href="' . vs_e($group['url']) . '" class="st-uc-nav__link' . $linkActive . '">';
        echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
        echo '<span>' . vs_e($group['title']) . '</span>';
        echo '</a>' . "\n";
    }
    echo '</nav>' . "\n";

    if ($user) {
        echo '<div class="st-uc-sidebar__user">' . "\n";
        echo '<a href="' . vs_e($base) . '/user/account" class="st-uc-user-chip">';
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" class="st-uc-user-chip__avatar" width="36" height="36">';
        echo '<span class="st-uc-user-chip__name">' . vs_e($user['username']) . '</span>';
        echo '</a>' . "\n";
        echo '<a href="' . vs_e($logoutUrl) . '" class="st-uc-sidebar__logout"><i class="vs-icon vs-icon--logout"></i><span>退出登录</span></a>' . "\n";
        echo '</div>' . "\n";
    }
    echo '</aside>' . "\n";

    echo '<div class="st-uc-mask" id="stUcMask"></div>' . "\n";
    echo '<div class="st-uc-main">' . "\n";
    echo '<header class="st-uc-topbar">' . "\n";
    echo '<div class="st-uc-topbar__left">' . "\n";
    echo '<button type="button" class="st-uc-topbar__toggle" id="stUcToggle" aria-label="展开或收缩菜单"><i class="vs-icon vs-icon--menu"></i></button>' . "\n";
    echo '<span class="st-uc-topbar__title">' . vs_e($pageTitle) . '</span>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="st-uc-topbar__right">' . "\n";
    echo '<div class="st-uc-topbar__theme" id="vsThemePickerMount"></div>' . "\n";
    if ($user) {
        echo '<a href="' . vs_e($base) . '/user/account" class="st-uc-topbar__user" title="账号设置">';
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" width="32" height="32">';
        echo '<span>用户中心</span></a>' . "\n";
    }
    echo '<a href="' . vs_e($logoutUrl) . '" class="st-uc-topbar__exit">退出</a>' . "\n";
    echo '</div></header>' . "\n";
    echo '<main class="st-uc-content">' . "\n";
    echo '<div class="st-uc-content__inner">' . "\n";
}

/**
 * @param array $extraScripts
 * @return void
 */
function vs_theme_user_layout_end(array $extraScripts = array())
{
    global $vsBase;

    echo '</div></main></div></div>' . "\n";

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
