/**
 * 文件：assets/js/user-points.js
 */
(function () {
    'use strict';
    var body = document.getElementById('pointsListBody');
    if (!body || !window.VS) {
        return;
    }
    var page = 1;
    var pager = document.getElementById('pointsPager');
    var totalEl = document.getElementById('pointsTotal');
    var footer = document.getElementById('pointsFooter');

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function load() {
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty" style="padding:24px;text-align:center;">加载失败</p>';
                return;
            }
            if (data.balance != null) {
                var bal = document.getElementById('pointsBalance');
                if (bal) {
                    bal.textContent = data.balance;
                }
            }
            var list = data.list || [];
            if (!list.length) {
                body.innerHTML = '<p class="vs-empty" style="padding:24px;text-align:center;">暂无记录</p>';
            } else {
                body.innerHTML = list.map(function (row) {
                    var sign = row.direct === 1 ? '+' : '-';
                    var detail = row.kind_label;
                    if (row.direct === 0 && row.kind === 0) {
                        detail += (row.apiname ? (' · ' + row.apiname) : '');
                        detail += (row.keymask ? (' · ' + row.keymask) : '');
                    }
                    return '<div style="display:flex;justify-content:space-between;gap:12px;padding:12px 8px;border-bottom:1px solid #f1f5f9;">'
                        + '<div><div style="font-weight:600;">' + escapeHtml(detail) + '</div>'
                        + '<div style="font-size:12px;color:#64748b;margin-top:4px;">' + escapeHtml(row.createtime) + ' · ' + escapeHtml(row.orderno) + '</div></div>'
                        + '<div style="text-align:right;"><div style="font-weight:700;color:' + (row.direct === 1 ? '#16a34a' : '#dc2626') + ';">'
                        + sign + escapeHtml(row.amount) + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">余额 ' + escapeHtml(row.balance) + '</div></div></div>';
                }).join('');
            }
            var total = data.total || 0;
            if (footer) {
                footer.hidden = false;
            }
            if (totalEl) {
                totalEl.textContent = '共 ' + total + ' 条';
            }
            if (pager) {
                var pages = Math.max(1, Math.ceil(total / 20));
                pager.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                    + '<span class="vs-api-pager__info">' + page + ' / ' + pages + '</span>'
                    + '<button type="button" class="vs-api-pager__nav" data-p="1"' + (page >= pages ? ' disabled' : '') + '>下一页</button>';
            }
        });
    }

    if (pager) {
        pager.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-p]');
            if (!btn || btn.disabled) {
                return;
            }
            page += parseInt(btn.getAttribute('data-p'), 10) || 0;
            if (page < 1) {
                page = 1;
            }
            load();
        });
    }
    load();
})();
