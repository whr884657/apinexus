<?php
/**
 * 文件：user/keys.php
 * 作用：用户中心 · 令牌管理（每账号上限由系统设置配置）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$tableReady = ApiKeyManager::tableReady();

/**
 * 从 POST 组装配额/到期字段（无下划线字段名）
 *
 * @return array
 */
function vs_keys_extra_from_post()
{
    $extra = array();
    if (array_key_exists('quota', $_POST)) {
        $extra['quota'] = $_POST['quota'];
    }
    if (array_key_exists('quotafallback', $_POST)) {
        $extra['quotafallback'] = ((int) $_POST['quotafallback'] === 1) ? 1 : 0;
    } else {
        $extra['quotafallback'] = 0;
    }
    if (array_key_exists('expiretime', $_POST)) {
        $raw = trim((string) $_POST['expiretime']);
        $extra['expiretime'] = ($raw === '') ? '' : str_replace('T', ' ', $raw);
    }
    return $extra;
}

/**
 * 配额展示：不限 或 剩余/上限
 *
 * @param array $token formatRow 结果
 * @return string
 */
function vs_token_quota_label(array $token)
{
    $quota = isset($token['quota']) ? (float) $token['quota'] : 0.0;
    if ($quota <= 0) {
        return '不限';
    }
    $left = isset($token['quotaleft']) && $token['quotaleft'] !== null
        ? (float) $token['quotaleft']
        : max(0.0, round($quota - (isset($token['quotaused']) ? (float) $token['quotaused'] : 0.0), 4));
    $fmt = function ($n) {
        return class_exists('PayConfig') ? PayConfig::fmtPoints($n) : (string) $n;
    };
    return $fmt($left) . '/' . $fmt($quota);
}

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
        $extra = vs_keys_extra_from_post();
        $result = ApiKeyManager::create($userId, $remark, $extra);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('令牌已创建', array(
            'token' => $result,
            'count' => ApiKeyManager::countByUser($userId),
            'max'   => ApiKeyManager::maxPerUser(),
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['token_id']) ? (int) $_POST['token_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $remark = isset($_POST['remark']) ? (string) $_POST['remark'] : '';
        $input = array_merge(array('remark' => $remark), vs_keys_extra_from_post());
        $result = ApiKeyManager::saveSettings($id, $userId, $input);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiKeyManager::formatRow(ApiKeyManager::findById($id));
        AjaxResponse::success('令牌已更新', array('token' => $row));
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
            'max'      => ApiKeyManager::maxPerUser(),
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tokens = $tableReady ? ApiKeyManager::listByUser($userId) : array();
$tokenCount = count($tokens);
$canAdd = $tableReady && ApiKeyManager::canCreateMore($userId);

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
    $quota = isset($token['quota']) ? (float) $token['quota'] : 0.0;
    $quotaused = isset($token['quotaused']) ? (float) $token['quotaused'] : 0.0;
    $quotafallback = !empty($token['quotafallback']) ? 1 : 0;
    $expireRaw = isset($token['expiretime']) && $token['expiretime'] !== null ? (string) $token['expiretime'] : '';
    $quotaLabel = vs_token_quota_label($token);
    $expireLabel = isset($token['expire_label']) ? (string) $token['expire_label'] : '永不过期';
    $createShort = (string) $token['createtime'];
    if ($createShort !== '' && strlen($createShort) >= 16) {
        $createShort = substr($createShort, 0, 16);
    }
    ?>
    <div class="vs-api-item vs-token-row<?php echo $enabled ? '' : ' is-token-disabled'; ?>"
         data-token-row="<?php echo $id; ?>"
         data-token-status="<?php echo (int) $token['status']; ?>"
         data-quota="<?php echo vs_e((string) $quota); ?>"
         data-quotaused="<?php echo vs_e((string) $quotaused); ?>"
         data-quotafallback="<?php echo $quotafallback; ?>"
         data-expiretime="<?php echo vs_e($expireRaw); ?>">
        <div class="vs-api-item__icon vs-token-row__icon" aria-hidden="true">
            <span class="vs-token-row__icon-mark">SK</span>
        </div>
        <div class="vs-api-item__title">
            <span class="vs-api-item__name" data-field="remark"><?php echo vs_e($token['remark']); ?></span>
            <span class="vs-token-row__created" data-field="createtime" title="创建时间"><?php echo vs_e($createShort !== '' ? $createShort : '—'); ?></span>
        </div>
        <div class="vs-api-item__endpoint vs-token-row__secret">
            <code class="vs-token-row__code uc-token-secret" data-field="secret" title="悬停查看明文"><?php echo vs_e($token['secret']); ?></code>
            <button type="button" class="vs-token-row__copy vs-key-copy" data-copy="<?php echo vs_e($token['secret']); ?>" title="复制" aria-label="复制令牌">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
        </div>
        <div class="vs-token-row__quota" data-field="quota_label" title="配额（剩余/上限）"><span class="vs-token-row__lbl">配额</span> <?php echo vs_e($quotaLabel); ?></div>
        <div class="vs-token-row__expire" data-field="expire_label" title="有效期"><span class="vs-token-row__lbl">有效期</span> <?php echo vs_e($expireLabel); ?></div>
        <div class="vs-api-item__calls vs-token-row__calls" title="调用次数">调用 <strong data-field="calls"><?php echo (int) $token['calls']; ?></strong></div>
        <div class="vs-api-item__spent vs-token-row__spent" title="累计消耗积分">消耗 <strong data-field="pointsspent"><?php
            $spent = isset($token['pointsspent']) ? (float) $token['pointsspent'] : 0.0;
            echo vs_e(class_exists('PayConfig') ? PayConfig::fmtPoints($spent) : (string) $spent);
        ?></strong></div>
        <div class="vs-api-item__tags">
            <span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>" data-field="status_label"><?php echo vs_e($token['status_label']); ?></span>
            <?php if ($quota > 0 && $quotafallback === 1): ?>
                <span class="vs-api-tag vs-api-tag--fallback" data-field="fallback_label" title="配额用尽后改用账户总积分">回落</span>
            <?php endif; ?>
        </div>
        <div class="vs-api-item__actions vs-token-row__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-token-copy-btn vs-key-copy" data-copy="<?php echo vs_e($token['secret']); ?>">复制</button>
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
