/**
 * 文件：assets/js/api-categories.js
 * 作用：后台接口分类管理
 */
(function () {
    'use strict';

    var page = document.getElementById('apiCategoriesPage');
    if (!page) {
        return;
    }

    var listEl = document.getElementById('apiCategoryList');
    var emptyEl = document.getElementById('apiCategoryEmpty');
    var emptySearch = document.getElementById('apiCategoryEmptySearch');
    var searchInput = document.getElementById('apiCatSearchInput');
    var openAddBtn = document.getElementById('apiCatOpenAddBtn');
    var formModal = document.getElementById('apiCategoryFormModal');
    var formEl = document.getElementById('apiCategoryForm');
    var formId = document.getElementById('apiCatFormId');
    var formName = document.getElementById('apiCatFormName');
    var formDesc = document.getElementById('apiCatFormDesc');
    var formTitle = document.getElementById('apiCategoryFormTitle');
    var formSubmitBtn = document.getElementById('apiCatFormSubmitBtn');
    var iconPicker = document.getElementById('apiCatIconPicker');
    var iconUrlInput = document.getElementById('apiCatIconUrl');

    var defaultIcons = [];
    try {
        defaultIcons = JSON.parse(page.getAttribute('data-default-icons') || '[]');
    } catch (e) {
        defaultIcons = [];
    }

    var formMode = 'create';

    function postAction(action, fields) {
        var fd = new FormData();
        fd.append('action', action);
        if (fields) {
            Object.keys(fields).forEach(function (key) {
                fd.append(key, fields[key]);
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

    function buildActionButtons(catId, enabled, apiCount) {
        var html = '';
        html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action" data-cat-action="edit" data-category-id="' + catId + '">编辑</button>';
        if (enabled) {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-api-cat-action" data-cat-action="disable" data-category-id="' + catId + '">禁用</button>';
        } else {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action" data-cat-action="enable" data-category-id="' + catId + '">启用</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger vs-api-cat-action" data-cat-action="delete" data-category-id="' + catId + '" data-api-count="' + apiCount + '">删除</button>';
        return html;
    }

    function getFilterText() {
        return searchInput ? searchInput.value.trim().toLowerCase() : '';
    }

    function categoryMatches(rowEl, query) {
        if (!query) {
            return true;
        }
        var name = (rowEl.getAttribute('data-cat-name') || '').toLowerCase();
        var desc = (rowEl.getAttribute('data-cat-desc') || '').toLowerCase();
        return name.indexOf(query) !== -1 || desc.indexOf(query) !== -1;
    }

    function applySearchFilter() {
        var q = getFilterText();
        var items = listEl ? listEl.querySelectorAll('.vs-api-cat-item') : [];
        var visible = 0;
        items.forEach(function (el) {
            var show = categoryMatches(el, q);
            el.hidden = !show;
            if (show) {
                visible++;
            }
        });
        if (emptySearch) {
            emptySearch.hidden = !(listEl && listEl.children.length > 0 && q && visible === 0);
        }
        if (emptyEl && listEl) {
            emptyEl.hidden = listEl.children.length > 0 || q !== '';
        }
    }

    function ensureListVisible() {
        if (listEl && listEl.hidden) {
            listEl.hidden = false;
        }
        if (emptyEl) {
            emptyEl.hidden = true;
        }
    }

    function buildItemHtml(row) {
        var catId = row.id;
        var enabled = parseInt(row.status, 10) === 1;
        var apiCount = parseInt(row.api_count, 10) || 0;
        var icon = safeIconUrl(row.icon);
        var desc = row.description || '';
        var name = row.name || '';

        var html = '<article class="vs-api-cat-item" data-category-row="' + catId + '"'
            + ' data-category-status="' + (enabled ? '1' : '0') + '"'
            + ' data-cat-name="' + escapeHtml(name) + '"'
            + ' data-cat-desc="' + escapeHtml(desc) + '">';
        html += '<div class="vs-api-cat-item__icon"><img src="' + escapeHtml(icon) + '" alt="" width="48" height="48" loading="lazy"></div>';
        html += '<div class="vs-api-cat-item__main">';
        html += '<div class="vs-api-cat-item__name" data-field="name">' + escapeHtml(name) + '</div>';
        html += '<div class="vs-api-cat-item__desc" data-field="description">';
        html += desc ? escapeHtml(desc) : '<span class="vs-api-cat-item__desc-empty">暂无描述</span>';
        html += '</div>';
        html += '<div class="vs-api-cat-item__meta"><span>关联接口 ' + apiCount + '</span>';
        html += '<span class="vs-api-cat-status' + (enabled ? ' is-on' : ' is-off') + '" data-field="status_label">' + (enabled ? '启用' : '禁用') + '</span></div>';
        html += '</div>';
        html += '<div class="vs-api-cat-item__actions">' + buildActionButtons(catId, enabled, apiCount) + '</div>';
        html += '</article>';
        return html;
    }

    function appendItem(row) {
        ensureListVisible();
        if (!listEl) {
            window.location.reload();
            return;
        }
        listEl.insertAdjacentHTML('afterbegin', buildItemHtml(Object.assign({ api_count: row.api_count || 0 }, row)));
        applySearchFilter();
    }

    function updateItem(rowEl, row, apiCount) {
        rowEl.setAttribute('data-cat-name', row.name || '');
        rowEl.setAttribute('data-cat-desc', row.description || '');
        rowEl.querySelector('[data-field="name"]').textContent = row.name || '';
        var descEl = rowEl.querySelector('[data-field="description"]');
        var desc = row.description || '';
        descEl.innerHTML = desc ? escapeHtml(desc) : '<span class="vs-api-cat-item__desc-empty">暂无描述</span>';
        var iconImg = rowEl.querySelector('.vs-api-cat-item__icon img');
        if (iconImg && row.icon) {
            iconImg.src = safeIconUrl(row.icon);
        }
        if (typeof apiCount === 'number') {
            rowEl.querySelector('.vs-api-cat-item__meta span').textContent = '关联接口 ' + apiCount;
            var delBtn = rowEl.querySelector('[data-cat-action="delete"]');
            if (delBtn) {
                delBtn.setAttribute('data-api-count', String(apiCount));
            }
        }
        applySearchFilter();
    }

    function setItemStatus(rowEl, enabled, label) {
        rowEl.setAttribute('data-category-status', enabled ? '1' : '0');
        var badge = rowEl.querySelector('[data-field="status_label"]');
        badge.textContent = label;
        badge.classList.toggle('is-on', enabled);
        badge.classList.toggle('is-off', !enabled);
        var catId = rowEl.getAttribute('data-category-row');
        var delBtn = rowEl.querySelector('[data-cat-action="delete"]');
        var apiCount = parseInt(delBtn ? delBtn.getAttribute('data-api-count') || '0' : '0', 10);
        rowEl.querySelector('.vs-api-cat-item__actions').innerHTML = buildActionButtons(catId, enabled, apiCount);
    }

    function getSelectedIconUrl() {
        if (iconUrlInput && iconUrlInput.value.trim()) {
            return iconUrlInput.value.trim();
        }
        if (!iconPicker) {
            return '';
        }
        var sel = iconPicker.querySelector('.vs-api-cat-icon-pick.is-selected');
        return sel ? (sel.getAttribute('data-icon-url') || '') : '';
    }

    function setIconPickerSelection(url) {
        if (!iconPicker) {
            return;
        }
        var normalized = safeIconUrl(url);
        var matched = false;
        iconPicker.querySelectorAll('.vs-api-cat-icon-pick').forEach(function (btn) {
            var btnUrl = btn.getAttribute('data-icon-url') || '';
            var isSel = btnUrl === normalized || btnUrl === url;
            btn.classList.toggle('is-selected', isSel);
            if (isSel) {
                matched = true;
            }
        });
        if (iconUrlInput) {
            iconUrlInput.value = matched ? '' : (url || '');
        }
    }

    function fillFormFromRow(rowEl) {
        if (!rowEl) {
            return;
        }
        formId.value = rowEl.getAttribute('data-category-row');
        formName.value = (rowEl.querySelector('[data-field="name"]') || {}).textContent || '';
        var descEl = rowEl.querySelector('[data-field="description"]');
        var descText = descEl ? descEl.textContent : '';
        if (descText === '暂无描述') {
            descText = '';
        }
        formDesc.value = descText.trim();
        var img = rowEl.querySelector('.vs-api-cat-item__icon img');
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
        if (defaultIcons.length) {
            setIconPickerSelection(defaultIcons[0]);
        } else {
            setIconPickerSelection('');
        }
    }

    function openFormModal(mode, rowEl) {
        if (!formModal) {
            return;
        }
        formMode = mode === 'edit' ? 'edit' : 'create';
        if (formMode === 'edit' && rowEl) {
            fillFormFromRow(rowEl);
            if (formTitle) {
                formTitle.textContent = '编辑分类';
            }
        } else {
            resetForm();
        }
        formModal.hidden = false;
        formModal.classList.add('is-open');
        formModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('vs-modal-open');
        if (formName) {
            formName.focus();
        }
    }

    function closeFormModal() {
        if (!formModal) {
            return;
        }
        formModal.hidden = true;
        formModal.classList.remove('is-open');
        formModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('vs-modal-open');
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
        postAction(action, payload)
            .then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '操作成功', 'success');
                closeFormModal();
                var cat = data.category || {};
                if (formMode === 'edit') {
                    var rowEl = page.querySelector('[data-category-row="' + cat.id + '"]');
                    if (rowEl) {
                        updateItem(rowEl, cat, data.api_count);
                    }
                } else {
                    appendItem(Object.assign({ api_count: data.api_count || 0 }, cat));
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

    if (iconPicker) {
        defaultIcons.forEach(function (url) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-api-cat-icon-pick';
            btn.setAttribute('data-icon-url', url);
            btn.innerHTML = '<img src="' + escapeHtml(url) + '" alt="" width="40" height="40">';
            btn.addEventListener('click', function () {
                setIconPickerSelection(url);
            });
            iconPicker.appendChild(btn);
        });
    }

    if (iconUrlInput) {
        iconUrlInput.addEventListener('input', function () {
            if (!iconPicker) {
                return;
            }
            iconPicker.querySelectorAll('.vs-api-cat-icon-pick').forEach(function (b) {
                b.classList.remove('is-selected');
            });
        });
    }

    if (formModal) {
        formModal.querySelectorAll('[data-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeFormModal);
        });
        formModal.addEventListener('click', function (e) {
            if (e.target === formModal) {
                closeFormModal();
            }
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
            openFormModal('create');
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applySearchFilter);
    }

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('.vs-api-cat-action');
        if (!btn) {
            return;
        }
        var action = btn.getAttribute('data-cat-action');
        var catId = btn.getAttribute('data-category-id');
        var row = page.querySelector('[data-category-row="' + catId + '"]');

        if (action === 'edit') {
            openFormModal('edit', row);
            return;
        }

        if (action === 'enable' || action === 'disable') {
            var nextStatus = action === 'enable' ? 1 : 0;
            postAction('toggle_status', {
                category_id: catId,
                status: String(nextStatus)
            }).then(function (data) {
                if (data.code !== 1 || !row) {
                    window.VS.showMessage(data.msg || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '操作成功', 'success');
                setItemStatus(row, nextStatus === 1, data.status_label);
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        if (action === 'delete') {
            var apiCount = parseInt(btn.getAttribute('data-api-count') || '0', 10);
            if (apiCount > 0) {
                window.VS.showMessage('该分类下仍有 ' + apiCount + ' 个接口，无法删除', 'error');
                return;
            }
            if (!window.confirm('确定删除该分类？')) {
                return;
            }
            postAction('delete', { category_id: catId }).then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '删除失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '分类已删除', 'success');
                if (row) {
                    row.remove();
                }
                if (listEl && !listEl.querySelector('.vs-api-cat-item')) {
                    listEl.hidden = true;
                    if (emptyEl) {
                        emptyEl.hidden = false;
                    }
                }
                applySearchFilter();
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        }
    });

    applySearchFilter();
})();
