/**
 * 管理员 · 令牌管理
 */
(function () {
    var page = document.getElementById('adminTokenPage');
    if (!page || !window.VS) {
        return;
    }

    var listEl = document.getElementById('adminTokenList');
    var emptyEl = document.getElementById('adminTokenEmpty');
    var footerEl = document.getElementById('adminTokenFooter');
    var statsEl = document.getElementById('adminTokenStats');
    var pagerEl = document.getElementById('adminTokenPager');
    var pagerNumsEl = document.getElementById('adminTokenPagerNums');
    var prevBtn = document.getElementById('adminTokenPrevBtn');
    var nextBtn = document.getElementById('adminTokenNextBtn');
    var pageSizeEl = document.getElementById('adminTokenPageSize');
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

    function allRows() {
        return listEl ? Array.prototype.slice.call(listEl.querySelectorAll('.vs-token-row')) : [];
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        return n > 0 ? n : 20;
    }

    function syncEmpty() {
        var rows = allRows();
        var total = rows.length;
        if (emptyEl) {
            emptyEl.hidden = total > 0;
        }
        if (listEl) {
            listEl.hidden = total === 0;
        }
        if (footerEl) {
            footerEl.hidden = total === 0;
        }
        if (statsEl) {
            statsEl.textContent = '共 ' + total + ' 个令牌';
        }
        page.setAttribute('data-token-total', String(total));
    }

    function renderPagerNums(totalPages) {
        if (!pagerNumsEl) {
            return;
        }
        pagerNumsEl.innerHTML = '';
        var maxShow = 7;
        var start = 1;
        var end = totalPages;
        if (totalPages > maxShow) {
            start = Math.max(1, currentPage - 3);
            end = Math.min(totalPages, start + maxShow - 1);
            start = Math.max(1, end - maxShow + 1);
        }
        var i;
        for (i = start; i <= end; i += 1) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-api-pager__num' + (i === currentPage ? ' is-active' : '');
            btn.textContent = String(i);
            btn.setAttribute('data-page', String(i));
            pagerNumsEl.appendChild(btn);
        }
    }

    function applyListView() {
        var rows = allRows();
        var size = getPageSize();
        var totalPages = Math.max(1, Math.ceil(rows.length / size) || 1);
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }
        var start = (currentPage - 1) * size;
        var end = start + size;
        rows.forEach(function (row, idx) {
            row.hidden = !(idx >= start && idx < end);
        });
        if (pagerEl) {
            pagerEl.hidden = rows.length === 0;
        }
        renderPagerNums(totalPages);
        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages || rows.length === 0;
        }
        syncEmpty();
    }

    function buildRowHtml(token) {
        var id = parseInt(token.id, 10) || 0;
        var enabled = parseInt(token.status, 10) === 1;
        var statusClass = enabled ? 'is-enabled' : 'is-disabled';
        var username = token.username || ('用户#' + (token.userid || ''));
        var html = '';
        html += '<div class="vs-api-item vs-token-row' + (enabled ? '' : ' is-token-disabled') + '" data-token-row="' + id + '" data-token-status="' + (enabled ? '1' : '0') + '">';
        html += '<div class="vs-api-item__icon vs-token-row__icon" aria-hidden="true"><span class="vs-token-row__icon-mark">SK</span></div>';
        html += '<div class="vs-api-item__title"><span class="vs-api-item__name" data-field="remark">' + escapeHtml(token.remark || '') + '</span>';
        html += '<span class="vs-api-item__id">#' + id + '</span></div>';
        html += '<div class="vs-api-item__endpoint vs-token-row__secret"><code class="vs-token-row__code" data-field="secret" title="' + escapeHtml(token.secret || '') + '">' + escapeHtml(token.secret || '') + '</code></div>';
        html += '<div class="vs-api-item__tags"><span class="vs-api-tag vs-api-tag--status ' + statusClass + '" data-field="status_label">' + escapeHtml(token.status_label || '') + '</span></div>';
        html += '<div class="vs-api-item__meta"><div class="vs-api-item__calls">调用：<strong data-field="calls">' + (parseInt(token.calls, 10) || 0) + '</strong></div>';
        html += '<div class="vs-api-item__author" data-field="username">' + escapeHtml(username) + '</div></div>';
        html += '<div class="vs-api-item__actions">';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-admin-token-reset" data-token-id="' + id + '">重置</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-admin-token-toggle" data-token-id="' + id + '" data-status="' + (enabled ? '0' : '1') + '">' + (enabled ? '禁用' : '启用') + '</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-admin-token-delete" data-token-id="' + id + '">删除</button>';
        html += '</div></div>';
        return html;
    }

    function upsertRow(token) {
        var body = listEl && (listEl.querySelector('.vs-api-list-table__body') || listEl);
        if (!body || !token) {
            return;
        }
        var id = String(token.id);
        var existing = body.querySelector('.vs-token-row[data-token-row="' + id + '"]');
        if (existing && !token.username) {
            var nameEl = existing.querySelector('[data-field="username"]');
            if (nameEl) {
                token.username = nameEl.textContent || '';
            }
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = buildRowHtml(token);
        var node = wrap.firstChild;
        if (existing && node) {
            existing.parentNode.replaceChild(node, existing);
        }
        applyListView();
    }

    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            currentPage = 1;
            applyListView();
        });
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            currentPage -= 1;
            applyListView();
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            currentPage += 1;
            applyListView();
        });
    }
    if (pagerNumsEl) {
        pagerNumsEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-api-pager__num');
            if (!btn) {
                return;
            }
            currentPage = parseInt(btn.getAttribute('data-page'), 10) || 1;
            applyListView();
        });
    }

    page.addEventListener('click', function (e) {
        var resetBtn = e.target.closest('.vs-admin-token-reset');
        if (resetBtn && page.contains(resetBtn)) {
            var resetId = resetBtn.getAttribute('data-token-id');
            var resetConfirm = window.VsModal && window.VsModal.confirm
                ? window.VsModal.confirm('重置后旧密钥立即失效，确定继续？', '重置令牌')
                : Promise.resolve(window.confirm('确定重置该令牌？'));
            resetConfirm.then(function (ok) {
                if (!ok) {
                    return;
                }
                return postAction('reset', { token_id: resetId }).then(function (data) {
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '重置失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已重置', 'success');
                    if (data.token) {
                        upsertRow(data.token);
                    }
                });
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        var toggleBtn = e.target.closest('.vs-admin-token-toggle');
        if (toggleBtn && page.contains(toggleBtn)) {
            postAction('set_status', {
                token_id: toggleBtn.getAttribute('data-token-id'),
                status: toggleBtn.getAttribute('data-status') || '0'
            }).then(function (data) {
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已更新', 'success');
                if (data.token) {
                    upsertRow(data.token);
                }
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        var delBtn = e.target.closest('.vs-admin-token-delete');
        if (delBtn && page.contains(delBtn)) {
            var delId = delBtn.getAttribute('data-token-id');
            var delConfirm = window.VsModal && window.VsModal.confirm
                ? window.VsModal.confirm('删除后不可恢复，确定删除该令牌？', '删除令牌')
                : Promise.resolve(window.confirm('确定删除该令牌？'));
            delConfirm.then(function (ok) {
                if (!ok) {
                    return;
                }
                return postAction('delete', { token_id: delId }).then(function (data) {
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '删除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已删除', 'success');
                    var row = listEl && listEl.querySelector('.vs-token-row[data-token-row="' + delId + '"]');
                    if (row && row.parentNode) {
                        row.parentNode.removeChild(row);
                    }
                    applyListView();
                });
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        }
    });

    applyListView();
})();
