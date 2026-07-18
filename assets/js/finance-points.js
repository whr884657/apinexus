/**
 * 文件：assets/js/finance-points.js
 * 作用：管理员积分变动列表
 */
(function () {
    'use strict';
    var body = document.getElementById('pointsListBody');
    var footer = document.getElementById('pointsFooter');
    var pagerNav = document.getElementById('pointsPagerNav');
    var totalEl = document.getElementById('pointsTotal');
    var pageSizeEl = document.getElementById('pointsPageSize');
    var page = 1;

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
        if (!body || !window.VS) {
            return;
        }
        var pagesize = getPageSize();
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            var list = data.list || [];
            var total = data.total || 0;
            if (!list.length) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">暂无积分变动</p>';
            } else {
                body.innerHTML = '<div class="vs-finance-list">' + list.map(function (row) {
                    var sign = row.direct === 1 ? '+' : '-';
                    var cls = row.direct === 1 ? 'is-inc' : 'is-dec';
                    var detail = '';
                    if (row.direct === 0 && row.kind === 0) {
                        detail = [row.apiname, row.keymask].filter(Boolean).join(' · ');
                    } else if (row.remark) {
                        detail = row.remark;
                    } else if (parseFloat(row.money) > 0) {
                        detail = '¥' + row.money;
                    }
                    return '<article class="vs-finance-card vs-finance-card--ledger">'
                        + '<div class="vs-finance-card__top">'
                        + '<div class="vs-finance-card__main">'
                        + '<span class="vs-ledger-kind">' + escapeHtml(row.kind_label) + '</span>'
                        + '<strong class="vs-finance-card__user">' + escapeHtml(row.username || ('#' + row.userid)) + '</strong>'
                        + '</div>'
                        + '<span class="vs-ledger-amount ' + cls + '">' + sign + escapeHtml(row.amount) + '</span>'
                        + '</div>'
                        + '<div class="vs-finance-card__meta">'
                        + '<span>余额 ' + escapeHtml(row.balance) + '</span>'
                        + (detail ? '<span>' + escapeHtml(detail) + '</span>' : '')
                        + '</div>'
                        + '<div class="vs-finance-card__time">' + escapeHtml(row.createtime) + '</div>'
                        + '</article>';
                }).join('') + '</div>';
            }
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
        }).catch(function () {
            body.innerHTML = '<p class="vs-empty vs-finance-empty">网络异常</p>';
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
    var refresh = document.getElementById('pointsRefreshBtn');
    if (refresh) {
        refresh.addEventListener('click', load);
    }
    load();
})();
