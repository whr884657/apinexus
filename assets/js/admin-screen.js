/**
 * 文件：assets/js/admin-screen.js
 * 作用：数据大屏渲染（KPI / 时序 / TOP / 飞线地图 / 实时日志 / 轻量轮询）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminScreenPage');
    if (!page || !window.VS) return;

    var BAR_COLORS = ['#2563eb', '#06b6d4', '#8b5cf6', '#f59e0b', '#10b981', '#ec4899', '#3b82f6', '#14b8a6', '#a855f7', '#fb923c'];
    var boot = {};
    var mapMode = 'china';
    var theme = page.classList.contains('vs-datascreen--dark') ? 'dark' : 'light';
    var pollTimer = null;
    var softTimer = null;

    try {
        boot = JSON.parse(page.getAttribute('data-boot') || '{}') || {};
    } catch (e) {
        boot = {};
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

    function deltaText(v, suffix) {
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        var sign = n >= 0 ? '+' : '';
        return sign + n + (suffix || '');
    }

    function deltaClass(v) {
        return (parseFloat(v) || 0) >= 0 ? 'ds-stat-strip__delta--up' : 'ds-stat-strip__delta--down';
    }

    function post(action) {
        var fd = new FormData();
        fd.append('action', action);
        return window.VS.postForm(fd);
    }

    function cityIndex(cities) {
        var map = {};
        (cities || []).forEach(function (c) {
            if (c && c.name) map[c.name] = c;
        });
        return map;
    }

    function curvePath(a, b) {
        var mx = (a.x + b.x) / 2;
        var my = Math.min(a.y, b.y) - Math.max(18, Math.abs(a.x - b.x) * 0.22);
        return 'M' + a.x + ',' + a.y + ' Q' + mx + ',' + my + ' ' + b.x + ',' + b.y;
    }

    function chinaOutline() {
        return 'M95,55 L145,42 L195,38 L245,48 L285,55 L320,78 L345,110 L355,150 '
            + 'L348,190 L325,225 L290,250 L250,265 L210,268 L170,255 L135,230 '
            + 'L110,195 L98,155 L92,115 L95,80 Z';
    }

    function worldOutline() {
        return 'M40,90 L90,70 L140,75 L190,62 L240,78 L290,70 L340,88 '
            + 'L365,120 L350,165 L300,185 L250,190 L200,182 L145,175 L95,160 L55,130 Z';
    }

    function ensureSvg(host) {
        var svg = host.querySelector('svg');
        if (!svg) {
            svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            host.insertBefore(svg, host.firstChild);
        }
        svg.setAttribute('viewBox', '0 0 400 300');
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        return svg;
    }

    function renderMap(geo) {
        var host = document.getElementById('dsMap');
        if (!host) return;
        var tip = document.getElementById('dsMapTip');
        geo = geo || {};
        var pack = mapMode === 'world' ? (geo.world || {}) : (geo.china || {});
        var cities = Array.isArray(pack.cities) ? pack.cities : [];
        var flows = Array.isArray(pack.flows) ? pack.flows : [];
        var idx = cityIndex(cities);
        var svg = ensureSvg(host);
        var ns = 'http://www.w3.org/2000/svg';

        while (svg.firstChild) svg.removeChild(svg.firstChild);

        var land = document.createElementNS(ns, 'path');
        land.setAttribute('class', 'ds-map-outline');
        land.setAttribute('d', mapMode === 'world' ? worldOutline() : chinaOutline());
        svg.appendChild(land);

        flows.forEach(function (f) {
            var from = idx[f.from];
            var to = idx[f.to];
            if (!from || !to) return;
            var a = { x: Number(from.x), y: Number(from.y) };
            var b = { x: Number(to.x), y: Number(to.y) };
            var path = document.createElementNS(ns, 'path');
            path.setAttribute('class', 'ds-flow-line');
            path.setAttribute('d', curvePath(a, b));
            path.setAttribute('data-tip', (f.from || '') + ' → ' + (f.to || '')
                + (f.count ? (' · ' + f.count + ' 次') : ''));
            svg.appendChild(path);
        });

        var shown = 0;
        cities.forEach(function (c) {
            var x = Number(c.x);
            var y = Number(c.y);
            var count = parseInt(c.count, 10) || 0;
            if (!count && cities.some(function (z) { return (z.count || 0) > 0; })) {
                // 有真实数据时只画有量的点，避免满屏空点
                return;
            }
            shown++;
            var g = document.createElementNS(ns, 'g');
            g.setAttribute('data-tip', (c.name || '') + ' · ' + fmtNum(count) + ' 次 · ' + (c.status || ''));

            var pulse = document.createElementNS(ns, 'circle');
            pulse.setAttribute('class', 'ds-city-pulse');
            pulse.setAttribute('cx', String(x));
            pulse.setAttribute('cy', String(y));
            pulse.setAttribute('r', '6');
            g.appendChild(pulse);

            var dot = document.createElementNS(ns, 'circle');
            dot.setAttribute('class', 'ds-city-dot');
            dot.setAttribute('cx', String(x));
            dot.setAttribute('cy', String(y));
            dot.setAttribute('r', String(Math.max(3, Math.min(8, 3 + Math.sqrt(count) * 0.45))));
            g.appendChild(dot);

            if (count > 0 || mapMode === 'world') {
                var label = document.createElementNS(ns, 'text');
                label.setAttribute('class', 'ds-city-label');
                label.setAttribute('x', String(x + 8));
                label.setAttribute('y', String(y + 3));
                label.textContent = c.name || '';
                g.appendChild(label);
            }
            svg.appendChild(g);
        });

        if (!shown && !flows.length) {
            var empty = document.createElementNS(ns, 'text');
            empty.setAttribute('x', '200');
            empty.setAttribute('y', '150');
            empty.setAttribute('text-anchor', 'middle');
            empty.setAttribute('fill', '#94a3b8');
            empty.setAttribute('font-size', '13');
            empty.textContent = '暂无地域调用数据';
            svg.appendChild(empty);
        }

        svg.onmousemove = function (e) {
            if (!tip) return;
            var el = e.target;
            if (el && el.nodeType === 3) el = el.parentElement;
            var t = (el && typeof el.closest === 'function') ? el.closest('[data-tip]') : null;
            if (!t) {
                tip.hidden = true;
                return;
            }
            tip.textContent = t.getAttribute('data-tip') || '';
            tip.hidden = false;
            var rect = host.getBoundingClientRect();
            tip.style.left = (e.clientX - rect.left) + 'px';
            tip.style.top = (e.clientY - rect.top) + 'px';
        };
        svg.onmouseleave = function () {
            if (tip) tip.hidden = true;
        };
    }

    function areaChart(el, labels, series) {
        if (!el) return;
        labels = labels || [];
        series = series || [];
        var w = 640, h = 220, L = 36, R = 10, T = 14, B = 28;
        var max = Math.max.apply(null, series.concat([1]));
        var n = Math.max(1, labels.length);
        function xAt(i) { return L + (i * (w - L - R)) / Math.max(1, n - 1); }
        function yAt(v) { return T + (1 - v / max) * (h - T - B); }
        var line = series.map(function (v, i) {
            return (i === 0 ? 'M' : 'L') + xAt(i).toFixed(1) + ' ' + yAt(v).toFixed(1);
        }).join(' ');
        var area = line + ' L' + xAt(n - 1).toFixed(1) + ' ' + (h - B) + ' L' + L + ' ' + (h - B) + ' Z';
        var grid = '';
        for (var g = 0; g < 4; g++) {
            var gy = T + g * (h - T - B) / 3;
            grid += '<line x1="' + L + '" y1="' + gy + '" x2="' + (w - R) + '" y2="' + gy + '" stroke="rgba(148,163,184,0.25)"/>';
        }
        var xLabels = '';
        labels.forEach(function (lb, i) {
            if (n > 12 && i % 3 !== 0 && i !== n - 1) return;
            xLabels += '<text x="' + xAt(i) + '" y="' + (h - 8) + '" text-anchor="middle" fill="#94a3b8" font-size="10">' + esc(lb) + '</text>';
        });
        el.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '">'
            + grid
            + '<path d="' + area + '" fill="rgba(37,99,235,0.12)"/>'
            + '<path d="' + line + '" fill="none" stroke="#2563eb" stroke-width="2"/>'
            + xLabels
            + '</svg>';
    }

    function renderKpi(kpi, rpm) {
        var el = document.getElementById('dsKpi');
        if (!el) return;
        kpi = kpi || {};
        var items = [
            {
                label: '今日调用',
                value: fmtNum(kpi.today_calls),
                delta: deltaText(kpi.today_delta, '%'),
                dcls: deltaClass(kpi.today_delta),
                vcls: ''
            },
            {
                label: '累计调用',
                value: fmtNum(kpi.total_calls),
                delta: deltaText(kpi.total_delta, '%'),
                dcls: deltaClass(kpi.total_delta),
                vcls: ''
            },
            {
                label: '成功率',
                value: (parseFloat(kpi.success_rate) || 0).toFixed(2) + '%',
                delta: deltaText(kpi.success_delta, '%'),
                dcls: deltaClass(kpi.success_delta),
                vcls: 'is-ok'
            },
            {
                label: '失败率',
                value: (parseFloat(kpi.fail_rate) || 0).toFixed(2) + '%',
                delta: deltaText(kpi.fail_delta, '%'),
                dcls: deltaClass(kpi.fail_delta),
                vcls: 'is-fail'
            }
        ];
        el.innerHTML = items.map(function (it) {
            return '<div class="ds-stat-strip__item">'
                + '<div class="ds-stat-strip__label">' + esc(it.label) + '</div>'
                + '<div class="ds-stat-strip__value ' + it.vcls + '">' + esc(it.value) + '</div>'
                + '<div class="ds-stat-strip__delta ' + it.dcls + '">' + esc(it.delta) + '</div>'
                + '</div>';
        }).join('');

        var rpmEl = document.getElementById('dsCurrentRpm');
        if (rpmEl) {
            rpmEl.textContent = fmtNum(rpm != null ? rpm : boot.current_rpm);
        }
    }

    function renderTop(list) {
        var el = document.getElementById('dsTopBars');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">暂无排行</div>';
            return;
        }
        el.innerHTML = list.map(function (row, i) {
            var pct = Math.max(4, parseFloat(row.pct) || 0);
            var color = BAR_COLORS[i % BAR_COLORS.length];
            return '<div class="ds-bar-row">'
                + '<div class="ds-bar-meta">'
                + '<span>' + esc(row.name) + '</span>'
                + '<span class="ds-bar-val">' + fmtNum(row.count) + '</span>'
                + '</div>'
                + '<div class="ds-bar-track"><div class="ds-bar-fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
                + '</div>';
        }).join('');
    }

    function renderRecent(list) {
        var el = document.getElementById('dsLogStream');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">暂无实时记录</div>';
            return;
        }
        el.innerHTML = list.map(function (r) {
            var level = r.level || (r.status === 'success' ? 'success' : 'error');
            var levelText = level === 'success' ? '成功' : (level === 'warning' ? '警告' : (level === 'info' ? '信息' : '失败'));
            return '<div class="ds-log-row">'
                + '<span class="ds-log-time">' + esc(r.time) + '</span>'
                + '<span class="ds-log-level ds-log-level--' + esc(level) + '">' + esc(levelText) + '</span>'
                + '<span>' + esc(r.apiname) + ' · ' + esc(r.caller)
                + (r.code_label ? (' · ' + esc(r.code_label)) : '')
                + '</span>'
                + '</div>';
        }).join('');
    }

    function renderClock(t) {
        var el = document.getElementById('dsClock');
        if (el) el.textContent = t || boot.server_time || '';
    }

    function renderAll(data) {
        boot = data || boot;
        renderClock(boot.server_time);
        renderKpi(boot.kpi, boot.current_rpm);
        areaChart(document.getElementById('dsHourlyChart'), (boot.hourly || {}).labels, (boot.hourly || {}).series);
        renderTop(boot.top_apis);
        renderMap(boot.geo);
        renderRecent(boot.recent);
    }

    function applyTheme(next) {
        theme = next === 'dark' ? 'dark' : 'light';
        page.classList.toggle('vs-datascreen--dark', theme === 'dark');
        page.classList.toggle('vs-datascreen--light', theme === 'light');
        var btn = document.getElementById('dsThemeBtn');
        if (btn) btn.textContent = theme === 'dark' ? '浅色' : '深色';
    }

    function toggleFullscreen() {
        var on = !document.body.classList.contains('is-ds-fullscreen');
        document.body.classList.toggle('is-ds-fullscreen', on);
        var btn = document.getElementById('dsFullscreenBtn');
        if (btn) btn.textContent = on ? '退出全屏' : '全屏';
        if (on && page.requestFullscreen) {
            try { page.requestFullscreen(); } catch (e) { /* ignore */ }
        } else if (!on && document.fullscreenElement && document.exitFullscreen) {
            try { document.exitFullscreen(); } catch (e) { /* ignore */ }
        }
    }

    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            document.body.classList.remove('is-ds-fullscreen');
            var btn = document.getElementById('dsFullscreenBtn');
            if (btn) btn.textContent = '全屏';
        }
    });

    var mapBtns = document.getElementById('dsMapToggle');
    if (mapBtns) {
        mapBtns.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-map]');
            if (!btn) return;
            mapMode = btn.getAttribute('data-map') === 'world' ? 'world' : 'china';
            Array.prototype.forEach.call(mapBtns.querySelectorAll('[data-map]'), function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            renderMap(boot.geo);
        });
    }

    var themeBtn = document.getElementById('dsThemeBtn');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            applyTheme(theme === 'dark' ? 'light' : 'dark');
        });
    }

    var fsBtn = document.getElementById('dsFullscreenBtn');
    if (fsBtn) {
        fsBtn.addEventListener('click', toggleFullscreen);
    }

    var refreshBtn = document.getElementById('dsRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            refreshBtn.disabled = true;
            post('refresh').then(function (res) {
                if (!res || res.code !== 1 || !res.snapshot) {
                    window.VS.showMessage((res && res.msg) || '刷新失败', 'error');
                    return;
                }
                renderAll(res.snapshot);
                window.VS.showMessage('已刷新', 'success');
            }).catch(function () {
                window.VS.showMessage('网络异常', 'error');
            }).then(function () {
                refreshBtn.disabled = false;
            });
        });
    }

    function pollLive() {
        post('live').then(function (res) {
            if (!res || res.code !== 1 || !res.live) return;
            var live = res.live;
            renderClock(live.server_time);
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
            }
            if (live.current_rpm != null) boot.current_rpm = live.current_rpm;
            renderKpi(boot.kpi, boot.current_rpm);
            if (live.recent) {
                boot.recent = live.recent;
                renderRecent(boot.recent);
            }
        }).catch(function () { /* 静默 */ });
    }

    function softSnapshot() {
        post('snapshot').then(function (res) {
            if (!res || res.code !== 1 || !res.snapshot) return;
            renderAll(res.snapshot);
        }).catch(function () { /* 静默 */ });
    }

    applyTheme(theme);
    renderAll(boot);
    pollTimer = setInterval(pollLive, 5000);
    softTimer = setInterval(softSnapshot, 60000);

    window.addEventListener('beforeunload', function () {
        if (pollTimer) clearInterval(pollTimer);
        if (softTimer) clearInterval(softTimer);
    });
})();
