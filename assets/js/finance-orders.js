/**
 * 文件：assets/js/finance-orders.js
 * 作用：管理员充值订单列表
 */
(function () {
    'use strict';
    var body = document.getElementById('ordersListBody');
    var footer = document.getElementById('ordersFooter');
    var pagerNav = document.getElementById('ordersPagerNav');
    var totalEl = document.getElementById('ordersTotal');
    var pageSizeEl = document.getElementById('ordersPageSize');
    var page = 1;
    var status = '';

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
        if (status !== '') {
            fd.append('status', status);
        }
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            var list = data.list || [];
            var total = data.total || 0;
            if (!list.length) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">暂无充值订单</p>';
            } else {
                body.innerHTML = '<div class="vs-finance-list">' + list.map(function (row) {
                    return '<article class="vs-finance-card">'
                        + '<div class="vs-finance-card__top">'
                        + '<div class="vs-finance-card__id">'
                        + '<strong class="vs-finance-mono">' + escapeHtml(row.orderno) + '</strong>'
                        + (row.tradeno ? '<span class="vs-finance-sub">平台 ' + escapeHtml(row.tradeno) + '</span>' : '')
                        + '</div>'
                        + '<span class="vs-order-status ' + escapeHtml(row.status_class || '') + '">'
                        + escapeHtml(row.status_label) + '</span>'
                        + '</div>'
                        + '<div class="vs-finance-card__meta">'
                        + '<span>' + escapeHtml(row.username || ('#' + row.userid)) + '</span>'
                        + '<span>' + escapeHtml(row.pay_label || '—') + '</span>'
                        + '<span>¥' + escapeHtml(row.money) + '</span>'
                        + '<span class="vs-ledger-amount is-inc">+' + escapeHtml(row.amount) + '</span>'
                        + '</div>'
                        + '<div class="vs-finance-card__time">' + escapeHtml(row.createtime)
                        + (row.paytime ? ' · 支付 ' + escapeHtml(row.paytime) : '')
                        + '</div>'
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

    document.querySelectorAll('.vs-finance-filter').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.vs-finance-filter').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
                el.classList.toggle('vs-btn--primary', el === btn);
                el.classList.toggle('vs-btn--default', el !== btn);
            });
            status = btn.getAttribute('data-status') || '';
            page = 1;
            load();
        });
    });

    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            page = 1;
            load();
        });
    }

    var refresh = document.getElementById('orderRefreshBtn');
    if (refresh) {
        refresh.addEventListener('click', load);
    }
    load();
})();
