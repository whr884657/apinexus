<?php
/**
 * 文件：user/keys.php
 * 作用：用户中心 · 令牌管理（每账号最多 3 个）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$tableReady = ApiKeyManager::tableReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!$tableReady) {
        AjaxResponse::error('令牌功能尚未就绪，请联系管理员完成系统升级');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $assertOwner = function ($tokenId) use ($userId) {
        $row = ApiKeyManager::findById($tokenId);
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
        $result = ApiKeyManager::create($userId, $remark);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已创建', array(
            'token' => $result,
            'count' => ApiKeyManager::countByUser($userId),
            'max'   => ApiKeyManager::MAX_PER_USER,
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $remark = isset($_POST['remark']) ? (string) $_POST['remark'] : '';
        $result = ApiKeyManager::updateRemark($id, $userId, $remark);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiKeyManager::formatRow(ApiKeyManager::findById($id));
        AjaxResponse::success('备注已更新', array('token' => $row));
    }

    if ($action === 'reset') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $result = ApiKeyManager::resetSecret($id, $userId);
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
        $status = isset($_POST['status']) ? (int) $_POST['status'] : ApiKeyManager::STATUS_DISABLED;
        $result = ApiKeyManager::setStatus($id, $userId, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiKeyManager::formatRow(ApiKeyManager::findById($id));
        $msg = ((int) $row['status'] === ApiKeyManager::STATUS_ENABLED) ? '令牌已启用' : '令牌已禁用';
        AjaxResponse::success($msg, array('token' => $row));
    }

    if ($action === 'delete') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $result = ApiKeyManager::delete($id, $userId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已删除', array(
            'token_id' => $id,
            'count'    => ApiKeyManager::countByUser($userId),
            'max'      => ApiKeyManager::MAX_PER_USER,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tokens = $tableReady ? ApiKeyManager::listByUser($userId) : array();
$tokenCount = count($tokens);
$canAdd = $tableReady && $tokenCount < ApiKeyManager::MAX_PER_USER;

/**
 * @param array $row
 * @return void
 */
function vs_render_user_token_item(array $row)
{
    $token = ApiKeyManager::formatRow($row);
    if (!$token) {
        return;
    }
    $id = (int) $token['id'];
    $enabled = (int) $token['status'] === ApiKeyManager::STATUS_ENABLED;
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
            <code class="vs-token-row__code vs-key-copy" data-field="secret" data-copy="<?php echo vs_e($token['secret']); ?>" title="点击复制" role="button" tabindex="0"><?php echo vs_e($token['secret']); ?></code>
        </div>
        <div class="vs-api-item__tags">
            <span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>" data-field="status_label"><?php echo vs_e($token['status_label']); ?></span>
        </div>
        <div class="vs-api-item__calls vs-token-row__calls" title="调用次数">调用 <strong data-field="calls"><?php echo (int) $token['calls']; ?></strong></div>
        <div class="vs-api-item__spent vs-token-row__spent" title="累计消耗积分">消耗 <strong data-field="pointsspent"><?php
            $spent = isset($token['pointsspent']) ? (float) $token['pointsspent'] : 0.0;
            echo vs_e(class_exists('PayConfig') ? PayConfig::fmtPoints($spent) : (string) $spent);
        ?></strong></div>
        <div class="vs-api-item__author vs-token-row__time" data-field="createtime" title="创建时间"><?php echo vs_e($token['createtime']); ?></div>
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

vs_user_render_page(
    'keys',
    '令牌管理',
    'keys',
    array(
        'tableReady' => $tableReady,
        'tokens'     => $tokens,
        'tokenCount' => $tokenCount,
    ),
    $headerActions,
    $tableReady ? array('user-keys.js') : array()
);
