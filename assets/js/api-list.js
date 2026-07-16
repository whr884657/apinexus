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
        method: document.getElementById('apiListFormMethod'),
        status: document.getElementById('apiListFormStatus'),
        audit: document.getElementById('apiListFormAudit'),
        apitype: document.getElementById('apiListFormApiType'),
        endpoint: document.getElementById('apiListFormEndpoint'),
        targeturl: document.getElementById('apiListFormTargetUrl'),
        proxyslug: document.getElementById('apiListFormProxySlug'),
        category: document.getElementById('apiListFormCategory'),
        requireKey: document.getElementById('apiListFormRequireKey'),
        params: document.getElementById('apiListFormParams'),
        response: document.getElementById('apiListFormResponse'),
        docNormal: document.getElementById('apiListFormDocNormal'),
        docAi: document.getElementById('apiListFormDocAi')
    };

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

    function statusClass(status) {
        var n = normalizeStatus(status);
        if (n === 1) {
            return 'is-disabled';
        }
        if (n === 2) {
            return 'is-maintenance';
        }
        return 'is-normal';
    }

    function auditClass(audit) {
        var n = normalizeAudit(audit);
        if (n === 1) {
            return 'is-approved';
        }
        if (n === 2) {
            return 'is-rejected';
        }
        return 'is-pending';
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
        page.querySelectorAll('.vs-api-item').forEach(function (row, i) {
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

    function methodSlug(method) {
        var m = String(method || 'GET').toLowerCase().replace(/[^a-z0-9]+/g, '');
        return m || 'get';
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

    function typeTagClass(badge) {
        return badge === '代理' ? 'vs-api-tag--proxy' : 'vs-api-tag--local';
    }

    function buildTagsHtml(api) {
        var audit = normalizeAudit(api.audit);
        var typeBadge = api.apitype_badge || '本地';
        var keyBadge = api.needkey_badge || '';
        var category = api.category ? String(api.category) : '';
        var html = '';
        if (category) {
            html += '<span class="vs-api-tag vs-api-tag--cat" data-field="category">' + escapeHtml(category) + '</span>';
        }
        html += '<span class="vs-api-tag vs-api-tag--free">免费</span>';
        if (keyBadge) {
            html += '<span class="vs-api-tag vs-api-tag--key" data-field="needkey_badge">' + escapeHtml(keyBadge) + '</span>';
        }
        html += '<span class="vs-api-tag ' + typeTagClass(typeBadge) + '" data-field="apitype_badge">'
            + escapeHtml(typeBadge) + '</span>';
        if (audit !== 1) {
            html += '<span class="vs-api-tag vs-api-tag--audit ' + auditClass(audit) + '" data-field="audit_label">'
                + escapeHtml(api.audit_label || '') + '</span>';
        }
        return html;
    }

    function buildActionButtons(api) {
        var id = api.id;
        var status = normalizeStatus(api.status);
        var html = '';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-api-action="edit" data-api-id="' + id + '">编辑</button>';
        if (status !== 0) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-api-action="normal" data-api-id="' + id + '">正常</button>';
        }
        if (status !== 2) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-api-action="maintenance" data-api-id="' + id + '">维护</button>';
        }
        if (status !== 1) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-api-action="disable" data-api-id="' + id + '">禁用</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="' + id + '">删除</button>';
        return html;
    }

    function buildItemHtml(api) {
        var icon = safeIconUrl(api.icon);
        var method = (api.method || 'GET').toUpperCase();
        var status = normalizeStatus(api.status);
        var audit = normalizeAudit(api.audit);
        var callUrl = callUrlOf(api);
        var typeBadge = api.apitype_badge || '本地';
        var username = displayUsername(api);
        var search = (String(api.name || '') + ' ' + callUrl + ' ' + String(api.endpoint || '') + ' '
            + String(api.category || '') + ' ' + typeBadge + ' ' + username).toLowerCase();
        var payload = escapeHtml(JSON.stringify(api));

        var html = '<div class="vs-api-item" data-api-row="' + api.id + '"'
            + ' data-api-status="' + status + '"'
            + ' data-api-audit="' + audit + '"'
            + ' data-search="' + escapeHtml(search) + '"'
            + ' data-payload="' + payload + '">';
        html += '<div class="vs-api-item__top"><div class="vs-api-item__icon">';
        html += '<img src="' + escapeHtml(icon) + '" alt="" width="32" height="32" loading="lazy" data-field="icon">';
        html += '</div><div class="vs-api-item__main">';
        html += '<div class="vs-api-item__row1"><div class="vs-api-item__title">';
        html += '<span class="vs-api-item__name" data-field="name">' + escapeHtml(api.name || '') + '</span>';
        html += '<span class="vs-api-item__id" data-field="id">#' + (parseInt(api.id, 10) || 0) + '</span>';
        html += '</div><div class="vs-api-item__tags" data-field="tags">' + buildTagsHtml(api) + '</div></div>';
        html += '<div class="vs-api-item__endpoint">';
        html += '<span class="vs-api-list-method vs-api-list-method--' + escapeHtml(methodSlug(method)) + '" data-field="method">' + escapeHtml(method) + '</span>';
        html += '<span class="vs-api-item__url" data-field="call_url" title="' + escapeHtml(callUrl) + '">' + escapeHtml(callUrl) + '</span>';
        html += '</div></div></div>';
        html += '<div class="vs-api-item__meta">';
        html += '<span class="vs-api-item__meta-status">状态：<span class="vs-api-tag vs-api-tag--status '
            + statusClass(status) + '" data-field="status_label">' + escapeHtml(api.status_label || String(status)) + '</span></span>';
        html += '<span class="vs-api-item__meta-calls" title="请求次数">请求：<strong data-field="calls">'
            + (parseInt(api.calls, 10) || 0) + '</strong></span>';
        html += '<span class="vs-api-item__meta-author" title="提交者">提交：<em data-field="username">' + escapeHtml(username) + '</em></span>';
        html += '</div>';
        html += '<div class="vs-api-item__actions">' + buildActionButtons(api) + '</div>';
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
        var rows = listEl.querySelectorAll('.vs-api-item');
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

    function matchedRows() {
        if (!listEl) {
            return [];
        }
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        var all = Array.prototype.slice.call(listEl.querySelectorAll('.vs-api-item'));
        if (!q) {
            return all;
        }
        return all.filter(function (row) {
            var hay = row.getAttribute('data-search') || '';
            return hay.indexOf(q) !== -1;
        });
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
        var rows = listEl.querySelectorAll('.vs-api-item');
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
        var all = listEl.querySelectorAll('.vs-api-item');
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
            if (!Object.prototype.hasOwnProperty.call(indexMap, key)) {
                row.hidden = true;
                return;
            }
            var idx = indexMap[key];
            row.hidden = !(idx >= start && idx < end);
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

    function updateItem(rowEl, api) {
        if (!rowEl || !api) {
            return;
        }
        var callUrl = callUrlOf(api);
        var typeBadge = api.apitype_badge || '本地';
        var username = displayUsername(api);
        var status = normalizeStatus(api.status);
        rowEl.setAttribute('data-api-status', String(status));
        rowEl.setAttribute('data-api-audit', String(normalizeAudit(api.audit)));
        rowEl.setAttribute(
            'data-search',
            (String(api.name || '') + ' ' + callUrl + ' ' + String(api.endpoint || '') + ' '
                + String(api.category || '') + ' ' + typeBadge + ' ' + username).toLowerCase()
        );
        rowEl.setAttribute('data-payload', JSON.stringify(api));

        var idEl = rowEl.querySelector('[data-field="id"]');
        if (idEl) {
            idEl.textContent = '#' + (parseInt(api.id, 10) || 0);
        }
        var nameEl = rowEl.querySelector('[data-field="name"]');
        if (nameEl) {
            nameEl.textContent = api.name || '';
        }
        var methodEl = rowEl.querySelector('[data-field="method"]');
        if (methodEl) {
            var m = (api.method || 'GET').toUpperCase();
            methodEl.textContent = m;
            methodEl.className = 'vs-api-list-method vs-api-list-method--' + methodSlug(m);
        }
        var urlEl = rowEl.querySelector('[data-field="call_url"]');
        if (urlEl) {
            urlEl.textContent = callUrl;
            urlEl.setAttribute('title', callUrl);
        }
        var callsEl = rowEl.querySelector('[data-field="calls"]');
        if (callsEl) {
            callsEl.textContent = String(parseInt(api.calls, 10) || 0);
        }
        var userEl = rowEl.querySelector('[data-field="username"]');
        if (userEl) {
            userEl.textContent = username;
        }
        var statusEl = rowEl.querySelector('[data-field="status_label"]');
        if (statusEl) {
            statusEl.textContent = api.status_label || String(status);
            statusEl.className = 'vs-api-tag vs-api-tag--status ' + statusClass(status);
        }
        var tagsEl = rowEl.querySelector('[data-field="tags"]');
        if (tagsEl) {
            tagsEl.innerHTML = buildTagsHtml(api);
        }
        var iconImg = rowEl.querySelector('[data-field="icon"]');
        if (iconImg) {
            iconImg.src = safeIconUrl(api.icon);
        }
        var actions = rowEl.querySelector('.vs-api-item__actions');
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
        if (fields.method) {
            fields.method.value = 'GET';
        }
        if (fields.status) {
            fields.status.value = '0';
        }
        if (fields.audit) {
            fields.audit.value = '1';
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
        setApiType(0);
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
            fields.status.value = String(normalizeStatus(api.status));
        }
        if (fields.audit) {
            fields.audit.value = String(normalizeAudit(api.audit));
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
        if (fields.params) {
            fields.params.value = api.params || '';
        }
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
        var apiType = fields.apitype ? String(parseInt(fields.apitype.value, 10) === 1 ? 1 : 0) : '0';
        return {
            name: fields.name ? fields.name.value.trim() : '',
            description: fields.description ? fields.description.value.trim() : '',
            apitype: apiType,
            endpoint: fields.endpoint ? fields.endpoint.value.trim() : '',
            targeturl: fields.targeturl ? fields.targeturl.value.trim() : '',
            proxyslug: fields.proxyslug ? fields.proxyslug.value.trim() : '',
            method: fields.method ? fields.method.value : 'GET',
            params: fields.params ? fields.params.value.trim() : '',
            response: fields.response ? fields.response.value : '',
            doc: fields.docNormal ? fields.docNormal.value : '',
            aidoc: fields.docAi ? fields.docAi.value : '',
            needkey: fields.requireKey ? String(fields.requireKey.value || '0') : '0',
            status: fields.status ? String(normalizeStatus(fields.status.value)) : '0',
            audit: fields.audit ? String(normalizeAudit(fields.audit.value)) : '1',
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
        if (payload.params) {
            try {
                var parsed = JSON.parse(payload.params);
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
                normal: 0,
                maintenance: 2,
                disable: 1
            };
            var nextStatus = statusMap[action];
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
                api.status_label = data.status_label || api.status;
                updateItem(row, api);
                refreshStatsFromDom();
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
