/**
 * 默认主题 · 404 动效（终端打字 / 状态芯片）
 */
(function () {
    var cfg = window.__DF404__ || {};
    var term = document.getElementById('df404Term');
    var back = document.getElementById('df404Back');
    var status = document.querySelector('[data-df404-status]');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (back) {
        back.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = cfg.home || '/';
            }
        });
    }

    var lines = [
        { cls: 'dim', text: '$ vsctl route inspect --uri ' + (cfg.path || '/') },
        { cls: 'err', text: 'error: target resource not mapped (HTTP 404)' },
        { cls: 'dim', text: '$ vsctl audit hint --scope public' },
        { cls: 'ok', text: 'hint: use official navigation; probing may be logged' },
        { cls: 'dim', text: '$ echo "' + String(cfg.heading || '页面不存在').replace(/"/g, '\\"') + '"' },
        { cls: 'ok', text: 'ready · return home or browse APIs' }
    ];

    function paintInstant() {
        if (!term) return;
        term.innerHTML = lines.map(function (row) {
            return '<span class="' + row.cls + '">' + escapeHtml(row.text) + '</span>';
        }).join('\n');
        if (status) status.textContent = 'SAFE_IDLE';
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function typeLines() {
        if (!term) return;
        var i = 0;
        var html = '';

        function nextLine() {
            if (i >= lines.length) {
                if (status) status.textContent = 'SAFE_IDLE';
                return;
            }
            var row = lines[i++];
            var j = 0;
            var buf = '';
            function tick() {
                buf += row.text.charAt(j++);
                term.innerHTML = html + '<span class="' + row.cls + '">' + escapeHtml(buf) + '</span><span class="dim">▍</span>';
                if (j < row.text.length) {
                    window.setTimeout(tick, 12 + Math.random() * 18);
                } else {
                    html += '<span class="' + row.cls + '">' + escapeHtml(row.text) + '</span>\n';
                    term.innerHTML = html;
                    window.setTimeout(nextLine, 180);
                }
            }
            tick();
        }
        nextLine();
    }

    if (reduce) {
        paintInstant();
    } else {
        typeLines();
    }
})();
