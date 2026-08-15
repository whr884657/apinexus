<?php
/**
 * 文件：admin/api/keys.php
 * 作用：管理员令牌管理（电脑表格 + 手机卡片；搜索/状态筛选）
 */

require_once dirname(__DIR__) . '/init.php';

$tableReady = ApiKeyManager::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!$tableReady) {
        AjaxResponse::error('令牌表尚未就绪，请先执行数据库结构更新');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;

    if ($action === 'set_status') {
        $status = isset($_POST['status']) ? (int) $_POST['status'] : ApiKeyManager::STATUS_DISABLED;
        $result = ApiKeyManager::setStatus($id, 0, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiKeyManager::formatRow(ApiKeyManager::findById($id));
        $msg = ((int) $row['status'] === ApiKeyManager::STATUS_ENABLED) ? '令牌已启用' : '令牌已禁用';
        AjaxResponse::success($msg, array('token' => $row));
    }

    if ($action === 'delete') {
        $result = ApiKeyManager::delete($id, 0);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已删除', array('token_id' => $id));
    }

    if ($action === 'reset') {
        $result = ApiKeyManager::resetSecret($id, 0);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已重置', array('token' => $result));
    }

    AjaxResponse::error('无效操作', 400);
}

$tokens = $tableReady ? ApiKeyManager::listAll() : array();

/**
 * @param array $row
 * @return array
 */
function vs_admin_key_row_ctx(array $row)
{
    $token = ApiKeyManager::formatRow($row);
    if (!$token) {
        return array();
    }
    $id = (int) $token['id'];
    $enabled = (int) $token['status'] === ApiKeyManager::STATUS_ENABLED;
    $username = $token['username'] !== '' ? $token['username'] : ('用户#' . $token['userid']);
    $avatar = mb_substr($username, 0, 1, 'UTF-8');
    $time = $token['createtime'];
    if ($time !== '' && strlen($time) >= 16) {
        $time = substr($time, 0, 16);
    }
    $search = mb_strtolower($token['secret'] . ' ' . $username . ' ' . $token['remark'] . ' #' . $id, 'UTF-8');
    $spent = isset($token['pointsspent']) ? (float) $token['pointsspent'] : 0.0;
    $spentFmt = class_exists('PayConfig') ? PayConfig::fmtPoints($spent) : (string) $spent;

    return array(
        'id'              => $id,
        'enabled'         => $enabled,
        'secret'          => $token['secret'],
        'username'        => $username,
        'avatar'          => $avatar,
        'time'            => $time,
        'calls'           => (int) $token['calls'],
        'pointsspent'     => $spent,
        'pointsspent_fmt' => $spentFmt,
        'remark'          => $token['remark'],
        'search'          => $search,
        'status'          => (int) $token['status'],
    );
}

/**
 * @param array $ctx
 * @return string
 */
function vs_admin_key_actions_html(array $ctx)
{
    $id = (int) $ctx['id'];
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-token-reset" data-token-id="' . $id . '">重置</button>';
    if ($ctx['enabled']) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-admin-token-toggle" data-token-id="'
            . $id . '" data-status="0">紧急禁用</button>';
    } else {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-admin-token-toggle" data-token-id="'
            . $id . '" data-status="1">启用</button>';
    }
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-admin-token-delete" data-token-id="'
        . $id . '">删除</button>';
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_admin_key_desktop_row(array $ctx)
{
    if ($ctx === array()) {
        return;
    }
    $attrs = ' data-token-row="' . (int) $ctx['id'] . '"'
        . ' data-token-status="' . (int) $ctx['status'] . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"';
    ?>
    <tr<?php echo $attrs; ?>>
        <td>
            <div class="key-cell">
                <code class="key-cell__code" data-field="secret" data-copy="<?php echo vs_e($ctx['secret']); ?>"><?php echo vs_e($ctx['secret']); ?></code>
                <button type="button" class="key-cell__copy vs-key-copy" data-copy="<?php echo vs_e($ctx['secret']); ?>" title="复制" aria-label="复制令牌">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
            </div>
        </td>
        <td>
            <div class="user-cell">
                <div class="user-cell__avatar" data-field="avatar"><?php echo vs_e($ctx['avatar']); ?></div>
                <span class="user-cell__name" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
            </div>
        </td>
        <td><span class="time-cell" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></td>
        <td class="vs-api-keys-stat-cell"><span data-field="calls"><?php echo number_format((int) $ctx['calls']); ?></span></td>
        <td class="vs-api-keys-stat-cell"><span data-field="pointsspent"><?php echo vs_e($ctx['pointsspent_fmt']); ?></span></td>
        <td>
            <span class="vs-badge <?php echo $ctx['enabled'] ? 'vs-badge--success' : 'vs-badge--error'; ?>" data-field="status_label">
                <?php echo $ctx['enabled'] ? '正常' : '已禁用'; ?>
            </span>
        </td>
        <td class="vs-api-keys-actions-cell" data-field="actions">
            <?php echo vs_admin_key_actions_html($ctx); ?>
        </td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_admin_key_mobile_card(array $ctx)
{
    if ($ctx === array()) {
        return;
    }
    $attrs = ' data-token-row="' . (int) $ctx['id'] . '"'
        . ' data-token-status="' . (int) $ctx['status'] . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"';
    ?>
    <div class="key-card"<?php echo $attrs; ?>>
        <div class="key-card__header">
            <code class="key-card__key" data-field="secret" data-copy="<?php echo vs_e($ctx['secret']); ?>"><?php echo vs_e($ctx['secret']); ?></code>
            <span class="vs-badge <?php echo $ctx['enabled'] ? 'vs-badge--success' : 'vs-badge--error'; ?>" data-field="status_label">
                <?php echo $ctx['enabled'] ? '正常' : '已禁用'; ?>
            </span>
        </div>
        <div class="key-card__info">
            <div class="key-card__info-item"><span class="key-card__info-label">所属用户</span><span data-field="username"><?php echo vs_e($ctx['username']); ?></span></div>
            <div class="key-card__info-item"><span class="key-card__info-label">创建时间</span><span data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></div>
            <div class="key-card__stats" aria-label="调用与消耗">
                <div class="key-card__stat"><span class="key-card__info-label">调用</span><strong data-field="calls"><?php echo number_format((int) $ctx['calls']); ?></strong></div>
                <div class="key-card__stat"><span class="key-card__info-label">消耗</span><strong data-field="pointsspent"><?php echo vs_e($ctx['pointsspent_fmt']); ?></strong></div>
            </div>
        </div>
        <div class="key-card__actions" data-field="actions">
            <button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-key-copy" data-copy="<?php echo vs_e($ctx['secret']); ?>">复制</button>
            <?php echo vs_admin_key_actions_html($ctx); ?>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="adminKeySearchInput"
                   placeholder="搜索令牌 Key 或用户名..." autocomplete="off">
        </div>
        <select class="vs-input vs-select vs-toolbar-filter" id="adminKeyStatusFilter" data-vs-pick aria-label="状态筛选">
            <option value="">全部状态</option>
            <option value="1">正常</option>
            <option value="0">已禁用</option>
        </select>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('令牌管理', 'api-keys', $headerActions);
?>

<div id="apiKeysPage" data-token-total="<?php echo (int) count($tokens); ?>">
    <?php if (!$tableReady): ?>
        <div class="vs-panel">
            <?php vs_render_notice('warning', '', '请先在「系统升级」中执行数据库结构更新，以创建令牌表。', array('compact' => true)); ?>
        </div>
    <?php else: ?>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminKeyEmpty"<?php echo count($tokens) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无令牌</h3>
                <p class="vs-api-list-empty__desc">用户在用户中心「令牌管理」创建后，将显示在此。可紧急禁用泄露的令牌。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminKeySearchEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前搜索或状态下没有令牌，可清空条件重试。</p>
            </div>
        </div>

        <div class="vs-api-list-table-card vs-api-list-table-wrap" id="adminKeyTableWrap"<?php echo count($tokens) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-table-responsive">
                <table class="vs-table vs-api-keys-table">
                    <thead>
                        <tr>
                            <th>令牌 Key</th>
                            <th>所属用户</th>
                            <th>创建时间</th>
                            <th>调用</th>
                            <th>积分消耗</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="adminKeyBody">
                        <?php foreach ($tokens as $row): ?>
                            <?php vs_render_admin_key_desktop_row(vs_admin_key_row_ctx($row)); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-keys-cards" id="adminKeyMobile"<?php echo count($tokens) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($tokens as $row): ?>
                <?php vs_render_admin_key_mobile_card(vs_admin_key_row_ctx($row)); ?>
            <?php endforeach; ?>
        </div>

        <div class="vs-api-list-footer" id="adminKeyFooter"<?php echo count($tokens) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-pager" id="adminKeyPager">
                <label class="vs-api-list-pagesize" for="adminKeyPageSize">
                    <span class="vs-api-list-pagesize__label">每页</span>
                    <select class="vs-input vs-select vs-api-list-pagesize__select" id="adminKeyPageSize" data-vs-pick="sheet">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <div class="vs-api-pager__navs" id="adminKeyPagerNav">
                    <button type="button" class="vs-api-pager__nav" id="adminKeyPrevBtn" aria-label="上一页">上一页</button>
                    <div class="vs-api-pager__nums" id="adminKeyPagerNums" role="navigation" aria-label="页码"></div>
                    <button type="button" class="vs-api-pager__nav" id="adminKeyNextBtn" aria-label="下一页">下一页</button>
                </div>
            </div>
            <p class="vs-api-list-stats" id="adminKeyStats">共 <?php echo (int) count($tokens); ?> 条</p>
        </div>
    <?php endif; ?>
</div>

<?php
vs_admin_layout_end($tableReady ? array('vs-pick.js', 'admin-keys.js') : array());
