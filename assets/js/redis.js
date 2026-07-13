/**
 * 文件：assets/js/redis.js
 * 作用：Redis 管理页监控刷新（AJAX + 静态更新 DOM）
 */
(function () {
    'use strict';

    var panel = document.getElementById('redisMonitorPanel');
    var refreshBtn = document.getElementById('redisRefreshBtn');
    if (!panel || !refreshBtn) {
        return;
    }

    function displayValue(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        return String(value);
    }

    function setField(name, value) {
        var el = panel.querySelector('[data-redis-field="' + name + '"]');
        if (el) {
            el.textContent = value;
        }
    }

    function renderSnapshot(snapshot) {
        if (!snapshot || typeof snapshot !== 'object') {
            return;
        }

        var config = snapshot.config || {};
        setField('config_host', displayValue(config.host) + ':' + displayValue(config.port));
        setField('config_database', 'db' + (config.database != null ? config.database : '0'));
        setField('config_prefix', displayValue(config.prefix));
        setField('config_auth', config.has_password ? '已配置' : '未配置');
        setField('collected_at', displayValue(snapshot.collected_at));

        var server = snapshot.server || {};
        var memory = snapshot.memory || {};
        var stats = snapshot.stats || {};
        var keys = snapshot.keys || {};

        setField('redis_version', displayValue(server.redis_version));
        setField('uptime_human', displayValue(server.uptime_human));
        setField('used_memory_human', displayValue(memory.used_memory_human));
        setField('used_memory_peak_human', displayValue(memory.used_memory_peak_human));
        setField('keys_total', displayValue(keys.total));
        setField('keys_prefixed', displayValue(keys.prefixed) + (parseInt(keys.prefixed, 10) >= 50000 ? '+' : ''));
        setField('hit_rate_percent', stats.hit_rate_percent == null ? '—' : stats.hit_rate_percent + '%');
        setField('connected_clients', displayValue(stats.connected_clients));

        setField('used_memory_rss_human', displayValue(memory.used_memory_rss_human));
        setField('maxmemory_human', displayValue(memory.maxmemory_human));
        setField('usage_percent', memory.usage_percent == null ? '—' : memory.usage_percent + '%');
        setField('mem_fragmentation_ratio', displayValue(memory.mem_fragmentation_ratio));
        setField('instantaneous_ops_per_sec', displayValue(stats.instantaneous_ops_per_sec));
        setField('total_commands_processed', displayValue(stats.total_commands_processed));
        setField('hits_misses', (stats.keyspace_hits || 0) + ' / ' + (stats.keyspace_misses || 0));
        setField('expired_evicted', (stats.expired_keys || 0) + ' / ' + (stats.evicted_keys || 0));

        setField('redis_mode', displayValue(server.redis_mode));
        setField('role', displayValue(server.role));
        setField('os', displayValue(server.os));
        setField('arch_bits', server.arch_bits ? displayValue(server.arch_bits) + ' bit' : '—');
        setField('blocked_clients', displayValue(stats.blocked_clients));
        setField('tcp_port', displayValue(server.tcp_port));

        panel.setAttribute('data-snapshot', JSON.stringify(snapshot));
    }

    function updateStatusNotice(snapshot) {
        var wrap = document.getElementById('redisStatusNotice');
        if (!wrap) {
            return;
        }

        var html = '';
        if (!snapshot.extension_loaded) {
            html = '<div class="vs-notice vs-notice--danger"><div class="vs-notice__body"><strong>Redis 扩展未就绪</strong><p>安装向导要求 PHP redis 扩展，请确认服务器已安装并启用该扩展后重试。</p></div></div>';
        } else if (!snapshot.connected) {
            html = '<div class="vs-notice vs-notice--warning"><div class="vs-notice__body"><strong>Redis 未连接</strong><p>' + (snapshot.error || '请检查 Redis 服务是否启动，以及连接地址与认证配置。') + '</p></div></div>';
        } else {
            html = '<div class="vs-notice vs-notice--success"><div class="vs-notice__body"><strong>Redis 运行正常</strong><p>已成功连接并读取服务器 INFO。下方数据可通过「刷新监控」获取最新状态。</p></div></div>';
        }
        wrap.innerHTML = html;
    }

    refreshBtn.addEventListener('click', function () {
        refreshBtn.disabled = true;
        var body = new FormData();
        body.append('action', 'refresh');

        window.VS.postForm(body)
            .then(function (data) {
                if (data.code !== 1 || !data.snapshot) {
                    throw new Error(data.msg || '刷新失败');
                }
                renderSnapshot(data.snapshot);
                updateStatusNotice(data.snapshot);
                window.VS.showMessage(data.msg || '监控数据已刷新', 'success');
            })
            .catch(function (err) {
                window.VS.showMessage(err.message || '网络异常，请稍后重试', 'error');
            })
            .finally(function () {
                refreshBtn.disabled = false;
            });
    });
})();
