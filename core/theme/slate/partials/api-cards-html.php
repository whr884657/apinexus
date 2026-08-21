<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!isset($apiData) || !is_array($apiData)) {
    // 禁止回落全量 listForTheme（会误把整站目录 SSR 进 HTML）；调用方须显式传入列表
    $apiData = array();
}

$apis = $apiData;
$vsBase = isset($vsBase) ? $vsBase : vs_site_base_path();

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
    $endpoint = trim((string) (isset($api['endpoint']) ? $api['endpoint'] : ''));
    $nameKey = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $descKey = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
    $apiId = (int) (isset($api['id']) ? $api['id'] : 0);
    $detailUrl = !empty($api['detail_url'])
        ? (string) $api['detail_url']
        : ($apiId > 0 ? vs_api_detail_url($apiId) : ($vsBase . '/apis'));
    $maintenance = !empty($api['maintenance']);
    $disabled = !empty($api['disabled']);
    $points = isset($api['points']) ? (float) $api['points'] : 0;
    $needkey = isset($api['needkey']) ? (int) $api['needkey'] : 0;
    $billing = trim((string) (isset($api['billing_label']) ? $api['billing_label'] : ''));
    if ($billing === '') {
        $charge = !empty($api['charge']);
        if ($charge && $points > 0) {
            $billing = rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.') . '积分/次';
        } else {
            $billing = '免费';
        }
    }
    $isPaid = ($points > 0) || ($billing !== '免费' && strcasecmp($billing, 'free') !== 0);
    $showMethods = array_slice($methods, 0, 2);
    $methodExtra = count($methods) > 2 ? count($methods) - 2 : 0;

    $chips = array();
    if ($disabled) {
        $chips[] = array('class' => 'st-api-chip--disabled', 'text' => '已禁用');
    } elseif ($maintenance) {
        $chips[] = array('class' => 'st-api-chip--maintenance', 'text' => '维护中');
    }
    if (!$disabled) {
        if ($isPaid) {
            $chips[] = array('class' => 'st-api-chip--points', 'text' => $billing);
        } else {
            $chips[] = array('class' => 'st-api-chip--free', 'text' => '免费');
        }
        if ($needkey === 1) {
            $chips[] = array('class' => 'st-api-chip--key', 'text' => 'KEY必填');
        } elseif ($needkey === 2) {
            $chips[] = array('class' => 'st-api-chip--key', 'text' => 'KEY可选');
        }
    }
    ?>
<article class="st-api-card<?php echo $disabled ? ' is-disabled' : ($maintenance ? ' is-maintenance' : ''); ?>" data-category="<?php echo vs_e($cat); ?>" data-name="<?php echo vs_e($nameKey); ?>" data-desc="<?php echo vs_e($descKey); ?>">
    <a class="st-api-card__link" href="<?php echo vs_e($detailUrl); ?>">
        <div class="st-api-card__head">
            <div class="st-api-card__methods">
                <?php foreach ($showMethods as $m): ?>
                <?php $mUp = strtoupper(trim((string) $m)); $mCls = strtolower($mUp); ?>
                <span class="st-api-card__method st-api-card__method--<?php echo vs_e($mCls); ?>"><?php echo vs_e($mUp); ?></span>
                <?php endforeach; ?>
                <?php if ($methodExtra > 0): ?>
                <span class="st-api-card__method-more">+<?php echo (int) $methodExtra; ?></span>
                <?php endif; ?>
            </div>
            <?php if ($chips !== array()): ?>
            <div class="st-api-card__chips" aria-label="接口标签">
                <?php foreach ($chips as $chip): ?>
                <span class="st-api-chip <?php echo vs_e($chip['class']); ?>"><?php echo vs_e($chip['text']); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <h3 class="st-api-card__title"><?php echo vs_e($name); ?></h3>
        <code class="st-api-card__endpoint"><?php echo $endpoint !== '' ? vs_e($endpoint) : '&nbsp;'; ?></code>
    </a>
</article>
<?php endforeach; ?>
<?php if ($apis === array()): ?>
<div class="st-api-empty st-api-empty--inline">
    <p class="st-api-empty__title">暂无已上线的公开接口</p>
</div>
<?php endif; ?>
