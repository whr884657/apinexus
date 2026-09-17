<?php
/**
 * 来自主题四（four）· 用户令牌管理页视图
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
     data-token-max="<?php echo (int) ApiKeyManager::maxPerUser(); ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '令牌功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
    <?php else: ?>
        <?php
        vs_render_notice(
            'info',
            '',
            '每个账号最多 ' . ApiKeyManager::maxPerUser() . ' 个令牌。令牌以 sk- 开头；禁用后即使泄露也无法继续调用。',
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
    <p class="vs-api-list-stats" id="userTokenStats">共 <?php echo (int) $tokenCount; ?> 个令牌（上限 <?php echo (int) ApiKeyManager::maxPerUser(); ?>）</p>
</div>
<?php endif; ?>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--lg" id="userTokenFormOverlay" hidden aria-hidden="true">
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
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userTokenFormQuota">配额（积分）</label>
                <input type="number" class="vs-input" id="userTokenFormQuota" name="quota" min="0" step="0.0001"
                       value="0" placeholder="0 = 不限制">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userTokenFormExpireBtn">有效期</label>
                <input type="hidden" id="userTokenFormExpire" name="expiretime" value="">
                <button type="button" class="vs-input vs-datetime__trigger" id="userTokenFormExpireBtn"
                        aria-haspopup="dialog" aria-expanded="false">永不过期</button>
            </div>
            <div class="vs-form-row">
                <label class="vs-checkbox vs-datetime-fallback">
                    <input type="checkbox" id="userTokenFormFallback" name="quotafallback" value="1">
                    <span>配额用尽后改用账户总积分</span>
                </label>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userTokenForm" class="vs-btn vs-btn--primary" id="userTokenFormSubmitBtn">确定</button>
        </footer>
    </div>
</div>

<div class="vs-nested-picker vs-nested-picker--viewport" id="userTokenDatetimePicker" hidden aria-hidden="true">
    <div class="vs-nested-picker__backdrop" data-dt-close="1"></div>
    <div class="vs-nested-picker__panel" role="dialog" aria-modal="true" aria-labelledby="userTokenDatetimeTitle">
        <div class="vs-nested-picker__handle" aria-hidden="true"></div>
        <header class="vs-nested-picker__head">
            <h3 class="vs-nested-picker__title" id="userTokenDatetimeTitle">选择有效期</h3>
            <button type="button" class="vs-nested-picker__close" data-dt-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-nested-picker__body vs-datetime-picker-body">
            <div class="vs-datetime__head">
                <button type="button" class="vs-datetime__nav" data-dt-nav="-1" aria-label="上月">‹</button>
                <span class="vs-datetime__title" data-dt-title></span>
                <button type="button" class="vs-datetime__nav" data-dt-nav="1" aria-label="下月">›</button>
            </div>
            <div class="vs-datetime__weekdays" aria-hidden="true">
                <span>日</span><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span>
            </div>
            <div class="vs-datetime__days" data-dt-days></div>
            <div class="vs-datetime__hm">
                <select id="userTokenExpireHour" class="vs-select" data-vs-pick aria-label="时">
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <option value="<?php echo sprintf('%02d', $h); ?>"><?php echo sprintf('%02d', $h); ?></option>
                    <?php endfor; ?>
                </select>
                <span class="vs-datetime__hm-sep" aria-hidden="true">:</span>
                <select id="userTokenExpireMinute" class="vs-select" data-vs-pick aria-label="分">
                    <?php for ($m = 0; $m < 60; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <footer class="vs-nested-picker__foot">
            <button type="button" class="vs-btn vs-btn--default" data-dt-clear>永不过期</button>
            <button type="button" class="vs-btn vs-btn--primary" data-dt-ok>确定</button>
        </footer>
    </div>
</div>
<?php endif; ?>
