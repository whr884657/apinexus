<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$publicApis = isset($publicApis) && is_array($publicApis) ? $publicApis : array();
$gridId = isset($gridId) ? (string) $gridId : 'dtApiGrid';
$emptyMessage = isset($emptyMessage) ? (string) $emptyMessage : '暂无已上线的公开接口，欢迎注册后在用户中心提交接口。';
$showEndpoint = !isset($showEndpoint) || $showEndpoint;
$cardLimit = isset($cardLimit) ? (int) $cardLimit : 0;
$renderApis = $publicApis;
if ($cardLimit > 0 && count($renderApis) > $cardLimit) {
    $renderApis = array_slice($renderApis, 0, $cardLimit);
}
?>
<div class="dt-api-grid" id="<?php echo vs_e($gridId); ?>" data-dt-api-grid>
<?php if ($renderApis === array()): ?>
    <div class="dt-api-empty"><?php echo vs_e($emptyMessage); ?></div>
<?php else: ?>
    <?php foreach ($renderApis as $api): ?>
        <?php
        if (!is_array($api)) {
            continue;
        }
        $name = trim((string) (isset($api['name']) ? $api['name'] : ''));
        $desc = trim((string) (isset($api['description']) ? $api['description'] : ''));
        $method = strtoupper(trim((string) (isset($api['method']) ? $api['method'] : 'GET')));
        $category = trim((string) (isset($api['category']) ? $api['category'] : ''));
        $endpoint = trim((string) (isset($api['endpoint']) ? $api['endpoint'] : ''));
        $type = strtolower(trim((string) (isset($api['type']) ? $api['type'] : 'api')));
        if ($name === '') {
            continue;
        }
        $nameKey = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $descKey = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
        $methodClass = 'dt-method--' . strtolower(preg_replace('/[^a-z0-9]/i', '', $method));
        if ($methodClass === 'dt-method--') {
            $methodClass = 'dt-method--get';
        }
        ?>
        <article class="dt-api-card"
                 data-name="<?php echo vs_e($nameKey); ?>"
                 data-desc="<?php echo vs_e($descKey); ?>"
                 data-category="<?php echo vs_e($category); ?>">
            <div class="dt-api-card__top">
                <span class="dt-method <?php echo vs_e($methodClass); ?>"><?php echo vs_e($method !== '' ? $method : 'GET'); ?></span>
                <?php if ($type === 'tapi'): ?>
                    <span class="dt-api-chip dt-api-chip--tapi">TAPI</span>
                <?php else: ?>
                    <span class="dt-api-chip">API</span>
                <?php endif; ?>
            </div>
            <h3 class="dt-api-card__title"><?php echo vs_e($name); ?></h3>
            <?php if ($desc !== ''): ?>
                <p class="dt-api-card__desc"><?php echo vs_e($desc); ?></p>
            <?php endif; ?>
            <?php if ($category !== ''): ?>
                <span class="dt-api-card__cat"><?php echo vs_e($category); ?></span>
            <?php endif; ?>
            <?php if ($showEndpoint && $endpoint !== ''): ?>
                <div class="dt-api-card__endpoint" title="<?php echo vs_e($endpoint); ?>"><?php echo vs_e($endpoint); ?></div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
</div>
