<?php
/**
 * 文件：admin/system/ip.php
 * 作用：管理员查看/管理全站用户 IP 白名单与出口代理（扁平多列 + 归属）
 */

require_once dirname(__DIR__) . '/init.php';

$allowReady = class_exists('UserIpAllow') && UserIpAllow::columnReady();
$proxyReady = class_exists('UserIpProxy') && UserIpProxy::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'allow_list') {
        if (!$allowReady) {
            AjaxResponse::error('IP 白名单尚未就绪，请先执行数据库结构更新');
        }
        $pack = UserIpAllow::adminFlatAllowList(2000);
        if (empty($pack['ok'])) {
            AjaxResponse::error(isset($pack['msg']) ? $pack['msg'] : '读取失败');
        }
        AjaxResponse::success('ok', array(
            'list'      => isset($pack['list']) ? $pack['list'] : array(),
            'truncated' => !empty($pack['truncated']),
        ));
    }

    if ($action === 'proxy_list') {
        if (!$proxyReady) {
            AjaxResponse::error('出口代理尚未就绪，请先执行数据库结构更新');
        }
        $pack = UserIpProxy::adminFlatList(2000);
        if (empty($pack['ok'])) {
            AjaxResponse::error(isset($pack['msg']) ? $pack['msg'] : '读取失败');
        }
        AjaxResponse::success('ok', array(
            'list'      => isset($pack['list']) ? $pack['list'] : array(),
            'truncated' => !empty($pack['truncated']),
        ));
    }

    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    if ($userId <= 0) {
        AjaxResponse::error('无效用户');
    }
    $user = UserManager::findById($userId);
    if (!is_array($user)) {
        AjaxResponse::error('用户不存在');
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
        AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已移除', array(
            'userid' => $userId,
            'ip'     => $ip,
        ));
    }

    if ($action === 'proxy_get') {
        if (!$proxyReady) {
            AjaxResponse::error('出口代理尚未就绪');
        }
        $proxyId = isset($_POST['proxy_id']) ? (int) $_POST['proxy_id'] : 0;
        $pack = UserIpProxy::findForUser($userId, $proxyId);
        if (empty($pack['ok'])) {
            AjaxResponse::error(isset($pack['msg']) ? $pack['msg'] : '读取失败');
        }
        AjaxResponse::success('ok', array(
            'row' => isset($pack['row']) ? $pack['row'] : null,
        ));
    }

    if ($action === 'proxy_save') {
        if (!$proxyReady) {
            AjaxResponse::error('出口代理尚未就绪');
        }
        $saveId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($saveId <= 0) {
            AjaxResponse::error('管理端不可代用户新增代理');
        }
        $input = array(
            'id'       => $saveId,
            'title'    => isset($_POST['title']) ? (string) $_POST['title'] : '',
            'mode'     => isset($_POST['mode']) ? (int) $_POST['mode'] : 0,
            'proto'    => isset($_POST['proto']) ? (int) $_POST['proto'] : 0,
            'host'     => isset($_POST['host']) ? (string) $_POST['host'] : '',
            'port'     => isset($_POST['port']) ? (int) $_POST['port'] : 0,
            'username' => isset($_POST['username']) ? (string) $_POST['username'] : '',
            'extract'  => isset($_POST['extract']) ? (string) $_POST['extract'] : '',
            'extfmt'   => isset($_POST['extfmt']) ? (int) $_POST['extfmt'] : 0,
            'jsonhost' => isset($_POST['jsonhost']) ? (string) $_POST['jsonhost'] : '',
            'jsonport' => isset($_POST['jsonport']) ? (string) $_POST['jsonport'] : '',
            'ttlmin'   => isset($_POST['ttlmin']) ? (int) $_POST['ttlmin'] : 10,
            'status'   => isset($_POST['status']) ? (int) $_POST['status'] : 1,
            'sort'     => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
        );
        if (array_key_exists('password', $_POST)) {
            $input['password'] = (string) $_POST['password'];
        }
        $result = UserIpProxy::save($userId, $input);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '保存失败');
        }
        AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已保存', array(
            'userid' => $userId,
            'row'    => isset($result['row']) ? $result['row'] : null,
        ));
    }

    if ($action === 'proxy_test') {
        if (!$proxyReady) {
            AjaxResponse::error('出口代理尚未就绪');
        }
        $proxyId = isset($_POST['proxy_id']) ? (int) $_POST['proxy_id'] : 0;
        $result = UserIpProxy::testConnectivity($userId, $proxyId);
        if (empty($result['ok'])) {
            AjaxResponse::json(array(
                'code'   => 0,
                'msg'    => isset($result['msg']) ? $result['msg'] : '测试失败',
                'logs'   => isset($result['logs']) ? $result['logs'] : array(),
                'detail' => isset($result['detail']) ? $result['detail'] : null,
            ));
        }
        AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '测试完成', array(
            'logs'   => isset($result['logs']) ? $result['logs'] : array(),
            'detail' => isset($result['detail']) ? $result['detail'] : null,
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
            AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已删除', array(
                'userid'   => $userId,
                'proxy_id' => $proxyId,
            ));
        }
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        $result = UserIpProxy::setStatus($userId, $proxyId, $status);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '操作失败');
        }
        AjaxResponse::success(isset($result['msg']) ? $result['msg'] : '已更新', array(
            'userid'   => $userId,
            'proxy_id' => $proxyId,
            'status'   => $status === 1 ? 1 : 0,
        ));
    }

    AjaxResponse::error('未知操作');
}

$allowPack = $allowReady
    ? UserIpAllow::adminFlatAllowList(2000)
    : array('ok' => true, 'list' => array(), 'truncated' => false);
$proxyPack = $proxyReady
    ? UserIpProxy::adminFlatList(2000)
    : array('ok' => true, 'list' => array(), 'truncated' => false);

$allowRows = (!empty($allowPack['ok']) && isset($allowPack['list']) && is_array($allowPack['list']))
    ? $allowPack['list'] : array();
$proxyRows = (!empty($proxyPack['ok']) && isset($proxyPack['list']) && is_array($proxyPack['list']))
    ? $proxyPack['list'] : array();
$allowTrunc = !empty($allowPack['truncated']);
$proxyTrunc = !empty($proxyPack['truncated']);

/**
 * @param array $row
 * @return array
 */
function vs_admin_ip_owner_ctx(array $row)
{
    $uid = isset($row['userid']) ? (int) $row['userid'] : 0;
    $name = isset($row['ownername']) ? trim((string) $row['ownername']) : '';
    if ($name === '' && isset($row['username'])) {
        $name = trim((string) $row['username']);
    }
    if ($name === '') {
        $name = '用户#' . $uid;
    }
    $avatar = isset($row['owneravatar']) ? trim((string) $row['owneravatar']) : '';
    if ($avatar === '' && isset($row['avatar'])) {
        $avatar = trim((string) $row['avatar']);
    }
    return array(
        'userid'   => $uid,
        'username' => $name,
        'avatar'   => $avatar,
    );
}

/**
 * @param array $owner
 * @return string
 */
function vs_admin_ip_owner_cell_html(array $owner)
{
    $html = '<div class="content-author-cell">';
    if ($owner['avatar'] !== '') {
        $html .= '<img class="content-author-cell__avatar" src="' . vs_e($owner['avatar']) . '" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer">';
    } else {
        $ch = function_exists('mb_substr')
            ? mb_substr($owner['username'], 0, 1, 'UTF-8')
            : substr($owner['username'], 0, 1);
        $html .= '<span class="content-author-cell__fallback">' . vs_e($ch) . '</span>';
    }
    $html .= '<div class="content-author-cell__meta">'
        . '<span class="content-author-cell__name">' . vs_e($owner['username']) . '</span>'
        . '</div></div>';
    return $html;
}

/**
 * @param string $text
 * @param int    $max
 * @return string
 */
function vs_admin_ip_truncate($text, $max = 42)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }
        return mb_substr($text, 0, max(1, $max - 1), 'UTF-8') . '…';
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, max(1, $max - 1)) . '…';
}

/**
 * 备注名为空或仅为「代理」等无信息词时，降级为短码 / #id（页面已标明出口代理，勿再堆「代理」）
 *
 * @param string $title
 * @param string $code
 * @param int    $pid
 * @return array{text:string,show_code:bool}
 */
function vs_admin_ip_proxy_display_title($title, $code, $pid)
{
    $title = trim((string) $title);
    $code = trim((string) $code);
    $pid = (int) $pid;
    $generic = false;
    if ($title === '') {
        $generic = true;
    } else {
        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($title, 'UTF-8')
            : strtolower($title);
        $lower = preg_replace('/\s+/u', '', $lower);
        if (in_array($lower, array('代理', '出口代理', 'ip代理', 'proxy', 'proxies'), true)) {
            $generic = true;
        } elseif (preg_match('/^代理#?\d*$/u', $title)) {
            $generic = true;
        }
    }
    if (!$generic) {
        return array(
            'text'      => $title,
            'show_code' => ($code !== ''),
        );
    }
    if ($code !== '') {
        return array(
            'text'      => '短码 ' . $code,
            'show_code' => false,
        );
    }
    return array(
        'text'      => '#' . $pid,
        'show_code' => false,
    );
}

/**
 * @param array $row
 * @return array{full:string,short:string}
 */
function vs_admin_ip_proxy_endpoint(array $row)
{
    $mode = isset($row['mode']) ? (int) $row['mode'] : 0;
    if ($mode === 1) {
        $full = isset($row['extract']) ? trim((string) $row['extract']) : '';
    } else {
        $host = isset($row['host']) ? trim((string) $row['host']) : '';
        $port = isset($row['port']) ? (int) $row['port'] : 0;
        $full = $host !== '' ? ($host . ($port > 0 ? (':' . $port) : '')) : '';
    }
    if ($full === '') {
        $full = '—';
    }
    return array(
        'full'  => $full,
        'short' => vs_admin_ip_truncate($full, 42),
    );
}

/**
 * @param array $row
 * @return string
 */
function vs_admin_ip_allow_search(array $row)
{
    $o = vs_admin_ip_owner_ctx($row);
    return strtolower($o['username'] . ' ' . (isset($row['ip']) ? $row['ip'] : ''));
}

/**
 * @param array $row
 * @return string
 */
function vs_admin_ip_proxy_search(array $row)
{
    $o = vs_admin_ip_owner_ctx($row);
    $ep = vs_admin_ip_proxy_endpoint($row);
    $bits = array(
        $o['username'],
        isset($row['title']) ? $row['title'] : '',
        isset($row['proxycode']) ? $row['proxycode'] : '',
        isset($row['modelabel']) ? $row['modelabel'] : '',
        isset($row['protolabel']) ? $row['protolabel'] : '',
        $ep['full'],
    );
    return strtolower(implode(' ', $bits));
}

/**
 * @param int $mode
 * @return string
 */
function vs_admin_ip_mode_badge_class($mode)
{
    return ((int) $mode === 1) ? 'vs-badge--warning' : 'vs-badge--info';
}

/**
 * @param string $protoLabel
 * @return string
 */
function vs_admin_ip_proto_badge_class($protoLabel)
{
    $p = strtoupper(trim((string) $protoLabel));
    if ($p === 'SOCKS5' || $p === 'SOCKS4') {
        return 'vs-badge--info';
    }
    return 'vs-badge--default';
}

ob_start();
?>
<div class="vs-search-bar vs-api-list-toolbar">
    <div class="vs-search-bar__input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" class="vs-input vs-search-bar__input" id="adminIpSearch"
               placeholder="搜索归属用户、IP 或代理…" autocomplete="off">
    </div>
    <?php echo vs_admin_refresh_btn_html('adminIpRefreshBtn'); ?>
</div>
<?php
$headerActions = ob_get_clean();

vs_admin_layout_start('IP 配置', 'ip', $headerActions);
?>

<div id="adminIpPage"
     data-proxy-ready="<?php echo $proxyReady ? '1' : '0'; ?>"
     data-allow-ready="<?php echo $allowReady ? '1' : '0'; ?>">

    <?php if (!$allowReady && !$proxyReady): ?>
        <div class="vs-panel">
            <?php vs_render_notice('warning', '', '请先在「系统升级」中执行数据库结构更新，以启用用户 IP 配置。', array('compact' => true)); ?>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip">
            <?php
            $tip = '全站用户白名单与出口代理；密码不展示。可移除白名单、测试/启停/编辑/删除代理。';
            if ($allowTrunc || $proxyTrunc) {
                $tip .= ' 列表已达展示上限，请用搜索定位。';
            }
            vs_render_notice('info', '', $tip, array('compact' => true));
            ?>
        </div>

        <div class="vs-tabs vs-api-review-tabs vs-admin-ip-tabs" id="adminIpTabs" role="tablist">
            <button type="button" class="vs-tabs__btn is-active" data-ip-tab="allow" role="tab" aria-selected="true">
                IP 白名单
                <span class="vs-badge vs-badge--info" id="adminIpAllowBadge"><?php echo count($allowRows); ?></span>
            </button>
            <button type="button" class="vs-tabs__btn" data-ip-tab="proxy" role="tab" aria-selected="false"<?php echo $proxyReady ? '' : ' disabled'; ?>>
                出口代理
                <span class="vs-badge vs-badge--success" id="adminIpProxyBadge"><?php echo count($proxyRows); ?></span>
            </button>
        </div>

        <!-- 白名单 -->
        <div class="vs-admin-ip-panel" data-ip-panel="allow" id="adminIpAllowPanel">
            <?php if (!$allowReady): ?>
                <?php vs_render_notice('warning', '', '白名单尚未就绪，请先执行数据库结构更新。', array('compact' => true)); ?>
            <?php else: ?>
                <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminIpAllowEmpty"<?php echo count($allowRows) > 0 ? ' hidden' : ''; ?>>
                    <div class="vs-api-list-empty__card">
                        <h3 class="vs-api-list-empty__title">暂无白名单记录</h3>
                        <p class="vs-api-list-empty__desc">用户在用户中心添加调用 IP 后，将显示在此。</p>
                    </div>
                </div>
                <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminIpAllowSearchEmpty" hidden>
                    <div class="vs-api-list-empty__card">
                        <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                        <p class="vs-api-list-empty__desc">当前搜索下没有白名单，可清空关键词。</p>
                    </div>
                </div>
                <div class="vs-api-list-table-card vs-api-list-table-wrap" id="adminIpAllowTableWrap"<?php echo count($allowRows) === 0 ? ' hidden' : ''; ?>>
                    <div class="vs-table-responsive">
                        <table class="vs-table">
                            <thead>
                                <tr>
                                    <th>归属</th>
                                    <th>IP</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="adminIpAllowTableBody">
                                <?php foreach ($allowRows as $row): ?>
                                    <?php
                                    $owner = vs_admin_ip_owner_ctx($row);
                                    $ip = isset($row['ip']) ? (string) $row['ip'] : '';
                                    $search = vs_admin_ip_allow_search($row);
                                    ?>
                                    <tr data-allow-row data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-ip="<?php echo vs_e($ip); ?>"
                                        data-search="<?php echo vs_e($search); ?>">
                                        <td><?php echo vs_admin_ip_owner_cell_html($owner); ?></td>
                                        <td><code class="vs-log-mono"><?php echo vs_e($ip); ?></code></td>
                                        <td class="vs-content-actions-cell">
                                            <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-remove-allow"
                                                    data-user-id="<?php echo (int) $owner['userid']; ?>"
                                                    data-ip="<?php echo vs_e($ip); ?>">移除</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mobile-admin-ip-cards" id="adminIpAllowCards"<?php echo count($allowRows) === 0 ? ' hidden' : ''; ?>>
                    <?php foreach ($allowRows as $row): ?>
                        <?php
                        $owner = vs_admin_ip_owner_ctx($row);
                        $ip = isset($row['ip']) ? (string) $row['ip'] : '';
                        $search = vs_admin_ip_allow_search($row);
                        ?>
                        <div class="admin-ip-card admin-ip-card--allow" data-allow-row
                             data-user-id="<?php echo (int) $owner['userid']; ?>"
                             data-ip="<?php echo vs_e($ip); ?>"
                             data-search="<?php echo vs_e($search); ?>">
                            <div class="admin-ip-card__header">
                                <?php echo vs_admin_ip_owner_cell_html($owner); ?>
                            </div>
                            <div class="admin-ip-card__meta">
                                <code class="vs-log-mono"><?php echo vs_e($ip); ?></code>
                            </div>
                            <div class="admin-ip-card__actions admin-ip-card__actions--end">
                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-remove-allow"
                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-ip="<?php echo vs_e($ip); ?>">移除</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="vs-api-list-footer" id="adminIpAllowFooter"<?php echo count($allowRows) === 0 ? ' hidden' : ''; ?>>
                    <div class="vs-api-pager" id="adminIpAllowPager">
                        <label class="vs-api-list-pagesize" for="adminIpAllowPageSize">
                            <span class="vs-api-list-pagesize__label">每页</span>
                            <select class="vs-input vs-select vs-api-list-pagesize__select" id="adminIpAllowPageSize" data-vs-pick="sheet">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                        <div class="vs-api-pager__navs" id="adminIpAllowPagerNav"></div>
                    </div>
                    <div class="vs-api-list-total" id="adminIpAllowTotal">共 <?php echo (int) count($allowRows); ?> 条</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 出口代理 -->
        <div class="vs-admin-ip-panel" data-ip-panel="proxy" id="adminIpProxyPanel" hidden>
            <?php if (!$proxyReady): ?>
                <?php vs_render_notice('warning', '', '出口代理尚未就绪，请先执行数据库结构更新。', array('compact' => true)); ?>
            <?php else: ?>
                <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminIpProxyEmpty"<?php echo count($proxyRows) > 0 ? ' hidden' : ''; ?>>
                    <div class="vs-api-list-empty__card">
                        <h3 class="vs-api-list-empty__title">暂无出口代理</h3>
                        <p class="vs-api-list-empty__desc">用户在用户中心配置出口代理后，将显示在此。</p>
                    </div>
                </div>
                <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminIpProxySearchEmpty" hidden>
                    <div class="vs-api-list-empty__card">
                        <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                        <p class="vs-api-list-empty__desc">当前搜索下没有代理，可清空关键词。</p>
                    </div>
                </div>
                <div class="vs-api-list-table-card vs-api-list-table-wrap" id="adminIpProxyTableWrap"<?php echo count($proxyRows) === 0 ? ' hidden' : ''; ?>>
                    <div class="vs-table-responsive">
                        <table class="vs-table vs-admin-ip-proxy-table">
                            <thead>
                                <tr>
                                    <th>归属</th>
                                    <th>备注名称</th>
                                    <th>对接模式</th>
                                    <th>协议</th>
                                    <th>节点 / 链接</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="adminIpProxyTableBody">
                                <?php foreach ($proxyRows as $row): ?>
                                    <?php
                                    $owner = vs_admin_ip_owner_ctx($row);
                                    $pid = isset($row['id']) ? (int) $row['id'] : 0;
                                    $title = isset($row['title']) ? (string) $row['title'] : '';
                                    $code = isset($row['proxycode']) ? (string) $row['proxycode'] : '';
                                    $mode = isset($row['mode']) ? (int) $row['mode'] : 0;
                                    $statusOn = !empty($row['status']);
                                    $modeLabel = isset($row['modelabel']) ? (string) $row['modelabel'] : '';
                                    $protoLabel = isset($row['protolabel']) ? (string) $row['protolabel'] : '';
                                    $ep = vs_admin_ip_proxy_endpoint($row);
                                    $search = vs_admin_ip_proxy_search($row);
                                    $disp = vs_admin_ip_proxy_display_title($title, $code, $pid);
                                    ?>
                                    <tr data-proxy-row data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-proxy-id="<?php echo (int) $pid; ?>"
                                        data-search="<?php echo vs_e($search); ?>">
                                        <td><?php echo vs_admin_ip_owner_cell_html($owner); ?></td>
                                        <td>
                                            <strong><?php echo vs_e($disp['text']); ?></strong>
                                            <?php if (!empty($disp['show_code'])): ?>
                                                <div class="vs-admin-ip-preview">短码 <?php echo vs_e($code); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="vs-badge <?php echo vs_admin_ip_mode_badge_class($mode); ?>">
                                                <?php echo vs_e($modeLabel !== '' ? $modeLabel : '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="vs-badge <?php echo vs_admin_ip_proto_badge_class($protoLabel); ?>">
                                                <?php echo vs_e($protoLabel !== '' ? $protoLabel : '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code class="vs-log-mono vs-admin-ip-endpoint" title="<?php echo vs_e($ep['full']); ?>">
                                                <?php echo vs_e($ep['short']); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <span class="vs-badge <?php echo $statusOn ? 'vs-badge--success' : 'vs-badge--default'; ?>">
                                                <?php echo $statusOn ? '启用' : '禁用'; ?>
                                            </span>
                                        </td>
                                        <td class="vs-content-actions-cell">
                                            <div class="action-btns">
                                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-test"
                                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                                        data-proxy-id="<?php echo (int) $pid; ?>">测试</button>
                                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-toggle"
                                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                                        data-proxy-id="<?php echo (int) $pid; ?>"
                                                        data-status="<?php echo $statusOn ? '0' : '1'; ?>">
                                                    <?php echo $statusOn ? '禁用' : '启用'; ?>
                                                </button>
                                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-edit"
                                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                                        data-proxy-id="<?php echo (int) $pid; ?>">编辑</button>
                                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-proxy-delete"
                                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                                        data-proxy-id="<?php echo (int) $pid; ?>">删除</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mobile-admin-ip-cards" id="adminIpProxyCards"<?php echo count($proxyRows) === 0 ? ' hidden' : ''; ?>>
                    <?php foreach ($proxyRows as $row): ?>
                        <?php
                        $owner = vs_admin_ip_owner_ctx($row);
                        $pid = isset($row['id']) ? (int) $row['id'] : 0;
                        $title = isset($row['title']) ? (string) $row['title'] : '';
                        $code = isset($row['proxycode']) ? (string) $row['proxycode'] : '';
                        $mode = isset($row['mode']) ? (int) $row['mode'] : 0;
                        $statusOn = !empty($row['status']);
                        $modeLabel = isset($row['modelabel']) ? (string) $row['modelabel'] : '';
                        $protoLabel = isset($row['protolabel']) ? (string) $row['protolabel'] : '';
                        $ep = vs_admin_ip_proxy_endpoint($row);
                        $search = vs_admin_ip_proxy_search($row);
                        $disp = vs_admin_ip_proxy_display_title($title, $code, $pid);
                        ?>
                        <div class="admin-ip-card admin-ip-card--proxy" data-proxy-row
                             data-user-id="<?php echo (int) $owner['userid']; ?>"
                             data-proxy-id="<?php echo (int) $pid; ?>"
                             data-search="<?php echo vs_e($search); ?>">
                            <div class="admin-ip-card__header">
                                <?php echo vs_admin_ip_owner_cell_html($owner); ?>
                                <span class="vs-badge <?php echo $statusOn ? 'vs-badge--success' : 'vs-badge--default'; ?>">
                                    <?php echo $statusOn ? '启用' : '禁用'; ?>
                                </span>
                            </div>
                            <div class="admin-ip-card__title-row">
                                <strong class="admin-ip-card__title"><?php echo vs_e($disp['text']); ?></strong>
                                <span class="vs-badge <?php echo vs_admin_ip_mode_badge_class($mode); ?>"><?php echo vs_e($modeLabel !== '' ? $modeLabel : '—'); ?></span>
                                <span class="vs-badge <?php echo vs_admin_ip_proto_badge_class($protoLabel); ?>"><?php echo vs_e($protoLabel !== '' ? $protoLabel : '—'); ?></span>
                            </div>
                            <code class="admin-ip-card__endpoint vs-log-mono" title="<?php echo vs_e($ep['full']); ?>"><?php echo vs_e($ep['short']); ?></code>
                            <?php if (!empty($disp['show_code'])): ?>
                                <p class="admin-ip-card__meta">短码 <code><?php echo vs_e($code); ?></code></p>
                            <?php endif; ?>
                            <div class="admin-ip-card__actions admin-ip-card__actions--end action-btns">
                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-test"
                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-proxy-id="<?php echo (int) $pid; ?>">测试</button>
                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-toggle"
                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-proxy-id="<?php echo (int) $pid; ?>"
                                        data-status="<?php echo $statusOn ? '0' : '1'; ?>">
                                    <?php echo $statusOn ? '禁用' : '启用'; ?>
                                </button>
                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-edit"
                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-proxy-id="<?php echo (int) $pid; ?>">编辑</button>
                                <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-proxy-delete"
                                        data-user-id="<?php echo (int) $owner['userid']; ?>"
                                        data-proxy-id="<?php echo (int) $pid; ?>">删除</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="vs-api-list-footer" id="adminIpProxyFooter"<?php echo count($proxyRows) === 0 ? ' hidden' : ''; ?>>
                    <div class="vs-api-pager" id="adminIpProxyPager">
                        <label class="vs-api-list-pagesize" for="adminIpProxyPageSize">
                            <span class="vs-api-list-pagesize__label">每页</span>
                            <select class="vs-input vs-select vs-api-list-pagesize__select" id="adminIpProxyPageSize" data-vs-pick="sheet">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                        <div class="vs-api-pager__navs" id="adminIpProxyPagerNav"></div>
                    </div>
                    <div class="vs-api-list-total" id="adminIpProxyTotal">共 <?php echo (int) count($proxyRows); ?> 条</div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($proxyReady): ?>
<div class="vs-overlay vs-overlay--lg" id="adminProxyFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="adminProxyFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="adminProxyFormTitle">编辑出口代理</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="adminProxyForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="adminProxyUserId" name="user_id" value="0">
            <input type="hidden" id="adminProxyId" name="id" value="0">
            <div class="vs-admin-ip-form-grid">
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxyTitle">备注名称</label>
                    <input type="text" class="vs-input" id="adminProxyTitle" name="title" maxlength="60" placeholder="例如 快代理隧道">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxyMode">对接模式</label>
                    <select class="vs-input vs-select" id="adminProxyMode" name="mode" data-vs-pick>
                        <option value="0">隧道（主机+端口）</option>
                        <option value="1">提取 API</option>
                    </select>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxyProto">协议</label>
                    <select class="vs-input vs-select" id="adminProxyProto" name="proto" data-vs-pick>
                        <option value="0">HTTP</option>
                        <option value="1">HTTPS</option>
                        <option value="2">SOCKS5</option>
                        <option value="3">SOCKS4</option>
                    </select>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxyStatus">状态</label>
                    <select class="vs-input vs-select" id="adminProxyStatus" name="status" data-vs-pick>
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <div class="vs-form-row vs-admin-ip-field-tunnel">
                    <label class="vs-label" for="adminProxyHost">主机</label>
                    <input type="text" class="vs-input" id="adminProxyHost" name="host" maxlength="255" placeholder="例如 proxy.example.com">
                </div>
                <div class="vs-form-row vs-admin-ip-field-tunnel">
                    <label class="vs-label" for="adminProxyPort">端口</label>
                    <input type="number" class="vs-input" id="adminProxyPort" name="port" min="1" max="65535" placeholder="例如 8080">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxyUser">账号（可选）</label>
                    <input type="text" class="vs-input" id="adminProxyUser" name="username" maxlength="200" placeholder="厂商用户名或带地区参数">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxyPass">密码（可选）</label>
                    <input type="password" class="vs-input" id="adminProxyPass" name="password" maxlength="200" placeholder="留空表示不修改已保存密码" autocomplete="new-password">
                </div>
                <div class="vs-form-row vs-admin-ip-field-extract vs-admin-ip-field-span">
                    <label class="vs-label" for="adminProxyExtract">提取 API 地址</label>
                    <input type="url" class="vs-input" id="adminProxyExtract" name="extract" maxlength="1000" placeholder="https://…/getip?…">
                </div>
                <div class="vs-form-row vs-admin-ip-field-extract">
                    <label class="vs-label" for="adminProxyExtfmt">提取返回格式</label>
                    <select class="vs-input vs-select" id="adminProxyExtfmt" name="extfmt" data-vs-pick>
                        <option value="0">自动识别</option>
                        <option value="1">纯文本 ip:port</option>
                        <option value="2">JSON</option>
                    </select>
                </div>
                <div class="vs-form-row vs-admin-ip-field-extract vs-admin-ip-field-json">
                    <label class="vs-label" for="adminProxyJsonHost">JSON 主机字段（可选）</label>
                    <input type="text" class="vs-input" id="adminProxyJsonHost" name="jsonhost" maxlength="80" placeholder="例如 ip、data.0.ip">
                </div>
                <div class="vs-form-row vs-admin-ip-field-extract vs-admin-ip-field-json">
                    <label class="vs-label" for="adminProxyJsonPort">JSON 端口字段（可选）</label>
                    <input type="text" class="vs-input" id="adminProxyJsonPort" name="jsonport" maxlength="80" placeholder="例如 port">
                </div>
                <div class="vs-form-row vs-admin-ip-field-extract">
                    <label class="vs-label" for="adminProxyTtlmin">节点缓存（分钟）</label>
                    <input type="number" class="vs-input" id="adminProxyTtlmin" name="ttlmin" value="10" min="0" max="10080">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="adminProxySort">排序（越小越前）</label>
                    <input type="number" class="vs-input" id="adminProxySort" name="sort" value="0" min="-9999" max="9999">
                </div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="adminProxyForm" class="vs-btn vs-btn--primary" id="adminProxySaveBtn">保存</button>
        </footer>
    </div>
</div>

<div class="vs-overlay vs-overlay--lg" id="adminProxyTestOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="adminProxyTestTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="adminProxyTestTitle">出口代理测试</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body vs-admin-ip-test-body">
            <p class="vs-form-hint">以经代理访问公网站点为准；终端只展示摘要。</p>
            <div class="vs-admin-ip-test-toolbar">
                <span>测试终端</span>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="adminProxyTestCopy">复制全部</button>
            </div>
            <div class="vs-admin-ip-test-term" id="adminProxyTestLog" role="log" aria-live="polite"></div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--primary" data-overlay-close="1">关闭</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php
vs_admin_layout_end(array('admin-ip.js'));
