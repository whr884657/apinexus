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
        if (typeHint) {
            typeHint.textContent = t === 1
                ? '外链接口：填写对方完整地址；系统生成本站 /apis/短码，访问时跳转上游并附带参数。'
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
        return window.VS.postForm(fd, window.location.pathname);
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

    function buildRowHtml(api) {
        var id = parseInt(api.id, 10) || 0;
        var reason = api.rejectreason ? String(api.rejectreason) : '';
        var callUrl = api.call_url || api.endpoint || '';
        var html = '';
        html += '<div class="vs-user-api-row" data-api-row="' + id + '">';
        html += '<div class="vs-user-api-row__main">';
        html += '<div class="vs-user-api-row__title">';
        html += '<strong data-field="name">' + escapeHtml(api.name || '') + '</strong>';
        html += '<span class="vs-api-list-audit ' + auditClass(api.audit) + '" data-field="audit_label">'
            + escapeHtml(api.audit_label || '') + '</span>';
        if (api.apitype_label) {
            html += '<span class="vs-user-api-type">' + escapeHtml(api.apitype_label) + '</span>';
        }
        html += '</div>';
        html += '<div class="vs-user-api-row__meta"><span>' + escapeHtml(api.method || 'GET')
            + '</span> · <span>' + escapeHtml(callUrl) + '</span></div>';
        html += '<p class="vs-user-api-row__reason" data-field="rejectreason"' + (reason ? '' : ' hidden') + '>';
        html += reason ? ('未通过原因：' + escapeHtml(reason)) : '';
        html += '</p></div>';
        html += '<div class="vs-user-api-row__actions">';
        html += '<button type="button" class="vs-btn vs-btn--default vs-user-api-edit" data-api-id="' + id + '">编辑</button>';
        html += '<button type="button" class="vs-btn vs-btn--danger vs-user-api-delete" data-api-id="' + id + '">删除</button>';
        html += '</div></div>';
        return html;
    }

    function upsertRow(api) {
        if (!listEl || !api) {
            return;
        }
        var id = String(api.id);
        var existing = listEl.querySelector('.vs-user-api-row[data-api-row="' + id + '"]');
        var temp = document.createElement('div');
        temp.innerHTML = buildRowHtml(api);
        var node = temp.firstChild;
        if (existing && node) {
            existing.parentNode.replaceChild(node, existing);
        } else if (node) {
            listEl.insertBefore(node, listEl.firstChild);
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
            iconCtl.select(defaultIcons.length ? defaultIcons[0] : '');
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
                    iconCtl.select('');
                }
            } else {
                iconUrlInput.value = '';
                if (iconCtl) {
                    iconCtl.select(api.icon || (defaultIcons[0] || ''));
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
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = collectPayload();
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
