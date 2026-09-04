/**
 * 文件：assets/js/admin-ip.js
 * 作用：管理员 IP 配置（双 DOM 列表 + 详情浮层管理白名单/代理）
 */
(function () {
    'use strict';

    function boot() {
        if (!window.VS) {
            setTimeout(boot, 30);
            return;
        }
        init();
    }

    function init() {
        var page = document.getElementById('adminIpPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('adminIpTableWrap');
        var mobileEl = document.getElementById('adminIpMobileCards');
        var emptyEl = document.getElementById('adminIpEmpty');
        var searchEmptyEl = document.getElementById('adminIpSearchEmpty');
        var searchInput = document.getElementById('adminIpSearch');
        var overlay = document.getElementById('adminIpOverlay');
        var overlayBody = document.getElementById('adminIpOverlayBody');
        var overlayTitle = document.getElementById('adminIpOverlayTitle');
        var currentUserId = 0;
        var busy = false;

        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function ok(res) {
            return res && Number(res.code) === 1;
        }

        /** AjaxResponse 扁平合并：优先 data 包装，否则读顶层 */
        function ajaxExtra(res) {
            if (!res || typeof res !== 'object') {
                return {};
            }
            if (res.data != null && typeof res.data === 'object' && !Array.isArray(res.data)) {
                return res.data;
            }
            return res;
        }

        function msg(res, fallback) {
            return (res && res.msg) ? String(res.msg) : fallback;
        }

        function toast(text, type) {
            if (window.VS && typeof window.VS.toast === 'function') {
                window.VS.toast(text, type || 'info');
                return;
            }
            if (window.VsToast && typeof window.VsToast.show === 'function') {
                window.VsToast.show(text, type || 'info');
            }
        }

        function confirmDanger(message, title) {
            if (window.VsModal && typeof window.VsModal.confirm === 'function') {
                return window.VsModal.confirm(message, title || '确认操作', { danger: true });
            }
            return Promise.resolve(window.confirm(message));
        }

        function post(fields) {
            var fd = new FormData();
            Object.keys(fields).forEach(function (k) {
                fd.append(k, fields[k]);
            });
            return window.VS.postForm(fd);
        }

        function allNodes() {
            return Array.prototype.slice.call(page.querySelectorAll('[data-ip-row]') || []);
        }

        function uniqueUserCount(nodes) {
            var seen = {};
            var n = 0;
            nodes.forEach(function (el) {
                if (el.hidden) {
                    return;
                }
                var uid = el.getAttribute('data-ip-row');
                if (!seen[uid]) {
                    seen[uid] = true;
                    n += 1;
                }
            });
            return n;
        }

        function applySearch() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var nodes = allNodes();
            var totalUsers = 0;
            var totalSeen = {};
            nodes.forEach(function (el) {
                var uid = el.getAttribute('data-ip-row');
                if (!totalSeen[uid]) {
                    totalSeen[uid] = true;
                    totalUsers += 1;
                }
                var hay = String(el.getAttribute('data-search') || '').toLowerCase();
                el.hidden = !!(q && hay.indexOf(q) === -1);
            });
            var visibleUsers = uniqueUserCount(nodes);
            if (emptyEl) {
                emptyEl.hidden = totalUsers > 0;
            }
            if (searchEmptyEl) {
                searchEmptyEl.hidden = !(totalUsers > 0 && visibleUsers === 0);
            }
            var hideList = totalUsers === 0 || visibleUsers === 0;
            if (tableWrapEl) {
                tableWrapEl.hidden = hideList;
            }
            if (mobileEl) {
                mobileEl.hidden = hideList;
            }
        }

        function openOverlay() {
            if (!overlay) {
                return;
            }
            overlay.hidden = false;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-overlay-open');
        }

        function closeOverlay() {
            if (!overlay) {
                return;
            }
            overlay.classList.remove('is-open');
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-overlay-open');
            currentUserId = 0;
        }

        function statusBadge(on) {
            return on
                ? '<span class="vs-badge vs-badge--success">启用</span>'
                : '<span class="vs-badge vs-badge--default">停用</span>';
        }

        function renderDetail(data) {
            if (!overlayBody) {
                return;
            }
            var allowList = Array.isArray(data.allow_list) ? data.allow_list : [];
            var proxyList = Array.isArray(data.proxy_list) ? data.proxy_list : [];
            var proxyReady = Number(data.proxy_ready) === 1;
            var html = '';
            html += '<div class="admin-ip-detail">';
            html += '<div class="admin-ip-detail__user">';
            if (data.avatar) {
                html += '<img class="content-author-cell__avatar" src="' + esc(data.avatar)
                    + '" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer">';
            }
            html += '<div><div class="admin-ip-detail__name">' + esc(data.username || '') + '</div>';
            html += '<div class="admin-ip-detail__email">' + esc(data.email || '—') + '</div></div></div>';

            html += '<section class="admin-ip-detail__section">';
            html += '<div class="admin-ip-detail__section-head">';
            html += '<h3>IP 白名单 <span class="vs-muted">(' + allowList.length + '/'
                + esc(data.allow_max || 32) + ')</span></h3>';
            if (allowList.length > 0) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--danger" data-ip-clear-allow>清空</button>';
            }
            html += '</div>';
            if (allowList.length === 0) {
                html += '<p class="vs-muted">未配置白名单（不限制调用 IP）</p>';
            } else {
                html += '<ul class="admin-ip-allow-list">';
                allowList.forEach(function (ip) {
                    html += '<li><code class="vs-log-mono">' + esc(ip) + '</code>'
                        + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-ip-remove-allow="'
                        + esc(ip) + '">移除</button></li>';
                });
                html += '</ul>';
            }
            html += '</section>';

            html += '<section class="admin-ip-detail__section">';
            html += '<div class="admin-ip-detail__section-head">';
            html += '<h3>出口代理 <span class="vs-muted">(' + proxyList.length + '/'
                + esc(data.proxy_max || 5) + ')</span></h3>';
            if (proxyReady && proxyList.length > 0) {
                html += '<span class="vs-badge vs-badge--info">' + esc(data.strategy_label || '轮询') + '</span>';
            }
            html += '</div>';
            if (!proxyReady) {
                html += '<p class="vs-muted">出口代理表尚未就绪</p>';
            } else if (proxyList.length === 0) {
                html += '<p class="vs-muted">未配置出口代理</p>';
            } else {
                html += '<div class="admin-ip-proxy-list">';
                proxyList.forEach(function (p) {
                    var on = Number(p.status) === 1;
                    var isExtract = Number(p.mode) === 1;
                    var endpoint = isExtract
                        ? esc(p.extract || '—')
                        : (esc(p.host || '') + ':' + esc(p.port || ''));
                    html += '<div class="admin-ip-proxy-item" data-proxy-id="' + esc(p.id) + '">';
                    html += '<div class="admin-ip-proxy-item__main">';
                    html += '<div class="admin-ip-proxy-item__title">' + esc(p.title || ('代理#' + p.id)) + '</div>';
                    html += '<div class="admin-ip-proxy-item__meta">'
                        + statusBadge(on) + ' '
                        + '<span class="vs-badge vs-badge--default">' + esc(p.modelabel || '') + '</span> '
                        + '<span class="vs-badge vs-badge--default">' + esc(p.protolabel || '') + '</span>'
                        + (p.haspass ? ' <span class="vs-badge vs-badge--warning">有密码</span>' : '')
                        + '</div>';
                    html += '<div class="admin-ip-proxy-item__endpoint vs-log-mono">' + endpoint + '</div>';
                    if (p.proxycode) {
                        html += '<div class="admin-ip-proxy-item__code">代码 <code>' + esc(p.proxycode) + '</code></div>';
                    }
                    html += '</div>';
                    html += '<div class="admin-ip-proxy-item__actions action-btns">';
                    html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-ip-proxy-toggle="'
                        + esc(p.id) + '" data-status="' + (on ? '0' : '1') + '">'
                        + (on ? '停用' : '启用') + '</button>';
                    html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--danger" data-ip-proxy-delete="'
                        + esc(p.id) + '">删除</button>';
                    html += '</div></div>';
                });
                html += '</div>';
            }
            html += '</section></div>';
            overlayBody.innerHTML = html;
        }

        function syncRowFromOverview(userId) {
            return post({ action: 'overview' }).then(function (ov) {
                var pack = ajaxExtra(ov);
                if (!ok(ov) || !Array.isArray(pack.list)) {
                    return;
                }
                var hit = null;
                pack.list.forEach(function (row) {
                    if (Number(row.userid) === Number(userId)) {
                        hit = row;
                    }
                });
                var nodes = page.querySelectorAll('[data-ip-row="' + userId + '"]');
                if (!hit) {
                    Array.prototype.forEach.call(nodes, function (el) {
                        el.remove();
                    });
                    applySearch();
                    return;
                }
                var allowCount = Number(hit.allow_count) || 0;
                var proxyCount = Number(hit.proxy_count) || 0;
                var preview = hit.allow_preview || '—';
                var strategy = hit.strategy_label || '';
                Array.prototype.forEach.call(nodes, function (el) {
                    if (el.classList.contains('admin-ip-card')) {
                        var tags = el.querySelector('.admin-ip-card__tags');
                        if (tags) {
                            tags.innerHTML = '<span class="vs-badge vs-badge--info">白名单 '
                                + allowCount + '</span><span class="vs-badge vs-badge--success">代理 '
                                + proxyCount + '</span>';
                        }
                        var meta = el.querySelectorAll('.admin-ip-card__meta span');
                        if (meta && meta[1]) {
                            meta[1].textContent = preview;
                        }
                    } else {
                        var cells = el.querySelectorAll('td');
                        if (cells.length >= 4) {
                            cells[2].innerHTML = '<span class="vs-badge '
                                + (allowCount > 0 ? 'vs-badge--info' : 'vs-badge--default')
                                + '">' + allowCount + ' 条</span>'
                                + '<div class="vs-admin-ip-preview">' + esc(preview) + '</div>';
                            cells[3].innerHTML = '<span class="vs-badge '
                                + (proxyCount > 0 ? 'vs-badge--success' : 'vs-badge--default')
                                + '">' + proxyCount + ' 条</span>'
                                + (proxyCount > 0 && strategy
                                    ? '<span class="vs-admin-ip-strategy">' + esc(strategy) + '</span>'
                                    : '');
                        }
                    }
                    var search = String(el.getAttribute('data-search') || '');
                    // keep username/email; refresh IP preview part lightly
                    el.setAttribute('data-search', search.replace(/\s[\d.:a-f,…]+\s*$/i, '') + ' ' + preview);
                });
                applySearch();
            });
        }

        function loadDetail(userId, keepOpen) {
            if (busy && !keepOpen) {
                return;
            }
            busy = true;
            currentUserId = userId;
            if (!keepOpen) {
                if (overlayTitle) {
                    overlayTitle.textContent = '用户 IP 配置';
                }
                if (overlayBody) {
                    overlayBody.innerHTML = '<p class="vs-muted">加载中…</p>';
                }
                openOverlay();
            }
            return post({ action: 'detail', user_id: userId }).then(function (res) {
                busy = false;
                if (!ok(res)) {
                    toast(msg(res, '加载失败'), 'error');
                    if (!keepOpen) {
                        closeOverlay();
                    }
                    return;
                }
                var data = ajaxExtra(res);
                if (overlayTitle) {
                    overlayTitle.textContent = (data.username || '用户') + ' · IP 配置';
                }
                renderDetail(data);
            }).catch(function () {
                busy = false;
                toast('网络错误', 'error');
                if (!keepOpen) {
                    closeOverlay();
                }
            });
        }

        function afterMutate(res) {
            if (!ok(res)) {
                toast(msg(res, '操作失败'), 'error');
                return Promise.resolve();
            }
            toast(msg(res, '已更新'), 'success');
            return Promise.all([
                loadDetail(currentUserId, true),
                syncRowFromOverview(currentUserId)
            ]);
        }

        page.addEventListener('click', function (ev) {
            var btn = ev.target && ev.target.closest
                ? ev.target.closest('.vs-admin-ip-view')
                : null;
            if (!btn || !page.contains(btn)) {
                return;
            }
            var uid = parseInt(btn.getAttribute('data-user-id'), 10) || 0;
            if (uid > 0) {
                loadDetail(uid, false);
            }
        });

        if (overlay) {
            overlay.addEventListener('click', function (ev) {
                if (ev.target === overlay) {
                    closeOverlay();
                    return;
                }
                var closeBtn = ev.target.closest
                    ? ev.target.closest('[data-ip-overlay-close]')
                    : null;
                if (closeBtn) {
                    closeOverlay();
                    return;
                }
                if (busy || !currentUserId) {
                    return;
                }

                var clearBtn = ev.target.closest
                    ? ev.target.closest('[data-ip-clear-allow]')
                    : null;
                if (clearBtn) {
                    confirmDanger('确定清空该用户的全部 IP 白名单？清空后不限制调用 IP。', '清空白名单')
                        .then(function (yes) {
                            if (!yes) {
                                return;
                            }
                            busy = true;
                            return post({ action: 'clear_allow', user_id: currentUserId })
                                .then(afterMutate)
                                .finally(function () { busy = false; });
                        });
                    return;
                }

                var removeBtn = ev.target.closest
                    ? ev.target.closest('[data-ip-remove-allow]')
                    : null;
                if (removeBtn) {
                    var ip = removeBtn.getAttribute('data-ip-remove-allow') || '';
                    confirmDanger('确定从白名单移除「' + ip + '」？', '移除 IP').then(function (yes) {
                        if (!yes) {
                            return;
                        }
                        busy = true;
                        return post({ action: 'remove_allow', user_id: currentUserId, ip: ip })
                            .then(afterMutate)
                            .finally(function () { busy = false; });
                    });
                    return;
                }

                var toggleBtn = ev.target.closest
                    ? ev.target.closest('[data-ip-proxy-toggle]')
                    : null;
                if (toggleBtn) {
                    var tid = parseInt(toggleBtn.getAttribute('data-ip-proxy-toggle'), 10) || 0;
                    var st = parseInt(toggleBtn.getAttribute('data-status'), 10);
                    if (tid <= 0) {
                        return;
                    }
                    busy = true;
                    post({
                        action: 'proxy_toggle',
                        user_id: currentUserId,
                        proxy_id: tid,
                        status: st
                    }).then(afterMutate).finally(function () { busy = false; });
                    return;
                }

                var delBtn = ev.target.closest
                    ? ev.target.closest('[data-ip-proxy-delete]')
                    : null;
                if (delBtn) {
                    var did = parseInt(delBtn.getAttribute('data-ip-proxy-delete'), 10) || 0;
                    if (did <= 0) {
                        return;
                    }
                    confirmDanger('删除后不可恢复，确定删除该出口代理？', '删除代理').then(function (yes) {
                        if (!yes) {
                            return;
                        }
                        busy = true;
                        return post({
                            action: 'proxy_delete',
                            user_id: currentUserId,
                            proxy_id: did
                        }).then(afterMutate).finally(function () { busy = false; });
                    });
                }
            });
        }

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && overlay && overlay.classList.contains('is-open')) {
                closeOverlay();
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', applySearch);
        }
        applySearch();
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
