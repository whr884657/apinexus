/**
 * 文件：assets/js/redis.js
 * 作用：Redis 管理页刷新与清空业务缓存
 */
(function () {
    'use strict';

    var panel = document.getElementById('redisMonitorPanel');
    var refreshBtn = document.getElementById('redisRefreshBtn');
    var clearBtn = document.getElementById('redisClearBtn');
    if (!panel) {
        return;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setField(name, value) {
        var el = panel.querySelector('[data-redis-field="' + name + '"]');
        if (el) {
            el.textContent = value;
        }
    }

    function renderEntries(entries) {
        var list = document.getElementById('redisEntryList');
        if (!list || !Array.isArray(entries)) {
            return;
        }

        var html = '';
        entries.forEach(function (entry) {
            html += '<div class="vs-redis-entry">';
            html += '<div class="vs-redis-entry__main">';
            html += '<div class="vs-redis-entry__title">' + escapeHtml(entry.label || '') + '</div>';
            html += '<div class="vs-redis-entry__meta">刷新周期 ' + escapeHtml(entry.ttl_hint || '')
                + ' · 键 ' + escapeHtml(entry.key || '') + '</div>';
            html += '</div><div class="vs-redis-entry__status">';
            if (entry.cached) {
                html += '<span class="vs-redis-badge vs-redis-badge--on">已缓存</span>';
                html += '<span class="vs-redis-entry__detail">剩余 ' + (entry.ttl_seconds != null ? entry.ttl_seconds : '—')
                    + ' 秒 · ' + escapeHtml(entry.size_human || '—') + '</span>';
            } else {
                html += '<span class="vs-redis-badge vs-redis-badge--off">未缓存</span>';
                html += '<span class="vs-redis-entry__detail">下次访问时自动建立</span>';
            }
            html += '</div></div>';
        });
        list.innerHTML = html;
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
        setField('cache_keys', String(biz.cache_keys || 0));
        setField('rate_limit_keys', String(biz.rate_limit_keys || 0));
        setField('collected_at', snapshot.collected_at || '—');
        setField('redis_version', server.redis_version || '—');
        setField('uptime_human', server.uptime_human || '—');
        setField('used_memory_human', server.used_memory_human || '—');

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
            } else {
                if (window.confirm('确定清空业务缓存？')) run();
            }
        });
    }
})();
