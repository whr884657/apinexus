<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!isset($apiData) || !is_array($apiData)) {
    $apiData = FrontendApi::listForTheme();
}

$apis = $apiData;
$showDetailBtn = !isset($showDetailBtn) || $showDetailBtn;
$cardExtraClass = isset($cardExtraClass) ? trim((string) $cardExtraClass) : '';
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');

/**
 * 与首页卡片一致：最多展示 2 个 Method，多余显示 +N
 *
 * @param array $methods
 * @return array{0: array, 1: int}
 */
$vsSplitMethods = function (array $methods) {
    $clean = array();
    foreach ($methods as $m) {
        $m = strtoupper(trim((string) $m));
        if ($m !== '') {
            $clean[] = $m;
        }
    }
    if ($clean === array()) {
        $clean = array('GET');
    }
    $extra = count($clean) > 2 ? count($clean) - 2 : 0;
    return array(array_slice($clean, 0, 2), $extra);
};

foreach ($apis as $api):
    if (!is_array($api)) {
        continue;
    }
    $name = trim((string) (isset($api['name']) ? $api['name'] : ''));
    if ($name === '') {
        continue;
    }
    $desc = trim((string) (isset($api['desc']) ? $api['desc'] : ''));
    $cat = (string) (isset($api['category']) ? $api['category'] : '');
    $methods = isset($api['methods']) && is_array($api['methods']) ? $api['methods'] : array('GET');
    list($showMethods, $methodExtra) = $vsSplitMethods($methods);
    $endpoint = trim((string) (isset($api['endpoint']) ? $api['endpoint'] : ''));
    $nameKey = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $descKey = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
    $maintenance = !empty($api['maintenance']);
    $apiId = (int) (isset($api['id']) ? $api['id'] : 0);
    $detailUrl = !empty($api['detail_url'])
        ? (string) $api['detail_url']
        : ($apiId > 0 ? vs_api_detail_url($apiId) : ($vsBase . '/apis'));
    $points = isset($api['points']) ? (float) $api['points'] : 0;
    $needkey = isset($api['needkey']) ? (int) $api['needkey'] : 0;
    $billingLabel = !empty($api['billing_label'])
        ? (string) $api['billing_label']
        : ($points > 0
            ? (rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.') . '积分/次')
            : '免费');
    $cardClass = 'api-card' . ($cardExtraClass !== '' ? ' ' . $cardExtraClass : '');
    ?>
<div class="<?php echo vs_e($cardClass); ?>" data-category="<?php echo vs_e($cat); ?>" data-name="<?php echo vs_e($nameKey); ?>" data-desc="<?php echo vs_e($descKey); ?>" style="position: relative;">
    <?php if (!$maintenance): ?>
    <div style="position: absolute; top: 0.75rem; right: 0.75rem; display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end;">
        <?php if ($points > 0): ?>
            <span class="api-chip api-chip--points"><?php echo vs_e($billingLabel); ?></span>
        <?php else: ?>
            <span class="api-chip api-chip--free">免费</span>
        <?php endif; ?>
        <?php if ($needkey === 1): ?>
            <span class="api-chip api-chip--key">KEY必填</span>
        <?php elseif ($needkey === 2): ?>
            <span class="api-chip api-chip--key">KEY可选</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="flex justify-start items-start mb-2 flex-wrap gap-1">
        <?php foreach ($showMethods as $m): ?>
            <span class="method-badge <?php echo vs_e(strtolower($m)); ?>"><?php echo vs_e($m); ?></span>
        <?php endforeach; ?>
        <?php if ($methodExtra > 0): ?>
            <span class="api-item-more">+<?php echo (int) $methodExtra; ?></span>
        <?php endif; ?>
        <?php if ($maintenance): ?>
            <span class="api-chip api-chip--maintenance" style="margin-left: auto;">维护中</span>
        <?php endif; ?>
    </div>
    <h3 class="font-bold text-sm mb-1"><?php echo vs_e($name); ?></h3>
    <?php if ($desc !== ''): ?>
    <p class="text-xs mb-2" style="color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo vs_e($desc); ?></p>
    <?php endif; ?>
    <?php if ($endpoint !== ''): ?>
    <div class="endpoint-box font-mono" style="background: var(--endpoint-bg); border: 1px solid var(--endpoint-border); color: var(--accent-primary);"><?php echo vs_e($endpoint); ?></div>
    <?php endif; ?>
    <?php if ($showDetailBtn): ?>
    <a href="<?php echo vs_e($detailUrl); ?>" class="btn-geek w-full mt-2 text-center text-xs block">查看详情</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($apis === array()): ?>
<div class="col-span-full text-center py-8" style="color: var(--text-muted); grid-column: 1 / -1;">暂无已上线的公开接口</div>
<?php endif; ?>
