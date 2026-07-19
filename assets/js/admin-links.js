/**
 * 后台 · 友情链接管理
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

    function openOverlay() {
        if (!overlay) return;
        overlay.hidden = false;
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeOverlay() {
        if (!overlay) return;
        overlay.hidden = true;
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
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
        document.getElementById('linkStatus').value = data.status != null ? String(data.status) : '1';
    }

    function postAction(fd) {
        return VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || !data.code) {
                throw new Error((data && data.msg) || '操作失败');
            }
            return data;
        });
    }

    function reloadPage() {
        window.location.reload();
    }

    var addBtn = document.getElementById('linkOpenAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            fillForm({ action: 'create', title: '添加友链', status: 1, sort: 0 });
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
            var fd = new FormData(form);
            postAction(fd).then(function (data) {
                VS.showMessage(data.msg || '已保存', 'success');
                closeOverlay();
                reloadPage();
            }).catch(function (err) {
                VS.showMessage(err.message || '保存失败', 'error');
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
                status: row.getAttribute('data-link-status') || 1
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
                reloadPage();
            }).catch(function (err) {
                VS.showMessage(err.message || '操作失败', 'error');
            });
            return;
        }

        if (action === 'delete') {
            if (!window.confirm('确定删除该友链？')) return;
            var del = new FormData();
            del.append('action', 'delete');
            del.append('link_id', String(id));
            postAction(del).then(function (data) {
                VS.showMessage(data.msg || '已删除', 'success');
                if (row) row.remove();
                if (list && list.children.length === 0) {
                    if (list) list.hidden = true;
                    if (empty) empty.hidden = false;
                }
            }).catch(function (err) {
                VS.showMessage(err.message || '删除失败', 'error');
            });
        }
    });
})();
