/**
 * 后台 · 合作伙伴管理（AJAX + 局部 DOM，禁止整页刷新）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminPartnersPage');
    if (!page) {
        return;
    }

    var overlay = document.getElementById('partnerFormOverlay');
    var form = document.getElementById('partnerForm');
    var list = document.getElementById('partnerList');
    var empty = document.getElementById('partnerEmpty');
    var currentPage = 1;
    var prevBtn = document.getElementById('partnerPrevBtn');
    var nextBtn = document.getElementById('partnerNextBtn');
    var pagerNums = document.getElementById('partnerPagerNums');
    var pageSizeEl = document.getElementById('partnerPageSize');
    var footerEl = document.getElementById('partnerFooter');
    var statsEl = document.getElementById('partnerStats');

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if ([10, 20, 30, 50].indexOf(n) === -1) {
            n = 20;
        }
        return n;
    }

    var listTotal = 0;

    function applyListPage() {
        if (!list) {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(currentPage));
        fd.append('pagesize', String(getPageSize()));
        postAction(fd).then(function (data) {
            if (data.page) {
                currentPage = parseInt(data.page, 10) || currentPage;
            }
            listTotal = parseInt(data.total, 10) || 0;
            var items = data.list || [];
            var html = headHtml();
            items.forEach(function (link) {
                html += rowHtml(link);
            });
            list.innerHTML = html;
            var has = listTotal > 0;
            list.hidden = items.length === 0;
            if (empty) {
                empty.hidden = has;
            }
            if (footerEl) {
                footerEl.hidden = !has;
            }
            if (statsEl) {
                statsEl.textContent = '共 ' + listTotal + ' 条';
            }
            var totalPages = Math.max(1, Math.ceil(listTotal / getPageSize()) || 1);
            if (window.VS && VS.applyLogsPager) {
                if (!has) {
                    if (pagerNums) {
                        pagerNums.innerHTML = '';
                    }
                    if (prevBtn) {
                        prevBtn.disabled = true;
                    }
                    if (nextBtn) {
                        nextBtn.disabled = true;
                    }
                } else {
                    VS.applyLogsPager(currentPage, totalPages, prevBtn, nextBtn, pagerNums);
                }
            }
            if (window.VS && window.VS.bindExternalImgFallback) {
                window.VS.bindExternalImgFallback(list);
            }
        }).catch(function (err) {
            if (window.VS && VS.showMessage) {
                VS.showMessage(err.message || '加载失败', 'error');
            }
        });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syncEmpty() {
        applyListPage();
    }

    function actionsHtml(link) {
        var id = parseInt(link.id, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        var html = '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-partner-action="edit" data-link-id="' + id + '">编辑</button>';
        if (enabled === 1) {
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning" data-partner-action="disable" data-link-id="' + id + '">禁用</button>';
        } else {
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success" data-partner-action="enable" data-link-id="' + id + '">启用</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger" data-partner-action="delete" data-link-id="' + id + '">删除</button>';
        return html;
    }

    function iconHtml(link) {
        var url = (window.VS && window.VS.upgradeInsecureUrl)
            ? window.VS.upgradeInsecureUrl(link.icon_url || '')
            : (link.icon_url || '');
        var name = link.name || '';
        if (url) {
            return '<img src="' + esc(url) + '" alt="' + esc(name) + '" width="32" height="32" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">';
        }
        var initial = name ? name.charAt(0) : '?';
        return '<span class="vs-link-row__initial">' + esc(initial) + '</span>';
    }

    function headHtml() {
        return '<div class="vs-link-row vs-link-row--head" aria-hidden="true">'
            + '<div class="vs-link-cell">名称</div>'
            + '<div class="vs-link-cell">链接</div>'
            + '<div class="vs-link-cell">简介</div>'
            + '<div class="vs-link-cell">状态</div>'
            + '<div class="vs-link-cell">操作</div>'
            + '</div>';
    }

    function rowHtml(link) {
        var id = parseInt(link.id, 10) || 0;
        var enabled = parseInt(link.enabled, 10);
        if (enabled !== 0) enabled = 1;
        var name = link.name || '';
        var siteurl = link.siteurl || '';
        var icon = link.icon || '';
        var sort = link.sort != null ? link.sort : 0;
        var desc = link.description || '';
        var label = enabled === 1 ? '启用' : '禁用';
        var cls = enabled === 1 ? 'is-on' : 'is-off';
        return (
            '<div class="vs-link-row"' +
            ' data-partner-row="' + id + '"' +
            ' data-link-enabled="' + enabled + '"' +
            ' data-name="' + esc(name) + '"' +
            ' data-siteurl="' + esc(siteurl) + '"' +
            ' data-icon="' + esc(icon) + '"' +
            ' data-description="' + esc(desc) + '"' +
            ' data-sort="' + esc(sort) + '"' +
            ' data-enabled="' + enabled + '">' +
            '<div class="vs-link-cell vs-link-c-site">' +
            '<div class="vs-link-row__icon">' + iconHtml(link) + '</div>' +
            '<div class="vs-link-row__name" data-field="name">' + esc(name) + '</div>' +
            '</div>' +
            '<div class="vs-link-cell vs-link-c-url">' +
            '<a class="vs-link-row__url" data-field="siteurl" href="' + esc(siteurl) + '" target="_blank" rel="noopener noreferrer">' + esc(siteurl) + '</a>' +
            '</div>' +
            '<div class="vs-link-cell vs-link-c-desc">' +
            '<div class="vs-link-row__desc" data-field="description">' + esc(desc || '—') + '</div>' +
            '</div>' +
            '<div class="vs-link-cell vs-link-c-status">' +
            '<span class="vs-link-status ' + cls + '" data-field="enabled_label">' + esc(label) + '</span>' +
            '</div>' +
            '<div class="vs-link-cell vs-link-c-actions">' + actionsHtml(link) + '</div>' +
            '</div>'
        );
    }

    function upsertRow() {
        applyListPage();
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
        document.getElementById('partnerFormAction').value = data.action || 'create';
        document.getElementById('partnerFormId').value = data.id || 0;
        document.getElementById('partnerFormTitle').textContent = data.title || '添加合作伙伴';
        document.getElementById('partnerName').value = data.name || '';
        document.getElementById('partnerUrl').value = data.siteurl || '';
        document.getElementById('partnerIcon').value = data.icon || '';
        var descEl = document.getElementById('partnerDesc');
        if (descEl) {
            descEl.value = data.description || '';
        }
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
            var submitBtn = form.querySelector('[type="submit"]') || document.querySelector('button[form="partnerForm"]');
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
                description: row.getAttribute('data-description') || '',
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
                ? window.VsModal.confirm('删除后不可恢复，确定删除该合作伙伴？', '删除合作伙伴')
                : Promise.resolve(window.confirm('确定删除该合作伙伴？'));
            ask.then(function (ok) {
                if (!ok) return;
                var del = new FormData();
                del.append('action', 'delete');
                del.append('link_id', String(id));
                postAction(del).then(function (data) {
                    VS.showMessage(data.msg || '已删除', 'success');
                    syncEmpty();
                }).catch(function (err) {
                    VS.showMessage(err.message || '删除失败', 'error');
                });
            });
        }
    });

    var purgeBtn = document.getElementById('partnerPurgeCacheBtn');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'purge_cache');
            postAction(fd).then(function (data) {
                VS.showMessage(data.msg || '已清理', 'success');
            }).catch(function (err) {
                VS.showMessage(err.message || '清理失败', 'error');
            });
        });
    }
    function listTotalPages() {
        return Math.max(1, Math.ceil(listTotal / getPageSize()) || 1);
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (prevBtn.disabled) {
                return;
            }
            if (currentPage > 1) {
                currentPage -= 1;
                applyListPage();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (nextBtn.disabled) {
                return;
            }
            if (currentPage < listTotalPages()) {
                currentPage += 1;
                applyListPage();
            }
        });
    }
    if (pagerNums) {
        pagerNums.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-page]');
            if (!btn) {
                return;
            }
            var next = parseInt(btn.getAttribute('data-page'), 10) || 0;
            if (!next || next === currentPage) {
                return;
            }
            currentPage = next;
            applyListPage();
        });
    }
    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            currentPage = 1;
            applyListPage();
        });
    }
    applyListPage();
})();
