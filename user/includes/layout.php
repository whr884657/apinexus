<?php
/**
 * 用户中心布局入口（委托至当前前台主题包）
 */

/**
 * @return array
 */
function vs_user_menu_groups()
{
    return ThemeManager::userMenuGroups();
}

/**
 * @param array  $group
 * @param string $activeMenu
 * @return bool
 */
function vs_user_group_is_active(array $group, $activeMenu)
{
    return isset($group['id']) && $group['id'] === $activeMenu;
}

/**
 * @param string $pageTitle
 * @param string $activeMenu
 * @return void
 */
function vs_user_layout_start($pageTitle, $activeMenu = '')
{
    ThemeManager::renderUserLayoutStart($pageTitle, $activeMenu);
}

/**
 * @param array $extraScripts
 * @return void
 */
function vs_user_layout_end(array $extraScripts = array())
{
    ThemeManager::renderUserLayoutEnd($extraScripts);
}

/**
 * @param string $pageTitle
 * @param string $activeMenu
 * @return void
 */
function vs_user_stub_page($pageTitle, $activeMenu)
{
    vs_user_layout_start($pageTitle, $activeMenu);
    echo '<div class="vs-panel">';
    echo '<p class="vs-panel__desc">功能开发中，敬请期待。</p>';
    echo '</div>';
    vs_user_layout_end();
}
