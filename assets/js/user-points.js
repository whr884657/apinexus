/**
 * 文件：assets/js/user-points.js
 * 作用：用户积分变动列表（时间窗 / keyset / Abort）
 */
(function () {
    'use strict';
    var body = document.getElementById('pointsListBody');
    if (!body || !window.VS) {
        return;
    }
    var pagerNav = document.getElementById('pointsPagerNav');
    var totalEl = document.getElementById('pointsTotal');
    var footer = document.getElementById('pointsFooter');
    var pageSizeEl = document.getElementById('userPointsPageSize');
    var daysEl = document.getElementById('userPointsDays');

    var page = 1;
    var days = parseInt(body.getAttribute('data-default-days'), 10) || 30;
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
        if (daysEl) daysEl.disabled = !!disabled;
        if (pageSizeEl) pageSizeEl.disabled = !!disabled;
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
        if (loading) return;
        if (listAbort) {
            try { listAbort.abort(); } catch (e) { /* ignore */ }
        }
        listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var pagesize = getPageSize();
        var beforeId = cursorStack[page - 1] || 0;
        loading = true;
        setControlsDisabled(true);
        if (VS.setLoading) VS.setLoading(body, '正在加载积分变动');

        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        fd.append('days', String(getDays()));
        fd.append('before_id', String(beforeId));

        var opts = listAbort ? { signal: listAbort.signal } : {};
        VS.postForm(fd, window.location.href, opts).then(function (data) {
            loading = false;
            setControlsDisabled(false);
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">加载失败</p>';
                return;
            }
            if (data.balance != null) {
                var bal = document.getElementById('pointsBalance');
                if (bal) bal.textContent = data.balance;
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
    load();
})();
