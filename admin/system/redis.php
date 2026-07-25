<?php
/**
 * 文件：admin/system/redis.php
 * 作用：Redis 管理（连接状态 / 缓存概览 / 业务缓存项；禁止命令台与裸键浏览）
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
        RedisCache::invalidateApiLog();
        AjaxResponse::success('业务缓存已清空', array('snapshot' => RedisService::collectMonitorSnapshot()));
    }

    AjaxResponse::error('无效操作', 400);
}

$snapshot = RedisService::collectMonitorSnapshot();
$biz = isset($snapshot['business']) ? $snapshot['business'] : array();
$server = isset($snapshot['server']) ? $snapshot['server'] : array();
$cfg = isset($snapshot['config']) ? $snapshot['config'] : array();

$hits = (int) (isset($biz['app_hits']) ? $biz['app_hits'] : 0);
$misses = (int) (isset($biz['app_misses']) ? $biz['app_misses'] : 0);
$hitTotal = $hits + $misses;
$hitPercent = $hitTotal > 0 ? round(($hits / $hitTotal) * 100, 1) : 0;

$cacheKeys = (int) (isset($biz['cache_keys']) ? $biz['cache_keys'] : 0);
$rateKeys = (int) (isset($biz['rate_limit_keys']) ? $biz['rate_limit_keys'] : 0);
$keyTotal = $cacheKeys + $rateKeys;
$cacheKeyPercent = $keyTotal > 0 ? round(($cacheKeys / $keyTotal) * 100, 1) : 0;

$entries = isset($biz['entries']) ? $biz['entries'] : array();
$entrySegments = array();
foreach ($entries as $entry) {
    $bytes = isset($entry['size_bytes']) ? (int) $entry['size_bytes'] : 0;
    $entrySegments[] = array(
        'id'    => isset($entry['id']) ? (string) $entry['id'] : '',
        'label' => isset($entry['label']) ? (string) $entry['label'] : '',
        'value' => $bytes > 0 ? $bytes : (!empty($entry['cached']) ? 1 : 0),
        'color' => isset($entry['chart_color']) ? (string) $entry['chart_color'] : '#94a3b8',
        'unit'  => $bytes > 0 ? '字节' : '项',
        'extra' => (!empty($entry['cached']) ? '已缓存' : '未缓存')
            . (isset($entry['desc']) && $entry['desc'] !== '' ? ' · ' . $entry['desc'] : ''),
    );
}
$cacheMemory = isset($biz['cache_memory_human']) ? (string) $biz['cache_memory_human'] : '—';

$chartBoot = array(
    'hit' => array(
        'title' => '读写命中',
        'centerValue' => $hitTotal > 0 ? ($hitPercent . '%') : '—',
        'centerHint' => '缓存命中率',
        'segments' => array(
            array('id' => 'hits', 'label' => '命中', 'value' => $hits, 'color' => '#10b981', 'unit' => '次'),
            array('id' => 'misses', 'label' => '未命中', 'value' => $misses, 'color' => '#d1d5db', 'unit' => '次'),
        ),
    ),
    'keys' => array(
        'title' => '用途分布',
        'centerValue' => $keyTotal > 0 ? ($cacheKeyPercent . '%') : '—',
        'centerHint' => '业务缓存占比',
        'segments' => array(
            array('id' => 'cache', 'label' => '业务数据缓存', 'value' => $cacheKeys, 'color' => '#3b82f6', 'unit' => '项', 'extra' => '接口 / 分类 / 日志等'),
            array('id' => 'rate', 'label' => '发信限流', 'value' => $rateKeys, 'color' => '#fbbf24', 'unit' => '项', 'extra' => '防刷验证码'),
        ),
    ),
    'entries' => array(
        'title' => '缓存组成',
        'centerValue' => $cacheMemory !== '' ? $cacheMemory : '—',
        'centerHint' => '业务缓存占用',
        'segments' => !empty($entrySegments) ? $entrySegments : array(
            array('id' => 'empty', 'label' => '暂无缓存项', 'value' => 1, 'color' => '#e5e7eb', 'unit' => ''),
        ),
    ),
);

$statusTone = 'offline';
$statusText = '未连接';
if (!empty($snapshot['extension_loaded']) && !empty($snapshot['connected'])) {
    $statusTone = 'online';
    $statusText = '连接正常';
} elseif (empty($snapshot['extension_loaded'])) {
    $statusTone = 'danger';
    $statusText = '扩展未安装';
}

$barMax = max(1, $hitTotal, $keyTotal);

/**
 * @param array $entry
 * @return void
 */
function vs_redis_render_entry_row(array $entry)
{
    $cached = !empty($entry['cached']);
    $search = strtolower(
        (isset($entry['label']) ? $entry['label'] : '') . ' '
        . (isset($entry['id']) ? $entry['id'] : '') . ' '
        . (isset($entry['key']) ? $entry['key'] : '')
    );
    ?>
    <tr class="redis-entry-row"
        data-redis-entry="1"
        data-search="<?php echo vs_e($search); ?>"
        data-cached="<?php echo $cached ? '1' : '0'; ?>"
        data-ttl="<?php echo $cached ? (int) $entry['ttl_seconds'] : ''; ?>"
        data-size="<?php echo vs_e(isset($entry['size_human']) ? $entry['size_human'] : '—'); ?>">
        <td>
            <div class="redis-entry-name"><?php echo vs_e($entry['label']); ?></div>
            <?php if (!empty($entry['desc'])): ?>
                <div class="redis-entry-desc"><?php echo vs_e($entry['desc']); ?></div>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($cached): ?>
                <span class="vs-redis-badge vs-redis-badge--on">已缓存</span>
            <?php else: ?>
                <span class="vs-redis-badge vs-redis-badge--off">未缓存</span>
            <?php endif; ?>
        </td>
        <td><span class="redis-mono" data-field="size"><?php echo vs_e(isset($entry['size_human']) ? $entry['size_human'] : '—'); ?></span></td>
        <td>
            <?php if ($cached): ?>
                <span class="redis-mono" data-redis-ttl-text>剩余 <?php echo (int) $entry['ttl_seconds']; ?> 秒</span>
            <?php else: ?>
                <span class="redis-muted">访问时建立</span>
            <?php endif; ?>
        </td>
        <td><span class="redis-muted">约每 <?php echo vs_e($entry['ttl_hint']); ?></span></td>
    </tr>
    <?php
}

/**
 * @param array $entry
 * @return void
 */
function vs_redis_render_entry_card(array $entry)
{
    $cached = !empty($entry['cached']);
    $search = strtolower(
        (isset($entry['label']) ? $entry['label'] : '') . ' '
        . (isset($entry['id']) ? $entry['id'] : '') . ' '
        . (isset($entry['key']) ? $entry['key'] : '')
    );
    ?>
    <div class="redis-entry-card"
         data-redis-entry="1"
         data-search="<?php echo vs_e($search); ?>"
         data-cached="<?php echo $cached ? '1' : '0'; ?>"
         data-ttl="<?php echo $cached ? (int) $entry['ttl_seconds'] : ''; ?>"
         data-size="<?php echo vs_e(isset($entry['size_human']) ? $entry['size_human'] : '—'); ?>">
        <div class="redis-entry-card__top">
            <div class="redis-entry-card__title"><?php echo vs_e($entry['label']); ?></div>
            <?php if ($cached): ?>
                <span class="vs-redis-badge vs-redis-badge--on">已缓存</span>
            <?php else: ?>
                <span class="vs-redis-badge vs-redis-badge--off">未缓存</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($entry['desc'])): ?>
            <div class="redis-entry-card__desc"><?php echo vs_e($entry['desc']); ?></div>
        <?php endif; ?>
        <div class="redis-entry-card__meta">
            <span>大小 <strong data-field="size"><?php echo vs_e(isset($entry['size_human']) ? $entry['size_human'] : '—'); ?></strong></span>
            <?php if ($cached): ?>
                <span data-redis-ttl-text>剩余 <?php echo (int) $entry['ttl_seconds']; ?> 秒</span>
            <?php else: ?>
                <span>访问时建立</span>
            <?php endif; ?>
            <span>约每 <?php echo vs_e($entry['ttl_hint']); ?></span>
        </div>
    </div>
    <?php
}

vs_admin_layout_start(
    'Redis 管理',
    'redis',
    '<button type="button" class="vs-btn vs-btn--default" id="redisClearBtn">清空业务缓存</button>'
    . '<button type="button" class="vs-btn vs-btn--primary" id="redisRefreshBtn">刷新</button>'
);
?>

<div id="adminRedisPage"
     class="admin-redis-page"
     data-chart-boot="<?php echo vs_e(json_encode($chartBoot, JSON_UNESCAPED_UNICODE)); ?>">

    <div id="redisStatusNotice" class="admin-redis-notice">
        <?php if (!$snapshot['extension_loaded']): ?>
            <?php vs_render_notice('danger', '未安装 Redis 扩展', '缓存加速不可用，请在运行环境中启用 Redis 扩展。', array('compact' => true)); ?>
        <?php elseif (!$snapshot['connected']): ?>
            <?php vs_render_notice('warning', 'Redis 未连接', vs_e($snapshot['error'] !== '' ? $snapshot['error'] : '请启动 Redis 并检查连接配置。'), array('compact' => true)); ?>
        <?php else: ?>
            <?php vs_render_notice('success', 'Redis 已连接', '业务缓存正常；修改接口或分类后会自动刷新相关缓存。', array('compact' => true)); ?>
        <?php endif; ?>
    </div>

    <section class="vs-panel redis-panel">
        <div class="redis-panel__head">
            <h2 class="redis-panel__title">连接状态</h2>
            <span class="redis-status-badge redis-status-badge--<?php echo vs_e($statusTone); ?>" data-redis-status-badge>
                <span class="redis-status-badge__dot" aria-hidden="true"></span>
                <span data-redis-field="status_text"><?php echo vs_e($statusText); ?></span>
            </span>
        </div>
        <div class="redis-info-grid">
            <div class="redis-info-cell">
                <div class="redis-info-cell__label">状态</div>
                <div class="redis-info-cell__value redis-info-cell__value--status">
                    <span class="redis-info-cell__status-dot redis-info-cell__status-dot--<?php echo vs_e($statusTone); ?>" data-redis-status-dot></span>
                    <span data-redis-field="status_text_cell"><?php echo vs_e($statusText); ?></span>
                </div>
            </div>
            <div class="redis-info-cell">
                <div class="redis-info-cell__label">版本</div>
                <div class="redis-info-cell__value" data-redis-field="redis_version"><?php echo vs_e(!empty($server['redis_version']) ? $server['redis_version'] : '—'); ?></div>
            </div>
            <div class="redis-info-cell">
                <div class="redis-info-cell__label">运行时长</div>
                <div class="redis-info-cell__value" data-redis-field="uptime_human" data-uptime-seconds="<?php echo (int) (isset($server['uptime_seconds']) ? $server['uptime_seconds'] : 0); ?>"><?php echo vs_e(!empty($server['uptime_human']) ? $server['uptime_human'] : '—'); ?></div>
            </div>
            <div class="redis-info-cell">
                <div class="redis-info-cell__label">连接数</div>
                <div class="redis-info-cell__value" data-redis-field="connected_clients"><?php echo vs_e(isset($server['connected_clients']) ? (string) (int) $server['connected_clients'] : '—'); ?></div>
            </div>
        </div>
    </section>

    <section class="vs-panel redis-panel">
        <div class="redis-panel__head">
            <h2 class="redis-panel__title">缓存概览</h2>
            <span class="redis-mono" data-redis-field="cache_memory_human"><?php echo vs_e($cacheMemory); ?></span>
        </div>
        <div class="redis-mem-layout">
            <div class="redis-overview-donut" data-redis-chart="hit">
                <div class="vs-redis-donut-wrap">
                    <svg class="vs-redis-donut-svg" viewBox="0 0 120 120" role="img" aria-label="缓存命中率"></svg>
                    <div class="vs-redis-donut__center" aria-hidden="true">
                        <span class="vs-redis-donut__value"><?php echo vs_e($hitTotal > 0 ? ($hitPercent . '%') : '—'); ?></span>
                        <span class="vs-redis-donut__hint">命中率</span>
                    </div>
                </div>
                <div class="vs-redis-chart-tip" data-redis-tip>悬停或点击扇区查看明细</div>
            </div>
            <div class="redis-mem-bars">
                <div class="redis-mem-bars__title">读写与占用</div>
                <div class="redis-bar-row">
                    <span class="redis-bar-label">命中</span>
                    <div class="redis-bar-track"><div class="redis-bar-fill redis-bar-fill--hit" style="width:<?php echo $barMax > 0 ? min(100, round($hits / $barMax * 100)) : 0; ?>%" data-redis-bar="hits"></div></div>
                    <span class="redis-bar-value" data-redis-field="app_hits"><?php echo (int) $hits; ?> 次</span>
                </div>
                <div class="redis-bar-row">
                    <span class="redis-bar-label">未命中</span>
                    <div class="redis-bar-track"><div class="redis-bar-fill redis-bar-fill--miss" style="width:<?php echo $barMax > 0 ? min(100, round($misses / $barMax * 100)) : 0; ?>%" data-redis-bar="misses"></div></div>
                    <span class="redis-bar-value" data-redis-field="app_misses"><?php echo (int) $misses; ?> 次</span>
                </div>
                <div class="redis-bar-row">
                    <span class="redis-bar-label">业务缓存</span>
                    <div class="redis-bar-track"><div class="redis-bar-fill redis-bar-fill--cache" style="width:<?php echo $barMax > 0 ? min(100, round($cacheKeys / $barMax * 100)) : 0; ?>%" data-redis-bar="cache_keys"></div></div>
                    <span class="redis-bar-value" data-redis-field="cache_keys"><?php echo (int) $cacheKeys; ?> 项</span>
                </div>
                <div class="redis-bar-row">
                    <span class="redis-bar-label">发信限流</span>
                    <div class="redis-bar-track"><div class="redis-bar-fill redis-bar-fill--rate" style="width:<?php echo $barMax > 0 ? min(100, round($rateKeys / $barMax * 100)) : 0; ?>%" data-redis-bar="rate_keys"></div></div>
                    <span class="redis-bar-value" data-redis-field="rate_keys"><?php echo (int) $rateKeys; ?> 项</span>
                </div>
                <div class="redis-bar-row">
                    <span class="redis-bar-label">进程内存</span>
                    <div class="redis-bar-track"><div class="redis-bar-fill redis-bar-fill--mem" style="width:62%"></div></div>
                    <span class="redis-bar-value" data-redis-field="used_memory_human"><?php echo vs_e(!empty($server['used_memory_human']) ? $server['used_memory_human'] : '—'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="vs-redis-charts" id="redisCharts" role="group" aria-label="Redis 监控图表">
        <?php foreach (array('keys' => $chartBoot['keys'], 'entries' => $chartBoot['entries']) as $chartId => $chart): ?>
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
    </section>

    <section class="vs-panel redis-panel">
        <div class="redis-panel__head redis-panel__head--stack">
            <div class="redis-panel__head-main">
                <h2 class="redis-panel__title">业务缓存</h2>
                <span class="redis-mono">共 <span data-redis-field="entry_count"><?php echo (int) count($entries); ?></span> 项</span>
            </div>
            <div class="vs-search-bar redis-entry-search">
                <div class="vs-search-bar__input-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" class="vs-input vs-search-bar__input" id="redisEntrySearch" placeholder="搜索缓存项…" autocomplete="off">
                </div>
            </div>
        </div>

        <div class="redis-entry-table-wrap" id="redisEntryTableWrap">
            <div class="vs-table-responsive">
                <table class="vs-table">
                    <thead>
                        <tr>
                            <th>缓存项</th>
                            <th>状态</th>
                            <th>大小</th>
                            <th>剩余</th>
                            <th>刷新周期</th>
                        </tr>
                    </thead>
                    <tbody id="redisEntryBody">
                        <?php foreach ($entries as $entry): ?>
                            <?php vs_redis_render_entry_row($entry); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="redis-entry-cards" id="redisEntryCards">
            <?php foreach ($entries as $entry): ?>
                <?php vs_redis_render_entry_card($entry); ?>
            <?php endforeach; ?>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="redisEntryEmpty"<?php echo count($entries) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无业务缓存项</h3>
                <p class="vs-api-list-empty__desc">访问前台或日志相关页面后，这里会显示缓存状态。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="redisEntrySearchEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">换个关键词试试，或清空搜索。</p>
            </div>
        </div>
    </section>

    <section class="vs-panel redis-panel">
        <div class="redis-panel__head">
            <h2 class="redis-panel__title">连接信息</h2>
            <span class="redis-mono" data-redis-field="collected_at"><?php echo vs_e($snapshot['collected_at']); ?></span>
        </div>
        <div class="vs-info-grid vs-redis-info-grid">
            <div class="vs-info-item">
                <span class="vs-info-item__label">连接地址</span>
                <span class="vs-info-item__value"><?php
                    echo vs_e(
                        (isset($cfg['host']) ? $cfg['host'] : '127.0.0.1')
                        . ':' . (isset($cfg['port']) ? $cfg['port'] : '6379')
                        . ' / db' . (isset($cfg['database']) ? $cfg['database'] : '0')
                    );
                ?></span>
            </div>
            <div class="vs-info-item">
                <span class="vs-info-item__label">内存峰值</span>
                <span class="vs-info-item__value" data-redis-field="used_memory_peak_human"><?php echo vs_e(!empty($server['used_memory_peak_human']) ? $server['used_memory_peak_human'] : '—'); ?></span>
            </div>
        </div>
    </section>
</div>

<?php vs_admin_layout_end(array('redis.js')); ?>
