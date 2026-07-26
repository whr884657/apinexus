/**
 * 文件：assets/js/admin-dashboard.js
 * 作用：管理员控制台渲染（KPI / 平滑趋势 / TOP10 / 概览 / 最近调用 + 实时轮询）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminDashPage');
    if (!page || !window.VS) return;

    var BAR_COLORS = ['#2563eb', '#06b6d4', '#8b5cf6', '#f59e0b', '#10b981', '#ec4899', '#3b82f6', '#14b8a6', '#a855f7', '#fb923c'];
    var filter = 'all';
    var boot = {};
    var pollTimer = null;
    var softTimer = null;
    var loading = false;
    var pendingForceRefresh = false;
    var ready = false; // 首屏 snapshot 成功后再开 live，避免半截 KPI
    var initialPending = false;

    try {
        boot = JSON.parse(page.getAttribute('data-boot') || '{}') || {};
    } catch (e) {
        boot = {};
    }
    initialPending = !!boot.boot_light;

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

    /** Catmull-Rom → 三次贝塞尔，折线变平滑曲线 */
    function smoothPath(pts) {
        if (!pts.length) return '';
        if (pts.length === 1) {
            return 'M' + pts[0].x.toFixed(1) + ' ' + pts[0].y.toFixed(1);
        }
        if (pts.length === 2) {
            return 'M' + pts[0].x.toFixed(1) + ' ' + pts[0].y.toFixed(1)
                + ' L' + pts[1].x.toFixed(1) + ' ' + pts[1].y.toFixed(1);
        }
        var d = 'M' + pts[0].x.toFixed(1) + ' ' + pts[0].y.toFixed(1);
        var i;
        for (i = 0; i < pts.length - 1; i++) {
            var p0 = pts[i === 0 ? i : i - 1];
            var p1 = pts[i];
            var p2 = pts[i + 1];
            var p3 = pts[i + 2] || p2;
            var cp1x = p1.x + (p2.x - p0.x) / 6;
            var cp1y = p1.y + (p2.y - p0.y) / 6;
            var cp2x = p2.x - (p3.x - p1.x) / 6;
            var cp2y = p2.y - (p3.y - p1.y) / 6;
            d += ' C' + cp1x.toFixed(1) + ' ' + cp1y.toFixed(1)
                + ',' + cp2x.toFixed(1) + ' ' + cp2y.toFixed(1)
                + ',' + p2.x.toFixed(1) + ' ' + p2.y.toFixed(1);
        }
        return d;
    }

    function sparkSvg(values, color) {
        values = Array.isArray(values) ? values : [];
        if (!values.length) values = [0];
        var w = 120, h = 36, pad = 2;
        var max = Math.max.apply(null, values.concat([1]));
        var min = Math.min.apply(null, values);
        var span = Math.max(1, max - min);
        var pts = values.map(function (v, i) {
            var x = pad + (i * (w - pad * 2)) / Math.max(1, values.length - 1);
            var y = h - pad - ((v - min) / span) * (h - pad * 2);
            return { x: x, y: y };
        });
        var line = smoothPath(pts);
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
        var w = 560, h = 220, L = 42, R = 12, T = 16, B = 28;
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
            var d = smoothPath(pts);
            var dash = s.dashed ? ' stroke-dasharray="5 4"' : '';
            return '<path d="' + d + '" fill="none" stroke="' + s.color + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"' + dash + '/>';
        }).join('');
        var xLabels = '';
        (labels || []).forEach(function (lb, i) {
            if (n > 8 && i % 2 === 1 && i !== n - 1) return;
            xLabels += '<text x="' + xAt(i) + '" y="' + (h - 8) + '" text-anchor="middle" fill="#94a3b8" font-size="11">' + esc(lb) + '</text>';
        });
        el.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img">' + grid + paths + xLabels + '</svg>';
    }

    function showKpiLoading() {
        var grid = document.getElementById('dashKpiGrid');
        if (!grid) return;
        var i, html = '';
        for (i = 0; i < 4; i++) {
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
                label: '调用成功率',
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
            { data: type.guest || [], color: '#64748b' },
            { data: type.key || [], color: '#2563eb' },
            { data: type.points || [], color: '#f59e0b', dashed: true }
        ]);
        lineChart(document.getElementById('dashRateChart'), rate.labels || [], [
            { data: rate.success || [], color: '#16a34a' },
            { data: rate.fail || [], color: '#dc2626', dashed: true }
        ], { fixedMin: 0, fixedMax: 100 });
    }

    function renderTop(list) {
        var el = document.getElementById('dashTopBars');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">今日暂无调用排行</div>';
            return;
        }
        el.innerHTML = list.map(function (row, i) {
            var pct = Math.max(2, parseFloat(row.pct) || 0);
            var color = BAR_COLORS[i % BAR_COLORS.length];
            return '<div class="dash-bar">'
                + '<div class="dash-bar__name" title="' + esc(row.name) + '">' + esc(row.name) + '</div>'
                + '<div class="dash-bar__track"><div class="dash-bar__fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
                + '<div class="dash-bar__count">' + fmtNum(row.count) + '</div>'
                + '</div>';
        }).join('');
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

    function renderRecent(list) {
        var el = document.getElementById('dashRecentTable');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        var rows = list.filter(function (r) {
            if (filter === 'success') return r.status === 'success';
            if (filter === 'error') return r.status === 'error';
            return true;
        });
        if (!rows.length) {
            el.innerHTML = '<div class="dash-empty">' + (list.length ? '暂无匹配记录' : '暂无调用记录') + '</div>';
            return;
        }
        var html = '<table class="dash-table"><thead><tr>'
            + '<th>时间</th><th>接口名称</th><th>调用者</th><th>状态</th><th>错误码</th>'
            + '</tr></thead><tbody>';
        rows.forEach(function (r) {
            var ok = r.status === 'success';
            html += '<tr>'
                + '<td data-label="时间">' + esc(r.time) + '</td>'
                + '<td data-label="接口">' + esc(r.apiname) + '</td>'
                + '<td data-label="调用者"><span class="dash-caller"><span class="dash-caller__avatar">' + esc(r.initial || '访') + '</span>' + esc(r.caller) + '</span></td>'
                + '<td data-label="状态"><span class="dash-status ' + (ok ? 'is-ok' : 'is-fail') + '">' + (ok ? '成功' : '失败') + '</span></td>'
                + '<td data-label="错误码">' + esc(ok ? '—' : (r.code_label || r.httpcode || '—')) + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    function updateClock(data) {
        var dateEl = page.querySelector('[data-dash-date]');
        if (!dateEl || !data) return;
        dateEl.textContent = (data.server_time || '') + ' · ' + (data.weekday || '');
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
        loading = true;
        var action = forceRefresh ? 'refresh' : 'snapshot';
        post(action).then(function (res) {
            if (!res || res.code !== 1 || !res.snapshot) {
                if (forceRefresh || initialPending) {
                    window.VS.showMessage((res && res.msg) || '统计加载失败', 'error');
                }
                return;
            }
            initialPending = false;
            ready = true;
            renderAll(res.snapshot);
            if (forceRefresh) {
                window.VS.showMessage('已刷新', 'success');
            }
        }).catch(function () {
            if (forceRefresh || initialPending) {
                window.VS.showMessage('网络异常', 'error');
            }
        }).then(function () {
            loading = false;
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

    var refreshBtn = document.getElementById('dashRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            refreshBtn.disabled = true;
            fetchSnapshot(true);
        });
    }

    var filters = document.getElementById('dashRecentFilters');
    if (filters) {
        filters.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-filter]');
            if (!btn) return;
            filter = btn.getAttribute('data-filter') || 'all';
            Array.prototype.forEach.call(filters.querySelectorAll('[data-filter]'), function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            renderRecent(boot.recent);
        });
    }

    // 首屏壳 → 立即拉全量；live 5s（需 ready）；软刷新 45s（趋势/TOP）
    if (boot.boot_light) {
        showKpiLoading();
        updateClock(boot);
        fetchSnapshot(false);
    } else {
        ready = true;
        renderAll(boot);
    }
    pollTimer = setInterval(pollLive, 5000);
    // 软刷不依赖 ready：首屏失败时仍可自动重试
    softTimer = setInterval(function () { fetchSnapshot(false); }, 45000);

    window.addEventListener('beforeunload', function () {
        if (pollTimer) clearInterval(pollTimer);
        if (softTimer) clearInterval(softTimer);
    });
})();
