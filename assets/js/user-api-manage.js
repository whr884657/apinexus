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
    var upAuthBlock = document.getElementById('userApiUpAuthBlock');
    var upKeyViaWrap = document.getElementById('userApiUpKeyViaWrap');
    var upKeyFields = document.getElementById('userApiUpKeyFields');
    var upKeyNameWrap = document.getElementById('userApiUpKeyNameWrap');
    var upAuthSelect = document.getElementById('userApiFormUpAuth');
    var upKeyViaSelect = document.getElementById('userApiFormUpKeyVia');
    var upKeyNameInput = document.getElementById('userApiFormUpKeyName');
    var upKeyInput = document.getElementById('userApiFormUpKey');
    var upUaModeSelect = document.getElementById('userApiFormUpUaMode');
    var upUaPresetSelect = document.getElementById('userApiFormUpUaPreset');
    var upUaInput = document.getElementById('userApiFormUpUa');
    var upRefererModeSelect = document.getElementById('userApiFormUpRefererMode');
    var upRefererInput = document.getElementById('userApiFormUpReferer');
    var upUaPresetWrap = document.getElementById('userApiUpUaPresetWrap');
    var upUaCustomWrap = document.getElementById('userApiUpUaCustomWrap');
    var upRefererWrap = document.getElementById('userApiUpRefererWrap');
    var typeHint = document.getElementById('userApiTypeHint');
    var endpointInput = document.getElementById('userApiFormEndpoint');
    var targetInput = document.getElementById('userApiFormTargetUrl');
    var slugInput = document.getElementById('userApiFormProxySlug');
    var iconCtl = null;
    var formMode = 'create';
    var canLocal = page.getAttribute('data-can-local') === '1';

    var jsonRewriteHidden = document.getElementById('userApiFormJsonRewrite');
    var jsonRewriteOn = document.getElementById('userApiFormJsonRewriteOn');
    var jsonRewriteRows = document.getElementById('userApiJsonRewriteRows');
    var jsonRewriteEditor = document.getElementById('userApiJsonRewriteEditor');
    var jsonRewriteAddBtn = document.getElementById('userApiJsonRewriteAdd');

    function parseJsonRewriteValue(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === '') {
            return '';
        }
        if ((s.charAt(0) === '{' && s.charAt(s.length - 1) === '}')
            || (s.charAt(0) === '[' && s.charAt(s.length - 1) === ']')
            || s === 'true' || s === 'false' || s === 'null'
            || /^-?\d+(\.\d+)?([eE][+-]?\d+)?$/.test(s)) {
            try {
                return JSON.parse(s);
            } catch (e) {
                return s;
            }
        }
        return s;
    }

    function formatJsonRewriteValue(val) {
        if (val === null) {
            return 'null';
        }
        if (typeof val === 'object') {
            try {
                return JSON.stringify(val);
            } catch (e) {
                return '';
            }
        }
        if (typeof val === 'boolean' || typeof val === 'number') {
            return String(val);
        }
        return String(val == null ? '' : val);
    }

    function addJsonRewriteRow(op, path, valueText) {
        if (!jsonRewriteRows) {
            return;
        }
        if (jsonRewriteRows.children.length >= 40) {
            window.VS.showMessage('最多添加 40 条改写规则', 'error');
            return;
        }
        var row = document.createElement('div');
        row.className = 'vs-json-rewrite__row';
        var pathInput = document.createElement('input');
        pathInput.type = 'text';
        pathInput.className = 'vs-input';
        pathInput.placeholder = '例如 api_info.developer';
        pathInput.maxLength = 256;
        pathInput.value = path || '';
        var opSelect = document.createElement('select');
        opSelect.className = 'vs-input vs-select';
        opSelect.innerHTML = '<option value="set">设置</option><option value="del">删除</option>';
        opSelect.value = (op === 'del') ? 'del' : 'set';
        var valInput = document.createElement('textarea');
        valInput.className = 'vs-input';
        valInput.rows = 1;
        valInput.placeholder = '字符串或 JSON';
        valInput.value = valueText || '';
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'vs-btn vs-btn--default vs-btn--sm vs-json-rewrite__del';
        delBtn.setAttribute('aria-label', '删除规则');
        delBtn.textContent = '删';
        function syncValVis() {
            valInput.disabled = opSelect.value === 'del';
            valInput.style.opacity = opSelect.value === 'del' ? '0.45' : '1';
        }
        opSelect.addEventListener('change', syncValVis);
        delBtn.addEventListener('click', function () {
            if (row.parentNode) {
                row.parentNode.removeChild(row);
            }
            syncJsonRewriteHidden();
        });
        pathInput.addEventListener('input', syncJsonRewriteHidden);
        opSelect.addEventListener('change', syncJsonRewriteHidden);
        valInput.addEventListener('input', syncJsonRewriteHidden);
        syncValVis();
        row.appendChild(pathInput);
        row.appendChild(opSelect);
        row.appendChild(valInput);
        row.appendChild(delBtn);
        jsonRewriteRows.appendChild(row);
    }

    function clearJsonRewriteRows() {
        if (jsonRewriteRows) {
            jsonRewriteRows.innerHTML = '';
        }
    }

    function setJsonRewriteFromConfig(raw) {
        clearJsonRewriteRows();
        var on = false;
        var ops = [];
        var s = String(raw || '').trim();
        if (s) {
            try {
                var data = JSON.parse(s);
                if (data && typeof data === 'object') {
                    on = parseInt(data.on, 10) === 1 || parseInt(data.enabled, 10) === 1;
                    if (Array.isArray(data.ops)) {
                        ops = data.ops;
                    }
                }
            } catch (e) {
                on = false;
                ops = [];
            }
        }
        if (jsonRewriteOn) {
            jsonRewriteOn.checked = on;
        }
        if (jsonRewriteEditor) {
            jsonRewriteEditor.hidden = !on;
        }
        if (on) {
            if (!ops.length) {
                addJsonRewriteRow('set', '', '');
            } else {
                ops.forEach(function (item) {
                    if (!item || typeof item !== 'object') {
                        return;
                    }
                    var op = String(item.op || 'set').toLowerCase();
                    if (op === 'remove' || op === 'delete' || op === 'unset') {
                        op = 'del';
                    }
                    if (op !== 'del') {
                        op = 'set';
                    }
                    addJsonRewriteRow(op, item.path || item.key || '', formatJsonRewriteValue(item.value));
                });
            }
        }
        syncJsonRewriteHidden();
    }

    function syncJsonRewriteHidden() {
        if (!jsonRewriteHidden) {
            return;
        }
        var enabled = !!(jsonRewriteOn && jsonRewriteOn.checked);
        if (jsonRewriteEditor) {
            jsonRewriteEditor.hidden = !enabled;
        }
        if (!enabled) {
            jsonRewriteHidden.value = '';
            return;
        }
        var ops = [];
        if (jsonRewriteRows) {
            Array.prototype.forEach.call(jsonRewriteRows.children, function (row) {
                var inputs = row.querySelectorAll('input, select, textarea');
                if (inputs.length < 3) {
                    return;
                }
                var path = String(inputs[0].value || '').trim();
                var op = String(inputs[1].value || 'set');
                var rawVal = String(inputs[2].value || '');
                if (!path) {
                    return;
                }
                if (op === 'del') {
                    ops.push({ op: 'del', path: path });
                } else {
                    ops.push({ op: 'set', path: path, value: parseJsonRewriteValue(rawVal) });
                }
            });
        }
        if (!ops.length) {
            jsonRewriteHidden.value = '';
            return;
        }
        jsonRewriteHidden.value = JSON.stringify({ on: 1, ops: ops });
    }

    if (jsonRewriteOn) {
        jsonRewriteOn.addEventListener('change', function () {
            if (jsonRewriteOn.checked && jsonRewriteRows && !jsonRewriteRows.children.length) {
                addJsonRewriteRow('set', '', '');
            }
            syncJsonRewriteHidden();
        });
    }
    if (jsonRewriteAddBtn) {
        jsonRewriteAddBtn.addEventListener('click', function () {
            addJsonRewriteRow('set', '', '');
            syncJsonRewriteHidden();
        });
    }

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

    function encodeUpAuthUi(upauth, upkeyvia) {
        var a = parseInt(upauth, 10) || 0;
        var v = parseInt(upkeyvia, 10) === 1 ? 1 : 0;
        if (a === 1 && v === 1) return '3';
        if (a === 1) return '1';
        if (a === 2) return '2';
        return '0';
    }

    function decodeUpAuthUi(uiVal) {
        var n = parseInt(uiVal, 10) || 0;
        if (n === 3) return { upauth: 1, upkeyvia: 1 };
        if (n === 1) return { upauth: 1, upkeyvia: 0 };
        if (n === 2) return { upauth: 2, upkeyvia: 1 };
        return { upauth: 0, upkeyvia: 0 };
    }

    function syncUserUpAuthUi() {
        var isProxy = apiTypeInput && parseInt(apiTypeInput.value, 10) === 1;
        var uiMode = upAuthSelect ? parseInt(upAuthSelect.value, 10) || 0 : 0;
        var decoded = decodeUpAuthUi(uiMode);
        if (upKeyViaSelect) {
            upKeyViaSelect.value = String(decoded.upkeyvia);
        }
        if (upAuthBlock) {
            upAuthBlock.hidden = !isProxy;
            upAuthBlock.setAttribute('aria-hidden', isProxy ? 'false' : 'true');
        }
        if (!isProxy) {
            if (upKeyFields) {
                upKeyFields.hidden = true;
            }
            if (upKeyInput) {
                upKeyInput.required = false;
            }
            return;
        }
        var needKey = decoded.upauth === 1 || decoded.upauth === 2;
        if (upKeyViaWrap) {
            upKeyViaWrap.hidden = true;
        }
        if (upKeyFields) {
            upKeyFields.hidden = !needKey;
        }
        if (upKeyNameWrap) {
            upKeyNameWrap.hidden = decoded.upauth !== 1;
        }
        if (upKeyInput) {
            upKeyInput.required = needKey;
            if (!needKey) {
                upKeyInput.value = '';
            }
        }
        if (decoded.upauth === 0 && upKeyNameInput) {
            upKeyNameInput.value = '';
        }
        if (decoded.upauth === 1 && upKeyNameInput && !upKeyNameInput.value.trim()) {
            upKeyNameInput.value = decoded.upkeyvia === 1 ? 'X-API-Key' : 'api_key';
        }
        var uaMode = upUaModeSelect ? parseInt(upUaModeSelect.value, 10) || 0 : 0;
        if (upUaPresetWrap) {
            upUaPresetWrap.hidden = uaMode !== 1;
        }
        if (upUaCustomWrap) {
            upUaCustomWrap.hidden = uaMode !== 2;
        }
        var refMode = upRefererModeSelect ? parseInt(upRefererModeSelect.value, 10) || 0 : 0;
        if (upRefererWrap) {
            upRefererWrap.hidden = refMode !== 1;
        }
        if (window.VSPick) {
            ['userApiFormUpAuth', 'userApiFormUpUaMode', 'userApiFormUpUaPreset', 'userApiFormUpRefererMode'].forEach(function (id) {
                var s = document.getElementById(id);
                if (s) { window.VSPick.refresh(s); }
            });
        }
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
                ? '外链接口：填写对方完整地址与短码；一律本站中继；可配置上游认证与出站身份。'
                : '本地接口：只填本站路径，如 /api/img/index.php';
        }
        syncUserUpAuthUi();
    }

    document.querySelectorAll('.vs-user-api-type-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setApiType(btn.getAttribute('data-apitype') || '0');
        });
    });

    if (upAuthSelect) {
        upAuthSelect.addEventListener('change', syncUserUpAuthUi);
    }
    if (upUaModeSelect) {
        upUaModeSelect.addEventListener('change', syncUserUpAuthUi);
    }
    if (upRefererModeSelect) {
        upRefererModeSelect.addEventListener('change', syncUserUpAuthUi);
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
            var encoded = {};
            Object.keys(payload).forEach(function (key) {
                encoded[key] = payload[key];
            });
            if (window.VS.encodeTransportFields) {
                window.VS.encodeTransportFields(encoded, ['doc', 'aidoc', 'response', 'params', 'jsonrewrite']);
            }
            Object.keys(encoded).forEach(function (key) {
                fd.append(key, encoded[key]);
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

    function switchFormTab(tab) {
        if (!formOverlay) {
            return;
        }
        formOverlay.querySelectorAll('.vs-api-list-form-tab').forEach(function (btn) {
            var on = btn.getAttribute('data-api-form-tab') === tab;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        formOverlay.querySelectorAll('.vs-api-list-form-pane').forEach(function (pane) {
            var on = pane.getAttribute('data-api-form-pane') === tab;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;
        });
    }

    function openOverlay() {
        switchFormTab('basic');
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

    function getSelectedKeyways() {
        var list = [];
        var nodes = document.querySelectorAll('#userApiFormKeywayChecks [data-api-keyway]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].classList.contains('is-on') || nodes[i].checked) {
                list.push(String(nodes[i].getAttribute('data-api-keyway') || '').toLowerCase());
            }
        }
        return list;
    }

    function setSelectedKeyways(value) {
        var set = {};
        var raw = Array.isArray(value) ? value : String(value || 'query').split(/[\s,|\/]+/);
        for (var i = 0; i < raw.length; i++) {
            var k = String(raw[i] || '').toLowerCase();
            if (k === 'query' || k === 'header' || k === 'bearer') {
                set[k] = true;
            }
        }
        if (!set.query && !set.header && !set.bearer) {
            set.query = true;
        }
        var nodes = document.querySelectorAll('#userApiFormKeywayChecks [data-api-keyway]');
        for (var j = 0; j < nodes.length; j++) {
            var key = String(nodes[j].getAttribute('data-api-keyway') || '').toLowerCase();
            var on = !!set[key];
            nodes[j].classList.toggle('is-on', on);
            nodes[j].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    }

    function syncUserKeywaysUi() {
        var row = document.getElementById('userApiKeywaysRow');
        var needEl = document.getElementById('userApiFormNeedkey');
        if (row && needEl) {
            row.hidden = (parseInt(needEl.value, 10) || 0) === 0;
        }
    }

    (function bindUserKeywayToggles() {
        var wrap = document.getElementById('userApiFormKeywayChecks');
        if (!wrap) {
            return;
        }
        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-api-keyway]');
            if (!btn || !wrap.contains(btn)) {
                return;
            }
            e.preventDefault();
            var on = !btn.classList.contains('is-on');
            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (getSelectedKeyways().length === 0) {
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
        needkeyEl.addEventListener('change', function () {
            syncUserKeyParam();
            syncUserKeywaysUi();
        });
        syncUserKeywaysUi();
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
        var apitype = parseInt(api.apitype, 10);
        if (isNaN(apitype)) {
            apitype = 0;
        }
        var typeBadge = api.apitype_badge || (apitype === 1 ? '代理' : '本地');
        html += '<span class="vs-api-tag ' + (apitype === 1 ? 'vs-api-tag--proxy' : 'vs-api-tag--local')
            + '" data-field="apitype_badge">' + escapeHtml(typeBadge) + '</span>';
        if (category) {
            html += '<span class="vs-api-tag vs-api-tag--cat">' + escapeHtml(category) + '</span>';
        }
        html += '<span class="vs-api-tag vs-api-tag--free" data-field="charge_tag">'
            + escapeHtml((parseInt(api.charge, 10) === 1 && parseFloat(api.price) > 0) ? ('每次 ' + api.price + ' 积分') : '免费')
            + '</span>';
        if (keyBadge) {
            html += '<span class="vs-api-tag vs-api-tag--key">' + escapeHtml(keyBadge) + '</span>';
        }
        var qpmN = parseInt(api.qpm, 10) || 0;
        if (qpmN > 0) {
            html += '<span class="vs-api-tag vs-api-tag--qpm" data-field="qpm_badge">QPM '
                + escapeHtml(String(qpmN) + '/MIN') + '</span>';
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
        setSelectedKeyways('query');
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
        syncUserKeywaysUi();
        syncUserUpAuthUi();
        setJsonRewriteFromConfig('');
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
            userApiFormQpm: String(Math.max(0, parseInt(api.qpm, 10) || 0)),
            userApiFormCharge: String(parseInt(api.charge, 10) === 1 ? 1 : 0),
            userApiFormPrice: (parseInt(api.charge, 10) === 1 && api.price) ? String(api.price) : '',
            userApiFormEndpoint: apiType === 0 ? (api.endpoint || '') : '',
            userApiFormTargetUrl: api.targeturl || '',
            userApiFormProxySlug: api.proxyslug || '',
            userApiFormUpAuth: encodeUpAuthUi(api.upauth, api.upkeyvia),
            userApiFormUpKeyVia: String(parseInt(api.upkeyvia, 10) === 1 ? 1 : 0),
            userApiFormUpKeyName: api.upkeyname || '',
            userApiFormUpKey: api.upkey || '',
            userApiFormUpUaMode: String(parseInt(api.upuamode, 10) || 0),
            userApiFormUpUaPreset: api.upuapreset || '',
            userApiFormUpUa: api.upua || '',
            userApiFormUpRefererMode: String(parseInt(api.upreferermode, 10) || 0),
            userApiFormUpReferer: api.upreferer || '',
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
        syncUserUpAuthUi();
        setJsonRewriteFromConfig(api.jsonrewrite || '');
        syncUserChargeUi();
        if (window.VsParamsEditor && paramsEditor) {
            window.VsParamsEditor.setValue(paramsEditor, api.params || '');
        }
        syncUserKeyParam();
        setSelectedMethods(api.methods || api.method || 'GET');
        setSelectedKeyways(api.keyways || 'query');
        syncUserKeywaysUi();
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
            upauth: (function () {
                var d = decodeUpAuthUi(upAuthSelect ? upAuthSelect.value : '0');
                return String(d.upauth);
            })(),
            upkeyvia: (function () {
                var d = decodeUpAuthUi(upAuthSelect ? upAuthSelect.value : '0');
                return String(d.upkeyvia);
            })(),
            upkeyname: upKeyNameInput ? upKeyNameInput.value.trim() : '',
            upkey: upKeyInput ? upKeyInput.value.trim() : '',
            upuamode: upUaModeSelect ? String(parseInt(upUaModeSelect.value, 10) || 0) : '0',
            upuapreset: upUaPresetSelect ? upUaPresetSelect.value : '',
            upua: upUaInput ? upUaInput.value.trim() : '',
            upreferermode: upRefererModeSelect ? String(parseInt(upRefererModeSelect.value, 10) || 0) : '0',
            upreferer: upRefererInput ? upRefererInput.value.trim() : '',
            jsonrewrite: (function () {
                syncJsonRewriteHidden();
                return jsonRewriteHidden ? jsonRewriteHidden.value : '';
            })(),
            method: getSelectedMethods().join(','),
            needkey: (document.getElementById('userApiFormNeedkey') || {}).value || '0',
            keyways: getSelectedKeyways().join(','),
            qpm: String(Math.max(0, parseInt((document.getElementById('userApiFormQpm') || {}).value, 10) || 0)),
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
                switchFormTab('params');
                return;
            }
            payload.name = String(payload.name || '').trim();
            payload.endpoint = String(payload.endpoint || '').trim();
            payload.targeturl = String(payload.targeturl || '').trim();
            payload.proxyslug = String(payload.proxyslug || '').trim();
            if (!payload.name) {
                window.VS.showMessage('请填写接口名称', 'error');
                switchFormTab('basic');
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
                    switchFormTab('basic');
                    if (targetInput) {
                        targetInput.focus();
                    }
                    return;
                }
                if (!/^[a-zA-Z0-9]{3,64}$/.test(payload.proxyslug)) {
                    window.VS.showMessage('请填写 3～64 位字母或数字短码', 'error');
                    switchFormTab('basic');
                    if (slugInput) {
                        slugInput.focus();
                    }
                    return;
                }
                var upMode = parseInt(payload.upauth, 10) || 0;
                var isEdit = !!(formId && formId.value);
                if ((upMode === 1 || upMode === 2) && !payload.upkey && !isEdit) {
                    window.VS.showMessage(upMode === 2 ? '请填写 Bearer Token' : '请填写上游 API Key', 'error');
                    switchFormTab('basic');
                    if (upKeyInput) {
                        upKeyInput.focus();
                    }
                    return;
                }
                var uaMode = parseInt(payload.upuamode, 10) || 0;
                if (uaMode === 1 && !payload.upuapreset) {
                    window.VS.showMessage('请选择内置 User-Agent 预设', 'error');
                    switchFormTab('basic');
                    return;
                }
                if (uaMode === 2 && !payload.upua) {
                    window.VS.showMessage('请填写自定义 User-Agent', 'error');
                    switchFormTab('basic');
                    return;
                }
                if ((parseInt(payload.upreferermode, 10) || 0) === 1) {
                    if (!payload.upreferer || !/^https?:\/\//i.test(payload.upreferer)) {
                        window.VS.showMessage('请填写合法的 Referer（以 http:// 或 https:// 开头）', 'error');
                        switchFormTab('basic');
                        return;
                    }
                }
            } else if (!payload.endpoint) {
                window.VS.showMessage('请填写本地接口路径', 'error');
                switchFormTab('basic');
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

    /* ── AI 编写详细文档 / 代码示例（与管理员分片协议一致） ── */
    var aiBanner = document.getElementById('userApiAiBanner');
    var aiBannerText = document.getElementById('userApiAiBannerText');
    var aiBannerTime = document.getElementById('userApiAiBannerTime');
    var aiDocBtn = document.getElementById('userApiAiDocBtn');
    var aiDocContinueBtn = document.getElementById('userApiAiDocContinueBtn');
    var aiChatClearBtn = document.getElementById('userApiAiChatClearBtn');
    var aiCodeBtn = document.getElementById('userApiAiCodeBtn');
    var aiBusy = false;
    var aiTickTimer = null;
    var aiStageTimer = null;
    var aiStartedAt = 0;
    var aiAbort = null;

    function setAiDocContinueVisible(show) {
        if (aiDocContinueBtn) {
            aiDocContinueBtn.hidden = !show;
        }
    }

    function postActionSse(action, payload, handlers, extra) {
        var fd = new FormData();
        fd.append('action', action);
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                fd.append(k, extra[k]);
            });
        }
        if (payload) {
            var encoded = {};
            Object.keys(payload).forEach(function (key) {
                encoded[key] = payload[key];
            });
            if (window.VS.encodeTransportFields) {
                window.VS.encodeTransportFields(encoded, ['doc', 'aidoc', 'response', 'params', 'jsonrewrite']);
            }
            Object.keys(encoded).forEach(function (key) {
                fd.append(key, encoded[key]);
            });
        }
        return window.VS.postFormSse(fd, window.location.href, handlers, aiAbort ? { signal: aiAbort.signal } : {});
    }

    function mergeCodePieces(pieces) {
        var ok = [];
        pieces.forEach(function (p) {
            if (p) {
                ok.push(p);
            }
        });
        return ok.join('\n\n');
    }

    function setTextareaValue(el, text) {
        if (!el) {
            return;
        }
        el.value = text == null ? '' : String(text);
        try {
            el.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (err) {
            var ev = document.createEvent('Event');
            ev.initEvent('input', true, true);
            el.dispatchEvent(ev);
        }
    }

    function aiNow() {
        var d = new Date();
        function pad(n) { return n < 10 ? '0' + n : String(n); }
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function aiTermEls(kind) {
        if (kind === 'code') {
            return {
                details: document.getElementById('userApiAiTermCode'),
                log: document.getElementById('userApiAiTermCodeLog')
            };
        }
        return {
            details: document.getElementById('userApiAiTermDoc'),
            log: document.getElementById('userApiAiTermDocLog')
        };
    }

    function aiTermClear(kind) {
        var els = aiTermEls(kind);
        if (els.log) {
            els.log.textContent = '';
        }
    }

    function aiTermAppend(kind, line) {
        var els = aiTermEls(kind);
        if (!els.log) {
            return;
        }
        els.log.textContent += (els.log.textContent ? '\n' : '') + '[' + aiNow() + '] ' + line;
        els.log.scrollTop = els.log.scrollHeight;
    }

    function aiTermOpen(kind, running) {
        var els = aiTermEls(kind);
        if (els.details) {
            els.details.open = true;
            if (running) {
                els.details.classList.add('is-running');
            } else {
                els.details.classList.remove('is-running');
            }
        }
    }

    function aiTermStopRunning(kind) {
        var els = aiTermEls(kind);
        if (els.details) {
            els.details.classList.remove('is-running');
        }
    }

    function aiStopTimers() {
        if (aiTickTimer) {
            clearInterval(aiTickTimer);
            aiTickTimer = null;
        }
        if (aiStageTimer) {
            clearInterval(aiStageTimer);
            aiStageTimer = null;
        }
    }

    function aiSetBanner(state, text) {
        if (!aiBanner) {
            return;
        }
        aiBanner.hidden = false;
        aiBanner.classList.remove('is-done', 'is-error');
        if (state === 'done') {
            aiBanner.classList.add('is-done');
        } else if (state === 'error') {
            aiBanner.classList.add('is-error');
        }
        if (aiBannerText) {
            aiBannerText.textContent = text || '';
        }
    }

    function aiHideBannerLater() {
        setTimeout(function () {
            if (!aiBusy && aiBanner) {
                aiBanner.hidden = true;
            }
        }, 5000);
    }

    function aiElapsedLabel() {
        var sec = Math.max(0, Math.floor((Date.now() - aiStartedAt) / 1000));
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return (m > 0 ? (m + '分') : '') + s + '秒';
    }

    function getAiCodeOpts() {
        var o = window.VS_AI_CODE || {};
        var mode = o.mode === 'parallel' ? 'parallel' : 'sequential';
        var conc = parseInt(o.concurrency, 10);
        if (isNaN(conc) || conc < 1) {
            conc = 3;
        }
        if (conc > 6) {
            conc = 6;
        }
        return { mode: mode, concurrency: mode === 'parallel' ? conc : 1 };
    }

    function authWayLabel(way) {
        var w = String(way || '').toLowerCase();
        if (w === 'header') {
            return 'Header';
        }
        if (w === 'bearer') {
            return 'Bearer';
        }
        return 'Query';
    }

    function buildCodeJobs(payload) {
        var langs = ['curl', 'typescript', 'browser', 'python', 'go', 'java', 'php', 'cpp', 'rust'];
        var need = parseInt(payload.needkey, 10) || 0;
        var ways = need === 0 ? ['query'] : getSelectedKeyways();
        ways = ways.filter(function (w) {
            return w === 'query' || w === 'header' || w === 'bearer';
        });
        if (!ways.length) {
            ways = ['query'];
        }
        var jobs = [];
        ways.forEach(function (auth) {
            langs.forEach(function (lang) {
                jobs.push({ auth: auth, lang: lang });
            });
        });
        return jobs;
    }

    function aiUnlockButtons() {
        if (aiDocBtn) {
            aiDocBtn.disabled = false;
        }
        if (aiDocContinueBtn) {
            aiDocContinueBtn.disabled = false;
        }
        if (aiCodeBtn) {
            aiCodeBtn.disabled = false;
        }
        aiBusy = false;
        aiStopTimers();
        aiHideBannerLater();
    }

    function runAiCodePieces(payload, btn) {
        var kind = 'code';
        var title = '代码示例';
        var jobs = buildCodeJobs(payload);
        var opts = getAiCodeOpts();
        var concurrency = opts.concurrency;
        var pieces = new Array(jobs.length);
        var failed = [];
        var doneCount = 0;
        var runningLabels = {};
        var aidocEl = document.getElementById('userApiFormAidoc');

        aiBusy = true;
        aiStartedAt = Date.now();
        aiStopTimers();
        aiTermClear(kind);
        aiTermOpen(kind, true);
        aiTermAppend(kind, '开始分片生成「' + title + '」· 共 ' + jobs.length + ' 片 · '
            + (opts.mode === 'parallel' ? ('并行×' + concurrency) : '单线程逐片'));
        aiSetBanner('run', '代码示例 0/' + jobs.length);
        if (aiBannerTime) {
            aiBannerTime.textContent = '已用时 0秒';
        }
        if (aiDocBtn) {
            aiDocBtn.disabled = true;
        }
        if (aiCodeBtn) {
            aiCodeBtn.disabled = true;
        }
        if (btn) {
            btn.disabled = true;
        }

        function refreshRunBanner() {
            var running = Object.keys(runningLabels).map(function (k) {
                return runningLabels[k];
            });
            var tip = '代码示例 ' + doneCount + '/' + jobs.length;
            if (running.length) {
                tip += ' · 进行中：' + running.slice(0, 3).join('、');
                if (running.length > 3) {
                    tip += ' 等';
                }
            }
            tip += ' · ' + aiElapsedLabel();
            aiSetBanner('run', tip);
        }

        aiTickTimer = setInterval(function () {
            if (aiBannerTime) {
                aiBannerTime.textContent = '已用时 ' + aiElapsedLabel();
            }
            if (aiBusy) {
                refreshRunBanner();
            }
        }, 1000);

        window.VS.showMessage('正在分片生成代码示例（' + jobs.length + ' 片）', 'info');

        function finishAll() {
            var okChunks = [];
            pieces.forEach(function (p) {
                if (p) {
                    okChunks.push(p);
                }
            });
            if (!okChunks.length) {
                var failMsg = failed.length
                    ? ('全部失败：' + failed.slice(0, 5).join('；') + (failed.length > 5 ? '…' : ''))
                    : '未能生成任何代码块';
                aiTermAppend(kind, failMsg);
                aiTermStopRunning(kind);
                aiSetBanner('error', failMsg);
                window.VS.showMessage(failMsg, 'error');
                return;
            }
            var merged = okChunks.join('\n\n');
            if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
                merged = window.VsSyntax.scrubHighlightLeak(merged);
            }
            setTextareaValue(aidocEl, merged);
            aiTermAppend(kind, '已写入代码示例（成功 ' + okChunks.length + '/' + jobs.length
                + '，约 ' + merged.length + ' 字符）');
            if (failed.length) {
                var warn = '部分示例未成功：' + failed.slice(0, 12).join('、')
                    + (failed.length > 12 ? (' 等共 ' + failed.length + ' 项') : '');
                aiTermAppend(kind, '注意：' + warn);
                window.VS.showMessage(warn, 'info');
            } else {
                window.VS.showMessage('代码示例已全部生成', 'success');
            }
            aiTermAppend(kind, '完成，总用时 ' + aiElapsedLabel());
            aiTermStopRunning(kind);
            aiSetBanner('done', (failed.length ? '代码示例已部分生成' : '代码示例已生成')
                + ' · 用时 ' + aiElapsedLabel());
            switchFormTab('docs');
        }

        function runPool() {
            return new Promise(function (resolve) {
                var next = 0;
                var active = 0;
                var settled = 0;

                function kick() {
                    while (active < concurrency && next < jobs.length) {
                        (function (jobIndex) {
                            var job = jobs[jobIndex];
                            var label = authWayLabel(job.auth) + ' · ' + job.lang;
                            var key = String(jobIndex);
                            next += 1;
                            active += 1;
                            runningLabels[key] = label;
                            aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + jobs.length + '] 开始编写 · ' + label);
                            refreshRunBanner();

                            var piecePayload = {};
                            Object.keys(payload).forEach(function (k) {
                                piecePayload[k] = payload[k];
                            });
                            delete piecePayload.doc;
                            delete piecePayload.aidoc;
                            piecePayload.auth = job.auth;
                            piecePayload.lang = job.lang;

                            postAction('ai_gen_code_piece', piecePayload)
                                .then(function (data) {
                                    if (!data || data.code !== 1 || !data.piece) {
                                        var err = (data && data.msg) || '生成失败';
                                        failed.push(label + '（' + err + '）');
                                        aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + jobs.length + '] 失败 · ' + label + '：' + err);
                                        return;
                                    }
                                    pieces[jobIndex] = String(data.piece);
                                    if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
                                        pieces[jobIndex] = window.VsSyntax.scrubHighlightLeak(pieces[jobIndex]);
                                    }
                                    var liveMerged = mergeCodePieces(pieces);
                                    if (liveMerged) {
                                        if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
                                            liveMerged = window.VsSyntax.scrubHighlightLeak(liveMerged);
                                        }
                                        setTextareaValue(aidocEl, liveMerged);
                                    }
                                    aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + jobs.length + '] 完成并已回填 · ' + label
                                        + '（' + String(data.piece).length + ' 字符）');
                                })
                                .catch(function () {
                                    failed.push(label + '（网络异常）');
                                    aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + jobs.length + '] 失败 · ' + label + '：网络异常');
                                })
                                .then(function () {
                                    delete runningLabels[key];
                                    active -= 1;
                                    settled += 1;
                                    doneCount += 1;
                                    refreshRunBanner();
                                    if (settled >= jobs.length) {
                                        resolve();
                                    } else {
                                        kick();
                                    }
                                });
                        })(next);
                    }
                }
                if (!jobs.length) {
                    resolve();
                    return;
                }
                kick();
            });
        }

        runPool().then(function () {
            finishAll();
        }).finally(function () {
            aiUnlockButtons();
        });
    }

    function runAiGenerate(action, btn, continueFlag) {
        if (aiBusy) {
            window.VS.showMessage('已有生成任务进行中，请稍候', 'info');
            return;
        }
        var payload = collectPayload();
        if (payload.__error) {
            window.VS.showMessage(payload.__error, 'error');
            return;
        }
        if (!payload.name) {
            window.VS.showMessage('请先填写接口名称', 'error');
            return;
        }
        delete payload.upkey;
        delete payload.targeturl;
        if (formMode === 'edit' && formId && formId.value) {
            payload.api_id = formId.value;
        }

        if (action === 'ai_gen_code') {
            runAiCodePieces(payload, btn);
            return;
        }

        var kind = 'doc';
        var title = continueFlag ? '详细文档（续写）' : '详细文档';
        var docEl = document.getElementById('userApiFormDoc');
        var liveDoc = continueFlag && docEl ? String(docEl.value || '') : '';

        aiBusy = true;
        aiStartedAt = Date.now();
        aiStopTimers();
        if (aiAbort) {
            try { aiAbort.abort(); } catch (e1) { /* ignore */ }
        }
        aiAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        aiTermClear(kind);
        aiTermOpen(kind, true);
        aiTermAppend(kind, continueFlag
            ? '续写请求已发出（对话式流式输出）…'
            : '已向 AI 发出「撰写详细文档」请求（对话式流式输出）…');
        aiSetBanner('run', '正在生成' + title + '…');
        if (aiBannerTime) {
            aiBannerTime.textContent = '已用时 0秒';
        }
        if (aiDocBtn) {
            aiDocBtn.disabled = true;
        }
        if (aiDocContinueBtn) {
            aiDocContinueBtn.disabled = true;
        }
        if (aiCodeBtn) {
            aiCodeBtn.disabled = true;
        }
        if (btn) {
            btn.disabled = true;
        }

        if (!continueFlag) {
            setTextareaValue(docEl, '');
            liveDoc = '';
        }

        aiTickTimer = setInterval(function () {
            if (aiBannerTime) {
                aiBannerTime.textContent = '已用时 ' + aiElapsedLabel();
            }
            if (aiBannerText && aiBanner && !aiBanner.classList.contains('is-done') && !aiBanner.classList.contains('is-error')) {
                aiBannerText.textContent = '正在流式生成' + title + '…（' + aiElapsedLabel() + ' · '
                    + liveDoc.length + ' 字）';
            }
        }, 1000);

        window.VS.showMessage('正在流式生成' + title, 'info');
        switchFormTab('docs');

        var gotDelta = false;
        postActionSse('ai_gen_doc_stream', payload, {
            meta: function (m) {
                if (m && m.history) {
                    aiTermAppend(kind, '会话已接入短时效多轮上下文');
                }
            },
            delta: function (d) {
                var chunk = d && d.text != null ? String(d.text) : '';
                if (!chunk) {
                    return;
                }
                gotDelta = true;
                liveDoc += chunk;
                if (docEl) {
                    docEl.value = liveDoc;
                }
            },
            done: function (data) {
                if (data && data.doc != null) {
                    liveDoc = String(data.doc);
                }
                setTextareaValue(docEl, liveDoc);
                setAiDocContinueVisible(false);
                aiTermAppend(kind, '完成，约 ' + liveDoc.length + ' 字符，总用时 ' + aiElapsedLabel());
                aiTermStopRunning(kind);
                aiSetBanner('done', '详细文档已生成 · 用时 ' + aiElapsedLabel());
                window.VS.showMessage((data && data.msg) || '详细文档已生成', 'success');
            },
            error: function (err) {
                var msg = (err && err.msg) ? String(err.msg) : '生成失败';
                if (err && err.doc) {
                    liveDoc = String(err.doc);
                    setTextareaValue(docEl, liveDoc);
                }
                if (liveDoc) {
                    setAiDocContinueVisible(true);
                    aiTermAppend(kind, '中断/失败，可点「继续生成」：' + msg);
                } else {
                    aiTermAppend(kind, '失败：' + msg);
                }
            }
        }, continueFlag ? { continue: '1' } : {})
            .then(function () { /* done */ })
            .catch(function (err) {
                var hint = (err && err.message) ? String(err.message) : '网络异常或网关超时';
                if (gotDelta || liveDoc) {
                    setAiDocContinueVisible(true);
                    setTextareaValue(docEl, liveDoc);
                    aiTermAppend(kind, hint + '（可继续生成）');
                    aiSetBanner('error', hint);
                    window.VS.showMessage(hint, 'error');
                } else {
                    aiTermAppend(kind, hint);
                    aiTermStopRunning(kind);
                    aiSetBanner('error', hint);
                    window.VS.showMessage(hint, 'error');
                }
            })
            .finally(function () {
                aiAbort = null;
                aiUnlockButtons();
            });
    }

    if (aiDocBtn) {
        aiDocBtn.addEventListener('click', function () {
            runAiGenerate('ai_gen_doc', aiDocBtn, false);
        });
    }
    if (aiDocContinueBtn) {
        aiDocContinueBtn.addEventListener('click', function () {
            runAiGenerate('ai_gen_doc', aiDocContinueBtn, true);
        });
    }
    if (aiChatClearBtn) {
        aiChatClearBtn.addEventListener('click', function () {
            var payload = collectPayload();
            if (formMode === 'edit' && formId && formId.value) {
                payload.api_id = formId.value;
            }
            delete payload.upkey;
            delete payload.targeturl;
            payload.scope = 'all';
            postAction('ai_chat_clear', payload).then(function (data) {
                setAiDocContinueVisible(false);
                window.VS.showMessage((data && data.msg) || '已清除对话', 'success');
                aiTermAppend('doc', '已清除短时效对话记录');
            }).catch(function () {
                window.VS.showMessage('清除失败', 'error');
            });
        });
    }
    if (aiCodeBtn) {
        aiCodeBtn.addEventListener('click', function () {
            runAiGenerate('ai_gen_code', aiCodeBtn, false);
        });
    }

    if (formOverlay) {
        formOverlay.querySelectorAll('.vs-api-list-form-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-api-form-tab') || 'basic';
                switchFormTab(tab);
            });
        });
    }

    syncEmpty();
})();
