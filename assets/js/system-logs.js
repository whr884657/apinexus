/**
 * 文件：assets/js/system-logs.js
 * 作用：管理员 API 调用日志（时间窗 / keyset 翻页 / Abort 防叠请求 / 抽屉详情）
 */
(function () {
    'use strict';

    var pageRoot = document.getElementById('logsPage');
    var body = document.getElementById('logsListBody');
    var footer = document.getElementById('logsFooter');
    var pagerNav = document.getElementById('logsPagerNav');
    var totalEl = document.getElementById('logsTotal');
    var pageSizeEl = document.getElementById('logsPageSize');
    var daysEl = document.getElementById('logsDays');
    var searchInput = document.getElementById('logsSearchInput');
    var overlay = document.getElementById('logsDetailOverlay');
    var detailBody = document.getElementById('logsDetailBody');
    var refreshBtn = document.getElementById('logsRefreshBtn');
    var searchBtn = document.getElementById('logsSearchBtn');

    var page = 1;
    var okFilter = '';
    var q = '';
    var days = pageRoot ? (parseInt(pageRoot.getAttribute('data-default-days'), 10) || 7) : 7;
    /** 每页进入时的 before_id；第 1 页为 0 */
    var cursorStack = [0];
    var nextBeforeId = 0;
    var hasMore = false;
    var loadSeq = 0;
    var listAbort = null;
    var returnFocusEl = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function isMobile() {
        return window.matchMedia('(max-width: 900px)').matches;
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if (!n || n < 1) {
            n = 20;
        }
        return Math.min(50, n);
    }

    function getDays() {
        var n = daysEl ? parseInt(daysEl.value, 10) : days;
        if (!n || n < 1) {
            n = days;
        }
        return Math.min(90, n);
    }

    function setControlsDisabled(disabled) {
        if (refreshBtn) {
            refreshBtn.disabled = !!disabled;
        }
        if (searchBtn) {
            searchBtn.disabled = !!disabled;
        }
        if (daysEl) {
            daysEl.disabled = !!disabled;
        }
        if (pageSizeEl) {
            pageSizeEl.disabled = !!disabled;
        }
        document.querySelectorAll('.vs-log-filter').forEach(function (btn) {
            btn.disabled = !!disabled;
        });
    }

    function resetCursors() {
        page = 1;
        cursorStack = [0];
        nextBeforeId = 0;
        hasMore = false;
    }

    function methodBadge(row) {
        return '<span class="vs-log-method ' + escapeHtml(row.method_class || 'is-other') + '">'
            + escapeHtml(row.method || '—') + '</span>';
    }

    function httpBadge(row) {
        return '<span class="vs-log-http ' + escapeHtml(row.http_class || '') + '">'
            + escapeHtml(row.httpcode) + '</span>';
    }

    function headHtml() {
        return '<div class="vs-log-row vs-log-row--head" aria-hidden="true">'
            + '<div class="vs-log-cell">接口</div>'
            + '<div class="vs-log-cell">方法</div>'
            + '<div class="vs-log-cell">IP / 归属地</div>'
            + '<div class="vs-log-cell">结果</div>'
            + '<div class="vs-log-cell">状态码</div>'
            + '<div class="vs-log-cell">时间</div>'
            + '<div class="vs-log-cell">操作</div>'
            + '</div>';
    }

    function rowHtml(row) {
        return '<article class="vs-log-row" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button">'
            + '<div class="vs-log-cell vs-log-c-name">'
            + '<strong>' + escapeHtml(row.apiname || ('#' + row.apiid)) + '</strong>'
            + '<span class="vs-log-sub">' + escapeHtml(row.path || '') + '</span>'
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-method">' + methodBadge(row) + '</div>'
            + '<div class="vs-log-cell vs-log-c-ip">'
            + '<span class="vs-log-mono">' + escapeHtml(row.ip || '—') + '</span>'
            + '<span class="vs-log-sub">' + escapeHtml(row.iploc !== undefined && row.iploc !== null && row.iploc !== '' ? row.iploc : '—') + '</span>'
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-ok">'
            + '<span class="vs-log-status ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label) + '</span>'
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-code">' + httpBadge(row) + '</div>'
            + '<div class="vs-log-cell vs-log-c-time">' + escapeHtml(row.createtime || '—') + '</div>'
            + '<div class="vs-log-cell vs-log-c-act"><span class="vs-log-view">查看</span></div>'
            + '</article>';
    }

    function cardHtml(row) {
        return '<article class="vs-log-card" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button">'
            + '<div class="vs-log-card__top">'
            + '<strong class="vs-log-card__name">' + escapeHtml(row.apiname || ('#' + row.apiid)) + '</strong>'
            + '<span class="vs-log-status ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label) + '</span>'
            + '</div>'
            + '<div class="vs-log-card__meta">'
            + methodBadge(row)
            + '<span class="vs-log-mono">' + escapeHtml(row.ip || '—') + '</span>'
            + '<span>' + escapeHtml(row.iploc !== undefined && row.iploc !== null && row.iploc !== '' ? row.iploc : '—') + '</span>'
            + httpBadge(row)
            + '</div>'
            + '<div class="vs-log-card__foot">'
            + '<span class="vs-log-card__time">' + escapeHtml(row.createtime || '—') + '</span>'
            + '<span class="vs-log-view">查看详情</span>'
            + '</div>'
            + '</article>';
    }

    function detailItem(label, value, full) {
        var v = value == null || value === '' ? '—' : String(value);
        return '<div class="vs-log-detail__item' + (full ? ' vs-log-detail__item--full' : '') + '">'
            + '<span class="vs-log-detail__label">' + escapeHtml(label) + '</span>'
            + '<span class="vs-log-detail__value">' + escapeHtml(v) + '</span>'
            + '</div>';
    }

    function detailHtml(row) {
        return '<div class="vs-log-detail">'
            + '<div class="vs-log-detail__hero">'
            + '<span class="vs-log-detail__hero-name">' + escapeHtml(row.apiname || ('接口 #' + row.apiid)) + '</span>'
            + methodBadge(row)
            + '<span class="vs-log-status ' + escapeHtml(row.ok_class || '') + '">' + escapeHtml(row.ok_label) + '</span>'
            + httpBadge(row)
            + '</div>'
            + '<div class="vs-log-detail__section">'
            + '<h4 class="vs-log-detail__section-title">调用信息</h4>'
            + '<div class="vs-log-detail__grid">'
            + detailItem('记录 ID', row.id)
            + detailItem('接口 ID', row.apiid)
            + detailItem('类型', row.apitype_label)
            + detailItem('时间', row.createtime)
            + detailItem('用户', row.user_label || (row.userid ? ('#' + row.userid) : '匿名'))
            + detailItem('密钥', row.apikey)
            + detailItem('扣费', (row.charged_label || '') + (row.charged ? (' · ' + row.cost) : ''))
            + '</div></div>'
            + '<div class="vs-log-detail__section">'
            + '<h4 class="vs-log-detail__section-title">网络与来源</h4>'
            + '<div class="vs-log-detail__grid">'
            + detailItem('IP', row.ip)
            + detailItem('IP 归属地', row.iploc)
            + detailItem('来源域名', row.domain)
            + detailItem('Host', row.host)
            + detailItem('路径', row.path, true)
            + detailItem('完整 URL', row.url, true)
            + detailItem('Referer', row.referer, true)
            + detailItem('Origin', row.origin, true)
            + detailItem('User-Agent', row.ua, true)
            + '</div></div>'
            + '</div>';
    }

    function openOverlay() {
        if (!overlay) {
            return;
        }
        returnFocusEl = document.activeElement;
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
    }

    function closeOverlay() {
        if (!overlay) {
            return;
        }
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-open');
        document.body.classList.remove('is-overlay-open');
        if (returnFocusEl && returnFocusEl.focus) {
            returnFocusEl.focus();
        }
        returnFocusEl = null;
    }

    function openDetail(id) {
        if (!detailBody || !window.VS) {
            return;
        }
        detailBody.innerHTML = (window.VS && VS.loadingHtml)
            ? VS.loadingHtml('正在加载详情', true)
            : '<p class="vs-empty">正在加载</p>';
        openOverlay();
        var fd = new FormData();
        fd.append('action', 'detail');
        fd.append('id', String(id));
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1 || !data.row) {
                detailBody.innerHTML = '<p class="vs-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            detailBody.innerHTML = detailHtml(data.row);
        }).catch(function () {
            detailBody.innerHTML = '<p class="vs-empty">网络异常</p>';
        });
    }

    function renderPager(total, pagesize) {
        if (footer) {
            footer.hidden = false;
        }
        if (totalEl) {
            var label = '共 ' + total + ' 条';
            if (total && pageRoot && pageRoot.getAttribute('data-total-approx') === '1') {
                label += '（近期）';
            }
            totalEl.textContent = label;
        }
        if (pagerNav) {
            pagerNav.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                + '<span class="vs-api-pager__info">' + page + '</span>'
                + '<button type="button" class="vs-api-pager__nav" data-p="1"' + (!hasMore ? ' disabled' : '') + '>下一页</button>';
        }
    }

    function renderList(list, total, pagesize) {
        if (!body) {
            return;
        }
        if (!list.length) {
            body.innerHTML = '<p class="vs-empty vs-finance-empty">暂无调用记录</p>';
        } else if (isMobile()) {
            body.innerHTML = '<div class="vs-log-cards">' + list.map(cardHtml).join('') + '</div>';
        } else {
            body.innerHTML = '<div class="vs-log-table-wrap"><div class="vs-log-grid">'
                + headHtml()
                + list.map(rowHtml).join('')
                + '</div></div>';
        }
        renderPager(total, pagesize);
    }

    function applyListPayload(data, pagesize) {
        nextBeforeId = parseInt(data.next_before_id, 10) || 0;
        hasMore = !!data.has_more;
        if (pageRoot) {
            pageRoot.setAttribute('data-total-approx', data.total_approx ? '1' : '0');
        }
        if (data.days) {
            days = parseInt(data.days, 10) || days;
        }
        renderList(data.list || [], data.total || 0, pagesize);
    }

    function load() {
        if (!body) {
            return;
        }
        if (!window.VS || typeof VS.postForm !== 'function') {
            setTimeout(load, 40);
            return;
        }
        if (listAbort) {
            try {
                listAbort.abort();
            } catch (e) { /* ignore */ }
        }
        listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var seq = ++loadSeq;
        var pagesize = getPageSize();
        var beforeId = cursorStack[page - 1] || 0;
        setControlsDisabled(true);
        if (VS.setLoading) {
            VS.setLoading(body, '正在加载日志');
        }
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        fd.append('days', String(getDays()));
        fd.append('before_id', String(beforeId));
        if (q) {
            fd.append('q', q);
        }
        if (okFilter !== '') {
            fd.append('ok', okFilter);
        }
        var opts = listAbort ? { signal: listAbort.signal } : {};
        VS.postForm(fd, window.location.href, opts).then(function (data) {
            if (seq !== loadSeq) {
                return;
            }
            setControlsDisabled(false);
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            applyListPayload(data, pagesize);
        }).catch(function (err) {
            if (seq !== loadSeq) {
                return;
            }
            // 被更新请求中止时不改 UI；若仍是当前 seq 的 Abort，复位加载态（E77）
            if (err && err.name === 'AbortError') {
                setControlsDisabled(false);
                return;
            }
            setControlsDisabled(false);
            body.innerHTML = '<p class="vs-empty vs-finance-empty">网络异常</p>';
        });
    }

    function doSearch() {
        q = searchInput ? String(searchInput.value || '').trim() : '';
        resetCursors();
        load();
    }

    if (body) {
        body.addEventListener('click', function (e) {
            var item = e.target.closest('[data-id]');
            if (!item || !body.contains(item)) {
                return;
            }
            openDetail(item.getAttribute('data-id'));
        });
        body.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            var item = e.target.closest('[data-id]');
            if (!item || !body.contains(item)) {
                return;
            }
            e.preventDefault();
            openDetail(item.getAttribute('data-id'));
        });
    }

    if (pagerNav) {
        pagerNav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-p]');
            if (!btn || btn.disabled) {
                return;
            }
            var delta = parseInt(btn.getAttribute('data-p'), 10) || 0;
            if (delta > 0) {
                if (!hasMore || !nextBeforeId) {
                    return;
                }
                cursorStack[page] = nextBeforeId;
                page += 1;
                load();
                return;
            }
            if (delta < 0) {
                if (page <= 1) {
                    return;
                }
                page -= 1;
                cursorStack.length = page;
                load();
            }
        });
    }

    document.querySelectorAll('.vs-log-filter').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.vs-log-filter').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
                el.classList.toggle('vs-btn--primary', el === btn);
                el.classList.toggle('vs-btn--default', el !== btn);
            });
            okFilter = btn.getAttribute('data-ok') || '';
            resetCursors();
            load();
        });
    });

    if (searchBtn) {
        searchBtn.addEventListener('click', doSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
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
            days = getDays();
            resetCursors();
            load();
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            load();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target.closest('[data-overlay-close]')) {
                closeOverlay();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeOverlay();
            }
        });
    }

    var lastMobile = isMobile();
    window.addEventListener('resize', function () {
        var now = isMobile();
        if (now !== lastMobile) {
            lastMobile = now;
            if (body && body.querySelector('[data-id]')) {
                load();
            }
        }
    });

    // 首屏只走 AJAX（与积分/订单一致），避免 data-boot 过大解析失败导致一直加载中（E77）
    if (body) {
        load();
    }
})();
