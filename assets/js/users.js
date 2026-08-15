/**
 * 文件：assets/js/users.js
 * 作用：用户管理页（Tab 筛选 / 搜索 / 分页 + AJAX 操作）
 */
(function () {
    'use strict';

    var page = document.getElementById('usersPage');
    if (!page) {
        return;
    }

    var listEl = document.getElementById('usersBody');
    var mobileEl = document.getElementById('usersMobile');
    var tableWrapEl = document.getElementById('usersTableWrap');
    var filterEmpty = document.getElementById('usersFilterEmpty');
    var searchInput = document.getElementById('usersSearchInput');
    var pageSizeEl = document.getElementById('usersPageSize');
    var footerEl = document.getElementById('usersFooter');
    var pagerNumsEl = document.getElementById('usersPagerNums');
    var statsEl = document.getElementById('usersStats');
    var prevBtn = document.getElementById('usersPrevBtn');
    var nextBtn = document.getElementById('usersNextBtn');
    var currentFilter = 'all';
    var currentPage = 1;

    function createActionBtn(userId, action, label, className, confirmDelete, role) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vs-btn vs-btn--pill ' + className + ' vs-user-action-btn';
        btn.setAttribute('data-user-action', action);
        btn.setAttribute('data-user-id', String(userId));
        if (role) {
            btn.setAttribute('data-user-role', role);
        }
        if (confirmDelete) {
            btn.setAttribute('data-confirm-delete', '1');
        }
        btn.textContent = label;
        return btn;
    }

    function roleBadgeHtml(role, label) {
        var cls = role === 'developer' ? 'vs-role-badge--developer' : 'vs-role-badge--user';
        return '<span class="vs-role-badge ' + cls + '">' + label + '</span>';
    }

    function rebuildActions(container, userId, banned, role) {
        var pointsBtn = container.querySelector('[data-user-action="adjust_points"]');
        var pointsVal = pointsBtn ? (pointsBtn.getAttribute('data-user-points') || '0') : '0';
        var nameEl = container.closest('[data-user-row]');
        var userName = nameEl ? (nameEl.getAttribute('data-user-name') || '') : '';
        container.innerHTML = '';
        var logsBtn = createActionBtn(userId, 'view_logs', '调用日志', 'vs-btn--pill-secondary');
        if (userName) {
            logsBtn.setAttribute('data-user-name', userName);
        }
        container.appendChild(logsBtn);
        if (banned) {
            container.appendChild(createActionBtn(userId, 'unban', '解封', 'vs-btn--pill-primary'));
        } else {
            container.appendChild(createActionBtn(userId, 'ban', '封禁', 'vs-btn--pill-danger'));
        }
        if (role === 'developer') {
            container.appendChild(createActionBtn(userId, 'set_role', '设为普通', 'vs-btn--pill-secondary', false, 'user'));
        } else {
            container.appendChild(createActionBtn(userId, 'set_role', '设为开发者', 'vs-btn--pill-primary', false, 'developer'));
        }
        if (document.getElementById('usersPointsOverlay')) {
            var pts = createActionBtn(userId, 'adjust_points', '积分', 'vs-btn--pill-secondary');
            pts.setAttribute('data-user-points', pointsVal);
            container.appendChild(pts);
        }
        container.appendChild(createActionBtn(userId, 'delete', '删除', 'vs-btn--pill-danger', true));
    }

    function defaultPageSize() {
        return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
        if (!n || n < 1) {
            n = defaultPageSize();
        }
        return n;
    }

    function allDesktopRows() {
        if (!listEl) {
            return [];
        }
        return Array.prototype.slice.call(listEl.querySelectorAll('tr[data-user-row]'));
    }

    function rowMatchesFilter(row) {
        var role = String(row.getAttribute('data-user-role') || 'user');
        var status = String(row.getAttribute('data-user-status') || '1');
        if (currentFilter === 'developer') {
            return role === 'developer';
        }
        if (currentFilter === 'user') {
            return role === 'user';
        }
        if (currentFilter === 'banned') {
            return status === '0';
        }
        return true;
    }

    function syncRowOrder(rows) {
        if (!listEl) {
            return;
        }
        rows.forEach(function (row) {
            listEl.appendChild(row);
            if (mobileEl) {
                var id = row.getAttribute('data-user-row');
                var card = mobileEl.querySelector('[data-user-row="' + id + '"]');
                if (card) {
                    mobileEl.appendChild(card);
                }
            }
        });
    }

    function matchedRows() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        var all = allDesktopRows();
        var filtered = all.filter(function (row) {
            if (!rowMatchesFilter(row)) {
                return false;
            }
            if (q) {
                var hay = row.getAttribute('data-search') || '';
                if (hay.indexOf(q) === -1) {
                    return false;
                }
            }
            return true;
        });
        syncRowOrder(filtered);
        return filtered;
    }

    function refreshTabBadges() {
        var counts = { all: 0, developer: 0, user: 0, banned: 0 };
        allDesktopRows().forEach(function (row) {
            counts.all += 1;
            var role = String(row.getAttribute('data-user-role') || 'user');
            var status = String(row.getAttribute('data-user-status') || '1');
            if (role === 'developer') {
                counts.developer += 1;
            } else {
                counts.user += 1;
            }
            if (status === '0') {
                counts.banned += 1;
            }
        });
        document.querySelectorAll('.vs-api-review-tabs__badge[data-count]').forEach(function (el) {
            var key = String(el.getAttribute('data-count') || '');
            if (counts[key] != null) {
                el.textContent = String(counts[key]);
            }
        });
        page.setAttribute('data-user-total', String(counts.all));
    }

    function renderPagerNums(totalPages) {
        if (!pagerNumsEl) {
            return;
        }
        if (totalPages <= 1) {
            pagerNumsEl.innerHTML = '';
            return;
        }
        // 中间最多 3 个页码：当前页尽量居中（首尾贴边）
        var start = Math.max(1, currentPage - 1);
        var end = Math.min(totalPages, start + 2);
        start = Math.max(1, end - 2);
        var html = '';
        var i;
        for (i = start; i <= end; i += 1) {
            html += '<button type="button" class="vs-api-pager__num'
                + (i === currentPage ? ' is-active' : '')
                + '" data-page="' + i + '">' + i + '</button>';
        }
        pagerNumsEl.innerHTML = html;
    }

    function applyView() {
        var totalAll = allDesktopRows().length;
        var matched = matchedRows();
        var pageSize = getPageSize();
        var totalPages = Math.max(1, Math.ceil(matched.length / pageSize) || 1);

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }

        var start = (currentPage - 1) * pageSize;
        var end = start + pageSize;
        var visibleIds = {};

        matched.forEach(function (row, idx) {
            var show = idx >= start && idx < end;
            var id = row.getAttribute('data-user-row');
            row.hidden = !show;
            if (show) {
                visibleIds[id] = true;
            }
        });

        allDesktopRows().forEach(function (row) {
            if (matched.indexOf(row) === -1) {
                row.hidden = true;
            }
        });

        if (mobileEl) {
            mobileEl.querySelectorAll('[data-user-row]').forEach(function (card) {
                var id = card.getAttribute('data-user-row');
                var matchedDesktop = listEl
                    ? listEl.querySelector('tr[data-user-row="' + id + '"]')
                    : null;
                var inMatched = matchedDesktop && matched.indexOf(matchedDesktop) !== -1;
                card.hidden = !(inMatched && visibleIds[id]);
            });
        }

        var hasAny = totalAll > 0;
        var hasMatched = matched.length > 0;

        if (tableWrapEl) {
            tableWrapEl.hidden = !hasMatched;
        }
        if (mobileEl) {
            mobileEl.hidden = !hasMatched;
        }
        if (footerEl) {
            footerEl.hidden = !hasAny;
        }
        if (filterEmpty) {
            filterEmpty.hidden = !hasAny || hasMatched;
        }

        if (statsEl) {
            statsEl.textContent = hasMatched
                ? ('共 ' + matched.length + ' 条')
                : ('共 0 条');
        }

        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages;
        }

        renderPagerNums(totalPages);
    }

    function setActiveTab(filter) {
        currentFilter = filter;
        currentPage = 1;
        document.querySelectorAll('.vs-users-filter').forEach(function (btn) {
            var active = btn.getAttribute('data-filter') === filter;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        applyView();
    }

    function updateRoleCells(userId, role, roleLabel) {
        document.querySelectorAll('[data-user-row="' + userId + '"]').forEach(function (row) {
            row.setAttribute('data-user-role', role);
            var roleCell = row.querySelector('.vs-users-role-cell, .vs-user-card__role');
            if (roleCell) {
                roleCell.innerHTML = roleBadgeHtml(role, roleLabel);
            }
        });
        refreshTabBadges();
        applyView();
    }

    function ensureBannedTag(nameEl) {
        if (!nameEl || nameEl.querySelector('.vs-users-banned-tag')) {
            return;
        }
        var tag = document.createElement('span');
        tag.className = 'vs-users-banned-tag';
        tag.textContent = '已封禁';
        nameEl.appendChild(tag);
    }

    function removeBannedTag(nameEl) {
        if (!nameEl) {
            return;
        }
        var tag = nameEl.querySelector('.vs-users-banned-tag');
        if (tag) {
            tag.parentNode.removeChild(tag);
        }
    }

    function updateUserRows(userId, action, extra) {
        var rows = document.querySelectorAll('[data-user-row="' + userId + '"]');
        rows.forEach(function (row) {
            if (action === 'delete') {
                if (row.parentNode) {
                    row.parentNode.removeChild(row);
                }
                return;
            }

            if (action === 'set_role' && extra) {
                updateRoleCells(userId, extra.role, extra.role_label);
                var actions = row.querySelector('.vs-users-actions, .vs-user-card__actions');
                var banned = row.getAttribute('data-user-status') === '0';
                if (actions) {
                    rebuildActions(actions, userId, banned, extra.role);
                }
                return;
            }

            var banned = action === 'ban';
            row.setAttribute('data-user-status', banned ? '0' : '1');
            row.classList.toggle('vs-users-row--banned', banned);
            row.classList.toggle('vs-user-card--banned', banned);

            var nameEl = row.querySelector('.vs-users-name');
            if (banned) {
                ensureBannedTag(nameEl);
            } else {
                removeBannedTag(nameEl);
            }

            var actions = row.querySelector('.vs-users-actions, .vs-user-card__actions');
            var role = row.getAttribute('data-user-role') || 'user';
            if (actions) {
                rebuildActions(actions, userId, banned, role);
            }
        });

        if (action === 'ban' || action === 'unban') {
            refreshTabBadges();
            applyView();
        }
    }

    function confirmDelete() {
        if (window.VsModal && window.VsModal.confirm) {
            return window.VsModal.confirm(
                '删除后该用户的账号与绑定信息将永久移除，且不可恢复。确定删除吗？',
                '确认删除用户',
                { confirmText: '删除', danger: true }
            );
        }
        return Promise.resolve(window.confirm('删除后该用户的账号与绑定信息将永久移除，且不可恢复。确定删除吗？'));
    }

    function postAction(userId, action, role) {
        var body = new FormData();
        body.append('action', action);
        body.append('user_id', String(userId));
        if (action === 'set_role' && role) {
            body.append('role', role);
        }

        return window.VS.postForm(body).then(function (data) {
            if (data.code !== 1) {
                throw new Error(data.msg || '操作失败');
            }
            return data;
        });
    }

    document.querySelectorAll('.vs-users-filter').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setActiveTab(btn.getAttribute('data-filter') || 'all');
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentPage = 1;
            applyView();
        });
    }

    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            currentPage = 1;
            applyView();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage -= 1;
                applyView();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            currentPage += 1;
            applyView();
        });
    }

    if (pagerNumsEl) {
        pagerNumsEl.addEventListener('click', function (e) {
            var pageBtn = e.target.closest('.vs-api-pager__num[data-page]');
            if (!pageBtn) {
                return;
            }
            currentPage = parseInt(pageBtn.getAttribute('data-page'), 10) || 1;
            applyView();
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.vs-user-action-btn');
        if (!btn || btn.disabled) {
            return;
        }

        var userId = btn.getAttribute('data-user-id');
        var action = btn.getAttribute('data-user-action');
        var role = btn.getAttribute('data-user-role');
        if (!userId || !action) {
            return;
        }

        if (action === 'adjust_points') {
            var overlay = document.getElementById('usersPointsOverlay');
            var form = document.getElementById('usersPointsForm');
            var uidEl = document.getElementById('usersPointsUserId');
            var hint = document.getElementById('usersPointsHint');
            var delta = document.getElementById('usersPointsDelta');
            var remark = document.getElementById('usersPointsRemark');
            if (!overlay || !form || !uidEl) {
                return;
            }
            uidEl.value = userId;
            if (hint) {
                hint.textContent = '当前余额：' + (btn.getAttribute('data-user-points') || '0');
            }
            if (delta) {
                delta.value = '';
            }
            if (remark) {
                remark.value = '';
            }
            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
            overlay.classList.add('is-open');
            document.body.classList.add('is-overlay-open');
            if (delta) {
                delta.focus();
            }
            return;
        }

        if (action === 'view_logs') {
            if (window.VsUsersLogs && typeof window.VsUsersLogs.open === 'function') {
                var uname = btn.getAttribute('data-user-name') || '';
                if (!uname) {
                    var row = btn.closest('[data-user-row]');
                    uname = row ? (row.getAttribute('data-user-name') || '') : '';
                }
                window.VsUsersLogs.open(userId, uname);
            }
            return;
        }

        function run() {
            btn.disabled = true;
            postAction(userId, action, role)
                .then(function (data) {
                    var extra = null;
                    if (action === 'set_role') {
                        extra = { role: data.role, role_label: data.role_label };
                    }
                    updateUserRows(userId, action, extra);
                    if (action === 'delete') {
                        refreshTabBadges();
                        applyView();
                        if (allDesktopRows().length === 0) {
                            window.location.reload();
                        }
                    }
                    window.VS.showMessage(data.msg || '操作成功', 'success');
                })
                .catch(function (err) {
                    window.VS.showMessage(err.message || '网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        }

        if (action === 'delete') {
            confirmDelete().then(function (ok) {
                if (ok) {
                    run();
                }
            });
            return;
        }

        run();
    });

    (function bindPointsOverlay() {
        var overlay = document.getElementById('usersPointsOverlay');
        var form = document.getElementById('usersPointsForm');
        if (!overlay || !form) {
            return;
        }
        function close() {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            overlay.classList.remove('is-open');
            document.body.classList.remove('is-overlay-open');
        }
        overlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            window.VS.postForm(form).then(function (data) {
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '调整失败', 'error');
                    return;
                }
                var uid = document.getElementById('usersPointsUserId').value;
                document.querySelectorAll('[data-user-row="' + uid + '"] [data-field="points"]').forEach(function (el) {
                    el.textContent = data.points || '0';
                });
                document.querySelectorAll('[data-user-action="adjust_points"][data-user-id="' + uid + '"]').forEach(function (el) {
                    el.setAttribute('data-user-points', data.points || '0');
                });
                window.VS.showMessage(data.msg || '已调整', 'success');
                close();
            }).catch(function () {
                window.VS.showMessage('网络异常', 'error');
            });
        });
    })();

    (function bindUsersLogs() {
        var listOverlay = document.getElementById('usersLogsOverlay');
        var detailOverlay = document.getElementById('usersLogDetailOverlay');
        var listEl = document.getElementById('usersLogsList');
        var titleEl = document.getElementById('usersLogsTitle');
        var footerEl = document.getElementById('usersLogsFooter');
        var pagerNav = document.getElementById('usersLogsPagerNav');
        var totalEl = document.getElementById('usersLogsTotal');
        var pageSizeEl = document.getElementById('usersLogsPageSize');
        var detailBody = document.getElementById('usersLogDetailBody');
        if (!listOverlay || !listEl) {
            return;
        }

        var currentUserId = 0;
        var currentUserName = '';
        var page = 1;
        var cursorStack = [0];
        var hasMore = false;
        var totalCount = 0;
        var okFilter = '';
        var loadSeq = 0;
        var listAbort = null;
        var openOverlays = 0;

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function lockBody(on) {
            if (on) {
                openOverlays += 1;
                document.body.classList.add('is-overlay-open');
            } else {
                openOverlays = Math.max(0, openOverlays - 1);
                if (openOverlays === 0) {
                    document.body.classList.remove('is-overlay-open');
                }
            }
        }

        function openShell(el) {
            if (!el || !el.hidden) {
                return;
            }
            el.hidden = false;
            el.setAttribute('aria-hidden', 'false');
            el.classList.add('is-open');
            lockBody(true);
        }

        function closeShell(el) {
            if (!el || el.hidden) {
                return;
            }
            el.hidden = true;
            el.setAttribute('aria-hidden', 'true');
            el.classList.remove('is-open');
            lockBody(false);
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
            if (!n || n < 1) n = 20;
            return Math.min(50, n);
        }

        function resetCursors() {
            page = 1;
            cursorStack = [0];
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

        function statusBadge(row) {
            return '<span class="vs-log-status ' + escapeHtml(row.ok_class || '') + '">'
                + escapeHtml(row.ok_label || '—') + '</span>';
        }

        function httpcodeDisplay(row) {
            var code = row && row.httpcode != null ? String(row.httpcode) : '';
            var label = row && row.httpcode_label ? String(row.httpcode_label) : '';
            if (code === '' && !label) return '—';
            if (label) return code + ' · ' + label;
            return code;
        }

        function cardHtml(row) {
            var name = row.apiname || ('接口 #' + row.apiid);
            var ip = row.ip || '—';
            var loc = row.iploc || '';
            var ipLine = escapeHtml(ip) + (loc ? (' ' + escapeHtml(loc)) : '');
            return '<article class="vs-users-log-card" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button">'
                + '<div class="vs-users-log-card__top">'
                + '<strong class="vs-users-log-card__name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</strong>'
                + statusBadge(row)
                + '</div>'
                + '<div class="vs-users-log-card__mid">'
                + methodBadge(row)
                + '<span class="vs-users-log-card__ip" title="' + escapeHtml(ip + (loc ? (' ' + loc) : '')) + '">' + ipLine + '</span>'
                + '</div>'
                + '<div class="vs-users-log-card__foot">'
                + '<time>' + escapeHtml(row.createtime || '—') + '</time>'
                + '<span class="vs-log-view">详情</span>'
                + '</div>'
                + '</article>';
        }

        function desktopRowHtml(row) {
            var name = row.apiname || ('接口 #' + row.apiid);
            var ip = row.ip || '—';
            var loc = row.iploc || '';
            return '<div class="vs-users-log-row" data-id="' + escapeHtml(row.id) + '" tabindex="0" role="button">'
                + '<div class="vs-users-log-row__name" title="' + escapeHtml(name) + '">'
                + '<span class="vs-log-id">#' + escapeHtml(row.id) + '</span>'
                + escapeHtml(name)
                + '</div>'
                + '<div>' + methodBadge(row) + '</div>'
                + '<div class="vs-users-log-row__ip" title="' + escapeHtml(ip + (loc ? (' ' + loc) : '')) + '">'
                + '<span class="vs-log-mono">' + escapeHtml(ip) + '</span>'
                + (loc ? escapeHtml(loc) : '')
                + '</div>'
                + '<div>' + statusBadge(row) + '</div>'
                + '<div>' + httpBadge(row) + '</div>'
                + '<div class="vs-users-log-row__time">' + escapeHtml(row.createtime || '—') + '</div>'
                + '<div class="vs-users-log-row__act"><span class="vs-log-view">详情</span></div>'
                + '</div>';
        }

        function renderList(list) {
            if (!list || !list.length) {
                listEl.innerHTML = '<p class="vs-empty vs-finance-empty" style="padding:24px;">暂无调用记录</p>';
                return;
            }
            var head = '<div class="vs-users-log-row vs-users-log-row--head" role="presentation">'
                + '<div>接口</div><div>方法</div><div>IP / 归属</div><div>状态</div><div>HTTP</div><div>时间</div><div></div>'
                + '</div>';
            listEl.innerHTML = '<div class="vs-users-logs-desktop">' + head + list.map(desktopRowHtml).join('') + '</div>'
                + '<div class="vs-users-logs-mobile">' + list.map(cardHtml).join('') + '</div>';
        }

        function renderPager() {
            if (footerEl) footerEl.hidden = false;
            if (totalEl) totalEl.textContent = '共 ' + (totalCount || 0) + ' 条';
            if (pagerNav) {
                pagerNav.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"'
                    + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
                    + '<span class="vs-api-pager__info">' + page + '</span>'
                    + '<button type="button" class="vs-api-pager__nav" data-p="1"'
                    + (!hasMore ? ' disabled' : '') + '>下一页</button>';
            }
        }

        function loadLogs() {
            if (!window.VS || !currentUserId) return;
            if (listAbort) {
                try { listAbort.abort(); } catch (e) { /* ignore */ }
            }
            listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var seq = ++loadSeq;
            var pagesize = getPageSize();
            var beforeId = cursorStack[page - 1] || 0;
            if (VS.setLoading) {
                VS.setLoading(listEl, '正在加载日志');
            } else {
                listEl.innerHTML = '<div class="vs-loading">正在加载日志…</div>';
            }
            var fd = new FormData();
            fd.append('action', 'user_logs');
            fd.append('user_id', String(currentUserId));
            fd.append('page', String(page));
            fd.append('pagesize', String(pagesize));
            fd.append('before_id', String(beforeId));
            if (okFilter === '0' || okFilter === '1') {
                fd.append('ok', okFilter);
            }
            var opts = listAbort ? { signal: listAbort.signal } : {};
            VS.postForm(fd, window.location.href, opts).then(function (data) {
                if (seq !== loadSeq) return;
                if (!data || data.code !== 1) {
                    listEl.innerHTML = '<p class="vs-empty vs-finance-empty">'
                        + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                    return;
                }
                var nextBefore = parseInt(data.next_before_id, 10) || 0;
                hasMore = !!data.has_more;
                totalCount = parseInt(data.total, 10) || 0;
                if (cursorStack.length === page) {
                    cursorStack.push(nextBefore);
                } else {
                    cursorStack[page] = nextBefore;
                }
                renderList(data.list || []);
                renderPager();
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                if (seq !== loadSeq) return;
                listEl.innerHTML = '<p class="vs-empty vs-finance-empty">网络异常</p>';
            });
        }

        function detailItem(label, value, full) {
            var v = value == null || value === '' ? '—' : String(value);
            return '<div class="vs-log-detail__item' + (full ? ' vs-log-detail__item--full' : '') + '">'
                + '<span class="vs-log-detail__label">' + escapeHtml(label) + '</span>'
                + '<span class="vs-log-detail__value">' + escapeHtml(v) + '</span>'
                + '</div>';
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
            if (full === '' && masked === '') return detailItem(label, '—');
            if (masked === '') masked = full;
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
                + btn + '</div></div>';
        }

        function bindSecretToggles(root) {
            if (!root) return;
            root.querySelectorAll('.vs-log-secret__toggle').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var wrap = btn.closest('.vs-log-secret');
                    if (!wrap) return;
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
                + detailItem('结果', row.ok_label)
                + detailItem('状态码', httpcodeDisplay(row), true)
                + detailItem('用户', row.user_label || (row.userid ? ('#' + row.userid) : '匿名'))
                + detailSecretItem('密钥', row.apikey, row.apikey_masked)
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
                + '</div></div></div>';
        }

        function openDetail(id) {
            if (!detailOverlay || !detailBody || !window.VS) return;
            detailBody.innerHTML = (VS.loadingHtml)
                ? VS.loadingHtml('正在加载详情', true)
                : '<p class="vs-empty">正在加载</p>';
            openShell(detailOverlay);
            var fd = new FormData();
            fd.append('action', 'user_log_detail');
            fd.append('user_id', String(currentUserId));
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

        function open(userId, userName) {
            currentUserId = parseInt(userId, 10) || 0;
            currentUserName = userName || '';
            if (currentUserId <= 0) return;
            closeShell(detailOverlay);
            if (titleEl) {
                titleEl.textContent = currentUserName
                    ? ('调用日志 · ' + currentUserName)
                    : ('调用日志 · 用户 #' + currentUserId);
            }
            okFilter = '';
            listOverlay.querySelectorAll('[data-user-log-ok]').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-user-log-ok') === '');
            });
            resetCursors();
            openShell(listOverlay);
            loadLogs();
        }

        listOverlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeShell(detailOverlay);
                closeShell(listOverlay);
            });
        });
        if (detailOverlay) {
            detailOverlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
                el.addEventListener('click', function () {
                    closeShell(detailOverlay);
                });
            });
        }

        listOverlay.addEventListener('click', function (e) {
            var filterBtn = e.target.closest('[data-user-log-ok]');
            if (filterBtn && listOverlay.contains(filterBtn)) {
                okFilter = filterBtn.getAttribute('data-user-log-ok') || '';
                listOverlay.querySelectorAll('[data-user-log-ok]').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === filterBtn);
                });
                resetCursors();
                loadLogs();
                return;
            }
            var item = e.target.closest('[data-id]');
            if (item && listEl.contains(item)) {
                openDetail(item.getAttribute('data-id'));
            }
        });

        if (pagerNav) {
            pagerNav.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-p]');
                if (!btn || btn.disabled) return;
                var dir = parseInt(btn.getAttribute('data-p'), 10);
                if (dir < 0 && page > 1) {
                    page -= 1;
                    loadLogs();
                } else if (dir > 0 && hasMore) {
                    page += 1;
                    loadLogs();
                }
            });
        }

        if (pageSizeEl) {
            pageSizeEl.addEventListener('change', function () {
                resetCursors();
                loadLogs();
            });
        }

        window.VsUsersLogs = { open: open };
    })();

    if (pageSizeEl && !pageSizeEl.value) {
        pageSizeEl.value = String(defaultPageSize());
    }
    applyView();
})();
