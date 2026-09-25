<?php
/**
 * 文件：admin/finance/payment.php
 * 作用：码支付与积分充值配置（三 Tab：积分与套餐 / 支付接口 / 充值说明）
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
    $bonusRaw = isset($_POST['custom_bonus']) ? (string) $_POST['custom_bonus'] : '[]';
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
        'custom_bonus'    => $bonusRaw,
        'tip_package'     => isset($_POST['tip_package']) ? $_POST['tip_package'] : '',
        'tip_custom'      => isset($_POST['tip_custom']) ? $_POST['tip_custom'] : '',
        'tip_cardkey'     => isset($_POST['tip_cardkey']) ? $_POST['tip_cardkey'] : '',
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
$bonusJson = json_encode(isset($cfg['custom_bonus']) ? $cfg['custom_bonus'] : array(), JSON_UNESCAPED_UNICODE);
if ($bonusJson === false) {
    $bonusJson = '[]';
}

$tipDefs = array(
    'package' => array(
        'label' => '套餐充值说明',
        'name'  => 'tip_package',
        'md'    => $cfg['tip_package'],
    ),
    'custom' => array(
        'label' => '自定义充值说明',
        'name'  => 'tip_custom',
        'md'    => $cfg['tip_custom'],
    ),
    'cardkey' => array(
        'label' => '卡密充值说明',
        'name'  => 'tip_cardkey',
        'md'    => $cfg['tip_cardkey'],
    ),
);

vs_admin_layout_start('支付配置', 'payment');
echo Markdown::renderAssetsHtml();

if (!$cfg['ready']) {
    vs_render_notice('warning', '支付尚未就绪', '请填写网关地址、商户 ID、商户密钥，并至少启用一种支付方式。', array('compact' => true));
}
?>

<div id="payConfigPage" class="vs-pay-config-page" data-pay-rate="<?php echo vs_e(PayConfig::fmtPoints($cfg['rate'])); ?>">
    <div class="vs-tabs vs-api-review-tabs vs-pay-config-tabs" id="payConfigTabs" role="tablist" aria-label="支付配置分类">
        <button type="button" class="vs-tabs__btn is-active" data-pay-tab="packages" role="tab" aria-selected="true" aria-controls="payPanelPackages" id="payTabPackages">
            积分与套餐
        </button>
        <button type="button" class="vs-tabs__btn" data-pay-tab="gateway" role="tab" aria-selected="false" aria-controls="payPanelGateway" id="payTabGateway">
            支付接口
        </button>
        <button type="button" class="vs-tabs__btn" data-pay-tab="tips" role="tab" aria-selected="false" aria-controls="payPanelTips" id="payTabTips">
            充值说明
        </button>
    </div>

    <form method="post" data-ajax="1" id="payConfigForm" class="vs-panel vs-pay-config-panel">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="packages" id="payPackages" value="<?php echo vs_e($packagesJson); ?>">
        <input type="hidden" name="custom_bonus" id="payCustomBonus" value="<?php echo vs_e($bonusJson); ?>">
        <div class="vs-panel__body">
            <div class="vs-pay-config-panel-pane is-active" data-pay-panel="packages" id="payPanelPackages" role="tabpanel" aria-labelledby="payTabPackages">
                <div class="vs-form-section">
                    <div class="vs-form-row">
                        <label class="vs-label" for="payRate">兑换比例</label>
                        <input type="number" class="vs-input" id="payRate" name="rate" min="0.0001" step="0.0001"
                               value="<?php echo vs_e(PayConfig::fmtPoints($cfg['rate'])); ?>">
                        <p class="vs-form-hint">自定义金额：1 元兑换多少积分。套餐多于该比例时卡片自动显示「赠 xx%」。</p>
                    </div>
                    <div class="vs-form-row">
                        <div class="vs-pkg-editor-head">
                            <span class="vs-label">充值套餐</span>
                            <button type="button" class="vs-btn vs-btn--primary" id="payPkgAddBtn">添加套餐</button>
                        </div>
                        <div class="vs-pkg-editor-list" id="payPkgList"></div>
                    </div>
                    <div class="vs-form-row">
                        <div class="vs-pkg-editor-head">
                            <span class="vs-label">自定义充值优惠</span>
                            <button type="button" class="vs-btn vs-btn--primary" id="payBonusAddBtn">添加一档</button>
                        </div>
                        <p class="vs-form-hint">仅作用于「自定义金额」。区间为「满起始、不足上限」；上限填 0 表示不封顶。区间不可重叠。</p>
                        <div class="vs-bonus-editor-list" id="payBonusList"></div>
                    </div>
                </div>
            </div>

            <div class="vs-pay-config-panel-pane" data-pay-panel="gateway" id="payPanelGateway" role="tabpanel" aria-labelledby="payTabGateway" hidden>
                <div class="vs-form-section">
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
                                    <?php echo PayConfig::iconHtml($code); ?>
                                    <span><?php echo vs_e($label); ?></span>
                                </button>
                                <input type="checkbox" class="vs-pay-method-input" name="methods[]" value="<?php echo vs_e($code); ?>"<?php echo $on ? ' checked' : ''; ?> hidden>
                            <?php endforeach; ?>
                        </div>
                        <p class="vs-form-hint">支付宝、微信、QQ 三种均未启用时，用户端充值中心将只显示「卡密兑换」。</p>
                    </div>
                </div>
            </div>

            <div class="vs-pay-config-panel-pane" data-pay-panel="tips" id="payPanelTips" role="tabpanel" aria-labelledby="payTabTips" hidden>
                <div class="vs-form-section vs-pay-tips-section">
                    <p class="vs-form-hint vs-pay-tips-lead">以下说明展示在用户充值中心底部。留空则用户端不显示对应板块。正文用 Markdown 自写标题与内容（系统不另加固定标题）。点「编辑」修改，底部「保存配置」写入。</p>
                    <?php foreach ($tipDefs as $tipKey => $tip):
                        $md = (string) $tip['md'];
                        $html = $md !== '' && class_exists('Markdown') ? Markdown::render($md) : '';
                        ?>
                        <div class="vs-pay-tip-block" data-tip-key="<?php echo vs_e($tipKey); ?>">
                            <div class="vs-pay-tip-block__head">
                                <span class="vs-label vs-pay-tip-block__slot"><?php echo vs_e($tip['label']); ?></span>
                                <div class="vs-pay-tip-block__actions">
                                    <?php if ($tipKey === 'custom'): ?>
                                        <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="payTipFillBonusBtn" data-tip-fill-bonus>按优惠档位生成说明</button>
                                    <?php endif; ?>
                                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-tip-edit>编辑</button>
                                </div>
                            </div>
                            <div class="vs-pay-tip-block__preview">
                                <?php if ($html !== ''): ?>
                                    <div class="vs-notice vs-notice--tip vs-pay-tip-preview">
                                        <div class="vs-notice__text"><?php echo $html; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="vs-notice vs-notice--tip vs-pay-tip-preview vs-pay-tip-preview--empty">
                                        <div class="vs-notice__text">暂未配置，点击「编辑」填写。留空保存后用户端不显示。</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="vs-pay-tip-block__editor" hidden>
                                <textarea class="vs-input vs-textarea" name="<?php echo vs_e($tip['name']); ?>"
                                          id="payTip_<?php echo vs_e($tipKey); ?>" rows="10"
                                          placeholder="Markdown 正文（标题请写在正文里）"><?php echo vs_e($md); ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="vs-panel__foot vs-finance-form-foot">
            <button type="submit" class="vs-btn vs-btn--primary" id="payConfigSaveBtn">保存配置</button>
        </div>
    </form>
</div>

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

<div class="vs-overlay vs-overlay--form" id="payBonusOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-bonus-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="payBonusTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="payBonusTitle">编辑优惠档位</h3>
            <button type="button" class="vs-overlay__close" data-bonus-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <input type="hidden" id="payBonusEditIndex" value="-1">
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="payBonusMin">起始金额（元）</label>
                    <input type="number" class="vs-input" id="payBonusMin" min="0.01" step="0.01" placeholder="如 10">
                </div>
                <div>
                    <label class="vs-label" for="payBonusMax">上限金额（元）</label>
                    <input type="number" class="vs-input" id="payBonusMax" min="0" step="0.01" placeholder="0 = 不封顶">
                </div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="payBonusPercent">额外赠送（%）</label>
                <input type="number" class="vs-input" id="payBonusPercent" min="1" max="1000" step="1" placeholder="如 5">
                <p class="vs-form-hint">到账 = 金额 × 兑换比例 × (1 + 赠送%)。区间含起始、不含上限。</p>
            </div>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--outline" data-bonus-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="payBonusSaveBtn">确定</button>
        </footer>
    </div>
</div>
<?php vs_admin_layout_end(array('finance-payment.js')); ?>
