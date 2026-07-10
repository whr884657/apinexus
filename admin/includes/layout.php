<?php
/**
 * 文件：admin/includes/layout.php
 * 作用：misc-api 后台自定义布局（分组侧边栏）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

/**
 * 后台菜单（分组）
 *
 * @return array
 */
function vs_admin_menu_groups()
{
    return array(
        array(
            'id'    => 'dashboard',
            'title' => '控制台',
            'icon'  => 'dashboard',
            'url'   => '/admin/index.php',
        ),
        array(
            'id'    => 'users',
            'title' => '用户管理',
            'icon'  => 'users',
            'url'   => '/admin/users.php',
        ),
        array(
            'id'       => 'system',
            'title'    => '系统',
            'icon'     => 'setting',
            'children' => array(
                array('id' => 'account', 'title' => '账号设置', 'url' => '/admin/account.php'),
                array('id' => 'settings', 'title' => '系统设置', 'url' => '/admin/settings.php'),
                array('id' => 'upgrade', 'title' => '系统升级', 'url' => '/admin/upgrade.php'),
                array('id' => 'about', 'title' => '关于', 'url' => '/admin/about.php'),
            ),
        ),
    );
}

/**
 * 当前菜单是否匹配分组或子项
 *
 * @param array  $group
 * @param string $activeMenu
 * @return bool
 */
function vs_admin_group_is_active(array $group, $activeMenu)
{
    if (isset($group['url']) && isset($group['id']) && $group['id'] === $activeMenu) {
        return true;
    }
    if (!empty($group['children'])) {
        foreach ($group['children'] as $child) {
            if ($child['id'] === $activeMenu) {
                return true;
            }
        }
    }
    return false;
}

/**
 * 渲染后台页面头部
 *
 * @param string $pageTitle
 * @param string $activeMenu
 * @return void
 */
function vs_admin_layout_start($pageTitle, $activeMenu = '')
{
    global $vsBase, $vsAdmin, $vsSiteName;

    $base = $vsBase;
    $siteName = $vsSiteName;
    $admin = $vsAdmin;
    $favicon = SiteContext::siteFavicon();
    $menuGroups = vs_admin_menu_groups();
    $showUpdateSidebarBadge = false;
    if (InstallChecker::isInstalled()) {
        $sidebarUpdateCheck = Updater::checkForUpdate();
        if (!empty($sidebarUpdateCheck['update_available'])) {
            $dismissedVer = isset($_SESSION['vs_update_dismiss']) ? (string) $_SESSION['vs_update_dismiss'] : '';
            if ($dismissedVer === (string) $sidebarUpdateCheck['remote_version']) {
                $showUpdateSidebarBadge = true;
            }
        }
    }

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
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/admin.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/upload-queue.css?v=' . VS_VERSION . '">' . "\n";
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
        $hasChildren = !empty($group['children']);
        $groupActive = vs_admin_group_is_active($group, $activeMenu);
        $isOpen = $hasChildren && $groupActive;

        if ($hasChildren) {
            $badgeOnGroup = ($group['id'] === 'system' && $showUpdateSidebarBadge && !$isOpen);
            echo '<div class="vs-sidebar__group' . ($isOpen ? ' is-open' : '') . '" data-group="' . vs_e($group['id']) . '">' . "\n";
            echo '<button type="button" class="vs-sidebar__group-btn' . ($groupActive ? ' is-active' : '') . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '">';
            echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
            echo '<span class="vs-sidebar__text">';
            echo vs_e($group['title']);
            if ($group['id'] === 'system') {
                echo '<span class="vs-sidebar__badge" id="vsUpdateBadgeGroup" aria-hidden="true"';
                echo $badgeOnGroup ? '>' : ' hidden>';
                echo '</span>';
            }
            echo '</span>';
            echo '<i class="vs-icon vs-icon--chevron"></i>';
            echo '</button>' . "\n";
            echo '<div class="vs-sidebar__sub">' . "\n";
            foreach ($group['children'] as $child) {
                $childActive = ($child['id'] === $activeMenu) ? ' is-active' : '';
                $badgeOnUpgrade = ($group['id'] === 'system' && $child['id'] === 'upgrade' && $showUpdateSidebarBadge && $isOpen);
                echo '<a href="' . vs_e($base . $child['url']) . '" class="vs-sidebar__sublink' . $childActive . '">';
                echo '<span class="vs-sidebar__text">' . vs_e($child['title']) . '</span>';
                if ($group['id'] === 'system' && $child['id'] === 'upgrade') {
                    echo '<span class="vs-sidebar__badge" id="vsUpdateBadgeUpgrade" aria-hidden="true"';
                    echo $badgeOnUpgrade ? '>' : ' hidden>';
                    echo '</span>';
                }
                echo '</a>' . "\n";
            }
            echo '</div></div>' . "\n";
        } else {
            $linkActive = ($group['id'] === $activeMenu) ? ' is-active' : '';
            echo '<a href="' . vs_e($base . $group['url']) . '" class="vs-sidebar__link' . $linkActive . '">';
            echo '<i class="vs-icon vs-icon--' . vs_e($group['icon']) . '"></i>';
            echo '<span class="vs-sidebar__text">' . vs_e($group['title']) . '</span>';
            echo '</a>' . "\n";
        }
    }

    echo '</nav>' . "\n";

    $logoutUrl = $base . '/admin/login.php?action=logout';
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
    echo '<span class="vs-topbar__title">' . vs_e($siteName) . '</span>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="vs-topbar__right">' . "\n";
    echo '<div class="vs-topbar__theme" id="vsThemePickerMount"></div>' . "\n";
    if ($admin) {
        $avatarUrl = UserAvatar::resolve($admin);
        echo '<a href="' . vs_e($base) . '/admin/account.php" class="vs-topbar__avatar-link" title="账号设置">' . "\n";
        echo '<img src="' . vs_e($avatarUrl) . '" alt="" class="vs-topbar__avatar" width="32" height="32">' . "\n";
        echo '</a>' . "\n";
    }
    echo '<a href="' . vs_e($base) . '/admin/login.php?action=logout" class="vs-topbar__logout">退出</a>' . "\n";
    echo '</div>' . "\n";
    echo '</header>' . "\n";

    echo '<main class="vs-content">' . "\n";
    echo '<div class="vs-content__head">' . "\n";
    echo '<h1 class="vs-content__title">' . vs_e($pageTitle) . '</h1>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="vs-content__body">' . "\n";
}

/**
 * 渲染后台页面底部
 *
 * @param array $extraScripts
 * @return void
 */
function vs_admin_layout_end(array $extraScripts = array())
{
    global $vsBase;

    echo '</div>' . "\n";
    echo '</main>' . "\n";
    echo '</div>' . "\n";
    echo '</div>' . "\n";

    vs_render_modal_shell();

    echo '<script>window.VS_BASE_URL = ' . json_encode($vsBase) . ';</script>' . "\n";
    echo '<script>window.VS_CSRF_TOKEN = ' . json_encode(AuthSecurity::csrfToken()) . ';</script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/modal.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/vs-update.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/theme-picker.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/admin.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/upload-queue.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($vsBase) . '/assets/js/update-check.js?v=' . VS_VERSION . '"></script>' . "\n";
    foreach ($extraScripts as $js) {
        echo '<script src="' . vs_e($vsBase) . '/assets/js/' . vs_e($js) . '?v=' . VS_VERSION . '"></script>' . "\n";
    }
    echo '</body></html>';
}

/**
 * 占位页面（功能开发中）
 *
 * @param string $pageTitle
 * @param string $activeMenu
 * @return void
 */
function vs_admin_stub_page($pageTitle, $activeMenu)
{
    vs_admin_layout_start($pageTitle, $activeMenu);
    echo '<div class="vs-panel">';
    echo '<p class="vs-panel__desc">功能开发中，敬请期待。</p>';
    echo '</div>';
    vs_admin_layout_end();
}

/**
 * 系统设置折叠板块开始
 *
 * @param string $id
 * @param string $title
 * @param string $desc
 * @param bool   $open
 * @param bool   $nested 嵌套折叠（储存配置子板块）
 * @return void
 */
function vs_admin_accordion_start($id, $title, $desc = '', $open = false, $nested = false)
{
    $openClass = $open ? ' is-open' : '';
    $nestedClass = $nested ? ' vs-accordion--nested' : '';
    $aria = $open ? 'true' : 'false';

    echo '<section class="vs-panel vs-accordion' . $nestedClass . $openClass . '" id="' . vs_e($id) . '" data-accordion>' . "\n";
    echo '<button type="button" class="vs-accordion__trigger" aria-expanded="' . $aria . '">' . "\n";
    echo '<div class="vs-accordion__head">' . "\n";
    echo '<h3 class="vs-accordion__title">' . vs_e($title) . '</h3>' . "\n";
    if ($desc !== '') {
        echo '<p class="vs-accordion__desc">' . vs_e($desc) . '</p>' . "\n";
    }
    echo '</div><i class="vs-icon vs-icon--chevron"></i></button>' . "\n";
    echo '<div class="vs-accordion__body">' . "\n";
}

/**
 * 系统设置折叠板块结束
 *
 * @return void
 */
function vs_admin_accordion_end()
{
    echo '</div></section>' . "\n";
}
