/**
 * 文件：assets/js/system-logs.js
 * 作用：管理员 API 调用日志（桌面列表 / 手机卡片 + 抽屉详情）
 */
(function () {
    'use strict';

    var body = document.getElementById('logsListBody');
    var footer = document.getElementById('logsFooter');
    var pagerNav = document.getElementById('logsPagerNav');
    var totalEl = document.getElementById('logsTotal');
    var pageSizeEl = document.getElementById('logsPageSize');
    var searchInput = document.getElementById('logsSearchInput');
    var overlay = document.getElementById('logsDetailOverlay');
    var detailBody = document.getElementById('logsDetailBody');
    var page = 1;
    var okFilter = '';
    var q = '';
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

    function headHtml() {
        return '<div class="vs-log-row vs-log-row--head" aria-hidden="true">'
            + '<div class="vs-log-cell">接口</div>'
            + '<div class="vs-log-cell">方法</div>'
            + '<div class="vs-log-cell">IP</div>'
            + '<div class="vs-log-cell">结果</div>'
            + '<div class="vs-log-cell">状态码</div>'
            + '<div class="vs-log-cell">时间</div>'
            + '</div>';
    }

    function rowHtml(row) {
        return '<article class="vs-log-row" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button">'
            + '<div class="vs-log-cell vs-log-c-name">'
            + '<strong>' + escapeHtml(row.apiname || ('#' + row.apiid)) + '</strong>'
            + '<span class="vs-log-sub">' + escapeHtml(row.path || '') + '</span>'
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-method">'
            + '<span class="vs-finance-m-label">方法</span>'
            + escapeHtml(row.method || '—')
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-ip">'
            + '<span class="vs-finance-m-label">IP</span>'
            + '<span class="vs-log-mono">' + escapeHtml(row.ip || '—') + '</span>'
            + (row.iploc ? '<span class="vs-log-sub">' + escapeHtml(row.iploc) + '</span>' : '')
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-ok">'
            + '<span class="vs-log-status ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label) + '</span>'
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-code">'
            + '<span class="vs-finance-m-label">状态码</span>'
            + escapeHtml(row.httpcode)
            + '</div>'
            + '<div class="vs-log-cell vs-log-c-time">'
            + escapeHtml(row.createtime || '—')
            + '</div>'
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
            + '<span>' + escapeHtml(row.method || '—') + '</span>'
            + '<span class="vs-log-mono">' + escapeHtml(row.ip || '—') + '</span>'
            + '<span>HTTP ' + escapeHtml(row.httpcode) + '</span>'
            + '</div>'
            + '<div class="vs-log-card__time">' + escapeHtml(row.createtime || '—') + '</div>'
            + '</article>';
    }

    function detailHtml(row) {
        function line(label, value) {
            var v = value == null || value === '' ? '—' : String(value);
            return '<div class="vs-log-detail__row">'
                + '<span class="vs-log-detail__label">' + escapeHtml(label) + '</span>'
                + '<span class="vs-log-detail__value">' + escapeHtml(v) + '</span>'
                + '</div>';
        }
        return '<div class="vs-log-detail">'
            + line('记录 ID', row.id)
            + line('接口', (row.apiname || '') + (row.apiid ? ' (#' + row.apiid + ')' : ''))
            + line('类型', row.apitype_label)
            + line('方法', row.method)
            + line('结果', row.ok_label)
            + line('HTTP', row.httpcode)
            + line('IP', row.ip + (row.iploc ? ' · ' + row.iploc : ''))
            + line('路径', row.path)
            + line('完整 URL', row.url)
            + line('来源域名', row.domain)
            + line('Referer', row.referer)
            + line('Origin', row.origin)
            + line('密钥', row.apikey_mask)
            + line('用户 ID', row.userid || '匿名')
            + line('扣费', row.charged_label + (row.charged ? ' · ' + row.cost : ''))
            + line('User-Agent', row.ua)
            + line('时间', row.createtime)
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
        detailBody.innerHTML = '<p class="vs-empty">加载中…</p>';
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

    function load() {
        if (!body || !window.VS) {
            return;
        }
        var pagesize = getPageSize();
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        if (q) {
            fd.append('q', q);
        }
        if (okFilter !== '') {
            fd.append('ok', okFilter);
        }
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            var list = data.list || [];
            var total = data.total || 0;
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

    function doSearch() {
        q = searchInput ? String(searchInput.value || '').trim() : '';
        page = 1;
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
            page += parseInt(btn.getAttribute('data-p'), 10) || 0;
            if (page < 1) {
                page = 1;
            }
            load();
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
            page = 1;
            load();
        });
    });

    var searchBtn = document.getElementById('logsSearchBtn');
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
            page = 1;
            load();
        });
    }

    var refresh = document.getElementById('logsRefreshBtn');
    if (refresh) {
        refresh.addEventListener('click', load);
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

    load();
})();
