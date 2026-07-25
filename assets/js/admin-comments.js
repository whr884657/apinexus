/**
 * 文件：assets/js/admin-comments.js
 * 作用：评论管理（双 DOM + 搜索分页 + 回复/置顶/审核）
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
        var page = document.getElementById('adminCommentsPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('adminCmtTableWrap');
        var listEl = document.getElementById('adminCmtBody');
        var mobileEl = document.getElementById('adminCmtMobile');
        var emptyEl = document.getElementById('adminCmtEmpty');
        var searchEmptyEl = document.getElementById('adminCmtSearchEmpty');
        var searchInput = document.getElementById('adminCmtSearchInput');
        var pageSizeEl = document.getElementById('adminCmtPageSize');
        var footerEl = document.getElementById('adminCmtFooter');
        var pagerNumsEl = document.getElementById('adminCmtPagerNums');
        var statsEl = document.getElementById('adminCmtStats');
        var prevBtn = document.getElementById('adminCmtPrevBtn');
        var nextBtn = document.getElementById('adminCmtNextBtn');
        var replyOverlay = document.getElementById('adminCmtReplyOverlay');
        var addOverlay = document.getElementById('adminCmtAddOverlay');
        var addBtn = document.getElementById('adminCmtAddBtn');
        var addForm = document.getElementById('adminCmtAddForm');
        var replySaveBtn = document.getElementById('adminCmtReplySaveBtn');
        var currentPage = 1;

        [replyOverlay, addOverlay].forEach(function (ov) {
            if (ov && ov.parentNode !== document.body) {
                document.body.appendChild(ov);
            }
        });

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function ok(res) { return res && Number(res.code) === 1; }
        function msg(res, fb) { return (res && res.msg) ? String(res.msg) : fb; }

        function openOv(ov) {
            if (!ov) return;
            ov.hidden = false;
            ov.classList.add('is-open');
            ov.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-overlay-open');
            if (window.VSPick) VSPick.init(ov);
        }

        function closeOv(ov) {
            if (!ov) return;
            ov.classList.remove('is-open');
            ov.hidden = true;
            ov.setAttribute('aria-hidden', 'true');
            if ((!replyOverlay || !replyOverlay.classList.contains('is-open'))
                && (!addOverlay || !addOverlay.classList.contains('is-open'))) {
                document.body.classList.remove('is-overlay-open');
            }
        }

        function post(fields) {
            var fd = new FormData();
            Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
            return window.VS.postForm(fd);
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
            return n > 0 ? n : (window.matchMedia('(max-width: 900px)').matches ? 10 : 20);
        }

        function allRows() {
            return listEl ? Array.prototype.slice.call(listEl.querySelectorAll('tr[data-comment-row]')) : [];
        }

        function getPair(id) {
            var sid = String(id);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-comment-row="' + sid + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.cmt-card[data-comment-row="' + sid + '"]') : null
            };
        }

        function syncOrder(rows) {
            rows.forEach(function (row) {
                if (listEl) listEl.appendChild(row);
                if (mobileEl) {
                    var card = mobileEl.querySelector('.cmt-card[data-comment-row="' + row.getAttribute('data-comment-row') + '"]');
                    if (card) mobileEl.appendChild(card);
                }
            });
        }

        function matched() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var rows = allRows().filter(function (row) {
                return !q || (row.getAttribute('data-search') || '').toLowerCase().indexOf(q) !== -1;
            });
            syncOrder(rows);
            return rows;
        }

        function applyView() {
            var totalAll = allRows().length;
            var rows = matched();
            var pageSize = getPageSize();
            var totalPages = Math.max(1, Math.ceil(rows.length / pageSize) || 1);
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;
            var start = (currentPage - 1) * pageSize;
            var end = start + pageSize;
            var visible = {};
            var filtered = !!(searchInput && String(searchInput.value || '').trim());

            rows.forEach(function (row, idx) {
                var show = idx >= start && idx < end;
                var id = row.getAttribute('data-comment-row');
                row.hidden = !show;
                if (show) visible[id] = true;
            });
            if (mobileEl) {
                Array.prototype.slice.call(mobileEl.querySelectorAll('.cmt-card')).forEach(function (card) {
                    var id = card.getAttribute('data-comment-row');
                    var inM = rows.some(function (r) { return r.getAttribute('data-comment-row') === id; });
                    card.hidden = !(inM && visible[id]);
                });
            }
            var hasAny = totalAll > 0;
            var hasVisible = rows.length > 0 && Object.keys(visible).length > 0;
            if (emptyEl) emptyEl.hidden = hasAny;
            if (searchEmptyEl) searchEmptyEl.hidden = !(hasAny && !hasVisible && filtered);
            if (tableWrapEl) tableWrapEl.hidden = !hasVisible;
            if (mobileEl) mobileEl.hidden = !hasVisible;
            if (footerEl) footerEl.hidden = !hasAny;
            if (statsEl) {
                statsEl.textContent = '共 ' + rows.length + ' 条' + (hasAny && filtered ? ('（全部 ' + totalAll + '）') : '');
            }
            if (prevBtn) prevBtn.disabled = currentPage <= 1;
            if (nextBtn) nextBtn.disabled = currentPage >= totalPages || rows.length === 0;
            if (pagerNumsEl) {
                if (totalPages <= 1 || rows.length === 0) {
                    pagerNumsEl.innerHTML = '';
                } else {
                    var html = '';
                    var i;
                    for (i = 1; i <= totalPages; i += 1) {
                        html += '<button type="button" class="vs-api-pager__num' + (i === currentPage ? ' is-active' : '')
                            + '" data-page="' + i + '">' + i + '</button>';
                    }
                    pagerNumsEl.innerHTML = html;
                }
            }
        }

        function actionsHtml(c) {
            var id = c.id;
            var html = '<div class="action-btns">';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cmt-act" data-act="reply" data-comment-id="' + id + '">回复</button>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cmt-act" data-act="pin" data-comment-id="' + id + '">'
                + (Number(c.ispinned) === 1 ? '取消置顶' : '置顶') + '</button>';
            if (Number(c.status) === 0) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-cmt-act" data-act="approve" data-comment-id="' + id + '">通过</button>';
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-cmt-act" data-act="reject" data-comment-id="' + id + '">拒绝</button>';
            }
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-cmt-act" data-act="delete" data-comment-id="' + id + '">删除</button>';
            html += '</div>';
            return html;
        }

        function statusClass(st) {
            if (Number(st) === 1) return 'vs-badge--success';
            if (Number(st) === 2) return 'vs-badge--danger';
            return 'vs-badge--warning';
        }

        function dataAttrs(c) {
            var search = (c.body + ' ' + c.nickname + ' ' + c.email + ' ' + (c.content_title || '') + ' #' + c.id).toLowerCase();
            return ' data-comment-row="' + c.id + '"'
                + ' data-search="' + esc(search) + '"'
                + ' data-contentid="' + (c.contentid || 0) + '"'
                + ' data-content-title="' + esc(c.content_title || '') + '"'
                + ' data-nickname="' + esc(c.nickname || '') + '"'
                + ' data-email="' + esc(c.email || '') + '"'
                + ' data-body="' + esc(c.body || '') + '"'
                + ' data-reply="' + esc(c.reply || '') + '"'
                + ' data-ispinned="' + (c.ispinned || 0) + '"'
                + ' data-status="' + (c.status != null ? c.status : 0) + '"'
                + ' data-status-label="' + esc(c.status_label || '') + '"'
                + ' data-avatar-url="' + esc(c.avatar_url || '') + '"'
                + ' data-createtime="' + esc(c.createtime_short || c.createtime || '') + '"';
        }

        function desktopHtml(c) {
            var title = c.content_title || ('文章#' + (c.contentid || ''));
            var time = c.createtime_short || c.createtime || '—';
            if (time.length >= 16) time = time.slice(0, 16);
            var html = '<tr' + dataAttrs(c) + '>';
            html += '<td><div class="cmt-body-cell">';
            if (Number(c.ispinned) === 1) html += '<span class="cmt-pin-mark">[顶]</span> ';
            html += '<span data-field="body">' + esc(c.body) + '</span></div></td>';
            html += '<td><span data-field="content_title">' + esc(title) + '</span></td>';
            html += '<td><div class="cmt-author-cell">';
            if (c.avatar_url) {
                html += '<img class="cmt-author-cell__avatar" src="' + esc(c.avatar_url) + '" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer">';
            }
            html += '<div class="cmt-author-cell__meta"><span class="cmt-author-cell__name" data-field="nickname">'
                + esc(c.nickname) + '</span><span class="cmt-author-cell__email" data-field="email">'
                + esc(c.email) + '</span></div></div></td>';
            html += '<td><span data-field="createtime">' + esc(time) + '</span></td>';
            html += '<td><span class="vs-badge ' + statusClass(c.status) + '" data-field="status_label">'
                + esc(c.status_label || '') + '</span></td>';
            html += '<td data-field="actions">' + actionsHtml(c) + '</td></tr>';
            return html;
        }

        function mobileHtml(c) {
            var title = c.content_title || ('文章#' + (c.contentid || ''));
            var time = c.createtime_short || c.createtime || '';
            if (time.length >= 16) time = time.slice(0, 16);
            var pin = Number(c.ispinned) === 1 ? '[顶] ' : '';
            return '<div class="cmt-card"' + dataAttrs(c) + '>'
                + '<div class="cmt-card__text" data-field="body">' + esc(pin + c.body) + '</div>'
                + '<div class="cmt-card__meta">'
                + '<span class="cmt-card__meta-item">关联：<span data-field="content_title">' + esc(title) + '</span></span>'
                + '<span class="cmt-card__meta-item" data-field="nickname">' + esc(c.nickname) + '</span>'
                + '<span class="cmt-card__meta-item" data-field="email">' + esc(c.email) + '</span>'
                + '<span class="cmt-card__meta-item" data-field="createtime">' + esc(time) + '</span>'
                + '<span class="vs-badge ' + statusClass(c.status) + '" data-field="status_label">' + esc(c.status_label || '') + '</span>'
                + '</div><div class="cmt-card__actions" data-field="actions">' + actionsHtml(c) + '</div></div>';
        }

        function upsert(c) {
            if (!c || !c.id) return;
            if (c.createtime && !c.createtime_short && String(c.createtime).length >= 16) {
                c.createtime_short = String(c.createtime).slice(0, 16);
            }
            var pair = getPair(c.id);
            var d = desktopHtml(c);
            var m = mobileHtml(c);
            if (pair.desktop) pair.desktop.outerHTML = d;
            else if (listEl) listEl.insertAdjacentHTML('afterbegin', d);
            if (pair.mobile) pair.mobile.outerHTML = m;
            else if (mobileEl) mobileEl.insertAdjacentHTML('afterbegin', m);
            applyView();
        }

        function remove(id) {
            var pair = getPair(id);
            if (pair.desktop) pair.desktop.remove();
            if (pair.mobile) pair.mobile.remove();
            applyView();
        }

        function fromEl(el) {
            return {
                id: parseInt(el.getAttribute('data-comment-row'), 10) || 0,
                contentid: parseInt(el.getAttribute('data-contentid'), 10) || 0,
                content_title: el.getAttribute('data-content-title') || '',
                nickname: el.getAttribute('data-nickname') || '',
                email: el.getAttribute('data-email') || '',
                body: el.getAttribute('data-body') || '',
                reply: el.getAttribute('data-reply') || '',
                ispinned: parseInt(el.getAttribute('data-ispinned'), 10) || 0,
                status: parseInt(el.getAttribute('data-status'), 10) || 0,
                status_label: el.getAttribute('data-status-label') || '',
                avatar_url: el.getAttribute('data-avatar-url') || '',
                createtime_short: el.getAttribute('data-createtime') || ''
            };
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                if (addForm) addForm.reset();
                openOv(addOverlay);
            });
        }

        [replyOverlay, addOverlay].forEach(function (ov) {
            if (!ov) return;
            ov.addEventListener('click', function (e) {
                if (e.target.closest('[data-overlay-close]')) closeOv(ov);
            });
        });

        if (addForm) {
            addForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(addForm);
                var payload = { action: 'create' };
                fd.forEach(function (v, k) { payload[k] = v; });
                post(payload).then(function (res) {
                    if (!ok(res)) throw new Error(msg(res, '添加失败'));
                    window.VS.showMessage(msg(res, '已添加'), 'success');
                    closeOv(addOverlay);
                    if (res.comment) upsert(res.comment);
                }).catch(function (err) {
                    window.VS.showMessage(err.message || '添加失败', 'error');
                });
            });
        }

        if (replySaveBtn) {
            replySaveBtn.addEventListener('click', function () {
                var id = document.getElementById('adminCmtReplyId').value;
                var reply = document.getElementById('adminCmtReplyText').value || '';
                replySaveBtn.disabled = true;
                post({ action: 'set_reply', comment_id: id, reply: reply }).then(function (res) {
                    if (!ok(res)) throw new Error(msg(res, '保存失败'));
                    window.VS.showMessage(msg(res, '已保存'), 'success');
                    closeOv(replyOverlay);
                    if (res.comment) upsert(res.comment);
                }).catch(function (err) {
                    window.VS.showMessage(err.message || '保存失败', 'error');
                }).then(function () {
                    replySaveBtn.disabled = false;
                });
            });
        }

        page.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-cmt-act');
            if (!btn) return;
            var id = btn.getAttribute('data-comment-id');
            var pair = getPair(id);
            var src = pair.desktop || pair.mobile;
            if (!src) return;
            var data = fromEl(src);
            var act = btn.getAttribute('data-act');

            if (act === 'reply') {
                document.getElementById('adminCmtReplyId').value = String(id);
                document.getElementById('adminCmtReplyBodyView').textContent = data.body || '—';
                document.getElementById('adminCmtReplyEmailView').textContent = data.email || '—';
                document.getElementById('adminCmtReplyText').value = data.reply || '';
                openOv(replyOverlay);
                return;
            }
            if (act === 'pin') {
                var next = Number(data.ispinned) === 1 ? 0 : 1;
                post({ action: 'set_pinned', comment_id: id, ispinned: String(next) }).then(function (res) {
                    if (!ok(res)) throw new Error(msg(res, '操作失败'));
                    data.ispinned = next;
                    upsert(data);
                    window.VS.showMessage(msg(res, '已更新'), 'success');
                }).catch(function (err) {
                    window.VS.showMessage(err.message || '操作失败', 'error');
                });
                return;
            }
            if (act === 'approve' || act === 'reject') {
                var st = act === 'approve' ? 1 : 2;
                post({ action: 'set_status', comment_id: id, status: String(st) }).then(function (res) {
                    if (!ok(res)) throw new Error(msg(res, '操作失败'));
                    data.status = st;
                    data.status_label = res.status_label || (st === 1 ? '已通过' : '已拒绝');
                    upsert(data);
                    window.VS.showMessage(msg(res, '已更新'), 'success');
                }).catch(function (err) {
                    window.VS.showMessage(err.message || '操作失败', 'error');
                });
                return;
            }
            if (act === 'delete') {
                var ask = (window.VsModal && window.VsModal.confirm)
                    ? window.VsModal.confirm('确定删除该评论？', '删除评论', { danger: true })
                    : Promise.resolve(window.confirm('确定删除该评论？'));
                ask.then(function (yes) {
                    if (!yes) return;
                    post({ action: 'delete', comment_id: id }).then(function (res) {
                        if (!ok(res)) throw new Error(msg(res, '删除失败'));
                        remove(id);
                        window.VS.showMessage(msg(res, '已删除'), 'success');
                    }).catch(function (err) {
                        window.VS.showMessage(err.message || '删除失败', 'error');
                    });
                });
            }
        });

        if (searchInput) searchInput.addEventListener('input', function () { currentPage = 1; applyView(); });
        if (pageSizeEl) pageSizeEl.addEventListener('change', function () { currentPage = 1; applyView(); });
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage -= 1; applyView(); } });
        if (nextBtn) nextBtn.addEventListener('click', function () { currentPage += 1; applyView(); });
        if (pagerNumsEl) {
            pagerNumsEl.addEventListener('click', function (e) {
                var n = e.target.closest('[data-page]');
                if (!n) return;
                currentPage = parseInt(n.getAttribute('data-page'), 10) || 1;
                applyView();
            });
        }

        applyView();
    }

    boot();
})();
