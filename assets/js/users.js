/**
 * 文件：assets/js/users.js
 * 作用：用户管理页 AJAX 操作 + 列表搜索（静态过滤）
 */
(function () {
    'use strict';

    var searchRoot = document.getElementById('usersSearch');
    var searchInput = document.getElementById('usersSearchInput');
    var searchToggle = document.getElementById('usersSearchToggle');
    var countDesc = document.getElementById('usersCountDesc');
    var searchEmpty = document.getElementById('usersSearchEmpty');

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
            var ptsBtn = createActionBtn(userId, 'adjust_points', '积分', 'vs-btn--pill-secondary');
            container.appendChild(ptsBtn);
        }
        container.appendChild(createActionBtn(userId, 'delete', '删除', 'vs-btn--pill-danger', true));
    }

    function updateRoleCells(userId, role, roleLabel) {
        var rows = document.querySelectorAll('[data-user-row="' + userId + '"]');
        rows.forEach(function (row) {
            row.setAttribute('data-user-role', role);
            var roleCell = row.querySelector('.vs-users-role-cell, .vs-user-card__role');
            if (roleCell) {
                roleCell.innerHTML = roleBadgeHtml(role, roleLabel);
            }
        });
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

    function getTotalCount() {
        if (!countDesc) {
            return 0;
        }
        var total = countDesc.getAttribute('data-total');
        return total ? parseInt(total, 10) : 0;
    }

    function setTotalCount(count) {
        if (!countDesc) {
            return;
        }
        countDesc.setAttribute('data-total', String(count));
    }

    function updateCountDesc(visible) {
        if (!countDesc) {
            return;
        }
        var total = getTotalCount();
        if (visible == null || visible === total) {
            countDesc.textContent = '共 ' + total + ' 位用户';
            return;
        }
        countDesc.textContent = '显示 ' + visible + ' / 共 ' + total + ' 位用户';
    }

    function getSearchableRows() {
        return document.querySelectorAll('[data-user-row][data-search]');
    }

    function applySearch() {
        if (!searchInput) {
            return;
        }
        var keyword = (searchInput.value || '').trim().toLowerCase();
        var rows = getSearchableRows();
        var visible = 0;

        rows.forEach(function (row) {
            var blob = row.getAttribute('data-search') || '';
            var match = keyword === '' || blob.indexOf(keyword) !== -1;
            row.hidden = !match;
            if (match) {
                visible += 1;
            }
        });

        updateCountDesc(keyword === '' ? null : visible);

        if (searchEmpty) {
            var hasRows = rows.length > 0;
            searchEmpty.hidden = !(hasRows && keyword !== '' && visible === 0);
        }
    }

    function bindSearch() {
        if (!searchInput) {
            return;
        }

        searchInput.addEventListener('input', function () {
            applySearch();
        });

        if (searchToggle && searchRoot) {
            searchToggle.addEventListener('click', function () {
                var open = searchRoot.classList.toggle('is-open');
                searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                searchToggle.setAttribute('aria-label', open ? '收起搜索' : '展开搜索');
                if (open) {
                    searchInput.focus();
                } else if (!searchInput.value) {
                    searchInput.blur();
                }
            });
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
                var banned = row.classList.contains('vs-users-row--banned') || row.classList.contains('vs-user-card--banned');
                if (actions) {
                    rebuildActions(actions, userId, banned, extra.role);
                }
                return;
            }

            var banned = action === 'ban';
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
    }

    function updateCount(delta) {
        var total = Math.max(0, getTotalCount() + delta);
        setTotalCount(total);
        applySearch();
        if (total === 0 && countDesc) {
            countDesc.textContent = '共 0 位用户';
            window.location.reload();
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
                        updateCount(-1);
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

    bindSearch();
})();
