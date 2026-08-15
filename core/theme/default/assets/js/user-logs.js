/**
 * 用户调用日志：电脑端列表 / 手机端紧凑卡片（IP+归属同行，时间右下）
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

    function ipLocHtml(row) {
        var ip = row.ip ? String(row.ip) : '—';
        var loc = row.iploc ? String(row.iploc) : '';
        return '<span class="uc-log-ip" title="' + escapeHtml(ip + (loc ? (' ' + loc) : '')) + '">'
            + '<span class="uc-log-ip__addr">' + escapeHtml(ip) + '</span>'
            + (loc
                ? ('<span class="uc-log-ip__loc">' + escapeHtml(loc) + '</span>')
                : '<span class="uc-log-ip__loc is-empty">归属地暂无</span>')
            + '</span>';
    }

    function desktopRowHtml(row) {
        return '<div class="uc-log-row">'
            + '<div class="uc-log-row__name" title="' + escapeHtml(row.apiname || '') + '">'
            + escapeHtml(row.apiname || '—') + '</div>'
            + '<div class="uc-log-row__ip">' + ipLocHtml(row) + '</div>'
            + '<div class="uc-log-row__ok"><span class="uc-log-ok ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label || '') + '</span></div>'
            + '<div class="uc-log-row__time">' + escapeHtml(row.createtime || '—') + '</div>'
            + '</div>';
    }

    function cardHtml(row, index) {
        var delay = Math.min(index, 12) * 0.035;
        return '<article class="uc-log-card" style="--uc-log-delay:' + delay + 's">'
            + '<div class="uc-log-card__top">'
            + '<strong class="uc-log-card__name" title="' + escapeHtml(row.apiname || '') + '">'
            + escapeHtml(row.apiname || '—') + '</strong>'
            + '<span class="uc-log-ok ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label || '') + '</span>'
            + '</div>'
            + '<div class="uc-log-card__bottom">'
            + ipLocHtml(row)
            + '<time class="uc-log-card__time">' + escapeHtml(row.createtime || '—') + '</time>'
            + '</div>'
            + '</article>';
    }

    function renderList(list) {
        if (!list || !list.length) {
            body.innerHTML = '<p class="uc-logs__empty">暂无调用记录</p>';
            return;
        }
        var head = '<div class="uc-log-row uc-log-row--head" role="presentation">'
            + '<div>接口</div><div>IP / 归属</div><div>状态</div><div>时间</div></div>';
        body.innerHTML = '<div class="uc-logs__desktop">' + head + list.map(desktopRowHtml).join('') + '</div>'
            + '<div class="uc-logs__mobile">' + list.map(cardHtml).join('') + '</div>';
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
                body.innerHTML = '<p class="uc-logs__empty">'
                    + escapeHtml((data && data.msg) || '加载失败') + '</p>';
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
            body.innerHTML = '<p class="uc-logs__empty">网络异常</p>';
        });
    }

    pageRoot.querySelectorAll('[data-ok-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pageRoot.querySelectorAll('[data-ok-filter]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            okFilter = btn.getAttribute('data-ok-filter') || '';
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

    if (pagerNav) {
        pagerNav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-p]');
            if (!btn || btn.disabled) return;
            var delta = parseInt(btn.getAttribute('data-p'), 10) || 0;
            var next = page + delta;
            if (next < 1) return;
            if (delta > 0 && !hasMore) return;
            page = next;
            load();
        });
    }

    load();
})();
