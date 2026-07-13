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

    var addForm = document.getElementById('apiCategoryAddForm');
    var tableBody = document.getElementById('apiCategoryTableBody');
    var editModal = document.getElementById('apiCategoryEditModal');
    var editForm = document.getElementById('apiCategoryEditForm');
    var editId = document.getElementById('apiCatEditId');
    var editName = document.getElementById('apiCatEditName');
    var editSort = document.getElementById('apiCatEditSort');

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

    function openEditModal(row) {
        if (!editModal || !row) {
            return;
        }
        editId.value = row.getAttribute('data-category-row');
        editName.value = row.querySelector('[data-field="name"]').textContent.trim();
        editSort.value = row.querySelector('[data-field="sort_order"]').textContent.trim();
        editModal.hidden = false;
        document.body.classList.add('vs-modal-open');
        editName.focus();
    }

    function closeEditModal() {
        if (!editModal) {
            return;
        }
        editModal.hidden = true;
        document.body.classList.remove('vs-modal-open');
    }

    function buildActionButtons(catId, enabled, apiCount) {
        var html = '<div class="vs-api-cat-actions">';
        html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action" data-cat-action="edit" data-category-id="' + catId + '">编辑</button>';
        if (enabled) {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-api-cat-action" data-cat-action="disable" data-category-id="' + catId + '">禁用</button>';
        } else {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action" data-cat-action="enable" data-category-id="' + catId + '">启用</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger vs-api-cat-action" data-cat-action="delete" data-category-id="' + catId + '" data-api-count="' + apiCount + '">删除</button>';
        html += '</div>';
        return html;
    }

    function appendRow(data, apiCount) {
        if (!tableBody) {
            window.location.reload();
            return;
        }
        var tr = document.createElement('tr');
        tr.setAttribute('data-category-row', String(data.id));
        tr.setAttribute('data-category-status', '1');
        tr.innerHTML =
            '<td>' + data.id + '</td>' +
            '<td><span class="vs-api-cat-name" data-field="name">' + escapeHtml(data.name) + '</span></td>' +
            '<td><span class="vs-api-cat-sort" data-field="sort_order">' + data.sort_order + '</span></td>' +
            '<td>' + (apiCount || 0) + '</td>' +
            '<td class="vs-api-cat-status-cell"><span class="vs-api-cat-status is-on" data-field="status_label">启用</span></td>' +
            '<td>' + buildActionButtons(data.id, true, apiCount || 0) + '</td>';
        tableBody.appendChild(tr);
    }

    function updateRow(row, data, apiCount) {
        row.querySelector('[data-field="name"]').textContent = data.name;
        row.querySelector('[data-field="sort_order"]').textContent = String(data.sort_order);
        if (typeof apiCount === 'number') {
            row.cells[3].textContent = String(apiCount);
        }
    }

    function setRowStatus(row, enabled, label) {
        row.setAttribute('data-category-status', enabled ? '1' : '0');
        var badge = row.querySelector('[data-field="status_label"]');
        badge.textContent = label;
        badge.classList.toggle('is-on', enabled);
        badge.classList.toggle('is-off', !enabled);
        var catId = row.getAttribute('data-category-row');
        var apiCount = parseInt(row.querySelector('[data-cat-action="delete"]').getAttribute('data-api-count') || '0', 10);
        row.cells[5].innerHTML = buildActionButtons(catId, enabled, apiCount);
    }

    if (editModal) {
        editModal.querySelectorAll('[data-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeEditModal);
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var nameInput = document.getElementById('apiCatAddName');
            var sortInput = document.getElementById('apiCatAddSort');
            postAction('create', {
                name: nameInput.value.trim(),
                sort_order: sortInput.value || '0'
            }).then(function (res) {
                if (!res || !res.ok) {
                    return;
                }
                if (tableBody) {
                    appendRow(res.data.category, res.data.api_count);
                } else {
                    window.location.reload();
                }
                nameInput.value = '';
                sortInput.value = '0';
            });
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var id = editId.value;
            postAction('update', {
                category_id: id,
                name: editName.value.trim(),
                sort_order: editSort.value || '0'
            }).then(function (res) {
                if (!res || !res.ok) {
                    return;
                }
                var row = page.querySelector('[data-category-row="' + id + '"]');
                if (row) {
                    updateRow(row, res.data.category, res.data.api_count);
                }
                closeEditModal();
            });
        });
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
            openEditModal(row);
            return;
        }

        if (action === 'enable' || action === 'disable') {
            var nextStatus = action === 'enable' ? 1 : 0;
            postAction('toggle_status', {
                category_id: catId,
                status: String(nextStatus)
            }).then(function (res) {
                if (!res || !res.ok || !row) {
                    return;
                }
                setRowStatus(row, nextStatus === 1, res.data.status_label);
            });
            return;
        }

        if (action === 'delete') {
            var apiCount = parseInt(btn.getAttribute('data-api-count') || '0', 10);
            if (apiCount > 0) {
                window.VS.toast('该分类下仍有 ' + apiCount + ' 个接口，无法删除', 'error');
                return;
            }
            if (!window.confirm('确定删除该分类？')) {
                return;
            }
            postAction('delete', { category_id: catId }).then(function (res) {
                if (!res || !res.ok) {
                    return;
                }
                if (row) {
                    row.remove();
                }
                if (tableBody && !tableBody.querySelector('[data-category-row]')) {
                    window.location.reload();
                }
            });
        }
    });
})();
