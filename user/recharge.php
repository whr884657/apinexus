<?php
/**
 * 文件：user/recharge.php
 * 作用：用户充值中心
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$ready = OrderManager::tableReady() && PointsManager::hasPointsColumn();
$payReady = PayConfig::isReady();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create') {
        if (!$ready) {
            AjaxResponse::error('积分系统未就绪');
        }
        $payType = isset($_POST['paytype']) ? (string) $_POST['paytype'] : '';
        $packageId = isset($_POST['package_id']) ? (string) $_POST['package_id'] : '';
        $money = isset($_POST['money']) ? (float) $_POST['money'] : 0;
        $result = PointsManager::createRecharge($userId, $payType, $packageId, $money);
        if (!$result['ok']) {
            AjaxResponse::error($result['msg']);
        }
        AjaxResponse::success($result['msg'], $result['data']);
    }

    if ($action === 'status') {
        $orderno = isset($_POST['orderno']) ? trim((string) $_POST['orderno']) : '';
        $row = OrderManager::findByOrderNo($orderno);
        if (!$row || (int) $row['userid'] !== $userId) {
            AjaxResponse::error('订单不存在');
        }
        AjaxResponse::success('ok', array(
            'orderno' => (string) $row['orderno'],
            'status'  => (int) $row['status'],
            'label'   => OrderManager::statusLabel($row['status']),
            'points'  => PayConfig::fmtPoints($row['amount']),
            'balance' => PayConfig::fmtPoints(PointsManager::balance($userId)),
            'money'   => number_format((float) $row['money'], 2, '.', ''),
        ));
    }

    if ($action === 'cancel') {
        $orderno = isset($_POST['orderno']) ? trim((string) $_POST['orderno']) : '';
        $row = OrderManager::findByOrderNo($orderno);
        if (!$row || (int) $row['userid'] !== $userId) {
            AjaxResponse::error('订单不存在');
        }
        if ((int) $row['status'] !== OrderManager::STATUS_PENDING) {
            AjaxResponse::error('当前订单不可取消');
        }
        PointsManager::cancelPending($orderno);
        AjaxResponse::success('已取消');
    }

    AjaxResponse::error('无效操作', 400);
}

$balance = $ready ? PointsManager::balance($userId) : 0;
$cfg = PayConfig::all();
$packages = $cfg['packages'];
$methods = $cfg['methods'];
$rate = PayConfig::fmtPoints($cfg['rate']);

$payIcons = array(
    'alipay' => '<svg class="vs-pay-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#1677FF"/><path fill="#fff" d="M6.2 15.4c1.7 1.1 3.5 1.7 5.3 1.7 2.7 0 4.6-1.3 4.6-3.2 0-1.5-1.1-2.5-3.3-3.1l-1.7-.4c-.9-.2-1.3-.5-1.3-1 0-.6.6-1 1.6-1 1.1 0 2.2.3 3.3.9l.7-1.8c-1.1-.6-2.4-.9-3.9-.9-2.5 0-4.1 1.3-4.1 3.1 0 1.5 1.1 2.5 3.2 3l1.7.4c1 .2 1.5.5 1.5 1.1 0 .6-.7 1.1-1.9 1.1-1.4 0-2.8-.5-4.1-1.4l-.6 1.5z"/></svg>',
    'wxpay'  => '<svg class="vs-pay-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#07C160"/><path fill="#fff" d="M9.4 8.2c-2.7 0-4.9 1.8-4.9 4 0 1.3.7 2.4 1.9 3.2l-.5 1.5 1.7-.9c.5.1 1 .2 1.5.2.2 0 .4 0 .6-.1-.1-.3-.2-.7-.2-1 0-2.1 2-3.8 4.5-3.8.2 0 .4 0 .6.1C14.1 9.7 11.9 8.2 9.4 8.2zm-1.9 2.1a.7.7 0 110 1.4.7.7 0 010-1.4zm3.5 0a.7.7 0 110 1.4.7.7 0 010-1.4zm4.4.9c-2.3 0-4.1 1.5-4.1 3.3 0 1.8 1.8 3.3 4.1 3.3.5 0 1-.1 1.4-.2l1.4.8-.4-1.3c1-.7 1.6-1.6 1.6-2.6 0-1.8-1.8-3.3-4-3.3zm-1.5 1.8a.6.6 0 110 1.2.6.6 0 010-1.2zm3 0a.6.6 0 110 1.2.6.6 0 010-1.2z"/></svg>',
    'qqpay'  => '<svg class="vs-pay-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#12B7F5"/><path fill="#fff" d="M12 5.5c-2 0-3.5 1.6-3.5 4.1 0 1.6.5 3.1 1.1 4.2-.9.4-1.5 1.1-1.5 1.9 0 .8.6 1.4 1.7 1.7-.1.4-.3 1-.3 1.4 0 .7.5 1.2 1.4 1.2.7 0 1.3-.3 1.7-.8.4.5 1 .8 1.7.8.9 0 1.4-.5 1.4-1.2 0-.4-.2-1-.3-1.4 1.1-.3 1.7-.9 1.7-1.7 0-.8-.6-1.5-1.5-1.9.6-1.1 1.1-2.6 1.1-4.2 0-2.5-1.5-4.1-3.5-4.1z"/></svg>',
);

vs_user_layout_start('充值中心', 'recharge');
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
                    <?php echo isset($payIcons[$m]) ? $payIcons[$m] : ''; ?>
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
        'alipay' => $payIcons['alipay'],
        'wxpay'  => $payIcons['wxpay'],
        'qqpay'  => $payIcons['qqpay'],
    ), JSON_UNESCAPED_UNICODE);
?></script>
<?php endif; ?>
<?php vs_user_layout_end(($ready && $payReady) ? array('user-recharge.js') : array()); ?>
