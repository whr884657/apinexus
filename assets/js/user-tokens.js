/**
 * 用户中心 · 令牌管理
 */
(function () {
    var page = document.getElementById('userTokenPage');
    if (!page || !window.VS) {
        return;
    }

    var listEl = document.getElementById('userTokenList');
    var emptyEl = document.getElementById('userTokenEmpty');
    var footerEl = document.getElementById('userTokenFooter');
    var statsEl = document.getElementById('userTokenStats');
    var addBtn = document.getElementById('userTokenAddBtn');
    var formOverlay = document.getElementById('userTokenFormOverlay');
    var form = document.getElementById('userTokenForm');
    var formTitle = document.getElementById('userTokenFormTitle');
    var formId = document.getElementById('userTokenFormId');
    var remarkInput = document.getElementById('userTokenFormRemark');
    var submitBtn = document.getElementById('userTokenFormSubmitBtn');
    var formMode = 'create';
    var maxTokens = parseInt(page.getAttribute('data-token-max') || '3', 10) || 3;

    if (formOverlay && formOverlay.parentNode !== document.body) {
        document.body.appendChild(formOverlay);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

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

    function openOverlay() {
        if (!formOverlay) {
            return;
        }
        formOverlay.hidden = false;
        formOverlay.setAttribute('aria-hidden', 'false');
        formOverlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
        if (remarkInput) {
            setTimeout(function () {
                remarkInput.focus();
            }, 50);
        }
    }

    function closeOverlay() {
        if (!formOverlay) {
            return;
        }
        formOverlay.hidden = true;
        formOverlay.setAttribute('aria-hidden', 'true');
        formOverlay.classList.remove('is-open');
        document.body.classList.remove('is-overlay-open');
    }

    function tokenCount() {
        return listEl ? listEl.querySelectorAll('.vs-token-row').length : 0;
    }

    function syncEmptyAndStats() {
        var count = tokenCount();
        page.setAttribute('data-token-count', String(count));
        if (emptyEl) {
            emptyEl.hidden = count > 0;
        }
        if (listEl) {
            listEl.hidden = count === 0;
        }
        if (footerEl) {
            footerEl.hidden = count === 0;
        }
        if (statsEl) {
            statsEl.textContent = '共 ' + count + ' 个令牌（上限 ' + maxTokens + '）';
        }
        if (addBtn) {
            addBtn.disabled = count >= maxTokens;
            if (count >= maxTokens) {
                addBtn.setAttribute('title', '已达上限');
            } else {
                addBtn.removeAttribute('title');
            }
        }
    }

    function listBody() {
        if (!listEl) {
            return null;
        }
        return listEl.querySelector('.vs-api-list-table__body') || listEl;
    }

    function buildRowHtml(token) {
        var id = parseInt(token.id, 10) || 0;
        var enabled = parseInt(token.status, 10) === 1;
        var statusClass = enabled ? 'is-enabled' : 'is-disabled';
        var html = '';
        html += '<div class="vs-api-item vs-token-row' + (enabled ? '' : ' is-token-disabled') + '" data-token-row="' + id + '" data-token-status="' + (enabled ? '1' : '0') + '">';
        html += '<div class="vs-api-item__icon vs-token-row__icon" aria-hidden="true"><span class="vs-token-row__icon-mark">SK</span></div>';
        html += '<div class="vs-api-item__title">';
        html += '<span class="vs-api-item__name" data-field="remark">' + escapeHtml(token.remark || '') + '</span>';
        html += '<span class="vs-api-item__id">#' + id + '</span>';
        html += '</div>';
        html += '<div class="vs-api-item__endpoint vs-token-row__secret">';
        html += '<code class="vs-token-row__code" data-field="secret" title="' + escapeHtml(token.secret || '') + '">' + escapeHtml(token.secret || '') + '</code>';
        html += '</div>';
        html += '<div class="vs-api-item__tags">';
        html += '<span class="vs-api-tag vs-api-tag--status ' + statusClass + '" data-field="status_label">' + escapeHtml(token.status_label || '') + '</span>';
        html += '</div>';
        html += '<div class="vs-api-item__meta">';
        html += '<div class="vs-api-item__calls" title="调用次数">调用：<strong data-field="calls">' + (parseInt(token.calls, 10) || 0) + '</strong></div>';
        html += '<div class="vs-api-item__author" data-field="createtime" title="创建时间">' + escapeHtml(token.createtime || '') + '</div>';
        html += '</div>';
        html += '<div class="vs-api-item__actions vs-token-row__actions">';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-edit" data-token-id="' + id + '">编辑</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-reset" data-token-id="' + id + '">重置</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-toggle" data-token-id="' + id + '" data-status="' + (enabled ? '0' : '1') + '">' + (enabled ? '禁用' : '启用') + '</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-token-delete" data-token-id="' + id + '">删除</button>';
        html += '</div></div>';
        return html;
    }

    function upsertRow(token) {
        var body = listBody();
        if (!body || !token) {
            return;
        }
        var id = String(token.id);
        var existing = body.querySelector('.vs-token-row[data-token-row="' + id + '"]');
        var wrap = document.createElement('div');
        wrap.innerHTML = buildRowHtml(token);
        var node = wrap.firstChild;
        if (existing && node) {
            existing.parentNode.replaceChild(node, existing);
        } else if (node) {
            body.insertBefore(node, body.firstChild);
        }
        syncEmptyAndStats();
    }

    function openCreate() {
        if (tokenCount() >= maxTokens) {
            window.VS.showMessage('每个账号最多 ' + maxTokens + ' 个令牌', 'error');
            return;
        }
        formMode = 'create';
        if (formTitle) {
            formTitle.textContent = '添加令牌';
        }
        if (formId) {
            formId.value = '';
        }
        if (remarkInput) {
            remarkInput.value = '';
        }
        if (submitBtn) {
            submitBtn.textContent = '确定';
        }
        openOverlay();
    }

    function openEdit(tokenId) {
        var row = listEl && listEl.querySelector('.vs-token-row[data-token-row="' + tokenId + '"]');
        if (!row) {
            return;
        }
        var remarkEl = row.querySelector('[data-field="remark"]');
        formMode = 'update';
        if (formTitle) {
            formTitle.textContent = '编辑令牌';
        }
        if (formId) {
            formId.value = String(tokenId);
        }
        if (remarkInput) {
            remarkInput.value = remarkEl ? remarkEl.textContent : '';
        }
        if (submitBtn) {
            submitBtn.textContent = '保存';
        }
        openOverlay();
    }

    if (addBtn) {
        addBtn.addEventListener('click', openCreate);
    }

    if (formOverlay) {
        formOverlay.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-overlay-close') === '1') {
                closeOverlay();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && formOverlay && formOverlay.classList.contains('is-open')) {
            closeOverlay();
        }
    });

    page.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.vs-token-edit');
        if (editBtn && page.contains(editBtn)) {
            openEdit(editBtn.getAttribute('data-token-id'));
            return;
        }

        var resetBtn = e.target.closest('.vs-token-reset');
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

        var toggleBtn = e.target.closest('.vs-token-toggle');
        if (toggleBtn && page.contains(toggleBtn)) {
            var toggleId = toggleBtn.getAttribute('data-token-id');
            var nextStatus = toggleBtn.getAttribute('data-status') || '0';
            postAction('set_status', { token_id: toggleId, status: nextStatus }).then(function (data) {
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

        var delBtn = e.target.closest('.vs-token-delete');
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
                    syncEmptyAndStats();
                });
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        }
    });

    if (form) {
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var remark = remarkInput ? String(remarkInput.value || '').trim() : '';
            if (!remark) {
                window.VS.showMessage('请填写令牌名称', 'error');
                if (remarkInput) {
                    remarkInput.focus();
                }
                return;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            var action = formMode === 'update' ? 'update' : 'create';
            var payload = { remark: remark };
            if (action === 'update') {
                payload.token_id = formId ? formId.value : '';
            }
            postAction(action, payload).then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已保存', 'success');
                closeOverlay();
                if (data.token) {
                    upsertRow(data.token);
                }
            }).catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        });
    }

    syncEmptyAndStats();
})();
