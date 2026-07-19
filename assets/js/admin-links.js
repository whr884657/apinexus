/**
 * 后台 · 友情链接管理（AJAX + 局部 DOM，禁止整页刷新）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminLinksPage');
    if (!page) {
        return;
    }

    var overlay = document.getElementById('linkFormOverlay');
    var form = document.getElementById('linkForm');
    var list = document.getElementById('linkList');
    var empty = document.getElementById('linkEmpty');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syncEmpty() {
        var has = list && list.querySelector('[data-link-row]');
        if (list) list.hidden = !has;
        if (empty) empty.hidden = !!has;
    }

    function statusDisplay(link) {
        var status = parseInt(link.status, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        if (status === 1 && enabled === 0) {
            return { label: '已禁用', cls: 'is-off' };
        }
        if (status === 1) {
            return { label: '已通过', cls: 'is-on' };
        }
        if (status === 2) {
            return { label: '已拒绝', cls: 'is-off' };
        }
        return { label: '待审核', cls: 'is-pending' };
    }

    function actionsHtml(link) {
        var id = parseInt(link.id, 10) || 0;
        var status = parseInt(link.status, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        var html = '<button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-link-action="edit" data-link-id="' + id + '">编辑</button>';
        if (status !== 1) {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary" data-link-action="approve" data-link-id="' + id + '">通过</button>';
        }
        if (status !== 2) {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-link-action="reject" data-link-id="' + id + '">拒绝</button>';
        }
        if (status === 1) {
            if (enabled === 1) {
                html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-link-action="disable" data-link-id="' + id + '">禁用</button>';
            } else {
                html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary" data-link-action="enable" data-link-id="' + id + '">启用</button>';
            }
        }
        html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger" data-link-action="delete" data-link-id="' + id + '">删除</button>';
        return html;
    }

    function iconHtml(link) {
        var url = link.icon_url || '';
        var name = link.name || '';
        if (url) {
            return '<img src="' + esc(url) + '" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer">';
        }
        var initial = name ? name.charAt(0) : '?';
        return '<span class="vs-link-row__initial">' + esc(initial) + '</span>';
    }

    function rowHtml(link) {
        var id = parseInt(link.id, 10) || 0;
        var status = parseInt(link.status, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        var name = link.name || '';
        var siteurl = link.siteurl || '';
        var desc = link.description || '';
        var icon = link.icon || '';
        var contact = link.contact || '';
        var sort = link.sort != null ? link.sort : 0;
        var st = statusDisplay(link);
        var descBlock = desc
            ? '<div class="vs-link-row__desc" data-field="description">' + esc(desc) + '</div>'
            : '';
        return (
            '<div class="vs-link-row"' +
            ' data-link-row="' + id + '"' +
            ' data-link-status="' + status + '"' +
            ' data-link-enabled="' + enabled + '"' +
            ' data-name="' + esc(name) + '"' +
            ' data-siteurl="' + esc(siteurl) + '"' +
            ' data-icon="' + esc(icon) + '"' +
            ' data-description="' + esc(desc) + '"' +
            ' data-contact="' + esc(contact) + '"' +
            ' data-sort="' + esc(sort) + '"' +
            ' data-enabled="' + enabled + '">' +
            '<div class="vs-link-row__icon">' + iconHtml(link) + '</div>' +
            '<div class="vs-link-row__main">' +
            '<div class="vs-link-row__name" data-field="name">' + esc(name) + '</div>' +
            '<a class="vs-link-row__url" data-field="siteurl" href="' + esc(siteurl) + '" target="_blank" rel="noopener noreferrer">' + esc(siteurl) + '</a>' +
            descBlock +
            '</div>' +
            '<div class="vs-link-row__status">' +
            '<span class="vs-link-status ' + st.cls + '" data-field="status_label">' + esc(st.label) + '</span>' +
            '</div>' +
            '<div class="vs-link-row__actions">' + actionsHtml(link) + '</div>' +
            '</div>'
        );
    }

    function upsertRow(link) {
        if (!link || !list) return;
        var id = parseInt(link.id, 10) || 0;
        var html = rowHtml(link);
        var existing = list.querySelector('[data-link-row="' + id + '"]');
        if (existing) {
            existing.outerHTML = html;
        } else {
            list.insertAdjacentHTML('afterbegin', html);
        }
        syncEmpty();
    }

    function openOverlay() {
        if (!overlay) return;
        overlay.hidden = false;
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('is-overlay-open');
        if (window.VSPick) {
            window.VSPick.init(overlay);
        }
    }

    function closeOverlay() {
        if (!overlay) return;
        overlay.hidden = true;
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('is-overlay-open');
    }

    function fillForm(data) {
        document.getElementById('linkFormAction').value = data.action || 'create';
        document.getElementById('linkFormId').value = data.id || 0;
        document.getElementById('linkFormTitle').textContent = data.title || '添加友链';
        document.getElementById('linkName').value = data.name || '';
        document.getElementById('linkUrl').value = data.siteurl || '';
        document.getElementById('linkIcon').value = data.icon || '';
        document.getElementById('linkDesc').value = data.description || '';
        document.getElementById('linkContact').value = data.contact || '';
        document.getElementById('linkSort').value = data.sort != null ? data.sort : 0;
        var statusEl = document.getElementById('linkStatus');
        var enabledEl = document.getElementById('linkEnabled');
        statusEl.value = data.status != null ? String(data.status) : '1';
        enabledEl.value = data.enabled != null ? String(data.enabled) : '1';
        if (window.VSPick) {
            window.VSPick.refresh(statusEl);
            window.VSPick.refresh(enabledEl);
        }
    }

    function postAction(fd) {
        return VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || !data.code) {
                throw new Error((data && data.msg) || '操作失败');
            }
            return data;
        });
    }

    var addBtn = document.getElementById('linkOpenAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            fillForm({ action: 'create', title: '添加友链', status: 1, enabled: 1, sort: 0 });
            openOverlay();
        });
    }

    if (overlay) {
        overlay.querySelectorAll('[data-overlay-close="1"]').forEach(function (el) {
            el.addEventListener('click', closeOverlay);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeOverlay();
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = document.getElementById('linkFormSubmit');
            if (submitBtn) submitBtn.disabled = true;
            var fd = new FormData(form);
            postAction(fd).then(function (data) {
                VS.showMessage(data.msg || '已保存', 'success');
                closeOverlay();
                if (data.link) {
                    upsertRow(data.link);
                }
            }).catch(function (err) {
                VS.showMessage(err.message || '保存失败', 'error');
            }).then(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    }

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-link-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-link-action');
        var id = parseInt(btn.getAttribute('data-link-id') || '0', 10);
        var row = btn.closest('[data-link-row]');

        if (action === 'edit' && row) {
            fillForm({
                action: 'update',
                title: '编辑友链',
                id: id,
                name: row.getAttribute('data-name') || '',
                siteurl: row.getAttribute('data-siteurl') || '',
                icon: row.getAttribute('data-icon') || '',
                description: row.getAttribute('data-description') || '',
                contact: row.getAttribute('data-contact') || '',
                sort: row.getAttribute('data-sort') || 0,
                status: row.getAttribute('data-link-status') || 1,
                enabled: row.getAttribute('data-link-enabled') || 1
            });
            openOverlay();
            return;
        }

        if (action === 'approve' || action === 'reject') {
            var fd = new FormData();
            fd.append('action', 'set_status');
            fd.append('link_id', String(id));
            fd.append('status', action === 'approve' ? '1' : '2');
            postAction(fd).then(function (data) {
                VS.showMessage(data.msg || '已更新', 'success');
                if (!row) return;
                var enabled = parseInt(row.getAttribute('data-link-enabled') || '1', 10);
                upsertRow({
                    id: id,
                    name: row.getAttribute('data-name') || '',
                    siteurl: row.getAttribute('data-siteurl') || '',
                    icon: row.getAttribute('data-icon') || '',
                    icon_url: (row.querySelector('.vs-link-row__icon img') || {}).src || '',
                    description: row.getAttribute('data-description') || '',
                    contact: row.getAttribute('data-contact') || '',
                    sort: row.getAttribute('data-sort') || 0,
                    status: data.status != null ? data.status : (action === 'approve' ? 1 : 2),
                    enabled: enabled
                });
            }).catch(function (err) {
                VS.showMessage(err.message || '操作失败', 'error');
            });
            return;
        }

        if (action === 'enable' || action === 'disable') {
            var en = new FormData();
            en.append('action', 'set_enabled');
            en.append('link_id', String(id));
            en.append('enabled', action === 'enable' ? '1' : '0');
            postAction(en).then(function (data) {
                VS.showMessage(data.msg || '已更新', 'success');
                if (!row) return;
                upsertRow({
                    id: id,
                    name: row.getAttribute('data-name') || '',
                    siteurl: row.getAttribute('data-siteurl') || '',
                    icon: row.getAttribute('data-icon') || '',
                    icon_url: (row.querySelector('.vs-link-row__icon img') || {}).src || '',
                    description: row.getAttribute('data-description') || '',
                    contact: row.getAttribute('data-contact') || '',
                    sort: row.getAttribute('data-sort') || 0,
                    status: parseInt(row.getAttribute('data-link-status') || '1', 10),
                    enabled: data.enabled != null ? data.enabled : (action === 'enable' ? 1 : 0)
                });
            }).catch(function (err) {
                VS.showMessage(err.message || '操作失败', 'error');
            });
            return;
        }

        if (action === 'delete') {
            var ask = (window.VsModal && window.VsModal.confirm)
                ? window.VsModal.confirm('删除后不可恢复，确定删除该友链？', '删除友链')
                : Promise.resolve(window.confirm('确定删除该友链？'));
            ask.then(function (ok) {
                if (!ok) return;
                var del = new FormData();
                del.append('action', 'delete');
                del.append('link_id', String(id));
                postAction(del).then(function (data) {
                    VS.showMessage(data.msg || '已删除', 'success');
                    if (row) row.remove();
                    syncEmpty();
                }).catch(function (err) {
                    VS.showMessage(err.message || '删除失败', 'error');
                });
            });
        }
    });
})();
