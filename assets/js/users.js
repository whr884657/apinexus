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
        container.innerHTML = '';
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
            container.appendChild(createActionBtn(userId, 'adjust_points', '积分', 'vs-btn--pill-secondary'));
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
        var html = '';
        var i;
        for (i = 1; i <= totalPages; i += 1) {
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

    if (pageSizeEl && !pageSizeEl.value) {
        pageSizeEl.value = String(defaultPageSize());
    }
    applyView();
})();
