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

    var tableWrapEl = document.getElementById('apiListTableWrap');
    var listEl = document.getElementById('apiListBody');
    var mobileEl = document.getElementById('apiListMobile');
    var emptyEl = document.getElementById('apiListEmpty');
    var searchEmptyEl = document.getElementById('apiListSearchEmpty');
    var searchInput = document.getElementById('apiListSearchInput');
    var filterCategoryEl = document.getElementById('apiListFilterCategory');
    var filterStatusEl = document.getElementById('apiListFilterStatus');
    var filterSortEl = document.getElementById('apiListFilterSort');
    var pageSizeEl = document.getElementById('apiListPageSize');
    var footerEl = document.getElementById('apiListFooter');
    var pagerEl = document.getElementById('apiListPager');
    var pagerNumsEl = document.getElementById('apiListPagerNums');
    var statsEl = document.getElementById('apiListStats');
    var prevBtn = document.getElementById('apiListPrevBtn');
    var nextBtn = document.getElementById('apiListNextBtn');
    var currentPage = 1;
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
        methodChecks: document.querySelectorAll('#apiListFormMethodChecks [data-api-method]'),
        keywayChecks: document.querySelectorAll('#apiListFormKeywayChecks [data-api-keyway]'),
        keywaysRow: document.getElementById('apiListKeywaysRow'),
        status: document.getElementById('apiListFormStatus'),
        apitype: document.getElementById('apiListFormApiType'),
        endpoint: document.getElementById('apiListFormEndpoint'),
        targeturl: document.getElementById('apiListFormTargetUrl'),
        proxyslug: document.getElementById('apiListFormProxySlug'),
        upauth: document.getElementById('apiListFormUpAuth'),
        upmethod: document.getElementById('apiListFormUpMethod'),
        upkeyvia: document.getElementById('apiListFormUpKeyVia'),
        upkeyname: document.getElementById('apiListFormUpKeyName'),
        upkey: document.getElementById('apiListFormUpKey'),
        upuamode: document.getElementById('apiListFormUpUaMode'),
        upuapreset: document.getElementById('apiListFormUpUaPreset'),
        upua: document.getElementById('apiListFormUpUa'),
        upreferermode: document.getElementById('apiListFormUpRefererMode'),
        upreferer: document.getElementById('apiListFormUpReferer'),
        jsonrewrite: document.getElementById('apiListFormJsonRewrite'),
        jsonrewriteOn: document.getElementById('apiListFormJsonRewriteOn'),
        category: document.getElementById('apiListFormCategory'),
        requireKey: document.getElementById('apiListFormRequireKey'),
        qpm: document.getElementById('apiListFormQpm'),
        charge: document.getElementById('apiListFormCharge'),
        price: document.getElementById('apiListFormPrice'),
        priceRow: document.getElementById('apiListPriceRow'),
        params: document.getElementById('apiListFormParams'),
        paramsEditor: document.getElementById('apiListParamsEditor'),
        response: document.getElementById('apiListFormResponse'),
        docNormal: document.getElementById('apiListFormDocNormal'),
        docAi: document.getElementById('apiListFormDocAi')
    };

    if (window.VsParamsEditor && fields.paramsEditor) {
        window.VsParamsEditor.mount(fields.paramsEditor, { hiddenId: 'apiListFormParams' });
    }

    var typeHint = document.getElementById('apiListTypeHint');
    var endpointLabel = document.getElementById('apiListEndpointLabel');
    var endpointRow = document.getElementById('apiListEndpointRow');
    var targetRow = document.getElementById('apiListTargetRow');
    var slugRow = document.getElementById('apiListSlugRow');
    var upAuthBlock = document.getElementById('apiListUpAuthBlock');
    var upKeyViaWrap = document.getElementById('apiListUpKeyViaWrap');
    var upKeyFields = document.getElementById('apiListUpKeyFields');
    var upKeyNameWrap = document.getElementById('apiListUpKeyNameWrap');
    var upUaPresetWrap = document.getElementById('apiListUpUaPresetWrap');
    var upUaCustomWrap = document.getElementById('apiListUpUaCustomWrap');
    var upRefererWrap = document.getElementById('apiListUpRefererWrap');

    /** 界面值：0无需 1Query 3Header 2Bearer → 落库 upauth + upkeyvia */
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

    function syncUpAuthUi() {
        var isProxy = fields.apitype && parseInt(fields.apitype.value, 10) === 1;
        var uiMode = fields.upauth ? parseInt(fields.upauth.value, 10) || 0 : 0;
        var decoded = decodeUpAuthUi(uiMode);
        if (fields.upkeyvia) {
            fields.upkeyvia.value = String(decoded.upkeyvia);
        }
        if (upAuthBlock) {
            upAuthBlock.hidden = !isProxy;
            upAuthBlock.setAttribute('aria-hidden', isProxy ? 'false' : 'true');
        }

        var uaMode = fields.upuamode ? parseInt(fields.upuamode.value, 10) || 0 : 0;
        if (upUaPresetWrap) {
            upUaPresetWrap.hidden = uaMode !== 1;
        }
        if (upUaCustomWrap) {
            upUaCustomWrap.hidden = uaMode !== 2;
        }
        var refMode = fields.upreferermode ? parseInt(fields.upreferermode.value, 10) || 0 : 0;
        if (upRefererWrap) {
            upRefererWrap.hidden = refMode !== 1;
        }

        if (!isProxy) {
            if (upKeyFields) {
                upKeyFields.hidden = true;
            }
            if (fields.upkey) {
                fields.upkey.required = false;
            }
            if (window.VSPick) {
                ['apiListFormUpUaMode', 'apiListFormUpUaPreset', 'apiListFormUpRefererMode'].forEach(function (id) {
                    var s = document.getElementById(id);
                    if (s) { window.VSPick.refresh(s); }
                });
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
        if (fields.upkey) {
            fields.upkey.required = needKey;
            if (!needKey) {
                fields.upkey.value = '';
            }
        }
        if (decoded.upauth === 0 && fields.upkeyname) {
            fields.upkeyname.value = '';
        }
        if (decoded.upauth === 1 && fields.upkeyname && !fields.upkeyname.value.trim()) {
            fields.upkeyname.value = decoded.upkeyvia === 1 ? 'X-API-Key' : 'api_key';
        }

        if (window.VSPick) {
            ['apiListFormUpMethod', 'apiListFormUpAuth', 'apiListFormUpUaMode', 'apiListFormUpUaPreset', 'apiListFormUpRefererMode'].forEach(function (id) {
                var s = document.getElementById(id);
                if (s) { window.VSPick.refresh(s); }
            });
        }
    }

    function setApiType(type) {
        var t = parseInt(type, 10) === 1 ? 1 : 0;
        if (fields.apitype) {
            fields.apitype.value = String(t);
        }
        document.querySelectorAll('.vs-api-type-tab').forEach(function (btn) {
            var on = parseInt(btn.getAttribute('data-apitype'), 10) === t;
            btn.classList.toggle('vs-btn--primary', on);
            btn.classList.toggle('vs-btn--default', !on);
        });
        if (endpointRow) {
            endpointRow.hidden = t === 1;
        }
        if (fields.endpoint) {
            fields.endpoint.required = t === 0;
        }
        if (targetRow) {
            targetRow.hidden = t !== 1;
        }
        if (fields.targeturl) {
            fields.targeturl.required = t === 1;
        }
        if (slugRow) {
            slugRow.hidden = t !== 1;
        }
        if (fields.proxyslug) {
            fields.proxyslug.required = t === 1;
        }
        if (typeHint) {
            typeHint.textContent = t === 1
                ? '外链接口：填写对方完整地址与短码；一律本站中继；可配置上游认证与出站身份。'
                : '本地接口：只填本站路径，如 /api/img/index.php';
        }
        if (endpointLabel) {
            endpointLabel.innerHTML = t === 1
                ? '本地路径'
                : '本地路径 <span class="vs-req">*</span>';
        }
        syncUpAuthUi();
    }

    document.querySelectorAll('.vs-api-type-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setApiType(btn.getAttribute('data-apitype') || '0');
        });
    });

    if (fields.upauth) {
        fields.upauth.addEventListener('change', syncUpAuthUi);
    }
    if (fields.upuamode) {
        fields.upuamode.addEventListener('change', syncUpAuthUi);
    }
    if (fields.upreferermode) {
        fields.upreferermode.addEventListener('change', syncUpAuthUi);
    }

    /** 接口状态：0正常 1禁用 2维护（兼容旧英文串） */
    function normalizeStatus(status) {
        if (status === 'normal') {
            return 0;
        }
        if (status === 'disabled') {
            return 1;
        }
        if (status === 'maintenance') {
            return 2;
        }
        var n = parseInt(status, 10);
        if (n === 1 || n === 2) {
            return n;
        }
        return 0;
    }

    /** 审核：0待审 1通过 2不通过 */
    function normalizeAudit(value) {
        var n = parseInt(value, 10);
        if (n === 1 || n === 2) {
            return n;
        }
        return 0;
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
            return 'KEY 必填';
        }
        if (n === 2) {
            return 'KEY 可选';
        }
        return '无需 KEY';
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

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var jsonRewriteRows = document.getElementById('apiListJsonRewriteRows');
    var jsonRewriteEditor = document.getElementById('apiListJsonRewriteEditor');
    var jsonRewriteAddBtn = document.getElementById('apiListJsonRewriteAdd');

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
        if (fields.jsonrewriteOn) {
            fields.jsonrewriteOn.checked = on;
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
        if (!fields.jsonrewrite) {
            return;
        }
        var enabled = !!(fields.jsonrewriteOn && fields.jsonrewriteOn.checked);
        if (jsonRewriteEditor) {
            jsonRewriteEditor.hidden = !enabled;
        }
        if (!enabled) {
            fields.jsonrewrite.value = '';
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
            fields.jsonrewrite.value = '';
            return;
        }
        fields.jsonrewrite.value = JSON.stringify({ on: 1, ops: ops });
    }

    if (fields.jsonrewriteOn) {
        fields.jsonrewriteOn.addEventListener('change', function () {
            if (fields.jsonrewriteOn.checked && jsonRewriteRows && !jsonRewriteRows.children.length) {
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

    function safeIconUrl(url) {
        var u = String(url || '').trim();
        if (!u && defaultIcons.length) {
            return defaultIcons[0];
        }
        return u || '';
    }

    function displayStatusLabel(status) {
        var n = normalizeStatus(status);
        if (n === 1) {
            return '已禁用';
        }
        if (n === 2) {
            return '维护中';
        }
        return '正常';
    }

    function statusBadgeClass(status) {
        var n = normalizeStatus(status);
        if (n === 1) {
            return 'vs-badge--error';
        }
        if (n === 2) {
            return 'vs-badge--warning';
        }
        return 'vs-badge--success';
    }

    function typeBadgeClass(badge) {
        return badge === '代理' ? 'type-badge--proxy' : 'type-badge--local';
    }

    function formatCalls(n) {
        var num = parseInt(n, 10) || 0;
        try {
            return num.toLocaleString('zh-CN');
        } catch (e) {
            return String(num);
        }
    }

    function chargeBadgeHtml(api) {
        var charge = parseInt(api.charge, 10) === 1;
        var price = api.price != null ? String(api.price) : '0';
        if (charge && parseFloat(price) > 0) {
            return '<span class="charge-badge charge-badge--points" data-field="charge_tag">'
                + escapeHtml(price + '积分/次') + '</span>';
        }
        return '<span class="charge-badge charge-badge--free" data-field="charge_tag">免费</span>';
    }

    function qpmBadgeHtml(api) {
        var n = parseInt(api && api.qpm, 10) || 0;
        if (n <= 0) {
            return '';
        }
        return '<span class="qpm-badge qpm-badge--limit" data-field="qpm_badge">QPM '
            + escapeHtml(String(n) + '/MIN') + '</span>';
    }

    function keyBadgeHtml(keyBadge) {
        var badge = keyBadge ? String(keyBadge).trim() : '';
        if (!badge) {
            return '<span class="key-badge key-badge--none" data-field="needkey_badge">KEY 不必要</span>';
        }
        var cls = 'key-badge--optional';
        if (badge.indexOf('必填') !== -1) {
            cls = 'key-badge--required';
        }
        return '<span class="key-badge ' + cls + '" data-field="needkey_badge">' + escapeHtml(badge) + '</span>';
    }

    function categoryBadgeHtml(category) {
        var cat = category ? String(category).trim() : '';
        var text = cat || '未分类';
        return '<span class="vs-badge vs-badge--default" data-field="category">' + escapeHtml(text) + '</span>';
    }

    function getRowPair(apiId) {
        var id = String(apiId);
        return {
            desktop: listEl ? listEl.querySelector('tr[data-api-row="' + id + '"]') : null,
            mobile: mobileEl ? mobileEl.querySelector('.api-card[data-api-row="' + id + '"]') : null
        };
    }

    function rowDataAttrs(api) {
        var status = normalizeStatus(api.status);
        var audit = normalizeAudit(api.audit);
        var callUrl = callUrlOf(api);
        var typeBadge = api.apitype_badge || '本地';
        var username = displayUsername(api);
        var category = api.category ? String(api.category).trim() : '';
        var search = (String(api.name || '') + ' ' + callUrl + ' ' + String(api.endpoint || '') + ' '
            + category + ' ' + typeBadge + ' ' + username).toLowerCase();
        return {
            status: status,
            audit: audit,
            category: category,
            calls: parseInt(api.calls, 10) || 0,
            name: String(api.name || ''),
            search: search,
            payload: JSON.stringify(api)
        };
    }

    function applyRowDataAttrs(el, api) {
        if (!el || !api) {
            return;
        }
        var d = rowDataAttrs(api);
        el.setAttribute('data-api-row', String(api.id));
        el.setAttribute('data-api-status', String(d.status));
        el.setAttribute('data-api-audit', String(d.audit));
        el.setAttribute('data-api-category', d.category);
        el.setAttribute('data-api-calls', String(d.calls));
        el.setAttribute('data-api-name', d.name);
        el.setAttribute('data-search', d.search);
        el.setAttribute('data-payload', d.payload);
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
        if (!listEl) {
            return;
        }
        listEl.querySelectorAll('tr[data-api-row]').forEach(function (row, i) {
            row.style.setProperty('--row-i', String(Math.min(i, 20)));
            row.classList.add('is-enter');
        });
        if (mobileEl) {
            mobileEl.querySelectorAll('.api-card[data-api-row]').forEach(function (row, i) {
                row.style.setProperty('--row-i', String(Math.min(i, 20)));
                row.classList.add('is-enter');
            });
        }
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
        var html = '<div class="method-list" data-field="method">';
        methods.forEach(function (m) {
            var slug = methodSlug(m);
            html += '<span class="method-badge method-badge--' + escapeHtml(slug) + '">'
                + escapeHtml(String(m).toUpperCase()) + '</span>';
        });
        html += '</div>';
        return html;
    }

    function methodBadgesInlineHtml(api) {
        var methods = (api && api.methods && api.methods.length)
            ? api.methods
            : String((api && (api.method_label || api.method)) || 'GET').split(/[\s,|\/]+/).filter(Boolean);
        if (!methods.length) {
            methods = ['GET'];
        }
        var html = '';
        methods.forEach(function (m) {
            var slug = methodSlug(m);
            html += '<span class="method-badge method-badge--' + escapeHtml(slug) + '">'
                + escapeHtml(String(m).toUpperCase()) + '</span>';
        });
        return html;
    }

    function methodDisplay(api) {
        if (api && api.method_label) {
            return String(api.method_label);
        }
        if (api && api.methods && api.methods.length) {
            return api.methods.join(' / ');
        }
        return String((api && api.method) || 'GET').replace(/,/g, ' / ');
    }

    function getSelectedMethods() {
        var list = [];
        var nodes = fields.methodChecks || [];
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].classList.contains('is-on') || nodes[i].checked) {
                list.push(String(nodes[i].getAttribute('data-api-method') || '').toUpperCase());
            }
        }
        return list;
    }

    function setSelectedMethods(value) {
        var set = {};
        var raw = Array.isArray(value) ? value : String(value || 'GET').split(/[\s,|\/]+/);
        for (var i = 0; i < raw.length; i++) {
            var m = String(raw[i] || '').toUpperCase();
            if (m === 'GET' || m === 'POST') {
                set[m] = true;
            }
        }
        if (!set.GET && !set.POST) {
            set.GET = true;
        }
        var nodes = fields.methodChecks || [];
        for (var j = 0; j < nodes.length; j++) {
            var key = String(nodes[j].getAttribute('data-api-method') || '').toUpperCase();
            var on = !!set[key];
            nodes[j].classList.toggle('is-on', on);
            nodes[j].setAttribute('aria-pressed', on ? 'true' : 'false');
            if (typeof nodes[j].checked !== 'undefined' && nodes[j].tagName === 'INPUT') {
                nodes[j].checked = on;
            }
        }
    }

    function getSelectedKeyways() {
        var list = [];
        var nodes = fields.keywayChecks || [];
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
        var nodes = fields.keywayChecks || [];
        for (var j = 0; j < nodes.length; j++) {
            var key = String(nodes[j].getAttribute('data-api-keyway') || '').toLowerCase();
            var on = !!set[key];
            nodes[j].classList.toggle('is-on', on);
            nodes[j].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    }

    function syncKeywaysUi() {
        if (fields.keywaysRow && fields.requireKey) {
            var need = parseInt(fields.requireKey.value, 10) || 0;
            fields.keywaysRow.hidden = need === 0;
        }
        syncAiCodeAuthButtons();
    }

    function syncAiCodeAuthButtons() {
        var wrap = document.getElementById('apiListAiCodeWays');
        if (!wrap) {
            return;
        }
        var need = fields.requireKey ? (parseInt(fields.requireKey.value, 10) || 0) : 0;
        var selected = {};
        if (need > 0) {
            getSelectedKeyways().forEach(function (w) {
                selected[w] = true;
            });
        }
        var any = false;
        var nodes = wrap.querySelectorAll('[data-ai-code-auth]');
        for (var i = 0; i < nodes.length; i++) {
            var auth = String(nodes[i].getAttribute('data-ai-code-auth') || '').toLowerCase();
            var show = need > 0 && !!selected[auth];
            nodes[i].hidden = !show;
            if (show) {
                any = true;
            }
        }
        wrap.hidden = !any;
    }

    function setAiCodeAuthButtonsDisabled(disabled) {
        var wrap = document.getElementById('apiListAiCodeWays');
        if (!wrap) {
            return;
        }
        var nodes = wrap.querySelectorAll('[data-ai-code-auth]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].disabled = !!disabled;
        }
    }

    (function bindMethodToggles() {
        var wrap = document.getElementById('apiListFormMethodChecks');
        if (!wrap) {
            return;
        }
        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-api-method]');
            if (!btn || !wrap.contains(btn)) {
                return;
            }
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

    (function bindKeywayToggles() {
        var wrap = document.getElementById('apiListFormKeywayChecks');
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
            syncAiCodeAuthButtons();
        });
    })();

    function syncKeyParam() {
        if (!window.VsParamsEditor || !fields.paramsEditor || !fields.requireKey) {
            return;
        }
        var need = parseInt(fields.requireKey.value, 10) || 0;
        var got = window.VsParamsEditor.getValue(fields.paramsEditor);
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
                window.VsParamsEditor.setValue(fields.paramsEditor, rows.length ? JSON.stringify(rows, null, 4) : '');
            }
            return;
        }
        var required = need === 1;
        if (keyIdx >= 0) {
            rows[keyIdx].required = required;
            if (!rows[keyIdx].description) {
                rows[keyIdx].description = autoDesc;
            }
            if (!rows[keyIdx].type) {
                rows[keyIdx].type = 'string';
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
        window.VsParamsEditor.setValue(fields.paramsEditor, JSON.stringify(rows, null, 4));
    }

    function syncChargeUi() {
        if (!fields.charge || !fields.priceRow) {
            return;
        }
        var paid = String(fields.charge.value) === '1';
        fields.priceRow.hidden = !paid;
        if (!paid && fields.price) {
            fields.price.value = '';
        }
        if (fields.requireKey) {
            var optNone = fields.requireKey.querySelector('option[value="0"]');
            if (optNone) {
                optNone.disabled = paid;
            }
            if (paid && String(fields.requireKey.value) === '0') {
                fields.requireKey.value = '1';
                if (window.VSPick && typeof window.VSPick.refresh === 'function') {
                    window.VSPick.refresh(fields.requireKey);
                }
            }
        }
        syncKeyParam();
        syncKeywaysUi();
    }

    if (fields.charge) {
        fields.charge.addEventListener('change', syncChargeUi);
        syncChargeUi();
    }
    if (fields.requireKey) {
        fields.requireKey.addEventListener('change', function () {
            syncKeyParam();
            syncKeywaysUi();
        });
        syncKeywaysUi();
    }

    function callUrlOf(api) {
        return String((api && (api.call_url || api.endpoint)) || '');
    }

    function displayUsername(api) {
        var name = api && api.username ? String(api.username).trim() : '';
        if (name) {
            return name;
        }
        var uid = api ? (parseInt(api.userid, 10) || 0) : 0;
        return uid > 0 ? ('用户#' + uid) : '管理员';
    }

    function buildMobileTagsHtml(api) {
        var typeBadge = api.apitype_badge || '本地';
        var category = api.category ? String(api.category).trim() : '';
        var html = '';
        if (category) {
            html += categoryBadgeHtml(category);
        }
        html += '<span class="type-badge ' + typeBadgeClass(typeBadge) + '" data-field="apitype_badge">'
            + escapeHtml(typeBadge) + '</span>';
        html += chargeBadgeHtml(api);
        html += keyBadgeHtml(api.needkey_badge || '');
        html += qpmBadgeHtml(api);
        return html;
    }

    function buildActionButtons(api) {
        var id = api.id;
        var status = normalizeStatus(api.status);
        var html = '<div class="action-btns">';
        html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-api-list-action" data-api-action="edit" data-api-id="' + id + '">编辑</button>';
        if (status === 0) {
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-list-action" data-api-action="maintenance" data-api-id="' + id + '">维护</button>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-list-action" data-api-action="disable" data-api-id="' + id + '">禁用</button>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="' + id + '">删除</button>';
        } else if (status === 2) {
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-list-action" data-api-action="normal" data-api-id="' + id + '">恢复正常</button>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-list-action" data-api-action="disable" data-api-id="' + id + '">禁用</button>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="' + id + '">删除</button>';
        } else {
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-list-action" data-api-action="normal" data-api-id="' + id + '">启用</button>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="' + id + '">删除</button>';
        }
        html += '</div>';
        return html;
    }

    function buildDesktopRowHtml(api) {
        var icon = safeIconUrl(api.icon);
        var typeBadge = api.apitype_badge || '本地';
        var username = displayUsername(api);
        var d = rowDataAttrs(api);
        var html = '<tr data-api-row="' + api.id + '"'
            + ' data-api-status="' + d.status + '"'
            + ' data-api-audit="' + d.audit + '"'
            + ' data-api-category="' + escapeHtml(d.category) + '"'
            + ' data-api-calls="' + d.calls + '"'
            + ' data-api-name="' + escapeHtml(d.name) + '"'
            + ' data-search="' + escapeHtml(d.search) + '"'
            + ' data-payload="' + escapeHtml(d.payload) + '">';
        html += '<td><span class="api-id" data-field="id">' + (parseInt(api.id, 10) || 0) + '</span></td>';
        html += '<td><div class="api-name-cell"><div class="api-icon"><img src="' + escapeHtml(icon)
            + '" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon"></div>'
            + '<span class="api-name-text" data-field="name">' + escapeHtml(api.name || '') + '</span></div></td>';
        html += '<td><span class="vs-api-list-author" data-field="username">' + escapeHtml(username) + '</span></td>';
        html += '<td data-field="category_cell">' + categoryBadgeHtml(d.category) + '</td>';
        html += '<td><span class="type-badge ' + typeBadgeClass(typeBadge) + '" data-field="apitype_badge">'
            + escapeHtml(typeBadge) + '</span></td>';
        html += '<td>' + methodBadgesHtml(api) + '</td>';
        html += '<td>' + chargeBadgeHtml(api) + '</td>';
        html += '<td>' + keyBadgeHtml(api.needkey_badge || '') + qpmBadgeHtml(api) + '</td>';
        html += '<td><span class="vs-badge ' + statusBadgeClass(api.status) + '" data-field="status_label">'
            + escapeHtml(displayStatusLabel(api.status)) + '</span></td>';
        html += '<td class="vs-api-list-calls-cell"><span data-field="calls">' + formatCalls(api.calls) + '</span></td>';
        html += '<td>' + buildActionButtons(api) + '</td>';
        html += '</tr>';
        return html;
    }

    function buildMobileCardHtml(api) {
        var icon = safeIconUrl(api.icon);
        var username = displayUsername(api);
        var d = rowDataAttrs(api);
        var html = '<div class="api-card" data-api-row="' + api.id + '"'
            + ' data-api-status="' + d.status + '"'
            + ' data-api-audit="' + d.audit + '"'
            + ' data-api-category="' + escapeHtml(d.category) + '"'
            + ' data-api-calls="' + d.calls + '"'
            + ' data-api-name="' + escapeHtml(d.name) + '"'
            + ' data-search="' + escapeHtml(d.search) + '"'
            + ' data-payload="' + escapeHtml(d.payload) + '">';
        html += '<div class="api-card__header"><div class="api-card__header-left">';
        html += '<span class="api-id" data-field="id">#' + (parseInt(api.id, 10) || 0) + '</span>';
        html += '<div class="api-card__icon"><img src="' + escapeHtml(icon)
            + '" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon"></div>';
        html += '<span class="api-card__name" data-field="name">' + escapeHtml(api.name || '') + '</span></div>';
        html += '<span class="vs-badge ' + statusBadgeClass(api.status) + '" data-field="status_label">'
            + escapeHtml(displayStatusLabel(api.status)) + '</span></div>';
        html += '<div class="api-card__tags" data-field="tags">' + buildMobileTagsHtml(api) + '</div>';
        html += '<div class="api-card__info">';
        html += '<span class="api-card__info-item"><span class="api-card__info-label">提交者</span> <span class="api-card__info-value" data-field="username">'
            + escapeHtml(username) + '</span></span>';
        html += '<span class="api-card__info-item"><span class="api-card__info-label">方式</span> ' + methodBadgesInlineHtml(api) + '</span>';
        html += '<span class="api-card__info-item"><span class="api-card__info-label">调用</span> <span class="api-card__calls" data-field="calls">'
            + formatCalls(api.calls) + '</span></span></div>';
        html += '<div class="api-card__actions">' + buildActionButtons(api) + '</div></div>';
        return html;
    }

    function ensureListVisible() {
        if (tableWrapEl) {
            tableWrapEl.hidden = false;
        }
        if (mobileEl) {
            mobileEl.hidden = false;
        }
        if (emptyEl) {
            emptyEl.hidden = true;
        }
    }

    function refreshEmptyState() {
        if (!listEl) {
            return;
        }
        var rows = listEl.querySelectorAll('tr[data-api-row]');
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
        if (tableWrapEl) {
            tableWrapEl.hidden = !hasAny;
        }
        if (mobileEl) {
            mobileEl.hidden = !hasAny;
        }
        if (searchEmptyEl) {
            searchEmptyEl.hidden = !(hasAny && visible === 0);
        }
        if (footerEl) {
            footerEl.hidden = !hasAny;
        }
    }

    function defaultPageSize() {
        return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
        if (!n || n < 1) {
            n = defaultPageSize();
        }
        return n;
    }

    function getSortMode() {
        return filterSortEl ? String(filterSortEl.value || 'newest') : 'newest';
    }

    function sortMatchedRows(rows) {
        var mode = getSortMode();
        rows.sort(function (a, b) {
            var idA = parseInt(a.getAttribute('data-api-row'), 10) || 0;
            var idB = parseInt(b.getAttribute('data-api-row'), 10) || 0;
            var callsA = parseInt(a.getAttribute('data-api-calls'), 10) || 0;
            var callsB = parseInt(b.getAttribute('data-api-calls'), 10) || 0;
            var nameA = String(a.getAttribute('data-api-name') || '').toLowerCase();
            var nameB = String(b.getAttribute('data-api-name') || '').toLowerCase();
            if (mode === 'calls-desc') {
                return callsB - callsA || idB - idA;
            }
            if (mode === 'calls-asc') {
                return callsA - callsB || idB - idA;
            }
            if (mode === 'name-az') {
                if (nameA < nameB) {
                    return -1;
                }
                if (nameA > nameB) {
                    return 1;
                }
                return idB - idA;
            }
            return idB - idA;
        });
        return rows;
    }

    function syncRowOrder(rows) {
        if (!listEl) {
            return;
        }
        rows.forEach(function (row) {
            listEl.appendChild(row);
            if (mobileEl) {
                var id = row.getAttribute('data-api-row');
                var card = mobileEl.querySelector('.api-card[data-api-row="' + id + '"]');
                if (card) {
                    mobileEl.appendChild(card);
                }
            }
        });
    }

    function matchedRows() {
        if (!listEl) {
            return [];
        }
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        var cat = filterCategoryEl ? String(filterCategoryEl.value || '').trim() : '';
        var statusFilter = filterStatusEl ? String(filterStatusEl.value || '') : '';
        var all = Array.prototype.slice.call(listEl.querySelectorAll('tr[data-api-row]'));
        var filtered = all.filter(function (row) {
            if (q) {
                var hay = row.getAttribute('data-search') || '';
                if (hay.indexOf(q) === -1) {
                    return false;
                }
            }
            if (cat) {
                var rowCat = row.getAttribute('data-api-category') || '';
                if (rowCat !== cat) {
                    return false;
                }
            }
            if (statusFilter !== '') {
                if (String(row.getAttribute('data-api-status')) !== statusFilter) {
                    return false;
                }
            }
            return true;
        });
        filtered = sortMatchedRows(filtered);
        syncRowOrder(filtered);
        return filtered;
    }

    function buildStatsText(total, maint, pending) {
        var text = '当前接口总数 ' + total;
        if (maint > 0 || pending > 0) {
            if (maint > 0) {
                text += '，维护中 ' + maint;
            }
            if (pending > 0) {
                text += '，待审核 ' + pending;
            }
        }
        return text;
    }

    function refreshStatsFromDom() {
        if (!listEl || !statsEl) {
            return;
        }
        var rows = listEl.querySelectorAll('tr[data-api-row]');
        var total = rows.length;
        var maint = 0;
        var pending = 0;
        rows.forEach(function (row) {
            if (parseInt(row.getAttribute('data-api-status'), 10) === 2) {
                maint += 1;
            }
            if (parseInt(row.getAttribute('data-api-audit'), 10) === 0) {
                pending += 1;
            }
        });
        statsEl.textContent = buildStatsText(total, maint, pending);
        page.setAttribute('data-stats-total', String(total));
        page.setAttribute('data-stats-maint', String(maint));
        page.setAttribute('data-stats-pending', String(pending));
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
        var matched = matchedRows();
        var all = listEl.querySelectorAll('tr[data-api-row]');
        var size = getPageSize();
        var totalPages = Math.max(1, Math.ceil(matched.length / size) || 1);
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }
        var start = (currentPage - 1) * size;
        var end = start + size;
        var indexMap = {};
        matched.forEach(function (row, i) {
            indexMap[String(row.getAttribute('data-api-row'))] = i;
        });
        all.forEach(function (row) {
            var key = String(row.getAttribute('data-api-row'));
            var card = mobileEl ? mobileEl.querySelector('.api-card[data-api-row="' + key + '"]') : null;
            if (!Object.prototype.hasOwnProperty.call(indexMap, key)) {
                row.hidden = true;
                if (card) {
                    card.hidden = true;
                }
                return;
            }
            var idx = indexMap[key];
            var show = idx >= start && idx < end;
            row.hidden = !show;
            if (card) {
                card.hidden = !show;
            }
        });
        if (footerEl) {
            footerEl.hidden = matched.length === 0 && all.length === 0;
        }
        if (pagerEl) {
            pagerEl.hidden = matched.length === 0;
        }
        renderPagerNums(totalPages);
        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages || matched.length === 0;
        }
        refreshEmptyState();
    }

    function applySearchFilter() {
        currentPage = 1;
        applyListView();
    }

    function parseRowPayload(rowEl) {
        try {
            return JSON.parse(rowEl.getAttribute('data-payload') || '{}');
        } catch (e) {
            return null;
        }
    }

    function updateRowFields(rowEl, api) {
        if (!rowEl || !api) {
            return;
        }
        applyRowDataAttrs(rowEl, api);
        var typeBadge = api.apitype_badge || '本地';
        var username = displayUsername(api);
        var category = api.category ? String(api.category).trim() : '';
        var isDesktop = rowEl.tagName === 'TR';

        var idEl = rowEl.querySelector('[data-field="id"]');
        if (idEl) {
            idEl.textContent = isDesktop ? String(parseInt(api.id, 10) || 0) : ('#' + (parseInt(api.id, 10) || 0));
        }
        var nameEl = rowEl.querySelector('[data-field="name"]');
        if (nameEl) {
            nameEl.textContent = api.name || '';
        }
        var methodEl = rowEl.querySelector('[data-field="method"]');
        if (methodEl && isDesktop) {
            var wrap = document.createElement('div');
            wrap.innerHTML = methodBadgesHtml(api);
            var next = wrap.firstChild;
            if (next) {
                methodEl.parentNode.replaceChild(next, methodEl);
            }
        }
        var callsEl = rowEl.querySelector('[data-field="calls"]');
        if (callsEl) {
            callsEl.textContent = formatCalls(api.calls);
        }
        var userEl = rowEl.querySelector('[data-field="username"]');
        if (userEl) {
            userEl.textContent = username;
        }
        var statusEl = rowEl.querySelector('[data-field="status_label"]');
        if (statusEl) {
            statusEl.textContent = displayStatusLabel(api.status);
            statusEl.className = 'vs-badge ' + statusBadgeClass(api.status);
        }
        var catCell = rowEl.querySelector('[data-field="category_cell"]');
        if (catCell) {
            catCell.innerHTML = categoryBadgeHtml(category);
        }
        var catEl = rowEl.querySelector('[data-field="category"]');
        if (catEl && !catCell) {
            if (category) {
                catEl.textContent = category;
                catEl.hidden = false;
            } else {
                catEl.hidden = true;
            }
        }
        var typeEl = rowEl.querySelector('[data-field="apitype_badge"]');
        if (typeEl) {
            typeEl.textContent = typeBadge;
            typeEl.className = 'type-badge ' + typeBadgeClass(typeBadge);
        }
        var chargeEl = rowEl.querySelector('[data-field="charge_tag"]');
        if (chargeEl) {
            var tmp = document.createElement('div');
            tmp.innerHTML = chargeBadgeHtml(api);
            var nextCharge = tmp.firstChild;
            if (nextCharge) {
                chargeEl.parentNode.replaceChild(nextCharge, chargeEl);
            }
        }
        var keyEl = rowEl.querySelector('[data-field="needkey_badge"]');
        if (keyEl) {
            var tmpKey = document.createElement('div');
            tmpKey.innerHTML = keyBadgeHtml(api.needkey_badge || '');
            var nextKey = tmpKey.firstChild;
            if (nextKey) {
                keyEl.parentNode.replaceChild(nextKey, keyEl);
            }
        }
        var qpmEl = rowEl.querySelector('[data-field="qpm_badge"]');
        var qpmHtml = qpmBadgeHtml(api);
        if (qpmHtml) {
            var tmpQpm = document.createElement('div');
            tmpQpm.innerHTML = qpmHtml;
            var nextQpm = tmpQpm.firstChild;
            if (qpmEl && nextQpm) {
                qpmEl.parentNode.replaceChild(nextQpm, qpmEl);
            } else if (!qpmEl && nextQpm) {
                var keyAnchor = rowEl.querySelector('[data-field="needkey_badge"]');
                if (keyAnchor && keyAnchor.parentNode) {
                    if (keyAnchor.nextSibling) {
                        keyAnchor.parentNode.insertBefore(nextQpm, keyAnchor.nextSibling);
                    } else {
                        keyAnchor.parentNode.appendChild(nextQpm);
                    }
                }
            }
        } else if (qpmEl && qpmEl.parentNode) {
            qpmEl.parentNode.removeChild(qpmEl);
        }
        var tagsEl = rowEl.querySelector('[data-field="tags"]');
        if (tagsEl && !isDesktop) {
            tagsEl.innerHTML = buildMobileTagsHtml(api);
        }
        var iconImg = rowEl.querySelector('[data-field="icon"]');
        if (iconImg) {
            iconImg.src = safeIconUrl(api.icon);
        }
        var actions = rowEl.querySelector('.action-btns');
        if (actions) {
            actions.outerHTML = buildActionButtons(api);
        } else {
            var actionsWrap = rowEl.querySelector('.api-card__actions');
            if (actionsWrap) {
                actionsWrap.innerHTML = buildActionButtons(api);
            }
        }
        if (!isDesktop) {
            var methodInfoItems = rowEl.querySelectorAll('.api-card__info-item');
            if (methodInfoItems.length > 1) {
                methodInfoItems[1].innerHTML = '<span class="api-card__info-label">方式</span> ' + methodBadgesInlineHtml(api);
            }
        }
    }

    function updateItem(apiId, api) {
        if (!api) {
            return;
        }
        var pair = getRowPair(apiId);
        if (pair.desktop) {
            updateRowFields(pair.desktop, api);
        }
        if (pair.mobile) {
            updateRowFields(pair.mobile, api);
        }
    }

    function appendItem(api) {
        ensureListVisible();
        if (listEl) {
            listEl.insertAdjacentHTML('afterbegin', buildDesktopRowHtml(api));
        }
        if (mobileEl) {
            mobileEl.insertAdjacentHTML('afterbegin', buildMobileCardHtml(api));
        }
        currentPage = 1;
        refreshStatsFromDom();
        applyListView();
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
        if (fields.methodChecks && fields.methodChecks.length) {
            setSelectedMethods('GET');
        }
        if (fields.keywayChecks && fields.keywayChecks.length) {
            setSelectedKeyways('query');
        }
        if (fields.status) {
            fields.status.value = '0';
        }
        if (fields.endpoint) {
            fields.endpoint.value = '';
        }
        if (fields.targeturl) {
            fields.targeturl.value = '';
        }
        if (fields.proxyslug) {
            fields.proxyslug.value = '';
        }
        if (fields.upmethod) {
            fields.upmethod.value = '0';
        }
        if (fields.upauth) {
            fields.upauth.value = '0';
        }
        if (fields.upkeyvia) {
            fields.upkeyvia.value = '0';
        }
        if (fields.upkeyname) {
            fields.upkeyname.value = '';
        }
        if (fields.upkey) {
            fields.upkey.value = '';
        }
        if (fields.upuamode) {
            fields.upuamode.value = '0';
        }
        if (fields.upuapreset) {
            fields.upuapreset.value = '';
        }
        if (fields.upua) {
            fields.upua.value = '';
        }
        if (fields.upreferermode) {
            fields.upreferermode.value = '0';
        }
        if (fields.upreferer) {
            fields.upreferer.value = '';
        }
        setJsonRewriteFromConfig('');
        if (fields.category) {
            fields.category.value = '';
        }
        if (fields.requireKey) {
            fields.requireKey.value = '0';
        }
        if (fields.qpm) {
            fields.qpm.value = '0';
        }
        if (fields.charge) {
            fields.charge.value = '0';
        }
        if (fields.price) {
            fields.price.value = '';
        }
        syncChargeUi();
        if (fields.params) {
            fields.params.value = '';
        }
        if (window.VsParamsEditor && fields.paramsEditor) {
            window.VsParamsEditor.setValue(fields.paramsEditor, '');
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
        setApiType(0);
        setIconPickerSelection(defaultIcons.length ? defaultIcons[0] : '');
        switchFormTab('basic');
        syncKeywaysUi();
        if (window.VSPick) {
            ['apiListFormStatus', 'apiListFormCategory', 'apiListFormRequireKey', 'apiListFormUpMethod', 'apiListFormUpAuth', 'apiListFormUpUaMode', 'apiListFormUpUaPreset', 'apiListFormUpRefererMode'].forEach(function (id) {
                var s = document.getElementById(id);
                if (s) { window.VSPick.refresh(s); }
            });
        }
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
        if (fields.methodChecks && fields.methodChecks.length) {
            setSelectedMethods(api.methods || api.method || 'GET');
        }
        if (fields.status) {
            fields.status.value = String(normalizeStatus(api.status));
        }
        var apiType = parseInt(api.apitype, 10) === 1 ? 1 : 0;
        setApiType(apiType);
        if (fields.endpoint) {
            fields.endpoint.value = apiType === 1 ? '' : (api.endpoint || '');
        }
        if (fields.targeturl) {
            fields.targeturl.value = api.targeturl || (apiType === 1 ? (api.endpoint || '') : '');
        }
        if (fields.proxyslug) {
            fields.proxyslug.value = api.proxyslug || '';
        }
        if (fields.upmethod) {
            fields.upmethod.value = String(parseInt(api.upmethod, 10) || 0);
        }
        if (fields.upauth) {
            fields.upauth.value = encodeUpAuthUi(api.upauth, api.upkeyvia);
        }
        if (fields.upkeyvia) {
            fields.upkeyvia.value = String(parseInt(api.upkeyvia, 10) === 1 ? 1 : 0);
        }
        if (fields.upkeyname) {
            fields.upkeyname.value = api.upkeyname || '';
        }
        if (fields.upkey) {
            fields.upkey.value = api.upkey || '';
        }
        if (fields.upuamode) {
            fields.upuamode.value = String(parseInt(api.upuamode, 10) || 0);
        }
        if (fields.upuapreset) {
            fields.upuapreset.value = api.upuapreset || '';
        }
        if (fields.upua) {
            fields.upua.value = api.upua || '';
        }
        if (fields.upreferermode) {
            fields.upreferermode.value = String(parseInt(api.upreferermode, 10) || 0);
        }
        if (fields.upreferer) {
            fields.upreferer.value = api.upreferer || '';
        }
        setJsonRewriteFromConfig(api.jsonrewrite || '');
        syncUpAuthUi();
        if (fields.category) {
            fields.category.value = api.category || '';
        }
        if (fields.requireKey) {
            fields.requireKey.value = String(parseInt(api.needkey, 10) || 0);
        }
        if (fields.keywayChecks && fields.keywayChecks.length) {
            setSelectedKeyways(api.keyways || 'query');
        }
        syncKeywaysUi();
        if (fields.qpm) {
            var qpmVal = parseInt(api.qpm, 10);
            fields.qpm.value = String(isNaN(qpmVal) || qpmVal < 0 ? 0 : qpmVal);
        }
        if (fields.charge) {
            fields.charge.value = String(parseInt(api.charge, 10) === 1 ? 1 : 0);
        }
        if (fields.price) {
            fields.price.value = (parseInt(api.charge, 10) === 1 && api.price) ? String(api.price) : '';
        }
        syncChargeUi();
        if (fields.params) {
            fields.params.value = api.params || '';
        }
        if (window.VsParamsEditor && fields.paramsEditor) {
            window.VsParamsEditor.setValue(fields.paramsEditor, api.params || '');
        }
        syncKeyParam();
        if (fields.response) {
            fields.response.value = api.response || '';
        }
        if (fields.docNormal) {
            fields.docNormal.value = api.doc || '';
        }
        if (fields.docAi) {
            fields.docAi.value = api.aidoc || '';
        }
        if (formTitle) {
            formTitle.textContent = '编辑接口';
        }
        setIconPickerSelection(api.icon || api.icon_raw || '');
        switchFormTab('basic');
        if (window.VSPick) {
            ['apiListFormStatus','apiListFormCategory','apiListFormRequireKey','apiListFormCharge','apiListFormUpMethod','apiListFormUpAuth','apiListFormUpUaMode','apiListFormUpUaPreset','apiListFormUpRefererMode'].forEach(function (id) {
                var s = document.getElementById(id);
                if (s) { window.VSPick.refresh(s); }
            });
        }
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
        if (window.VsParamsEditor && typeof window.VsParamsEditor.closeTypePicker === 'function') {
            window.VsParamsEditor.closeTypePicker();
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
        var apiType = fields.apitype ? String(parseInt(fields.apitype.value, 10) === 1 ? 1 : 0) : '0';
        var paramsVal = '';
        if (window.VsParamsEditor && fields.paramsEditor) {
            var got = window.VsParamsEditor.getValue(fields.paramsEditor);
            if (got && typeof got === 'object' && got.error) {
                return { __error: got.error };
            }
            paramsVal = typeof got === 'string' ? got : '';
            if (fields.params) {
                fields.params.value = paramsVal;
            }
        } else if (fields.params) {
            paramsVal = fields.params.value.trim();
        }
        return {
            name: fields.name ? fields.name.value.trim() : '',
            description: fields.description ? fields.description.value.trim() : '',
            apitype: apiType,
            endpoint: fields.endpoint ? fields.endpoint.value.trim() : '',
            targeturl: fields.targeturl ? fields.targeturl.value.trim() : '',
            proxyslug: fields.proxyslug ? fields.proxyslug.value.trim() : '',
            upauth: (function () {
                var d = decodeUpAuthUi(fields.upauth ? fields.upauth.value : '0');
                return String(d.upauth);
            })(),
            upkeyvia: (function () {
                var d = decodeUpAuthUi(fields.upauth ? fields.upauth.value : '0');
                return String(d.upkeyvia);
            })(),
            upkeyname: fields.upkeyname ? fields.upkeyname.value.trim() : '',
            upkey: fields.upkey ? fields.upkey.value.trim() : '',
            upmethod: fields.upmethod ? String(parseInt(fields.upmethod.value, 10) || 0) : '0',
            upuamode: fields.upuamode ? String(parseInt(fields.upuamode.value, 10) || 0) : '0',
            upuapreset: fields.upuapreset ? fields.upuapreset.value : '',
            upua: fields.upua ? fields.upua.value.trim() : '',
            upreferermode: fields.upreferermode ? String(parseInt(fields.upreferermode.value, 10) || 0) : '0',
            upreferer: fields.upreferer ? fields.upreferer.value.trim() : '',
            jsonrewrite: (function () {
                syncJsonRewriteHidden();
                return fields.jsonrewrite ? fields.jsonrewrite.value : '';
            })(),
            method: getSelectedMethods().join(','),
            params: paramsVal,
            response: fields.response ? fields.response.value : '',
            doc: fields.docNormal ? fields.docNormal.value : '',
            aidoc: fields.docAi ? fields.docAi.value : '',
            needkey: fields.requireKey ? String(fields.requireKey.value || '0') : '0',
            keyways: getSelectedKeyways().join(','),
            qpm: fields.qpm ? String(Math.max(0, parseInt(fields.qpm.value, 10) || 0)) : '0',
            charge: fields.charge ? String(parseInt(fields.charge.value, 10) === 1 ? 1 : 0) : '0',
            price: fields.price ? String(fields.price.value || '') : '',
            status: fields.status ? String(normalizeStatus(fields.status.value)) : '0',
            icon: getSelectedIconUrl(),
            category: fields.category ? fields.category.value : ''
        };
    }

    function handleFormSubmit() {
        var payload = collectPayload();
        if (payload.__error) {
            window.VS.showMessage(payload.__error, 'error');
            switchFormTab('params');
            return;
        }
        if (!payload.name) {
            window.VS.showMessage('请填写接口名称', 'error');
            switchFormTab('basic');
            if (fields.name) {
                fields.name.focus();
            }
            return;
        }
        var isProxy = parseInt(payload.apitype, 10) === 1;
        if (isProxy) {
            if (!payload.targeturl || !/^https?:\/\//i.test(payload.targeturl)) {
                window.VS.showMessage('请填写完整的上游地址（以 http:// 或 https:// 开头）', 'error');
                switchFormTab('basic');
                if (fields.targeturl) {
                    fields.targeturl.focus();
                }
                return;
            }
            if (!/^[a-zA-Z0-9]{3,64}$/.test(payload.proxyslug || '')) {
                window.VS.showMessage('请填写 3～64 位字母或数字短码', 'error');
                switchFormTab('basic');
                if (fields.proxyslug) {
                    fields.proxyslug.focus();
                }
                return;
            }
            var upMode = parseInt(payload.upauth, 10) || 0;
            var isEdit = !!(formId && formId.value);
            if ((upMode === 1 || upMode === 2) && !payload.upkey && !isEdit) {
                window.VS.showMessage(upMode === 2 ? '请填写 Bearer Token' : '请填写上游 API Key', 'error');
                switchFormTab('basic');
                if (fields.upkey) {
                    fields.upkey.focus();
                }
                return;
            }
        } else if (!payload.endpoint) {
            window.VS.showMessage('请填写本地接口路径', 'error');
            switchFormTab('basic');
            if (fields.endpoint) {
                fields.endpoint.focus();
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
        if (getSelectedMethods().length === 0) {
            window.VS.showMessage('请至少选择一种请求方式', 'error');
            switchFormTab('basic');
            return;
        }
        if (payload.params) {
            try {
                var parsed = JSON.parse(payload.params);
                if (!Array.isArray(parsed)) {
                    throw new Error('not array');
                }
            } catch (err) {
                window.VS.showMessage('请求参数须为合法 JSON 数组', 'error');
                switchFormTab('params');
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
                setDraftHint('');
                if (formMode !== 'edit') {
                    clearLocalDraft();
                }
                var summary = data.api_summary || data.api || {};
                if (formMode === 'edit') {
                    updateItem(summary.id, summary);
                    applySearchFilter();
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

    var draftHint = document.getElementById('apiListDraftHint');
    var draftTimer = null;
    var draftBusy = false;
    var draftSkip = false;
    var LOCAL_DRAFT_KEY = 'vs_api_form_draft_v1';

    function setDraftHint(text) {
        if (!draftHint) {
            return;
        }
        if (!text) {
            draftHint.hidden = true;
            draftHint.textContent = '';
            return;
        }
        draftHint.hidden = false;
        draftHint.textContent = text;
    }

    function refreshMdField(el) {
        if (!el) {
            return;
        }
        try {
            el.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (err) {
            var ev = document.createEvent('Event');
            ev.initEvent('input', true, true);
            el.dispatchEvent(ev);
        }
    }

    function saveLocalDraft(payload) {
        try {
            localStorage.setItem(LOCAL_DRAFT_KEY, JSON.stringify({
                saved_at: Date.now(),
                payload: payload
            }));
            setDraftHint('已保存到本地草稿');
        } catch (e) {
            setDraftHint('');
        }
    }

    function loadLocalDraft() {
        try {
            var raw = localStorage.getItem(LOCAL_DRAFT_KEY);
            if (!raw) {
                return null;
            }
            var obj = JSON.parse(raw);
            return obj && obj.payload ? obj.payload : null;
        } catch (e) {
            return null;
        }
    }

    function clearLocalDraft() {
        try {
            localStorage.removeItem(LOCAL_DRAFT_KEY);
        } catch (e) {}
    }

    function scheduleDraftSave() {
        if (!formOverlay || !formOverlay.classList.contains('is-open') || draftSkip) {
            return;
        }
        if (draftTimer) {
            clearTimeout(draftTimer);
        }
        draftTimer = setTimeout(function () {
            draftTimer = null;
            runDraftSave();
        }, 1800);
    }

    function runDraftSave() {
        if (draftBusy || !formOverlay || !formOverlay.classList.contains('is-open')) {
            return;
        }
        var payload = collectPayload();
        if (payload.__error) {
            return;
        }
        if (!payload.name && !payload.doc && !payload.aidoc) {
            return;
        }
        if (formMode !== 'edit') {
            saveLocalDraft(payload);
            return;
        }
        var id = formId ? String(formId.value || '') : '';
        if (!id) {
            saveLocalDraft(payload);
            return;
        }
        draftBusy = true;
        payload.api_id = id;
        postAction('draft_save', payload)
            .then(function (data) {
                if (data && data.code === 1) {
                    if (data.local_only) {
                        saveLocalDraft(payload);
                    } else {
                        setDraftHint(data.msg || '已自动保存');
                    }
                }
            })
            .catch(function () {})
            .finally(function () {
                draftBusy = false;
            });
    }

    function bindDraftAutoSave() {
        if (!formEl) {
            return;
        }
        formEl.addEventListener('input', scheduleDraftSave);
        formEl.addEventListener('change', scheduleDraftSave);
    }

    function setTextareaValue(el, text) {
        if (!el) {
            return;
        }
        el.value = text == null ? '' : String(text);
        refreshMdField(el);
    }

    var aiBanners = {
        doc: {
            el: document.getElementById('apiListAiBanner'),
            text: document.getElementById('apiListAiBannerText'),
            time: document.getElementById('apiListAiBannerTime')
        },
        code: {
            el: document.getElementById('apiListAiBannerCode'),
            text: document.getElementById('apiListAiBannerCodeText'),
            time: document.getElementById('apiListAiBannerCodeTime')
        }
    };
    var aiBanner = aiBanners.doc.el;
    var aiBannerText = aiBanners.doc.text;
    var aiBannerTime = aiBanners.doc.time;
    var aiActiveBannerKind = 'doc';
    var aiDocBtn = document.getElementById('apiListAiDocBtn');
    var aiDocContinueBtn = document.getElementById('apiListAiDocContinueBtn');
    var aiChatClearBtn = document.getElementById('apiListAiChatClearBtn');
    var aiCodeBtn = document.getElementById('apiListAiCodeBtn');
    var aiCodeRetryBtn = document.getElementById('apiListAiCodeRetryBtn');
    var aiCodeClearBtn = document.getElementById('apiListAiCodeClearBtn');
    var aiBusy = false;
    var aiTickTimer = null;
    var aiStageTimer = null;
    var aiStartedAt = 0;
    var aiAbort = null;
    var aiDocPartial = false;
    var aiDocResumeIndex = 0;
    var aiCodeLastState = null;

    function setAiDocContinueVisible(show) {
        aiDocPartial = !!show;
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
        var okChunks = [];
        pieces.forEach(function (p) {
            if (p) {
                okChunks.push(p);
            }
        });
        return okChunks.join('\n\n');
    }

    function stripAidocForAuth(text, auth) {
        var a = String(auth || '').toLowerCase();
        if (!a) {
            return String(text || '').trim();
        }
        return String(text || '')
            .replace(/:::qs[^\n]*\n[\s\S]*?:::/gi, function (block) {
                var m = block.match(/\bauth\s*=\s*([a-z]+)/i);
                if (m && String(m[1]).toLowerCase() === a) {
                    return '';
                }
                return block;
            })
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function combineAidocKeep(kept, merged) {
        var parts = [];
        if (kept) {
            parts.push(kept);
        }
        if (merged) {
            parts.push(merged);
        }
        return parts.join('\n\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    function aiNow() {
        var d = new Date();
        function pad(n) { return n < 10 ? '0' + n : String(n); }
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function aiTermEls(kind) {
        if (kind === 'code') {
            return {
                details: document.getElementById('apiListAiTermCode'),
                log: document.getElementById('apiListAiTermCodeLog')
            };
        }
        return {
            details: document.getElementById('apiListAiTermDoc'),
            log: document.getElementById('apiListAiTermDocLog')
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
        var prev = els.log.textContent || '';
        if (prev === '尚未开始生成。') {
            prev = '';
        }
        els.log.textContent = prev + '[' + aiNow() + '] ' + line + '\n';
        els.log.scrollTop = els.log.scrollHeight;
    }

    function aiTermOpen(kind, running) {
        var els = aiTermEls(kind);
        if (els.details) {
            els.details.open = true;
            els.details.classList.toggle('is-running', !!running);
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

    function aiBannerRef(kind) {
        return aiBanners[kind === 'code' ? 'code' : 'doc'] || aiBanners.doc;
    }

    function aiSetBanner(state, text, kind) {
        var targetKind = kind === 'code' ? 'code' : 'doc';
        aiActiveBannerKind = targetKind;
        Object.keys(aiBanners).forEach(function (k) {
            if (k !== targetKind && aiBanners[k].el) {
                aiBanners[k].el.hidden = true;
            }
        });
        var ref = aiBannerRef(targetKind);
        aiBanner = ref.el;
        aiBannerText = ref.text;
        aiBannerTime = ref.time;
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

    function aiHideBannerLater(kind) {
        var hideKind = kind || aiActiveBannerKind;
        setTimeout(function () {
            if (aiBusy) {
                return;
            }
            var ref = aiBannerRef(hideKind);
            if (ref.el) {
                ref.el.hidden = true;
            }
        }, 5000);
    }

    function setAiCodeRetryVisible(show) {
        if (aiCodeRetryBtn) {
            aiCodeRetryBtn.hidden = !show;
        }
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

    function buildCodeJobs(payload, onlyAuth) {
        var langs = ['curl', 'typescript', 'browser', 'python', 'go', 'java', 'php', 'cpp', 'rust'];
        var need = parseInt(payload.needkey, 10) || 0;
        var ways = need === 0 ? ['query'] : getSelectedKeyways();
        ways = ways.filter(function (w) {
            return w === 'query' || w === 'header' || w === 'bearer';
        });
        if (!ways.length) {
            ways = ['query'];
        }
        var filterAuth = String(onlyAuth || '').toLowerCase();
        if (filterAuth === 'query' || filterAuth === 'header' || filterAuth === 'bearer') {
            ways = ways.filter(function (w) {
                return w === filterAuth;
            });
            if (!ways.length) {
                ways = [filterAuth];
            }
        }
        var jobs = [];
        ways.forEach(function (auth) {
            langs.forEach(function (lang) {
                jobs.push({ auth: auth, lang: lang });
            });
        });
        return jobs;
    }

    function runAiCodePieces(payload, btn, retryOnly, onlyAuth) {
        var kind = 'code';
        var title = '代码示例';
        var filterAuth = '';
        var keptBase = '';
        if (retryOnly && aiCodeLastState) {
            filterAuth = String(aiCodeLastState.onlyAuth || '').toLowerCase();
            keptBase = String(aiCodeLastState.keptBase || '');
        } else {
            filterAuth = String(onlyAuth || '').toLowerCase();
            if (filterAuth !== 'query' && filterAuth !== 'header' && filterAuth !== 'bearer') {
                filterAuth = '';
            }
            if (filterAuth && fields.docAi) {
                keptBase = stripAidocForAuth(String(fields.docAi.value || ''), filterAuth);
            }
        }
        var allJobs = buildCodeJobs(payload, filterAuth);
        var opts = getAiCodeOpts();
        var concurrency = opts.concurrency;
        var pieces;
        var jobIndexes;
        var failed = [];
        var doneCount = 0;
        var runningLabels = {};
        var charsWritten = 0;
        var scopeLabel = filterAuth
            ? (authWayLabel(filterAuth) + ' · 9 语言')
            : ('已选鉴权×9 语言 · 共 ' + allJobs.length + ' 片');

        if (retryOnly && aiCodeLastState && aiCodeLastState.pieces && aiCodeLastState.jobs) {
            pieces = aiCodeLastState.pieces.slice();
            allJobs = aiCodeLastState.jobs;
            jobIndexes = [];
            allJobs.forEach(function (job, idx) {
                if (!pieces[idx]) {
                    jobIndexes.push(idx);
                }
            });
            if (!jobIndexes.length) {
                window.VS.showMessage('没有可重试的失败片', 'info');
                return;
            }
        } else {
            pieces = new Array(allJobs.length);
            jobIndexes = allJobs.map(function (_, idx) { return idx; });
        }

        switchFormTab('docs');
        aiBusy = true;
        aiStartedAt = Date.now();
        aiStopTimers();
        setAiCodeRetryVisible(false);
        if (!retryOnly) {
            aiTermClear(kind);
        }
        aiTermOpen(kind, true);
        var useSse = true;
        aiTermAppend(kind, (retryOnly ? '重试失败片「' : '开始分片生成「') + title + '」· 本轮 '
            + jobIndexes.length + '/' + allJobs.length + ' 片 · '
            + (opts.mode === 'parallel' ? ('并行×' + concurrency + ' · SSE') : '单线程 · SSE 保活')
            + '（' + scopeLabel + '）');
        aiSetBanner('run', '代码示例 0/' + jobIndexes.length, 'code');
        if (aiBannerTime) {
            aiBannerTime.textContent = '已用时 0秒';
        }
        if (aiDocBtn) {
            aiDocBtn.disabled = true;
        }
        if (aiCodeBtn) {
            aiCodeBtn.disabled = true;
        }
        if (aiCodeRetryBtn) {
            aiCodeRetryBtn.disabled = true;
        }
        setAiCodeAuthButtonsDisabled(true);
        if (btn) {
            btn.disabled = true;
        }

        function composeLiveAidoc() {
            var merged = mergeCodePieces(pieces);
            if (!merged && !keptBase) {
                return '';
            }
            return filterAuth ? combineAidocKeep(keptBase, merged) : merged;
        }

        function refreshRunBanner() {
            var running = Object.keys(runningLabels).map(function (k) {
                return runningLabels[k];
            });
            var tip = '正在生成代码示例 ' + doneCount + '/' + jobIndexes.length;
            if (running.length) {
                tip += ' · 进行中：' + running.slice(0, 3).join('、');
                if (running.length > 3) {
                    tip += ' 等';
                }
            }
            tip += '（' + aiElapsedLabel() + ' · 已回填约 ' + charsWritten + ' 字）';
            aiSetBanner('run', tip, 'code');
        }

        aiTickTimer = setInterval(function () {
            if (aiBannerTime) {
                aiBannerTime.textContent = '已用时 ' + aiElapsedLabel();
            }
            if (aiBusy) {
                refreshRunBanner();
            }
        }, 1000);

        window.VS.showMessage(
            (retryOnly ? '正在重试失败片（' : '正在分片生成代码示例（') + jobIndexes.length + ' 片），进度见下方代码区',
            'info'
        );

        function finishAll() {
            var merged = mergeCodePieces(pieces);
            aiCodeLastState = {
                payload: payload,
                jobs: allJobs,
                pieces: pieces.slice(),
                onlyAuth: filterAuth,
                keptBase: keptBase
            };
            var miss = 0;
            allJobs.forEach(function (_, idx) {
                if (!pieces[idx]) {
                    miss += 1;
                }
            });
            setAiCodeRetryVisible(miss > 0);

            var finalText = filterAuth ? combineAidocKeep(keptBase, merged) : merged;
            if (!finalText) {
                var failMsg = failed.length
                    ? ('全部失败：' + failed.slice(0, 5).join('；') + (failed.length > 5 ? '…' : ''))
                    : '未能生成任何代码块';
                aiTermAppend(kind, failMsg);
                aiTermStopRunning(kind);
                aiSetBanner('error', failMsg, 'code');
                window.VS.showMessage(failMsg, 'error');
                return;
            }
            if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
                finalText = window.VsSyntax.scrubHighlightLeak(finalText);
            }
            draftSkip = true;
            setTextareaValue(fields.docAi, finalText);
            draftSkip = false;
            charsWritten = finalText.length;
            aiTermAppend(kind, '已写入代码示例（成功 '
                + pieces.filter(Boolean).length + '/' + allJobs.length
                + '，约 ' + finalText.length + ' 字符）');
            if (failed.length || miss > 0) {
                var warn = '部分示例未成功：' + failed.slice(0, 12).join('、')
                    + (failed.length > 12 ? (' 等共 ' + failed.length + ' 项') : '')
                    + '。可点「重试失败」。';
                aiTermAppend(kind, '注意：' + warn);
                window.VS.showMessage(warn, 'info');
            } else {
                window.VS.showMessage('代码示例已全部生成', 'success');
            }
            aiTermAppend(kind, '完成，总用时 ' + aiElapsedLabel());
            aiTermStopRunning(kind);
            aiSetBanner('done', (miss > 0 ? '代码示例已部分生成' : '代码示例已生成')
                + ' · ' + aiElapsedLabel() + ' · ' + finalText.length + ' 字', 'code');
            scheduleDraftSave();
        }

        function flushPiecesLive() {
            var finalText = composeLiveAidoc();
            if (!finalText) {
                return;
            }
            if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
                finalText = window.VsSyntax.scrubHighlightLeak(finalText);
            }
            draftSkip = true;
            setTextareaValue(fields.docAi, finalText);
            draftSkip = false;
            charsWritten = finalText.length;
        }

        function runPool() {
            return new Promise(function (resolve) {
                var next = 0;
                var active = 0;
                var settled = 0;

                function kick() {
                    while (active < concurrency && next < jobIndexes.length) {
                        (function (queuePos) {
                            var jobIndex = jobIndexes[queuePos];
                            var job = allJobs[jobIndex];
                            var label = authWayLabel(job.auth) + ' · ' + job.lang;
                            var key = String(jobIndex);
                            next += 1;
                            active += 1;
                            runningLabels[key] = label;
                            aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + allJobs.length + '] 开始编写 · ' + label);
                            refreshRunBanner();

                            var piecePayload = {};
                            Object.keys(payload).forEach(function (k) {
                                piecePayload[k] = payload[k];
                            });
                            delete piecePayload.doc;
                            delete piecePayload.aidoc;
                            piecePayload.auth = job.auth;
                            piecePayload.lang = job.lang;

                            var pieceDone = function (pieceText) {
                                pieces[jobIndex] = String(pieceText);
                                if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
                                    pieces[jobIndex] = window.VsSyntax.scrubHighlightLeak(pieces[jobIndex]);
                                }
                                flushPiecesLive();
                                aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + allJobs.length + '] 完成并已回填 · ' + label
                                    + '（' + String(pieceText).length + ' 字符）');
                            };
                            var pieceFail = function (errMsg) {
                                failed.push(label + '（' + errMsg + '）');
                                aiTermAppend(kind, '[' + (jobIndex + 1) + '/' + allJobs.length + '] 失败 · ' + label + '：' + errMsg);
                            };
                            var afterPiece = function () {
                                delete runningLabels[key];
                                active -= 1;
                                doneCount += 1;
                                settled += 1;
                                refreshRunBanner();
                                if (settled >= jobIndexes.length) {
                                    resolve();
                                    return;
                                }
                                kick();
                            };

                            var pieceFailed = false;
                            var liveBuf = '';
                            postActionSse('ai_gen_code_piece_stream', piecePayload, {
                                delta: function (d) {
                                    var chunk = d && d.text != null ? String(d.text) : '';
                                    if (!chunk) {
                                        return;
                                    }
                                    liveBuf += chunk;
                                    // 流式过程预览：未完成解析前先写入原始增量，done 再换成合法 :::qs
                                    pieces[jobIndex] = liveBuf;
                                    flushPiecesLive();
                                },
                                done: function (data) {
                                    if (data && data.piece) {
                                        pieceDone(data.piece);
                                    } else {
                                        pieceFailed = true;
                                        pieceFail('空结果');
                                    }
                                },
                                error: function (err) {
                                    pieceFailed = true;
                                    pieceFail((err && err.msg) ? String(err.msg) : '生成失败');
                                }
                            }).catch(function (err) {
                                if (pieceFailed || (err && err.sseHandled)) {
                                    if (!pieceFailed && err && err.sseHandled) {
                                        pieceFailed = true;
                                        pieceFail((err && err.message) ? String(err.message) : '生成失败');
                                    }
                                    return;
                                }
                                var hint = '网络异常或 CDN/网关超时';
                                if (err && err.message === 'invalid_json') {
                                    hint = '流被切断。CDN 请关闭缓冲并加大回源超时；或改单线程';
                                } else if (err && err.message) {
                                    hint = String(err.message);
                                }
                                pieceFail(hint);
                            }).then(afterPiece);
                        })(next);
                    }
                }

                if (!jobIndexes.length) {
                    resolve();
                    return;
                }
                kick();
            });
        }

        runPool()
            .then(finishAll)
            .finally(function () {
                aiBusy = false;
                aiStopTimers();
                if (aiBannerTime) {
                    aiBannerTime.textContent = '总用时 ' + aiElapsedLabel();
                }
                if (aiDocBtn) {
                    aiDocBtn.disabled = false;
                }
                if (aiCodeBtn) {
                    aiCodeBtn.disabled = false;
                }
                if (aiCodeRetryBtn) {
                    aiCodeRetryBtn.disabled = false;
                }
                setAiCodeAuthButtonsDisabled(false);
                aiHideBannerLater('code');
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
            switchFormTab('params');
            return;
        }
        if (!payload.name) {
            window.VS.showMessage('请先填写接口名称', 'error');
            switchFormTab('basic');
            return;
        }
        delete payload.upkey;
        delete payload.targeturl;
        if (formMode === 'edit' && formId) {
            payload.api_id = formId.value || '';
        }

        if (action === 'ai_gen_code') {
            runAiCodePieces(payload, btn);
            return;
        }

        runAiDocSections(payload, btn, !!continueFlag);
    }

    function defaultDocSections() {
        return [
            { id: 'intro', title: '接口说明' },
            { id: 'call', title: '调用地址、请求方式与鉴权' },
            { id: 'params', title: '请求参数' },
            { id: 'success', title: '成功响应与字段说明' },
            { id: 'errors', title: '错误响应与业务错误码' },
            { id: 'examples', title: '调用示例' },
            { id: 'notes', title: '注意事项' }
        ];
    }

    function getDocSections() {
        var list = window.VS_AI_CODE && window.VS_AI_CODE.doc_sections;
        if (Array.isArray(list) && list.length) {
            return list;
        }
        return defaultDocSections();
    }

    /**
     * 详细文档：按章节顺序多次请求，逐章回填
     */
    function runAiDocSections(payload, btn, continueFlag) {
        var sections = getDocSections();
        var startIdx = continueFlag ? Math.max(0, Number(aiDocResumeIndex) || 0) : 0;
        if (startIdx >= sections.length) {
            if (continueFlag) {
                window.VS.showMessage('文档章节已全部生成，无需继续', 'info');
                setAiDocContinueVisible(false);
                aiDocResumeIndex = 0;
                return;
            }
            startIdx = 0;
        }
        var kind = 'doc';
        var assembled = '';
        if (continueFlag && fields.docNormal) {
            assembled = String(fields.docNormal.value || '').replace(/\s+$/, '');
        }
        // 同一次任务内：章节失败自动重试 1 次；第二次失败才要人点「继续生成」
        var autoRetryUsed = 0;

        switchFormTab('docs');
        aiBusy = true;
        aiStartedAt = Date.now();
        aiStopTimers();
        if (aiAbort) {
            try { aiAbort.abort(); } catch (eAbort) { /* ignore */ }
        }
        aiAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        aiTermClear(kind);
        aiTermOpen(kind, true);
        aiTermAppend(kind, continueFlag
            ? ('从第 ' + (startIdx + 1) + '/' + sections.length + ' 章继续…')
            : ('正在生成详细文档（共 ' + sections.length + ' 章）…'));
        aiSetBanner('run', '正在生成详细文档，请稍等', 'doc');
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
        setAiCodeAuthButtonsDisabled(true);
        if (btn) {
            btn.disabled = true;
        }

        if (!continueFlag) {
            draftSkip = true;
            setTextareaValue(fields.docNormal, '');
            draftSkip = false;
            assembled = '';
            aiDocResumeIndex = 0;
            setAiDocContinueVisible(false);
        }

        var liveSection = '';
        var currentIdx = startIdx;
        aiTickTimer = setInterval(function () {
            if (aiBannerTime) {
                aiBannerTime.textContent = '已用时 ' + aiElapsedLabel();
            }
            if (aiBannerText && aiBanner && !aiBanner.classList.contains('is-done') && !aiBanner.classList.contains('is-error')) {
                aiBannerText.textContent = '正在生成详细文档，请稍等（'
                    + (currentIdx + 1) + '/' + sections.length + ' · ' + aiElapsedLabel() + '）';
            }
        }, 1000);

        window.VS.showMessage('正在生成详细文档，请稍等', 'info');

        function paintDoc(text) {
            draftSkip = true;
            if (fields.docNormal) {
                fields.docNormal.value = text;
            }
            draftSkip = false;
        }

        function requestSection(index) {
            var sec = sections[index];
            var secId = sec && sec.id ? String(sec.id) : '';
            var secTitle = sec && sec.title ? String(sec.title) : secId;
            var baseParts = assembled ? (assembled + '\n\n') : '';
            var sectionFailed = false;
            liveSection = '';
            return postActionSse('ai_gen_doc_section_stream', payload, {
                meta: function () { /* noop */ },
                delta: function (d) {
                    var chunk = d && d.text != null ? String(d.text) : '';
                    if (!chunk) {
                        return;
                    }
                    liveSection += chunk;
                    paintDoc(baseParts + liveSection);
                },
                done: function (data) {
                    var piece = data && data.section != null ? String(data.section) : liveSection;
                    piece = String(piece).replace(/^\s+|\s+$/g, '');
                    if (piece) {
                        assembled = assembled ? (assembled + '\n\n' + piece) : piece;
                    }
                    liveSection = '';
                    paintDoc(assembled);
                    aiTermAppend(kind, '[' + (index + 1) + '/' + sections.length + '] 完成 · ' + secTitle
                        + '（' + piece.length + ' 字）');
                },
                error: function (err) {
                    sectionFailed = true;
                    liveSection = '';
                    paintDoc(assembled);
                    var msg = (err && err.msg) ? String(err.msg) : '生成失败';
                    aiTermAppend(kind, '[' + (index + 1) + '/' + sections.length + '] 失败 · ' + secTitle + '：' + msg);
                }
            }, { section: secId }).then(function () {
                if (sectionFailed) {
                    var stop = new Error('section_failed');
                    stop.sseHandled = true;
                    throw stop;
                }
                return true;
            });
        }

        function runOne(index) {
            if (index >= sections.length) {
                setAiDocContinueVisible(false);
                aiDocResumeIndex = 0;
                paintDoc(assembled);
                setTextareaValue(fields.docNormal, assembled);
                aiTermAppend(kind, '全部完成，约 ' + assembled.length + ' 字符，用时 ' + aiElapsedLabel());
                aiTermStopRunning(kind);
                aiSetBanner('done', '详细文档已生成', 'doc');
                window.VS.showMessage('详细文档已生成', 'success');
                scheduleDraftSave();
                return Promise.resolve();
            }

            currentIdx = index;
            var sec = sections[index];
            var secTitle = sec && sec.title ? String(sec.title) : String(sec && sec.id ? sec.id : '');
            aiTermAppend(kind, '[' + (index + 1) + '/' + sections.length + '] 开始 · ' + secTitle);
            aiDocResumeIndex = index;

            return requestSection(index).then(function () {
                return runOne(index + 1);
            }).catch(function (err) {
                if (!(err && err.message === 'section_failed') && !(err && err.sseHandled)) {
                    throw err;
                }
                if (autoRetryUsed < 1) {
                    autoRetryUsed += 1;
                    aiTermAppend(kind, '网络异常，正在自动重试第 ' + (index + 1) + ' 章…');
                    aiSetBanner('run', '正在生成详细文档，请稍等', 'doc');
                    window.VS.showMessage('网络异常，正在自动重试', 'info');
                    return requestSection(index).then(function () {
                        return runOne(index + 1);
                    });
                }
                throw err;
            });
        }

        runOne(startIdx)
            .catch(function (err) {
                var msg;
                if (err && err.message === 'section_failed') {
                    msg = '生成中断';
                } else if (err && err.sseError && err.sseError.msg) {
                    msg = String(err.sseError.msg);
                } else if (err && err.message) {
                    msg = String(err.message);
                } else {
                    msg = '网络异常';
                }
                if (msg === 'invalid_json') {
                    msg = '生成中断，可点「继续生成」';
                }
                liveSection = '';
                paintDoc(assembled);
                setTextareaValue(fields.docNormal, assembled);
                if (assembled || currentIdx < sections.length) {
                    setAiDocContinueVisible(true);
                    aiTermAppend(kind, '中断于第 ' + (currentIdx + 1) + ' 章，可点「继续生成」');
                } else {
                    aiTermAppend(kind, '失败：' + msg);
                    aiTermStopRunning(kind);
                }
                aiSetBanner('error', '生成中断，可点「继续生成」', 'doc');
                window.VS.showMessage('生成中断，可点「继续生成」', 'error');
            })
            .finally(function () {
                aiBusy = false;
                aiAbort = null;
                aiStopTimers();
                if (aiBannerTime) {
                    aiBannerTime.textContent = '总用时 ' + aiElapsedLabel();
                }
                if (aiDocBtn) {
                    aiDocBtn.disabled = false;
                }
                if (aiDocContinueBtn) {
                    aiDocContinueBtn.disabled = false;
                }
                if (aiCodeBtn) {
                    aiCodeBtn.disabled = false;
                }
                setAiCodeAuthButtonsDisabled(false);
                refreshMdField(fields.docNormal);
                aiHideBannerLater('doc');
            });
    }

    function AiChatSessionHint() {
        return !!(window.VS_AI_CODE && window.VS_AI_CODE.ready);
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
            if (formMode === 'edit' && formId) {
                payload.api_id = formId.value || '';
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
    (function bindAiCodeAuthButtons() {
        var wrap = document.getElementById('apiListAiCodeWays');
        if (!wrap) {
            return;
        }
        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ai-code-auth]');
            if (!btn || !wrap.contains(btn) || btn.hidden) {
                return;
            }
            e.preventDefault();
            if (aiBusy) {
                window.VS.showMessage('已有生成任务进行中，请稍候', 'info');
                return;
            }
            var auth = String(btn.getAttribute('data-ai-code-auth') || '').toLowerCase();
            if (auth !== 'query' && auth !== 'header' && auth !== 'bearer') {
                return;
            }
            var payload = collectPayload();
            if (payload.__error) {
                window.VS.showMessage(payload.__error, 'error');
                switchFormTab('params');
                return;
            }
            if (!payload.name) {
                window.VS.showMessage('请先填写接口名称', 'error');
                switchFormTab('basic');
                return;
            }
            delete payload.upkey;
            delete payload.targeturl;
            if (formMode === 'edit' && formId) {
                payload.api_id = formId.value || '';
            }
            runAiCodePieces(payload, btn, false, auth);
        });
    })();
    if (aiCodeRetryBtn) {
        aiCodeRetryBtn.addEventListener('click', function () {
            if (aiBusy) {
                window.VS.showMessage('已有生成任务进行中，请稍候', 'info');
                return;
            }
            if (!aiCodeLastState || !aiCodeLastState.payload) {
                window.VS.showMessage('没有可重试的记录，请先生成一次', 'info');
                return;
            }
            runAiCodePieces(aiCodeLastState.payload, aiCodeRetryBtn, true);
        });
    }
    if (aiCodeClearBtn) {
        aiCodeClearBtn.addEventListener('click', function () {
            if (aiBusy) {
                window.VS.showMessage('生成进行中，请稍候再清空', 'info');
                return;
            }
            draftSkip = true;
            setTextareaValue(fields.docAi, '');
            draftSkip = false;
            aiCodeLastState = null;
            setAiCodeRetryVisible(false);
            aiTermClear('code');
            aiTermAppend('code', '已清空代码示例与进程日志');
            if (aiBanners.code.el) {
                aiBanners.code.el.hidden = true;
            }
            window.VS.showMessage('已清空代码示例', 'success');
            scheduleDraftSave();
        });
    }
    bindDraftAutoSave();

    if (openAddBtn) {
        openAddBtn.addEventListener('click', function () {
            openFormOverlay('create');
            var local = loadLocalDraft();
            if (local && window.confirm('检测到未提交的本地草稿，是否恢复？')) {
                draftSkip = true;
                var merged = {
                    id: 0, status: 0, apitype: 0, needkey: 0, charge: 0, qpm: 0
                };
                Object.keys(local).forEach(function (k) {
                    merged[k] = local[k];
                });
                fillForm(merged);
                draftSkip = false;
                setDraftHint('已恢复本地草稿');
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applySearchFilter);
    }

    [filterCategoryEl, filterStatusEl, filterSortEl].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('change', applySearchFilter);
    });

    if (pageSizeEl) {
        if (!pageSizeEl.value) {
            pageSizeEl.value = String(defaultPageSize());
        } else if (window.matchMedia('(max-width: 900px)').matches && pageSizeEl.value === '20') {
            pageSizeEl.value = '10';
        }
        pageSizeEl.addEventListener('change', function () {
            currentPage = 1;
            applyListView();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage -= 1;
                applyListView();
            }
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
            var p = parseInt(btn.getAttribute('data-page'), 10);
            if (!p || p === currentPage) {
                return;
            }
            currentPage = p;
            applyListView();
        });
    }

    applyListView();

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('.vs-api-list-action');
        if (!btn) {
            return;
        }
        var action = btn.getAttribute('data-api-action');
        var apiId = btn.getAttribute('data-api-id');
        var pair = getRowPair(apiId);
        var row = pair.desktop;

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
                normal: 0,
                maintenance: 2,
                disable: 1
            };
            var nextStatus = statusMap[action];
            if (row && parseInt(row.getAttribute('data-api-status'), 10) === nextStatus) {
                return;
            }
            postAction('set_status', {
                api_id: apiId,
                status: String(nextStatus)
            }).then(function (data) {
                if (data.code !== 1 || !row) {
                    window.VS.showMessage(data.msg || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '状态已更新', 'success');
                var api = parseRowPayload(row) || { id: parseInt(apiId, 10) || 0 };
                api.status = normalizeStatus(data.status !== undefined ? data.status : nextStatus);
                api.status_label = displayStatusLabel(api.status);
                updateItem(apiId, api);
                refreshStatsFromDom();
                applyListView();
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
                    if (pair.desktop) {
                        pair.desktop.remove();
                    }
                    if (pair.mobile) {
                        pair.mobile.remove();
                    }
                    refreshStatsFromDom();
                    applyListView();
                }).catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                });
            });
        }
    });

    // 审核页「编辑」跳转：list.php?edit={id}
    (function openEditFromQuery() {
        var match = /[?&]edit=(\d+)/.exec(window.location.search || '');
        if (!match) {
            return;
        }
        var editId = match[1];
        var btn = page.querySelector('.vs-api-list-action[data-api-action="edit"][data-api-id="' + editId + '"]');
        if (btn) {
            btn.click();
            return;
        }
        postAction('get', { api_id: editId }).then(function (data) {
            if (data.code !== 1 || !data.api) {
                return;
            }
            formMode = 'edit';
            fillForm(data.api);
            if (!formOverlay) {
                return;
            }
            formOverlay.hidden = false;
            formOverlay.setAttribute('aria-hidden', 'false');
            formOverlay.classList.add('is-open');
            document.body.classList.add('is-overlay-open');
        }).catch(function () {});
    })();
})();
