/**
 * 文件：assets/js/finance-orders.js
 * 作用：管理员充值订单列表（时间窗 / keyset / Abort）
 */
(function () {
    'use strict';
    var pageRoot = document.getElementById('ordersPage');
    var body = document.getElementById('ordersListBody');
    var footer = document.getElementById('ordersFooter');
    var pagerNav = document.getElementById('ordersPagerNav');
    var totalEl = document.getElementById('ordersTotal');
    var pageSizeEl = document.getElementById('ordersPageSize');
    var daysEl = document.getElementById('ordersDays');
    var refreshBtn = document.getElementById('orderRefreshBtn');

    var page = 1;
    var status = '';
    var days = pageRoot ? (parseInt(pageRoot.getAttribute('data-default-days'), 10) || 30) : 30;
    var cursorStack = [0];
    var nextBeforeId = 0;
    var hasMore = false;
    var loading = false;
    var listAbort = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if (!n || n < 1) n = 20;
        return Math.min(50, n);
    }

    function getDays() {
        var n = daysEl ? parseInt(daysEl.value, 10) : days;
        if (!n || n < 1) n = days;
        return Math.min(365, n);
    }

    function resetCursors() {
        page = 1;
        cursorStack = [0];
        nextBeforeId = 0;
        hasMore = false;
    }

    function setControlsDisabled(disabled) {
        if (refreshBtn) refreshBtn.disabled = !!disabled;
        if (daysEl) daysEl.disabled = !!disabled;
        if (pageSizeEl) pageSizeEl.disabled = !!disabled;
        document.querySelectorAll('.vs-finance-filter').forEach(function (btn) {
            btn.disabled = !!disabled;
        });
    }

    function headHtml() {
        return '<div class="vs-finance-row vs-finance-row--order vs-finance-row--head" aria-hidden="true">'
            + '<div class="vs-finance-cell">订单号</div>'
            + '<div class="vs-finance-meta">'
            + '<div class="vs-finance-cell">用户</div>'
            + '<div class="vs-finance-cell">支付</div>'
            + '<div class="vs-finance-cell">金额</div>'
            + '<div class="vs-finance-cell">积分</div>'
            + '</div>'
            + '<div class="vs-finance-cell">状态</div>'
            + '<div class="vs-finance-cell">时间</div>'
            + '</div>';
    }

    function rowHtml(row) {
        return '<article class="vs-finance-row vs-finance-row--order">'
            + '<div class="vs-finance-cell vs-finance-cell--stack vs-finance-c-id">'
            + '<strong class="vs-finance-mono">' + escapeHtml(row.orderno) + '</strong>'
            + (row.tradeno ? '<span class="vs-finance-sub">平台 ' + escapeHtml(row.tradeno) + '</span>' : '')
            + '</div>'
            + '<div class="vs-finance-meta">'
            + '<div class="vs-finance-cell vs-finance-c-user">'
            + '<span class="vs-finance-m-label">用户</span>'
            + '<span class="vs-finance-user">' + escapeHtml(row.username || ('#' + row.userid)) + '</span>'
            + '</div>'
            + '<div class="vs-finance-cell vs-finance-c-pay">'
            + '<span class="vs-finance-m-label">支付</span>'
            + escapeHtml(row.pay_label || '—')
            + '</div>'
            + '<div class="vs-finance-cell vs-finance-c-money">'
            + '<span class="vs-finance-m-label">金额</span>¥' + escapeHtml(row.money)
            + '</div>'
            + '<div class="vs-finance-cell vs-finance-c-points">'
            + '<span class="vs-finance-m-label">积分</span>'
            + '<span class="vs-ledger-amount is-inc">+' + escapeHtml(row.amount) + '</span>'
            + '</div>'
            + '</div>'
            + '<div class="vs-finance-cell vs-finance-c-status">'
            + '<span class="vs-order-status ' + escapeHtml(row.status_class || '') + '">'
            + escapeHtml(row.status_label) + '</span>'
            + '</div>'
            + '<div class="vs-finance-cell vs-finance-c-time vs-finance-time">'
            + escapeHtml(row.createtime)
            + (row.paytime ? '<br><span class="vs-finance-sub">支付 ' + escapeHtml(row.paytime) + '</span>' : '')
            + '</div>'
            + '</article>';
    }

    function renderPager(total) {
        if (footer) footer.hidden = false;
        if (totalEl) totalEl.textContent = '共 ' + total + ' 条（近 ' + getDays() + ' 天）';
        if (pagerNav) {
            pagerNav.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                + '<span class="vs-api-pager__info">' + page + '</span>'
                + '<button type="button" class="vs-api-pager__nav" data-p="1"' + (!hasMore ? ' disabled' : '') + '>下一页</button>';
        }
    }

    function load() {
        if (!body || !window.VS || loading) return;
        if (listAbort) {
            try { listAbort.abort(); } catch (e) { /* ignore */ }
        }
        listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var pagesize = getPageSize();
        var beforeId = cursorStack[page - 1] || 0;
        loading = true;
        setControlsDisabled(true);
        if (VS.setLoading) VS.setLoading(body, '正在加载订单');

        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        fd.append('days', String(getDays()));
        fd.append('before_id', String(beforeId));
        if (status !== '') fd.append('status', status);

        var opts = listAbort ? { signal: listAbort.signal } : {};
        VS.postForm(fd, window.location.href, opts).then(function (data) {
            loading = false;
            setControlsDisabled(false);
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            nextBeforeId = parseInt(data.next_before_id, 10) || 0;
            hasMore = !!data.has_more;
            if (cursorStack.length === page) {
                cursorStack.push(nextBeforeId);
            } else {
                cursorStack[page] = nextBeforeId;
            }
            var list = data.list || [];
            if (!list.length) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">暂无充值订单</p>';
            } else {
                body.innerHTML = '<div class="vs-finance-table-wrap"><div class="vs-finance-grid">'
                    + headHtml() + list.map(rowHtml).join('') + '</div></div>';
            }
            renderPager(data.total || 0);
        }).catch(function (err) {
            loading = false;
            setControlsDisabled(false);
            if (err && err.name === 'AbortError') return;
            body.innerHTML = '<p class="vs-empty vs-finance-empty">网络异常</p>';
        });
    }

    if (pagerNav) {
        pagerNav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-p]');
            if (!btn || btn.disabled || loading) return;
            var delta = parseInt(btn.getAttribute('data-p'), 10) || 0;
            if (delta > 0 && hasMore) {
                page += 1;
                load();
            } else if (delta < 0 && page > 1) {
                page -= 1;
                load();
            }
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
            resetCursors();
            load();
        });
    });

    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            resetCursors();
            load();
        });
    }
    if (daysEl) {
        daysEl.addEventListener('change', function () {
            resetCursors();
            load();
        });
    }
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            resetCursors();
            load();
        });
    }
    load();
})();
