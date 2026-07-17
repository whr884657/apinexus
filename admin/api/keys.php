<?php
/**
 * 文件：admin/api/keys.php
 * 作用：管理员查看 / 禁用 / 删除用户令牌
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
$total = count($tokens);

/**
 * @param array $row
 * @return void
 */
function vs_render_admin_token_item(array $row)
{
    $token = ApiKeyManager::formatRow($row);
    if (!$token) {
        return;
    }
    $id = (int) $token['id'];
    $enabled = (int) $token['status'] === ApiKeyManager::STATUS_ENABLED;
    $statusClass = $enabled ? 'is-enabled' : 'is-disabled';
    $username = $token['username'] !== '' ? $token['username'] : ('用户#' . $token['userid']);
    ?>
    <div class="vs-api-item vs-token-row<?php echo $enabled ? '' : ' is-token-disabled'; ?>"
         data-token-row="<?php echo $id; ?>"
         data-token-status="<?php echo (int) $token['status']; ?>">
        <div class="vs-api-item__icon vs-token-row__icon" aria-hidden="true">
            <span class="vs-token-row__icon-mark">SK</span>
        </div>
        <div class="vs-api-item__title">
            <span class="vs-api-item__name" data-field="remark"><?php echo vs_e($token['remark']); ?></span>
            <span class="vs-api-item__id">#<?php echo $id; ?></span>
        </div>
        <div class="vs-api-item__endpoint vs-token-row__secret">
            <code class="vs-token-row__code vs-key-copy" data-field="secret" data-copy="<?php echo vs_e($token['secret']); ?>" title="点击复制" role="button" tabindex="0"><?php echo vs_e($token['secret']); ?></code>
        </div>
        <div class="vs-api-item__tags">
            <span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>" data-field="status_label"><?php echo vs_e($token['status_label']); ?></span>
        </div>
        <div class="vs-api-item__meta">
            <div class="vs-api-item__calls">调用：<strong data-field="calls"><?php echo (int) $token['calls']; ?></strong></div>
            <div class="vs-api-item__author" data-field="username" title="所属用户"><?php echo vs_e($username); ?></div>
        </div>
        <div class="vs-api-item__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-admin-token-reset" data-token-id="<?php echo $id; ?>">重置</button>
            <button type="button" class="vs-btn vs-btn--outline vs-admin-token-toggle" data-token-id="<?php echo $id; ?>" data-status="<?php echo $enabled ? '0' : '1'; ?>">
                <?php echo $enabled ? '禁用' : '启用'; ?>
            </button>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-admin-token-delete" data-token-id="<?php echo $id; ?>">删除</button>
        </div>
    </div>
    <?php
}

vs_admin_layout_start('令牌管理', 'api-keys');
?>

<div class="vs-panel" id="adminTokenPage" data-token-total="<?php echo (int) $total; ?>">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '请先在「系统升级」中执行数据库结构更新，以创建令牌表。', array('compact' => true)); ?>
    <?php else: ?>
        <?php vs_render_notice('info', '', '可查看全站用户令牌，并执行禁用、重置或删除。用户侧每账号最多 3 个令牌。', array('compact' => true)); ?>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="adminTokenEmpty"<?php echo $total > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无令牌</h3>
                <p class="vs-api-list-empty__desc">用户在用户中心「令牌管理」创建后，将显示在此。</p>
            </div>
        </div>

        <div class="vs-api-list-table vs-admin-token-list" id="adminTokenList"<?php echo $total === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-table__body">
                <?php foreach ($tokens as $row): ?>
                    <?php vs_render_admin_token_item($row); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-api-list-footer" id="adminTokenFooter"<?php echo $total === 0 ? ' hidden' : ''; ?>>
    <div class="vs-api-pager" id="adminTokenPager">
        <label class="vs-api-list-pagesize" for="adminTokenPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select vs-api-list-pagesize__select" id="adminTokenPageSize" data-vs-pick>
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
            </select>
        </label>
        <button type="button" class="vs-api-pager__nav" id="adminTokenPrevBtn" aria-label="上一页">上一页</button>
        <div class="vs-api-pager__nums" id="adminTokenPagerNums" role="navigation" aria-label="页码"></div>
        <button type="button" class="vs-api-pager__nav" id="adminTokenNextBtn" aria-label="下一页">下一页</button>
    </div>
    <p class="vs-api-list-stats" id="adminTokenStats">共 <?php echo (int) $total; ?> 个令牌</p>
</div>
<?php endif; ?>

<?php
vs_admin_layout_end($tableReady ? array('vs-pick.js', 'admin-keys.js') : array());

