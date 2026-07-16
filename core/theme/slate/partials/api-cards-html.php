<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!isset($apiData) || !is_array($apiData)) {
    $apiData = FrontendApi::listForTheme();
}

$apis = $apiData;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');

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
    ?>
<article class="st-api-card" data-category="<?php echo vs_e($cat); ?>" data-name="<?php echo vs_e($nameKey); ?>" data-desc="<?php echo vs_e($descKey); ?>">
    <a class="st-api-card__link" href="<?php echo vs_e($detailUrl); ?>">
        <div class="st-api-card__head">
            <div class="st-api-card__methods">
                <?php foreach (array_slice($methods, 0, 2) as $m): ?>
                <span class="st-api-card__method st-api-card__method--<?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
                <?php endforeach; ?>
            </div>
            <span class="st-api-card__badge">免费</span>
        </div>
        <h3 class="st-api-card__title"><?php echo vs_e($name); ?></h3>
        <p class="st-api-card__desc"><?php echo $desc !== '' ? vs_e($desc) : '&nbsp;'; ?></p>
        <code class="st-api-card__endpoint"><?php echo $endpoint !== '' ? vs_e($endpoint) : '&nbsp;'; ?></code>
    </a>
</article>
<?php endforeach; ?>
<?php if ($apis === array()): ?>
<div class="st-api-empty st-api-empty--inline">
    <p class="st-api-empty__title">暂无已上线的公开接口</p>
</div>
<?php endif; ?>
