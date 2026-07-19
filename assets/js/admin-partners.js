/**
 * 后台 · 合作伙伴管理
 */
(function () {
    'use strict';

    var page = document.getElementById('adminPartnersPage');
    if (!page) {
        return;
    }

    var overlay = document.getElementById('partnerFormOverlay');
    var form = document.getElementById('partnerForm');

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
        document.getElementById('partnerFormAction').value = data.action || 'create';
        document.getElementById('partnerFormId').value = data.id || 0;
        document.getElementById('partnerFormTitle').textContent = data.title || '添加合作伙伴';
        document.getElementById('partnerName').value = data.name || '';
        document.getElementById('partnerUrl').value = data.siteurl || '';
        document.getElementById('partnerIcon').value = data.icon || '';
        document.getElementById('partnerSort').value = data.sort != null ? data.sort : 0;
        var enabledEl = document.getElementById('partnerEnabled');
        enabledEl.value = data.enabled != null ? String(data.enabled) : '1';
        if (window.VSPick) {
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

    var addBtn = document.getElementById('partnerOpenAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            fillForm({ action: 'create', title: '添加合作伙伴', enabled: 1, sort: 0 });
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
                window.location.reload();
            }).catch(function (err) {
                VS.showMessage(err.message || '保存失败', 'error');
            });
        });
    }

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-partner-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-partner-action');
        var id = parseInt(btn.getAttribute('data-link-id') || '0', 10);
        var row = btn.closest('[data-partner-row]');

        if (action === 'edit' && row) {
            fillForm({
                action: 'update',
                title: '编辑合作伙伴',
                id: id,
                name: row.getAttribute('data-name') || '',
                siteurl: row.getAttribute('data-siteurl') || '',
                icon: row.getAttribute('data-icon') || '',
                sort: row.getAttribute('data-sort') || 0,
                enabled: row.getAttribute('data-link-enabled') || 1
            });
            openOverlay();
            return;
        }

        if (action === 'enable' || action === 'disable') {
            var fd = new FormData();
            fd.append('action', 'set_enabled');
            fd.append('link_id', String(id));
            fd.append('enabled', action === 'enable' ? '1' : '0');
            postAction(fd).then(function (data) {
                VS.showMessage(data.msg || '已更新', 'success');
                window.location.reload();
            }).catch(function (err) {
                VS.showMessage(err.message || '操作失败', 'error');
            });
        }
    });
})();
