/**
 * 文件：assets/js/finance-orders.js
 * 作用：管理员订单列表
 */
(function () {
    'use strict';
    var body = document.getElementById('ordersListBody');
    var footer = document.getElementById('ordersFooter');
    var pager = document.getElementById('ordersPager');
    var totalEl = document.getElementById('ordersTotal');
    var filter = document.getElementById('orderStatusFilter');
    var page = 1;
    var pagesize = 20;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function load() {
        if (!body || !window.VS) {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        if (filter && filter.value !== '') {
            fd.append('status', filter.value);
        }
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty" style="padding:24px;text-align:center;">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            var list = data.list || [];
            var total = data.total || 0;
            if (!list.length) {
                body.innerHTML = '<p class="vs-empty" style="padding:24px;text-align:center;">暂无订单</p>';
            } else {
                body.innerHTML = list.map(function (row) {
                    var extra = '';
                    if (row.direct === 0 && row.kind === 0) {
                        extra = '<div class="muted">' + escapeHtml(row.apiname || '') + (row.keymask ? (' · ' + row.keymask) : '') + '</div>';
                    }
                    var sign = row.direct === 1 ? '+' : '-';
                    return '<div class="vs-orders-row">'
                        + '<span data-label="订单号"><strong>' + escapeHtml(row.orderno) + '</strong>' + extra + '</span>'
                        + '<span data-label="用户">' + escapeHtml(row.username || ('#' + row.userid)) + '</span>'
                        + '<span data-label="类型">' + escapeHtml(row.kind_label) + '</span>'
                        + '<span data-label="变动">' + sign + escapeHtml(row.amount) + '</span>'
                        + '<span data-label="余额">' + escapeHtml(row.balance) + '</span>'
                        + '<span data-label="金额">' + (parseFloat(row.money) > 0 ? ('¥' + escapeHtml(row.money)) : '—') + '</span>'
                        + '<span data-label="状态">' + escapeHtml(row.status_label) + '</span>'
                        + '<span data-label="时间">' + escapeHtml(row.createtime) + '</span>'
                        + '</div>';
                }).join('');
            }
            if (footer) {
                footer.hidden = false;
            }
            if (totalEl) {
                totalEl.textContent = '共 ' + total + ' 条';
            }
            if (pager) {
                var pages = Math.max(1, Math.ceil(total / pagesize));
                pager.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                    + '<span class="vs-api-pager__info">' + page + ' / ' + pages + '</span>'
                    + '<button type="button" class="vs-api-pager__nav" data-p="1"' + (page >= pages ? ' disabled' : '') + '>下一页</button>';
            }
        }).catch(function () {
            body.innerHTML = '<p class="vs-empty" style="padding:24px;text-align:center;">网络异常</p>';
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
    if (filter) {
        filter.addEventListener('change', function () {
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
