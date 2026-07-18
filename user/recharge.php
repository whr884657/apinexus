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
            'orderno'  => (string) $row['orderno'],
            'status'   => (int) $row['status'],
            'label'    => OrderManager::statusLabel($row['status']),
            'points'   => PayConfig::fmtPoints($row['amount']),
            'balance'  => PayConfig::fmtPoints(PointsManager::balance($userId)),
            'money'    => number_format((float) $row['money'], 2, '.', ''),
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

vs_user_layout_start('充值中心', 'recharge');
?>
<div class="vs-page-head">
    <div>
        <h1 class="vs-page-title">充值中心</h1>
        <p class="vs-page-desc">选择套餐或自定义金额，扫码完成支付后积分即时到账。</p>
    </div>
</div>

<div class="vs-panel" style="margin-bottom:16px;">
    <div class="vs-panel__body" style="padding:20px;">
        <div style="font-size:13px;color:#64748b;">当前积分余额</div>
        <div id="rechargeBalance" style="font-size:32px;font-weight:700;color:#0f172a;margin-top:4px;"><?php echo vs_e(PayConfig::fmtPoints($balance)); ?></div>
    </div>
</div>

<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', '积分功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
<?php elseif (!$payReady): ?>
    <?php vs_render_notice('warning', '', '充值暂未开放，请稍后再试或联系管理员。', array('compact' => true)); ?>
<?php else: ?>
<form id="rechargeForm" class="vs-panel" method="post" data-ajax="1">
    <div class="vs-panel__body" style="padding:20px;">
        <div class="vs-form-row">
            <span class="vs-label">充值套餐</span>
            <div class="vs-recharge-packages" id="rechargePackages" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;">
                <?php foreach ($packages as $pkg): ?>
                    <button type="button" class="vs-recharge-pkg<?php echo !empty($pkg['hot']) ? ' is-hot' : ''; ?>"
                            data-pkg="<?php echo vs_e($pkg['id']); ?>"
                            data-money="<?php echo vs_e($pkg['money']); ?>"
                            data-points="<?php echo vs_e($pkg['points']); ?>"
                            style="text-align:left;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;cursor:pointer;">
                        <div style="font-weight:600;"><?php echo vs_e($pkg['name']); ?></div>
                        <div style="margin-top:6px;color:#2563eb;font-size:18px;font-weight:700;">¥<?php echo vs_e($pkg['money']); ?></div>
                        <div style="margin-top:4px;font-size:12px;color:#64748b;"><?php echo vs_e($pkg['points']); ?> 积分</div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="vs-form-row vs-form-row--2">
            <div>
                <label class="vs-label" for="rechargeMoney">自定义金额（元）</label>
                <input type="number" class="vs-input" id="rechargeMoney" min="0.01" step="0.01" placeholder="不选套餐时可填">
                <p class="vs-form-hint">按 1 元 = <?php echo vs_e(PayConfig::fmtPoints($cfg['rate'])); ?> 积分换算。</p>
            </div>
            <div>
                <label class="vs-label" for="rechargePaytype">支付方式</label>
                <select class="vs-input vs-select" id="rechargePaytype" data-vs-pick>
                    <?php foreach ($methods as $m): ?>
                        <option value="<?php echo vs_e($m); ?>"><?php echo vs_e(PayConfig::methodLabel($m)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <input type="hidden" id="rechargePackageId" value="">
        <div style="display:flex;justify-content:flex-end;margin-top:8px;">
            <button type="button" class="vs-btn vs-btn--primary" id="rechargeSubmitBtn">立即支付</button>
        </div>
    </div>
</form>

<div class="vs-overlay vs-overlay--form" id="rechargePayOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="rechargePayTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="rechargePayTitle">扫码支付</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body" style="text-align:center;">
            <p>订单号：<strong id="payOrderNo"></strong></p>
            <p>实付：¥<strong id="payMoney"></strong> · <span id="payTypeLabel"></span></p>
            <p>预计到账：<strong id="payPoints"></strong> 积分</p>
            <img id="payQrImg" alt="支付二维码" width="200" height="200" style="margin:12px auto;display:block;border-radius:8px;">
            <p class="vs-form-hint">请使用对应 App 扫码；支付完成后将自动到账。</p>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--outline" id="payCancelBtn">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="payCheckBtn">我已支付</button>
        </footer>
    </div>
</div>
<style>
.vs-recharge-pkg.is-selected,.vs-recharge-pkg.is-hot.is-selected{border-color:#2563eb!important;box-shadow:0 0 0 2px rgba(37,99,235,.15);background:#eff6ff}
.vs-recharge-pkg.is-hot{border-color:#93c5fd}
</style>
<?php endif; ?>
<?php vs_user_layout_end(($ready && $payReady) ? array('user-recharge.js') : array()); ?>
