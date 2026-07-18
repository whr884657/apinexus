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
$packagesJson = json_encode($cfg['packages'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($packagesJson === false) {
    $packagesJson = '[]';
}

vs_admin_layout_start('支付配置', 'payment');
?>
<div class="vs-page-head">
    <div>
        <h1 class="vs-page-title">支付配置</h1>
        <p class="vs-page-desc">对接码支付网关，配置可用支付方式、积分兑换比例与充值套餐。</p>
    </div>
</div>

<?php
if (!$cfg['ready']) {
    vs_render_notice('warning', '支付尚未就绪', '请填写网关地址、商户 ID、商户密钥，并至少启用一种支付方式后，用户充值中心才可下单。', array('compact' => true));
}
?>

<form method="post" data-ajax="1" id="payConfigForm" class="vs-panel">
    <input type="hidden" name="action" value="save">
    <div class="vs-panel__body">
        <div class="vs-form-section">
            <h2 class="vs-form-section__title">码支付网关</h2>
            <div class="vs-form-row">
                <label class="vs-label" for="payUrl">接口地址</label>
                <input type="url" class="vs-input" id="payUrl" name="url" value="<?php echo vs_e($cfg['url']); ?>"
                       placeholder="https://pay.example.com" maxlength="255">
                <p class="vs-form-hint">填写码支付站点根地址，不要带 /mapi.php。</p>
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
                <div class="vs-method-checks">
                    <label class="vs-check"><input type="checkbox" name="methods[]" value="alipay"<?php echo in_array('alipay', $methods, true) ? ' checked' : ''; ?>> 支付宝</label>
                    <label class="vs-check"><input type="checkbox" name="methods[]" value="wxpay"<?php echo in_array('wxpay', $methods, true) ? ' checked' : ''; ?>> 微信支付</label>
                    <label class="vs-check"><input type="checkbox" name="methods[]" value="qqpay"<?php echo in_array('qqpay', $methods, true) ? ' checked' : ''; ?>> QQ 钱包</label>
                </div>
            </div>
        </div>

        <div class="vs-form-section">
            <h2 class="vs-form-section__title">积分与套餐</h2>
            <div class="vs-form-row">
                <label class="vs-label" for="payRate">兑换比例</label>
                <input type="number" class="vs-input" id="payRate" name="rate" min="0.0001" step="0.0001"
                       value="<?php echo vs_e(PayConfig::fmtPoints($cfg['rate'])); ?>">
                <p class="vs-form-hint">用户自定义金额充值时：1 元兑换多少积分。套餐可单独指定积分。</p>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="payPackages">充值套餐（JSON）</label>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="payPackages" name="packages" rows="12"
                          spellcheck="false"><?php echo vs_e($packagesJson); ?></textarea>
                <p class="vs-form-hint">数组项字段：id、name、money、points、hot（1 推荐 / 0 普通）。</p>
            </div>
        </div>
    </div>
    <div class="vs-panel__foot" style="padding:16px 20px;border-top:1px solid var(--vs-border,#e2e8f0);display:flex;justify-content:flex-end;">
        <button type="submit" class="vs-btn vs-btn--primary" id="payConfigSaveBtn">保存配置</button>
    </div>
</form>
<?php vs_admin_layout_end(array('finance-payment.js')); ?>
