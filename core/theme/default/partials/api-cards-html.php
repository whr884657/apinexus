<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!isset($apiData) || !is_array($apiData)) {
    $apiData = FrontendApi::listForTheme();
}

$apis = $apiData;
$showDetailBtn = !isset($showDetailBtn) || $showDetailBtn;
$detailUrl = isset($detailUrl) ? $detailUrl : ($vsBase . '/apis');

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
    $maintenance = !empty($api['maintenance']);
    ?>
<div class="api-card" data-category="<?php echo vs_e($cat); ?>" data-name="<?php echo vs_e($nameKey); ?>" data-desc="<?php echo vs_e($descKey); ?>" style="position: relative;">
    <div style="position: absolute; top: 0.75rem; right: 0.75rem; display: flex; gap: 0.25rem; flex-wrap: wrap; justify-content: flex-end;">
        <?php if ($maintenance): ?><span class="api-chip api-chip--maintenance">维护中</span><?php else: ?><span class="api-chip api-chip--free">免费</span><?php endif; ?>
    </div>
    <div class="flex justify-start items-start mb-2 flex-wrap gap-1">
        <?php foreach (array_slice($methods, 0, 3) as $m): ?>
            <span class="method-badge <?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
        <?php endforeach; ?>
    </div>
    <h3 class="font-bold text-sm mb-1"><?php echo vs_e($name); ?></h3>
    <?php if ($desc !== ''): ?>
    <p class="text-xs mb-2" style="color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo vs_e($desc); ?></p>
    <?php endif; ?>
    <?php if ($endpoint !== ''): ?>
    <div class="endpoint-box"><?php echo vs_e($endpoint); ?></div>
    <?php endif; ?>
    <?php if ($showDetailBtn): ?>
    <a href="<?php echo vs_e($detailUrl); ?>" class="btn-geek w-full mt-2 text-center text-xs block">查看详情</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($apis === array()): ?>
<div class="col-span-full text-center py-8" style="color: var(--text-muted); grid-column: 1 / -1;">暂无已上线的公开接口</div>
<?php endif; ?>
