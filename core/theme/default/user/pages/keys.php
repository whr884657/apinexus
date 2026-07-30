<?php
/**
 * 默认主题 · 用户令牌管理页视图
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$tableReady = !empty($tableReady);
$tokens = isset($tokens) && is_array($tokens) ? $tokens : array();
$tokenCount = isset($tokenCount) ? (int) $tokenCount : count($tokens);
?>

<div class="vs-panel" id="userTokenPage"
     data-token-count="<?php echo (int) $tokenCount; ?>"
     data-token-max="<?php echo (int) ApiKeyManager::MAX_PER_USER; ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '令牌功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
    <?php else: ?>
        <?php
        vs_render_notice(
            'info',
            '',
            '每个账号最多 ' . ApiKeyManager::MAX_PER_USER . ' 个令牌。令牌以 sk- 开头；禁用后即使泄露也无法继续调用。',
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
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-api-list-footer" id="userTokenFooter"<?php echo $tokenCount === 0 ? ' hidden' : ''; ?>>
    <p class="vs-api-list-stats" id="userTokenStats">共 <?php echo (int) $tokenCount; ?> 个令牌（上限 <?php echo (int) ApiKeyManager::MAX_PER_USER; ?>）</p>
</div>
<?php endif; ?>

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
