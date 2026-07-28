/**
 * 文件：assets/js/admin-dashboard.js
 * 作用：管理员控制台渲染（KPI / 平滑趋势 / TOP10 / 概览 / 最近调用 + 实时轮询）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminDashPage');
    if (!page || !window.VS) return;

    var BAR_COLORS = ['#2563eb', '#06b6d4', '#8b5cf6', '#f59e0b', '#10b981', '#ec4899', '#3b82f6', '#14b8a6', '#a855f7', '#fb923c'];
    var boot = {};
    var pollTimer = null;
    var softTimer = null;
    var clockTimer = null;
    var loading = false;
    var softLoading = false;
    var pendingForceRefresh = false;
    var ready = false;
    var initialPending = false;
    var clockBaseMs = 0;
    var clockOffset = 0;
    var liveIntervalMs = 5000;
    var topMarqueeRaf = null;
    var topMarqueeY = 0;
    var topMarqueePaused = false;

    try {
        boot = JSON.parse(page.getAttribute('data-boot') || '{}') || {};
    } catch (e) {
        boot = {};
    }
    initialPending = !!boot.boot_light;
    liveIntervalMs = readLiveIntervalMs(boot.live_interval || page.getAttribute('data-live-interval'));

    function readLiveIntervalMs(v) {
        var n = parseInt(v, 10);
        if (isNaN(n) || n < 1) n = 5;
        if (n > 5) n = 5;
        return n * 1000;
    }

    function weekdayLabel(ts) {
        var map = ['日', '一', '二', '三', '四', '五', '六'];
        return '周' + map[(new Date(ts)).getDay()];
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function formatClock(ts) {
        var d = new Date(ts);
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate())
            + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds())
            + ' · ' + weekdayLabel(ts);
    }

    function syncClock(serverTime) {
        var parsed = Date.parse(String(serverTime || '').replace(/-/g, '/'));
        if (isNaN(parsed)) {
            parsed = Date.now();
        }
        clockBaseMs = parsed;
        clockOffset = Date.now();
        tickClock();
    }

    function tickClock() {
        var dateEl = page.querySelector('[data-dash-date]');
        if (!dateEl || !clockBaseMs) return;
        var now = clockBaseMs + (Date.now() - clockOffset);
        dateEl.textContent = formatClock(now);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtNum(n) {
        n = parseInt(n, 10) || 0;
        return n.toLocaleString('zh-CN');
    }

    function deltaHtml(v, suffix) {
        if (v == null || v === '') {
            return '<span class="dash-kpi__delta">—</span>';
        }
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        var up = n >= 0;
        var cls = up ? 'dash-kpi__delta--up' : 'dash-kpi__delta--down';
        var sign = up ? '+' : '';
        return '<span class="dash-kpi__delta ' + cls + '">' + sign + esc(String(n)) + esc(suffix || '') + '</span>';
    }

    /**
     * Catmull-Rom → 三次贝塞尔；可选 y 钳制，避免平滑曲线穿出绘图区（含 X 轴下方）
     * @param {{x:number,y:number}[]} pts
     * @param {number|null} yMin
     * @param {number|null} yMax
     */
    function smoothPath(pts, yMin, yMax) {
        function clampY(y) {
            if (yMin == null || yMax == null) return y;
            return Math.max(yMin, Math.min(yMax, y));
        }
        if (!pts.length) return '';
        if (pts.length === 1) {
            return 'M' + pts[0].x.toFixed(1) + ' ' + clampY(pts[0].y).toFixed(1);
        }
        if (pts.length === 2) {
            return 'M' + pts[0].x.toFixed(1) + ' ' + clampY(pts[0].y).toFixed(1)
                + ' L' + pts[1].x.toFixed(1) + ' ' + clampY(pts[1].y).toFixed(1);
        }
        var d = 'M' + pts[0].x.toFixed(1) + ' ' + clampY(pts[0].y).toFixed(1);
        var i;
        for (i = 0; i < pts.length - 1; i++) {
            var p0 = pts[i === 0 ? i : i - 1];
            var p1 = pts[i];
            var p2 = pts[i + 1];
            var p3 = pts[i + 2] || p2;
            var cp1x = p1.x + (p2.x - p0.x) / 6;
            var cp1y = clampY(p1.y + (p2.y - p0.y) / 6);
            var cp2x = p2.x - (p3.x - p1.x) / 6;
            var cp2y = clampY(p2.y - (p3.y - p1.y) / 6);
            d += ' C' + cp1x.toFixed(1) + ' ' + cp1y.toFixed(1)
                + ',' + cp2x.toFixed(1) + ' ' + cp2y.toFixed(1)
                + ',' + p2.x.toFixed(1) + ' ' + clampY(p2.y).toFixed(1);
        }
        return d;
    }

    function sparkSvg(values, color) {
        values = Array.isArray(values) ? values : [];
        if (!values.length) values = [0];
        var w = 120, h = 36, pad = 2;
        var max = Math.max.apply(null, values.concat([1]));
        var min = Math.min.apply(null, values.concat([0]));
        if (min > 0) min = 0;
        var span = Math.max(1, max - min);
        var yLo = pad;
        var yHi = h - pad;
        var pts = values.map(function (v, i) {
            var x = pad + (i * (w - pad * 2)) / Math.max(1, values.length - 1);
            var y = h - pad - ((v - min) / span) * (h - pad * 2);
            return { x: x, y: y };
        });
        var line = smoothPath(pts, yLo, yHi);
        var area = line + ' L' + (w - pad).toFixed(1) + ' ' + (h - pad)
            + ' L' + pad + ' ' + (h - pad) + ' Z';
        return '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">'
            + '<path d="' + area + '" fill="' + color + '" opacity="0.12"></path>'
            + '<path d="' + line + '" fill="none" stroke="' + color + '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>'
            + '</svg>';
    }

    function lineChart(el, labels, seriesList, opts) {
        if (!el) return;
        opts = opts || {};
        var w = 560, h = 200, L = 36, R = 10, T = 14, B = 26;
        var plotTop = T;
        var plotBottom = h - B;
        var all = [];
        seriesList.forEach(function (s) { all = all.concat(s.data || []); });
        if (!all.length) {
            el.innerHTML = '<div class="dash-empty">暂无趋势数据</div>';
            return;
        }
        var max = Math.max.apply(null, all.concat([1]));
        var min = opts.min != null ? opts.min : Math.min.apply(null, all.concat([0]));
        if (opts.fixedMin != null) min = opts.fixedMin;
        if (opts.fixedMax != null) max = opts.fixedMax;
        // 调用量 / 比率不得为负：保证 0 落在 X 轴，避免曲线视觉下穿
        if (opts.allowNegative !== true && min < 0) min = 0;
        var span = Math.max(0.0001, max - min);
        var n = Math.max(1, (labels || []).length);
        function xAt(i) { return L + (i * (w - L - R)) / Math.max(1, n - 1); }
        function yAt(v) { return T + (1 - (v - min) / span) * (h - T - B); }
        var grid = '';
        for (var g = 0; g < 4; g++) {
            var gy = T + g * (h - T - B) / 3;
            grid += '<line x1="' + L + '" y1="' + gy + '" x2="' + (w - R) + '" y2="' + gy + '" stroke="#e5e7eb" stroke-width="1"/>';
        }
        var paths = seriesList.map(function (s) {
            var pts = (s.data || []).map(function (v, i) {
                return { x: xAt(i), y: yAt(v) };
            });
            var d = smoothPath(pts, plotTop, plotBottom);
            var dash = s.dashed ? ' stroke-dasharray="5 4"' : '';
            return '<path d="' + d + '" fill="none" stroke="' + s.color + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"' + dash + '/>';
        }).join('');
        var xLabels = '';
        (labels || []).forEach(function (lb, i) {
            if (n > 8 && i % 2 === 1 && i !== n - 1) return;
            xLabels += '<text x="' + xAt(i) + '" y="' + (h - 8) + '" text-anchor="middle" fill="#94a3b8" font-size="11">' + esc(lb) + '</text>';
        });
        var fmtTip = opts.formatValue || function (v) {
            if (typeof v === 'number') {
                return (Math.round(v * 100) / 100).toLocaleString('zh-CN');
            }
            return String(v);
        };
        el.innerHTML = '<div class="dash-chart-canvas">'
            + '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img">'
            + grid + paths + xLabels
            + '<line class="dash-chart-guide" x1="0" y1="' + plotTop + '" x2="0" y2="' + plotBottom + '" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" opacity="0"></line>'
            + '<g class="dash-chart-dots"></g>'
            + '</svg>'
            + '<div class="dash-chart-tip" hidden></div>'
            + '</div>';

        var svg = el.querySelector('svg');
        var tip = el.querySelector('.dash-chart-tip');
        var guide = el.querySelector('.dash-chart-guide');
        var dotsG = el.querySelector('.dash-chart-dots');
        if (!svg || !tip) return;

        function nearestIndex(px) {
            var best = 0;
            var bestDist = Infinity;
            var i;
            for (i = 0; i < n; i++) {
                var d = Math.abs(xAt(i) - px);
                if (d < bestDist) {
                    bestDist = d;
                    best = i;
                }
            }
            return best;
        }

        function hideTip() {
            tip.hidden = true;
            if (guide) guide.setAttribute('opacity', '0');
            if (dotsG) dotsG.innerHTML = '';
        }

        function showAt(idx, clientX, clientY) {
            var xi = xAt(idx);
            if (guide) {
                guide.setAttribute('x1', xi.toFixed(1));
                guide.setAttribute('x2', xi.toFixed(1));
                guide.setAttribute('opacity', '0.7');
            }
            var dotsHtml = '';
            var rows = '';
            seriesList.forEach(function (s) {
                var raw = (s.data || [])[idx];
                if (raw == null) raw = 0;
                var yi = Math.max(plotTop, Math.min(plotBottom, yAt(raw)));
                dotsHtml += '<circle cx="' + xi.toFixed(1) + '" cy="' + yi.toFixed(1) + '" r="3.5" fill="#fff" stroke="' + s.color + '" stroke-width="2"/>';
                rows += '<div class="dash-chart-tip__row">'
                    + '<i style="background:' + s.color + '"></i>'
                    + '<span>' + esc(s.name || '') + '</span>'
                    + '<b>' + esc(fmtTip(raw) + (opts.unit || '')) + '</b>'
                    + '</div>';
            });
            if (dotsG) dotsG.innerHTML = dotsHtml;
            tip.innerHTML = '<div class="dash-chart-tip__title">' + esc((labels || [])[idx] || '') + '</div>' + rows;
            tip.hidden = false;
            var canvas = el.querySelector('.dash-chart-canvas');
            var box = canvas ? canvas.getBoundingClientRect() : el.getBoundingClientRect();
            var left = clientX - box.left + 12;
            var top = clientY - box.top - 8;
            if (left + tip.offsetWidth > box.width - 4) {
                left = clientX - box.left - tip.offsetWidth - 12;
            }
            if (top < 4) top = 4;
            if (top + tip.offsetHeight > box.height - 4) {
                top = Math.max(4, box.height - tip.offsetHeight - 4);
            }
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
        }

        svg.addEventListener('mousemove', function (e) {
            var rect = svg.getBoundingClientRect();
            if (!rect.width) return;
            var px = ((e.clientX - rect.left) / rect.width) * w;
            if (px < L - 8 || px > w - R + 8) {
                hideTip();
                return;
            }
            showAt(nearestIndex(px), e.clientX, e.clientY);
        });
        svg.addEventListener('mouseleave', hideTip);
    }

    function showKpiLoading() {
        var grid = document.getElementById('dashKpiGrid');
        if (!grid) return;
        var i, html = '';
        for (i = 0; i < 5; i++) {
            html += '<article class="dash-kpi dash-kpi--loading" aria-busy="true">'
                + '<div class="dash-kpi__skeleton dash-kpi__skeleton--label"></div>'
                + '<div class="dash-kpi__skeleton dash-kpi__skeleton--value"></div>'
                + '<div class="dash-kpi__skeleton dash-kpi__skeleton--chart"></div>'
                + '</article>';
        }
        grid.innerHTML = html;
    }

    function renderKpi(kpi) {
        kpi = kpi || {};
        var grid = document.getElementById('dashKpiGrid');
        if (!grid) return;
        if (!kpi || (kpi.api_total == null && kpi.today_calls == null && kpi.success_rate == null)) {
            showKpiLoading();
            return;
        }
        var cards = [
            {
                label: '接口总数',
                value: fmtNum(kpi.api_total),
                delta: deltaHtml(kpi.api_delta, ' 较上周'),
                spark: sparkSvg(kpi.api_spark, '#2563eb'),
                meta: ''
            },
            {
                label: '注册用户',
                value: fmtNum(kpi.user_total),
                delta: deltaHtml(kpi.user_delta, ' 今日新增'),
                spark: sparkSvg(kpi.user_spark, '#06b6d4'),
                meta: ''
            },
            {
                label: '今日调用',
                value: fmtNum(kpi.today_calls),
                delta: deltaHtml(kpi.today_delta, '% 较昨日'),
                spark: sparkSvg(kpi.today_spark, '#8b5cf6'),
                meta: ''
            },
            {
                label: '累计调用',
                value: fmtNum(kpi.total_calls),
                delta: deltaHtml(kpi.total_delta, '% 较上周'),
                spark: sparkSvg(kpi.today_spark, '#0d9488'),
                meta: ''
            },
            {
                label: '今日调用成功率',
                value: (parseFloat(kpi.success_rate) || 0).toFixed(2) + '%',
                delta: deltaHtml(kpi.success_delta, '% 较昨日'),
                spark: '',
                meta: '<span class="dash-kpi__meta-ok">成功 <strong>' + fmtNum(kpi.success_count) + '</strong></span>'
                    + '<span class="dash-kpi__meta-fail">失败 <strong>' + fmtNum(kpi.fail_count) + '</strong></span>'
            }
        ];
        grid.innerHTML = cards.map(function (c) {
            return '<article class="dash-kpi">'
                + '<div class="dash-kpi__top"><span class="dash-kpi__label">' + esc(c.label) + '</span>' + c.delta + '</div>'
                + '<div class="dash-kpi__value">' + esc(c.value) + '</div>'
                + (c.spark ? '<div class="dash-kpi__chart">' + c.spark + '</div>' : '')
                + (c.meta ? '<div class="dash-kpi__meta">' + c.meta + '</div>' : '')
                + '</article>';
        }).join('');
    }

    function renderTrends(data) {
        var type = data.type_trend || {};
        var rate = data.rate_trend || {};
        var typeLegend = document.getElementById('dashTypeLegend');
        var rateLegend = document.getElementById('dashRateLegend');
        if (typeLegend) {
            typeLegend.innerHTML = [
                ['游客', 'guest'], ['密钥', 'key'], ['积分', 'points']
            ].map(function (x) {
                return '<span class="dash-chart-legend__item"><i class="dash-chart-legend__line dash-chart-legend__line--' + x[1] + '"></i>' + x[0] + '</span>';
            }).join('');
        }
        if (rateLegend) {
            rateLegend.innerHTML = [
                ['成功率', 'ok'], ['失败率', 'fail']
            ].map(function (x) {
                return '<span class="dash-chart-legend__item"><i class="dash-chart-legend__line dash-chart-legend__line--' + x[1] + '"></i>' + x[0] + '</span>';
            }).join('');
        }
        lineChart(document.getElementById('dashTypeChart'), type.labels || [], [
            { name: '游客', data: type.guest || [], color: '#64748b' },
            { name: '密钥', data: type.key || [], color: '#2563eb' },
            { name: '积分', data: type.points || [], color: '#f59e0b', dashed: true }
        ], { fixedMin: 0 });
        lineChart(document.getElementById('dashRateChart'), rate.labels || [], [
            { name: '成功率', data: rate.success || [], color: '#16a34a' },
            { name: '失败率', data: rate.fail || [], color: '#dc2626', dashed: true }
        ], {
            fixedMin: 0,
            fixedMax: 100,
            unit: '%',
            formatValue: function (v) {
                var n = parseFloat(v);
                if (isNaN(n)) n = 0;
                return (Math.round(n * 100) / 100).toFixed(2);
            }
        });
    }

    function stopTopMarquee() {
        if (topMarqueeRaf) {
            cancelAnimationFrame(topMarqueeRaf);
            topMarqueeRaf = null;
        }
        topMarqueeY = 0;
        topMarqueePaused = false;
    }

    function startTopMarquee(el, halfHeight) {
        stopTopMarquee();
        if (!el || halfHeight <= 0) return;
        var speed = 0.32;
        function tick() {
            if (!topMarqueePaused) {
                topMarqueeY += speed;
                if (topMarqueeY >= halfHeight) {
                    topMarqueeY -= halfHeight;
                }
                el.style.transform = 'translateY(' + (-topMarqueeY) + 'px)';
            }
            topMarqueeRaf = requestAnimationFrame(tick);
        }
        topMarqueeRaf = requestAnimationFrame(tick);
    }

    function renderTop(list) {
        var el = document.getElementById('dashTopBars');
        var viewport = document.getElementById('dashTopViewport');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        stopTopMarquee();
        el.classList.remove('is-marquee');
        el.style.transform = '';
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">今日暂无调用排行</div>';
            return;
        }
        var rowsHtml = list.map(function (row, i) {
            var pct = Math.max(2, parseFloat(row.pct) || 0);
            var color = BAR_COLORS[i % BAR_COLORS.length];
            var rank = i + 1;
            var rankCls = rank <= 3 ? (' is-' + rank) : '';
            return '<div class="dash-bar">'
                + '<span class="dash-bar__rank' + rankCls + '">' + rank + '</span>'
                + '<div class="dash-bar__name" title="' + esc(row.name) + '">' + esc(row.name) + '</div>'
                + '<div class="dash-bar__track"><div class="dash-bar__fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
                + '<div class="dash-bar__count">' + fmtNum(row.count) + '</div>'
                + '</div>';
        }).join('');
        el.innerHTML = rowsHtml;
        // 超过可视区约 5 条才无限循环滚动；少则静止
        if (list.length > 5 && viewport) {
            el.innerHTML = rowsHtml + rowsHtml;
            el.classList.add('is-marquee');
            requestAnimationFrame(function () {
                var half = el.scrollHeight / 2;
                if (half > 8) startTopMarquee(el, half);
            });
        }
    }

    function renderSys(list) {
        var el = document.getElementById('dashSys');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">暂无概览数据</div>';
            return;
        }
        el.innerHTML = list.map(function (row) {
            return '<div class="dash-sys-item">'
                + '<span class="dash-sys-dot is-' + esc(row.tone || 'neutral') + '"></span>'
                + '<span class="dash-sys-name">' + esc(row.name) + '</span>'
                + '<span class="dash-sys-num">' + esc(row.value) + '</span>'
                + '</div>';
        }).join('');
    }

    function httpCodeClass(code) {
        var n = parseInt(code, 10) || 0;
        if (n >= 200 && n < 300) return 'is-ok';
        if (n >= 400) return 'is-fail';
        if (n > 0) return 'is-warn';
        return '';
    }

    function renderRecent(list) {
        var el = document.getElementById('dashRecentTable');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">暂无调用记录</div>';
            return;
        }
        var html = '<div class="dash-recent-list" role="table" aria-label="最近调用记录">'
            + '<div class="dash-recent-row dash-recent-row--head" role="row">'
            + '<span class="dash-recent-id">ID</span>'
            + '<span class="dash-recent-name">接口</span>'
            + '<span class="dash-recent-ip">IP</span>'
            + '<span class="dash-recent-code">状态</span>'
            + '<span class="dash-recent-time">时间</span>'
            + '</div>';
        list.forEach(function (r) {
            var code = parseInt(r.httpcode, 10) || 0;
            var codeText = code > 0 ? String(code) : '—';
            html += '<div class="dash-recent-row" role="row">'
                + '<span class="dash-recent-id">' + esc(r.id) + '</span>'
                + '<span class="dash-recent-name" title="' + esc(r.apiname) + '">' + esc(r.apiname || '—') + '</span>'
                + '<span class="dash-recent-ip" title="' + esc(r.ip) + '">' + esc(r.ip || '—') + '</span>'
                + '<span class="dash-recent-code ' + httpCodeClass(code) + '">' + esc(codeText) + '</span>'
                + '<span class="dash-recent-time">' + esc(r.time || '—') + '</span>'
                + '</div>';
        });
        html += '</div>';
        el.innerHTML = html;
    }

    function updateClock(data) {
        if (!data) return;
        if (data.server_time) {
            syncClock(data.server_time);
        }
        if (data.live_interval != null) {
            var next = readLiveIntervalMs(data.live_interval);
            if (next !== liveIntervalMs) {
                liveIntervalMs = next;
                restartLivePoll();
                restartSoftPoll();
            }
        }
    }

    function renderAll(data) {
        boot = data || boot;
        updateClock(boot);
        renderKpi(boot.kpi);
        renderTrends(boot);
        renderTop(boot.top_apis);
        renderSys(boot.sys_overview);
        renderRecent(boot.recent);
    }

    function mergeLive(live) {
        if (!live) return;
        updateClock(live);
        if (live.kpi) {
            var merged = {};
            var prev = boot.kpi || {};
            var k;
            for (k in prev) {
                if (Object.prototype.hasOwnProperty.call(prev, k)) merged[k] = prev[k];
            }
            for (k in live.kpi) {
                if (Object.prototype.hasOwnProperty.call(live.kpi, k)) merged[k] = live.kpi[k];
            }
            boot.kpi = merged;
            renderKpi(boot.kpi);
        }
        if (live.recent) {
            boot.recent = live.recent;
            renderRecent(boot.recent);
        }
        if (live.sys_overview) {
            boot.sys_overview = live.sys_overview;
            renderSys(boot.sys_overview);
        }
        if (live.top_apis) {
            boot.top_apis = live.top_apis;
            renderTop(boot.top_apis);
        }
    }

    function post(action) {
        var fd = new FormData();
        fd.append('action', action);
        return window.VS.postForm(fd);
    }

    function fetchSnapshot(forceRefresh) {
        // 加载中再点「刷新」：排队强制刷新，避免按钮永久 disabled / 请求被吞
        if (loading) {
            if (forceRefresh) {
                pendingForceRefresh = true;
            }
            return;
        }
        if (!forceRefresh && softLoading) {
            return;
        }
        if (forceRefresh) {
            loading = true;
        } else {
            softLoading = true;
        }
        var action = forceRefresh ? 'refresh' : 'snapshot';
        post(action).then(function (res) {
            if (!res || res.code !== 1 || !res.snapshot) {
                if (forceRefresh || initialPending) {
                    window.VS.showMessage((res && res.msg) || '统计加载失败', 'error');
                }
                return;
            }
            initialPending = false;
            var wasReady = ready;
            ready = true;
            // 软刷：保留 live 已刷新的最近调用 / 系统概览 / KPI / TOP，避免被 snapshot 慢路径覆盖
            var keepRecent = (!forceRefresh && wasReady && Array.isArray(boot.recent) && boot.recent.length)
                ? boot.recent
                : null;
            var keepSys = (!forceRefresh && wasReady && boot.sys_overview)
                ? boot.sys_overview
                : null;
            var keepKpi = (!forceRefresh && wasReady && boot.kpi) ? boot.kpi : null;
            var keepTop = (!forceRefresh && wasReady && Array.isArray(boot.top_apis) && boot.top_apis.length)
                ? boot.top_apis
                : null;
            renderAll(res.snapshot);
            if (keepRecent) {
                boot.recent = keepRecent;
                renderRecent(keepRecent);
            }
            if (keepSys) {
                boot.sys_overview = keepSys;
                renderSys(keepSys);
            }
            if (keepKpi) {
                boot.kpi = keepKpi;
                renderKpi(keepKpi);
            }
            if (keepTop) {
                boot.top_apis = keepTop;
                renderTop(keepTop);
            }
            if (forceRefresh) {
                window.VS.showMessage('已刷新', 'success');
            }
        }).catch(function () {
            if (forceRefresh || initialPending) {
                window.VS.showMessage('网络异常', 'error');
            }
        }).then(function () {
            loading = false;
            softLoading = false;
            var btn = document.getElementById('dashRefreshBtn');
            if (btn) btn.disabled = false;
            if (pendingForceRefresh) {
                pendingForceRefresh = false;
                fetchSnapshot(true);
            }
        });
    }

    function pollLive() {
        if (!ready || loading) return;
        post('live').then(function (res) {
            if (!res || res.code !== 1 || !res.live) return;
            mergeLive(res.live);
        }).catch(function () { /* 静默 */ });
    }

    function restartLivePoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        pollTimer = setInterval(pollLive, liveIntervalMs);
    }

    function restartSoftPoll() {
        if (softTimer) {
            clearInterval(softTimer);
            softTimer = null;
        }
        var softMs = Math.max(liveIntervalMs * 6, 10000);
        softTimer = setInterval(function () { fetchSnapshot(false); }, softMs);
    }

    var refreshBtn = document.getElementById('dashRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            refreshBtn.disabled = true;
            fetchSnapshot(true);
        });
    }

    // 首屏壳 → 立即拉全量；live 可配置；软刷新与间隔对齐；时钟每秒走
    if (boot.boot_light) {
        showKpiLoading();
        if (boot.server_time) syncClock(boot.server_time);
        fetchSnapshot(false);
    } else {
        ready = true;
        renderAll(boot);
    }
    clockTimer = setInterval(tickClock, 1000);
    restartLivePoll();
    restartSoftPoll();

    window.addEventListener('beforeunload', function () {
        if (pollTimer) clearInterval(pollTimer);
        if (softTimer) clearInterval(softTimer);
        if (clockTimer) clearInterval(clockTimer);
    });
})();
