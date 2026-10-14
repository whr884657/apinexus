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
 * 用户中心顶栏铃铛（站内公告红点/自动弹窗；列表面板筹备中，不可查看）
 *
 * @return void
 */
function vs_user_notify_markup()
{
    global $vsBase;

    $base = isset($vsBase) ? (string) $vsBase : vs_site_base_path();
    $userId = (int) UserAuth::id();
    $items = array();
    $popups = array();
    if ($userId > 0 && class_exists('ContentManager')
        && ContentManager::tableReady() && ContentManager::hasAudModeColumn()) {
        // items 仅供控制台；铃铛列表不再渲染
        $items = ContentManager::userListMessages($userId, 20);
        $popups = ContentManager::userListPopups($userId);
    }
    $total = count($items);
    $hasItems = $total > 0;

    echo '<div class="vs-topbar__notify"'
        . ' id="vsUserNotify"'
        . ' data-user-id="' . (int) $userId . '"'
        . ' data-has-items="' . ($hasItems ? '1' : '0') . '"'
        . ' data-has-pending="0"'
        . ' data-total="' . (int) $total . '"'
        . ' data-endpoint="' . vs_e($base . '/user/notify') . '">' . "\n";
    echo '<button type="button" class="vs-notify-btn" id="vsUserNotifyBtn"'
        . ' aria-label="通知"'
        . ' aria-expanded="false"'
        . ' aria-controls="vsUserNotifyPanel"'
        . ' title="通知">' . "\n";
    echo '<i class="vs-icon vs-icon--bell" aria-hidden="true"></i>' . "\n";
    echo '<span class="vs-notify-btn__dot" id="vsUserNotifyDot" hidden aria-hidden="true"></span>' . "\n";
    echo '<span class="vs-notify-btn__count" id="vsUserNotifyCount" hidden>0</span>' . "\n";
    echo '</button>' . "\n";
    echo '<div class="vs-notify-panel" id="vsUserNotifyPanel" role="dialog" aria-label="通知" hidden>' . "\n";
    echo '<div class="vs-notify-panel__head">' . "\n";
    echo '<strong class="vs-notify-panel__title">通知</strong>' . "\n";
    echo '<span class="vs-notify-panel__meta" id="vsUserNotifyMeta">筹备中</span>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="vs-notify-panel__list" id="vsUserNotifyList" hidden></div>' . "\n";
    echo '<div class="vs-notify-panel__empty vs-notify-panel__empty--soon" id="vsUserNotifyEmpty">' . "\n";
    echo '<p class="vs-notify-panel__soon-title">功能筹备中</p>' . "\n";
    echo '<p class="vs-notify-panel__soon-desc">通知中心后续开放。站内公告请在控制台「近期站内公告」查看，或等待自动弹窗。</p>' . "\n";
    echo '</div>' . "\n";
    echo '</div>' . "\n";
    echo '<script type="application/json" id="vsUserNotifyBoot">' . vs_json_script(array(
        'user_id'   => (int) $userId,
        'items'     => $items,
        'popups'    => $popups,
        'has_items' => $hasItems ? 1 : 0,
    )) . '</script>' . "\n";
    echo '</div>' . "\n";
}

/**
 * 控制台「近期站内公告」面板 HTML
 *
 * @param array $messages userListMessages 结果
 * @return void
 */
function vs_user_dash_messages_panel(array $messages = array())
{
    echo '<section class="vs-panel uc-dash__panel uc-dash__msg uc-motion" aria-label="近期站内公告">' . "\n";
    echo '<div class="vs-panel__header uc-dash__panel-head">' . "\n";
    echo '<h2 class="vs-panel__title">近期站内公告</h2>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="vs-panel__body" id="ucDashMsgBody">' . "\n";
    if (empty($messages)) {
        echo '<p class="uc-dash__empty">暂无站内公告</p>' . "\n";
    } else {
        echo '<div class="uc-dash__msg-list" role="list">' . "\n";
        foreach ($messages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $title = isset($row['title']) ? (string) $row['title'] : '';
            $time = isset($row['createtime']) ? (string) $row['createtime'] : '';
            if ($time !== '' && strlen($time) >= 16) {
                $time = substr($time, 0, 16);
            }
            $pinned = !empty($row['ispinned']);
            echo '<button type="button" class="uc-dash__msg-row" role="listitem" data-msg-id="'
                . (int) $id . '" title="' . vs_e($title) . '">' . "\n";
            echo '<span class="uc-dash__msg-title">' . vs_e($title !== '' ? $title : '（无标题）') . '</span>' . "\n";
            if ($pinned) {
                echo '<span class="uc-dash__msg-pin">置顶</span>' . "\n";
            }
            echo '<span class="uc-dash__msg-time">' . vs_e($time !== '' ? $time : '—') . '</span>' . "\n";
            echo '</button>' . "\n";
        }
        echo '</div>' . "\n";
    }
    echo '</div>' . "\n";
    echo '</section>' . "\n";
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
    $body .= '<p>普通用户可在用户中心生成密钥并调用平台全部公开接口；如需发布自己的接口，请前往「账号设置 → 开发者申请」提交申请，或联系管理员调整身份。</p>';

    vs_render_notice('warning', '权限不足', $body, array('allow_html' => true));
    echo '</div>';
    vs_user_layout_end();
    exit;
}
