/**
 * Slate 主题 · 用户控制台近 7 日折线图（青绿系）
 */
(function () {
    'use strict';

    var page = document.getElementById('ucDashboard');
    if (!page) return;

    var COLORS = { calls: '#24a66a', cost: '#c9a227' };
    var boot = {};
    try {
        boot = JSON.parse(page.getAttribute('data-chart-boot') || '{}') || {};
    } catch (e) {
        boot = {};
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

    function lineChart(el, labels, values, color, unit) {
        if (!el) return;
        labels = Array.isArray(labels) ? labels : [];
        values = Array.isArray(values) ? values : [];
        if (!values.length) {
            el.innerHTML = '<div class="uc-dash__chart-empty">暂无趋势数据</div>';
            return;
        }
        var w = 560, h = 200, L = 36, R = 10, T = 14, B = 26;
        var plotTop = T, plotBottom = h - B;
        var max = Math.max.apply(null, values.concat([1]));
        var min = 0;
        var span = Math.max(0.0001, max - min);
        var n = Math.max(1, labels.length || values.length);
        function xAt(i) { return L + (i * (w - L - R)) / Math.max(1, n - 1); }
        function yAt(v) { return T + (1 - (v - min) / span) * (h - T - B); }
        var grid = '';
        var g;
        for (g = 0; g < 4; g++) {
            var gy = T + g * (h - T - B) / 3;
            grid += '<line x1="' + L + '" y1="' + gy + '" x2="' + (w - R) + '" y2="' + gy + '" stroke="rgba(36,166,106,0.15)" stroke-width="1"/>';
        }
        var pts = values.map(function (v, i) {
            return { x: xAt(i), y: yAt(v) };
        });
        var path = smoothPath(pts, plotTop, plotBottom);
        var xLabels = '';
        labels.forEach(function (lb, i) {
            if (n > 8 && i % 2 === 1 && i !== n - 1) return;
            xLabels += '<text x="' + xAt(i) + '" y="' + (h - 8) + '" text-anchor="middle" fill="#5a6072" font-size="11">' + esc(lb) + '</text>';
        });
        el.innerHTML = '<div class="uc-dash__chart-canvas">'
            + '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img">'
            + grid
            + '<path d="' + path + '" fill="none" stroke="' + color + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
            + xLabels
            + '<line class="uc-dash__chart-guide" x1="0" y1="' + plotTop + '" x2="0" y2="' + plotBottom + '" stroke="#5a6072" stroke-width="1" stroke-dasharray="3 3" opacity="0"></line>'
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
            var v = values[idx] != null ? values[idx] : 0;
            var lb = labels[idx] != null ? labels[idx] : '';
            var x = xAt(idx);
            var y = yAt(v);
            tip.hidden = false;
            tip.innerHTML = '<div class="uc-dash__chart-tip-title">' + esc(lb) + '</div>'
                + '<div class="uc-dash__chart-tip-row"><i style="background:' + color + '"></i><b>'
                + esc(String(v)) + esc(unit || '') + '</b></div>';
            tip.style.left = Math.min(Math.max(8, x - 40), w - 100) + 'px';
            tip.style.top = '8px';
            if (guide) {
                guide.setAttribute('x1', String(x));
                guide.setAttribute('x2', String(x));
                guide.setAttribute('opacity', '1');
            }
            if (dotsG) {
                dotsG.innerHTML = '<circle cx="' + x + '" cy="' + y + '" r="4" fill="#fff" stroke="' + color + '" stroke-width="2"/>';
            }
        }

        svg.addEventListener('mousemove', function (ev) {
            var rect = svg.getBoundingClientRect();
            var px = ((ev.clientX - rect.left) / rect.width) * w;
            showAt(nearestIndex(px));
        });
        svg.addEventListener('mouseleave', hideTip);
    }

    lineChart(
        document.getElementById('ucDashCallsChart'),
        boot.labels || [],
        boot.calls || [],
        COLORS.calls,
        ' 次'
    );
    lineChart(
        document.getElementById('ucDashCostChart'),
        boot.labels || [],
        boot.cost || [],
        COLORS.cost,
        ''
    );
})();
