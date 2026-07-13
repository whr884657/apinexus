<?php
/**
 * 文件：admin/system/redis.php
 * 作用：Redis 管理（连接状态、内存与缓存监控）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'refresh') {
        $snapshot = RedisService::collectMonitorSnapshot();
        AjaxResponse::success('监控数据已刷新', array('snapshot' => $snapshot));
    }

    AjaxResponse::error('无效操作', 400);
}

$snapshot = RedisService::collectMonitorSnapshot();

/**
 * @param mixed $value
 * @return string
 */
function vs_redis_display_value($value)
{
    if ($value === null || $value === '') {
        return '—';
    }
    return (string) $value;
}

/**
 * @param array $snapshot
 * @return void
 */
function vs_redis_render_status_notice(array $snapshot)
{
    if (!$snapshot['extension_loaded']) {
        vs_render_notice(
            'danger',
            'Redis 扩展未就绪',
            '安装向导要求 PHP redis 扩展，请确认服务器已安装并启用该扩展后重试。',
            array('compact' => true)
        );
        return;
    }

    if (!$snapshot['connected']) {
        vs_render_notice(
            'warning',
            'Redis 未连接',
            vs_e($snapshot['error'] !== '' ? $snapshot['error'] : '请检查 Redis 服务是否启动，以及连接地址与认证配置。'),
            array('compact' => true)
        );
        return;
    }

    vs_render_notice(
        'success',
        'Redis 运行正常',
        '已成功连接并读取服务器 INFO。下方数据可通过「刷新监控」获取最新状态。',
        array('compact' => true)
    );
}

vs_admin_layout_start(
    'Redis 管理',
    'redis',
    '<button type="button" class="vs-btn vs-btn--primary" id="redisRefreshBtn">刷新监控</button>'
);
?>

<div class="vs-panel" id="redisMonitorPanel" data-snapshot="<?php echo vs_e(json_encode($snapshot, JSON_UNESCAPED_UNICODE)); ?>">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">Redis 监控</h2>
        <p class="vs-panel__desc">查看当前 misc-api 所连 Redis 的版本、内存占用、键数量与命中率等指标。</p>
    </div>

    <div id="redisStatusNotice">
        <?php vs_redis_render_status_notice($snapshot); ?>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">连接信息</h3>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">连接地址</span>
                <span class="vs-info-item__value" data-redis-field="config_host"><?php echo vs_e($snapshot['config']['host'] . ':' . $snapshot['config']['port']); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">数据库</span>
                <span class="vs-info-item__value" data-redis-field="config_database">db<?php echo (int) $snapshot['config']['database']; ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">键前缀</span>
                <span class="vs-info-item__value" data-redis-field="config_prefix"><?php echo vs_e($snapshot['config']['prefix']); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">密码认证</span>
                <span class="vs-info-item__value" data-redis-field="config_auth"><?php echo $snapshot['config']['has_password'] ? '已配置' : '未配置'; ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">采集时间</span>
                <span class="vs-info-item__value" data-redis-field="collected_at"><?php echo vs_e($snapshot['collected_at']); ?></span>
            </div>
        </div>
        <?php vs_render_notice('tip', '', '连接参数可在 config 表键 redis_host / redis_port / redis_password / redis_database / redis_prefix 中配置；未配置时使用 127.0.0.1:6379、db0、前缀 misc_api:。', array('compact' => true)); ?>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">核心指标</h3>
        <div class="vs-stat-grid vs-redis-stat-grid">
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">Redis 版本</span>
                <span class="vs-stat-card__value" data-redis-field="redis_version"><?php echo vs_e(vs_redis_display_value(isset($snapshot['server']['redis_version']) ? $snapshot['server']['redis_version'] : '')); ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">运行时长</span>
                <span class="vs-stat-card__value" data-redis-field="uptime_human"><?php echo vs_e(vs_redis_display_value(isset($snapshot['server']['uptime_human']) ? $snapshot['server']['uptime_human'] : '')); ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">内存占用</span>
                <span class="vs-stat-card__value" data-redis-field="used_memory_human"><?php echo vs_e(vs_redis_display_value(isset($snapshot['memory']['used_memory_human']) ? $snapshot['memory']['used_memory_human'] : '')); ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">内存峰值</span>
                <span class="vs-stat-card__value" data-redis-field="used_memory_peak_human"><?php echo vs_e(vs_redis_display_value(isset($snapshot['memory']['used_memory_peak_human']) ? $snapshot['memory']['used_memory_peak_human'] : '')); ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">当前库键总数</span>
                <span class="vs-stat-card__value" data-redis-field="keys_total"><?php echo vs_e(vs_redis_display_value(isset($snapshot['keys']['total']) ? $snapshot['keys']['total'] : '')); ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">本系统前缀键</span>
                <span class="vs-stat-card__value" data-redis-field="keys_prefixed"><?php echo vs_e(vs_redis_display_value(isset($snapshot['keys']['prefixed']) ? $snapshot['keys']['prefixed'] : '')); ?><?php echo (isset($snapshot['keys']['prefixed']) && (int) $snapshot['keys']['prefixed'] >= 50000) ? '+' : ''; ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">缓存命中率</span>
                <span class="vs-stat-card__value" data-redis-field="hit_rate_percent"><?php
                    $rate = isset($snapshot['stats']['hit_rate_percent']) ? $snapshot['stats']['hit_rate_percent'] : null;
                    echo vs_e($rate === null ? '—' : $rate . '%');
                ?></span>
            </div>
            <div class="vs-stat-card">
                <span class="vs-stat-card__label">在线客户端</span>
                <span class="vs-stat-card__value" data-redis-field="connected_clients"><?php echo vs_e(vs_redis_display_value(isset($snapshot['stats']['connected_clients']) ? $snapshot['stats']['connected_clients'] : '')); ?></span>
            </div>
        </div>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">内存与性能</h3>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">RSS 内存</span>
                <span class="vs-info-item__value" data-redis-field="used_memory_rss_human"><?php echo vs_e(vs_redis_display_value(isset($snapshot['memory']['used_memory_rss_human']) ? $snapshot['memory']['used_memory_rss_human'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">最大内存限制</span>
                <span class="vs-info-item__value" data-redis-field="maxmemory_human"><?php echo vs_e(vs_redis_display_value(isset($snapshot['memory']['maxmemory_human']) ? $snapshot['memory']['maxmemory_human'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">内存使用率</span>
                <span class="vs-info-item__value" data-redis-field="usage_percent"><?php
                    $usage = isset($snapshot['memory']['usage_percent']) ? $snapshot['memory']['usage_percent'] : null;
                    echo vs_e($usage === null ? '—' : $usage . '%');
                ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">内存碎片率</span>
                <span class="vs-info-item__value" data-redis-field="mem_fragmentation_ratio"><?php echo vs_e(vs_redis_display_value(isset($snapshot['memory']['mem_fragmentation_ratio']) ? $snapshot['memory']['mem_fragmentation_ratio'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">每秒操作数</span>
                <span class="vs-info-item__value" data-redis-field="instantaneous_ops_per_sec"><?php echo vs_e(vs_redis_display_value(isset($snapshot['stats']['instantaneous_ops_per_sec']) ? $snapshot['stats']['instantaneous_ops_per_sec'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">累计命令数</span>
                <span class="vs-info-item__value" data-redis-field="total_commands_processed"><?php echo vs_e(vs_redis_display_value(isset($snapshot['stats']['total_commands_processed']) ? $snapshot['stats']['total_commands_processed'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">命中 / 未命中</span>
                <span class="vs-info-item__value" data-redis-field="hits_misses"><?php
                    $hits = isset($snapshot['stats']['keyspace_hits']) ? (int) $snapshot['stats']['keyspace_hits'] : 0;
                    $misses = isset($snapshot['stats']['keyspace_misses']) ? (int) $snapshot['stats']['keyspace_misses'] : 0;
                    echo vs_e($hits . ' / ' . $misses);
                ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">过期 / 驱逐键</span>
                <span class="vs-info-item__value" data-redis-field="expired_evicted"><?php
                    $expired = isset($snapshot['stats']['expired_keys']) ? (int) $snapshot['stats']['expired_keys'] : 0;
                    $evicted = isset($snapshot['stats']['evicted_keys']) ? (int) $snapshot['stats']['evicted_keys'] : 0;
                    echo vs_e($expired . ' / ' . $evicted);
                ?></span>
            </div>
        </div>
    </div>

    <div class="vs-redis-section">
        <h3 class="vs-form-section__title">服务器信息</h3>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">运行模式</span>
                <span class="vs-info-item__value" data-redis-field="redis_mode"><?php echo vs_e(vs_redis_display_value(isset($snapshot['server']['redis_mode']) ? $snapshot['server']['redis_mode'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">角色</span>
                <span class="vs-info-item__value" data-redis-field="role"><?php echo vs_e(vs_redis_display_value(isset($snapshot['server']['role']) ? $snapshot['server']['role'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">操作系统</span>
                <span class="vs-info-item__value" data-redis-field="os"><?php echo vs_e(vs_redis_display_value(isset($snapshot['server']['os']) ? $snapshot['server']['os'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">架构</span>
                <span class="vs-info-item__value" data-redis-field="arch_bits"><?php
                    $arch = isset($snapshot['server']['arch_bits']) ? $snapshot['server']['arch_bits'] : '';
                    echo vs_e($arch !== '' ? $arch . ' bit' : '—');
                ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">阻塞客户端</span>
                <span class="vs-info-item__value" data-redis-field="blocked_clients"><?php echo vs_e(vs_redis_display_value(isset($snapshot['stats']['blocked_clients']) ? $snapshot['stats']['blocked_clients'] : '')); ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">监听端口</span>
                <span class="vs-info-item__value" data-redis-field="tcp_port"><?php echo vs_e(vs_redis_display_value(isset($snapshot['server']['tcp_port']) ? $snapshot['server']['tcp_port'] : '')); ?></span>
            </div>
        </div>
    </div>
</div>

<?php vs_admin_layout_end(array('redis.js')); ?>
