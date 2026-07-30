<?php
/**
 * Slate 主题 · 用户充值中心页视图
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$ready = !empty($ready);
$payReady = !empty($payReady);
$balance = isset($balance) ? $balance : 0;
$packages = isset($packages) && is_array($packages) ? $packages : array();
$methods = isset($methods) && is_array($methods) ? $methods : array();
$rate = isset($rate) ? (string) $rate : '0';
$payIcons = isset($payIcons) && is_array($payIcons) ? $payIcons : array();
?>
<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', '积分功能尚未就绪，请联系管理员。', array('compact' => true)); ?>
<?php elseif (!$payReady): ?>
    <?php vs_render_notice('warning', '', '充值暂未开放，请稍后再试。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-recharge" id="rechargeApp" data-rate="<?php echo vs_e($rate); ?>">
    <div class="vs-recharge-hero">
        <div class="vs-recharge-hero__label">当前积分</div>
        <div class="vs-recharge-hero__value" id="rechargeBalance"><?php echo vs_e(PayConfig::fmtPoints($balance)); ?></div>
        <div class="vs-recharge-hero__meta">1 元 = <?php echo vs_e($rate); ?> 积分</div>
    </div>

    <div class="vs-recharge-section">
        <div class="vs-recharge-section__title">选择套餐</div>
        <div class="vs-recharge-grid" id="rechargePackages">
            <?php foreach ($packages as $pkg): ?>
                <button type="button" class="vs-recharge-card<?php echo !empty($pkg['hot']) ? ' is-hot' : ''; ?>"
                        data-pkg="<?php echo vs_e($pkg['id']); ?>"
                        data-money="<?php echo vs_e($pkg['money']); ?>"
                        data-points="<?php echo vs_e($pkg['points']); ?>">
                    <?php if (!empty($pkg['hot'])): ?><span class="vs-recharge-card__badge">荐</span><?php endif; ?>
                    <div class="vs-recharge-card__name"><?php echo vs_e($pkg['name']); ?></div>
                    <div class="vs-recharge-card__money">¥<?php echo vs_e($pkg['money']); ?></div>
                    <div class="vs-recharge-card__points"><?php echo vs_e($pkg['points']); ?> 积分</div>
                </button>
            <?php endforeach; ?>
            <button type="button" class="vs-recharge-card vs-recharge-card--custom" id="rechargeCustomCard" data-pkg="">
                <div class="vs-recharge-card__name">自定义金额</div>
                <div class="vs-recharge-card__money">自选</div>
                <div class="vs-recharge-card__points">按比例兑换</div>
            </button>
        </div>
    </div>

    <div class="vs-recharge-section">
        <div class="vs-recharge-section__title">支付方式</div>
        <div class="vs-pay-method-btns vs-pay-method-btns--pick" id="rechargePayMethods" role="group">
            <?php foreach ($methods as $i => $m): ?>
                <button type="button" class="vs-pay-method-btn<?php echo $i === 0 ? ' is-on' : ''; ?>" data-paytype="<?php echo vs_e($m); ?>" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                    <?php echo PayConfig::iconHtml($m); ?>
                    <span><?php echo vs_e(PayConfig::methodLabel($m)); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <input type="hidden" id="rechargePaytype" value="<?php echo vs_e(isset($methods[0]) ? $methods[0] : ''); ?>">
        <input type="hidden" id="rechargePackageId" value="">
    </div>

    <div class="vs-recharge-actions">
        <button type="button" class="vs-btn vs-btn--primary vs-recharge-pay-btn" id="rechargeSubmitBtn" disabled>请先选择套餐</button>
    </div>
</div>

<div class="vs-overlay vs-overlay--form" id="rechargeCustomOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-custom-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="rechargeCustomTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="rechargeCustomTitle">自定义金额</h3>
            <button type="button" class="vs-overlay__close" data-custom-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <div class="vs-form-row">
                <label class="vs-label" for="rechargeMoney">充值金额（元）</label>
                <input type="number" class="vs-input" id="rechargeMoney" min="0.01" step="0.01" placeholder="如 10.00">
            </div>
            <p class="vs-form-hint" id="rechargeCustomHint">预计到账 — 积分</p>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--outline" data-custom-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="rechargeCustomConfirm">确认并支付</button>
        </footer>
    </div>
</div>

<div class="vs-overlay vs-overlay--form" id="rechargePayOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="rechargePayTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="rechargePayTitle">扫码支付</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body vs-recharge-pay-body">
            <div class="vs-recharge-pay-meta">
                <div>订单号 <strong id="payOrderNo"></strong></div>
                <div>实付 <strong>¥<span id="payMoney"></span></strong> · <span id="payTypeLabel"></span></div>
                <div>预计 <strong id="payPoints"></strong> 积分</div>
            </div>
            <div class="vs-recharge-qr">
                <img id="payQrImg" alt="支付二维码" width="200" height="200">
                <div class="vs-recharge-qr__logo" id="payQrLogo" aria-hidden="true"></div>
            </div>
            <p class="vs-form-hint">请使用对应 App 扫码；支付完成后将自动到账。</p>
        </div>
        <footer class="vs-overlay__foot vs-recharge-pay-foot">
            <button type="button" class="vs-btn vs-btn--outline" id="payCancelBtn">取消支付</button>
            <button type="button" class="vs-btn vs-btn--primary" id="payCheckBtn">我已支付</button>
        </footer>
    </div>
</div>
<script type="application/json" id="rechargePayIcons"><?php
    echo json_encode(array(
        'alipay' => isset($payIcons['alipay']) ? $payIcons['alipay'] : '',
        'wxpay'  => isset($payIcons['wxpay']) ? $payIcons['wxpay'] : '',
        'qqpay'  => isset($payIcons['qqpay']) ? $payIcons['qqpay'] : '',
    ), JSON_UNESCAPED_UNICODE);
?></script>
<?php endif; ?>
