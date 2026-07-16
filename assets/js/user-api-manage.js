/**
 * 用户中心 · API 管理（开发者投稿）
 */
(function () {
    var page = document.getElementById('userApiManagePage');
    if (!page || !window.VS) {
        return;
    }

    var listEl = document.getElementById('userApiList');
    var emptyEl = document.getElementById('userApiEmpty');
    var addBtn = document.getElementById('userApiAddBtn');
    var formOverlay = document.getElementById('userApiFormOverlay');
    var form = document.getElementById('userApiForm');
    var formTitle = document.getElementById('userApiFormTitle');
    var formId = document.getElementById('userApiFormId');
    var submitBtn = document.getElementById('userApiFormSubmitBtn');
    var iconPicker = document.getElementById('userApiIconPicker');
    var iconUrlInput = document.getElementById('userApiIconUrl');
    var apiTypeInput = document.getElementById('userApiFormApiType');
    var endpointRow = document.getElementById('userApiEndpointRow');
    var targetRow = document.getElementById('userApiTargetRow');
    var slugRow = document.getElementById('userApiSlugRow');
    var typeHint = document.getElementById('userApiTypeHint');
    var endpointInput = document.getElementById('userApiFormEndpoint');
    var targetInput = document.getElementById('userApiFormTargetUrl');
    var slugInput = document.getElementById('userApiFormProxySlug');
    var iconCtl = null;
    var formMode = 'create';
    var canLocal = page.getAttribute('data-can-local') === '1';

    var iconBase = (page.getAttribute('data-icon-base') || '').replace(/\/$/, '');
    var defaultIcons = [];
    try {
        defaultIcons = JSON.parse(page.getAttribute('data-default-icons') || '[]');
    } catch (e) {
        defaultIcons = [];
    }
    defaultIcons = defaultIcons.map(function (item) {
        var u = String(item || '');
        if (!u) {
            return '';
        }
        if (/^https?:\/\//i.test(u)) {
            return u;
        }
        return iconBase + (u.charAt(0) === '/' ? u : '/' + u);
    }).filter(Boolean);

    if (formOverlay && formOverlay.parentNode !== document.body) {
        document.body.appendChild(formOverlay);
    }

    if (window.VsIconPicker && iconPicker) {
        iconCtl = window.VsIconPicker.mount(iconPicker, defaultIcons, {
            onSelect: function () {
                if (iconUrlInput) {
                    iconUrlInput.value = '';
                }
            }
        });
    }

    function setApiType(type) {
        var t = canLocal ? (parseInt(type, 10) === 1 ? 1 : 0) : 1;
        if (apiTypeInput) {
            apiTypeInput.value = String(t);
        }
        document.querySelectorAll('.vs-user-api-type-tab').forEach(function (btn) {
            var on = parseInt(btn.getAttribute('data-apitype'), 10) === t;
            btn.classList.toggle('vs-btn--primary', on);
            btn.classList.toggle('vs-btn--default', !on);
        });
        if (endpointRow) {
            endpointRow.hidden = t === 1;
        }
        if (endpointInput) {
            endpointInput.required = t === 0;
        }
        if (targetRow) {
            targetRow.hidden = t !== 1;
        }
        if (targetInput) {
            targetInput.required = t === 1;
        }
        if (slugRow) {
            slugRow.hidden = t !== 1;
        }
        if (slugInput) {
            slugInput.required = t === 1;
        }
        if (typeHint) {
            typeHint.textContent = t === 1
                ? '外链接口：填写对方完整地址与短码；系统生成本站 /apis/短码，访问时跳转上游并附带参数。'
                : '本地接口：只填本站路径，如 /api/img/index.php';
        }
    }

    document.querySelectorAll('.vs-user-api-type-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setApiType(btn.getAttribute('data-apitype') || '0');
        });
    });

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

    function getSelectedIconUrl() {
        if (iconUrlInput && iconUrlInput.value.trim()) {
            return iconUrlInput.value.trim();
        }
        if (iconCtl) {
            return iconCtl.getSelected() || (defaultIcons.length ? defaultIcons[0] : '');
        }
        return defaultIcons.length ? defaultIcons[0] : '';
    }

    function openOverlay() {
        if (!formOverlay) {
            return;
        }
        formOverlay.hidden = false;
        formOverlay.setAttribute('aria-hidden', 'false');
        formOverlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
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

    function syncEmpty() {
        var has = listEl && listEl.querySelectorAll('.vs-user-api-row').length > 0;
        if (emptyEl) {
            emptyEl.hidden = has;
        }
        if (listEl) {
            listEl.hidden = !has;
        }
    }

    function listBody() {
        if (!listEl) {
            return null;
        }
        return listEl.querySelector('.vs-api-list-table__body') || listEl;
    }

    function auditClass(audit) {
        var n = parseInt(audit, 10);
        if (n === 1) {
            return 'is-approved';
        }
        if (n === 2) {
            return 'is-rejected';
        }
        return 'is-pending';
    }

    function statusClass(status) {
        var n = parseInt(status, 10);
        if (n === 1) {
            return 'is-disabled';
        }
        if (n === 2) {
            return 'is-maintenance';
        }
        return 'is-normal';
    }

    function methodSlug(method) {
        var m = String(method || 'GET').toLowerCase().replace(/[^a-z0-9]+/g, '');
        return m || 'get';
    }

    function buildStatusButtons(api) {
        var id = parseInt(api.id, 10) || 0;
        var status = parseInt(api.status, 10);
        if (isNaN(status)) {
            status = 0;
        }
        var html = '';
        if (status !== 0) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-user-api-status" data-api-id="' + id + '" data-status="0">正常</button>';
        }
        if (status !== 2) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-user-api-status" data-api-id="' + id + '" data-status="2">维护</button>';
        }
        if (status !== 1) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-user-api-status" data-api-id="' + id + '" data-status="1">禁用</button>';
        }
        return html;
    }

    function buildRowHtml(api) {
        var id = parseInt(api.id, 10) || 0;
        var reason = api.rejectreason ? String(api.rejectreason) : '';
        var callUrl = api.call_url || api.endpoint || '';
        var method = (api.method || 'GET').toUpperCase();
        var audit = parseInt(api.audit, 10);
        if (isNaN(audit)) {
            audit = 0;
        }
        var approved = audit === 1;
        var status = parseInt(api.status, 10);
        if (isNaN(status)) {
            status = 0;
        }
        var keyBadge = api.needkey_badge || '';
        var category = api.category ? String(api.category) : '';
        var icon = api.icon || '';
        var html = '';
        html += '<div class="vs-api-item vs-user-api-row" data-api-row="' + id + '" data-api-status="' + status + '" data-api-audit="' + audit + '">';
        html += '<div class="vs-api-item__icon"><img src="' + escapeHtml(icon) + '" alt="" width="32" height="32" loading="lazy"></div>';
        html += '<div class="vs-api-item__title">';
        html += '<span class="vs-api-item__id">#' + id + '</span>';
        html += '<span class="vs-api-item__name" data-field="name">' + escapeHtml(api.name || '') + '</span>';
        html += '</div>';
        html += '<div class="vs-api-item__tags">';
        if (category) {
            html += '<span class="vs-api-tag vs-api-tag--cat">' + escapeHtml(category) + '</span>';
        }
        html += '<span class="vs-api-tag vs-api-tag--free">免费</span>';
        if (keyBadge) {
            html += '<span class="vs-api-tag vs-api-tag--key">' + escapeHtml(keyBadge) + '</span>';
        }
        if (approved) {
            html += '<span class="vs-api-tag vs-api-tag--status ' + statusClass(status) + '" data-field="status_label">'
                + escapeHtml(api.status_label || '正常') + '</span>';
        } else {
            html += '<span class="vs-api-tag vs-api-tag--audit ' + auditClass(audit) + '" data-field="audit_label">'
                + escapeHtml(api.audit_label || '') + '</span>';
        }
        html += '</div>';
        html += '<div class="vs-api-item__endpoint">';
        html += '<span class="vs-api-list-method vs-api-list-method--' + escapeHtml(methodSlug(method)) + '" data-field="method">' + escapeHtml(method) + '</span>';
        html += '<span class="vs-api-item__url" data-field="call_url" title="' + escapeHtml(callUrl) + '">' + escapeHtml(callUrl) + '</span>';
        html += '</div>';
        html += '<div class="vs-api-item__calls" title="调用次数"><span data-field="calls">' + (parseInt(api.calls, 10) || 0) + '</span></div>';
        html += '<div class="vs-api-item__actions vs-user-api-row__actions">';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-user-api-edit" data-api-id="' + id + '">编辑</button>';
        if (approved) {
            html += buildStatusButtons(api);
        }
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-user-api-delete" data-api-id="' + id + '">删除</button>';
        html += '</div>';
        html += '<p class="vs-api-review-reason vs-user-api-row__reason" data-field="rejectreason"' + (reason ? '' : ' hidden') + '>';
        html += reason ? ('未通过原因：' + escapeHtml(reason)) : '';
        html += '</p></div>';
        return html;
    }

    function upsertRow(api) {
        if (!listEl || !api) {
            return;
        }
        var body = listBody();
        if (!body) {
            return;
        }
        var id = String(api.id);
        var existing = body.querySelector('.vs-user-api-row[data-api-row="' + id + '"]');
        var temp = document.createElement('div');
        temp.innerHTML = buildRowHtml(api);
        var node = temp.firstChild;
        if (existing && node) {
            existing.parentNode.replaceChild(node, existing);
        } else if (node) {
            body.insertBefore(node, body.firstChild);
        }
        syncEmpty();
    }

    function resetForm() {
        formMode = 'create';
        if (formId) {
            formId.value = '';
        }
        if (formTitle) {
            formTitle.textContent = '提交接口';
        }
        if (submitBtn) {
            submitBtn.textContent = '提交审核';
        }
        if (form) {
            form.reset();
        }
        setApiType(canLocal ? 0 : 1);
        if (iconCtl) {
            iconCtl.setSelected(defaultIcons.length ? defaultIcons[0] : '');
        }
        if (iconUrlInput) {
            iconUrlInput.value = '';
        }
    }

    function fillForm(api) {
        formMode = 'edit';
        if (formId) {
            formId.value = String(api.id || '');
        }
        if (formTitle) {
            formTitle.textContent = '编辑接口';
        }
        if (submitBtn) {
            submitBtn.textContent = '重新提交审核';
        }
        var apiType = canLocal ? (parseInt(api.apitype, 10) === 1 ? 1 : 0) : 1;
        setApiType(apiType);
        var map = {
            userApiFormName: api.name,
            userApiFormDesc: api.description,
            userApiFormMethod: api.method || 'GET',
            userApiFormNeedkey: String(api.needkey != null ? api.needkey : 0),
            userApiFormEndpoint: apiType === 0 ? (api.endpoint || '') : '',
            userApiFormTargetUrl: api.targeturl || '',
            userApiFormProxySlug: api.proxyslug || '',
            userApiFormCategory: api.category || '',
            userApiFormParams: api.params || '',
            userApiFormResponse: api.response || '',
            userApiFormDoc: api.doc || '',
            userApiFormAidoc: api.aidoc || ''
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.value = map[id] != null ? map[id] : '';
            }
        });
        var raw = api.icon_raw || '';
        if (iconUrlInput) {
            if (raw && /^https?:\/\//i.test(raw)) {
                iconUrlInput.value = raw;
                if (iconCtl) {
                    iconCtl.setSelected('');
                }
            } else {
                iconUrlInput.value = '';
                if (iconCtl) {
                    iconCtl.setSelected(api.icon || (defaultIcons[0] || ''));
                }
            }
        }
    }

    function collectPayload() {
        var apiType = apiTypeInput ? String(parseInt(apiTypeInput.value, 10) === 1 ? 1 : 0) : (canLocal ? '0' : '1');
        if (!canLocal) {
            apiType = '1';
        }
        return {
            name: (document.getElementById('userApiFormName') || {}).value || '',
            description: (document.getElementById('userApiFormDesc') || {}).value || '',
            apitype: apiType,
            endpoint: endpointInput ? endpointInput.value : '',
            targeturl: targetInput ? targetInput.value : '',
            proxyslug: slugInput ? slugInput.value : '',
            method: (document.getElementById('userApiFormMethod') || {}).value || 'GET',
            needkey: (document.getElementById('userApiFormNeedkey') || {}).value || '0',
            category: (document.getElementById('userApiFormCategory') || {}).value || '',
            params: (document.getElementById('userApiFormParams') || {}).value || '',
            response: (document.getElementById('userApiFormResponse') || {}).value || '',
            doc: (document.getElementById('userApiFormDoc') || {}).value || '',
            aidoc: (document.getElementById('userApiFormAidoc') || {}).value || '',
            icon: getSelectedIconUrl()
        };
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            resetForm();
            openOverlay();
        });
    }

    document.addEventListener('click', function (e) {
        var closeEl = e.target.closest('[data-overlay-close]');
        if (closeEl && formOverlay && formOverlay.contains(closeEl)) {
            closeOverlay();
            return;
        }

        var editBtn = e.target.closest('.vs-user-api-edit');
        if (editBtn && page.contains(editBtn)) {
            var id = editBtn.getAttribute('data-api-id');
            postAction('get', { api_id: id }).then(function (data) {
                if (!data || data.code !== 1 || !data.api) {
                    window.VS.showMessage((data && data.msg) || '加载失败', 'error');
                    return;
                }
                fillForm(data.api);
                openOverlay();
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        var statusBtn = e.target.closest('.vs-user-api-status');
        if (statusBtn && page.contains(statusBtn)) {
            var statusId = statusBtn.getAttribute('data-api-id');
            var nextStatus = statusBtn.getAttribute('data-status');
            postAction('set_status', { api_id: statusId, status: String(nextStatus) }).then(function (data) {
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '状态已更新', 'success');
                if (data.api_summary) {
                    upsertRow(data.api_summary);
                }
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        var delBtn = e.target.closest('.vs-user-api-delete');
        if (delBtn && page.contains(delBtn)) {
            var delId = delBtn.getAttribute('data-api-id');
            var confirmPromise = window.VsModal && window.VsModal.confirm
                ? window.VsModal.confirm('删除后不可恢复，确定删除该接口？', '删除接口')
                : Promise.resolve(window.confirm('确定删除该接口？'));
            confirmPromise.then(function (ok) {
                if (!ok) {
                    return;
                }
                return postAction('delete', { api_id: delId }).then(function (data) {
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '删除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已删除', 'success');
                    var row = listEl && listEl.querySelector('.vs-user-api-row[data-api-row="' + delId + '"]');
                    if (row) {
                        row.parentNode.removeChild(row);
                    }
                    syncEmpty();
                });
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && formOverlay && formOverlay.classList.contains('is-open')) {
            closeOverlay();
        }
    });

    if (form) {
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = collectPayload();
            payload.name = String(payload.name || '').trim();
            payload.endpoint = String(payload.endpoint || '').trim();
            payload.targeturl = String(payload.targeturl || '').trim();
            payload.proxyslug = String(payload.proxyslug || '').trim();
            if (!payload.name) {
                window.VS.showMessage('请填写接口名称', 'error');
                var nameEl = document.getElementById('userApiFormName');
                if (nameEl) {
                    nameEl.focus();
                }
                return;
            }
            var isProxy = parseInt(payload.apitype, 10) === 1;
            if (isProxy) {
                if (!payload.targeturl || !/^https?:\/\//i.test(payload.targeturl)) {
                    window.VS.showMessage('请填写完整的上游地址（以 http:// 或 https:// 开头）', 'error');
                    if (targetInput) {
                        targetInput.focus();
                    }
                    return;
                }
                if (!/^[a-zA-Z0-9]{3,64}$/.test(payload.proxyslug)) {
                    window.VS.showMessage('请填写 3～64 位字母或数字短码', 'error');
                    if (slugInput) {
                        slugInput.focus();
                    }
                    return;
                }
            } else if (!payload.endpoint) {
                window.VS.showMessage('请填写本地接口路径', 'error');
                if (endpointInput) {
                    endpointInput.focus();
                }
                return;
            }
            var action = formMode === 'edit' ? 'update' : 'create';
            if (action === 'update') {
                payload.api_id = formId ? formId.value : '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            postAction(action, payload).then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '提交失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已提交', 'success');
                var api = data.api_summary || data.api || null;
                if (api) {
                    upsertRow(api);
                }
                closeOverlay();
            }).catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        });
    }

    setApiType(canLocal ? 0 : 1);
    syncEmpty();
})();
