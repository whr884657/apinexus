<?php
/**
 * 文件：admin/system/ip.php
 * 作用：管理员查看/管理所有用户的 IP 白名单与出口代理
 */

require_once dirname(__DIR__) . '/init.php';

$allowReady = class_exists('UserIpAllow') && UserIpAllow::columnReady();
$proxyReady = class_exists('UserIpProxy') && UserIpProxy::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

    if ($action === 'overview') {
        if (!$allowReady) {
            AjaxResponse::error('IP 白名单尚未就绪，请先执行数据库结构更新');
        }
        $pack = UserIpAllow::adminOverview(500);
        if (empty($pack['ok'])) {
            AjaxResponse::error(isset($pack['msg']) ? $pack['msg'] : '读取失败');
        }
        AjaxResponse::success('ok', array(
            'list'       => isset($pack['list']) ? $pack['list'] : array(),
            'truncated'  => !empty($pack['truncated']),
            'proxy_ready'=> $proxyReady ? 1 : 0,
        ));
    }

    if ($userId <= 0) {
        AjaxResponse::error('无效用户');
    }
    $user = UserManager::findById($userId);
    if (!is_array($user)) {
        AjaxResponse::error('用户不存在');
    }

    if ($action === 'detail') {
        if (!$allowReady) {
            AjaxResponse::error('IP 配置尚未就绪');
        }
        $allowList = UserIpAllow::parseList(UserIpAllow::rawForUser($userId));
        $proxies = array();
        $strategy = 0;
        $strategyLabel = '轮询';
        if ($proxyReady) {
            $pack = UserIpProxy::listForUser($userId);
            if (!empty($pack['ok']) && isset($pack['list']) && is_array($pack['list'])) {
                $proxies = $pack['list'];
            }
            $strategy = UserIpProxy::strategyForUser($userId);
            $strategyLabel = UserIpProxy::strategyLabel($strategy);
        }
        $username = isset($user['username']) ? trim((string) $user['username']) : '';
        if ($username === '') {
            $username = '用户#' . $userId;
        }
        AjaxResponse::success('ok', array(
            'userid'         => $userId,
            'username'       => $username,
            'email'          => isset($user['email']) ? trim((string) $user['email']) : '',
            'avatar'         => UserAvatar::resolve($user),
            'allow_list'     => $allowList,
            'allow_count'    => count($allowList),
            'allow_max'      => UserIpAllow::MAX_COUNT,
            'proxy_list'     => $proxies,
            'proxy_count'    => count($proxies),
            'proxy_max'      => UserIpProxy::MAX_COUNT,
            'strategy'       => $strategy,
            'strategy_label' => $strategyLabel,
            'proxy_ready'    => $proxyReady ? 1 : 0,
        ));
    }

    if ($action === 'clear_allow') {
        if (!$allowReady) {
            AjaxResponse::error('IP 白名单尚未就绪');
        }
        $saved = UserIpAllow::saveList($userId, array());
        if ($saved !== true) {
            AjaxResponse::error((string) $saved);
        }
        AjaxResponse::success('已清空该用户白名单', array(
            'userid'      => $userId,
            'allow_list'  => array(),
            'allow_count' => 0,
        ));
    }

    if ($action === 'remove_allow') {
        if (!$allowReady) {
            AjaxResponse::error('IP 白名单尚未就绪');
        }
        $ip = isset($_POST['ip']) ? (string) $_POST['ip'] : '';
        $result = UserIpAllow::removeIp($userId, $ip);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '移除失败');
        }
        $list = isset($result['list']) && is_array($result['list']) ? $result['list'] : array();
        AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已移除', array(
            'userid'      => $userId,
            'allow_list'  => $list,
            'allow_count' => count($list),
        ));
    }

    if ($action === 'proxy_delete' || $action === 'proxy_toggle') {
        if (!$proxyReady) {
            AjaxResponse::error('出口代理尚未就绪');
        }
        $proxyId = isset($_POST['proxy_id']) ? (int) $_POST['proxy_id'] : 0;
        if ($proxyId <= 0) {
            AjaxResponse::error('无效代理');
        }
        if ($action === 'proxy_delete') {
            $result = UserIpProxy::delete($userId, $proxyId);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '删除失败');
            }
            $pack = UserIpProxy::listForUser($userId);
            AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已删除', array(
                'userid'      => $userId,
                'proxy_list'  => (!empty($pack['ok']) && isset($pack['list'])) ? $pack['list'] : array(),
                'proxy_count' => (!empty($pack['ok']) && isset($pack['count'])) ? (int) $pack['count'] : 0,
            ));
        }
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        $result = UserIpProxy::setStatus($userId, $proxyId, $status);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '操作失败');
        }
        $pack = UserIpProxy::listForUser($userId);
        AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已更新', array(
            'userid'      => $userId,
            'proxy_list'  => (!empty($pack['ok']) && isset($pack['list'])) ? $pack['list'] : array(),
            'proxy_count' => (!empty($pack['ok']) && isset($pack['count'])) ? (int) $pack['count'] : 0,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$overview = $allowReady
    ? UserIpAllow::adminOverview(500)
    : array('ok' => false, 'list' => array(), 'truncated' => false);
$rows = (!empty($overview['ok']) && isset($overview['list']) && is_array($overview['list']))
    ? $overview['list']
    : array();
$truncated = !empty($overview['truncated']);

/**
 * @param array $row
 * @return array
 */
function vs_admin_ip_row_ctx(array $row)
{
    $uid = (int) (isset($row['userid']) ? $row['userid'] : 0);
    $username = isset($row['username']) ? (string) $row['username'] : ('用户#' . $uid);
    $email = isset($row['email']) ? (string) $row['email'] : '';
    $search = mb_strtolower(
        $username . ' ' . $email . ' #' . $uid . ' '
        . (isset($row['allow_preview']) ? $row['allow_preview'] : ''),
        'UTF-8'
    );
    return array(
        'userid'         => $uid,
        'username'       => $username,
        'email'          => $email,
        'avatar'         => isset($row['avatar']) ? (string) $row['avatar'] : '',
        'allow_count'    => isset($row['allow_count']) ? (int) $row['allow_count'] : 0,
        'allow_preview'  => isset($row['allow_preview']) ? (string) $row['allow_preview'] : '—',
        'proxy_count'    => isset($row['proxy_count']) ? (int) $row['proxy_count'] : 0,
        'strategy_label' => isset($row['strategy_label']) ? (string) $row['strategy_label'] : '',
        'search'         => $search,
    );
}

/**
 * @param array $ctx
 * @return string
 */
function vs_admin_ip_actions_html(array $ctx)
{
    return '<div class="action-btns">'
        . '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-view"'
        . ' data-user-id="' . (int) $ctx['userid'] . '">查看</button>'
        . '</div>';
}

/**
 * @param array $ctx
 * @return void
 */
function vs_admin_ip_desktop_row(array $ctx)
{
    ?>
    <tr data-ip-row="<?php echo (int) $ctx['userid']; ?>" data-search="<?php echo vs_e($ctx['search']); ?>">
        <td>
            <div class="content-author-cell">
                <?php if ($ctx['avatar'] !== ''): ?>
                    <img class="content-author-cell__avatar" src="<?php echo vs_e($ctx['avatar']); ?>" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer">
                <?php else: ?>
                    <span class="content-author-cell__fallback"><?php echo vs_e(mb_substr($ctx['username'], 0, 1, 'UTF-8')); ?></span>
                <?php endif; ?>
                <span class="content-author-cell__name"><?php echo vs_e($ctx['username']); ?></span>
            </div>
        </td>
        <td><span class="vs-log-mono"><?php echo vs_e($ctx['email'] !== '' ? $ctx['email'] : '—'); ?></span></td>
        <td>
            <span class="vs-badge <?php echo $ctx['allow_count'] > 0 ? 'vs-badge--info' : 'vs-badge--default'; ?>">
                <?php echo (int) $ctx['allow_count']; ?> 条
            </span>
            <div class="vs-admin-ip-preview"><?php echo vs_e($ctx['allow_preview']); ?></div>
        </td>
        <td>
            <span class="vs-badge <?php echo $ctx['proxy_count'] > 0 ? 'vs-badge--success' : 'vs-badge--default'; ?>">
                <?php echo (int) $ctx['proxy_count']; ?> 条
            </span>
            <?php if ($ctx['strategy_label'] !== '' && $ctx['proxy_count'] > 0): ?>
                <span class="vs-admin-ip-strategy"><?php echo vs_e($ctx['strategy_label']); ?></span>
            <?php endif; ?>
        </td>
        <td class="vs-content-actions-cell"><?php echo vs_admin_ip_actions_html($ctx); ?></td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @return void
 */
function vs_admin_ip_mobile_card(array $ctx)
{
    ?>
    <div class="admin-ip-card" data-ip-row="<?php echo (int) $ctx['userid']; ?>" data-search="<?php echo vs_e($ctx['search']); ?>">
        <div class="admin-ip-card__header">
            <div class="content-author-cell">
                <?php if ($ctx['avatar'] !== ''): ?>
                    <img class="content-author-cell__avatar" src="<?php echo vs_e($ctx['avatar']); ?>" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer">
                <?php else: ?>
                    <span class="content-author-cell__fallback"><?php echo vs_e(mb_substr($ctx['username'], 0, 1, 'UTF-8')); ?></span>
                <?php endif; ?>
                <span class="content-author-cell__name"><?php echo vs_e($ctx['username']); ?></span>
            </div>
            <div class="admin-ip-card__tags">
                <span class="vs-badge vs-badge--info">白名单 <?php echo (int) $ctx['allow_count']; ?></span>
                <span class="vs-badge vs-badge--success">代理 <?php echo (int) $ctx['proxy_count']; ?></span>
            </div>
        </div>
        <div class="admin-ip-card__meta">
            <span><?php echo vs_e($ctx['email'] !== '' ? $ctx['email'] : '—'); ?></span>
            <span><?php echo vs_e($ctx['allow_preview']); ?></span>
        </div>
        <div class="admin-ip-card__actions"><?php echo vs_admin_ip_actions_html($ctx); ?></div>
    </div>
    <?php
}

ob_start();
?>
<div class="vs-search-bar vs-api-list-toolbar">
    <div class="vs-search-bar__input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" class="vs-input vs-search-bar__input" id="adminIpSearch"
               placeholder="搜索用户名、邮箱或 IP…" autocomplete="off">
    </div>
</div>
<?php
$headerActions = ob_get_clean();

vs_admin_layout_start('IP 配置', 'ip', $headerActions);
?>

<div id="adminIpPage" data-proxy-ready="<?php echo $proxyReady ? '1' : '0'; ?>">
    <?php if (!$allowReady): ?>
        <div class="vs-panel">
            <?php vs_render_notice('warning', '', '请先在「系统升级」中执行数据库结构更新，以启用用户 IP 白名单。', array('compact' => true)); ?>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip">
            <?php
            vs_render_notice(
                'info',
                '',
                '查看并管理用户自配的调用 IP 白名单与出口代理。密码不展示；可清空白名单、删除或启停代理节点。'
                    . ($truncated ? ' 当前仅展示最近 500 条有配置的用户。' : ''),
                array('compact' => true)
            );
            ?>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminIpEmpty"<?php echo count($rows) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无用户配置</h3>
                <p class="vs-api-list-empty__desc">用户在用户中心配置白名单或出口代理后，将显示在此。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminIpSearchEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前搜索下没有用户，可清空关键词。</p>
            </div>
        </div>

        <div class="vs-api-list-table-card vs-api-list-table-wrap" id="adminIpTableWrap"<?php echo count($rows) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-table-responsive">
                <table class="vs-table">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>邮箱</th>
                            <th>IP 白名单</th>
                            <th>出口代理</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="adminIpTableBody">
                        <?php foreach ($rows as $row): ?>
                            <?php vs_admin_ip_desktop_row(vs_admin_ip_row_ctx($row)); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-admin-ip-cards" id="adminIpMobileCards"<?php echo count($rows) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($rows as $row): ?>
                <?php vs_admin_ip_mobile_card(vs_admin_ip_row_ctx($row)); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--lg" id="adminIpOverlay" hidden>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="adminIpOverlayTitle">
        <div class="vs-overlay__head">
            <h2 class="vs-overlay__title" id="adminIpOverlayTitle">用户 IP 配置</h2>
            <button type="button" class="vs-overlay__close" data-ip-overlay-close aria-label="关闭">&times;</button>
        </div>
        <div class="vs-overlay__body" id="adminIpOverlayBody">
            <p class="vs-muted">加载中…</p>
        </div>
    </div>
</div>

<?php
vs_admin_layout_end(array('admin-ip.js'));
