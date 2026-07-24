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
        status: document.getElementById('apiListFormStatus'),
        apitype: document.getElementById('apiListFormApiType'),
        endpoint: document.getElementById('apiListFormEndpoint'),
        targeturl: document.getElementById('apiListFormTargetUrl'),
        proxyslug: document.getElementById('apiListFormProxySlug'),
        category: document.getElementById('apiListFormCategory'),
        requireKey: document.getElementById('apiListFormRequireKey'),
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
                ? '外链接口：填写对方完整地址与短码，系统生成本站 /apis/短码；访问时跳转上游并附带查询参数。'
                : '本地接口：只填本站路径，如 /api/img/index.php';
        }
        if (endpointLabel) {
            endpointLabel.innerHTML = t === 1
                ? '本地路径'
                : '本地路径 <span class="vs-req">*</span>';
        }
    }

    document.querySelectorAll('.vs-api-type-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setApiType(btn.getAttribute('data-apitype') || '0');
        });
    });

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
    }

    if (fields.charge) {
        fields.charge.addEventListener('change', syncChargeUi);
        syncChargeUi();
    }
    if (fields.requireKey) {
        fields.requireKey.addEventListener('change', syncKeyParam);
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
        html += '<span class="vs-badge ' + statusBadgeClass(api.status) + '" data-field="status_label">'
            + escapeHtml(displayStatusLabel(api.status)) + '</span>';
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
        html += '<td>' + keyBadgeHtml(api.needkey_badge || '') + '</td>';
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
        html += '<div class="api-card__header-right" data-field="tags">' + buildMobileTagsHtml(api) + '</div></div>';
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
        if (fields.category) {
            fields.category.value = '';
        }
        if (fields.requireKey) {
            fields.requireKey.value = '0';
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
        if (window.VSPick) {
            ['apiListFormStatus', 'apiListFormCategory', 'apiListFormRequireKey'].forEach(function (id) {
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
        if (fields.category) {
            fields.category.value = api.category || '';
        }
        if (fields.requireKey) {
            fields.requireKey.value = String(parseInt(api.needkey, 10) || 0);
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
            ['apiListFormStatus','apiListFormCategory','apiListFormRequireKey','apiListFormCharge'].forEach(function (id) {
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
            method: getSelectedMethods().join(','),
            params: paramsVal,
            response: fields.response ? fields.response.value : '',
            doc: fields.docNormal ? fields.docNormal.value : '',
            aidoc: fields.docAi ? fields.docAi.value : '',
            needkey: fields.requireKey ? String(fields.requireKey.value || '0') : '0',
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
        } else if (!payload.endpoint) {
            window.VS.showMessage('请填写本地接口路径', 'error');
            switchFormTab('basic');
            if (fields.endpoint) {
                fields.endpoint.focus();
            }
            return;
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

    if (openAddBtn) {
        openAddBtn.addEventListener('click', function () {
            openFormOverlay('create');
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
