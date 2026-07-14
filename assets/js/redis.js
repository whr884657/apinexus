/**
 * 文件：assets/js/redis.js
 * 作用：Redis 管理页刷新、清空缓存、交互环形图与实时倒计时
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
    var chartControllers = {};
    var NS = 'http://www.w3.org/2000/svg';

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

    function polar(cx, cy, r, angleDeg) {
        var rad = ((angleDeg - 90) * Math.PI) / 180;
        return {
            x: cx + r * Math.cos(rad),
            y: cy + r * Math.sin(rad)
        };
    }

    function annularPath(cx, cy, rOut, rIn, a0, a1) {
        var sweep = a1 - a0;
        if (sweep <= 0.001) {
            return '';
        }
        if (sweep >= 359.99) {
            return [
                'M', cx, cy - rOut,
                'A', rOut, rOut, 0, 1, 1, cx, cy + rOut,
                'A', rOut, rOut, 0, 1, 1, cx, cy - rOut,
                'M', cx, cy - rIn,
                'A', rIn, rIn, 0, 1, 0, cx, cy + rIn,
                'A', rIn, rIn, 0, 1, 0, cx, cy - rIn,
                'Z'
            ].join(' ');
        }
        var large = sweep > 180 ? 1 : 0;
        var p0o = polar(cx, cy, rOut, a0);
        var p1o = polar(cx, cy, rOut, a1);
        var p1i = polar(cx, cy, rIn, a1);
        var p0i = polar(cx, cy, rIn, a0);
        return [
            'M', p0o.x, p0o.y,
            'A', rOut, rOut, 0, large, 1, p1o.x, p1o.y,
            'L', p1i.x, p1i.y,
            'A', rIn, rIn, 0, large, 0, p0i.x, p0i.y,
            'Z'
        ].join(' ');
    }

    function parseBoot() {
        var raw = panel.getAttribute('data-chart-boot') || '{}';
        try {
            return JSON.parse(raw);
        } catch (err) {
            return {};
        }
    }

    function segmentPercent(seg, total) {
        if (total <= 0) {
            return 0;
        }
        return Math.round((seg.value / total) * 1000) / 10;
    }

    function tipText(seg, total) {
        var pct = segmentPercent(seg, total);
        var line = seg.label + '：' + seg.value + (seg.unit ? ' ' + seg.unit : '');
        if (total > 0) {
            line += '（' + pct + '%）';
        }
        if (seg.extra) {
            line += ' · ' + seg.extra;
        }
        return line;
    }

    function createChartController(card, chartId, config) {
        var svg = card.querySelector('.vs-redis-donut-svg');
        var valueEl = card.querySelector('.vs-redis-donut__value');
        var hintEl = card.querySelector('.vs-redis-donut__hint');
        var tipEl = card.querySelector('[data-redis-tip]');
        if (!svg) {
            return null;
        }

        var state = {
            id: chartId,
            config: config,
            pinned: null,
            hover: null
        };

        var CX = 60;
        var CY = 60;
        var R_OUT = 48;
        var R_IN = 30;

        function totalOf(segments) {
            var sum = 0;
            (segments || []).forEach(function (s) {
                sum += Math.max(0, Number(s.value) || 0);
            });
            return sum;
        }

        function resetCenter() {
            if (valueEl) {
                valueEl.textContent = state.config.centerValue || '—';
            }
            if (hintEl) {
                hintEl.textContent = state.config.centerHint || '';
            }
        }

        function hideTip() {
            if (!tipEl) {
                return;
            }
            tipEl.textContent = '悬停或点击扇区查看明细';
            tipEl.classList.remove('is-active');
        }

        function showTip(seg) {
            if (!tipEl || !seg) {
                return;
            }
            var total = totalOf(state.config.segments);
            tipEl.textContent = tipText(seg, total);
            tipEl.classList.add('is-active');
        }

        function setActive(index) {
            svg.querySelectorAll('.vs-redis-donut-seg').forEach(function (path, i) {
                path.classList.toggle('is-active', index !== null && i === index);
                path.classList.toggle('is-dim', index !== null && i !== index);
            });
        }

        function applyFocus(index) {
            var segs = state.config.segments || [];
            if (index == null || !segs[index]) {
                setActive(null);
                resetCenter();
                if (state.pinned == null) {
                    hideTip();
                } else if (segs[state.pinned]) {
                    setActive(state.pinned);
                    showTip(segs[state.pinned]);
                    if (valueEl) {
                        valueEl.textContent = String(segs[state.pinned].value);
                    }
                    if (hintEl) {
                        hintEl.textContent = segs[state.pinned].label;
                    }
                }
                return;
            }
            var seg = segs[index];
            setActive(index);
            showTip(seg);
            if (valueEl) {
                valueEl.textContent = String(seg.value);
            }
            if (hintEl) {
                hintEl.textContent = seg.label;
            }
        }

        function draw() {
            while (svg.firstChild) {
                svg.removeChild(svg.firstChild);
            }

            var segments = state.config.segments || [];
            var total = totalOf(segments);
            var angle = 0;

            if (total <= 0) {
                var empty = document.createElementNS(NS, 'circle');
                empty.setAttribute('cx', String(CX));
                empty.setAttribute('cy', String(CY));
                empty.setAttribute('r', String((R_OUT + R_IN) / 2));
                empty.setAttribute('fill', 'none');
                empty.setAttribute('stroke', '#e5e7eb');
                empty.setAttribute('stroke-width', String(R_OUT - R_IN));
                empty.setAttribute('class', 'vs-redis-donut-empty');
                svg.appendChild(empty);
                resetCenter();
                hideTip();
                return;
            }

            segments.forEach(function (seg, index) {
                var value = Math.max(0, Number(seg.value) || 0);
                var sweep = (value / total) * 360;
                var a0 = angle;
                var a1 = angle + sweep;
                angle = a1;

                if (sweep <= 0) {
                    return;
                }

                var path = document.createElementNS(NS, 'path');
                path.setAttribute('d', annularPath(CX, CY, R_OUT, R_IN, a0, a1));
                path.setAttribute('fill', seg.color || '#94a3b8');
                path.setAttribute('class', 'vs-redis-donut-seg');
                path.setAttribute('data-seg-index', String(index));
                path.setAttribute('tabindex', '0');
                path.setAttribute('role', 'button');
                path.setAttribute('aria-label', tipText(seg, total));

                path.addEventListener('mouseenter', function () {
                    state.hover = index;
                    applyFocus(index);
                });
                path.addEventListener('mouseleave', function () {
                    state.hover = null;
                    applyFocus(state.pinned);
                });
                path.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (state.pinned === index) {
                        state.pinned = null;
                        applyFocus(state.hover);
                    } else {
                        state.pinned = index;
                        applyFocus(index);
                    }
                });
                path.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        path.click();
                    }
                });

                svg.appendChild(path);
            });

            resetCenter();
            if (state.pinned != null) {
                applyFocus(state.pinned);
            } else {
                hideTip();
                setActive(null);
            }
        }

        card.addEventListener('mouseleave', function () {
            state.hover = null;
            applyFocus(state.pinned);
        });

        return {
            update: function (nextConfig) {
                state.config = nextConfig || state.config;
                if (state.pinned != null && (!state.config.segments || !state.config.segments[state.pinned])) {
                    state.pinned = null;
                }
                draw();
            },
            draw: draw
        };
    }

    function buildChartsFromBiz(biz) {
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
        var memory = biz.cache_memory_human || '—';

        return {
            hit: {
                title: '命中分布',
                centerValue: hitTotal > 0 ? hitPercent + '%' : '—',
                centerHint: '命中率',
                segments: [
                    { id: 'hits', label: '命中', value: hits, color: '#10b981', unit: '次' },
                    { id: 'misses', label: '未命中', value: misses, color: '#d1d5db', unit: '次' }
                ]
            },
            keys: {
                title: '键类型分布',
                centerValue: keyTotal > 0 ? cacheKeyPercent + '%' : '—',
                centerHint: '数据缓存占比',
                segments: [
                    { id: 'cache', label: '数据缓存', value: cacheKeys, color: '#3b82f6', unit: '个键' },
                    { id: 'rate', label: '发信限流', value: rateKeys, color: '#fbbf24', unit: '个键' }
                ]
            },
            entries: {
                title: '缓存项状态',
                centerValue: memory,
                centerHint: '缓存占用',
                segments: [
                    {
                        id: 'cached',
                        label: '已缓存',
                        value: cachedCount,
                        color: '#8b5cf6',
                        unit: '项',
                        extra: '占用 ' + memory
                    },
                    {
                        id: 'uncached',
                        label: '未缓存',
                        value: Math.max(0, entryTotal - cachedCount),
                        color: '#e5e7eb',
                        unit: '项'
                    }
                ]
            }
        };
    }

    function initCharts() {
        var boot = parseBoot();
        panel.querySelectorAll('[data-redis-chart]').forEach(function (card) {
            var id = card.getAttribute('data-redis-chart');
            var ctrl = createChartController(card, id, boot[id] || {});
            if (ctrl) {
                chartControllers[id] = ctrl;
                ctrl.draw();
            }
        });
    }

    function renderCharts(biz) {
        var next = buildChartsFromBiz(biz);
        Object.keys(next).forEach(function (id) {
            if (chartControllers[id]) {
                chartControllers[id].update(next[id]);
            }
        });
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

    function renderSnapshot(snapshot) {
        if (!snapshot) {
            return;
        }

        var biz = snapshot.business || {};
        var server = snapshot.server || {};

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

    initCharts();

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
