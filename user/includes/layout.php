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
