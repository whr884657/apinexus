/**
 * 文件：assets/js/api-categories.js
 * 作用：后台接口分类（桌面表格 + 手机卡片双 DOM；搜索/分页）
 */
(function () {
    'use strict';

    function boot() {
        if (!window.VS) {
            setTimeout(boot, 30);
            return;
        }
        init();
    }

    function init() {
        var page = document.getElementById('apiCategoriesPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('apiCatTableWrap');
        var listEl = document.getElementById('apiCategoryBody');
        var mobileEl = document.getElementById('apiCatMobile');
        var emptyEl = document.getElementById('apiCategoryEmpty');
        var searchEmptyEl = document.getElementById('apiCategorySearchEmpty');
        var searchInput = document.getElementById('apiCatSearchInput');
        var pageSizeEl = document.getElementById('apiCatPageSize');
        var footerEl = document.getElementById('apiCatFooter');
        var pagerNumsEl = document.getElementById('apiCatPagerNums');
        var statsEl = document.getElementById('apiCatStats');
        var prevBtn = document.getElementById('apiCatPrevBtn');
        var nextBtn = document.getElementById('apiCatNextBtn');
        var openAddBtn = document.getElementById('apiCatOpenAddBtn');
        var formOverlay = document.getElementById('apiCategoryFormOverlay');
        var formEl = document.getElementById('apiCategoryForm');
        var formId = document.getElementById('apiCatFormId');
        var formName = document.getElementById('apiCatFormName');
        var formDesc = document.getElementById('apiCatFormDesc');
        var formTitle = document.getElementById('apiCategoryFormTitle');
        var formSubmitBtn = document.getElementById('apiCatFormSubmitBtn');
        var iconPicker = document.getElementById('apiCatIconPicker');
        var iconUrlInput = document.getElementById('apiCatIconUrl');
        var iconCountHint = document.getElementById('apiCatIconCountHint');
        var transferOverlay = document.getElementById('apiCategoryTransferOverlay');
        var transferForm = document.getElementById('apiCategoryTransferForm');
        var transferId = document.getElementById('apiCatTransferId');
        var transferTarget = document.getElementById('apiCatTransferTarget');
        var transferOptions = document.getElementById('apiCatTransferOptions');
        var transferHint = document.getElementById('apiCatTransferHint');
        var transferSubmitBtn = document.getElementById('apiCatTransferSubmitBtn');

        var iconBase = (page.getAttribute('data-icon-base') || '').replace(/\/$/, '');
        var defaultIcons = [];
        var allCategories = [];
        var currentPage = 1;
        var formMode = 'create';
        var returnFocusEl = null;
        var transferReturnFocusEl = null;
        var transferRowId = '';
        var iconCtl = null;

        try {
            defaultIcons = JSON.parse(page.getAttribute('data-default-icons') || '[]');
        } catch (e1) {
            defaultIcons = [];
        }
        try {
            allCategories = JSON.parse(page.getAttribute('data-categories') || '[]');
        } catch (e2) {
            allCategories = [];
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

        if (iconCountHint && defaultIcons.length) {
            iconCountHint.textContent = '共 ' + defaultIcons.length + ' 个数字编号 SVG，点选即可';
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

        if (formOverlay && formOverlay.parentNode !== document.body) {
            document.body.appendChild(formOverlay);
        }
        if (transferOverlay && transferOverlay.parentNode !== document.body) {
            document.body.appendChild(transferOverlay);
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

        function postAction(action, fields) {
            var fd = new FormData();
            fd.append('action', action);
            if (fields) {
                Object.keys(fields).forEach(function (key) {
                    fd.append(key, fields[key]);
                });
            }
            return window.VS.postForm(fd);
        }

        function buildActionButtons(catId, enabled, apiCount) {
            var html = '<div class="action-btns">';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-api-cat-action" data-cat-action="edit" data-category-id="'
                + catId + '">编辑</button>';
            if (enabled) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-cat-action" data-cat-action="disable" data-category-id="'
                    + catId + '">禁用</button>';
            } else {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-cat-action" data-cat-action="enable" data-category-id="'
                    + catId + '">启用</button>';
            }
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-cat-action" data-cat-action="delete" data-category-id="'
                + catId + '" data-api-count="' + apiCount + '">删除</button>';
            html += '</div>';
            return html;
        }

        function searchHay(name, desc, id) {
            return String(name || '') + ' ' + String(desc || '') + ' #' + String(id || '');
        }

        function getRowPair(catId) {
            var id = String(catId);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-category-row="' + id + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.cat-card[data-category-row="' + id + '"]') : null
            };
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

        function allDesktopRows() {
            if (!listEl) {
                return [];
            }
            return Array.prototype.slice.call(listEl.querySelectorAll('tr[data-category-row]'));
        }

        function syncRowOrder(rows) {
            if (!listEl) {
                return;
            }
            rows.forEach(function (row) {
                listEl.appendChild(row);
                if (mobileEl) {
                    var id = row.getAttribute('data-category-row');
                    var card = mobileEl.querySelector('.cat-card[data-category-row="' + id + '"]');
                    if (card) {
                        mobileEl.appendChild(card);
                    }
                }
            });
        }

        function matchedRows() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var all = allDesktopRows();
            var filtered = all.filter(function (row) {
                if (!q) {
                    return true;
                }
                var hay = (row.getAttribute('data-search') || '').toLowerCase();
                return hay.indexOf(q) !== -1;
            });
            syncRowOrder(filtered);
            return filtered;
        }

        function renderPagerNums(totalPages) {
            if (!pagerNumsEl) {
                return;
            }
            if (totalPages <= 1) {
                pagerNumsEl.innerHTML = '';
                return;
            }
            var html = '';
            var i;
            for (i = 1; i <= totalPages; i += 1) {
                html += '<button type="button" class="vs-api-pager__num'
                    + (i === currentPage ? ' is-active' : '')
                    + '" data-page="' + i + '">' + i + '</button>';
            }
            pagerNumsEl.innerHTML = html;
        }

        function applyView() {
            var totalAll = allDesktopRows().length;
            var matched = matchedRows();
            var pageSize = getPageSize();
            var totalPages = Math.max(1, Math.ceil(matched.length / pageSize) || 1);
            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }
            var start = (currentPage - 1) * pageSize;
            var end = start + pageSize;
            var visibleIds = {};

            matched.forEach(function (row, idx) {
                var show = idx >= start && idx < end;
                var id = row.getAttribute('data-category-row');
                row.hidden = !show;
                if (show) {
                    visibleIds[id] = true;
                }
            });
            allDesktopRows().forEach(function (row) {
                if (matched.indexOf(row) === -1) {
                    row.hidden = true;
                }
            });
            if (mobileEl) {
                mobileEl.querySelectorAll('.cat-card[data-category-row]').forEach(function (card) {
                    var id = card.getAttribute('data-category-row');
                    var desk = listEl ? listEl.querySelector('tr[data-category-row="' + id + '"]') : null;
                    var inMatched = desk && matched.indexOf(desk) !== -1;
                    card.hidden = !(inMatched && visibleIds[id]);
                });
            }

            var hasAny = totalAll > 0;
            var hasVisible = matched.length > 0;
            var q = searchInput ? String(searchInput.value || '').trim() : '';
            if (emptyEl) {
                emptyEl.hidden = hasAny;
            }
            if (searchEmptyEl) {
                searchEmptyEl.hidden = !(hasAny && !hasVisible && q !== '');
            }
            if (tableWrapEl) {
                tableWrapEl.hidden = !hasVisible;
            }
            if (mobileEl) {
                mobileEl.hidden = !hasVisible;
            }
            if (footerEl) {
                footerEl.hidden = !hasAny;
            }
            if (statsEl) {
                statsEl.textContent = '共 ' + matched.length + ' 条'
                    + (hasAny && q ? ('（全部 ' + totalAll + '）') : '');
            }
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || matched.length === 0;
            }
            renderPagerNums(matched.length === 0 ? 0 : totalPages);
        }

        function buildDesktopHtml(row) {
            var catId = row.id;
            var enabled = parseInt(row.status, 10) === 1;
            var apiCount = parseInt(row.api_count, 10) || 0;
            var icon = safeIconUrl(row.icon);
            var desc = row.description || '';
            var name = row.name || '';
            var hay = searchHay(name, desc, catId).toLowerCase();
            var html = '<tr data-category-row="' + catId + '"'
                + ' data-category-status="' + (enabled ? '1' : '0') + '"'
                + ' data-search="' + escapeHtml(hay) + '">';
            html += '<td><div class="cat-name-cell"><div class="cat-icon"><img src="'
                + escapeHtml(icon) + '" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon"></div>'
                + '<span class="cat-name-text" data-field="name">' + escapeHtml(name) + '</span></div></td>';
            html += '<td><span class="cat-desc" data-field="description">'
                + (desc ? escapeHtml(desc) : '—') + '</span></td>';
            html += '<td><span class="cat-count" data-field="api_count">' + apiCount + '</span></td>';
            html += '<td><span class="vs-badge ' + (enabled ? 'vs-badge--success' : 'vs-badge--default')
                + '" data-field="status_label">' + (enabled ? '启用' : '禁用') + '</span></td>';
            html += '<td class="vs-api-cat-actions-cell" data-field="actions">'
                + buildActionButtons(catId, enabled, apiCount) + '</td>';
            html += '</tr>';
            return html;
        }

        function buildMobileHtml(row) {
            var catId = row.id;
            var enabled = parseInt(row.status, 10) === 1;
            var apiCount = parseInt(row.api_count, 10) || 0;
            var icon = safeIconUrl(row.icon);
            var desc = row.description || '';
            var name = row.name || '';
            var hay = searchHay(name, desc, catId).toLowerCase();
            var html = '<div class="cat-card" data-category-row="' + catId + '"'
                + ' data-category-status="' + (enabled ? '1' : '0') + '"'
                + ' data-search="' + escapeHtml(hay) + '">';
            html += '<div class="cat-card__header"><div class="cat-card__header-left">'
                + '<div class="cat-card__icon"><img src="' + escapeHtml(icon)
                + '" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer" data-field="icon"></div>'
                + '<div class="cat-card__title-wrap"><span class="cat-card__name" data-field="name">'
                + escapeHtml(name) + '</span><span class="vs-badge '
                + (enabled ? 'vs-badge--success' : 'vs-badge--default')
                + '" data-field="status_label">' + (enabled ? '启用' : '禁用') + '</span></div></div>'
                + '<span class="cat-card__count"><span data-field="api_count">' + apiCount + '</span> 个</span></div>';
            html += '<div class="cat-card__desc" data-field="description">'
                + (desc ? escapeHtml(desc) : '暂无描述') + '</div>';
            html += '<div class="cat-card__actions" data-field="actions">'
                + buildActionButtons(catId, enabled, apiCount) + '</div></div>';
            return html;
        }

        function appendItem(row) {
            if (!listEl || !mobileEl) {
                window.location.reload();
                return;
            }
            listEl.insertAdjacentHTML('afterbegin', buildDesktopHtml(row));
            mobileEl.insertAdjacentHTML('afterbegin', buildMobileHtml(row));
            currentPage = 1;
            applyView();
        }

        function updatePairFields(pair, row, apiCount) {
            [pair.desktop, pair.mobile].forEach(function (el) {
                if (!el) {
                    return;
                }
                var nameEl = el.querySelector('[data-field="name"]');
                if (nameEl) {
                    nameEl.textContent = row.name || '';
                }
                var descEl = el.querySelector('[data-field="description"]');
                if (descEl) {
                    var desc = row.description || '';
                    if (el.classList.contains('cat-card')) {
                        descEl.textContent = desc || '暂无描述';
                    } else {
                        descEl.textContent = desc || '—';
                    }
                }
                var img = el.querySelector('[data-field="icon"]');
                if (img && row.icon) {
                    img.src = safeIconUrl(row.icon);
                }
                el.setAttribute('data-search', searchHay(row.name, row.description, row.id).toLowerCase());
                if (typeof apiCount === 'number') {
                    var countEl = el.querySelector('[data-field="api_count"]');
                    if (countEl) {
                        countEl.textContent = String(apiCount);
                    }
                    el.querySelectorAll('[data-cat-action="delete"]').forEach(function (btn) {
                        btn.setAttribute('data-api-count', String(apiCount));
                    });
                }
            });
        }

        function setPairStatus(pair, enabled, label) {
            [pair.desktop, pair.mobile].forEach(function (el) {
                if (!el) {
                    return;
                }
                el.setAttribute('data-category-status', enabled ? '1' : '0');
                var badge = el.querySelector('[data-field="status_label"]');
                if (badge) {
                    badge.textContent = label;
                    badge.className = 'vs-badge ' + (enabled ? 'vs-badge--success' : 'vs-badge--default');
                }
                var catId = el.getAttribute('data-category-row');
                var delBtn = el.querySelector('[data-cat-action="delete"]');
                var apiCount = parseInt(delBtn ? delBtn.getAttribute('data-api-count') || '0' : '0', 10);
                var actions = el.querySelector('[data-field="actions"]');
                if (actions) {
                    actions.innerHTML = buildActionButtons(catId, enabled, apiCount);
                }
            });
        }

        function removePair(catId) {
            var pair = getRowPair(catId);
            if (pair.desktop) {
                pair.desktop.remove();
            }
            if (pair.mobile) {
                pair.mobile.remove();
            }
            applyView();
        }

        function syncCategoriesAfterDelete(deletedId) {
            allCategories = allCategories.filter(function (cat) {
                return parseInt(cat.id, 10) !== parseInt(deletedId, 10);
            });
            page.setAttribute('data-categories', JSON.stringify(allCategories));
        }

        function setTransferTarget(targetId) {
            var tid = String(targetId || '');
            if (transferTarget) {
                transferTarget.value = tid;
            }
            if (!transferOptions) {
                return;
            }
            transferOptions.querySelectorAll('.vs-cat-transfer-option').forEach(function (btn) {
                var selected = btn.getAttribute('data-target-id') === tid;
                btn.classList.toggle('is-selected', selected);
                btn.setAttribute('aria-checked', selected ? 'true' : 'false');
            });
        }

        function fillTransferTargetOptions(excludeId) {
            if (!transferOptions || !transferTarget) {
                return 0;
            }
            var exclude = parseInt(excludeId, 10);
            transferOptions.innerHTML = '';
            transferTarget.value = '';
            var count = 0;
            allCategories.forEach(function (cat) {
                if (parseInt(cat.id, 10) === exclude) {
                    return;
                }
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'vs-cat-transfer-option';
                btn.setAttribute('role', 'radio');
                btn.setAttribute('aria-checked', 'false');
                btn.setAttribute('data-target-id', String(cat.id));
                var apiN = parseInt(cat.api_count != null ? cat.api_count : 0, 10) || 0;
                btn.innerHTML = '<span class="vs-cat-transfer-option__name"></span>'
                    + '<span class="vs-cat-transfer-option__count"></span>';
                btn.querySelector('.vs-cat-transfer-option__name').textContent = cat.name || ('分类 #' + cat.id);
                btn.querySelector('.vs-cat-transfer-option__count').textContent = apiN > 0 ? (apiN + ' 个接口') : '';
                transferOptions.appendChild(btn);
                count += 1;
            });
            return count;
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

        function setIconPickerSelection(url) {
            var normalized = safeIconUrl(url);
            if (iconCtl) {
                iconCtl.setSelected(normalized || url || '');
                var matched = false;
                if (iconPicker) {
                    iconPicker.querySelectorAll('.vs-icon-picker__item, .vs-api-cat-icon-pick').forEach(function (btn) {
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
            if (iconUrlInput) {
                iconUrlInput.value = url || '';
            }
        }

        function fillFormFromPair(pair) {
            var el = pair.desktop || pair.mobile;
            if (!el) {
                return;
            }
            formId.value = el.getAttribute('data-category-row');
            formName.value = (el.querySelector('[data-field="name"]') || {}).textContent || '';
            var descEl = el.querySelector('[data-field="description"]');
            var descText = descEl ? descEl.textContent : '';
            if (descText === '—' || descText === '暂无描述') {
                descText = '';
            }
            formDesc.value = descText.trim();
            var img = el.querySelector('[data-field="icon"]');
            setIconPickerSelection(img ? img.getAttribute('src') : '');
        }

        function resetForm() {
            formMode = 'create';
            if (formId) {
                formId.value = '';
            }
            if (formName) {
                formName.value = '';
            }
            if (formDesc) {
                formDesc.value = '';
            }
            if (formTitle) {
                formTitle.textContent = '添加分类';
            }
            setIconPickerSelection(defaultIcons.length ? defaultIcons[0] : '');
        }

        function openFormOverlay(mode, pair) {
            if (!formOverlay) {
                return;
            }
            returnFocusEl = document.activeElement;
            formMode = mode === 'edit' ? 'edit' : 'create';
            if (formMode === 'edit' && pair) {
                fillFormFromPair(pair);
                if (formTitle) {
                    formTitle.textContent = '编辑分类';
                }
            } else {
                resetForm();
            }
            formOverlay.hidden = false;
            formOverlay.setAttribute('aria-hidden', 'false');
            formOverlay.classList.add('is-open');
            document.body.classList.add('is-overlay-open');
            if (formName) {
                formName.focus();
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

        function openTransferOverlay(catId, apiCount, catName) {
            if (!transferOverlay) {
                return;
            }
            var others = fillTransferTargetOptions(catId);
            if (others === 0) {
                window.VS.showMessage('没有其他分类可转移接口，请先创建目标分类', 'error');
                return;
            }
            transferReturnFocusEl = document.activeElement;
            transferRowId = String(catId);
            if (transferId) {
                transferId.value = String(catId);
            }
            if (transferHint) {
                transferHint.textContent = '分类「' + (catName || '') + '」下仍有 ' + apiCount + ' 个接口，删除前需转移至其他分类。';
            }
            transferOverlay.hidden = false;
            transferOverlay.setAttribute('aria-hidden', 'false');
            transferOverlay.classList.add('is-open');
            document.body.classList.add('is-overlay-open');
        }

        function closeTransferOverlay() {
            if (!transferOverlay) {
                return;
            }
            transferOverlay.hidden = true;
            transferOverlay.setAttribute('aria-hidden', 'true');
            transferOverlay.classList.remove('is-open');
            document.body.classList.remove('is-overlay-open');
            transferRowId = '';
            if (transferReturnFocusEl && transferReturnFocusEl.focus) {
                transferReturnFocusEl.focus();
            }
            transferReturnFocusEl = null;
        }

        function handleFormSubmit() {
            var name = formName ? formName.value.trim() : '';
            if (!name) {
                window.VS.showMessage('请填写分类名称', 'error');
                return;
            }
            var payload = {
                name: name,
                icon: getSelectedIconUrl(),
                description: formDesc ? formDesc.value.trim() : ''
            };
            var action = formMode === 'edit' ? 'update' : 'create';
            if (formMode === 'edit') {
                payload.category_id = formId.value;
            }
            if (formSubmitBtn) {
                formSubmitBtn.disabled = true;
            }
            postAction(action, payload).then(function (data) {
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '操作成功', 'success');
                closeFormOverlay();
                var cat = data.category || {};
                if (formMode === 'edit') {
                    updatePairFields(getRowPair(cat.id), cat, data.api_count);
                    allCategories = allCategories.map(function (c) {
                        if (parseInt(c.id, 10) === parseInt(cat.id, 10)) {
                            return {
                                id: cat.id,
                                name: cat.name || '',
                                api_count: typeof data.api_count === 'number' ? data.api_count : c.api_count
                            };
                        }
                        return c;
                    });
                    page.setAttribute('data-categories', JSON.stringify(allCategories));
                    applyView();
                } else {
                    appendItem(Object.assign({ api_count: data.api_count || 0, status: 1 }, cat));
                    allCategories.unshift({
                        id: cat.id,
                        name: cat.name || '',
                        api_count: data.api_count || 0
                    });
                    page.setAttribute('data-categories', JSON.stringify(allCategories));
                }
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            }).finally(function () {
                if (formSubmitBtn) {
                    formSubmitBtn.disabled = false;
                }
            });
        }

        if (formOverlay) {
            formOverlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
                el.addEventListener('click', closeFormOverlay);
            });
        }
        if (transferOverlay) {
            transferOverlay.querySelectorAll('[data-transfer-overlay-close]').forEach(function (el) {
                el.addEventListener('click', closeTransferOverlay);
            });
        }
        if (transferOptions) {
            transferOptions.addEventListener('click', function (e) {
                var btn = e.target.closest('.vs-cat-transfer-option');
                if (!btn || !transferOptions.contains(btn)) {
                    return;
                }
                setTransferTarget(btn.getAttribute('data-target-id') || '');
            });
        }
        if (transferForm) {
            transferForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var catId = transferId ? transferId.value : '';
                var targetId = transferTarget ? transferTarget.value : '';
                if (!targetId) {
                    window.VS.showMessage('请选择目标分类', 'error');
                    return;
                }
                if (transferSubmitBtn) {
                    transferSubmitBtn.disabled = true;
                }
                postAction('delete_move', {
                    category_id: catId,
                    target_id: targetId
                }).then(function (data) {
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '删除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '分类已删除', 'success');
                    syncCategoriesAfterDelete(catId);
                    removePair(catId);
                    closeTransferOverlay();
                }).catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                }).finally(function () {
                    if (transferSubmitBtn) {
                        transferSubmitBtn.disabled = false;
                    }
                });
            });
        }
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
            searchInput.addEventListener('input', function () {
                currentPage = 1;
                applyView();
            });
        }
        if (pageSizeEl) {
            if (!pageSizeEl.value) {
                pageSizeEl.value = String(defaultPageSize());
            } else if (window.matchMedia('(max-width: 900px)').matches && pageSizeEl.value === '20') {
                pageSizeEl.value = '10';
            }
            pageSizeEl.addEventListener('change', function () {
                currentPage = 1;
                applyView();
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (currentPage > 1) {
                    currentPage -= 1;
                    applyView();
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                currentPage += 1;
                applyView();
            });
        }
        if (iconUrlInput) {
            iconUrlInput.addEventListener('input', function () {
                if (iconCtl) {
                    iconCtl.setSelected('');
                }
            });
        }

        document.addEventListener('click', function (e) {
            var pageBtn = e.target.closest('.vs-api-pager__num[data-page]');
            if (pageBtn && pagerNumsEl && pagerNumsEl.contains(pageBtn)) {
                currentPage = parseInt(pageBtn.getAttribute('data-page'), 10) || 1;
                applyView();
                return;
            }

            var btn = e.target.closest('.vs-api-cat-action');
            if (!btn || !page.contains(btn)) {
                return;
            }
            var action = btn.getAttribute('data-cat-action') || '';
            var catId = btn.getAttribute('data-category-id') || '';
            var pair = getRowPair(catId);
            var rowEl = pair.desktop || pair.mobile;
            if (!rowEl) {
                return;
            }

            if (action === 'edit') {
                openFormOverlay('edit', pair);
                return;
            }
            if (action === 'enable' || action === 'disable') {
                var nextStatus = action === 'enable' ? 1 : 0;
                postAction('toggle_status', {
                    category_id: catId,
                    status: nextStatus
                }).then(function (data) {
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已更新', 'success');
                    setPairStatus(pair, nextStatus === 1, data.status_label || (nextStatus === 1 ? '启用' : '禁用'));
                }).catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                });
                return;
            }
            if (action === 'delete') {
                var apiCount = parseInt(btn.getAttribute('data-api-count') || '0', 10);
                var catName = (rowEl.querySelector('[data-field="name"]') || {}).textContent || '';
                if (apiCount > 0) {
                    openTransferOverlay(catId, apiCount, catName);
                    return;
                }
                if (!window.confirm('确定删除分类「' + catName + '」？')) {
                    return;
                }
                postAction('delete', { category_id: catId }).then(function (data) {
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '删除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '分类已删除', 'success');
                    syncCategoriesAfterDelete(catId);
                    removePair(catId);
                }).catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                });
            }
        });

        applyView();
    }

    boot();
})();
