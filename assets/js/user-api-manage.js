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
    var footerEl = document.getElementById('userApiFooter');
    var statsEl = document.getElementById('userApiStats');
    var pagerEl = document.getElementById('userApiPager');
    var pagerNumsEl = document.getElementById('userApiPagerNums');
    var prevBtn = document.getElementById('userApiPrevBtn');
    var nextBtn = document.getElementById('userApiNextBtn');
    var pageSizeEl = document.getElementById('userApiPageSize');
    var addBtn = document.getElementById('userApiAddBtn');
    var currentPage = 1;
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
        if (window.VsParamsEditor && typeof window.VsParamsEditor.closeTypePicker === 'function') {
            window.VsParamsEditor.closeTypePicker();
        }
        formOverlay.hidden = true;
        formOverlay.setAttribute('aria-hidden', 'true');
        formOverlay.classList.remove('is-open');
        document.body.classList.remove('is-overlay-open');
    }

    function syncEmpty() {
        var rows = listEl ? listEl.querySelectorAll('.vs-user-api-row') : [];
        var has = rows.length > 0;
        if (emptyEl) {
            emptyEl.hidden = has;
        }
        if (listEl) {
            listEl.hidden = !has;
        }
        if (footerEl) {
            footerEl.hidden = !has;
        }
        if (statsEl) {
            statsEl.textContent = '共 ' + rows.length + ' 个接口';
        }
        applyListView();
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        return n > 0 ? n : 20;
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
        if (!listEl) {
            return;
        }
        var rows = Array.prototype.slice.call(listEl.querySelectorAll('.vs-user-api-row'));
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


    function methodDisplay(api) {
        if (api && api.method_label) { return String(api.method_label); }
        if (api && api.methods && api.methods.length) { return api.methods.join(' / '); }
        return String((api && api.method) || 'GET').replace(/,/g, ' / ');
    }
    function getSelectedMethods() {
        var list = [], nodes = document.querySelectorAll('#userApiFormMethodChecks [data-api-method]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].classList.contains('is-on') || nodes[i].checked) {
                list.push(String(nodes[i].getAttribute('data-api-method') || '').toUpperCase());
            }
        }
        return list;
    }
    function setSelectedMethods(value) {
        var set = {}, raw = Array.isArray(value) ? value : String(value || 'GET').split(/[\s,|\/]+/);
        for (var i = 0; i < raw.length; i++) {
            var m = String(raw[i] || '').toUpperCase();
            if (m === 'GET' || m === 'POST') set[m] = true;
        }
        if (!set.GET && !set.POST) set.GET = true;
        var nodes = document.querySelectorAll('#userApiFormMethodChecks [data-api-method]');
        for (var j = 0; j < nodes.length; j++) {
            var key = String(nodes[j].getAttribute('data-api-method') || '').toUpperCase();
            var on = !!set[key];
            nodes[j].classList.toggle('is-on', on);
            nodes[j].setAttribute('aria-pressed', on ? 'true' : 'false');
            if (nodes[j].tagName === 'INPUT') {
                nodes[j].checked = on;
            }
        }
    }
    (function bindUserMethodToggles() {
        var wrap = document.getElementById('userApiFormMethodChecks');
        if (!wrap) return;
        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-api-method]');
            if (!btn || !wrap.contains(btn)) return;
            e.preventDefault();
            var on = !btn.classList.contains('is-on');
            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (getSelectedMethods().length === 0) {
                btn.classList.add('is-on');
                btn.setAttribute('aria-pressed', 'true');
            }
        });
    })();
    function methodSlug(method) {
        var m = String(method || 'GET').toLowerCase().replace(/[^a-z0-9]+/g, '');
        return m || 'get';
    }

    function methodBadgesHtml(api) {
        var methods = (api && api.methods && api.methods.length)
            ? api.methods
            : String((api && (api.method_label || api.method)) || 'GET').split(/[\s,|\/]+/).filter(Boolean);
        if (!methods.length) {
            methods = ['GET'];
        }
        var html = '<span class="vs-api-list-methods" data-field="method">';
        methods.forEach(function (m) {
            html += '<span class="vs-api-list-method vs-api-list-method--' + escapeHtml(methodSlug(m)) + '">'
                + escapeHtml(String(m).toUpperCase()) + '</span>';
        });
        html += '</span>';
        return html;
    }

    var paramsEditor = document.getElementById('userApiParamsEditor');
    if (window.VsParamsEditor && paramsEditor) {
        window.VsParamsEditor.mount(paramsEditor, { hiddenId: 'userApiFormParams' });
    }

    function syncUserKeyParam() {
        var needEl = document.getElementById('userApiFormNeedkey');
        if (!window.VsParamsEditor || !paramsEditor || !needEl) {
            return;
        }
        var need = parseInt(needEl.value, 10) || 0;
        var got = window.VsParamsEditor.getValue(paramsEditor);
        if (got && typeof got === 'object' && got.error) {
            return;
        }
        var rows = [];
        try {
            rows = got ? JSON.parse(got) : [];
        } catch (err) {
            rows = [];
        }
        if (!Array.isArray(rows)) {
            rows = [];
        }
        var keyIdx = -1;
        for (var i = 0; i < rows.length; i++) {
            if (String(rows[i].name || '').toLowerCase() === 'key') {
                keyIdx = i;
                break;
            }
        }
        var autoDesc = '接口调用密钥';
        if (need === 0) {
            if (keyIdx >= 0 && String(rows[keyIdx].description || '') === autoDesc) {
                rows.splice(keyIdx, 1);
                window.VsParamsEditor.setValue(paramsEditor, rows.length ? JSON.stringify(rows, null, 4) : '');
            }
            return;
        }
        var required = need === 1;
        if (keyIdx >= 0) {
            rows[keyIdx].required = required;
            if (!rows[keyIdx].description) {
                rows[keyIdx].description = autoDesc;
            }
        } else {
            rows.unshift({
                name: 'key',
                type: 'string',
                required: required,
                description: autoDesc,
                example: ''
            });
        }
        window.VsParamsEditor.setValue(paramsEditor, JSON.stringify(rows, null, 4));
    }

    function syncUserChargeUi() {
        var charge = document.getElementById('userApiFormCharge');
        var row = document.getElementById('userApiPriceRow');
        var price = document.getElementById('userApiFormPrice');
        var needEl = document.getElementById('userApiFormNeedkey');
        if (!charge || !row) {
            return;
        }
        var paid = String(charge.value) === '1';
        row.hidden = !paid;
        if (!paid && price) {
            price.value = '';
        }
        if (needEl) {
            var optNone = needEl.querySelector('option[value="0"]');
            if (optNone) {
                optNone.disabled = paid;
            }
            if (paid && String(needEl.value) === '0') {
                needEl.value = '1';
                if (window.VSPick && typeof window.VSPick.refresh === 'function') {
                    window.VSPick.refresh(needEl);
                }
            }
        }
        syncUserKeyParam();
    }
    var chargeEl = document.getElementById('userApiFormCharge');
    if (chargeEl) {
        chargeEl.addEventListener('change', syncUserChargeUi);
        syncUserChargeUi();
    }
    var needkeyEl = document.getElementById('userApiFormNeedkey');
    if (needkeyEl) {
        needkeyEl.addEventListener('change', syncUserKeyParam);
    }

    function buildStatusButtons(api) {
        var id = parseInt(api.id, 10) || 0;
        var status = parseInt(api.status, 10);
        if (isNaN(status)) {
            status = 0;
        }
        var html = '';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-normal vs-user-api-status'
            + (status === 0 ? ' is-active' : '') + '" data-api-id="' + id + '" data-status="0">正常</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-maint vs-user-api-status'
            + (status === 2 ? ' is-active' : '') + '" data-api-id="' + id + '" data-status="2">维护</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-disabled vs-user-api-status'
            + (status === 1 ? ' is-active' : '') + '" data-api-id="' + id + '" data-status="1">禁用</button>';
        return html;
    }

    function buildRowHtml(api) {
        var id = parseInt(api.id, 10) || 0;
        var reason = api.rejectreason ? String(api.rejectreason) : '';
        var callUrl = api.call_url || api.endpoint || '';
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
        html += '<div class="vs-api-item__icon"><img src="' + escapeHtml(icon) + '" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer"></div>';
        html += '<div class="vs-api-item__title">';
        html += '<span class="vs-api-item__name" data-field="name">' + escapeHtml(api.name || '') + '</span>';
        html += '<span class="vs-api-item__id">#' + id + '</span></div>';
        html += '<div class="vs-api-item__endpoint">';
        html += methodBadgesHtml(api);
        html += '<span class="vs-api-item__url" data-field="call_url" title="' + escapeHtml(callUrl) + '">' + escapeHtml(callUrl) + '</span></div>';
        html += '<div class="vs-api-item__tags">';
        if (category) {
            html += '<span class="vs-api-tag vs-api-tag--cat">' + escapeHtml(category) + '</span>';
        }
        html += '<span class="vs-api-tag vs-api-tag--free" data-field="charge_tag">'
            + escapeHtml((parseInt(api.charge, 10) === 1 && parseFloat(api.price) > 0) ? ('每次 ' + api.price + ' 积分') : '免费')
            + '</span>';
        if (keyBadge) {
            html += '<span class="vs-api-tag vs-api-tag--key">' + escapeHtml(keyBadge) + '</span>';
        }
        if (!approved) {
            html += '<span class="vs-api-tag vs-api-tag--audit ' + auditClass(audit) + '" data-field="audit_label">'
                + escapeHtml(api.audit_label || '') + '</span>';
        }
        html += '</div>';
        html += '<div class="vs-api-item__meta">';
        html += '<div class="vs-api-item__status">';
        if (approved) {
            html += '状态：<span class="vs-api-tag vs-api-tag--status ' + statusClass(status)
                + '" data-field="status_label">' + escapeHtml(api.status_label || '正常') + '</span>';
        } else {
            html += '<span data-field="status_label"></span>';
        }
        html += '</div>';
        html += '<div class="vs-api-item__calls" title="请求次数">请求：<strong data-field="calls">'
            + (parseInt(api.calls, 10) || 0) + '</strong></div>';
        html += '<div class="vs-api-item__author"></div>';
        html += '</div>';
        html += '<p class="vs-api-review-reason vs-user-api-row__reason" data-field="rejectreason"' + (reason ? '' : ' hidden') + '>';
        html += reason ? ('未通过原因：' + escapeHtml(reason)) : '';
        html += '</p>';
        html += '<div class="vs-api-item__actions vs-user-api-row__actions">';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-user-api-edit" data-api-id="' + id + '">编辑</button>';
        if (approved) {
            html += buildStatusButtons(api);
        }
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-user-api-delete" data-api-id="' + id + '">删除</button>';
        html += '</div></div>';
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
        setSelectedMethods('GET');
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
        if (window.VsParamsEditor && paramsEditor) {
            window.VsParamsEditor.setValue(paramsEditor, '');
        }
        syncUserChargeUi();
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
            userApiFormNeedkey: String(api.needkey != null ? api.needkey : 0),
            userApiFormCharge: String(parseInt(api.charge, 10) === 1 ? 1 : 0),
            userApiFormPrice: (parseInt(api.charge, 10) === 1 && api.price) ? String(api.price) : '',
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
        syncUserChargeUi();
        if (window.VsParamsEditor && paramsEditor) {
            window.VsParamsEditor.setValue(paramsEditor, api.params || '');
        }
        syncUserKeyParam();
        setSelectedMethods(api.methods || api.method || 'GET');
        if (window.VSPick) {
            ['userApiFormNeedkey', 'userApiFormCategory', 'userApiFormCharge'].forEach(function (id) {
                var s = document.getElementById(id);
                if (s) { window.VSPick.refresh(s); }
            });
        }
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
        var paramsVal = '';
        var paramsHidden = document.getElementById('userApiFormParams');
        if (window.VsParamsEditor && paramsEditor) {
            var got = window.VsParamsEditor.getValue(paramsEditor);
            if (got && typeof got === 'object' && got.error) {
                return { __error: got.error };
            }
            paramsVal = typeof got === 'string' ? got : '';
            if (paramsHidden) {
                paramsHidden.value = paramsVal;
            }
        } else if (paramsHidden) {
            paramsVal = paramsHidden.value || '';
        }
        return {
            name: (document.getElementById('userApiFormName') || {}).value || '',
            description: (document.getElementById('userApiFormDesc') || {}).value || '',
            apitype: apiType,
            endpoint: endpointInput ? endpointInput.value : '',
            targeturl: targetInput ? targetInput.value : '',
            proxyslug: slugInput ? slugInput.value : '',
            method: getSelectedMethods().join(','),
            needkey: (document.getElementById('userApiFormNeedkey') || {}).value || '0',
            charge: (document.getElementById('userApiFormCharge') || {}).value || '0',
            price: (document.getElementById('userApiFormPrice') || {}).value || '',
            category: (document.getElementById('userApiFormCategory') || {}).value || '',
            params: paramsVal,
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
            if (payload.__error) {
                window.VS.showMessage(payload.__error, 'error');
                return;
            }
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

    syncEmpty();
})();
