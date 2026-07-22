/**
 * 文件：assets/js/admin-content.js
 * 作用：公告 / 文章管理（AJAX 局部更新 + Markdown 编辑器）
 */
(function () {
    'use strict';

    var page = document.getElementById('contentPage');
    var list = document.getElementById('contentList');
    var overlay = document.getElementById('contentOverlay');
    var form = document.getElementById('contentForm');
    var addBtn = document.getElementById('contentAddBtn');
    var saveBtn = document.getElementById('contentSaveBtn');
    var formTitle = document.getElementById('contentFormTitle');
    if (!page || !list || !form) {
        return;
    }

    var mode = page.getAttribute('data-mode') || 'article';
    var isAnnouncement = mode === 'announcement';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function openOverlay() {
        if (!overlay) return;
        overlay.hidden = false;
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        if (window.VsMarkdownEditor) {
            VsMarkdownEditor.mountAll(overlay);
        }
    }

    function closeOverlay() {
        if (!overlay) return;
        overlay.classList.remove('is-open');
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
    }

    function fillForm(row) {
        form.content_id.value = row ? String(row.id || 0) : '0';
        form.title.value = row ? (row.title || '') : '';
        form.body.value = row ? (row.body || '') : '';
        if (form.summary) {
            form.summary.value = row ? (row.summary || '') : '';
        }
        if (form.cover) {
            form.cover.value = row ? (row.cover || '') : '';
        }
        if (form.coverlayout) {
            form.coverlayout.value = row ? String(row.coverlayout != null ? row.coverlayout : 0) : '0';
            if (window.VSPick && VSPick.refresh) {
                VSPick.refresh(form.coverlayout);
            }
        }
        if (formTitle) {
            formTitle.textContent = row && row.id
                ? (isAnnouncement ? '编辑公告' : '编辑文章')
                : (isAnnouncement ? '发布公告' : '发布文章');
        }
        var ta = form.body;
        if (ta && ta.dispatchEvent) {
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function rowFromEl(el) {
        return {
            id: parseInt(el.getAttribute('data-content-row'), 10) || 0,
            title: el.getAttribute('data-title') || '',
            summary: el.getAttribute('data-summary') || '',
            body: el.getAttribute('data-body') || '',
            cover: el.getAttribute('data-cover') || '',
            coverlayout: parseInt(el.getAttribute('data-coverlayout'), 10) || 0,
            status: parseInt(el.getAttribute('data-status'), 10) || 0,
            ispinned: parseInt(el.getAttribute('data-ispinned'), 10) || 0,
            ispopup: parseInt(el.getAttribute('data-ispopup'), 10) || 0
        };
    }

    function metaHtml(item) {
        var parts = ['<span>' + esc(item.status_label || '已发布') + '</span>'];
        if (isAnnouncement && Number(item.ispinned) === 1) parts.push('<span>置顶</span>');
        if (isAnnouncement && Number(item.ispopup) === 1) parts.push('<span>弹窗</span>');
        if (!isAnnouncement) {
            parts.push('<span>阅读 ' + (item.views || 0) + '</span>');
            if (item.cover) parts.push('<span>有封面</span>');
            if (item.coverlayout_label) parts.push('<span>' + esc(item.coverlayout_label) + '</span>');
        }
        if (item.createtime) parts.push('<span>' + esc(item.createtime) + '</span>');
        return parts.join('');
    }

    function actionsHtml(item) {
        var html = '<button type="button" class="vs-btn vs-btn--default vs-btn--sm" data-act="edit">编辑</button>';
        if (isAnnouncement) {
            html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-act="pin">'
                + (Number(item.ispinned) === 1 ? '取消置顶' : '置顶') + '</button>';
            html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-act="popup">'
                + (Number(item.ispopup) === 1 ? '取消弹窗' : '设为弹窗') + '</button>';
        }
        html += '<button type="button" class="vs-btn vs-btn--danger vs-btn--sm" data-act="delete">删除</button>';
        return html;
    }

    function renderRow(item) {
        return '<div class="vs-api-item vs-content-item" data-content-row="' + item.id + '"'
            + ' data-title="' + esc(item.title) + '"'
            + ' data-summary="' + esc(item.summary || '') + '"'
            + ' data-body="' + esc(item.body) + '"'
            + ' data-cover="' + esc(item.cover || '') + '"'
            + ' data-coverlayout="' + (item.coverlayout != null ? item.coverlayout : 0) + '"'
            + ' data-status="' + item.status + '"'
            + ' data-ispinned="' + (item.ispinned || 0) + '"'
            + ' data-ispopup="' + (item.ispopup || 0) + '">'
            + '<div class="vs-content-item__main">'
            + '<div class="vs-api-item__title">'
            + '<span class="vs-api-item__name">' + esc(item.title) + '</span>'
            + '<span class="vs-api-item__id">#' + item.id + '</span>'
            + '</div>'
            + '<div class="vs-content-item__meta">' + metaHtml(item) + '</div>'
            + '</div>'
            + '<div class="vs-api-item__actions vs-content-item__actions">' + actionsHtml(item) + '</div>'
            + '</div>';
    }

    function upsertRow(item) {
        var empty = document.getElementById('contentEmpty');
        if (empty) empty.remove();
        var el = list.querySelector('[data-content-row="' + item.id + '"]');
        var html = renderRow(item);
        if (el) {
            el.outerHTML = html;
        } else {
            list.insertAdjacentHTML('afterbegin', html);
        }
    }

    function post(fd) {
        if (!window.VS || !VS.postForm) {
            return Promise.reject(new Error('VS'));
        }
        return VS.postForm(fd);
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            fillForm(null);
            openOverlay();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target.closest('[data-overlay-close]')) closeOverlay();
        });
    }

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        var row = btn.closest('[data-content-row]');
        if (!row) return;
        var data = rowFromEl(row);
        var act = btn.getAttribute('data-act');

        if (act === 'edit') {
            fillForm(data);
            openOverlay();
            return;
        }

        if (act === 'delete') {
            if (!window.confirm('确定删除？')) return;
            var fd = new FormData();
            fd.append('action', 'delete');
            fd.append('content_id', String(data.id));
            post(fd).then(function (res) {
                if (!res || res.code !== 1) {
                    if (VS.showMessage) VS.showMessage((res && res.msg) || '删除失败', 'error');
                    return;
                }
                row.remove();
                if (!list.querySelector('[data-content-row]')) {
                    list.innerHTML = '<p class="vs-empty" id="contentEmpty">暂无内容，点击右上角发布。</p>';
                }
                if (VS.showMessage) VS.showMessage(res.msg || '已删除', 'success');
            });
            return;
        }

        if (act === 'pin' || act === 'popup') {
            var fd2 = new FormData();
            if (act === 'pin') {
                fd2.append('action', 'set_pinned');
                fd2.append('ispinned', data.ispinned === 1 ? '0' : '1');
            } else {
                fd2.append('action', 'set_popup');
                fd2.append('ispopup', data.ispopup === 1 ? '0' : '1');
            }
            fd2.append('content_id', String(data.id));
            post(fd2).then(function (res) {
                if (!res || res.code !== 1) {
                    if (VS.showMessage) VS.showMessage((res && res.msg) || '操作失败', 'error');
                    return;
                }
                if (act === 'pin') data.ispinned = res.ispinned;
                if (act === 'popup') data.ispopup = res.ispopup;
                var metaEl = row.querySelector('.vs-content-item__meta span');
                data.status_label = metaEl ? metaEl.textContent : '已发布';
                var timeEl = row.querySelector('.vs-content-item__meta span:last-child');
                row.setAttribute('data-ispinned', String(data.ispinned || 0));
                row.setAttribute('data-ispopup', String(data.ispopup || 0));
                upsertRow({
                    id: data.id,
                    title: data.title,
                    summary: data.summary,
                    body: data.body,
                    cover: data.cover,
                    coverlayout: data.coverlayout,
                    status: data.status,
                    status_label: data.status_label || '已发布',
                    ispinned: data.ispinned,
                    ispopup: data.ispopup,
                    createtime: timeEl ? timeEl.textContent : ''
                });
                if (VS.showMessage) VS.showMessage(res.msg || '已更新', 'success');
            });
        }
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var id = parseInt(form.content_id.value, 10) || 0;
            var fd = new FormData(form);
            fd.set('action', id > 0 ? 'update' : 'create');
            fd.set('status', '1');
            if (isAnnouncement) {
                fd.delete('summary');
            }
            saveBtn.disabled = true;
            post(fd).then(function (res) {
                saveBtn.disabled = false;
                if (!res || res.code !== 1) {
                    if (VS.showMessage) VS.showMessage((res && res.msg) || '保存失败', 'error');
                    return;
                }
                if (res.item) upsertRow(res.item);
                closeOverlay();
                if (VS.showMessage) VS.showMessage(res.msg || '已保存', 'success');
            }).catch(function () {
                saveBtn.disabled = false;
                if (VS.showMessage) VS.showMessage('网络异常', 'error');
            });
        });
    }
})();
