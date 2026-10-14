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

<div id="userTokenPage"
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
            <div class="vs-form-row vs-token-scope-field">
                <span class="vs-label">接口范围</span>
                <div class="vs-token-scope-radios" role="radiogroup" aria-label="接口范围">
                    <label class="vs-radio">
                        <input type="radio" name="scope_mode_ui" id="userTokenScopeAll" value="all" checked>
                        <span>不限制</span>
                    </label>
                    <label class="vs-radio">
                        <input type="radio" name="scope_mode_ui" id="userTokenScopeList" value="list">
                        <span>指定范围</span>
                    </label>
                </div>
                <div class="vs-token-scope-summary" id="userTokenScopeSummaryWrap" hidden>
                    <span class="vs-token-scope-summary__text" id="userTokenScopeSummary">尚未选择</span>
                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="userTokenScopePickBtn">选择接口</button>
                </div>
                <?php vs_render_notice(
                    'tip',
                    '',
                    '范围限制只约束用该密钥发起的调用；免费且无需密钥的接口，游客仍可能调用。',
                    array('field' => true, 'compact' => true)
                ); ?>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userTokenForm" class="vs-btn vs-btn--primary" id="userTokenFormSubmitBtn">确定</button>
        </footer>
    </div>
</div>

<div class="vs-nested-picker vs-nested-picker--viewport" id="userTokenScopePicker" hidden aria-hidden="true">
    <div class="vs-nested-picker__backdrop" data-scope-close="1"></div>
    <div class="vs-nested-picker__panel vs-scope-panel" role="dialog" aria-modal="true" aria-labelledby="userTokenScopeTitle">
        <div class="vs-nested-picker__handle" aria-hidden="true"></div>
        <header class="vs-nested-picker__head">
            <h3 class="vs-nested-picker__title" id="userTokenScopeTitle">选择接口 · 已选 0</h3>
            <button type="button" class="vs-nested-picker__close" data-scope-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-nested-picker__body vs-scope-body">
            <div class="vs-scope-toolbar vs-scope-search">
                <label class="vs-scope-search__field" for="userTokenScopeSearchMode">
                    <select class="vs-input vs-select vs-scope-search__field-select" id="userTokenScopeSearchMode" data-scope-search-mode data-vs-pick aria-label="搜索模式">
                        <option value="name" selected>接口名</option>
                        <option value="id">接口 ID</option>
                    </select>
                </label>
                <input type="search" class="vs-input vs-scope-search__input" data-scope-search placeholder="输入接口名…" autocomplete="off">
                <button type="button" class="vs-btn vs-btn--primary vs-scope-search__btn" data-scope-search-btn>搜索</button>
            </div>
            <div class="vs-scope-list" data-scope-list></div>
        </div>
        <footer class="vs-nested-picker__foot vs-scope-foot">
            <button type="button" class="vs-btn vs-btn--default" data-scope-clear>全不选</button>
            <button type="button" class="vs-btn vs-btn--default" data-scope-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" data-scope-ok>确定</button>
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
