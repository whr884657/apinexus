<?php
/**
 * 文件：user/tokens.php
 * 作用：用户中心 · 令牌管理（每账号最多 3 个）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$tableReady = TokenManager::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!$tableReady) {
        AjaxResponse::error('令牌功能尚未就绪，请联系管理员完成系统升级');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $assertOwner = function ($tokenId) use ($userId) {
        $row = TokenManager::findById($tokenId);
        if (!$row) {
            return '令牌不存在';
        }
        if ((int) $row['userid'] !== $userId) {
            return '无权操作该令牌';
        }
        return $row;
    };

    if ($action === 'create') {
        $remark = isset($_POST['remark']) ? (string) $_POST['remark'] : '';
        $result = TokenManager::create($userId, $remark);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已创建', array(
            'token' => $result,
            'count' => TokenManager::countByUser($userId),
            'max'   => TokenManager::MAX_PER_USER,
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $remark = isset($_POST['remark']) ? (string) $_POST['remark'] : '';
        $result = TokenManager::updateRemark($id, $userId, $remark);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = TokenManager::formatRow(TokenManager::findById($id));
        AjaxResponse::success('备注已更新', array('token' => $row));
    }

    if ($action === 'reset') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $result = TokenManager::resetSecret($id, $userId);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已重置', array('token' => $result));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $status = isset($_POST['status']) ? (int) $_POST['status'] : TokenManager::STATUS_DISABLED;
        $result = TokenManager::setStatus($id, $userId, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = TokenManager::formatRow(TokenManager::findById($id));
        $msg = ((int) $row['status'] === TokenManager::STATUS_ENABLED) ? '令牌已启用' : '令牌已禁用';
        AjaxResponse::success($msg, array('token' => $row));
    }

    if ($action === 'delete') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $result = TokenManager::delete($id, $userId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已删除', array(
            'token_id' => $id,
            'count'    => TokenManager::countByUser($userId),
            'max'      => TokenManager::MAX_PER_USER,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tokens = $tableReady ? TokenManager::listByUser($userId) : array();
$tokenCount = count($tokens);
$canAdd = $tableReady && $tokenCount < TokenManager::MAX_PER_USER;

/**
 * @param array $row
 * @return void
 */
function vs_render_user_token_item(array $row)
{
    $token = TokenManager::formatRow($row);
    if (!$token) {
        return;
    }
    $id = (int) $token['id'];
    $enabled = (int) $token['status'] === TokenManager::STATUS_ENABLED;
    $statusClass = $enabled ? 'is-enabled' : 'is-disabled';
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
            <code class="vs-token-row__code" data-field="secret" title="<?php echo vs_e($token['secret']); ?>"><?php echo vs_e($token['secret']); ?></code>
        </div>
        <div class="vs-api-item__tags">
            <span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>" data-field="status_label"><?php echo vs_e($token['status_label']); ?></span>
        </div>
        <div class="vs-api-item__meta">
            <div class="vs-api-item__calls" title="调用次数">调用：<strong data-field="calls"><?php echo (int) $token['calls']; ?></strong></div>
            <div class="vs-api-item__author" data-field="createtime" title="创建时间"><?php echo vs_e($token['createtime']); ?></div>
        </div>
        <div class="vs-api-item__actions vs-token-row__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-token-edit" data-token-id="<?php echo $id; ?>">编辑</button>
            <button type="button" class="vs-btn vs-btn--outline vs-token-reset" data-token-id="<?php echo $id; ?>">重置</button>
            <button type="button" class="vs-btn vs-btn--outline vs-token-toggle" data-token-id="<?php echo $id; ?>" data-status="<?php echo $enabled ? '0' : '1'; ?>">
                <?php echo $enabled ? '禁用' : '启用'; ?>
            </button>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-token-delete" data-token-id="<?php echo $id; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    $headerActions = '<button type="button" class="vs-btn vs-btn--primary" id="userTokenAddBtn"'
        . ($canAdd ? '' : ' disabled title="已达上限"')
        . '>添加令牌</button>';
}

vs_user_layout_start('令牌管理', 'tokens', $headerActions);
$assetBase = rtrim(vs_base_url(), '/');
?>
<link rel="stylesheet" href="<?php echo vs_e($assetBase); ?>/assets/css/admin.css?v=<?php echo VS_VERSION; ?>">

<div class="vs-panel" id="userTokenPage"
     data-token-count="<?php echo (int) $tokenCount; ?>"
     data-token-max="<?php echo (int) TokenManager::MAX_PER_USER; ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '令牌功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
    <?php else: ?>
        <?php
        vs_render_notice(
            'info',
            '',
            '每个账号最多 ' . TokenManager::MAX_PER_USER . ' 个令牌。令牌以 SK- 开头；禁用后即使泄露也无法继续调用。',
            array('compact' => true)
        );
        ?>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="userTokenEmpty"<?php echo $tokenCount > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无令牌</h3>
                <p class="vs-api-list-empty__desc">点击右上角「添加令牌」，填写名称后系统将自动生成密钥。</p>
            </div>
        </div>

        <div class="vs-api-list-table vs-user-token-list" id="userTokenList"<?php echo $tokenCount === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-table__body">
                <?php foreach ($tokens as $row): ?>
                    <?php vs_render_user_token_item($row); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vs-api-list-footer" id="userTokenFooter"<?php echo $tokenCount === 0 ? ' hidden' : ''; ?>>
            <p class="vs-api-list-stats" id="userTokenStats">共 <?php echo (int) $tokenCount; ?> 个令牌（上限 <?php echo (int) TokenManager::MAX_PER_USER; ?>）</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--form" id="userTokenFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="userTokenFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="userTokenFormTitle">添加令牌</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="userTokenForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="userTokenFormId" name="token_id" value="">
            <div class="vs-form-row">
                <label class="vs-label" for="userTokenFormRemark">令牌名称 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userTokenFormRemark" name="remark" maxlength="100" required
                       placeholder="例如：测试环境 / 给合作方用" autofocus>
                <p class="vs-form-hint">仅用于区分用途，不会影响调用。</p>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userTokenForm" class="vs-btn vs-btn--primary" id="userTokenFormSubmitBtn">确定</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php
vs_user_layout_end($tableReady ? array('user-tokens.js') : array());
