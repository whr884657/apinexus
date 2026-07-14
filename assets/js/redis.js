/**
 * 文件：assets/js/redis.js
 * 作用：Redis 管理页刷新、清空缓存、图表更新与实时倒计时
 */
(function () {
    'use strict';

    var panel = document.getElementById('redisMonitorPanel');
    var refreshBtn = document.getElementById('redisRefreshBtn');
    var clearBtn = document.getElementById('redisClearBtn');
    if (!panel) {
        return;
    }

    var tickTimer = null;
    var uptimeBase = 0;
    var uptimeSyncedAt = 0;

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setField(name, value) {
        panel.querySelectorAll('[data-redis-field="' + name + '"]').forEach(function (el) {
            el.textContent = value;
        });
    }

    function formatUptime(seconds) {
        seconds = Math.max(0, Math.floor(seconds || 0));
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;
        var parts = [];
        if (days > 0) {
            parts.push(days + ' 天');
        }
        if (days > 0 || hours > 0) {
            parts.push(hours + ' 小时');
        }
        if (days > 0 || hours > 0 || minutes > 0) {
            parts.push(minutes + ' 分');
        }
        parts.push(secs + ' 秒');
        return parts.join(' ');
    }

    function setDonut(id, percent, label) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.style.setProperty('--p1', String(percent));
        var labelEl = el.querySelector('.vs-redis-donut__label');
        if (labelEl) {
            labelEl.textContent = label;
        }
    }

    function renderEntries(entries) {
        var list = document.getElementById('redisEntryList');
        if (!list || !Array.isArray(entries)) {
            return;
        }

        var html = '';
        entries.forEach(function (entry) {
            var cached = !!entry.cached;
            var ttl = entry.ttl_seconds != null ? parseInt(entry.ttl_seconds, 10) : '';
            var size = entry.size_human || '—';
            html += '<div class="vs-redis-entry" data-cached="' + (cached ? '1' : '0') + '" data-ttl="'
                + (cached ? ttl : '') + '" data-size="' + escapeHtml(size) + '">';
            html += '<div class="vs-redis-entry__main">';
            html += '<div class="vs-redis-entry__title">' + escapeHtml(entry.label || '') + '</div>';
            html += '<div class="vs-redis-entry__meta">刷新周期 ' + escapeHtml(entry.ttl_hint || '')
                + ' · 键 ' + escapeHtml(entry.key || '') + '</div>';
            html += '</div><div class="vs-redis-entry__status">';
            if (cached) {
                html += '<span class="vs-redis-badge vs-redis-badge--on">已缓存</span>';
                html += '<span class="vs-redis-entry__detail" data-redis-ttl-text>剩余 ' + ttl + ' 秒 · ' + escapeHtml(size) + '</span>';
            } else {
                html += '<span class="vs-redis-badge vs-redis-badge--off">未缓存</span>';
                html += '<span class="vs-redis-entry__detail">下次访问时自动建立</span>';
            }
            html += '</div></div>';
        });
        list.innerHTML = html;
    }

    function tickLive() {
        var uptimeEl = panel.querySelector('[data-redis-field="uptime_human"]');
        if (uptimeEl && uptimeBase > 0 && uptimeSyncedAt > 0) {
            var elapsed = Math.floor((Date.now() - uptimeSyncedAt) / 1000);
            var current = uptimeBase + elapsed;
            uptimeEl.setAttribute('data-uptime-seconds', String(current));
            uptimeEl.textContent = formatUptime(current);
        }

        panel.querySelectorAll('.vs-redis-entry[data-cached="1"]').forEach(function (entry) {
            var ttlAttr = entry.getAttribute('data-ttl');
            if (ttlAttr === '' || ttlAttr == null) {
                return;
            }
            var ttl = parseInt(ttlAttr, 10);
            if (isNaN(ttl)) {
                return;
            }
            ttl = Math.max(0, ttl - 1);
            entry.setAttribute('data-ttl', String(ttl));
            var detail = entry.querySelector('[data-redis-ttl-text]');
            var size = entry.getAttribute('data-size') || '—';
            if (detail) {
                if (ttl <= 0) {
                    entry.setAttribute('data-cached', '0');
                    entry.setAttribute('data-ttl', '');
                    var status = entry.querySelector('.vs-redis-entry__status');
                    if (status) {
                        status.innerHTML = '<span class="vs-redis-badge vs-redis-badge--off">未缓存</span>'
                            + '<span class="vs-redis-entry__detail">下次访问时自动建立</span>';
                    }
                } else {
                    detail.textContent = '剩余 ' + ttl + ' 秒 · ' + size;
                }
            }
        });
    }

    function startTicker() {
        if (tickTimer) {
            clearInterval(tickTimer);
        }
        tickTimer = setInterval(tickLive, 1000);
    }

    function syncUptime(server) {
        var sec = server && server.uptime_seconds != null ? parseInt(server.uptime_seconds, 10) : 0;
        uptimeBase = isNaN(sec) ? 0 : sec;
        uptimeSyncedAt = Date.now();
        var uptimeEl = panel.querySelector('[data-redis-field="uptime_human"]');
        if (uptimeEl) {
            uptimeEl.setAttribute('data-uptime-seconds', String(uptimeBase));
            uptimeEl.textContent = server.uptime_human || (uptimeBase > 0 ? formatUptime(uptimeBase) : '—');
        }
    }

    function renderCharts(biz) {
        var hits = biz.app_hits || 0;
        var misses = biz.app_misses || 0;
        var hitTotal = hits + misses;
        var hitPercent = hitTotal > 0 ? Math.round((hits / hitTotal) * 1000) / 10 : 0;

        var cacheKeys = biz.cache_keys || 0;
        var rateKeys = biz.rate_limit_keys || 0;
        var keyTotal = cacheKeys + rateKeys;
        var cacheKeyPercent = keyTotal > 0 ? Math.round((cacheKeys / keyTotal) * 1000) / 10 : 0;

        var entries = biz.entries || [];
        var cachedCount = 0;
        entries.forEach(function (entry) {
            if (entry.cached) {
                cachedCount += 1;
            }
        });
        var entryTotal = entries.length;
        var entryCachedPercent = entryTotal > 0 ? Math.round((cachedCount / entryTotal) * 1000) / 10 : 0;

        setDonut('redisChartHit', hitPercent, hitTotal > 0 ? hitPercent + '%' : '—');
        setDonut('redisChartKeys', cacheKeyPercent, keyTotal > 0 ? cacheKeyPercent + '%' : '—');
        setDonut('redisChartEntries', entryCachedPercent, entryTotal > 0 ? entryCachedPercent + '%' : '—');

        setField('chart_hits', String(hits));
        setField('chart_misses', String(misses));
        setField('cache_keys', String(cacheKeys));
        setField('rate_limit_keys', String(rateKeys));
        setField('chart_cached_count', String(cachedCount));
        setField('chart_uncached_count', String(Math.max(0, entryTotal - cachedCount)));
        setField('cache_keys_dup', String(cacheKeys));
        setField('rate_limit_keys_dup', String(rateKeys));
    }

    function renderSnapshot(snapshot) {
        if (!snapshot) {
            return;
        }

        var biz = snapshot.business || {};
        var server = snapshot.server || {};

        setField('app_hits', String(biz.app_hits || 0));
        setField('app_misses', String(biz.app_misses || 0));
        setField('app_hit_rate', biz.app_hit_rate_percent == null ? '—' : biz.app_hit_rate_percent + '%');
        setField('cache_memory', biz.cache_memory_human || '—');
        setField('collected_at', snapshot.collected_at || '—');
        setField('redis_version', server.redis_version || '—');
        setField('used_memory_human', server.used_memory_human || '—');
        syncUptime(server);

        renderCharts(biz);
        renderEntries(biz.entries || []);

        var notice = document.getElementById('redisStatusNotice');
        if (!notice) {
            return;
        }
        if (!snapshot.extension_loaded) {
            notice.innerHTML = '<div class="vs-notice vs-notice--danger"><div class="vs-notice__body"><strong>未安装 Redis 扩展</strong><p>系统将以 MySQL 直连运行，无法使用 Redis 缓存。</p></div></div>';
        } else if (!snapshot.connected) {
            notice.innerHTML = '<div class="vs-notice vs-notice--warning"><div class="vs-notice__body"><strong>Redis 未连接</strong><p>'
                + escapeHtml(snapshot.error || '请启动 Redis 并检查连接配置。') + '</p></div></div>';
        } else {
            notice.innerHTML = '<div class="vs-notice vs-notice--success"><div class="vs-notice__body"><strong>Redis 已连接</strong><p>业务缓存正常工作；修改接口或分类后会自动刷新相关缓存。</p></div></div>';
        }
    }

    function postAction(action) {
        var body = new FormData();
        body.append('action', action);
        return window.VS.postForm(body).then(function (data) {
            if (data.code !== 1) {
                throw new Error(data.msg || '操作失败');
            }
            return data;
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            refreshBtn.disabled = true;
            postAction('refresh')
                .then(function (data) {
                    renderSnapshot(data.snapshot);
                    window.VS.showMessage(data.msg || '已刷新', 'success');
                })
                .catch(function (err) {
                    window.VS.showMessage(err.message || '网络异常', 'error');
                })
                .finally(function () {
                    refreshBtn.disabled = false;
                });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var run = function () {
                clearBtn.disabled = true;
                postAction('clear_cache')
                    .then(function (data) {
                        renderSnapshot(data.snapshot);
                        window.VS.showMessage(data.msg || '已清空', 'success');
                    })
                    .catch(function (err) {
                        window.VS.showMessage(err.message || '操作失败', 'error');
                    })
                    .finally(function () {
                        clearBtn.disabled = false;
                    });
            };

            if (window.VsModal && window.VsModal.confirm) {
                window.VsModal.confirm('将清空公开接口、分类等业务缓存，下次访问会重新从 MySQL 加载。确定吗？', '清空业务缓存')
                    .then(function (ok) { if (ok) run(); });
            } else if (window.confirm('确定清空业务缓存？')) {
                run();
            }
        });
    }

    var initialUptime = panel.querySelector('[data-redis-field="uptime_human"]');
    if (initialUptime) {
        var initSec = parseInt(initialUptime.getAttribute('data-uptime-seconds') || '0', 10);
        uptimeBase = isNaN(initSec) ? 0 : initSec;
        uptimeSyncedAt = Date.now();
        if (uptimeBase > 0) {
            initialUptime.textContent = formatUptime(uptimeBase);
        }
    }
    startTicker();
})();
