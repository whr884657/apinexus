/**
 * 用户调用日志（keyset；IP+归属地卡片；双主题共用逻辑副本）
 */
(function () {
    'use strict';
    var body = document.getElementById('userLogsBody');
    var pagerNav = document.getElementById('userLogsPagerNav');
    var totalEl = document.getElementById('userLogsTotal');
    var footer = document.getElementById('userLogsFooter');
    var pageSizeEl = document.getElementById('userLogsPageSize');
    var pageRoot = document.getElementById('userLogsPage');

    if (!body || !pageRoot) {
        return;
    }

    var page = 1;
    var cursorStack = [0];
    var nextBeforeId = 0;
    var hasMore = false;
    var totalCount = 0;
    var totalApprox = false;
    var loadSeq = 0;
    var listAbort = null;
    var okFilter = '';

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

    function resetCursors() {
        page = 1;
        cursorStack = [0];
        nextBeforeId = 0;
        hasMore = false;
    }

    function setControlsDisabled(disabled) {
        if (pageSizeEl) pageSizeEl.disabled = !!disabled;
    }

    function renderPager() {
        if (footer) footer.hidden = false;
        if (totalEl) {
            var label = '共 ' + (totalCount || 0) + ' 条';
            if (totalApprox) {
                label += '（约）';
            }
            totalEl.textContent = label;
        }
        if (pagerNav) {
            pagerNav.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"'
                + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                + '<span class="vs-api-pager__info">' + page + '</span>'
                + '<button type="button" class="vs-api-pager__nav" data-p="1"'
                + (!hasMore ? ' disabled' : '') + '>下一页</button>';
        }
    }

    function cardHtml(row, index) {
        var ip = row.ip ? String(row.ip) : '—';
        var loc = row.iploc ? String(row.iploc) : '';
        var delay = Math.min(index, 12) * 0.035;
        return '<article class="uc-log-card uc-motion" style="--uc-log-delay:' + delay + 's">'
            + '<div class="uc-log-card__top">'
            + '<strong class="uc-log-card__name" title="' + escapeHtml(row.apiname || '') + '">'
            + escapeHtml(row.apiname || '—') + '</strong>'
            + '<span class="uc-log-card__ok ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label || '') + '</span>'
            + '</div>'
            + '<div class="uc-log-card__meta">'
            + '<time class="uc-log-card__time">' + escapeHtml(row.createtime || '—') + '</time>'
            + '</div>'
            + '<div class="uc-log-card__ip">'
            + '<span class="uc-log-card__ip-addr" title="' + escapeHtml(ip) + '">' + escapeHtml(ip) + '</span>'
            + (loc
                ? ('<span class="uc-log-card__ip-loc" title="' + escapeHtml(loc) + '">' + escapeHtml(loc) + '</span>')
                : '<span class="uc-log-card__ip-loc is-empty">归属地暂无</span>')
            + '</div>'
            + '</article>';
    }

    function renderList(list) {
        if (!list || !list.length) {
            body.innerHTML = '<p class="uc-logs__empty">暂无调用记录</p>';
            return;
        }
        body.innerHTML = '<div class="uc-logs__cards">'
            + list.map(cardHtml).join('')
            + '</div>';
    }

    function load() {
        if (!window.VS) {
            setTimeout(load, 40);
            return;
        }
        if (listAbort) {
            try { listAbort.abort(); } catch (e) { /* ignore */ }
        }
        listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var seq = ++loadSeq;
        var pagesize = getPageSize();
        var beforeId = cursorStack[page - 1] || 0;
        setControlsDisabled(true);
        if (VS.setLoading) {
            VS.setLoading(body, '正在加载日志');
        } else {
            body.innerHTML = '<div class="vs-loading">正在加载日志…</div>';
        }

        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        fd.append('before_id', String(beforeId));
        if (okFilter === '0' || okFilter === '1') {
            fd.append('ok', okFilter);
        }

        var opts = listAbort ? { signal: listAbort.signal } : {};
        VS.postForm(fd, window.location.href, opts).then(function (data) {
            if (seq !== loadSeq) return;
            setControlsDisabled(false);
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="uc-logs__empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            nextBeforeId = parseInt(data.next_before_id, 10) || 0;
            hasMore = !!data.has_more;
            totalCount = parseInt(data.total, 10) || 0;
            totalApprox = !!data.total_approx;
            if (cursorStack.length === page) {
                cursorStack.push(nextBeforeId);
            } else {
                cursorStack[page] = nextBeforeId;
            }
            renderList(data.list || []);
            renderPager();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') return;
            if (seq !== loadSeq) return;
            setControlsDisabled(false);
            body.innerHTML = '<p class="uc-logs__empty">加载失败，请稍后重试</p>';
        });
    }

    if (pagerNav) {
        pagerNav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-p]');
            if (!btn || btn.disabled) return;
            var dir = parseInt(btn.getAttribute('data-p'), 10);
            if (dir < 0 && page > 1) {
                page -= 1;
                load();
            } else if (dir > 0 && hasMore) {
                page += 1;
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

    pageRoot.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ok-filter]');
        if (!btn || !pageRoot.contains(btn)) return;
        var val = btn.getAttribute('data-ok-filter');
        if (val === null) return;
        okFilter = val;
        var nodes = pageRoot.querySelectorAll('[data-ok-filter]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].classList.toggle('is-active', nodes[i] === btn);
        }
        resetCursors();
        load();
    });

    load();
})();
