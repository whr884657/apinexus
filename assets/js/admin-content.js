/**
 * 文件：assets/js/admin-content.js
 * 作用：公告 / 文章管理（双 DOM + 搜索分页 + Markdown 编辑）
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
        var page = document.getElementById('contentPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('contentTableWrap');
        var listEl = document.getElementById('contentBody');
        var mobileEl = document.getElementById('contentMobile');
        var emptyEl = document.getElementById('contentEmpty');
        var searchEmptyEl = document.getElementById('contentSearchEmpty');
        var searchInput = document.getElementById('contentSearchInput');
        var pageSizeEl = document.getElementById('contentPageSize');
        var footerEl = document.getElementById('contentFooter');
        var pagerNumsEl = document.getElementById('contentPagerNums');
        var statsEl = document.getElementById('contentStats');
        var prevBtn = document.getElementById('contentPrevBtn');
        var nextBtn = document.getElementById('contentNextBtn');
        var overlay = document.getElementById('contentOverlay');
        var form = document.getElementById('contentForm');
        var addBtn = document.getElementById('contentAddBtn');
        var saveBtn = document.getElementById('contentSaveBtn');
        var formTitle = document.getElementById('contentFormTitle');
        var mode = page.getAttribute('data-mode') || 'article';
        var isAnnouncement = mode === 'announcement';
        var currentPage = 1;
        var returnFocusEl = null;

        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function ok(res) {
            return res && Number(res.code) === 1;
        }

        function msg(res, fallback) {
            return (res && res.msg) ? String(res.msg) : fallback;
        }

        function defaultPageSize() {
            return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
            return n > 0 ? n : defaultPageSize();
        }

        function allDesktopRows() {
            if (!listEl) {
                return [];
            }
            return Array.prototype.slice.call(listEl.querySelectorAll('tr[data-content-row]'));
        }

        function getPair(id) {
            var sid = String(id);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-content-row="' + sid + '"]') : null,
                mobile: mobileEl
                    ? mobileEl.querySelector('[data-content-row="' + sid + '"]')
                    : null
            };
        }

        function syncRowOrder(rows) {
            if (!listEl) {
                return;
            }
            rows.forEach(function (row) {
                listEl.appendChild(row);
                if (mobileEl) {
                    var id = row.getAttribute('data-content-row');
                    var card = mobileEl.querySelector('[data-content-row="' + id + '"]');
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
                return (row.getAttribute('data-search') || '').toLowerCase().indexOf(q) !== -1;
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
            var filtered = !!((searchInput && String(searchInput.value || '').trim()) || false);

            matched.forEach(function (row, idx) {
                var show = idx >= start && idx < end;
                var id = row.getAttribute('data-content-row');
                row.hidden = !show;
                if (show) {
                    visibleIds[id] = true;
                }
            });

            if (mobileEl) {
                Array.prototype.slice.call(mobileEl.querySelectorAll('[data-content-row]')).forEach(function (card) {
                    var id = card.getAttribute('data-content-row');
                    var inMatched = matched.some(function (r) {
                        return r.getAttribute('data-content-row') === id;
                    });
                    card.hidden = !(inMatched && visibleIds[id]);
                });
            }

            var hasAny = totalAll > 0;
            var hasVisible = matched.length > 0 && Object.keys(visibleIds).length > 0;
            if (emptyEl) {
                emptyEl.hidden = hasAny;
            }
            if (searchEmptyEl) {
                searchEmptyEl.hidden = !(hasAny && !hasVisible && filtered);
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
                    + (hasAny && filtered ? ('（全部 ' + totalAll + '）') : '');
            }
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || matched.length === 0;
            }
            renderPagerNums(matched.length === 0 ? 0 : totalPages);
        }

        function openOverlay() {
            if (!overlay) {
                return;
            }
            overlay.hidden = false;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-overlay-open');
            if (window.VsMarkdownEditor) {
                VsMarkdownEditor.mountAll(overlay);
            }
            if (window.VSPick) {
                VSPick.init(overlay);
            }
        }

        function closeOverlay() {
            if (!overlay) {
                return;
            }
            overlay.classList.remove('is-open');
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-overlay-open');
            if (returnFocusEl && typeof returnFocusEl.focus === 'function') {
                returnFocusEl.focus();
            }
            returnFocusEl = null;
        }

        function fillForm(row) {
            if (!form) {
                return;
            }
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
            if (form.body && form.body.dispatchEvent) {
                form.body.dispatchEvent(new Event('input', { bubbles: true }));
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
                status_label: el.getAttribute('data-status-label') || '已发布',
                ispinned: parseInt(el.getAttribute('data-ispinned'), 10) || 0,
                ispopup: parseInt(el.getAttribute('data-ispopup'), 10) || 0,
                views: parseInt(el.getAttribute('data-views'), 10) || 0,
                username: el.getAttribute('data-username') || '—',
                author_avatar: el.getAttribute('data-author-avatar') || '',
                createtime: el.getAttribute('data-createtime') || ''
            };
        }

        function dataAttrs(item) {
            var search = String(item.title || '') + ' ' + String(item.username || '')
                + ' ' + String(item.createtime || '') + ' #' + item.id;
            return ' data-content-row="' + item.id + '"'
                + ' data-search="' + esc(search.toLowerCase()) + '"'
                + ' data-title="' + esc(item.title) + '"'
                + ' data-summary="' + esc(item.summary || '') + '"'
                + ' data-body="' + esc(item.body || '') + '"'
                + ' data-cover="' + esc(item.cover || '') + '"'
                + ' data-coverlayout="' + (item.coverlayout != null ? item.coverlayout : 0) + '"'
                + ' data-status="' + (item.status != null ? item.status : 1) + '"'
                + ' data-status-label="' + esc(item.status_label || '已发布') + '"'
                + ' data-ispinned="' + (item.ispinned || 0) + '"'
                + ' data-ispopup="' + (item.ispopup || 0) + '"'
                + ' data-views="' + (item.views || 0) + '"'
                + ' data-username="' + esc(item.username || '—') + '"'
                + ' data-author-avatar="' + esc(item.author_avatar || '') + '"'
                + ' data-createtime="' + esc(item.createtime || '') + '"';
        }

        function actionsHtml(item) {
            var html = '<div class="action-btns">';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="edit">编辑</button>';
            if (isAnnouncement) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="pin">'
                    + (Number(item.ispinned) === 1 ? '取消置顶' : '置顶') + '</button>';
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="popup">'
                    + (Number(item.ispopup) === 1 ? '取消弹窗' : '设为弹窗') + '</button>';
            }
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-content-act" data-act="delete">删除</button>';
            html += '</div>';
            return html;
        }

        function desktopHtml(item) {
            var time = item.createtime || '—';
            var html = '<tr' + dataAttrs(item) + '>';
            html += '<td><div class="content-title-cell" data-field="title">' + esc(item.title) + '</div></td>';
            if (isAnnouncement) {
                html += '<td><span class="content-time-cell" data-field="createtime">' + esc(time) + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.ispinned) === 1 ? 'vs-badge--warning' : 'vs-badge--default')
                    + '" data-field="pin_label">' + (Number(item.ispinned) === 1 ? '置顶' : '—') + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.ispopup) === 1 ? 'vs-badge--info' : 'vs-badge--default')
                    + '" data-field="popup_label">' + (Number(item.ispopup) === 1 ? '弹窗' : '—') + '</span></td>';
            } else {
                var uname = item.username || '—';
                html += '<td><div class="content-author-cell">';
                if (item.author_avatar) {
                    html += '<img class="content-author-cell__avatar" src="' + esc(item.author_avatar)
                        + '" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer" data-field="author_avatar">';
                } else {
                    html += '<span class="content-author-cell__fallback" data-field="author_avatar">'
                        + esc(uname.charAt(0) || '?') + '</span>';
                }
                html += '<span class="content-author-cell__name" data-field="username">' + esc(uname) + '</span></div></td>';
                html += '<td><span class="content-time-cell" data-field="createtime">' + esc(time) + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.status) === 1 ? 'vs-badge--success' : 'vs-badge--default')
                    + '" data-field="status_label">' + esc(item.status_label || '已发布') + '</span></td>';
            }
            html += '<td class="vs-content-actions-cell" data-field="actions">' + actionsHtml(item) + '</td></tr>';
            return html;
        }

        function mobileHtml(item) {
            var cls = isAnnouncement ? 'ann-card' : 'art-card';
            var time = item.createtime || '—';
            var html = '<div class="' + cls + '"' + dataAttrs(item) + '>';
            html += '<div class="' + cls + '__header">';
            html += '<span class="' + cls + '__title" data-field="title">' + esc(item.title) + '</span>';
            html += '<div class="' + cls + '__tags">';
            if (isAnnouncement) {
                if (Number(item.ispinned) === 1) {
                    html += '<span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>';
                }
                if (Number(item.ispopup) === 1) {
                    html += '<span class="vs-badge vs-badge--info" data-field="popup_label">弹窗</span>';
                }
            } else {
                html += '<span class="vs-badge ' + (Number(item.status) === 1 ? 'vs-badge--success' : 'vs-badge--default')
                    + '" data-field="status_label">' + esc(item.status_label || '已发布') + '</span>';
            }
            html += '</div></div>';
            if (isAnnouncement) {
                html += '<span class="' + cls + '__time" data-field="createtime">' + esc(time) + '</span>';
            } else {
                html += '<div class="' + cls + '__info">';
                html += '<span class="' + cls + '__info-item"><span class="' + cls + '__info-label">作者</span> '
                    + '<span class="' + cls + '__info-value" data-field="username">' + esc(item.username || '—') + '</span></span>';
                html += '<span class="' + cls + '__info-item"><span class="' + cls + '__info-label">发布时间</span> '
                    + '<span class="' + cls + '__info-value" data-field="createtime">' + esc(time) + '</span></span>';
                html += '</div>';
            }
            html += '<div class="' + cls + '__actions" data-field="actions">' + actionsHtml(item) + '</div></div>';
            return html;
        }

        function upsertRow(item) {
            if (!item || !item.id) {
                return;
            }
            if (item.createtime && String(item.createtime).length >= 16) {
                item.createtime = String(item.createtime).slice(0, 16);
            }
            var pair = getPair(item.id);
            var dHtml = desktopHtml(item);
            var mHtml = mobileHtml(item);
            if (pair.desktop) {
                pair.desktop.outerHTML = dHtml;
            } else if (listEl) {
                listEl.insertAdjacentHTML('afterbegin', dHtml);
            }
            if (pair.mobile) {
                pair.mobile.outerHTML = mHtml;
            } else if (mobileEl) {
                mobileEl.insertAdjacentHTML('afterbegin', mHtml);
            }
            applyView();
        }

        function removePair(id) {
            var pair = getPair(id);
            if (pair.desktop) {
                pair.desktop.remove();
            }
            if (pair.mobile) {
                pair.mobile.remove();
            }
            applyView();
        }

        function post(fd) {
            return window.VS.postForm(fd);
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                returnFocusEl = addBtn;
                fillForm(null);
                openOverlay();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target.closest('[data-overlay-close]')) {
                    closeOverlay();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay && overlay.classList.contains('is-open')) {
                closeOverlay();
            }
        });

        if (saveBtn && form) {
            saveBtn.addEventListener('click', function () {
                var id = parseInt(form.content_id.value, 10) || 0;
                var fd = new FormData(form);
                fd.append('action', id > 0 ? 'update' : 'create');
                saveBtn.disabled = true;
                post(fd).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '保存失败'));
                    }
                    window.VS.showMessage(msg(res, '已保存'), 'success');
                    closeOverlay();
                    if (res.item) {
                        upsertRow(res.item);
                    }
                }).catch(function (err) {
                    window.VS.showMessage((err && err.message) ? err.message : '保存失败', 'error');
                }).then(function () {
                    saveBtn.disabled = false;
                });
            });
        }

        page.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-content-act');
            if (!btn) {
                return;
            }
            var row = btn.closest('[data-content-row]');
            if (!row) {
                return;
            }
            var act = btn.getAttribute('data-act');
            var data = rowFromEl(row);
            var id = data.id;

            if (act === 'edit') {
                returnFocusEl = btn;
                fillForm(data);
                openOverlay();
                return;
            }

            if (act === 'pin') {
                var nextPin = Number(data.ispinned) === 1 ? 0 : 1;
                var fdPin = new FormData();
                fdPin.append('action', 'set_pinned');
                fdPin.append('content_id', String(id));
                fdPin.append('ispinned', String(nextPin));
                post(fdPin).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '操作失败'));
                    }
                    data.ispinned = nextPin;
                    upsertRow(data);
                    window.VS.showMessage(msg(res, '已更新'), 'success');
                }).catch(function (err) {
                    window.VS.showMessage((err && err.message) ? err.message : '操作失败', 'error');
                });
                return;
            }

            if (act === 'popup') {
                var nextPopup = Number(data.ispopup) === 1 ? 0 : 1;
                var fdPop = new FormData();
                fdPop.append('action', 'set_popup');
                fdPop.append('content_id', String(id));
                fdPop.append('ispopup', String(nextPopup));
                post(fdPop).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '操作失败'));
                    }
                    data.ispopup = nextPopup;
                    upsertRow(data);
                    window.VS.showMessage(msg(res, '已更新'), 'success');
                }).catch(function (err) {
                    window.VS.showMessage((err && err.message) ? err.message : '操作失败', 'error');
                });
                return;
            }

            if (act === 'delete') {
                var ask = (window.VsModal && typeof window.VsModal.confirm === 'function')
                    ? window.VsModal.confirm('确定删除该' + (isAnnouncement ? '公告' : '文章') + '？', '删除确认', { danger: true })
                    : Promise.resolve(window.confirm('确定删除？'));
                ask.then(function (yes) {
                    if (!yes) {
                        return;
                    }
                    var fdDel = new FormData();
                    fdDel.append('action', 'delete');
                    fdDel.append('content_id', String(id));
                    post(fdDel).then(function (res) {
                        if (!ok(res)) {
                            throw new Error(msg(res, '删除失败'));
                        }
                        removePair(id);
                        window.VS.showMessage(msg(res, '已删除'), 'success');
                    }).catch(function (err) {
                        window.VS.showMessage((err && err.message) ? err.message : '删除失败', 'error');
                    });
                });
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentPage = 1;
                applyView();
            });
        }
        if (pageSizeEl) {
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
        if (pagerNumsEl) {
            pagerNumsEl.addEventListener('click', function (e) {
                var num = e.target.closest('[data-page]');
                if (!num) {
                    return;
                }
                currentPage = parseInt(num.getAttribute('data-page'), 10) || 1;
                applyView();
            });
        }

        applyView();
    }

    boot();
})();
