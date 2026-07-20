<?php
/**
 * 青绿平台 · 用户中心
 * 导航展开方式跟随主题设置 nav_expand_mode（前台 + 用户中心一致）
 */
if (!defined('VS_THEME_RENDER') && !function_exists('vs_theme_user_layout_start')) {
    // 由 ThemeManager 加载
}

/**
 * @return array{mode:string,use_fab:bool,tint:string,swatches:array<int,array{id:string,hex:string,label:string}>}
 */
function vs_slate_uc_nav_context()
{
    $mode = ThemeManager::themeSettingStr('nav_expand_mode', 'top_drawer');
    if ($mode !== 'fab_popup') {
        $mode = 'top_drawer';
    }
    $tint = ThemeManager::themeSettingStr('color_preset', 'green');
    $allowed = array('green', 'rose', 'orange', 'yellow', 'mint', 'sky', 'violet', 'pink', 'cyan');
    if (!in_array($tint, $allowed, true)) {
        $tint = 'green';
    }
    return array(
        'mode' => $mode,
        'use_fab' => ($mode === 'fab_popup'),
        'tint' => $tint,
        'swatches' => array(
            array('id' => 'green', 'hex' => '#eef6f1', 'label' => '浅绿'),
            array('id' => 'rose', 'hex' => '#fef2f2', 'label' => '浅玫瑰'),
            array('id' => 'orange', 'hex' => '#fff7ed', 'label' => '浅橙'),
            array('id' => 'yellow', 'hex' => '#fefce8', 'label' => '浅黄'),
            array('id' => 'mint', 'hex' => '#f0fdf4', 'label' => '薄荷'),
            array('id' => 'sky', 'hex' => '#eff6ff', 'label' => '天空蓝'),
            array('id' => 'violet', 'hex' => '#f5f3ff', 'label' => '浅紫'),
            array('id' => 'pink', 'hex' => '#fdf4ff', 'label' => '浅粉'),
            array('id' => 'cyan', 'hex' => '#ecfeff', 'label' => '浅青'),
        ),
    );
}

/**
 * @param string $pageTitle
 * @param string $activeMenu
 * @param string $headerActions
 * @return void
 */
function vs_theme_user_layout_start($pageTitle, $activeMenu = '', $headerActions = '')
{
    global $vsBase, $vsUser, $vsUserProfile, $vsSiteName;

    $base = $vsBase;
    $siteName = $vsSiteName;
    $userProfile = is_array($vsUserProfile) ? $vsUserProfile : FrontendUser::current();
    $favicon = SiteContext::siteFavicon();
    $nav = vs_slate_uc_nav_context();
    $GLOBALS['stUcActiveMenu'] = $activeMenu;
    $GLOBALS['stUcNavUseFab'] = $nav['use_fab'];

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    vs_render_seo_meta(vs_seo_defaults(array(
        'title'       => vs_page_title($pageTitle, $siteName),
        'description' => SiteContext::siteDescription() !== '' ? SiteContext::siteDescription() : ($siteName . ' 用户中心'),
        'robots'      => 'noindex,nofollow',
        'site_name'   => $siteName,
    )));
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
    echo '<body class="vs-body st-uc-body' . ($nav['use_fab'] ? ' st-uc-body--nav-fab' : ' st-uc-body--nav-drawer') . '" data-nav-mode="' . vs_e($nav['mode']) . '" data-st-default-tint="' . vs_e($nav['tint']) . '" data-theme-picker="off">' . "\n";
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

    echo '<div class="st-tint st-uc-tint" id="stTint">' . "\n";
    echo '<button type="button" class="st-tint__btn" id="stTintBtn" aria-label="选择主题色" aria-expanded="false" aria-controls="stTintPanel" title="主题色">' . "\n";
    echo '<svg class="theme-trigger-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
    echo '<path d="M12 3C7.03 3 3 7.03 3 12c0 2.2 1.02 4.16 2.62 5.45.42.33.68.84.68 1.38v1.17c0 .55.45 1 1 1h1.17c.54 0 1.05.26 1.38.68C10.84 22.98 12.8 24 15 24c4.97 0 9-4.03 9-9S19.97 3 15 3h-3z" stroke="#374151" stroke-width="1.5"/>';
    echo '<circle cx="8.5" cy="10.5" r="1.5" fill="#ef4444"/><circle cx="12" cy="8" r="1.5" fill="#3b82f6"/>';
    echo '<circle cx="15.5" cy="11" r="1.5" fill="#22c55e"/><circle cx="13" cy="15" r="1.5" fill="#eab308"/>';
    echo '</svg></button>' . "\n";
    echo '<div class="st-tint__panel" id="stTintPanel" hidden role="listbox" aria-label="浅色主题">' . "\n";
    foreach ($nav['swatches'] as $sw) {
        if ($sw['id'] === 'green') {
            continue;
        }
        echo '<button type="button" class="st-tint__swatch" data-tint="' . vs_e($sw['id']) . '" style="--swatch:' . vs_e($sw['hex']) . '" title="' . vs_e($sw['label']) . '" aria-label="' . vs_e($sw['label']) . '"></button>' . "\n";
    }
    echo '</div></div>' . "\n";

    if ($userProfile) {
        $avatarUrl = $userProfile['avatar'];
        echo '<a href="' . vs_e($base) . '/user/account" class="st-uc-topbar__avatar" title="账号设置">';
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" width="32" height="32"></a>' . "\n";
    }

    if (!$nav['use_fab']) {
        echo '<button type="button" class="st-uc-menu-btn" id="stUcMenuBtn" aria-label="打开导航菜单" aria-expanded="false" aria-controls="stUcDrawer">';
        echo '<span></span><span></span><span></span></button>' . "\n";
    }

    echo '</div></header>' . "\n";
    echo '<main class="st-uc-content">' . "\n";
    echo '<div class="st-uc-content__head">' . "\n";
    echo '<h1 class="st-uc-content__title">' . vs_e($pageTitle) . '</h1>' . "\n";
    if ($headerActions !== '') {
        echo '<div class="vs-content__actions st-uc-content__actions">' . $headerActions . '</div>' . "\n";
    }
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
    $useFab = !empty($GLOBALS['stUcNavUseFab']);

    echo '</div></main></div></div>' . "\n";

    echo '<div class="st-uc-mask" id="stUcMask" hidden></div>' . "\n";

    if ($useFab) {
        echo '<div class="st-uc-fab-wrap" id="stUcFabWrap">' . "\n";
        echo '<nav class="st-uc-pop" id="stUcPop" aria-label="用户中心导航" hidden>' . "\n";
        foreach ($menuGroups as $group) {
            $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
            echo '<a href="' . vs_e($group['url']) . '" class="st-uc-pop__link' . $linkActive . '">';
            echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
            echo '<span>' . vs_e($group['title']) . '</span></a>' . "\n";
        }
        echo '<a href="' . vs_e($logoutUrl) . '" class="st-uc-pop__link st-uc-pop__link--exit">';
        echo '<i class="vs-icon vs-icon--logout"></i><span>退出登录</span></a>' . "\n";
        echo '</nav>' . "\n";
        echo '<button type="button" class="st-uc-fab" id="stUcFab" aria-label="打开导航菜单" aria-expanded="false" aria-controls="stUcPop">';
        echo '<span class="st-uc-fab__lines" aria-hidden="true"><i></i><i></i><i></i></span>';
        echo '</button></div>' . "\n";
    } else {
        echo '<aside class="st-uc-drawer" id="stUcDrawer" aria-label="用户中心导航" hidden>' . "\n";
        echo '<div class="st-uc-drawer__head"><span class="st-uc-drawer__title">导航菜单</span></div>' . "\n";
        echo '<nav class="st-uc-drawer__nav">' . "\n";
        foreach ($menuGroups as $group) {
            $linkActive = isset($group['id']) && $group['id'] === $activeMenu ? ' is-active' : '';
            echo '<a href="' . vs_e($group['url']) . '" class="st-uc-drawer__link' . $linkActive . '">';
            echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
            echo '<span>' . vs_e($group['title']) . '</span></a>' . "\n";
        }
        echo '<a href="' . vs_e($logoutUrl) . '" class="st-uc-drawer__link st-uc-drawer__link--exit">';
        echo '<i class="vs-icon vs-icon--logout"></i><span>退出登录</span></a>' . "\n";
        echo '</nav></aside>' . "\n";
    }

    if (function_exists('vs_render_modal_shell')) {
        vs_render_modal_shell();
    }

    echo '<script>window.VS_BASE_URL = ' . json_encode($vsBase) . ';</script>' . "\n";
    echo '<script>window.VS_CSRF_TOKEN = ' . json_encode(AuthSecurity::csrfToken()) . ';</script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/modal.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e(ThemeManager::assetUrl('slate', 'assets/st-tint.js')) . '?v=' . VS_VERSION . '" defer></script>' . "\n";
    $userJs = ThemeManager::userScriptHref();
    if ($userJs !== '') {
        echo '<script src="' . vs_e($userJs) . '"></script>' . "\n";
    }
    foreach ($extraScripts as $js) {
        if ($js === 'modal.js') {
            continue;
        }
        echo '<script src="' . vs_e($vsBase) . '/assets/js/' . vs_e($js) . '?v=' . VS_VERSION . '"></script>' . "\n";
    }
    echo '</body></html>';
}
