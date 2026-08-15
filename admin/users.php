<?php
/**
 * 文件：admin/users.php
 * 作用：用户管理（列表、OAuth 绑定、封禁/删除）
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

    if ($userId <= 0) {
        AjaxResponse::error('无效用户');
    }

    if ($action === 'ban') {
        $result = UserManager::setStatus($userId, 0);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('用户已封禁', array('action' => 'ban', 'user_id' => $userId, 'status' => 0));
    }

    if ($action === 'unban') {
        $result = UserManager::setStatus($userId, 1);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('用户已解封', array('action' => 'unban', 'user_id' => $userId, 'status' => 1));
    }

    if ($action === 'delete') {
        $result = UserManager::delete($userId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('用户已删除', array('action' => 'delete', 'user_id' => $userId));
    }

    if ($action === 'set_role') {
        $role = isset($_POST['role']) ? (string) $_POST['role'] : '';
        $role = UserRole::normalize($role);
        $result = UserManager::setRole($userId, $role);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success(
            '已设为' . UserRole::label($role),
            array('action' => 'set_role', 'user_id' => $userId, 'role' => $role, 'role_label' => UserRole::label($role))
        );
    }

    if ($action === 'adjust_points') {
        if (!PointsManager::hasPointsColumn() || !OrderManager::tableReady()) {
            AjaxResponse::error('积分系统未就绪');
        }
        $delta = isset($_POST['delta']) ? (float) $_POST['delta'] : 0;
        $remark = isset($_POST['remark']) ? trim((string) $_POST['remark']) : '';
        $result = PointsManager::adminAdjust($userId, $delta, $remark);
        if (!$result['ok']) {
            AjaxResponse::error($result['msg']);
        }
        AjaxResponse::success('积分已调整', array(
            'action'  => 'adjust_points',
            'user_id' => $userId,
            'points'  => PayConfig::fmtPoints($result['balance']),
        ));
    }

    if ($action === 'user_logs') {
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            AjaxResponse::error('日志功能尚未就绪');
        }
        // 轻量预览：固定最新 20 条，跳过 COUNT（用户可能有海量日志）
        $ok = array_key_exists('ok', $_POST) && $_POST['ok'] !== '' ? $_POST['ok'] : null;
        $paged = ApiLogManager::listPaged(array(
            'page'       => 1,
            'pagesize'   => 20,
            'before_id'  => 0,
            'ok'         => $ok,
            'userid'     => $userId,
            'q'          => '',
            'apiid'      => 0,
            'skip_total' => true,
        ));
        AjaxResponse::success('ok', array(
            'action'   => 'user_logs',
            'user_id'  => $userId,
            'list'     => $paged['list'],
            'pagesize' => 20,
        ));
    }

    if ($action === 'user_log_detail') {
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            AjaxResponse::error('日志功能尚未就绪');
        }
        $logId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($logId <= 0) {
            AjaxResponse::error('无效日志');
        }
        $row = ApiLogManager::findById($logId);
        if ($row === null) {
            AjaxResponse::error('记录不存在');
        }
        if ((int) (isset($row['userid']) ? $row['userid'] : 0) !== $userId) {
            AjaxResponse::error('记录不属于该用户');
        }
        AjaxResponse::success('ok', array(
            'action'  => 'user_log_detail',
            'user_id' => $userId,
            'row'     => $row,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$users = UserManager::all();
$userCount = count($users);

$counts = array(
    'all'       => $userCount,
    'developer' => 0,
    'user'      => 0,
    'banned'    => 0,
);
foreach ($users as $countRow) {
    $countRole = UserRole::normalize(isset($countRow['role']) ? $countRow['role'] : UserRole::ROLE_USER);
    if ((int) $countRow['status'] !== 1) {
        $counts['banned']++;
    }
    if ($countRole === UserRole::ROLE_DEVELOPER) {
        $counts['developer']++;
    } else {
        $counts['user']++;
    }
}

$headerActions = '';
if ($userCount > 0) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="usersSearchInput"
                   placeholder="搜索用户名、邮箱或 ID..." autocomplete="off">
        </div>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

/**
 * @param array $row
 * @return string
 */
function vs_users_oauth_badges(array $row)
{
    $badges = array();
    if (trim((string) $row['qqopenid']) !== '') {
        $badges[] = '<span class="vs-oauth-badge vs-oauth-badge--qq" title="已绑定 QQ">QQ</span>';
    }
    if (trim((string) $row['giteeid']) !== '') {
        $badges[] = '<span class="vs-oauth-badge vs-oauth-badge--gitee" title="已绑定 Gitee">Gitee</span>';
    }
    // 未绑定：不显示
    return implode(' ', $badges);
}

/**
 * @param array  $row
 * @param string $base
 * @return string
 */
function vs_users_oauth_icons(array $row, $base)
{
    $icons = array();
    if (trim((string) $row['qqopenid']) !== '') {
        $icons[] = '<img src="' . vs_e($base) . '/assets/img/QQ.svg" alt="QQ" title="已绑定 QQ" class="vs-user-oauth-icon" width="18" height="18">';
    }
    if (trim((string) $row['giteeid']) !== '') {
        $icons[] = '<img src="' . vs_e($base) . '/assets/img/gitee.svg" alt="Gitee" title="已绑定 Gitee" class="vs-user-oauth-icon" width="18" height="18">';
    }
    // 未绑定：不显示
    return implode('', $icons);
}

/**
 * @param int $n
 * @return string
 */
function vs_users_fmt_count($n)
{
    $n = (int) $n;
    if ($n < 0) {
        $n = 0;
    }
    return number_format($n, 0, '.', ',');
}

/**
 * @param string|null $datetime
 * @return string
 */
function vs_users_format_time($datetime)
{
    if ($datetime === null || trim((string) $datetime) === '') {
        return '从未登录';
    }
    return (string) $datetime;
}

/**
 * @param int    $userId
 * @param string $action ban|unban|delete
 * @param string $label
 * @param string $class
 * @param bool   $confirmDelete
 * @return string
 */
function vs_users_action_button($userId, $action, $label, $class, $confirmDelete = false)
{
    $confirmAttr = $confirmDelete ? ' data-confirm-delete="1"' : '';
    return '<button type="button" class="vs-btn vs-btn--pill ' . vs_e($class) . ' vs-user-action-btn"'
        . ' data-user-action="' . vs_e($action) . '" data-user-id="' . (int) $userId . '"' . $confirmAttr . '>'
        . vs_e($label) . '</button>';
}

/**
 * @param array $row
 * @return string
 */
function vs_users_role_badge(array $row)
{
    $role = UserRole::normalize(isset($row['role']) ? $row['role'] : UserRole::ROLE_USER);
    if ($role === UserRole::ROLE_DEVELOPER) {
        return '<span class="vs-role-badge vs-role-badge--developer">' . vs_e(UserRole::label($role)) . '</span>';
    }
    return '<span class="vs-role-badge vs-role-badge--user">' . vs_e(UserRole::label($role)) . '</span>';
}

/**
 * @param int    $userId
 * @param string $role
 * @param string $label
 * @param string $class
 * @return string
 */
function vs_users_role_button($userId, $role, $label, $class)
{
    return '<button type="button" class="vs-btn vs-btn--pill ' . vs_e($class) . ' vs-user-action-btn"'
        . ' data-user-action="set_role" data-user-id="' . (int) $userId . '" data-user-role="' . vs_e($role) . '">'
        . vs_e($label) . '</button>';
}

/**
 * @param int   $userId
 * @param bool  $active
 * @param array $row
 * @return string
 */
function vs_users_action_group($userId, $active, array $row)
{
    $role = UserRole::normalize(isset($row['role']) ? $row['role'] : UserRole::ROLE_USER);
    $html = '<div class="vs-users-actions">';
    $html .= '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-secondary vs-user-action-btn"'
        . ' data-user-action="view_logs" data-user-id="' . (int) $userId . '"'
        . ' data-user-name="' . vs_e(isset($row['username']) ? $row['username'] : '') . '">调用日志</button>';
    if ($active) {
        $html .= vs_users_action_button($userId, 'ban', '封禁', 'vs-btn--pill-danger');
    } else {
        $html .= vs_users_action_button($userId, 'unban', '解封', 'vs-btn--pill-primary');
    }
    if ($role === UserRole::ROLE_DEVELOPER) {
        $html .= vs_users_role_button($userId, UserRole::ROLE_USER, '设为普通', 'vs-btn--pill-secondary');
    } else {
        $html .= vs_users_role_button($userId, UserRole::ROLE_DEVELOPER, '设为开发者', 'vs-btn--pill-primary');
    }
    if (class_exists('PointsManager') && PointsManager::hasPointsColumn()) {
        $html .= '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-secondary vs-user-action-btn"'
            . ' data-user-action="adjust_points" data-user-id="' . (int) $userId . '"'
            . ' data-user-points="' . vs_e(PayConfig::fmtPoints(isset($row['points']) ? $row['points'] : 0)) . '">积分</button>';
    }
    $html .= vs_users_action_button($userId, 'delete', '删除', 'vs-btn--pill-danger', true);
    $html .= '</div>';
    return $html;
}

/**
 * @param array $row
 * @return string
 */
function vs_users_search_blob(array $row)
{
    $parts = array(
        (string) (int) $row['id'],
        (string) $row['username'],
        (string) $row['email'],
    );
    return strtolower(implode(' ', $parts));
}

$pointsOn = class_exists('PointsManager') && PointsManager::hasPointsColumn();

vs_admin_layout_start('用户管理', 'users', $headerActions);
?>

<div id="usersPage" data-user-total="<?php echo (int) $userCount; ?>">
    <?php if ($userCount === 0): ?>
        <div class="vs-panel">
            <?php vs_render_notice('info', '', '暂无注册用户', array('compact' => true)); ?>
        </div>
    <?php else: ?>
        <div class="vs-tabs vs-api-review-tabs" id="usersFilters" role="tablist" aria-label="用户筛选">
            <button type="button" class="vs-tabs__btn vs-users-filter is-active" data-filter="all" role="tab" aria-selected="true">
                全部用户<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="all"><?php echo (int) $counts['all']; ?></span>
            </button>
            <button type="button" class="vs-tabs__btn vs-users-filter" data-filter="developer" role="tab" aria-selected="false">
                开发者<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="developer"><?php echo (int) $counts['developer']; ?></span>
            </button>
            <button type="button" class="vs-tabs__btn vs-users-filter" data-filter="user" role="tab" aria-selected="false">
                普通用户<span class="vs-badge vs-badge--default vs-api-review-tabs__badge" data-count="user"><?php echo (int) $counts['user']; ?></span>
            </button>
            <button type="button" class="vs-tabs__btn vs-users-filter" data-filter="banned" role="tab" aria-selected="false">
                已封禁<span class="vs-badge vs-badge--warning vs-api-review-tabs__badge" data-count="banned"><?php echo (int) $counts['banned']; ?></span>
            </button>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="usersFilterEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前 Tab 或搜索条件下没有用户，可切换上方筛选或清空搜索。</p>
            </div>
        </div>

        <div class="vs-users-desktop vs-table-wrap vs-api-list-table-wrap" id="usersTableWrap">
            <div class="vs-table-responsive">
                <table class="vs-table vs-users-table">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>邮箱</th>
                            <th>身份</th>
                            <?php if ($pointsOn): ?>
                            <th>积分</th>
                            <?php endif; ?>
                            <th>发布接口</th>
                            <th>调用数量</th>
                            <th>第三方绑定</th>
                            <th>最后登录</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="usersBody">
                    <?php foreach ($users as $row): ?>
                        <?php
                        $avatar = UserAvatar::resolve($row);
                        $active = (int) $row['status'] === 1;
                        $uid = (int) $row['id'];
                        $userRole = UserRole::normalize(isset($row['role']) ? $row['role'] : UserRole::ROLE_USER);
                        $apiCount = isset($row['api_count']) ? (int) $row['api_count'] : 0;
                        $callCount = isset($row['call_count']) ? (int) $row['call_count'] : 0;
                        $oauthHtml = vs_users_oauth_icons($row, $vsBase);
                        ?>
                        <tr class="<?php echo $active ? '' : 'vs-users-row--banned'; ?>" data-user-row="<?php echo $uid; ?>"
                            data-search="<?php echo vs_e(vs_users_search_blob($row)); ?>"
                            data-user-role="<?php echo vs_e($userRole); ?>"
                            data-user-status="<?php echo $active ? '1' : '0'; ?>"
                            data-user-name="<?php echo vs_e($row['username']); ?>">
                            <td>
                                <div class="vs-users-cell-user">
                                    <img src="<?php echo vs_e($avatar); ?>" alt="" class="vs-users-avatar">
                                    <div>
                                        <div class="vs-users-name">
                                            <span class="vs-users-id">ID <?php echo $uid; ?></span>
                                            <?php echo vs_e($row['username']); ?>
                                            <?php if (!$active): ?>
                                                <span class="vs-users-banned-tag">已封禁</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="vs-users-email-cell"><?php echo vs_e($row['email']); ?></td>
                            <td class="vs-users-role-cell"><?php echo vs_users_role_badge($row); ?></td>
                            <?php if ($pointsOn): ?>
                            <td class="vs-users-points-cell" data-field="points"><?php echo vs_e(PayConfig::fmtPoints(isset($row['points']) ? $row['points'] : 0)); ?></td>
                            <?php endif; ?>
                            <td class="vs-users-num-cell" data-field="api_count"><?php echo vs_e(vs_users_fmt_count($apiCount)); ?></td>
                            <td class="vs-users-num-cell" data-field="call_count"><?php echo vs_e(vs_users_fmt_count($callCount)); ?></td>
                            <td class="vs-users-oauth-cell"><?php echo $oauthHtml; ?></td>
                            <td class="vs-users-login-cell"><?php echo vs_e(vs_users_format_time(isset($row['lastlogin']) ? $row['lastlogin'] : null)); ?></td>
                            <td>
                                <?php echo vs_users_action_group($uid, $active, $row); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="vs-users-mobile" id="usersMobile">
            <?php foreach ($users as $row): ?>
                <?php
                $avatar = UserAvatar::resolve($row);
                $active = (int) $row['status'] === 1;
                $uid = (int) $row['id'];
                $userRole = UserRole::normalize(isset($row['role']) ? $row['role'] : UserRole::ROLE_USER);
                $isDev = $userRole === UserRole::ROLE_DEVELOPER;
                $apiCount = isset($row['api_count']) ? (int) $row['api_count'] : 0;
                $callCount = isset($row['call_count']) ? (int) $row['call_count'] : 0;
                $oauthHtml = vs_users_oauth_icons($row, $vsBase);
                ?>
                <article class="vs-user-card<?php echo $active ? '' : ' vs-user-card--banned'; ?>" data-user-row="<?php echo $uid; ?>"
                         data-search="<?php echo vs_e(vs_users_search_blob($row)); ?>"
                         data-user-role="<?php echo vs_e($userRole); ?>"
                         data-user-status="<?php echo $active ? '1' : '0'; ?>"
                         data-user-name="<?php echo vs_e($row['username']); ?>">
                    <div class="vs-user-card__head">
                        <img src="<?php echo vs_e($avatar); ?>" alt="" class="vs-users-avatar">
                        <div class="vs-user-card__main">
                            <div class="vs-user-card__top">
                                <div class="vs-users-name">
                                    <span class="vs-users-id">ID <?php echo $uid; ?></span>
                                    <?php echo vs_e($row['username']); ?>
                                    <?php if (!$active): ?>
                                        <span class="vs-users-banned-tag">已封禁</span>
                                    <?php endif; ?>
                                </div>
                                <div class="vs-user-card__badges">
                                    <span class="vs-user-card__role"><?php echo vs_users_role_badge($row); ?></span>
                                    <?php if ($oauthHtml !== ''): ?>
                                    <div class="vs-user-card__oauth"><?php echo $oauthHtml; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="vs-users-meta vs-user-card__email"><?php echo vs_e($row['email']); ?></div>
                            <div class="vs-user-card__stats">
                                <?php if ($pointsOn): ?>
                                <span class="vs-user-card__stat"><i>积分</i><b data-field="points"><?php echo vs_e(PayConfig::fmtPoints(isset($row['points']) ? $row['points'] : 0)); ?></b></span>
                                <?php endif; ?>
                                <span class="vs-user-card__stat vs-user-card__stat--apis"><i>发布</i><b data-field="api_count"><?php echo vs_e(vs_users_fmt_count($apiCount)); ?></b></span>
                                <span class="vs-user-card__stat"><i>调用</i><b data-field="call_count"><?php echo vs_e(vs_users_fmt_count($callCount)); ?></b></span>
                            </div>
                            <div class="vs-user-card__last-login">
                                最后登录：<?php echo vs_e(vs_users_format_time(isset($row['lastlogin']) ? $row['lastlogin'] : null)); ?>
                            </div>
                        </div>
                    </div>
                    <div class="vs-user-card__actions">
                        <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-secondary vs-user-action-btn"
                                data-user-action="view_logs" data-user-id="<?php echo $uid; ?>"
                                data-user-name="<?php echo vs_e($row['username']); ?>">调用日志</button>
                        <?php if ($active): ?>
                            <?php echo vs_users_action_button($uid, 'ban', '封禁', 'vs-btn--pill-danger'); ?>
                        <?php else: ?>
                            <?php echo vs_users_action_button($uid, 'unban', '解封', 'vs-btn--pill-primary'); ?>
                        <?php endif; ?>
                        <?php if ($isDev): ?>
                            <?php echo vs_users_role_button($uid, UserRole::ROLE_USER, '设为普通', 'vs-btn--pill-secondary'); ?>
                        <?php else: ?>
                            <?php echo vs_users_role_button($uid, UserRole::ROLE_DEVELOPER, '设为开发者', 'vs-btn--pill-primary'); ?>
                        <?php endif; ?>
                        <?php if ($pointsOn): ?>
                            <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-secondary vs-user-action-btn"
                                    data-user-action="adjust_points" data-user-id="<?php echo $uid; ?>"
                                    data-user-points="<?php echo vs_e(PayConfig::fmtPoints(isset($row['points']) ? $row['points'] : 0)); ?>">积分</button>
                        <?php endif; ?>
                        <?php echo vs_users_action_button($uid, 'delete', '删除', 'vs-btn--pill-danger', true); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="vs-api-list-footer" id="usersFooter">
            <div class="vs-api-pager" id="usersPager">
                <label class="vs-api-list-pagesize" for="usersPageSize">
                    <span class="vs-api-list-pagesize__label">每页</span>
                    <select class="vs-input vs-select vs-api-list-pagesize__select" id="usersPageSize" data-vs-pick="sheet">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <button type="button" class="vs-api-pager__nav" id="usersPrevBtn" aria-label="上一页">上一页</button>
                <div class="vs-api-pager__nums" id="usersPagerNums" role="navigation" aria-label="页码"></div>
                <button type="button" class="vs-api-pager__nav" id="usersNextBtn" aria-label="下一页">下一页</button>
            </div>
            <p class="vs-api-list-stats" id="usersStats">共 <?php echo (int) $userCount; ?> 条</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($pointsOn): ?>
<div class="vs-overlay vs-overlay--form" id="usersPointsOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="usersPointsTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="usersPointsTitle">调整积分</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form class="vs-overlay__body" id="usersPointsForm" method="post" data-ajax="1">
            <input type="hidden" name="action" value="adjust_points">
            <input type="hidden" name="user_id" id="usersPointsUserId" value="">
            <p class="vs-form-hint" id="usersPointsHint">当前余额：0</p>
            <div class="vs-form-row">
                <label class="vs-label" for="usersPointsDelta">变动数量</label>
                <input type="number" class="vs-input" id="usersPointsDelta" name="delta" step="0.0001" required placeholder="正数加款，负数扣款">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="usersPointsRemark">备注</label>
                <input type="text" class="vs-input" id="usersPointsRemark" name="remark" maxlength="100" placeholder="可选">
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--outline" data-overlay-close="1">取消</button>
            <button type="submit" form="usersPointsForm" class="vs-btn vs-btn--primary">确定</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<div class="vs-overlay vs-overlay--lg" id="usersLogsOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="usersLogsTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <button type="button" class="vs-overlay__back" id="usersLogsBackBtn" hidden aria-label="返回列表">&larr;</button>
            <h3 class="vs-overlay__title" id="usersLogsTitle">用户调用日志</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body vs-users-logs-body">
            <div id="usersLogsListPanel">
                <div class="vs-users-logs-toolbar">
                    <div class="vs-users-logs-filters" role="group" aria-label="状态筛选">
                        <button type="button" class="vs-btn vs-btn--sm vs-btn--outline is-active" data-user-log-ok="">全部</button>
                        <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-user-log-ok="1">成功</button>
                        <button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-user-log-ok="0">失败</button>
                    </div>
                    <p class="vs-users-logs-hint">仅最近 20 条</p>
                </div>
                <div class="vs-users-logs-list" id="usersLogsList">
                    <?php vs_render_loading('正在加载日志', array('compact' => true)); ?>
                </div>
            </div>
            <div id="usersLogsDetailPanel" hidden>
                <div class="vs-users-logs-detail" id="usersLogDetailBody">
                    <?php vs_render_loading('正在加载详情', array('compact' => true)); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php vs_admin_layout_end(array('users.js')); ?>
