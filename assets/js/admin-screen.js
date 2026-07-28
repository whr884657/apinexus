/**
 * 文件：assets/js/admin-screen.js
 * 作用：数据大屏（ECharts 地图飞线 / KPI / TOP / 趋势 / 实时日志）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminScreenPage');
    if (!page || !window.VS || !window.echarts) return;

    var BAR_COLORS = ['#2563eb', '#06b6d4', '#8b5cf6', '#f59e0b', '#10b981', '#ec4899', '#3b82f6', '#14b8a6', '#a855f7', '#fb923c'];
    var ICON_SUN = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
        + '<circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/>'
        + '<g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">'
        + '<path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>'
        + '</g></svg>';
    var ICON_MOON = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
        + '<path fill="currentColor" d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/>'
        + '</svg>';
    var ICON_FS = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
        + '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        + ' d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/>'
        + '</svg>';
    var ICON_FS_EXIT = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
        + '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        + ' d="M9 3v5H4M15 3v5h5M9 21v-5H4M15 21v-5h5"/>'
        + '</svg>';

    var boot = {};
    var mapMode = 'china';
    var geoScope = 'live';
    var theme = page.classList.contains('vs-datascreen--dark') ? 'dark' : 'light';
    var mapChart = null;
    var mapRegistered = { china: false, world: false };
    var pollTimer = null;
    var softTimer = null;
    var clockTimer = null;
    var clockBaseMs = 0;
    var clockOffset = 0;
    var liveIntervalMs = 5000;
    var softIntervalMs = 45000;
    var topMarqueeRaf = null;
    var topMarqueeY = 0;
    var topMarqueePaused = false;

    try {
        boot = JSON.parse(page.getAttribute('data-boot') || '{}') || {};
    } catch (e) {
        boot = {};
    }
    liveIntervalMs = readLiveIntervalMs(boot.live_interval);
    softIntervalMs = Math.max(15000, liveIntervalMs * 6);

    function syncSoftIntervalFromLive() {
        softIntervalMs = Math.max(15000, liveIntervalMs * 6);
        restartSoftPoll();
    }

    function readLiveIntervalMs(v) {
        var n = parseInt(v, 10);
        if (isNaN(n) || n < 1) n = 5;
        if (n > 5) n = 5;
        return n * 1000;
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

    function cssVar(name, fallback) {
        var v = '';
        try {
            v = getComputedStyle(page).getPropertyValue(name).trim();
        } catch (e) { /* ignore */ }
        return v || fallback;
    }

    function mapPalette() {
        var dark = theme === 'dark';
        return {
            areaColor: dark ? 'rgba(37,99,235,0.18)' : 'rgba(37,99,235,0.08)',
            borderColor: dark ? '#3b82f6' : '#93c5fd',
            labelColor: cssVar('--ds-muted', dark ? '#94a3b8' : '#64748b'),
            scatter: '#38bdf8',
            hub: '#f87171',
            line: 'rgba(251,191,36,0.35)',
            trail: '#fde68a',
            accent: cssVar('--ds-accent', '#2563eb'),
            text: cssVar('--ds-text', dark ? '#e5e7eb' : '#0f172a'),
            muted: cssVar('--ds-muted', dark ? '#94a3b8' : '#64748b'),
            grid: dark ? 'rgba(148,163,184,0.18)' : 'rgba(148,163,184,0.25)'
        };
    }

    function activeGeoPack() {
        var pack = geoScope === 'today'
            ? (boot.geo_today || boot.geo)
            : (boot.geo_live || boot.geo);
        return pack || {};
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function formatClock(ts) {
        var d = new Date(ts);
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate())
            + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
    }

    function syncClock(serverTime) {
        var parsed = Date.parse(String(serverTime || '').replace(/-/g, '/'));
        if (isNaN(parsed)) parsed = Date.now();
        clockBaseMs = parsed;
        clockOffset = Date.now();
        tickClock();
    }

    function tickClock() {
        var el = document.getElementById('dsClock');
        if (!el || !clockBaseMs) return;
        el.textContent = formatClock(clockBaseMs + (Date.now() - clockOffset));
    }

    function setMapStatus(text, isError) {
        var el = document.getElementById('dsMapStatus');
        if (!el) return;
        if (!text) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.hidden = false;
        el.textContent = text;
        el.setAttribute('data-state', isError ? 'error' : 'loading');
    }

    function mapUrl(mode) {
        var attr = mode === 'world' ? 'data-map-world' : 'data-map-china';
        return page.getAttribute(attr) || '';
    }

    function ensureMapChart() {
        var el = document.getElementById('dsMapChart');
        if (!el) return null;
        if (!mapChart) {
            mapChart = window.echarts.init(el);
        }
        return mapChart;
    }

    function buildMapOption(geoRoot) {
        var colors = mapPalette();
        var pack = ((geoRoot || {})[mapMode]) || {};
        var cities = Array.isArray(pack.cities) ? pack.cities : [];
        var flows = Array.isArray(pack.flows) ? pack.flows : [];
        var hub = pack.hub || {};
        var isChina = mapMode === 'china';
        var isDesktop = window.innerWidth >= 1200;

        var choropleth = cities
            .filter(function (c) { return c && c.name && (parseInt(c.count, 10) || 0) > 0; })
            .map(function (c) {
                return { name: c.name, value: parseInt(c.count, 10) || 0 };
            });

        var scatter = cities
            .filter(function (c) {
                return c && (parseInt(c.count, 10) || 0) > 0
                    && Array.isArray(c.coord) && c.coord.length >= 2;
            })
            .map(function (c) {
                return {
                    name: c.name,
                    value: [Number(c.coord[0]), Number(c.coord[1]), parseInt(c.count, 10) || 0]
                };
            });

        // 飞线样式对齐参考 UI：隐形轨迹 + 小圆粒子拖尾；色=身份（绿密钥/黄游客/红失败）
        var toneColor = {
            green: '#22c55e',
            yellow: '#eab308',
            red: '#ef4444'
        };
        var kindLabel = {
            green: '密钥成功',
            yellow: '游客成功',
            red: '失败'
        };

        function simpleHash(str) {
            var hash = 0;
            var s = String(str || '');
            for (var i = 0; i < s.length; i++) {
                hash = ((hash << 5) - hash) + s.charCodeAt(i);
                hash |= 0;
            }
            return hash;
        }
        function seededRandom(seed) {
            var x = Math.sin(seed) * 10000;
            return x - Math.floor(x);
        }
        function particleCountFor(n) {
            n = parseInt(n, 10) || 0;
            if (n <= 1) return 1;
            if (n <= 3) return 2;
            if (n <= 8) return 3;
            if (n <= 20) return 4;
            if (n <= 50) return 5;
            if (n <= 100) return 6;
            if (n <= 300) return 8;
            if (n <= 1000) return 10;
            return 12;
        }
        function particleSizeFor(n) {
            n = parseInt(n, 10) || 0;
            if (n <= 3) return 1.5;
            if (n <= 20) return 2;
            if (n <= 100) return 2.5;
            if (n <= 500) return 3;
            return 3.5;
        }
        function particlePeriodFor(n) {
            n = parseInt(n, 10) || 0;
            if (n <= 3) return 12;
            if (n <= 20) return 10;
            if (n <= 100) return 8;
            if (n <= 500) return 6;
            return 5;
        }

        var linesData = [];
        flows.forEach(function (f) {
            if (!f || !Array.isArray(f.coords) || f.coords.length < 2
                || !Array.isArray(f.coords[0]) || !Array.isArray(f.coords[1])) {
                return;
            }
            var tone = (f.tone === 'yellow' || f.tone === 'red') ? f.tone : 'green';
            var color = toneColor[tone];
            var count = parseInt(f.count, 10) || 1;
            var parts = particleCountFor(count);
            var size = particleSizeFor(count);
            var speed = particlePeriodFor(count);
            var fromName = f.from || '';
            var toName = f.to || '';
            var kind = f.kind || '';
            var c0 = [Number(f.coords[0][0]), Number(f.coords[0][1])];
            var c1 = [Number(f.coords[1][0]), Number(f.coords[1][1])];
            for (var i = 0; i < parts; i++) {
                var seed = simpleHash(fromName + ':' + tone + ':' + i);
                var curveness = 0.1 + seededRandom(seed) * 0.3;
                var period = Math.max(2, speed * (0.6 + seededRandom(seed + 1) * 0.8));
                linesData.push({
                    fromName: fromName,
                    toName: toName,
                    kind: kind,
                    tone: tone,
                    count: count,
                    coords: [c0, c1],
                    lineStyle: {
                        color: 'rgba(0,0,0,0)',
                        width: 0,
                        opacity: 0,
                        curveness: curveness
                    },
                    effect: {
                        color: color,
                        symbolSize: size,
                        period: period
                    }
                });
            }
        });

        var hubCoord = (hub && Array.isArray(hub.coord) && hub.coord.length >= 2)
            ? [Number(hub.coord[0]), Number(hub.coord[1])]
            : [116.4074, 39.9042];
        var hubName = (hub && hub.name) ? String(hub.name) : '本站';

        return {
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'item',
                formatter: function (p) {
                    if (!p) return '';
                    if (p.seriesName === '调用量') {
                        return esc(p.name) + '<br/>调用量: ' + fmtNum(p.value || 0);
                    }
                    if (p.seriesName === '调用城市') {
                        var v = (p.value && p.value[2] != null) ? p.value[2] : 0;
                        return esc(p.name) + '<br/>调用: ' + fmtNum(v) + ' 次';
                    }
                    if (p.seriesName === '调用飞线') {
                        var d = p.data || {};
                        var lab = kindLabel[d.tone] || '';
                        return esc(d.fromName || '') + ' → ' + esc(d.toName || '')
                            + (lab ? ('<br/>' + lab) : '')
                            + (d.count ? (' · ' + fmtNum(d.count) + ' 次') : '');
                    }
                    if (p.seriesName === '枢纽') {
                        return esc(p.name || hubName);
                    }
                    return esc(p.name || '');
                }
            },
            geo: {
                map: mapMode,
                roam: true,
                zoom: isChina ? 1.1 : 1.2,
                center: isChina ? [105, 32] : [10, 25],
                layoutCenter: ['50%', '50%'],
                layoutSize: isDesktop ? (isChina ? '115%' : '120%') : (isChina ? '100%' : '105%'),
                label: {
                    show: isChina,
                    color: colors.labelColor,
                    fontSize: 9
                },
                emphasis: { disabled: true },
                itemStyle: {
                    areaColor: colors.areaColor,
                    borderColor: colors.borderColor,
                    borderWidth: 1
                },
                select: { disabled: true }
            },
            series: [
                {
                    name: '调用量',
                    type: 'map',
                    geoIndex: 0,
                    data: choropleth,
                    select: { disabled: true },
                    itemStyle: {
                        areaColor: colors.areaColor,
                        borderColor: colors.borderColor
                    },
                    emphasis: { disabled: true }
                },
                {
                    name: '调用飞线',
                    type: 'lines',
                    zlevel: 2,
                    coordinateSystem: 'geo',
                    polyline: false,
                    tooltip: { show: true },
                    // 参考样式：隐形线体 + 小圆粒子 + 短拖尾
                    effect: {
                        show: true,
                        period: 8,
                        trailLength: 0.2,
                        symbol: 'circle',
                        symbolSize: 2,
                        color: colors.trail
                    },
                    lineStyle: {
                        color: 'rgba(0,0,0,0)',
                        width: 0,
                        opacity: 0,
                        curveness: 0.1
                    },
                    data: linesData
                },
                {
                    name: '调用城市',
                    type: 'effectScatter',
                    zlevel: 3,
                    coordinateSystem: 'geo',
                    rippleEffect: {
                        period: 4,
                        brushType: 'stroke',
                        scale: 4
                    },
                    symbolSize: function (val) {
                        var c = (val && val[2]) ? val[2] : 1;
                        return Math.max(6, Math.min(20, 4 + Math.sqrt(c) * 0.9));
                    },
                    itemStyle: {
                        color: colors.scatter,
                        shadowBlur: 10,
                        shadowColor: colors.scatter
                    },
                    data: scatter
                },
                {
                    name: '枢纽',
                    type: 'effectScatter',
                    zlevel: 4,
                    coordinateSystem: 'geo',
                    rippleEffect: { period: 3, brushType: 'stroke', scale: 6 },
                    symbolSize: 12,
                    itemStyle: {
                        color: colors.hub,
                        shadowBlur: 15,
                        shadowColor: colors.hub
                    },
                    data: [{ name: hubName, value: [hubCoord[0], hubCoord[1], 0] }]
                }
            ]
        };
    }

    function applyMapOption() {
        var chart = ensureMapChart();
        if (!chart || !mapRegistered[mapMode]) return;
        chart.setOption(buildMapOption(activeGeoPack()), true);
        chart.resize();
    }

    function loadMap(mode) {
        mode = mode === 'world' ? 'world' : 'china';
        if (mapRegistered[mode]) {
            mapMode = mode;
            setMapStatus('');
            applyMapOption();
            return Promise.resolve();
        }
        var url = mapUrl(mode);
        if (!url) {
            setMapStatus('地图资源暂不可用', true);
            return Promise.reject(new Error('missing url'));
        }
        mapMode = mode;
        setMapStatus('地图加载中…', false);
        return fetch(url, { credentials: 'omit', cache: 'force-cache' })
            .then(function (res) {
                if (!res.ok) throw new Error('bad status');
                return res.json();
            })
            .then(function (json) {
                window.echarts.registerMap(mode, json);
                mapRegistered[mode] = true;
                setMapStatus('');
                if (mapMode === mode) applyMapOption();
            })
            .catch(function () {
                if (mapMode === mode) setMapStatus('地图加载失败，请稍后重试', true);
            });
    }

    function smoothLine(pts) {
        if (!pts.length) return '';
        if (pts.length < 3) {
            return pts.map(function (p, i) {
                return (i === 0 ? 'M' : 'L') + p.x.toFixed(1) + ' ' + p.y.toFixed(1);
            }).join(' ');
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

    function areaChart(el, labels, series) {
        if (!el) return;
        labels = labels || [];
        series = series || [];
        var colors = mapPalette();
        var w = 640, h = 220, L = 36, R = 10, T = 14, B = 28;
        var max = Math.max.apply(null, series.concat([1]));
        var n = Math.max(1, labels.length);
        function xAt(i) { return L + (i * (w - L - R)) / Math.max(1, n - 1); }
        function yAt(v) { return T + (1 - v / max) * (h - T - B); }
        var pts = series.map(function (v, i) {
            return { x: xAt(i), y: yAt(v) };
        });
        var line = smoothLine(pts);
        var area = line + ' L' + xAt(Math.max(0, n - 1)).toFixed(1) + ' ' + (h - B) + ' L' + L + ' ' + (h - B) + ' Z';
        var grid = '';
        for (var g = 0; g < 4; g++) {
            var gy = T + g * (h - T - B) / 3;
            grid += '<line x1="' + L + '" y1="' + gy + '" x2="' + (w - R) + '" y2="' + gy
                + '" stroke="' + colors.grid + '"/>';
        }
        var xLabels = '';
        labels.forEach(function (lb, i) {
            if (n > 12 && i % 3 !== 0 && i !== n - 1) return;
            xLabels += '<text x="' + xAt(i) + '" y="' + (h - 8) + '" text-anchor="middle" fill="'
                + colors.muted + '" font-size="10">' + esc(lb) + '</text>';
        });
        el.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '">'
            + grid
            + '<path d="' + area + '" fill="rgba(37,99,235,0.12)"/>'
            + '<path d="' + line + '" fill="none" stroke="' + colors.accent
            + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
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
                + '<div class="ds-stat-strip__row">'
                + '<span class="ds-stat-strip__label">' + esc(it.label) + '</span>'
                + '<span class="ds-stat-strip__delta ' + it.dcls + '">' + esc(it.delta) + '</span>'
                + '</div>'
                + '<div class="ds-stat-strip__value ' + it.vcls + '">' + esc(it.value) + '</div>'
                + '</div>';
        }).join('');

        var rpmEl = document.getElementById('dsCurrentRpm');
        if (rpmEl) {
            rpmEl.textContent = fmtNum(rpm != null ? rpm : boot.current_rpm);
        }
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
        var speed = 0.35;
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
        var el = document.getElementById('dsTopBars');
        var viewport = document.getElementById('dsTopViewport');
        if (!el) return;
        list = Array.isArray(list) ? list : [];
        stopTopMarquee();
        el.classList.remove('is-marquee');
        el.style.transform = '';
        if (!list.length) {
            el.innerHTML = '<div class="dash-empty">暂无排行</div>';
            return;
        }
        var rowsHtml = list.map(function (row, i) {
            var pct = Math.max(4, parseFloat(row.pct) || 0);
            var color = BAR_COLORS[i % BAR_COLORS.length];
            var rank = i + 1;
            return '<div class="ds-bar-row">'
                + '<div class="ds-bar-meta">'
                + '<span class="ds-bar-rank">' + rank + '</span>'
                + '<span class="ds-bar-name">' + esc(row.name) + '</span>'
                + '<span class="ds-bar-val">' + fmtNum(row.count) + '</span>'
                + '</div>'
                + '<div class="ds-bar-track"><div class="ds-bar-fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
                + '</div>';
        }).join('');
        el.innerHTML = rowsHtml;
        // 超过 3 条才无限循环滚动；≤3 条静止展示
        if (list.length > 3 && viewport) {
            el.innerHTML = rowsHtml + rowsHtml;
            el.classList.add('is-marquee');
            // 等布局后再量半高，保证无缝循环
            requestAnimationFrame(function () {
                var half = el.scrollHeight / 2;
                if (half > 8) startTopMarquee(el, half);
            });
        }
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
            var levelText = level === 'success' ? '成功'
                : (level === 'warning' ? '警告' : (level === 'info' ? '信息' : '失败'));
            return '<div class="ds-log-row">'
                + '<span class="ds-log-time">' + esc(r.time) + '</span>'
                + '<span class="ds-log-level ds-log-level--' + esc(level) + '">' + esc(levelText) + '</span>'
                + '<span>' + esc(r.apiname) + ' · ' + esc(r.caller)
                + (r.code_label ? (' · ' + esc(r.code_label)) : '')
                + '</span>'
                + '</div>';
        }).join('');
    }

    function renderAll(data) {
        boot = data || boot;
        if (boot.live_interval != null) {
            var next = readLiveIntervalMs(boot.live_interval);
            if (next !== liveIntervalMs) {
                liveIntervalMs = next;
                restartLivePoll();
                syncSoftIntervalFromLive();
            }
        }
        if (boot.server_time) syncClock(boot.server_time);
        renderKpi(boot.kpi, boot.current_rpm);
        areaChart(document.getElementById('dsHourlyChart'), (boot.hourly || {}).labels, (boot.hourly || {}).series);
        renderTop(boot.top_apis);
        renderRecent(boot.recent);
        if (mapRegistered[mapMode]) {
            applyMapOption();
        } else {
            loadMap(mapMode);
        }
    }

    function mergeLive(live) {
        if (!live) return;
        if (live.server_time) syncClock(live.server_time);
        if (live.live_interval != null) {
            var next = readLiveIntervalMs(live.live_interval);
            if (next !== liveIntervalMs) {
                liveIntervalMs = next;
                restartLivePoll();
                syncSoftIntervalFromLive();
            }
        }
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
        if (live.geo_live) boot.geo_live = live.geo_live;
        if (live.geo_today) boot.geo_today = live.geo_today;
        if (live.geo) boot.geo = live.geo;
        if (live.geo_live || live.geo_today || live.geo) {
            if (mapRegistered[mapMode]) applyMapOption();
        }
    }

    function applyTheme(next) {
        theme = next === 'dark' ? 'dark' : 'light';
        page.classList.toggle('vs-datascreen--dark', theme === 'dark');
        page.classList.toggle('vs-datascreen--light', theme === 'light');
        var btn = document.getElementById('dsThemeBtn');
        if (btn) {
            btn.innerHTML = theme === 'dark' ? ICON_SUN : ICON_MOON;
            btn.title = theme === 'dark' ? '切换浅色' : '切换深色';
            btn.setAttribute('aria-label', btn.title);
        }
        var fsBtn = document.getElementById('dsFullscreenBtn');
        if (fsBtn && !fsBtn.innerHTML) {
            fsBtn.innerHTML = ICON_FS;
        }
        areaChart(document.getElementById('dsHourlyChart'), (boot.hourly || {}).labels, (boot.hourly || {}).series);
        if (mapRegistered[mapMode]) applyMapOption();
    }

    function setFullscreenIcon(on) {
        var btn = document.getElementById('dsFullscreenBtn');
        if (!btn) return;
        btn.innerHTML = on ? ICON_FS_EXIT : ICON_FS;
        btn.title = on ? '退出全屏' : '全屏';
        btn.setAttribute('aria-label', btn.title);
    }

    function toggleFullscreen() {
        var on = !document.body.classList.contains('is-ds-fullscreen');
        document.body.classList.toggle('is-ds-fullscreen', on);
        setFullscreenIcon(on);
        if (on && page.requestFullscreen) {
            try { page.requestFullscreen(); } catch (e) { /* ignore */ }
        } else if (!on && document.fullscreenElement && document.exitFullscreen) {
            try { document.exitFullscreen(); } catch (e) { /* ignore */ }
        }
        setTimeout(function () {
            if (mapChart) mapChart.resize();
        }, 80);
    }

    function pollLive() {
        post('live').then(function (res) {
            if (!res || res.code !== 1 || !res.live) return;
            mergeLive(res.live);
        }).catch(function () { /* 静默 */ });
    }

    function softSnapshot() {
        post('snapshot').then(function (res) {
            if (!res || res.code !== 1 || !res.snapshot) return;
            var snap = res.snapshot;
            var liveKpi = boot.kpi || {};
            // 软刷不得覆盖 live 已更新的 KPI / 最近日志 / RPM（E129）
            boot.hourly = snap.hourly || boot.hourly;
            boot.top_apis = snap.top_apis || boot.top_apis;
            if (snap.geo_today) boot.geo_today = snap.geo_today;
            if (snap.geo_live) boot.geo_live = snap.geo_live;
            if (snap.geo) boot.geo = snap.geo;
            // 软刷：今日地理可更新；实时地理以 live 为准（已有则不强制覆盖）
            if (!boot.geo_live && snap.geo_live) boot.geo_live = snap.geo_live;
            if (snap.server_time) syncClock(snap.server_time);
            if (snap.live_interval != null) {
                var next = readLiveIntervalMs(snap.live_interval);
                if (next !== liveIntervalMs) {
                    liveIntervalMs = next;
                    restartLivePoll();
                }
            }
            // 仅补齐 live 未带的 delta 等字段
            if (snap.kpi) {
                var merged = {};
                var k;
                for (k in snap.kpi) {
                    if (Object.prototype.hasOwnProperty.call(snap.kpi, k)) merged[k] = snap.kpi[k];
                }
                for (k in liveKpi) {
                    if (Object.prototype.hasOwnProperty.call(liveKpi, k)
                        && (k === 'today_calls' || k === 'total_calls' || k === 'success_rate' || k === 'fail_rate')) {
                        merged[k] = liveKpi[k];
                    }
                }
                boot.kpi = merged;
            }
            areaChart(document.getElementById('dsHourlyChart'), (boot.hourly || {}).labels, (boot.hourly || {}).series);
            renderTop(boot.top_apis);
            renderKpi(boot.kpi, boot.current_rpm);
            if (mapRegistered[mapMode]) applyMapOption();
            else loadMap(mapMode);
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
        softTimer = setInterval(softSnapshot, softIntervalMs);
    }

    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            document.body.classList.remove('is-ds-fullscreen');
            setFullscreenIcon(false);
        }
        if (mapChart) mapChart.resize();
    });

    var mapBtns = document.getElementById('dsMapToggle');
    if (mapBtns) {
        mapBtns.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-map]');
            if (!btn) return;
            var next = btn.getAttribute('data-map') === 'world' ? 'world' : 'china';
            Array.prototype.forEach.call(mapBtns.querySelectorAll('[data-map]'), function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            loadMap(next);
        });
    }

    var scopeBtns = document.getElementById('dsGeoScopeToggle');
    if (scopeBtns) {
        scopeBtns.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-scope]');
            if (!btn) return;
            var next = btn.getAttribute('data-scope') === 'today' ? 'today' : 'live';
            geoScope = next;
            Array.prototype.forEach.call(scopeBtns.querySelectorAll('[data-scope]'), function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            if (mapRegistered[mapMode]) applyMapOption();
            else loadMap(mapMode);
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

    window.addEventListener('resize', function () {
        if (mapChart) mapChart.resize();
    });

    var mapHost = document.getElementById('dsMapChart');
    if (mapHost && typeof ResizeObserver === 'function') {
        var ro = new ResizeObserver(function () {
            if (mapChart) mapChart.resize();
        });
        ro.observe(mapHost);
    }

    applyTheme(theme);
    setFullscreenIcon(false);
    ensureMapChart();
    renderAll(boot);
    clockTimer = setInterval(tickClock, 1000);
    restartLivePoll();
    restartSoftPoll();

    var topViewport = document.getElementById('dsTopViewport');
    if (topViewport) {
        topViewport.addEventListener('mouseenter', function () { topMarqueePaused = true; });
        topViewport.addEventListener('mouseleave', function () { topMarqueePaused = false; });
    }

    window.addEventListener('beforeunload', function () {
        stopTopMarquee();
        if (pollTimer) clearInterval(pollTimer);
        if (softTimer) clearInterval(softTimer);
        if (clockTimer) clearInterval(clockTimer);
        if (mapChart) {
            try { mapChart.dispose(); } catch (e) { /* ignore */ }
            mapChart = null;
        }
    });
})();
