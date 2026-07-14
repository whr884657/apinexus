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
$missPercent = $hitTotal > 0 ? round(100 - $hitPercent, 1) : 0;

$cacheKeys = (int) (isset($biz['cache_keys']) ? $biz['cache_keys'] : 0);
$rateKeys = (int) (isset($biz['rate_limit_keys']) ? $biz['rate_limit_keys'] : 0);
$keyTotal = $cacheKeys + $rateKeys;
$cacheKeyPercent = $keyTotal > 0 ? round(($cacheKeys / $keyTotal) * 100, 1) : 0;
$rateKeyPercent = $keyTotal > 0 ? round(100 - $cacheKeyPercent, 1) : 0;

$entries = isset($biz['entries']) ? $biz['entries'] : array();
$cachedCount = 0;
foreach ($entries as $entry) {
    if (!empty($entry['cached'])) {
        $cachedCount++;
    }
}
$entryTotal = count($entries);
$entryCachedPercent = $entryTotal > 0 ? round(($cachedCount / $entryTotal) * 100, 1) : 0;
$entryMissPercent = $entryTotal > 0 ? round(100 - $entryCachedPercent, 1) : 0;

vs_admin_layout_start(
    'Redis 管理',
    'redis',
    '<button type="button" class="vs-btn vs-btn--default" id="redisClearBtn">清空业务缓存</button>'
    . '<button type="button" class="vs-btn vs-btn--primary" id="redisRefreshBtn">刷新</button>'
);
?>

<div class="vs-panel vs-redis-panel" id="redisMonitorPanel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">Redis 缓存监控</h2>
        <p class="vs-panel__desc">查看业务缓存命中率、键分布与缓存项状态；仅高频读取的数据会写入 Redis，其余仍走 MySQL。</p>
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

    <div class="vs-redis-charts" id="redisCharts">
        <div class="vs-redis-chart-card">
            <div class="vs-redis-chart-card__title">命中分布</div>
            <div class="vs-redis-donut" id="redisChartHit" style="--p1: <?php echo vs_e($hitPercent); ?>; --c1: #10b981; --c2: #e5e7eb;">
                <span class="vs-redis-donut__label" data-redis-field="chart_hit_label"><?php echo $hitTotal > 0 ? vs_e($hitPercent . '%') : '—'; ?></span>
            </div>
            <ul class="vs-redis-chart-legend">
                <li><span class="vs-redis-chart-legend__name"><span class="vs-redis-chart-legend__dot" style="background:#10b981"></span>命中</span><span class="vs-redis-chart-legend__val" data-redis-field="chart_hits"><?php echo $hits; ?></span></li>
                <li><span class="vs-redis-chart-legend__name"><span class="vs-redis-chart-legend__dot" style="background:#e5e7eb"></span>未命中</span><span class="vs-redis-chart-legend__val" data-redis-field="chart_misses"><?php echo $misses; ?></span></li>
            </ul>
        </div>
        <div class="vs-redis-chart-card">
            <div class="vs-redis-chart-card__title">键类型分布</div>
            <div class="vs-redis-donut" id="redisChartKeys" style="--p1: <?php echo vs_e($cacheKeyPercent); ?>; --c1: #3b82f6; --c2: #fde68a;">
                <span class="vs-redis-donut__label" data-redis-field="chart_key_label"><?php echo $keyTotal > 0 ? vs_e($cacheKeyPercent . '%') : '—'; ?></span>
            </div>
            <ul class="vs-redis-chart-legend">
                <li><span class="vs-redis-chart-legend__name"><span class="vs-redis-chart-legend__dot" style="background:#3b82f6"></span>数据缓存</span><span class="vs-redis-chart-legend__val" data-redis-field="cache_keys"><?php echo $cacheKeys; ?></span></li>
                <li><span class="vs-redis-chart-legend__name"><span class="vs-redis-chart-legend__dot" style="background:#fde68a"></span>发信限流</span><span class="vs-redis-chart-legend__val" data-redis-field="rate_limit_keys"><?php echo $rateKeys; ?></span></li>
            </ul>
        </div>
        <div class="vs-redis-chart-card">
            <div class="vs-redis-chart-card__title">缓存项状态</div>
            <div class="vs-redis-donut" id="redisChartEntries" style="--p1: <?php echo vs_e($entryCachedPercent); ?>; --c1: #8b5cf6; --c2: #f3f4f6;">
                <span class="vs-redis-donut__label" data-redis-field="chart_entry_label"><?php echo $entryTotal > 0 ? vs_e($entryCachedPercent . '%') : '—'; ?></span>
            </div>
            <ul class="vs-redis-chart-legend">
                <li><span class="vs-redis-chart-legend__name"><span class="vs-redis-chart-legend__dot" style="background:#8b5cf6"></span>已缓存</span><span class="vs-redis-chart-legend__val" data-redis-field="chart_cached_count"><?php echo $cachedCount; ?></span></li>
                <li><span class="vs-redis-chart-legend__name"><span class="vs-redis-chart-legend__dot" style="background:#f3f4f6;border:1px solid #e5e7eb"></span>未缓存</span><span class="vs-redis-chart-legend__val" data-redis-field="chart_uncached_count"><?php echo max(0, $entryTotal - $cachedCount); ?></span></li>
            </ul>
        </div>
    </div>

    <div class="vs-stat-grid vs-redis-hero-grid">
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">缓存命中次数</span>
            <span class="vs-stat-card__value" data-redis-field="app_hits"><?php echo $hits; ?></span>
            <span class="vs-redis-stat-card__hint">读取缓存成功（累计）</span>
        </div>
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">缓存未命中</span>
            <span class="vs-stat-card__value" data-redis-field="app_misses"><?php echo $misses; ?></span>
            <span class="vs-redis-stat-card__hint">回源 MySQL 后写入缓存</span>
        </div>
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">业务命中率</span>
            <span class="vs-stat-card__value" data-redis-field="app_hit_rate"><?php
                $rate = isset($biz['app_hit_rate_percent']) ? $biz['app_hit_rate_percent'] : null;
                echo $rate === null ? '—' : vs_e($rate . '%');
            ?></span>
            <span class="vs-redis-stat-card__hint">命中 /（命中 + 未命中）</span>
        </div>
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">缓存占用（估算）</span>
            <span class="vs-stat-card__value" data-redis-field="cache_memory"><?php echo vs_e(isset($biz['cache_memory_human']) ? $biz['cache_memory_human'] : '—'); ?></span>
            <span class="vs-redis-stat-card__hint">cache:* 与 rl:* 键值大小合计</span>
        </div>
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
                <span class="vs-info-item__label">数据缓存键（cache:*）</span>
                <span class="vs-info-item__value" data-redis-field="cache_keys_dup"><?php echo $cacheKeys; ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">发信限流键（rl:*）</span>
                <span class="vs-info-item__value" data-redis-field="rate_limit_keys_dup"><?php echo $rateKeys; ?></span>
            </div>
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
