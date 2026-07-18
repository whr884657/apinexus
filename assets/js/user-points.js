/**
 * 文件：assets/js/user-points.js
 * 作用：用户积分变动列表
 */
(function () {
    'use strict';
    var body = document.getElementById('pointsListBody');
    if (!body || !window.VS) {
        return;
    }
    var page = 1;
    var pagerNav = document.getElementById('pointsPagerNav');
    var totalEl = document.getElementById('pointsTotal');
    var footer = document.getElementById('pointsFooter');
    var pageSizeEl = document.getElementById('userPointsPageSize');

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if (!n || n < 1) {
            n = 20;
        }
        return Math.min(50, n);
    }

    function load() {
        var pagesize = getPageSize();
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">加载失败</p>';
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
                body.innerHTML = '<p class="vs-empty vs-finance-empty">暂无记录</p>';
            } else {
                body.innerHTML = list.map(function (row) {
                    var sign = row.direct === 1 ? '+' : '-';
                    var cls = row.direct === 1 ? 'is-inc' : 'is-dec';
                    var detail = row.kind_label;
                    if (row.direct === 0 && row.kind === 0) {
                        detail += (row.apiname ? (' · ' + row.apiname) : '');
                    } else if (row.remark) {
                        detail += ' · ' + row.remark;
                    }
                    return '<div class="vs-points-item">'
                        + '<div class="vs-points-item__main">'
                        + '<div class="vs-points-item__title">' + escapeHtml(detail) + '</div>'
                        + '<div class="vs-points-item__meta">' + escapeHtml(row.createtime) + '</div>'
                        + '</div>'
                        + '<div class="vs-points-item__side">'
                        + '<div class="vs-ledger-amount ' + cls + '">' + sign + escapeHtml(row.amount) + '</div>'
                        + '<div class="vs-points-item__bal">余额 ' + escapeHtml(row.balance) + '</div>'
                        + '</div></div>';
                }).join('');
            }
            var total = data.total || 0;
            if (footer) {
                footer.hidden = false;
            }
            if (totalEl) {
                totalEl.textContent = '共 ' + total + ' 条';
            }
            if (pagerNav) {
                var pages = Math.max(1, Math.ceil(total / pagesize));
                pagerNav.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                    + '<span class="vs-api-pager__info">' + page + ' / ' + pages + '</span>'
                    + '<button type="button" class="vs-api-pager__nav" data-p="1"' + (page >= pages ? ' disabled' : '') + '>下一页</button>';
            }
        });
    }

    if (pagerNav) {
        pagerNav.addEventListener('click', function (e) {
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
    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            page = 1;
            load();
        });
    }
    load();
})();
