/**
 * 用户控制台：近 7 日折线 + KPI/排行/近期动态刷新（固定 3 秒轮询）
 */
(function () {
    'use strict';

    var LIVE_MS = 3000;
    var page = document.getElementById('ucDashboard');
    if (!page || !window.VS || typeof VS.postForm !== 'function') {
        return;
    }

    var COLORS = { key: '#2563eb', points: '#f59e0b', ok: '#16a34a', fail: '#dc2626' };
    var boot = {};
    try {
        boot = JSON.parse(page.getAttribute('data-chart-boot') || '{}') || {};
    } catch (e) {
        boot = {};
    }

    var labels = boot.labels || [];
    var keyCalls = boot.key_calls || [];
    var pointsCalls = boot.points_calls || [];
    var successRates = boot.success_rate || [];
    var failRates = boot.fail_rate || [];
    var liveInFlight = false;
    var liveTimer = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtCost(v) {
        var n = parseFloat(v);
        if (!isFinite(n) || n === 0) {
            return '0';
        }
        return (Math.round(n * 10000) / 10000).toString();
    }

    function fmtRate(v) {
        var n = parseFloat(v);
        if (!isFinite(n)) {
            return '0';
        }
        if (Math.abs(n - Math.round(n)) < 0.05) {
            return String(Math.round(n));
        }
        return n.toFixed(1);
    }

    function shortLabel(lb) {
        var s = String(lb == null ? '' : lb);
        return s.length >= 10 ? s.slice(5) : s;
    }

    function pageVisible() {
        return !(typeof document.hidden === 'boolean' && document.hidden);
    }

    function setField(name, value) {
        page.querySelectorAll('[data-field="' + name + '"]').forEach(function (el) {
            el.textContent = String(value == null ? '' : value);
        });
    }

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

    /**
     * 多系列折线（对齐管理端 admin-dashboard.js：固定 Y 轴、虚线、悬停各系列）
     * @param {HTMLElement} el
     * @param {string[]} chartLabels
     * @param {Array<{name:string,data:number[],color:string,dashed?:boolean}>} seriesList
     * @param {{fixedMin?:number,fixedMax?:number,unit?:string,formatValue?:function(*)}} opts
     */
    function lineChart(el, chartLabels, seriesList, opts) {
        if (!el) return;
        opts = opts || {};
        chartLabels = Array.isArray(chartLabels) ? chartLabels : [];
        seriesList = Array.isArray(seriesList) ? seriesList : [];
        var all = [];
        seriesList.forEach(function (s) { all = all.concat(s.data || []); });
        if (!all.length) {
            el.innerHTML = '<div class="uc-dash__chart-empty">暂无趋势数据</div>';
            return;
        }
        var unit = opts.unit || '';
        var w = 560, h = 200, L = 36, R = 10, T = 14, B = 26;
        var plotTop = T, plotBottom = h - B;
        var max = Math.max.apply(null, all.concat([1]));
        var min = 0;
        if (opts.fixedMin != null) min = opts.fixedMin;
        if (opts.fixedMax != null) max = opts.fixedMax;
        if (min < 0) min = 0;
        var span = Math.max(0.0001, max - min);
        var n = Math.max(1, chartLabels.length || 1);
        function xAt(i) { return L + (i * (w - L - R)) / Math.max(1, n - 1); }
        function yAt(v) { return T + (1 - (v - min) / span) * (h - T - B); }
        var grid = '';
        var g;
        for (g = 0; g < 4; g++) {
            var gy = T + g * (h - T - B) / 3;
            grid += '<line x1="' + L + '" y1="' + gy + '" x2="' + (w - R) + '" y2="' + gy + '" stroke="#e5e7eb" stroke-width="1"/>';
        }
        var paths = seriesList.map(function (s) {
            var pts = (s.data || []).map(function (v, i) {
                return { x: xAt(i), y: yAt(v) };
            });
            var dash = s.dashed ? ' stroke-dasharray="5 4"' : '';
            return '<path d="' + smoothPath(pts, plotTop, plotBottom) + '" fill="none" stroke="'
                + esc(s.color || '#2563eb') + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"' + dash + '/>';
        }).join('');
        var xLabels = '';
        chartLabels.forEach(function (lb, i) {
            if (n > 8 && i % 2 === 1 && i !== n - 1) return;
            xLabels += '<text x="' + xAt(i) + '" y="' + (h - 8) + '" text-anchor="middle" fill="#94a3b8" font-size="11">' + esc(lb) + '</text>';
        });
        var fmtTip = opts.formatValue || function (v) {
            var num = parseFloat(v);
            if (isNaN(num)) return '0';
            return (Math.round(num * 100) / 100).toLocaleString('zh-CN');
        };
        el.innerHTML = '<div class="uc-dash__chart-canvas">'
            + '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img">'
            + grid + paths + xLabels
            + '<line class="uc-dash__chart-guide" x1="0" y1="' + plotTop + '" x2="0" y2="' + plotBottom + '" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" opacity="0"></line>'
            + '<g class="uc-dash__chart-dots"></g>'
            + '</svg>'
            + '<div class="uc-dash__chart-tip" hidden></div>'
            + '</div>';

        var svg = el.querySelector('svg');
        var tip = el.querySelector('.uc-dash__chart-tip');
        var guide = el.querySelector('.uc-dash__chart-guide');
        var dotsG = el.querySelector('.uc-dash__chart-dots');
        if (!svg || !tip) return;

        function nearestIndex(px) {
            var best = 0, bestDist = Infinity, i;
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

        function showAt(idx) {
            var lb = chartLabels[idx] != null ? chartLabels[idx] : '';
            var x = xAt(idx);
            var rect = svg.getBoundingClientRect();
            var scaleX = rect.width > 0 ? (rect.width / w) : 1;
            var rowsHtml = '';
            var dotsHtml = '';
            seriesList.forEach(function (s) {
                var raw = (s.data || [])[idx];
                if (raw == null) raw = 0;
                var yi = Math.max(plotTop, Math.min(plotBottom, yAt(raw)));
                dotsHtml += '<circle cx="' + x.toFixed(1) + '" cy="' + yi.toFixed(1) + '" r="3.5" fill="#fff" stroke="'
                    + esc(s.color || '#2563eb') + '" stroke-width="2"/>';
                rowsHtml += '<div class="uc-dash__chart-tip-row"><i style="background:' + esc(s.color || '#2563eb')
                    + '"></i><span>' + esc(s.name || '') + '</span><b>' + esc(fmtTip(raw) + unit) + '</b></div>';
            });
            tip.hidden = false;
            tip.innerHTML = '<div class="uc-dash__chart-tip-title">' + esc(lb) + '</div>' + rowsHtml;
            var tipW = tip.offsetWidth || 100;
            var leftPx = (x * scaleX) - (tipW / 2);
            leftPx = Math.max(4, Math.min(leftPx, Math.max(4, rect.width - tipW - 4)));
            tip.style.left = leftPx + 'px';
            tip.style.top = '8px';
            if (guide) {
                guide.setAttribute('x1', String(x));
                guide.setAttribute('x2', String(x));
                guide.setAttribute('opacity', '1');
            }
            if (dotsG) dotsG.innerHTML = dotsHtml;
        }

        svg.addEventListener('mousemove', function (ev) {
            var rect = svg.getBoundingClientRect();
            if (!rect.width) return;
            var px = ((ev.clientX - rect.left) / rect.width) * w;
            showAt(nearestIndex(px));
        });
        svg.addEventListener('mouseleave', hideTip);
        svg.addEventListener('touchstart', function (ev) {
            if (!ev.touches || !ev.touches[0]) return;
            var rect = svg.getBoundingClientRect();
            if (!rect.width) return;
            var px = ((ev.touches[0].clientX - rect.left) / rect.width) * w;
            showAt(nearestIndex(px));
        }, { passive: true });
    }

    function renderCharts() {
        lineChart(
            document.getElementById('ucDashCallsChart'),
            labels,
            [
                { name: '密钥', data: keyCalls, color: COLORS.key },
                { name: '积分', data: pointsCalls, color: COLORS.points, dashed: true }
            ],
            { fixedMin: 0 }
        );
        lineChart(
            document.getElementById('ucDashRateChart'),
            labels,
            [
                { name: '成功率', data: successRates, color: COLORS.ok },
                { name: '失败率', data: failRates, color: COLORS.fail, dashed: true }
            ],
            {
                fixedMin: 0,
                fixedMax: 100,
                unit: '%',
                formatValue: function (v) {
                    var n = parseFloat(v);
                    if (isNaN(n)) n = 0;
                    return (Math.round(n * 100) / 100).toFixed(2);
                }
            }
        );
    }

    function renderTop(list) {
        var el = document.getElementById('ucDashTopBody');
        if (!el) return;
        list = Array.isArray(list) ? list.slice(0, 8) : [];
        if (!list.length) {
            el.innerHTML = '<p class="uc-dash__empty">近 7 日暂无本人调用排行</p>';
            return;
        }
        var maxCalls = 1;
        list.forEach(function (row) {
            var c = row && row.calls != null ? parseInt(row.calls, 10) || 0 : 0;
            if (c > maxCalls) maxCalls = c;
        });
        var html = '<div class="uc-dash__bars">';
        list.forEach(function (row, i) {
            var rank = i + 1;
            var c = row && row.calls != null ? parseInt(row.calls, 10) || 0 : 0;
            var pct = Math.max(4, Math.round((c / maxCalls) * 100));
            var name = row && row.name ? String(row.name) : ('接口 #' + (row && row.apiid != null ? row.apiid : 0));
            var rankCls = rank <= 3 ? (' is-' + rank) : '';
            html += '<div class="uc-dash__bar"><div class="uc-dash__bar-meta">'
                + '<span class="uc-dash__bar-rank' + rankCls + '">' + rank + '</span>'
                + '<span class="uc-dash__bar-name" title="' + esc(name) + '">' + esc(name) + '</span>'
                + '<span class="uc-dash__bar-count">' + c + '</span></div>'
                + '<div class="uc-dash__bar-track"><div class="uc-dash__bar-fill" style="width:' + pct + '%"></div></div></div>';
        });
        html += '</div>';
        el.innerHTML = html;
    }

    function renderRecent(stats) {
        var el = document.getElementById('ucDashRecentBody');
        if (!el) return;
        var enabled = el.getAttribute('data-detail-enabled') === '1';
        if (stats && typeof stats.detail_enabled !== 'undefined') {
            enabled = !!stats.detail_enabled;
            el.setAttribute('data-detail-enabled', enabled ? '1' : '0');
        }
        if (!enabled) {
            el.innerHTML = '<p class="uc-dash__empty">管理员未开启调用明细，列表暂不可用；上方趋势仍可统计。</p>';
            return;
        }
        var list = stats && Array.isArray(stats.recent) ? stats.recent.slice(0, 12) : [];
        if (!list.length) {
            el.innerHTML = '<p class="uc-dash__empty">暂无调用记录</p>';
            return;
        }
        var html = '<div class="uc-dash__recent" role="list">'
            + '<div class="uc-dash__recent-row uc-dash__recent-row--head" role="presentation">'
            + '<span>接口</span><span>时间</span><span>IP</span></div>';
        list.forEach(function (row) {
            var name = row && row.apiname ? String(row.apiname) : '—';
            var time = row && row.createtime ? String(row.createtime) : '';
            var ip = row && row.ip ? String(row.ip) : '—';
            var okClass = row && row.ok_class ? String(row.ok_class) : '';
            var okLabel = row && row.ok_label ? String(row.ok_label) : '';
            html += '<div class="uc-dash__recent-row" role="listitem">'
                + '<span class="uc-dash__recent-ok ' + esc(okClass) + '">' + esc(okLabel) + '</span>'
                + '<span class="uc-dash__recent-name" title="' + esc(name) + '">' + esc(name) + '</span>'
                + '<span class="uc-dash__recent-time">' + esc(time) + '</span>'
                + '<span class="uc-dash__recent-ip">' + esc(ip) + '</span></div>';
        });
        html += '</div>';
        el.innerHTML = html;
    }

    function applyStats(stats) {
        if (!stats || typeof stats !== 'object') return;
        var s7 = stats.stat7 && typeof stats.stat7 === 'object' ? stats.stat7 : {};
        var days = s7.days && typeof s7.days === 'object' ? s7.days : {};

        setField('today_calls', s7.today_calls != null ? parseInt(s7.today_calls, 10) || 0 : 0);
        setField('today_cost', s7.today_cost_fmt != null ? s7.today_cost_fmt : '0');
        setField('avg_calls', s7.avg_calls != null ? s7.avg_calls : 0);
        setField('points_kpi', stats.points != null ? stats.points : '0');
        setField('points_spent', stats.points_spent != null ? stats.points_spent : '0');
        setField('key_calls', stats.key_calls != null ? parseInt(stats.key_calls, 10) || 0 : 0);
        setField('api_total', stats.api_total != null ? parseInt(stats.api_total, 10) || 0 : 0);
        setField(
            'api_total_meta',
            '已通过 ' + (stats.api_approved != null ? parseInt(stats.api_approved, 10) || 0 : 0)
                + ' · 待审 ' + (stats.api_pending != null ? parseInt(stats.api_pending, 10) || 0 : 0)
        );
        if (page.querySelector('[data-field="api_calls"]')) {
            setField('api_calls', stats.api_calls != null ? parseInt(stats.api_calls, 10) || 0 : 0);
        }

        labels = Array.isArray(days.labels) ? days.labels.map(shortLabel) : [];
        keyCalls = Array.isArray(days.key_calls)
            ? days.key_calls.map(function (v) { return parseInt(v, 10) || 0; })
            : (Array.isArray(days.calls) ? days.calls.map(function (v) { return parseInt(v, 10) || 0; }) : []);
        pointsCalls = Array.isArray(days.points_calls)
            ? days.points_calls.map(function (v) { return parseInt(v, 10) || 0; })
            : keyCalls.map(function () { return 0; });
        successRates = Array.isArray(days.success_rate)
            ? days.success_rate.map(function (v) { return Math.round(parseFloat(v) * 100) / 100 || 0; })
            : [];
        failRates = Array.isArray(days.fail_rate)
            ? days.fail_rate.map(function (v) { return Math.round(parseFloat(v) * 100) / 100 || 0; })
            : successRates.map(function () { return 0; });
        renderCharts();
        renderTop(s7.top);
        renderRecent(stats);
    }

    function stopBtn(btn) {
        if (!btn) return;
        if (window.VsRefreshBtn) {
            VsRefreshBtn.stop(btn);
        } else {
            btn.classList.remove('is-spinning');
            btn.removeAttribute('aria-busy');
            btn.removeAttribute('aria-disabled');
        }
    }

    function startBtn(btn) {
        if (!btn) return;
        if (window.VsRefreshBtn) {
            VsRefreshBtn.start(btn);
        } else {
            btn.classList.add('is-spinning');
            btn.setAttribute('aria-busy', 'true');
            btn.setAttribute('aria-disabled', 'true');
        }
    }

    function fetchLive(forceRefresh) {
        if (liveInFlight) return;
        if (!forceRefresh && !pageVisible()) return;
        var btn = document.getElementById('ucDashRefreshBtn');
        if (forceRefresh && btn && window.VsRefreshBtn && VsRefreshBtn.isBusy(btn)) {
            return;
        }
        liveInFlight = true;
        if (forceRefresh) {
            startBtn(btn);
        }
        var fd = new FormData();
        fd.append('action', forceRefresh ? 'refresh' : 'live');
        VS.postForm(fd).then(function (res) {
            if (!res || Number(res.code) !== 1 || !res.stats) {
                if (forceRefresh) {
                    VS.showMessage((res && res.msg) || '刷新失败', 'error');
                }
                return;
            }
            applyStats(res.stats);
            if (forceRefresh) {
                VS.showMessage('已刷新', 'success');
            }
        }).catch(function () {
            if (forceRefresh) {
                VS.showMessage('网络异常', 'error');
            }
        }).then(function () {
            liveInFlight = false;
            if (forceRefresh) {
                stopBtn(btn);
            }
        });
    }

    function scheduleLive() {
        if (liveTimer) {
            clearInterval(liveTimer);
            liveTimer = null;
        }
        liveTimer = setInterval(function () {
            fetchLive(false);
        }, LIVE_MS);
    }

    renderCharts();

    var refreshBtn = document.getElementById('ucDashRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            fetchLive(true);
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (pageVisible()) {
            fetchLive(false);
            scheduleLive();
        } else if (liveTimer) {
            clearInterval(liveTimer);
            liveTimer = null;
        }
    });

    if (pageVisible()) {
        scheduleLive();
    }
})();
