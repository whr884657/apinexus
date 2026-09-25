<?php
/**
 * 主题二 slate · 用户充值中心（积分充值 / 卡密兑换；说明区与赠%角标）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$ready = !empty($ready);
$payReady = !empty($payReady);
$cardkeyReady = !empty($cardkeyReady);
$balance = isset($balance) ? $balance : 0;
$packages = isset($packages) && is_array($packages) ? $packages : array();
$methods = isset($methods) && is_array($methods) ? $methods : array();
$rate = isset($rate) ? (string) $rate : '0';
$customBonusJson = isset($customBonusJson) ? (string) $customBonusJson : '[]';
$payIcons = isset($payIcons) && is_array($payIcons) ? $payIcons : array();
$tipHtml = isset($tipHtml) && is_array($tipHtml) ? $tipHtml : array();
$showTabs = $payReady || $cardkeyReady;
$defaultTab = $payReady ? 'pay' : 'cardkey';

$tipOrder = array('package', 'custom', 'cardkey');
$hasTips = false;
foreach ($tipOrder as $tk) {
    if (!empty($tipHtml[$tk])) {
        $hasTips = true;
        break;
    }
}
?>
<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', '积分功能尚未就绪，请联系管理员。', array('compact' => true)); ?>
<?php else: ?>
<?php if ($hasTips): ?>
<link rel="stylesheet" href="<?php echo vs_e(vs_site_path('/core/markdown/assets/css/markdown-render.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">
<?php endif; ?>
<div class="vs-recharge" id="rechargeApp" data-rate="<?php echo vs_e($rate); ?>" data-custom-bonus="<?php echo vs_e($customBonusJson); ?>" data-default-tab="<?php echo vs_e($defaultTab); ?>">
    <div class="vs-recharge-hero">
        <div class="vs-recharge-hero__label">当前积分</div>
        <div class="vs-recharge-hero__value" id="rechargeBalance"><?php echo vs_e(PayConfig::fmtPoints($balance)); ?></div>
        <div class="vs-recharge-hero__meta">1 元 = <?php echo vs_e($rate); ?> 积分</div>
    </div>

    <?php if (!$payReady && $cardkeyReady): ?>
        <?php vs_render_notice('tip', '', '在线充值暂未开放，可使用卡密兑换。', array('compact' => true)); ?>
    <?php endif; ?>

    <?php if ($showTabs): ?>
    <div class="vs-tabs vs-recharge-tabs" id="rechargeTabs" role="tablist" aria-label="充值方式">
        <?php if ($payReady): ?>
        <button type="button" class="vs-tabs__btn vs-recharge-tab<?php echo $defaultTab === 'pay' ? ' is-active' : ''; ?>"
                data-tab="pay" role="tab" aria-selected="<?php echo $defaultTab === 'pay' ? 'true' : 'false'; ?>">积分充值</button>
        <?php endif; ?>
        <?php if ($cardkeyReady): ?>
        <button type="button" class="vs-tabs__btn vs-recharge-tab<?php echo $defaultTab === 'cardkey' ? ' is-active' : ''; ?>"
                data-tab="cardkey" role="tab" aria-selected="<?php echo $defaultTab === 'cardkey' ? 'true' : 'false'; ?>">卡密兑换</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($payReady): ?>
    <div class="vs-recharge-pane" id="rechargeTabPay" data-pane="pay"<?php echo $defaultTab !== 'pay' ? ' hidden' : ''; ?>>
        <div class="vs-recharge-section">
            <div class="vs-recharge-section__title">选择套餐</div>
            <div class="vs-recharge-grid" id="rechargePackages">
                <?php foreach ($packages as $pkg):
                    $gift = isset($pkg['gift']) ? (int) $pkg['gift'] : PayConfig::giftPercent(
                        isset($pkg['money']) ? $pkg['money'] : 0,
                        isset($pkg['points']) ? $pkg['points'] : 0,
                        $rate
                    );
                    $hasGift = $gift >= 1;
                    $isHot = !empty($pkg['hot']);
                    $cardClass = 'vs-recharge-card';
                    if ($isHot) {
                        $cardClass .= ' is-hot';
                    }
                    if ($hasGift) {
                        $cardClass .= ' has-gift';
                    }
                    if ($isHot || $hasGift) {
                        $cardClass .= ' has-badge';
                    }
                    ?>
                    <button type="button" class="<?php echo vs_e($cardClass); ?>"
                            data-pkg="<?php echo vs_e($pkg['id']); ?>"
                            data-money="<?php echo vs_e($pkg['money']); ?>"
                            data-points="<?php echo vs_e($pkg['points']); ?>">
                        <?php if ($isHot || $hasGift): ?>
                        <span class="vs-recharge-card__badges" aria-hidden="true">
                            <?php if ($isHot): ?><span class="vs-recharge-card__badge vs-recharge-card__badge--hot">荐</span><?php endif; ?>
                            <?php if ($hasGift): ?><span class="vs-recharge-card__badge vs-recharge-card__badge--gift">赠<?php echo (int) $gift; ?>%</span><?php endif; ?>
                        </span>
                        <?php endif; ?>
                        <div class="vs-recharge-card__name"><?php echo vs_e($pkg['name']); ?></div>
                        <div class="vs-recharge-card__money">¥<?php echo vs_e($pkg['money']); ?></div>
                        <div class="vs-notice vs-notice--tip vs-notice--compact vs-recharge-card__pts-tip" role="status">
                            <div class="vs-notice__text"><strong><?php echo vs_e($pkg['points']); ?></strong> 积分</div>
                        </div>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="vs-recharge-card vs-recharge-card--custom" id="rechargeCustomCard" data-pkg="">
                    <div class="vs-recharge-card__name">自定义金额</div>
                    <div class="vs-recharge-card__money">自选</div>
                    <div class="vs-notice vs-notice--tip vs-notice--compact vs-recharge-card__pts-tip" role="status">
                        <div class="vs-notice__text">按比例兑换</div>
                    </div>
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
    <?php elseif (!$cardkeyReady): ?>
        <?php vs_render_notice('tip', '', '在线充值暂未开放。', array('compact' => true)); ?>
    <?php endif; ?>

    <?php if ($cardkeyReady): ?>
    <div class="vs-recharge-pane" id="rechargeTabCardkey" data-pane="cardkey"<?php echo $defaultTab !== 'cardkey' ? ' hidden' : ''; ?>>
        <div class="vs-recharge-section vs-recharge-cardkey">
            <div class="vs-recharge-section__title">输入卡密</div>
            <div class="vs-recharge-cardkey__row">
                <input type="text" class="vs-input" id="cardkeyCodeInput" maxlength="20"
                       placeholder="输入 20 位字母数字卡密" autocomplete="off" spellcheck="false"
                       aria-label="卡密">
                <button type="button" class="vs-btn vs-btn--primary" id="cardkeyRedeemBtn">兑换</button>
            </div>
            <p class="vs-form-hint">卡密兑换即时到账；无效或已使用的卡密无法再次兑换。</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($hasTips): ?>
    <div class="vs-recharge-tips<?php echo $defaultTab === 'cardkey' ? ' is-cardkey-first' : ''; ?>" id="rechargeTips">
        <?php foreach ($tipOrder as $tk):
            if (empty($tipHtml[$tk])) {
                continue;
            }
            ?>
            <div class="vs-recharge-tip" data-tip="<?php echo vs_e($tk); ?>">
                <div class="vs-notice vs-notice--tip vs-recharge-tip__notice">
                    <div class="vs-notice__text"><?php echo $tipHtml[$tk]; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($payReady): ?>
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
            <div class="vs-notice vs-notice--tip vs-notice--compact vs-notice--field" id="rechargeCustomHint" role="status">
                <div class="vs-notice__text">预计到账 <strong id="rechargeCustomHintPts">—</strong> 积分<span id="rechargeCustomHintGift"></span></div>
            </div>
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
<?php endif; ?>
