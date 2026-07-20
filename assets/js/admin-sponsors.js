/**
 * 后台 · 赞助管理（AJAX + 局部 DOM）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminSponsorsPage');
    if (!page) {
        return;
    }

    var overlay = document.getElementById('sponsorFormOverlay');
    var form = document.getElementById('sponsorForm');
    var list = document.getElementById('sponsorList');
    var empty = document.getElementById('sponsorEmpty');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syncEmpty() {
        var has = list && list.querySelector('[data-sponsor-row]');
        if (list) list.hidden = !has;
        if (empty) empty.hidden = !!has;
    }

    function actionsHtml(link) {
        var id = parseInt(link.id, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        var html = '<button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-sponsor-action="edit" data-link-id="' + id + '">编辑</button>';
        if (enabled === 1) {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-sponsor-action="disable" data-link-id="' + id + '">禁用</button>';
        } else {
            html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary" data-sponsor-action="enable" data-link-id="' + id + '">启用</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger" data-sponsor-action="delete" data-link-id="' + id + '">删除</button>';
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

    function urlHtml(siteurl) {
        if (siteurl) {
            return '<a class="vs-link-row__url" data-field="siteurl" href="' + esc(siteurl) + '" target="_blank" rel="noopener noreferrer">' + esc(siteurl) + '</a>';
        }
        return '<span class="vs-link-row__url is-empty" data-field="siteurl">未填写链接</span>';
    }

    function rowHtml(link) {
        var id = parseInt(link.id, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        var name = link.name || '';
        var siteurl = link.siteurl || '';
        var icon = link.icon || '';
        var description = link.description || '';
        var sort = link.sort != null ? link.sort : 0;
        var label = enabled === 1 ? '启用' : '禁用';
        var cls = enabled === 1 ? 'is-on' : 'is-off';
        var descHtml = description
            ? '<div class="vs-link-row__desc" data-field="description">' + esc(description) + '</div>'
            : '<div class="vs-link-row__desc" data-field="description" hidden></div>';
        return (
            '<div class="vs-link-row"' +
            ' data-sponsor-row="' + id + '"' +
            ' data-link-enabled="' + enabled + '"' +
            ' data-name="' + esc(name) + '"' +
            ' data-siteurl="' + esc(siteurl) + '"' +
            ' data-icon="' + esc(icon) + '"' +
            ' data-description="' + esc(description) + '"' +
            ' data-sort="' + esc(sort) + '"' +
            ' data-enabled="' + enabled + '">' +
            '<div class="vs-link-row__icon">' + iconHtml(link) + '</div>' +
            '<div class="vs-link-row__main">' +
            '<div class="vs-link-row__name" data-field="name">' + esc(name) + '</div>' +
            descHtml +
            urlHtml(siteurl) +
            '</div>' +
            '<div class="vs-link-row__status">' +
            '<span class="vs-link-status ' + cls + '" data-field="enabled_label">' + esc(label) + '</span>' +
            '</div>' +
            '<div class="vs-link-row__actions">' + actionsHtml(link) + '</div>' +
            '</div>'
        );
    }

    function upsertRow(link) {
        if (!link || !list) return;
        var id = parseInt(link.id, 10) || 0;
        var html = rowHtml(link);
        var existing = list.querySelector('[data-sponsor-row="' + id + '"]');
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
        document.getElementById('sponsorFormAction').value = data.action || 'create';
        document.getElementById('sponsorFormId').value = data.id || 0;
        document.getElementById('sponsorFormTitle').textContent = data.title || '添加赞助';
        document.getElementById('sponsorName').value = data.name || '';
        document.getElementById('sponsorDesc').value = data.description || '';
        document.getElementById('sponsorUrl').value = data.siteurl || '';
        document.getElementById('sponsorIcon').value = data.icon || '';
        document.getElementById('sponsorSort').value = data.sort != null ? data.sort : 0;
        var enabledEl = document.getElementById('sponsorEnabled');
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

    var addBtn = document.getElementById('sponsorOpenAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            fillForm({ action: 'create', title: '添加赞助', enabled: 1, sort: 0 });
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
            var submitBtn = document.querySelector('button[form="sponsorForm"]');
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
        var btn = e.target.closest('[data-sponsor-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-sponsor-action');
        var id = parseInt(btn.getAttribute('data-link-id') || '0', 10);
        var row = btn.closest('[data-sponsor-row]');

        if (action === 'edit' && row) {
            fillForm({
                action: 'update',
                title: '编辑赞助',
                id: id,
                name: row.getAttribute('data-name') || '',
                description: row.getAttribute('data-description') || '',
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
                if (!row) return;
                upsertRow({
                    id: id,
                    name: row.getAttribute('data-name') || '',
                    description: row.getAttribute('data-description') || '',
                    siteurl: row.getAttribute('data-siteurl') || '',
                    icon: row.getAttribute('data-icon') || '',
                    icon_url: (row.querySelector('.vs-link-row__icon img') || {}).src || '',
                    sort: row.getAttribute('data-sort') || 0,
                    enabled: data.enabled != null ? data.enabled : (action === 'enable' ? 1 : 0)
                });
            }).catch(function (err) {
                VS.showMessage(err.message || '操作失败', 'error');
            });
            return;
        }

        if (action === 'delete') {
            var ask = (window.VsModal && window.VsModal.confirm)
                ? window.VsModal.confirm('删除后不可恢复，确定删除该赞助记录？', '删除赞助')
                : Promise.resolve(window.confirm('确定删除该赞助记录？'));
            ask.then(function (ok) {
                if (!ok) return;
                var del = new FormData();
                del.append('action', 'delete');
                del.append('link_id', String(id));
                postAction(del).then(function () {
                    VS.showMessage('已删除', 'success');
                    if (row) row.remove();
                    syncEmpty();
                }).catch(function (err) {
                    VS.showMessage(err.message || '删除失败', 'error');
                });
            });
        }
    });
})();
