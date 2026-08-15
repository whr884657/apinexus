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
 * @param string $headerActions
 * @return void
 */
function vs_user_layout_start($pageTitle, $activeMenu = '', $headerActions = '')
{
    ThemeManager::renderUserLayoutStart($pageTitle, $activeMenu, $headerActions);
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
 * 渲染当前主题用户中心业务页（视图在主题包 user/pages/）
 *
 * @param string $pageKey
 * @param string $pageTitle
 * @param string $activeMenu
 * @param array  $pageData
 * @param string $headerActions
 * @param array  $extraScripts
 * @return void
 */
function vs_user_render_page(
    $pageKey,
    $pageTitle,
    $activeMenu = '',
    array $pageData = array(),
    $headerActions = '',
    array $extraScripts = array()
) {
    ThemeManager::renderUserPage($pageKey, $pageTitle, $activeMenu, $pageData, $headerActions, $extraScripts);
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

/**
 * 用户中心统一图标刷新按钮（与管理员后台同款）
 *
 * @param string $id 按钮 id（如 ucDashRefreshBtn）
 * @param string $extraClass 额外 class（可选）
 * @return string
 */
function vs_user_refresh_btn_html($id, $extraClass = '')
{
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id);
    if ($id === '') {
        $id = 'vsRefreshBtn';
    }
    $extra = trim((string) $extraClass);
    $class = 'vs-btn vs-btn--outline vs-btn--icon vs-refresh-btn';
    if ($extra !== '') {
        $class .= ' ' . $extra;
    }
    return '<button type="button" class="' . vs_e($class) . '" id="' . vs_e($id) . '" title="刷新" aria-label="刷新">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<polyline points="23 4 23 10 17 10"></polyline>'
        . '<polyline points="1 20 1 14 7 14"></polyline>'
        . '<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>'
        . '</svg></button>';
}

/**
 * 要求当前用户为开发者，否则输出权限不足页并终止
 *
 * @param string $pageTitle
 * @return void
 */
function vs_user_require_developer($pageTitle = '权限不足')
{
    if (UserRole::currentCanPublishApi()) {
        return;
    }

    global $vsUserProfile;

    vs_user_layout_start($pageTitle, '');
    echo '<div class="vs-panel">';

    $roleLabel = '普通用户';
    if (is_array($vsUserProfile) && isset($vsUserProfile['role_label'])) {
        $roleLabel = (string) $vsUserProfile['role_label'];
    }

    $body = '<p>您当前身份为 <strong>' . vs_e($roleLabel) . '</strong>，无权访问「API 管理」。</p>';
    $body .= '<p>普通用户可在用户中心生成密钥并调用平台全部公开接口；如需发布自己的接口，请联系管理员将账号调整为开发者，或在注册时选择开发者身份。</p>';

    vs_render_notice('warning', '权限不足', $body, array('allow_html' => true));
    echo '</div>';
    vs_user_layout_end();
    exit;
}
