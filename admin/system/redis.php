<?php
/**
 * 文件：admin/system/redis.php
 * 作用：Redis 管理（misc-api 业务缓存监控 + 服务器简要状态）
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
        AjaxResponse::success('本系统业务缓存已清空', array('snapshot' => RedisService::collectMonitorSnapshot()));
    }

    AjaxResponse::error('无效操作', 400);
}

$snapshot = RedisService::collectMonitorSnapshot();
$biz = isset($snapshot['business']) ? $snapshot['business'] : array();
$server = isset($snapshot['server']) ? $snapshot['server'] : array();

vs_admin_layout_start(
    'Redis 管理',
    'redis',
    '<button type="button" class="vs-btn vs-btn--default" id="redisClearBtn">清空业务缓存</button>'
    . '<button type="button" class="vs-btn vs-btn--primary" id="redisRefreshBtn">刷新</button>'
);
?>

<div class="vs-panel vs-redis-panel" id="redisMonitorPanel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">本系统 Redis 缓存</h2>
        <p class="vs-panel__desc">仅监控 misc-api 前缀下的<strong>业务缓存</strong>（公开接口、分类标签、发信限流等），用于减轻 MySQL 读取与写入压力。</p>
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

    <div class="vs-stat-grid vs-redis-hero-grid">
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">缓存命中次数</span>
            <span class="vs-stat-card__value" data-redis-field="app_hits"><?php echo (int) (isset($biz['app_hits']) ? $biz['app_hits'] : 0); ?></span>
            <span class="vs-redis-stat-card__hint">读取缓存成功（累计）</span>
        </div>
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">缓存未命中</span>
            <span class="vs-stat-card__value" data-redis-field="app_misses"><?php echo (int) (isset($biz['app_misses']) ? $biz['app_misses'] : 0); ?></span>
            <span class="vs-redis-stat-card__hint">回源 MySQL 后写入缓存</span>
        </div>
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">业务命中率</span>
            <span class="vs-stat-card__value" data-redis-field="app_hit_rate"><?php
                $rate = isset($biz['app_hit_rate_percent']) ? $biz['app_hit_rate_percent'] : null;
                echo $rate === null ? '—' : vs_e($rate . '%');
            ?></span>
            <span class="vs-redis-stat-card__hint">仅统计 misc-api 业务缓存</span>
        </div>
        <div class="vs-stat-card vs-redis-stat-card">
            <span class="vs-stat-card__label">缓存占用（估算）</span>
            <span class="vs-stat-card__value" data-redis-field="cache_memory"><?php echo vs_e(isset($biz['cache_memory_human']) ? $biz['cache_memory_human'] : '—'); ?></span>
            <span class="vs-redis-stat-card__hint">cache:* 与 rl:* 键值大小合计</span>
        </div>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">业务缓存项</h3>
        <p class="vs-panel__desc vs-redis-section__desc">以下数据来自 misc-api 自动写入的缓存，前台访问接口页时会优先读 Redis。</p>
        <div class="vs-redis-entry-list" id="redisEntryList">
            <?php foreach ((isset($biz['entries']) ? $biz['entries'] : array()) as $entry): ?>
                <div class="vs-redis-entry">
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
                            <span class="vs-redis-entry__detail">剩余 <?php echo (int) $entry['ttl_seconds']; ?> 秒 · <?php echo vs_e($entry['size_human']); ?></span>
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
        <h3 class="vs-form-section__title">键数量统计</h3>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">数据缓存键（cache:*）</span>
                <span class="vs-info-item__value" data-redis-field="cache_keys"><?php echo (int) (isset($biz['cache_keys']) ? $biz['cache_keys'] : 0); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">发信限流键（rl:*）</span>
                <span class="vs-info-item__value" data-redis-field="rate_limit_keys"><?php echo (int) (isset($biz['rate_limit_keys']) ? $biz['rate_limit_keys'] : 0); ?></span>
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
                <span class="vs-info-item__value" data-redis-field="uptime_human"><?php echo vs_e(isset($server['uptime_human']) ? $server['uptime_human'] : '—'); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">进程内存占用</span>
                <span class="vs-info-item__value" data-redis-field="used_memory_human"><?php echo vs_e(isset($server['used_memory_human']) ? $server['used_memory_human'] : '—'); ?></span>
            </div>
        </div>
    </details>
</div>

<?php vs_admin_layout_end(array('redis.js')); ?>
