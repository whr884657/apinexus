/**
 * 文件：assets/js/redis.js
 * 作用：Redis 管理页刷新、清空缓存、引出线饼图与实时倒计时
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

    function labelLines(seg, total) {
        var pct = segmentPercent(seg, total);
        var main = seg.label + ' ' + seg.value + (seg.unit ? ' ' + seg.unit : '');
        var sub = total > 0 ? pct + '%' : '';
        if (seg.extra) {
            sub = sub ? (sub + ' · ' + seg.extra) : seg.extra;
        }
        return { main: main, sub: sub };
    }

    function ariaLabel(seg, total) {
        var lines = labelLines(seg, total);
        return lines.sub ? (lines.main + '（' + lines.sub + '）') : lines.main;
    }

    function createChartController(card, chartId, config) {
        var svg = card.querySelector('.vs-redis-pie-svg');
        var valueEl = card.querySelector('.vs-redis-pie__value');
        var hintEl = card.querySelector('.vs-redis-pie__hint');
        if (!svg) {
            return null;
        }

        var state = {
            id: chartId,
            config: config,
            pinned: null
        };

        var CX = 160;
        var CY = 110;
        var R_OUT = 54;
        var R_IN = 32;
        var R_CALL = 68;
        var R_ELBOW = 82;
        var LABEL_X = 118;

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
            card.classList.remove('is-focused');
        }

        function focusCenter(seg) {
            if (!seg) {
                resetCenter();
                return;
            }
            card.classList.add('is-focused');
            if (valueEl) {
                valueEl.textContent = String(seg.value) + (seg.unit ? ' ' + seg.unit : '');
            }
            if (hintEl) {
                hintEl.textContent = seg.label;
            }
        }

        function setActive(index) {
            svg.querySelectorAll('[data-seg-index]').forEach(function (node) {
                var i = parseInt(node.getAttribute('data-seg-index'), 10);
                var on = index !== null && i === index;
                var dim = index !== null && i !== index;
                node.classList.toggle('is-active', on);
                node.classList.toggle('is-dim', dim);
            });
            if (index == null) {
                resetCenter();
            } else {
                var segs = state.config.segments || [];
                focusCenter(segs[index] || null);
            }
        }

        function svgEl(name, attrs) {
            var el = document.createElementNS(NS, name);
            if (attrs) {
                Object.keys(attrs).forEach(function (k) {
                    el.setAttribute(k, attrs[k]);
                });
            }
            return el;
        }

        function draw() {
            while (svg.firstChild) {
                svg.removeChild(svg.firstChild);
            }

            var segments = state.config.segments || [];
            var total = totalOf(segments);
            var angle = 0;

            if (total <= 0) {
                svg.appendChild(svgEl('circle', {
                    cx: String(CX),
                    cy: String(CY),
                    r: String((R_OUT + R_IN) / 2),
                    fill: 'none',
                    stroke: '#e5e7eb',
                    'stroke-width': String(R_OUT - R_IN),
                    class: 'vs-redis-pie-empty'
                }));
                resetCenter();
                return;
            }

            var layout = [];
            segments.forEach(function (seg, index) {
                var value = Math.max(0, Number(seg.value) || 0);
                var sweep = (value / total) * 360;
                var a0 = angle;
                var a1 = angle + sweep;
                angle = a1;
                if (sweep <= 0) {
                    return;
                }
                layout.push({
                    seg: seg,
                    index: index,
                    a0: a0,
                    a1: a1,
                    mid: a0 + sweep / 2,
                    sweep: sweep
                });
            });

            layout.forEach(function (item) {
                var midNorm = ((item.mid % 360) + 360) % 360;
                item.side = midNorm < 180 ? 'right' : 'left';
            });
            if (layout.length === 2 && layout[0].side === layout[1].side) {
                layout[1].side = layout[0].side === 'right' ? 'left' : 'right';
            }

            // Stack labels vertically if same side to avoid overlap
            var sideBuckets = { left: [], right: [] };
            layout.forEach(function (item) {
                sideBuckets[item.side].push(item);
            });
            Object.keys(sideBuckets).forEach(function (side) {
                var list = sideBuckets[side];
                list.sort(function (a, b) { return a.mid - b.mid; });
                if (list.length <= 1) {
                    return;
                }
                var baseY = CY - ((list.length - 1) * 18);
                list.forEach(function (item, i) {
                    item.labelY = baseY + i * 36;
                });
            });

            layout.forEach(function (item) {
                var seg = item.seg;
                var group = svgEl('g', {
                    class: 'vs-redis-pie-group',
                    'data-seg-index': String(item.index)
                });

                var midRad = ((item.mid - 90) * Math.PI) / 180;
                var pullX = Math.cos(midRad) * 6;
                var pullY = Math.sin(midRad) * 6;
                group.style.setProperty('--pull-x', pullX + 'px');
                group.style.setProperty('--pull-y', pullY + 'px');

                var path = svgEl('path', {
                    d: annularPath(CX, CY, R_OUT, R_IN, item.a0, item.a1),
                    fill: seg.color || '#94a3b8',
                    class: 'vs-redis-pie-seg',
                    'data-seg-index': String(item.index),
                    tabindex: '0',
                    role: 'button',
                    'aria-label': ariaLabel(seg, total)
                });

                var start = polar(CX, CY, R_OUT + 1, item.mid);
                var elbow = polar(CX, CY, R_ELBOW, item.mid);
                var labelY = item.labelY != null ? item.labelY : elbow.y;
                var isRight = item.side === 'right';
                var endX = isRight ? CX + LABEL_X : CX - LABEL_X;
                var endY = Math.max(28, Math.min(192, labelY));

                // Soften elbow toward label side to avoid crossing the pie
                elbow = {
                    x: isRight ? Math.max(elbow.x, CX + R_CALL) : Math.min(elbow.x, CX - R_CALL),
                    y: endY
                };

                var line = svgEl('path', {
                    d: [
                        'M', start.x, start.y,
                        'L', elbow.x, elbow.y,
                        'L', endX, endY
                    ].join(' '),
                    class: 'vs-redis-pie-leader',
                    fill: 'none',
                    stroke: seg.color || '#94a3b8',
                    'data-seg-index': String(item.index)
                });

                var dot = svgEl('circle', {
                    cx: String(start.x),
                    cy: String(start.y),
                    r: '2.5',
                    fill: seg.color || '#94a3b8',
                    class: 'vs-redis-pie-anchor',
                    'data-seg-index': String(item.index)
                });

                var lines = labelLines(seg, total);
                var labelGroup = svgEl('g', {
                    class: 'vs-redis-pie-label',
                    'data-seg-index': String(item.index)
                });

                var textAnchor = isRight ? 'start' : 'end';
                var tx = isRight ? endX + 4 : endX - 4;

                var main = svgEl('text', {
                    x: String(tx),
                    y: String(endY - (lines.sub ? 4 : 0)),
                    'text-anchor': textAnchor,
                    class: 'vs-redis-pie-label__main'
                });
                main.textContent = lines.main;

                labelGroup.appendChild(main);
                if (lines.sub) {
                    var sub = svgEl('text', {
                        x: String(tx),
                        y: String(endY + 12),
                        'text-anchor': textAnchor,
                        class: 'vs-redis-pie-label__sub'
                    });
                    sub.textContent = lines.sub;
                    labelGroup.appendChild(sub);
                }

                function activate() {
                    if (state.pinned === item.index) {
                        state.pinned = null;
                        setActive(null);
                    } else {
                        state.pinned = item.index;
                        setActive(item.index);
                    }
                }

                path.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    activate();
                });
                path.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        activate();
                    }
                });
                labelGroup.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    activate();
                });

                group.appendChild(path);
                group.appendChild(line);
                group.appendChild(dot);
                group.appendChild(labelGroup);
                svg.appendChild(group);
            });

            if (state.pinned != null) {
                setActive(state.pinned);
            } else {
                setActive(null);
            }
        }

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
                    { id: 'misses', label: '未命中', value: misses, color: '#94a3b8', unit: '次' }
                ]
            },
            keys: {
                title: '键类型分布',
                centerValue: keyTotal > 0 ? cacheKeyPercent + '%' : '—',
                centerHint: '数据缓存占比',
                segments: [
                    { id: 'cache', label: '数据缓存', value: cacheKeys, color: '#3b82f6', unit: '键' },
                    { id: 'rate', label: '发信限流', value: rateKeys, color: '#f59e0b', unit: '键' }
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
                        color: '#cbd5e1',
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
