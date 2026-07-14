<?php
/**
 * 文件：admin/system/redis.php
 * 作用：Redis 管理（业务缓存监控 + 可视化统计）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'refresh') {
        $snapshot = RedisService::collectMonitorSnapshot();
        AjaxResponse::success('监控数据已刷新', array('snapshot' => $snapshot));
    }

    if ($action === 'clear_cache') {
        RedisCache::invalidateFrontend();
        AjaxResponse::success('业务缓存已清空', array('snapshot' => RedisService::collectMonitorSnapshot()));
    }

    AjaxResponse::error('无效操作', 400);
}

$snapshot = RedisService::collectMonitorSnapshot();
$biz = isset($snapshot['business']) ? $snapshot['business'] : array();
$server = isset($snapshot['server']) ? $snapshot['server'] : array();

$hits = (int) (isset($biz['app_hits']) ? $biz['app_hits'] : 0);
$misses = (int) (isset($biz['app_misses']) ? $biz['app_misses'] : 0);
$hitTotal = $hits + $misses;
$hitPercent = $hitTotal > 0 ? round(($hits / $hitTotal) * 100, 1) : 0;

$cacheKeys = (int) (isset($biz['cache_keys']) ? $biz['cache_keys'] : 0);
$rateKeys = (int) (isset($biz['rate_limit_keys']) ? $biz['rate_limit_keys'] : 0);
$keyTotal = $cacheKeys + $rateKeys;
$cacheKeyPercent = $keyTotal > 0 ? round(($cacheKeys / $keyTotal) * 100, 1) : 0;

$entries = isset($biz['entries']) ? $biz['entries'] : array();
$cachedCount = 0;
foreach ($entries as $entry) {
    if (!empty($entry['cached'])) {
        $cachedCount++;
    }
}
$entryTotal = count($entries);
$entryCachedPercent = $entryTotal > 0 ? round(($cachedCount / $entryTotal) * 100, 1) : 0;
$cacheMemory = isset($biz['cache_memory_human']) ? (string) $biz['cache_memory_human'] : '—';

$chartBoot = array(
    'hit' => array(
        'title' => '命中分布',
        'centerValue' => $hitTotal > 0 ? ($hitPercent . '%') : '—',
        'centerHint' => '命中率',
        'segments' => array(
            array('id' => 'hits', 'label' => '命中', 'value' => $hits, 'color' => '#10b981', 'unit' => '次'),
            array('id' => 'misses', 'label' => '未命中', 'value' => $misses, 'color' => '#d1d5db', 'unit' => '次'),
        ),
    ),
    'keys' => array(
        'title' => '键类型分布',
        'centerValue' => $keyTotal > 0 ? ($cacheKeyPercent . '%') : '—',
        'centerHint' => '数据缓存占比',
        'segments' => array(
            array('id' => 'cache', 'label' => '数据缓存', 'value' => $cacheKeys, 'color' => '#3b82f6', 'unit' => '个键'),
            array('id' => 'rate', 'label' => '发信限流', 'value' => $rateKeys, 'color' => '#fbbf24', 'unit' => '个键'),
        ),
    ),
    'entries' => array(
        'title' => '缓存项状态',
        'centerValue' => $cacheMemory !== '' ? $cacheMemory : '—',
        'centerHint' => '缓存占用',
        'segments' => array(
            array(
                'id' => 'cached',
                'label' => '已缓存',
                'value' => $cachedCount,
                'color' => '#8b5cf6',
                'unit' => '项',
                'extra' => '占用 ' . $cacheMemory,
            ),
            array(
                'id' => 'uncached',
                'label' => '未缓存',
                'value' => max(0, $entryTotal - $cachedCount),
                'color' => '#e5e7eb',
                'unit' => '项',
            ),
        ),
    ),
);

vs_admin_layout_start(
    'Redis 管理',
    'redis',
    '<button type="button" class="vs-btn vs-btn--default" id="redisClearBtn">清空业务缓存</button>'
    . '<button type="button" class="vs-btn vs-btn--primary" id="redisRefreshBtn">刷新</button>'
);
?>

<div class="vs-panel vs-redis-panel" id="redisMonitorPanel"
     data-chart-boot="<?php echo vs_e(json_encode($chartBoot, JSON_UNESCAPED_UNICODE)); ?>">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">Redis 缓存监控</h2>
        <p class="vs-panel__desc">悬停或点击环形图扇区查看明细；仅高频读取的数据写入 Redis，其余仍走 MySQL。</p>
    </div>

    <div id="redisStatusNotice">
        <?php if (!$snapshot['extension_loaded']): ?>
            <?php vs_render_notice('danger', '未安装 Redis 扩展', '系统将以 MySQL 直连运行，无法使用 Redis 缓存。请在 PHP 中启用 redis 扩展。', array('compact' => true)); ?>
        <?php elseif (!$snapshot['connected']): ?>
            <?php vs_render_notice('warning', 'Redis 未连接', vs_e($snapshot['error'] !== '' ? $snapshot['error'] : '请启动 Redis 并检查连接配置。'), array('compact' => true)); ?>
        <?php else: ?>
            <?php vs_render_notice('success', 'Redis 已连接', '业务缓存正常工作；修改接口或分类后会自动刷新相关缓存。', array('compact' => true)); ?>
        <?php endif; ?>
    </div>

    <div class="vs-redis-charts" id="redisCharts" role="group" aria-label="Redis 监控图表">
        <?php foreach ($chartBoot as $chartId => $chart): ?>
            <div class="vs-redis-chart-card" data-redis-chart="<?php echo vs_e($chartId); ?>">
                <div class="vs-redis-chart-card__title"><?php echo vs_e($chart['title']); ?></div>
                <div class="vs-redis-donut-wrap">
                    <svg class="vs-redis-donut-svg" viewBox="0 0 120 120" role="img" aria-label="<?php echo vs_e($chart['title']); ?>"></svg>
                    <div class="vs-redis-donut__center" aria-hidden="true">
                        <span class="vs-redis-donut__value"><?php echo vs_e($chart['centerValue']); ?></span>
                        <span class="vs-redis-donut__hint"><?php echo vs_e($chart['centerHint']); ?></span>
                    </div>
                </div>
                <div class="vs-redis-chart-tip" data-redis-tip>悬停或点击扇区查看明细</div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">业务缓存项</h3>
        <div class="vs-redis-entry-list" id="redisEntryList">
            <?php foreach ($entries as $entry): ?>
                <div class="vs-redis-entry" data-cached="<?php echo !empty($entry['cached']) ? '1' : '0'; ?>" data-ttl="<?php echo !empty($entry['cached']) ? (int) $entry['ttl_seconds'] : ''; ?>" data-size="<?php echo vs_e(isset($entry['size_human']) ? $entry['size_human'] : '—'); ?>">
                    <div class="vs-redis-entry__main">
                        <div class="vs-redis-entry__title"><?php echo vs_e($entry['label']); ?></div>
                        <div class="vs-redis-entry__meta">
                            刷新周期 <?php echo vs_e($entry['ttl_hint']); ?>
                            · 键 <?php echo vs_e($entry['key']); ?>
                        </div>
                    </div>
                    <div class="vs-redis-entry__status">
                        <?php if (!empty($entry['cached'])): ?>
                            <span class="vs-redis-badge vs-redis-badge--on">已缓存</span>
                            <span class="vs-redis-entry__detail" data-redis-ttl-text>剩余 <?php echo (int) $entry['ttl_seconds']; ?> 秒 · <?php echo vs_e($entry['size_human']); ?></span>
                        <?php else: ?>
                            <span class="vs-redis-badge vs-redis-badge--off">未缓存</span>
                            <span class="vs-redis-entry__detail">下次访问时自动建立</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">连接信息</h3>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">连接地址</span>
                <span class="vs-info-item__value"><?php
                    $cfg = $snapshot['config'];
                    echo vs_e($cfg['host'] . ':' . $cfg['port'] . ' / db' . $cfg['database']);
                ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">采集时间</span>
                <span class="vs-info-item__value" data-redis-field="collected_at"><?php echo vs_e($snapshot['collected_at']); ?></span>
            </div>
        </div>
    </div>

    <details class="vs-redis-details">
        <summary>服务器参考信息（Redis 进程）</summary>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">Redis 版本</span>
                <span class="vs-info-item__value" data-redis-field="redis_version"><?php echo vs_e(isset($server['redis_version']) ? $server['redis_version'] : '—'); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">运行时长</span>
                <span class="vs-info-item__value" data-redis-field="uptime_human" data-uptime-seconds="<?php echo (int) (isset($server['uptime_seconds']) ? $server['uptime_seconds'] : 0); ?>"><?php echo vs_e(isset($server['uptime_human']) ? $server['uptime_human'] : '—'); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">进程内存占用</span>
                <span class="vs-info-item__value" data-redis-field="used_memory_human"><?php echo vs_e(isset($server['used_memory_human']) ? $server['used_memory_human'] : '—'); ?></span>
            </div>
        </div>
    </details>
</div>

<?php vs_admin_layout_end(array('redis.js')); ?>
