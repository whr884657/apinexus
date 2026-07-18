<?php
/**
 * 文件：admin/finance/payment.php
 * 作用：码支付与积分充值配置
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'save') {
        AjaxResponse::error('无效操作', 400);
    }

    $methods = array();
    if (isset($_POST['methods']) && is_array($_POST['methods'])) {
        $methods = $_POST['methods'];
    }

    $packagesRaw = isset($_POST['packages']) ? (string) $_POST['packages'] : '[]';
    $result = PayConfig::save(array(
        'url'             => isset($_POST['url']) ? $_POST['url'] : '',
        'pid'             => isset($_POST['pid']) ? $_POST['pid'] : '',
        'key'             => isset($_POST['key']) ? $_POST['key'] : '',
        'channel_alipay'  => isset($_POST['channel_alipay']) ? $_POST['channel_alipay'] : '',
        'channel_wxpay'   => isset($_POST['channel_wxpay']) ? $_POST['channel_wxpay'] : '',
        'channel_qqpay'   => isset($_POST['channel_qqpay']) ? $_POST['channel_qqpay'] : '',
        'methods'         => $methods,
        'rate'            => isset($_POST['rate']) ? $_POST['rate'] : '1000',
        'packages'        => $packagesRaw,
    ));
    if (!is_array($result)) {
        AjaxResponse::error($result);
    }
    AjaxResponse::success('支付配置已保存', array('config' => $result));
}

$cfg = PayConfig::all();
$methods = $cfg['methods'];
$packagesJson = json_encode($cfg['packages'], JSON_UNESCAPED_UNICODE);
if ($packagesJson === false) {
    $packagesJson = '[]';
}

$payIcons = array(
    'alipay' => '<svg class="vs-pay-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#1677FF"/><path fill="#fff" d="M6.2 15.4c1.7 1.1 3.5 1.7 5.3 1.7 2.7 0 4.6-1.3 4.6-3.2 0-1.5-1.1-2.5-3.3-3.1l-1.7-.4c-.9-.2-1.3-.5-1.3-1 0-.6.6-1 1.6-1 1.1 0 2.2.3 3.3.9l.7-1.8c-1.1-.6-2.4-.9-3.9-.9-2.5 0-4.1 1.3-4.1 3.1 0 1.5 1.1 2.5 3.2 3l1.7.4c1 .2 1.5.5 1.5 1.1 0 .6-.7 1.1-1.9 1.1-1.4 0-2.8-.5-4.1-1.4l-.6 1.5z"/></svg>',
    'wxpay'  => '<svg class="vs-pay-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#07C160"/><path fill="#fff" d="M9.4 8.2c-2.7 0-4.9 1.8-4.9 4 0 1.3.7 2.4 1.9 3.2l-.5 1.5 1.7-.9c.5.1 1 .2 1.5.2.2 0 .4 0 .6-.1-.1-.3-.2-.7-.2-1 0-2.1 2-3.8 4.5-3.8.2 0 .4 0 .6.1C14.1 9.7 11.9 8.2 9.4 8.2zm-1.9 2.1a.7.7 0 110 1.4.7.7 0 010-1.4zm3.5 0a.7.7 0 110 1.4.7.7 0 010-1.4zm4.4.9c-2.3 0-4.1 1.5-4.1 3.3 0 1.8 1.8 3.3 4.1 3.3.5 0 1-.1 1.4-.2l1.4.8-.4-1.3c1-.7 1.6-1.6 1.6-2.6 0-1.8-1.8-3.3-4-3.3zm-1.5 1.8a.6.6 0 110 1.2.6.6 0 010-1.2zm3 0a.6.6 0 110 1.2.6.6 0 010-1.2z"/></svg>',
    'qqpay'  => '<svg class="vs-pay-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#12B7F5"/><path fill="#fff" d="M12 5.5c-2 0-3.5 1.6-3.5 4.1 0 1.6.5 3.1 1.1 4.2-.9.4-1.5 1.1-1.5 1.9 0 .8.6 1.4 1.7 1.7-.1.4-.3 1-.3 1.4 0 .7.5 1.2 1.4 1.2.7 0 1.3-.3 1.7-.8.4.5 1 .8 1.7.8.9 0 1.4-.5 1.4-1.2 0-.4-.2-1-.3-1.4 1.1-.3 1.7-.9 1.7-1.7 0-.8-.6-1.5-1.5-1.9.6-1.1 1.1-2.6 1.1-4.2 0-2.5-1.5-4.1-3.5-4.1z"/></svg>',
);

vs_admin_layout_start('支付配置', 'payment');

if (!$cfg['ready']) {
    vs_render_notice('warning', '支付尚未就绪', '请填写网关地址、商户 ID、商户密钥，并至少启用一种支付方式。', array('compact' => true));
}
?>

<form method="post" data-ajax="1" id="payConfigForm" class="vs-panel vs-finance-panel">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="packages" id="payPackages" value="<?php echo vs_e($packagesJson); ?>">
    <div class="vs-panel__body">
        <div class="vs-form-section">
            <h2 class="vs-form-section__title">码支付网关</h2>
            <div class="vs-form-row">
                <label class="vs-label" for="payUrl">接口地址</label>
                <input type="url" class="vs-input" id="payUrl" name="url" value="<?php echo vs_e($cfg['url']); ?>"
                       placeholder="https://pay.example.com" maxlength="255">
            </div>
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="payPid">商户 ID</label>
                    <input type="text" class="vs-input" id="payPid" name="pid" value="<?php echo vs_e($cfg['pid']); ?>" maxlength="64" autocomplete="off">
                </div>
                <div>
                    <label class="vs-label" for="payKey">商户密钥</label>
                    <input type="password" class="vs-input" id="payKey" name="key" value="<?php echo vs_e($cfg['key']); ?>" maxlength="128" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="vs-form-section">
            <h2 class="vs-form-section__title">渠道与支付方式</h2>
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="payChAlipay">支付宝渠道 ID</label>
                    <input type="text" class="vs-input" id="payChAlipay" name="channel_alipay"
                           value="<?php echo vs_e($cfg['channel']['alipay']); ?>" maxlength="32" placeholder="可选">
                </div>
                <div>
                    <label class="vs-label" for="payChWx">微信渠道 ID</label>
                    <input type="text" class="vs-input" id="payChWx" name="channel_wxpay"
                           value="<?php echo vs_e($cfg['channel']['wxpay']); ?>" maxlength="32" placeholder="可选">
                </div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="payChQq">QQ 钱包渠道 ID</label>
                <input type="text" class="vs-input" id="payChQq" name="channel_qqpay"
                       value="<?php echo vs_e($cfg['channel']['qqpay']); ?>" maxlength="32" placeholder="可选">
            </div>
            <div class="vs-form-row">
                <span class="vs-label">启用支付方式</span>
                <div class="vs-pay-method-btns" id="payMethodBtns" role="group" aria-label="支付方式">
                    <?php
                    $allMethods = array('alipay' => '支付宝', 'wxpay' => '微信支付', 'qqpay' => 'QQ 钱包');
                    foreach ($allMethods as $code => $label):
                        $on = in_array($code, $methods, true);
                        ?>
                        <button type="button" class="vs-pay-method-btn<?php echo $on ? ' is-on' : ''; ?>" data-method="<?php echo vs_e($code); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
                            <?php echo $payIcons[$code]; ?>
                            <span><?php echo vs_e($label); ?></span>
                        </button>
                        <input type="checkbox" class="vs-pay-method-input" name="methods[]" value="<?php echo vs_e($code); ?>"<?php echo $on ? ' checked' : ''; ?> hidden>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="vs-form-section">
            <h2 class="vs-form-section__title">积分与套餐</h2>
            <div class="vs-form-row">
                <label class="vs-label" for="payRate">兑换比例</label>
                <input type="number" class="vs-input" id="payRate" name="rate" min="0.0001" step="0.0001"
                       value="<?php echo vs_e(PayConfig::fmtPoints($cfg['rate'])); ?>">
                <p class="vs-form-hint">自定义金额：1 元兑换多少积分。</p>
            </div>
            <div class="vs-form-row">
                <div class="vs-pkg-editor-head">
                    <span class="vs-label">充值套餐</span>
                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="payPkgAddBtn">添加套餐</button>
                </div>
                <div class="vs-pkg-editor-list" id="payPkgList"></div>
            </div>
        </div>
    </div>
    <div class="vs-panel__foot vs-finance-form-foot">
        <button type="submit" class="vs-btn vs-btn--primary" id="payConfigSaveBtn">保存配置</button>
    </div>
</form>

<div class="vs-overlay vs-overlay--form" id="payPkgOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="payPkgTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="payPkgTitle">编辑套餐</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <input type="hidden" id="payPkgEditIndex" value="-1">
            <div class="vs-form-row">
                <label class="vs-label" for="payPkgName">套餐名称</label>
                <input type="text" class="vs-input" id="payPkgName" maxlength="64" placeholder="如 体验包">
            </div>
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="payPkgMoney">金额（元）</label>
                    <input type="number" class="vs-input" id="payPkgMoney" min="0.01" step="0.01">
                </div>
                <div>
                    <label class="vs-label" for="payPkgPoints">积分</label>
                    <input type="number" class="vs-input" id="payPkgPoints" min="0.0001" step="0.0001">
                </div>
            </div>
            <div class="vs-form-row">
                <label class="vs-check"><input type="checkbox" id="payPkgHot"> 推荐套餐</label>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--outline" data-overlay-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="payPkgSaveBtn">确定</button>
        </footer>
    </div>
</div>
<?php vs_admin_layout_end(array('finance-payment.js')); ?>
