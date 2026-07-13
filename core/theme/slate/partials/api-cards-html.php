<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!isset($payload) || !is_array($payload)) {
    require_once __DIR__ . '/../includes/api-payload.php';
    $payload = vs_theme_api_payload();
}

$apis = isset($payload['apiData']) ? $payload['apiData'] : array();

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
    $endpoint = trim((string) (isset($api['full_url']) ? $api['full_url'] : (isset($api['endpoint']) ? $api['endpoint'] : '')));
    $nameKey = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $descKey = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
    $methodClass = strtolower(trim((string) $methods[0]));
    ?>
<article class="st-api-card" data-category="<?php echo vs_e($cat); ?>" data-name="<?php echo vs_e($nameKey); ?>" data-desc="<?php echo vs_e($descKey); ?>">
    <div class="st-api-card__head">
        <span class="st-api-card__method st-api-card__method--<?php echo vs_e($methodClass); ?>"><?php echo vs_e(strtoupper(trim((string) $methods[0]))); ?></span>
        <span class="st-api-card__badge">免费</span>
    </div>
    <h3 class="st-api-card__title"><?php echo vs_e($name); ?></h3>
    <?php if ($desc !== ''): ?>
    <p class="st-api-card__desc"><?php echo vs_e($desc); ?></p>
    <?php endif; ?>
    <?php if ($endpoint !== ''): ?>
    <code class="st-api-card__endpoint"><?php echo vs_e($endpoint); ?></code>
    <?php endif; ?>
</article>
<?php endforeach; ?>
<?php if ($apis === array()): ?>
<div class="st-api-empty st-api-empty--inline">
    <p class="st-api-empty__title">暂无已上线的公开接口</p>
</div>
<?php endif; ?>
