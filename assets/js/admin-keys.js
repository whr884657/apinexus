/**
 * 文件：assets/js/admin-keys.js
 * 作用：管理员令牌管理（桌面表格 + 手机卡片；搜索/状态/分页）
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
        var page = document.getElementById('apiKeysPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('adminKeyTableWrap');
        var listEl = document.getElementById('adminKeyBody');
        var mobileEl = document.getElementById('adminKeyMobile');
        var emptyEl = document.getElementById('adminKeyEmpty');
        var searchEmptyEl = document.getElementById('adminKeySearchEmpty');
        var searchInput = document.getElementById('adminKeySearchInput');
        var statusFilter = document.getElementById('adminKeyStatusFilter');
        var pageSizeEl = document.getElementById('adminKeyPageSize');
        var footerEl = document.getElementById('adminKeyFooter');
        var pagerNums = document.getElementById('adminKeyPagerNums');
        var statsEl = document.getElementById('adminKeyStats');
        var prevBtn = document.getElementById('adminKeyPrevBtn');
        var nextBtn = document.getElementById('adminKeyNextBtn');
        var currentPage = 1;

        function postAction(action, payload) {
            var fd = new FormData();
            fd.append('action', action);
            if (payload) {
                Object.keys(payload).forEach(function (key) {
                    fd.append(key, payload[key]);
                });
            }
            return window.VS.postForm(fd);
        }

        /** E326：确认框用 VsModal；禁止调用不存在的 VS.confirm */
        function askConfirm(message, title, options) {
            if (window.VsModal && typeof window.VsModal.confirm === 'function') {
                return window.VsModal.confirm(message, title || '操作确认', options || {});
            }
            return Promise.resolve(window.confirm(message));
        }

        function notify(message, type) {
            if (window.VS && typeof window.VS.showMessage === 'function') {
                window.VS.showMessage(message, type);
                return;
            }
            if (window.VsToast && typeof window.VsToast.show === 'function') {
                window.VsToast.show(message, type === 'error' ? 'error' : 'success');
            }
        }

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function defaultPageSize() {
            return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
            return n > 0 ? n : defaultPageSize();
        }

        function getPair(id) {
            var sid = String(id);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-token-row="' + sid + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.key-card[data-token-row="' + sid + '"]') : null
            };
        }

        /** 对齐日志查询：静态上一页/下一页 + 最多 3 个页码 */
        function renderPagerNav(totalPages, matchedLen) {
            if (!window.VS || !VS.applyLogsPager) {
                return;
            }
            VS.applyLogsPager(currentPage, matchedLen === 0 ? 1 : totalPages, prevBtn, nextBtn, pagerNums);
        }

        var listTotal = parseInt(page.getAttribute('data-token-total') || '0', 10) || 0;

        function applyView() {
            var fd = new FormData();
            fd.append('action', 'list');
            fd.append('q', searchInput ? String(searchInput.value || '').trim() : '');
            fd.append('status', statusFilter ? String(statusFilter.value || '') : '');
            fd.append('page', String(currentPage));
            fd.append('pagesize', String(getPageSize()));
            VS.postForm(fd, window.location.href).then(function (data) {
                if (!data || !data.code) {
                    throw new Error((data && data.msg) || '加载失败');
                }
                if (data.page) {
                    currentPage = parseInt(data.page, 10) || currentPage;
                }
                listTotal = parseInt(data.total, 10) || 0;
                if (listEl) {
                    listEl.innerHTML = data.desktop_html || '';
                }
                if (mobileEl) {
                    mobileEl.innerHTML = data.mobile_html || '';
                }
                page.setAttribute('data-token-total', String(listTotal));
                var hasAny = listTotal > 0;
                var q = searchInput ? String(searchInput.value || '').trim() : '';
                var st = statusFilter ? String(statusFilter.value || '') : '';
                var filtered = q !== '' || st !== '';
                var pageItems = listEl ? listEl.querySelectorAll('tr[data-token-row]').length : 0;
                if (emptyEl) {
                    emptyEl.hidden = hasAny || filtered;
                }
                if (searchEmptyEl) {
                    searchEmptyEl.hidden = !(filtered && pageItems === 0);
                }
                if (tableWrapEl) {
                    tableWrapEl.hidden = pageItems === 0;
                }
                if (mobileEl) {
                    mobileEl.hidden = pageItems === 0;
                }
                if (footerEl) {
                    footerEl.hidden = listTotal === 0 && !filtered;
                }
                if (statsEl) {
                    statsEl.textContent = '共 ' + listTotal + ' 条';
                }
                var totalPages = Math.max(1, Math.ceil(listTotal / getPageSize()) || 1);
                renderPagerNav(totalPages, listTotal);
            }).catch(function (err) {
                if (window.VS && VS.showMessage) {
                    VS.showMessage(err.message || '加载失败', 'error');
                }
            });
        }

        function buildActions(id, enabled) {
            var html = '<div class="action-btns">';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-token-reset" data-token-id="'
                + id + '">重置</button>';
            if (enabled) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-admin-token-toggle" data-token-id="'
                    + id + '" data-status="0">紧急禁用</button>';
            } else {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-admin-token-toggle" data-token-id="'
                    + id + '" data-status="1">启用</button>';
            }
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-admin-token-delete" data-token-id="'
                + id + '">删除</button></div>';
            return html;
        }

        function setPairStatus(pair, enabled) {
            [pair.desktop, pair.mobile].forEach(function (el) {
                if (!el) {
                    return;
                }
                el.setAttribute('data-token-status', enabled ? '1' : '0');
                var badge = el.querySelector('[data-field="status_label"]');
                if (badge) {
                    badge.textContent = enabled ? '正常' : '已禁用';
                    badge.className = 'vs-badge ' + (enabled ? 'vs-badge--success' : 'vs-badge--error');
                }
                var id = el.getAttribute('data-token-row');
                var actions = el.querySelector('[data-field="actions"]');
                if (!actions) {
                    return;
                }
                if (el.classList.contains('key-card')) {
                    var secret = '';
                    var code = el.querySelector('[data-field="secret"]');
                    if (code) {
                        secret = code.getAttribute('data-copy') || code.textContent || '';
                    }
                    actions.innerHTML = '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-key-copy" data-copy="'
                        + escapeHtml(secret) + '">复制</button>' + buildActions(id, enabled);
                } else {
                    actions.innerHTML = buildActions(id, enabled);
                }
            });
        }

        function updatePairSecret(pair, secret) {
            [pair.desktop, pair.mobile].forEach(function (el) {
                if (!el) {
                    return;
                }
                el.querySelectorAll('[data-field="secret"], .vs-key-copy, .key-cell__copy').forEach(function (node) {
                    if (node.tagName === 'CODE' || node.getAttribute('data-field') === 'secret') {
                        node.textContent = secret;
                    }
                    if (node.hasAttribute('data-copy')) {
                        node.setAttribute('data-copy', secret);
                    }
                });
                var user = el.querySelector('[data-field="user_display"]') || el.querySelector('[data-field="username"]');
                var uname = user ? user.textContent : '';
                el.setAttribute('data-search', (secret + ' ' + uname + ' #' + el.getAttribute('data-token-row')).toLowerCase());
            });
        }

        function removePair(id) {
            var pair = getPair(id);
            if (pair.desktop) {
                pair.desktop.remove();
            }
            if (pair.mobile) {
                pair.mobile.remove();
            }
            applyView();
        }

        function copyText(text) {
            var t = String(text || '');
            if (!t) {
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(t).then(function () {
                    notify('已复制令牌', 'success');
                }).catch(function () {
                    notify('复制失败', 'error');
                });
                return;
            }
            notify('已复制令牌', 'success');
        }

        page.addEventListener('click', function (e) {
            var copyBtn = e.target.closest('.vs-key-copy, .key-cell__copy');
            if (copyBtn) {
                e.preventDefault();
                copyText(copyBtn.getAttribute('data-copy') || '');
                return;
            }

            var toggleBtn = e.target.closest('.vs-admin-token-toggle');
            if (toggleBtn) {
                e.preventDefault();
                var tid = toggleBtn.getAttribute('data-token-id');
                var nextStatus = parseInt(toggleBtn.getAttribute('data-status') || '0', 10);
                var isEnable = nextStatus === 1;
                var tip = isEnable ? '确定启用该令牌？' : '确定紧急禁用该令牌？禁用后调用将失败。';
                var title = isEnable ? '启用令牌' : '紧急禁用';
                askConfirm(tip, title, { danger: !isEnable }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    return postAction('set_status', { token_id: tid, status: nextStatus }).then(function (res) {
                        if (!res || Number(res.code) !== 1) {
                            notify((res && res.msg) || '操作失败', 'error');
                            return;
                        }
                        notify(res.msg || '已更新', 'success');
                        setPairStatus(getPair(tid), isEnable);
                        applyView();
                    });
                }).catch(function () {
                    notify('网络异常，请稍后重试', 'error');
                });
                return;
            }

            var resetBtn = e.target.closest('.vs-admin-token-reset');
            if (resetBtn) {
                e.preventDefault();
                var rid = resetBtn.getAttribute('data-token-id');
                askConfirm('确定重置该令牌密钥？旧密钥将立即失效。', '重置令牌', { danger: true }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    return postAction('reset', { token_id: rid }).then(function (res) {
                        var token = res && res.token
                            ? res.token
                            : (res && res.data && res.data.token ? res.data.token : null);
                        if (!res || Number(res.code) !== 1 || !token) {
                            notify((res && res.msg) || '重置失败', 'error');
                            return;
                        }
                        notify(res.msg || '已重置', 'success');
                        updatePairSecret(getPair(rid), token.secret || '');
                        applyView();
                    });
                }).catch(function () {
                    notify('网络异常，请稍后重试', 'error');
                });
                return;
            }

            var delBtn = e.target.closest('.vs-admin-token-delete');
            if (delBtn) {
                e.preventDefault();
                var did = delBtn.getAttribute('data-token-id');
                askConfirm('确定删除该令牌？此操作不可恢复。', '删除令牌', { danger: true }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    return postAction('delete', { token_id: did }).then(function (res) {
                        if (!res || Number(res.code) !== 1) {
                            notify((res && res.msg) || '删除失败', 'error');
                            return;
                        }
                        notify(res.msg || '已删除', 'success');
                        removePair(did);
                    });
                }).catch(function () {
                    notify('网络异常，请稍后重试', 'error');
                });
            }
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (prevBtn.disabled) {
                    return;
                }
                if (currentPage > 1) {
                    currentPage -= 1;
                    applyView();
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (nextBtn.disabled) {
                    return;
                }
                var tp = Math.max(1, Math.ceil(listTotal / getPageSize()) || 1);
                if (currentPage < tp) {
                    currentPage += 1;
                    applyView();
                }
            });
        }
        if (pagerNums) {
            pagerNums.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-page]');
                if (!btn) {
                    return;
                }
                var next = parseInt(btn.getAttribute('data-page'), 10) || 0;
                if (!next || next === currentPage) {
                    return;
                }
                currentPage = next;
                applyView();
            });
        }
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentPage = 1;
                applyView();
            });
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                currentPage = 1;
                applyView();
            });
        }
        if (pageSizeEl) {
            if (!pageSizeEl.value) {
                pageSizeEl.value = String(defaultPageSize());
            }
            pageSizeEl.addEventListener('change', function () {
                currentPage = 1;
                applyView();
            });
        }

        applyView();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
