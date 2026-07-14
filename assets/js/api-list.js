/**
 * 文件：assets/js/api-list.js
 * 作用：后台接口列表（添加 / 编辑 / 状态 / 删除）
 */
(function () {
    'use strict';

    var page = document.getElementById('apiListPage');
    if (!page) {
        return;
    }

    var tableEl = document.getElementById('apiListTable');
    var listEl = document.getElementById('apiListBody');
    var emptyEl = document.getElementById('apiListEmpty');
    var searchEmptyEl = document.getElementById('apiListSearchEmpty');
    var searchInput = document.getElementById('apiListSearchInput');
    var openAddBtn = document.getElementById('apiListOpenAddBtn');
    var formOverlay = document.getElementById('apiListFormOverlay');
    var formEl = document.getElementById('apiListForm');
    var formId = document.getElementById('apiListFormId');
    var formTitle = document.getElementById('apiListFormTitle');
    var formSubmitBtn = document.getElementById('apiListFormSubmitBtn');
    var iconPicker = document.getElementById('apiListIconPicker');
    var iconUrlInput = document.getElementById('apiListIconUrl');
    var iconCtl = null;

    var fields = {
        name: document.getElementById('apiListFormName'),
        description: document.getElementById('apiListFormDesc'),
        method: document.getElementById('apiListFormMethod'),
        status: document.getElementById('apiListFormStatus'),
        endpoint: document.getElementById('apiListFormEndpoint'),
        category: document.getElementById('apiListFormCategory'),
        requireKey: document.getElementById('apiListFormRequireKey'),
        params: document.getElementById('apiListFormParams'),
        response: document.getElementById('apiListFormResponse'),
        docNormal: document.getElementById('apiListFormDocNormal'),
        docAi: document.getElementById('apiListFormDocAi')
    };

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

    var formMode = 'create';
    var returnFocusEl = null;

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

    function requireKeyLabel(v) {
        var n = parseInt(v, 10) || 0;
        if (n === 1) {
            return '必须';
        }
        if (n === 2) {
            return '可选';
        }
        return '不需要';
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

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function safeIconUrl(url) {
        var u = String(url || '').trim();
        if (!u && defaultIcons.length) {
            return defaultIcons[0];
        }
        return u || '';
    }

    function statusClass(status) {
        if (status === 'disabled') {
            return 'is-disabled';
        }
        if (status === 'maintenance') {
            return 'is-maintenance';
        }
        return 'is-normal';
    }

    function getSelectedIconUrl() {
        if (iconUrlInput && iconUrlInput.value.trim()) {
            return iconUrlInput.value.trim();
        }
        if (iconCtl) {
            return iconCtl.getSelected() || (defaultIcons.length ? defaultIcons[0] : '');
        }
        if (!iconPicker) {
            return defaultIcons.length ? defaultIcons[0] : '';
        }
        var sel = iconPicker.querySelector('.vs-api-cat-icon-pick.is-selected');
        if (sel) {
            return sel.getAttribute('data-icon-url') || '';
        }
        return defaultIcons.length ? defaultIcons[0] : '';
    }

    function setIconPickerSelection(url) {
        var normalized = safeIconUrl(url);
        if (iconCtl) {
            iconCtl.setSelected(normalized || url || '');
            var matched = false;
            if (iconPicker) {
                iconPicker.querySelectorAll('.vs-icon-picker__item').forEach(function (btn) {
                    var btnUrl = btn.getAttribute('data-icon-url') || '';
                    if (btnUrl === normalized || btnUrl === url) {
                        iconCtl.setSelected(btnUrl);
                        matched = true;
                    }
                });
            }
            if (iconUrlInput) {
                iconUrlInput.value = matched ? '' : (url || '');
            }
            return;
        }
        if (!iconPicker) {
            return;
        }
        var hit = false;
        iconPicker.querySelectorAll('.vs-api-cat-icon-pick').forEach(function (btn) {
            var btnUrl = btn.getAttribute('data-icon-url') || '';
            var on = btnUrl === normalized || btnUrl === url;
            btn.classList.toggle('is-selected', on);
            if (on) {
                hit = true;
            }
        });
        if (iconUrlInput) {
            iconUrlInput.value = hit ? '' : (url || '');
        }
    }

    function markRowsEnter() {
        page.querySelectorAll('.vs-api-list-row').forEach(function (row, i) {
            row.style.setProperty('--row-i', String(Math.min(i, 20)));
            row.classList.add('is-enter');
        });
    }

    function switchFormTab(tab) {
        page.ownerDocument.querySelectorAll('.vs-api-list-form-tab').forEach(function (btn) {
            var on = btn.getAttribute('data-api-form-tab') === tab;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        page.ownerDocument.querySelectorAll('.vs-api-list-form-pane').forEach(function (pane) {
            var on = pane.getAttribute('data-api-form-pane') === tab;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;
        });
    }

    function buildActionButtons(api) {
        var id = api.id;
        var status = api.status || 'normal';
        var html = '';
        html += '<button type="button" class="vs-btn vs-btn--default vs-api-list-action" data-api-action="edit" data-api-id="' + id + '">编辑</button>';
        if (status !== 'normal') {
            html += '<button type="button" class="vs-btn vs-btn--default vs-api-list-action" data-api-action="normal" data-api-id="' + id + '">正常</button>';
        }
        if (status !== 'maintenance') {
            html += '<button type="button" class="vs-btn vs-btn--default vs-api-list-action" data-api-action="maintenance" data-api-id="' + id + '">维护</button>';
        }
        if (status !== 'disabled') {
            html += '<button type="button" class="vs-btn vs-btn--default vs-api-list-action" data-api-action="disable" data-api-id="' + id + '">禁用</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--danger vs-api-list-action" data-api-action="delete" data-api-id="' + id + '">删除</button>';
        return html;
    }

    function buildItemHtml(api) {
        var icon = safeIconUrl(api.icon);
        var desc = api.description || '';
        var method = (api.method || 'GET').toUpperCase();
        var status = api.status || 'normal';
        var search = (String(api.name || '') + ' ' + String(api.endpoint || '') + ' ' + String(api.category || '')).toLowerCase();
        var payload = escapeHtml(JSON.stringify(api));

        var html = '<div class="vs-api-list-row" data-api-row="' + api.id + '"'
            + ' data-api-status="' + escapeHtml(status) + '"'
            + ' data-search="' + escapeHtml(search) + '"'
            + ' data-payload="' + payload + '">';
        html += '<div class="vs-api-list-row__icon"><img src="' + escapeHtml(icon) + '" alt="" width="32" height="32" loading="lazy" data-field="icon"></div>';
        html += '<div class="vs-api-list-row__main">';
        html += '<div class="vs-api-list-row__name" data-field="name">' + escapeHtml(api.name || '') + '</div>';
        html += '<div class="vs-api-list-row__desc" data-field="description">';
        html += desc ? escapeHtml(desc) : '<span class="vs-api-list-row__empty">—</span>';
        html += '</div></div>';
        html += '<div class="vs-api-list-row__method"><span class="vs-api-list-method vs-api-list-method--' + escapeHtml(method.toLowerCase()) + '" data-field="method">' + escapeHtml(method) + '</span></div>';
        html += '<div class="vs-api-list-row__endpoint" data-field="endpoint" title="' + escapeHtml(api.endpoint || '') + '">' + escapeHtml(api.endpoint || '') + '</div>';
        html += '<div class="vs-api-list-row__calls" data-field="call_count">' + (parseInt(api.call_count, 10) || 0) + '</div>';
        html += '<div class="vs-api-list-row__key" data-field="require_key_label">' + escapeHtml(api.require_key_label || requireKeyLabel(api.require_key)) + '</div>';
        html += '<div class="vs-api-list-row__status"><span class="vs-api-list-status ' + statusClass(status) + '" data-field="status_label">' + escapeHtml(api.status_label || status) + '</span></div>';
        html += '<div class="vs-api-list-row__actions">' + buildActionButtons(api) + '</div>';
        html += '</div>';
        return html;
    }

    function ensureListVisible() {
        if (tableEl) {
            tableEl.hidden = false;
        }
        if (emptyEl) {
            emptyEl.hidden = true;
        }
    }

    function refreshEmptyState() {
        if (!listEl) {
            return;
        }
        var rows = listEl.querySelectorAll('.vs-api-list-row');
        var visible = 0;
        rows.forEach(function (row) {
            if (!row.hidden) {
                visible += 1;
            }
        });
        var hasAny = rows.length > 0;
        if (emptyEl) {
            emptyEl.hidden = hasAny;
        }
        if (tableEl) {
            tableEl.hidden = !hasAny;
        }
        if (searchEmptyEl) {
            searchEmptyEl.hidden = !(hasAny && visible === 0);
        }
    }

    function applySearchFilter() {
        if (!listEl) {
            return;
        }
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        listEl.querySelectorAll('.vs-api-list-row').forEach(function (row) {
            var hay = row.getAttribute('data-search') || '';
            row.hidden = q !== '' && hay.indexOf(q) === -1;
        });
        refreshEmptyState();
    }

    function parseRowPayload(rowEl) {
        try {
            return JSON.parse(rowEl.getAttribute('data-payload') || '{}');
        } catch (e) {
            return null;
        }
    }

    function updateItem(rowEl, api) {
        if (!rowEl || !api) {
            return;
        }
        rowEl.setAttribute('data-api-status', api.status || 'normal');
        rowEl.setAttribute(
            'data-search',
            (String(api.name || '') + ' ' + String(api.endpoint || '') + ' ' + String(api.category || '')).toLowerCase()
        );
        rowEl.setAttribute('data-payload', JSON.stringify(api));

        var nameEl = rowEl.querySelector('[data-field="name"]');
        if (nameEl) {
            nameEl.textContent = api.name || '';
        }
        var descEl = rowEl.querySelector('[data-field="description"]');
        if (descEl) {
            descEl.innerHTML = api.description
                ? escapeHtml(api.description)
                : '<span class="vs-api-list-row__empty">—</span>';
        }
        var methodEl = rowEl.querySelector('[data-field="method"]');
        if (methodEl) {
            var m = (api.method || 'GET').toUpperCase();
            methodEl.textContent = m;
            methodEl.className = 'vs-api-list-method vs-api-list-method--' + m.toLowerCase();
        }
        var endpointEl = rowEl.querySelector('[data-field="endpoint"]');
        if (endpointEl) {
            endpointEl.textContent = api.endpoint || '';
            endpointEl.setAttribute('title', api.endpoint || '');
        }
        var callsEl = rowEl.querySelector('[data-field="call_count"]');
        if (callsEl) {
            callsEl.textContent = String(parseInt(api.call_count, 10) || 0);
        }
        var keyEl = rowEl.querySelector('[data-field="require_key_label"]');
        if (keyEl) {
            keyEl.textContent = api.require_key_label || requireKeyLabel(api.require_key);
        }
        var statusEl = rowEl.querySelector('[data-field="status_label"]');
        if (statusEl) {
            statusEl.textContent = api.status_label || api.status || '';
            statusEl.className = 'vs-api-list-status ' + statusClass(api.status || 'normal');
        }
        var iconImg = rowEl.querySelector('[data-field="icon"]');
        if (iconImg) {
            iconImg.src = safeIconUrl(api.icon);
        }
        var actions = rowEl.querySelector('.vs-api-list-row__actions');
        if (actions) {
            actions.innerHTML = buildActionButtons(api);
        }
    }

    function appendItem(api) {
        ensureListVisible();
        if (!listEl) {
            return;
        }
        listEl.insertAdjacentHTML('afterbegin', buildItemHtml(api));
        applySearchFilter();
    }

    function resetForm() {
        if (formId) {
            formId.value = '';
        }
        if (fields.name) {
            fields.name.value = '';
        }
        if (fields.description) {
            fields.description.value = '';
        }
        if (fields.method) {
            fields.method.value = 'GET';
        }
        if (fields.status) {
            fields.status.value = 'normal';
        }
        if (fields.endpoint) {
            fields.endpoint.value = '';
        }
        if (fields.category) {
            fields.category.value = '';
        }
        if (fields.requireKey) {
            fields.requireKey.value = '0';
        }
        if (fields.params) {
            fields.params.value = '';
        }
        if (fields.response) {
            fields.response.value = '';
        }
        if (fields.docNormal) {
            fields.docNormal.value = '';
        }
        if (fields.docAi) {
            fields.docAi.value = '';
        }
        if (formTitle) {
            formTitle.textContent = '添加接口';
        }
        setIconPickerSelection(defaultIcons.length ? defaultIcons[0] : '');
        switchFormTab('basic');
    }

    function fillForm(api) {
        if (!api) {
            return;
        }
        if (formId) {
            formId.value = String(api.id || '');
        }
        if (fields.name) {
            fields.name.value = api.name || '';
        }
        if (fields.description) {
            fields.description.value = api.description || '';
        }
        if (fields.method) {
            fields.method.value = (api.method || 'GET').toUpperCase();
        }
        if (fields.status) {
            fields.status.value = api.status || 'normal';
        }
        if (fields.endpoint) {
            fields.endpoint.value = api.endpoint || '';
        }
        if (fields.category) {
            fields.category.value = api.category || '';
        }
        if (fields.requireKey) {
            fields.requireKey.value = String(parseInt(api.require_key, 10) || 0);
        }
        if (fields.params) {
            fields.params.value = api.request_params || '';
        }
        if (fields.response) {
            fields.response.value = api.response_example || '';
        }
        if (fields.docNormal) {
            fields.docNormal.value = api.doc_normal || '';
        }
        if (fields.docAi) {
            fields.docAi.value = api.doc_ai || '';
        }
        if (formTitle) {
            formTitle.textContent = '编辑接口';
        }
        setIconPickerSelection(api.icon || api.icon_raw || '');
        switchFormTab('basic');
    }

    function openFormOverlay(mode, rowEl) {
        if (!formOverlay) {
            return;
        }
        returnFocusEl = document.activeElement;
        formMode = mode === 'edit' ? 'edit' : 'create';
        if (formMode === 'edit' && rowEl) {
            fillForm(parseRowPayload(rowEl));
        } else {
            resetForm();
        }
        formOverlay.hidden = false;
        formOverlay.setAttribute('aria-hidden', 'false');
        formOverlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
        if (fields.name) {
            fields.name.focus();
        }
    }

    function closeFormOverlay() {
        if (!formOverlay) {
            return;
        }
        formOverlay.hidden = true;
        formOverlay.setAttribute('aria-hidden', 'true');
        formOverlay.classList.remove('is-open');
        document.body.classList.remove('is-overlay-open');
        if (returnFocusEl && returnFocusEl.focus) {
            returnFocusEl.focus();
        }
        returnFocusEl = null;
    }

    function collectPayload() {
        return {
            name: fields.name ? fields.name.value.trim() : '',
            description: fields.description ? fields.description.value.trim() : '',
            endpoint: fields.endpoint ? fields.endpoint.value.trim() : '',
            method: fields.method ? fields.method.value : 'GET',
            request_params: fields.params ? fields.params.value.trim() : '',
            response_example: fields.response ? fields.response.value : '',
            doc_normal: fields.docNormal ? fields.docNormal.value : '',
            doc_ai: fields.docAi ? fields.docAi.value : '',
            require_key: fields.requireKey ? String(fields.requireKey.value || '0') : '0',
            status: fields.status ? fields.status.value : 'normal',
            icon: getSelectedIconUrl(),
            category: fields.category ? fields.category.value : ''
        };
    }

    function handleFormSubmit() {
        var payload = collectPayload();
        if (!payload.name) {
            window.VS.showMessage('请填写接口名称', 'error');
            switchFormTab('basic');
            if (fields.name) {
                fields.name.focus();
            }
            return;
        }
        if (!payload.endpoint) {
            window.VS.showMessage('请填写接口地址', 'error');
            switchFormTab('basic');
            if (fields.endpoint) {
                fields.endpoint.focus();
            }
            return;
        }
        if (payload.request_params) {
            try {
                var parsed = JSON.parse(payload.request_params);
                if (!Array.isArray(parsed)) {
                    throw new Error('not array');
                }
            } catch (e) {
                window.VS.showMessage('请求参数须为合法 JSON 数组', 'error');
                switchFormTab('params');
                if (fields.params) {
                    fields.params.focus();
                }
                return;
            }
        }

        var action = formMode === 'edit' ? 'update' : 'create';
        if (formMode === 'edit') {
            payload.api_id = formId ? formId.value : '';
        }

        if (formSubmitBtn) {
            formSubmitBtn.disabled = true;
        }
        postAction(action, payload)
            .then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '操作成功', 'success');
                closeFormOverlay();
                var summary = data.api_summary || data.api || {};
                if (formMode === 'edit') {
                    var rowEl = page.querySelector('[data-api-row="' + summary.id + '"]');
                    if (rowEl) {
                        updateItem(rowEl, summary);
                        applySearchFilter();
                    }
                } else {
                    appendItem(summary);
                }
            })
            .catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            })
            .finally(function () {
                if (formSubmitBtn) {
                    formSubmitBtn.disabled = false;
                }
            });
    }

    function confirmDelete() {
        if (window.VsModal && window.VsModal.confirm) {
            return window.VsModal.confirm('删除后不可恢复，确定删除该接口？', '删除接口', {
                confirmText: '删除',
                danger: true
            });
        }
        return Promise.resolve(window.confirm('确定删除该接口？'));
    }

    if (iconUrlInput) {
        iconUrlInput.addEventListener('input', function () {
            if (iconCtl) {
                iconCtl.setSelected('');
            } else if (iconPicker) {
                iconPicker.querySelectorAll('.vs-api-cat-icon-pick').forEach(function (b) {
                    b.classList.remove('is-selected');
                });
            }
        });
    }

    markRowsEnter();

    document.querySelectorAll('.vs-api-list-form-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchFormTab(btn.getAttribute('data-api-form-tab'));
        });
    });

    if (formOverlay) {
        formOverlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', closeFormOverlay);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && formOverlay && formOverlay.classList.contains('is-open')) {
            closeFormOverlay();
        }
    });

    if (formEl) {
        formEl.addEventListener('submit', function (e) {
            e.preventDefault();
            handleFormSubmit();
        });
    }

    if (openAddBtn) {
        openAddBtn.addEventListener('click', function () {
            openFormOverlay('create');
        });
    }


    if (searchInput) {
        searchInput.addEventListener('input', applySearchFilter);
    }

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('.vs-api-list-action');
        if (!btn) {
            return;
        }
        var action = btn.getAttribute('data-api-action');
        var apiId = btn.getAttribute('data-api-id');
        var row = page.querySelector('[data-api-row="' + apiId + '"]');

        if (action === 'edit') {
            postAction('get', { api_id: apiId }).then(function (data) {
                if (data.code !== 1 || !data.api) {
                    window.VS.showMessage(data.msg || '加载失败', 'error');
                    return;
                }
                returnFocusEl = document.activeElement;
                formMode = 'edit';
                fillForm(data.api);
                if (!formOverlay) {
                    return;
                }
                formOverlay.hidden = false;
                formOverlay.setAttribute('aria-hidden', 'false');
                formOverlay.classList.add('is-open');
                document.body.classList.add('is-overlay-open');
                if (fields.name) {
                    fields.name.focus();
                }
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        if (action === 'normal' || action === 'maintenance' || action === 'disable') {
            var statusMap = {
                normal: 'normal',
                maintenance: 'maintenance',
                disable: 'disabled'
            };
            var nextStatus = statusMap[action];
            postAction('set_status', {
                api_id: apiId,
                status: nextStatus
            }).then(function (data) {
                if (data.code !== 1 || !row) {
                    window.VS.showMessage(data.msg || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '状态已更新', 'success');
                var api = parseRowPayload(row) || { id: parseInt(apiId, 10) || 0 };
                api.status = data.status || nextStatus;
                api.status_label = data.status_label || api.status;
                updateItem(row, api);
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        if (action === 'delete') {
            confirmDelete().then(function (ok) {
                if (!ok) {
                    return;
                }
                postAction('delete', { api_id: apiId }).then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '删除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '接口已删除', 'success');
                    if (row) {
                        row.remove();
                    }
                    refreshEmptyState();
                }).catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                });
            });
        }
    });
})();
