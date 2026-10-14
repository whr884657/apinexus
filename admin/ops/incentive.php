<?php
/**
 * 文件：admin/ops/incentive.php
 * 作用：运营中心 · 用户激励（模块一积分赠送 + 模块二全站优惠）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'promo_get' || $action === 'promo_save_discount'
        || $action === 'promo_save_free' || $action === 'promo_off') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if ($action === 'promo_get') {
            AjaxResponse::success('ok', IncentiveManager::sitePromoAdminStatus());
        }
        if ($action === 'promo_off') {
            $result = IncentiveManager::clearSitePromo((int) Auth::id());
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '重置失败');
            }
            AjaxResponse::success(
                isset($result['msg']) ? $result['msg'] : '已重置',
                isset($result['data']) ? $result['data'] : array()
            );
        }
        $expireAt = isset($_POST['expire_at']) ? (string) $_POST['expire_at'] : '';
        $label = isset($_POST['label']) ? (string) $_POST['label'] : '';
        if ($action === 'promo_save_discount') {
            $zhe = isset($_POST['zhe']) ? $_POST['zhe'] : (isset($_POST['percent_pay']) ? $_POST['percent_pay'] : '');
            $result = IncentiveManager::saveSitePromoDiscount($zhe, $expireAt, $label, (int) Auth::id());
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '保存失败');
            }
            AjaxResponse::success(
                isset($result['msg']) ? $result['msg'] : '已保存',
                isset($result['data']) ? $result['data'] : array()
            );
        }
        $result = IncentiveManager::saveSitePromoFree($expireAt, $label, (int) Auth::id());
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '保存失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : '已保存',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    if ($action === 'gift_filter' || $action === 'gift_preview'
        || $action === 'gift_filter_start' || $action === 'gift_filter_tick'
        || $action === 'gift_start' || $action === 'gift_tick'
        || $action === 'gift_execute') {
        if (!PointsManager::hasPointsColumn() || !OrderManager::tableReady()) {
            AjaxResponse::error('积分系统未就绪，请先完成系统升级');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($action === 'gift_tick') {
            $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
            $result = IncentiveManager::tickGift((int) Auth::id(), $token);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '推进失败');
            }
            AjaxResponse::success(
                isset($result['msg']) ? $result['msg'] : 'ok',
                isset($result['data']) ? $result['data'] : array()
            );
        }

        if ($action === 'gift_filter_tick') {
            $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
            $result = IncentiveManager::tickFilter((int) Auth::id(), $token);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '筛选推进失败');
            }
            AjaxResponse::success(
                isset($result['msg']) ? $result['msg'] : 'ok',
                isset($result['data']) ? $result['data'] : array()
            );
        }

        $norm = IncentiveManager::normalizeFilter($_POST);
        if (empty($norm['ok'])) {
            AjaxResponse::error(isset($norm['msg']) ? $norm['msg'] : '筛选条件无效');
        }
        $filter = $norm['filter'];
        $warnings = !empty($norm['warnings']) && is_array($norm['warnings']) ? $norm['warnings'] : array();

        if ($action === 'gift_filter' || $action === 'gift_preview' || $action === 'gift_filter_start') {
            $result = IncentiveManager::startFilter($filter, (int) Auth::id(), $warnings);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '筛选失败');
            }
            AjaxResponse::success(
                isset($result['msg']) ? $result['msg'] : 'ok',
                isset($result['data']) ? $result['data'] : array()
            );
        }

        $amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
        $remark = isset($_POST['remark']) ? (string) $_POST['remark'] : '';
        $confirm = isset($_POST['confirm_text']) ? (string) $_POST['confirm_text'] : '';
        $filterCount = isset($_POST['filter_count'])
            ? (int) $_POST['filter_count']
            : (isset($_POST['preview_count']) ? (int) $_POST['preview_count'] : 0);
        $filterToken = isset($_POST['filter_token']) ? (string) $_POST['filter_token'] : '';

        $result = IncentiveManager::startGift(
            $filter,
            $amount,
            $remark,
            (int) Auth::id(),
            $confirm,
            $filterCount,
            $filterToken
        );
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '启动失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : 'ok',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    AjaxResponse::error('无效操作', 400);
}

$ready = PointsManager::hasPointsColumn() && OrderManager::tableReady();
$redisOk = class_exists('RedisCache') && RedisCache::enabled();
$promoStatus = $ready ? IncentiveManager::sitePromoAdminStatus() : array(
    'active' => false,
    'mode' => 'off',
    'percent_pay' => null,
    'expire_at' => '',
    'label' => '',
    'expired_hint' => false,
);
$promoMode = isset($promoStatus['mode']) ? (string) $promoStatus['mode'] : 'off';
if ($promoMode !== 'discount' && $promoMode !== 'free') {
    $promoMode = 'discount';
}
$promoZhe = '';
if (!empty($promoStatus['percent_pay'])) {
    $pp = (int) $promoStatus['percent_pay'];
    $promoZhe = ($pp % 10 === 0)
        ? (string) (int) ($pp / 10)
        : rtrim(rtrim(number_format($pp / 10, 1, '.', ''), '0'), '.');
}
$promoExpireAttr = '';
$promoFoldHint = '';
if (!empty($promoStatus['expire_at'])) {
    $ets = strtotime((string) $promoStatus['expire_at']);
    if ($ets !== false) {
        // 自渲染时间选择器用 Y-m-d H:i（禁止原生 datetime-local）
        $promoExpireAttr = date('Y-m-d H:i', $ets);
    }
}
// 折叠标题旁生效摘要（默认折叠时一眼可见）
$promoFoldKind = '';
if (!empty($promoStatus['active'])) {
    $untilShort = '';
    if (!empty($promoStatus['expire_at'])) {
        $etsHint = strtotime((string) $promoStatus['expire_at']);
        if ($etsHint !== false) {
            $untilShort = date('n月j日 H:i', $etsHint);
        }
    }
    $pm = isset($promoStatus['mode']) ? (string) $promoStatus['mode'] : '';
    if ($pm === 'free') {
        $promoFoldHint = '全站免费' . ($untilShort !== '' ? ('至' . $untilShort) : '');
        $promoFoldKind = 'is-free';
    } elseif ($pm === 'discount' && $promoZhe !== '') {
        $promoFoldHint = '打' . $promoZhe . '折' . ($untilShort !== '' ? ('至' . $untilShort) : '');
        $promoFoldKind = 'is-discount';
    }
} elseif (!empty($promoStatus['expired_hint'])) {
    $promoFoldHint = '已过期';
    $promoFoldKind = 'is-expired';
}

vs_admin_layout_start('用户激励', 'ops-incentive');
?>
<?php if (!$ready): ?>
    <?php
    vs_render_notice(
        'warning',
        '尚未就绪',
        '积分系统尚未就绪，请先完成系统升级。',
        array('compact' => true)
    );
    ?>
<?php else: ?>
<div class="vs-incentive-page">
    <?php if (!$redisOk): ?>
        <?php
        vs_render_notice(
            'warning',
            '需要 Redis',
            '筛选与分批赠送依赖 Redis，请先在系统设置中启用。全站优惠配置仍可保存（镜像缓存可选）。',
            array('compact' => true)
        );
        ?>
    <?php endif; ?>

    <div class="vs-incentive-fold" id="incentiveGiftFold">
        <button type="button" class="vs-incentive-fold__bar" id="incentiveGiftFoldBar" aria-expanded="false">
            <span class="vs-incentive-fold__title">积分赠送</span>
            <span class="vs-icon vs-icon--chevron vs-incentive-fold__chevron" aria-hidden="true"></span>
        </button>

        <div class="vs-incentive-peek" id="incentivePeek" aria-hidden="false">
            <div class="vs-incentive-peek__card">
                <p class="vs-incentive-peek__text">按条件筛选用户并批量赠送积分，入账进财务「积分变动」，类型为管理员加款。</p>
            </div>
        </div>

        <div class="vs-incentive-fold__body" id="incentiveGiftFoldBody">
            <form class="vs-form vs-incentive-form" id="incentiveGiftForm" onsubmit="return false;">
                <div class="vs-incentive-toolbar">
                    <div class="vs-incentive-toolbar__filterline">
                        <div class="vs-incentive-toolbar__tags">
                            <label class="vs-label vs-incentive-toolbar__label" for="incentiveTagTrigger">筛选条件</label>
                            <div class="vs-incentive-tagfield" id="incentiveTagField">
                                <button type="button" class="vs-incentive-tagfield__trigger" id="incentiveTagTrigger" aria-haspopup="dialog" aria-expanded="false"<?php echo $redisOk ? '' : ' disabled'; ?>>
                                    <span class="vs-incentive-tagfield__chips" id="incentiveSelectedChips"></span>
                                    <span class="vs-incentive-tagfield__placeholder" id="incentiveTagPlaceholder">选择筛选标签（可多选）</span>
                                    <span class="vs-icon vs-icon--chevron" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="vs-btn vs-btn--secondary vs-incentive-filter-btn" id="giftFilterBtn"<?php echo $redisOk ? '' : ' disabled title="需要 Redis"'; ?>>筛选</button>
                    </div>
                    <div class="vs-incentive-toolbar__amount">
                        <label class="vs-label vs-incentive-toolbar__label" for="giftAmount">每人积分</label>
                        <input type="number" class="vs-input vs-incentive-amount" id="giftAmount" name="amount" min="1" max="10000" step="1" value="100" required>
                    </div>
                    <div class="vs-incentive-toolbar__remark">
                        <label class="vs-label vs-incentive-toolbar__label" for="giftRemark">赠送理由</label>
                        <input type="text" class="vs-input" id="giftRemark" name="remark" maxlength="120" required placeholder="如 中秋节积分赠送" autocomplete="off">
                    </div>
                    <button type="button" class="vs-btn vs-btn--primary vs-incentive-exec-btn" id="giftExecuteBtn"<?php echo $redisOk ? '' : ' disabled title="需要 Redis"'; ?>>确认赠送</button>
                </div>

                <div class="vs-incentive-tip" id="incentiveTipBanner" hidden role="status"></div>

                <div class="vs-incentive-progress" id="incentiveProgressWrap" hidden>
                    <div class="vs-incentive-progress__bar">
                        <div class="vs-incentive-progress__fill" id="incentiveProgressFill"></div>
                    </div>
                    <div class="vs-incentive-statusline" id="incentiveStatusLine">
                        <span class="vs-incentive-statusline__prog" id="incentiveProgressText"></span>
                        <span class="vs-incentive-statusline__detail" id="giftFilterText" hidden></span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="vs-incentive-divider" role="separator" aria-hidden="true"></div>

    <div class="vs-incentive-fold" id="incentivePromoFold">
        <button type="button" class="vs-incentive-fold__bar" id="incentivePromoFoldBar" aria-expanded="false">
            <span class="vs-incentive-fold__title-wrap">
                <span class="vs-incentive-fold__title">全站优惠</span>
                <span class="vs-incentive-fold__meta<?php echo $promoFoldHint !== '' ? ' is-on' : ''; ?><?php echo $promoFoldKind !== '' ? ' ' . vs_e($promoFoldKind) : ''; ?>" id="promoFoldMeta"<?php echo $promoFoldHint === '' ? ' hidden' : ''; ?>><?php
                    if ($promoFoldHint !== '') {
                        echo '<span class="vs-incentive-fold__meta-dash" aria-hidden="true">—</span>';
                        echo '<span class="vs-incentive-fold__meta-text">' . vs_e($promoFoldHint) . '</span>';
                    }
                ?></span>
            </span>
            <span class="vs-icon vs-icon--chevron vs-incentive-fold__chevron" aria-hidden="true"></span>
        </button>

        <div class="vs-incentive-peek" id="incentivePromoPeek" aria-hidden="false">
            <div class="vs-incentive-peek__card">
                <p class="vs-incentive-peek__text">活动期间全站调用按优惠计价，不会改各接口后台里的原价。</p>
            </div>
        </div>

        <div class="vs-incentive-fold__body">
            <form class="vs-form vs-incentive-promo" id="incentivePromoForm" onsubmit="return false;">
                <p class="vs-incentive-promo__hint">
                    <span class="vs-incentive-promo__hint-em">折扣与全站免费互斥，同时只能开一种</span>；到期后自动失效（以服务器时间为准）。
                </p>

                <div class="vs-incentive-promo__status" id="promoStatusBar" role="status">
                    加载中…
                </div>

                <div class="vs-incentive-promo__switch<?php echo $promoMode === 'free' ? ' is-free' : ' is-discount'; ?>" id="promoModeSwitch" role="tablist" aria-label="优惠模式">
                    <span class="vs-incentive-promo__switch-pill" aria-hidden="true"></span>
                    <button type="button" class="vs-incentive-promo__tab<?php echo $promoMode !== 'free' ? ' is-active' : ''; ?>" id="promoTabDiscount" role="tab" aria-selected="<?php echo $promoMode !== 'free' ? 'true' : 'false'; ?>" data-promo-mode="discount">全站折扣</button>
                    <button type="button" class="vs-incentive-promo__tab<?php echo $promoMode === 'free' ? ' is-active' : ''; ?>" id="promoTabFree" role="tab" aria-selected="<?php echo $promoMode === 'free' ? 'true' : 'false'; ?>" data-promo-mode="free">一键全站免费</button>
                    <input type="hidden" name="promo_mode" id="promoModeValue" value="<?php echo vs_e($promoMode === 'free' ? 'free' : 'discount'); ?>">
                </div>

                <div class="vs-incentive-promo__row<?php echo $promoMode === 'free' ? ' is-free' : ''; ?>" id="promoFieldsRow">
                    <div class="vs-incentive-promo__field" id="promoZheWrap">
                        <label class="vs-label" for="promoZhe">打几折</label>
                        <input type="text" class="vs-input" id="promoZhe" name="zhe" inputmode="decimal" placeholder="如 8 = 八折" value="<?php echo vs_e($promoZhe); ?>" autocomplete="off"<?php echo $promoMode === 'free' ? ' disabled' : ''; ?>>
                    </div>
                    <div class="vs-incentive-promo__field vs-incentive-promo__field--expire">
                        <label class="vs-label" for="promoExpireBtn">到期时间</label>
                        <input type="hidden" id="promoExpire" name="expire_at" value="<?php echo vs_e($promoExpireAttr); ?>">
                        <button type="button" class="vs-input vs-incentive-promo__expire-btn" id="promoExpireBtn" aria-haspopup="dialog" aria-expanded="false">
                            <?php echo $promoExpireAttr !== '' ? vs_e($promoExpireAttr) : '点击选择到期时间'; ?>
                        </button>
                    </div>
                    <div class="vs-incentive-promo__field" id="promoLabelWrap">
                        <label class="vs-label" for="promoLabel">活动标签</label>
                        <input type="text" class="vs-input" id="promoLabel" name="label" maxlength="16" placeholder="如 国庆八折（可选）" value="<?php echo vs_e(isset($promoStatus['label']) ? (string) $promoStatus['label'] : ''); ?>" autocomplete="off">
                    </div>
                    <div class="vs-incentive-promo__actions">
                        <button type="button" class="vs-btn vs-btn--primary" id="promoSaveBtn">确认</button>
                        <button type="button" class="vs-btn vs-btn--default" id="promoResetBtn">重置</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="vs-nested-picker vs-nested-picker--viewport" id="promoDatetimePicker" hidden aria-hidden="true">
    <div class="vs-nested-picker__backdrop" data-promo-dt-close="1"></div>
    <div class="vs-nested-picker__panel" role="dialog" aria-modal="true" aria-labelledby="promoDatetimeTitle">
        <div class="vs-nested-picker__handle" aria-hidden="true"></div>
        <header class="vs-nested-picker__head">
            <h3 class="vs-nested-picker__title" id="promoDatetimeTitle">选择到期时间</h3>
            <button type="button" class="vs-nested-picker__close" data-promo-dt-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-nested-picker__body vs-datetime-picker-body">
            <div class="vs-datetime__head">
                <button type="button" class="vs-datetime__nav" data-promo-dt-nav="-1" aria-label="上月">‹</button>
                <span class="vs-datetime__title" id="promoDtTitle"></span>
                <button type="button" class="vs-datetime__nav" data-promo-dt-nav="1" aria-label="下月">›</button>
            </div>
            <div class="vs-datetime__weekdays" aria-hidden="true">
                <span>日</span><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span>
            </div>
            <div class="vs-datetime__days" id="promoDtDays"></div>
            <div class="vs-datetime__hm">
                <select id="promoExpireHour" class="vs-select" data-vs-pick aria-label="时">
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <option value="<?php echo sprintf('%02d', $h); ?>"><?php echo sprintf('%02d', $h); ?></option>
                    <?php endfor; ?>
                </select>
                <span class="vs-datetime__hm-sep" aria-hidden="true">:</span>
                <select id="promoExpireMinute" class="vs-select" data-vs-pick aria-label="分">
                    <?php for ($m = 0; $m < 60; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <footer class="vs-nested-picker__foot">
            <button type="button" class="vs-btn vs-btn--default" id="promoDtClear">清空</button>
            <button type="button" class="vs-btn vs-btn--primary" id="promoDtOk">确定</button>
        </footer>
    </div>
</div>

<div class="vs-incentive-tagpop" id="incentiveTagPop" hidden>
    <div class="vs-incentive-tagpop__backdrop" id="incentiveTagPopBackdrop"></div>
    <div class="vs-incentive-tagpop__panel" role="dialog" aria-label="筛选标签">
        <div class="vs-incentive-tagpop__head">
            <span class="vs-incentive-tagpop__title">筛选标签（可多选）</span>
            <button type="button" class="vs-incentive-tagpop__close" id="incentiveTagPopClose" aria-label="关闭">×</button>
        </div>
        <div class="vs-incentive-tagpop__body">
            <div class="vs-incentive-tagpop__grid" id="incentiveTagGrid"></div>
        </div>
        <div class="vs-incentive-tagpop__foot">
            <button type="button" class="vs-btn vs-btn--default" id="incentiveTagPopClear">清空</button>
            <button type="button" class="vs-btn vs-btn--primary" id="incentiveTagPopOk">完成</button>
        </div>
    </div>
</div>
<script>
window.__VS_INCENTIVE_PROMO__ = <?php echo json_encode($promoStatus, JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php endif; ?>
<?php
vs_admin_layout_end($ready ? array('ops-incentive.js') : array());
