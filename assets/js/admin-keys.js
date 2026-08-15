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
        var pagerNumsEl = document.getElementById('adminKeyPagerNums');
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

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function defaultPageSize() {
            return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
            return n > 0 ? n : defaultPageSize();
        }

        function allDesktopRows() {
            if (!listEl) {
                return [];
            }
            return Array.prototype.slice.call(listEl.querySelectorAll('tr[data-token-row]'));
        }

        function getPair(id) {
            var sid = String(id);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-token-row="' + sid + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.key-card[data-token-row="' + sid + '"]') : null
            };
        }

        function syncRowOrder(rows) {
            if (!listEl) {
                return;
            }
            rows.forEach(function (row) {
                listEl.appendChild(row);
                if (mobileEl) {
                    var id = row.getAttribute('data-token-row');
                    var card = mobileEl.querySelector('.key-card[data-token-row="' + id + '"]');
                    if (card) {
                        mobileEl.appendChild(card);
                    }
                }
            });
        }

        function matchedRows() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var st = statusFilter ? String(statusFilter.value || '') : '';
            var all = allDesktopRows();
            var filtered = all.filter(function (row) {
                if (st !== '' && row.getAttribute('data-token-status') !== st) {
                    return false;
                }
                if (!q) {
                    return true;
                }
                var hay = (row.getAttribute('data-search') || '').toLowerCase();
                return hay.indexOf(q) !== -1;
            });
            syncRowOrder(filtered);
            return filtered;
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
                var id = row.getAttribute('data-token-row');
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
                mobileEl.querySelectorAll('.key-card[data-token-row]').forEach(function (card) {
                    var id = card.getAttribute('data-token-row');
                    var desk = listEl ? listEl.querySelector('tr[data-token-row="' + id + '"]') : null;
                    var inMatched = desk && matched.indexOf(desk) !== -1;
                    card.hidden = !(inMatched && visibleIds[id]);
                });
            }

            var hasAny = totalAll > 0;
            var hasVisible = matched.length > 0;
            var q = searchInput ? String(searchInput.value || '').trim() : '';
            var st = statusFilter ? String(statusFilter.value || '') : '';
            var filtered = q !== '' || st !== '';

            if (emptyEl) {
                emptyEl.hidden = hasAny;
            }
            if (searchEmptyEl) {
                searchEmptyEl.hidden = !(hasAny && !hasVisible && filtered);
            }
            if (tableWrapEl) {
                tableWrapEl.hidden = !hasVisible;
            }
            if (mobileEl) {
                mobileEl.hidden = !hasVisible;
            }
            if (footerEl) {
                footerEl.hidden = !hasAny;
            }
            if (statsEl) {
                statsEl.textContent = '共 ' + matched.length + ' 条'
                    + (hasAny && filtered ? ('（全部 ' + totalAll + '）') : '');
            }
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || matched.length === 0;
            }
            renderPagerNums(matched.length === 0 ? 0 : totalPages);
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
                var hay = (el.getAttribute('data-search') || '');
                // refresh search with new secret prefix kept simple
                var user = el.querySelector('[data-field="username"]');
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
                    window.VS.toast('已复制令牌', 'success');
                }).catch(function () {
                    window.VS.toast('复制失败', 'error');
                });
                return;
            }
            window.VS.toast('已复制令牌', 'success');
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
                var tip = nextStatus === 1 ? '确定启用该令牌？' : '确定紧急禁用该令牌？禁用后调用将失败。';
                window.VS.confirm(tip).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    postAction('set_status', { token_id: tid, status: nextStatus }).then(function (res) {
                        if (!res || !res.ok) {
                            window.VS.toast((res && res.msg) || '操作失败', 'error');
                            return;
                        }
                        window.VS.toast(res.msg || '已更新', 'success');
                        setPairStatus(getPair(tid), nextStatus === 1);
                        applyView();
                    });
                });
                return;
            }

            var resetBtn = e.target.closest('.vs-admin-token-reset');
            if (resetBtn) {
                e.preventDefault();
                var rid = resetBtn.getAttribute('data-token-id');
                window.VS.confirm('确定重置该令牌密钥？旧密钥将立即失效。').then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    postAction('reset', { token_id: rid }).then(function (res) {
                        if (!res || !res.ok || !res.data || !res.data.token) {
                            window.VS.toast((res && res.msg) || '重置失败', 'error');
                            return;
                        }
                        window.VS.toast(res.msg || '已重置', 'success');
                        updatePairSecret(getPair(rid), res.data.token.secret || '');
                        applyView();
                    });
                });
                return;
            }

            var delBtn = e.target.closest('.vs-admin-token-delete');
            if (delBtn) {
                e.preventDefault();
                var did = delBtn.getAttribute('data-token-id');
                window.VS.confirm('确定删除该令牌？此操作不可恢复。').then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    postAction('delete', { token_id: did }).then(function (res) {
                        if (!res || !res.ok) {
                            window.VS.toast((res && res.msg) || '删除失败', 'error');
                            return;
                        }
                        window.VS.toast(res.msg || '已删除', 'success');
                        removePair(did);
                    });
                });
            }
        });

        if (pagerNumsEl) {
            pagerNumsEl.addEventListener('click', function (e) {
                var btn = e.target.closest('.vs-api-pager__num');
                if (!btn) {
                    return;
                }
                currentPage = parseInt(btn.getAttribute('data-page') || '1', 10) || 1;
                applyView();
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                currentPage -= 1;
                applyView();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                currentPage += 1;
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
