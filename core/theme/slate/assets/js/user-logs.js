/**
 * 用户调用日志：电脑端列表+查看 / 手机端点卡片开详情（精简字段）
 */
(function () {
    'use strict';
    var body = document.getElementById('userLogsBody');
    var pagerNav = document.getElementById('userLogsPagerNav');
    var totalEl = document.getElementById('userLogsTotal');
    var footer = document.getElementById('userLogsFooter');
    var pageSizeEl = document.getElementById('userLogsPageSize');
    var pageRoot = document.getElementById('userLogsPage');
    var overlay = document.getElementById('userLogsDetailOverlay');
    var detailBody = document.getElementById('userLogsDetailBody');

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
    var returnFocusEl = null;

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

    function methodBadge(row) {
        return '<span class="vs-log-method ' + escapeHtml(row.method_class || 'is-other') + '">'
            + escapeHtml(row.method || '—') + '</span>';
    }

    function httpBadge(row) {
        return '<span class="vs-log-http ' + escapeHtml(row.http_class || '') + '">'
            + escapeHtml(row.httpcode) + '</span>';
    }

    function httpcodeDisplay(row) {
        var code = row && row.httpcode != null ? String(row.httpcode) : '';
        var label = row && row.httpcode_label ? String(row.httpcode_label) : '';
        if (code === '' && !label) {
            return '—';
        }
        if (label) {
            return code + ' · ' + label;
        }
        return code;
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
        var egress = row.egress ? String(row.egress) : '';
        var tip = ip + (loc ? (' ' + loc) : '') + (egress ? (' · 出口 ' + egress) : '');
        return '<span class="uc-log-ip" title="' + escapeHtml(tip) + '">'
            + '<span class="uc-log-ip__addr">' + escapeHtml(ip) + '</span>'
            + (loc
                ? ('<span class="uc-log-ip__loc">' + escapeHtml(loc) + '</span>')
                : '<span class="uc-log-ip__loc is-empty">归属地暂无</span>')
            + (egress
                ? ('<span class="uc-log-ip__egress">' + escapeHtml(egress) + '</span>')
                : '')
            + '</span>';
    }

    function desktopRowHtml(row) {
        return '<article class="uc-log-row" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button">'
            + '<div class="uc-log-row__name" title="' + escapeHtml(row.apiname || '') + '">'
            + escapeHtml(row.apiname || '—') + '</div>'
            + '<div class="uc-log-row__ip">' + ipLocHtml(row) + '</div>'
            + '<div class="uc-log-row__ok"><span class="uc-log-ok ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label || '') + '</span></div>'
            + '<div class="uc-log-row__time">' + escapeHtml(row.createtime || '—') + '</div>'
            + '<div class="uc-log-row__act"><span class="vs-log-view">查看</span></div>'
            + '</article>';
    }

    function cardHtml(row, index) {
        var delay = Math.min(index, 12) * 0.035;
        return '<article class="uc-log-card" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button"'
            + ' style="--uc-log-delay:' + delay + 's">'
            + '<div class="uc-log-card__top">'
            + '<strong class="uc-log-card__name" title="' + escapeHtml(row.apiname || '') + '">'
            + escapeHtml(row.apiname || '—') + '</strong>'
            + '<div class="uc-log-card__badges">'
            + methodBadge(row)
            + '<span class="uc-log-ok ' + escapeHtml(row.ok_class || '') + '">'
            + escapeHtml(row.ok_label || '') + '</span>'
            + '</div>'
            + '</div>'
            + '<div class="uc-log-card__bottom">'
            + ipLocHtml(row)
            + '<time class="uc-log-card__time">' + escapeHtml(row.createtime || '—') + '</time>'
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

    /** 已转义/含安全 HTML 的详情项（仅用于完整路径密钥模糊） */
    function detailItemHtml(label, html, full) {
        var body = (html == null || html === '') ? '—' : String(html);
        return '<div class="vs-log-detail__item' + (full ? ' vs-log-detail__item--full' : '') + '">'
            + '<span class="vs-log-detail__label">' + escapeHtml(label) + '</span>'
            + '<span class="vs-log-detail__value">' + body + '</span>'
            + '</div>';
    }

    /**
     * 完整路径：其它参数原样；仅密钥类参数值默认模糊，悬停明码
     * @param {string} url
     * @return {string} 安全 HTML
     */
    function urlWithSecretBlur(url) {
        url = url == null ? '' : String(url);
        if (url === '') {
            return '—';
        }
        var qPos = url.indexOf('?');
        if (qPos < 0) {
            return escapeHtml(url);
        }
        var hashPos = url.indexOf('#', qPos);
        var base = url.slice(0, qPos);
        var query = hashPos >= 0 ? url.slice(qPos + 1, hashPos) : url.slice(qPos + 1);
        var hash = hashPos >= 0 ? url.slice(hashPos) : '';
        var sensitive = {
            key: 1,
            apikey: 1,
            api_key: 1,
            token: 1,
            access_token: 1,
            secret: 1
        };
        var parts = query.split('&');
        var out = [];
        var i;
        for (i = 0; i < parts.length; i++) {
            var pair = parts[i];
            if (pair === '') {
                continue;
            }
            var eq = pair.indexOf('=');
            var rawName = eq >= 0 ? pair.slice(0, eq) : pair;
            var rawVal = eq >= 0 ? pair.slice(eq + 1) : '';
            var nameKey = rawName;
            try {
                nameKey = decodeURIComponent(rawName.replace(/\+/g, ' '));
            } catch (e) { /* keep raw */ }
            nameKey = String(nameKey).toLowerCase();
            if (sensitive[nameKey] && rawVal !== '') {
                // 只包「参数值」；参数名与 =、& 留在盒外清晰显示，避免整段糊住
                out.push(
                    escapeHtml(rawName) + '='
                    + '<span class="uc-log-url-secret" title="悬停显示密钥">'
                    + escapeHtml(rawVal)
                    + '</span>'
                );
            } else {
                out.push(escapeHtml(pair));
            }
        }
        return escapeHtml(base) + '?' + out.join('&') + escapeHtml(hash);
    }

    function eyeIconSvg(off) {
        if (off) {
            return '<svg class="vs-log-secret__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">'
                + '<path fill="currentColor" d="M12 7a5 5 0 0 1 5 5c0 .7-.15 1.36-.4 1.96l1.48 1.48A9.8 9.8 0 0 0 21 12c-1.73-4.39-6-7.5-9-7.5-1.1 0-2.16.3-3.12.82l1.5 1.5c.5-.2 1.05-.32 1.62-.32zm-7.03-.61 1.66 1.66A9.8 9.8 0 0 0 3 12c1.73 4.39 6 7.5 9 7.5 1.55 0 3.03-.45 4.3-1.22l1.7 1.7 1.27-1.27L5.24 4.12 3.97 5.39zm5.5 5.5 3.25 3.25A3 3 0 0 1 9 12c0-.2.02-.4.06-.58l1.41 1.41zM12 9a3 3 0 0 1 2.83 4.01l-3.84-3.84c.32-.1.66-.17 1.01-.17z"/>'
                + '</svg>';
        }
        return '<svg class="vs-log-secret__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">'
            + '<path fill="currentColor" d="M12 5c-5 0-9.27 3.11-11 7 1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 .001 6.001A3 3 0 0 0 12 9z"/>'
            + '</svg>';
    }

    function detailSecretItem(label, fullKey, maskedKey) {
        var full = fullKey == null ? '' : String(fullKey);
        var masked = maskedKey == null || maskedKey === '' ? '' : String(maskedKey);
        if (full === '' && masked === '') {
            return detailItem(label, '—');
        }
        if (masked === '') {
            masked = full;
        }
        var canReveal = full !== '' && full !== masked;
        var show = canReveal ? masked : (full || masked);
        var btn = canReveal
            ? ('<button type="button" class="vs-log-secret__toggle" aria-label="显示密钥" aria-pressed="false" title="显示/隐藏密钥">'
                + eyeIconSvg(false) + '</button>')
            : '';
        return '<div class="vs-log-detail__item vs-log-detail__item--secret">'
            + '<span class="vs-log-detail__label">' + escapeHtml(label) + '</span>'
            + '<div class="vs-log-secret" data-revealed="0"'
            + ' data-full="' + escapeHtml(full) + '"'
            + ' data-masked="' + escapeHtml(masked) + '">'
            + '<span class="vs-log-detail__value vs-log-secret__text">' + escapeHtml(show) + '</span>'
            + btn
            + '</div></div>';
    }

    function bindSecretToggles(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.vs-log-secret__toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = btn.closest('.vs-log-secret');
                if (!wrap) {
                    return;
                }
                var text = wrap.querySelector('.vs-log-secret__text');
                var on = wrap.getAttribute('data-revealed') === '1';
                var next = !on;
                wrap.setAttribute('data-revealed', next ? '1' : '0');
                if (text) {
                    text.textContent = next
                        ? (wrap.getAttribute('data-full') || '')
                        : (wrap.getAttribute('data-masked') || '');
                }
                btn.setAttribute('aria-pressed', next ? 'true' : 'false');
                btn.setAttribute('aria-label', next ? '隐藏密钥' : '显示密钥');
                btn.innerHTML = eyeIconSvg(next);
            });
        });
    }

    /** 用户侧精简详情：无类型/用户/Referer/Origin/UA/来源域名；完整路径在「网络与来源」 */
    function detailHtml(row) {
        var fullPathHtml = '—';
        if (row && row.url) {
            fullPathHtml = urlWithSecretBlur(String(row.url));
        } else if (row && row.path) {
            fullPathHtml = escapeHtml(String(row.path));
        }
        return '<div class="vs-log-detail vs-log-detail--user">'
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
            + detailItem('时间', row.createtime)
            + detailItem('结果', row.ok_label)
            + detailItem('状态码', httpcodeDisplay(row))
            + detailItem('扣费', (row.charged_label || '') + (row.charged ? (' · ' + row.cost) : ''))
            + detailSecretItem('密钥', row.apikey, row.apikey_masked)
            + '</div></div>'
            + '<div class="vs-log-detail__section">'
            + '<h4 class="vs-log-detail__section-title">网络与来源</h4>'
            + '<div class="vs-log-detail__grid">'
            + detailItem('IP', row.ip)
            + detailItem('IP 归属地', row.iploc)
            + detailItem('出口节点', row.egress)
            + detailItem('Host', row.host)
            + detailItemHtml('完整路径', fullPathHtml, true)
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
            bindSecretToggles(detailBody);
        }).catch(function () {
            detailBody.innerHTML = '<p class="vs-empty">网络异常</p>';
        });
    }

    function renderList(list) {
        if (!list || !list.length) {
            body.innerHTML = '<p class="uc-logs__empty">暂无调用记录</p>';
            return;
        }
        var head = '<div class="uc-log-row uc-log-row--head" role="presentation">'
            + '<div>接口</div><div>IP / 归属</div><div>状态</div><div>时间</div><div>操作</div></div>';
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

    load();
})();
