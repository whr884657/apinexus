/**
 * 文件：assets/js/admin-content.js
 * 作用：公告（站外/站内）/ 文章管理（双 DOM + 搜索分页 + Markdown 编辑）
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
        var bindWrap = document.getElementById('contentBindPageWrap');
        var bindSelect = document.getElementById('contentBindPage');
        var kindInput = document.getElementById('contentKind');
        var kindSeg = document.getElementById('contentKindSeg');
        var audModeInput = document.getElementById('contentAudMode');
        var audModeWrap = document.getElementById('contentAudModeWrap');
        var audModeSeg = document.getElementById('contentAudModeSeg')
            || document.getElementById('contentAudModeTabs');
        var msgReady = page.getAttribute('data-msg-ready') !== '0';
        var mode = page.getAttribute('data-mode') || 'article';
        var isAnnouncement = mode === 'announcement';
        var isMessage = mode === 'message';
        var currentPage = 1;
        var returnFocusEl = null;

        function kindLabel() {
            if (isMessage) {
                return '站内公告';
            }
            return isAnnouncement ? '公告' : '文章';
        }

        function currentFormKind() {
            if (kindInput) {
                return parseInt(kindInput.value, 10) || 0;
            }
            return isMessage ? 2 : 0;
        }

        function setKind(val, opts) {
            opts = opts || {};
            var n = parseInt(val, 10);
            if (n !== 2) {
                n = 0;
            }
            if (n === 2 && !msgReady && !opts.force) {
                n = 0;
            }
            if (kindInput) {
                kindInput.value = String(n);
            }
            if (kindSeg) {
                var kbtns = kindSeg.querySelectorAll('[data-kind]');
                Array.prototype.forEach.call(kbtns, function (btn) {
                    var v = parseInt(btn.getAttribute('data-kind'), 10) || 0;
                    var on = v === n;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }
            var showAud = (isAnnouncement && n === 2) || isMessage;
            if (audModeWrap) {
                audModeWrap.hidden = !showAud;
            }
            if (showAud && !opts.skipAudReset) {
                setAudMode(opts.audmode != null ? opts.audmode : (audModeInput ? audModeInput.value : 1));
            }
        }

        function setAudMode(val) {
            var n = parseInt(val, 10) || 1;
            if (n !== 1 && n !== 2 && n !== 3) {
                n = 1;
            }
            if (audModeInput) {
                audModeInput.value = String(n);
            }
            if (audModeSeg) {
                var btns = audModeSeg.querySelectorAll('[data-audmode]');
                Array.prototype.forEach.call(btns, function (btn) {
                    var v = parseInt(btn.getAttribute('data-audmode'), 10) || 0;
                    var on = v === n;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                    btn.disabled = false;
                });
            }
        }

        if (kindSeg) {
            kindSeg.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-kind]');
                if (!btn || btn.disabled) {
                    return;
                }
                setKind(btn.getAttribute('data-kind'), { skipAudReset: false });
            });
        }

        if (audModeSeg) {
            audModeSeg.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-audmode]');
                if (!btn || btn.disabled) {
                    return;
                }
                // 置顶时仍可改受众（只影响取消置顶后的可见范围）
                setAudMode(btn.getAttribute('data-audmode'));
            });
        }

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

        function aboutBound() {
            return page.getAttribute('data-about-bound') === '1';
        }

        function setAboutBound(flag) {
            page.setAttribute('data-about-bound', flag ? '1' : '0');
        }

        function syncAboutBoundFromRes(res) {
            if (res && res.about_bound != null) {
                setAboutBound(Number(res.about_bound) === 1);
            }
        }

        function statusBadgeClass(status) {
            var n = Number(status);
            if (n === 1) {
                return 'vs-badge--success';
            }
            if (n === 2) {
                return 'vs-badge--error';
            }
            return 'vs-badge--default';
        }

        function syncBindWrap(row) {
            if (!bindWrap || isAnnouncement || isMessage) {
                return;
            }
            var boundSelf = row && Number(row.bindpage) === 1;
            var show = boundSelf || !aboutBound();
            bindWrap.hidden = !show;
            if (bindSelect) {
                bindSelect.value = boundSelf ? '1' : '0';
                if (window.VSPick && VSPick.refresh) {
                    VSPick.refresh(bindSelect);
                }
            }
        }

        function defaultPageSize() {
            return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
            return n > 0 ? n : defaultPageSize();
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

        function renderPagerNums(totalPages) {
            if (window.VS && VS.applyLogsPager) {
                VS.applyLogsPager(currentPage, Math.max(1, totalPages), prevBtn, nextBtn, pagerNumsEl);
            }
        }

        var listTotal = parseInt(page.getAttribute('data-content-total') || '0', 10) || 0;

        function applyView() {
            var fd = new FormData();
            fd.append('action', 'list');
            fd.append('q', searchInput ? String(searchInput.value || '').trim() : '');
            fd.append('page', String(currentPage));
            fd.append('pagesize', String(getPageSize()));
            VS.postForm(fd, window.location.href).then(function (data) {
                if (!ok(data)) {
                    throw new Error(msg(data, '加载失败'));
                }
                if (data.page) {
                    currentPage = parseInt(data.page, 10) || currentPage;
                }
                listTotal = parseInt(data.total, 10) || 0;
                page.setAttribute('data-content-total', String(listTotal));
                if (listEl) {
                    listEl.innerHTML = data.desktop_html || '';
                }
                if (mobileEl) {
                    mobileEl.innerHTML = data.mobile_html || '';
                }
                var q = searchInput ? String(searchInput.value || '').trim() : '';
                var filtered = q !== '';
                var pageItems = listEl ? listEl.querySelectorAll('tr[data-content-row]').length : 0;
                var hasAny = listTotal > 0;
                if (emptyEl) {
                    emptyEl.hidden = hasAny || filtered;
                }
                if (searchEmptyEl) {
                    searchEmptyEl.hidden = !(filtered && pageItems === 0);
                }
                if (tableWrapEl) {
                    tableWrapEl.hidden = pageItems === 0;
                }
                if (mobileEl) {
                    mobileEl.hidden = pageItems === 0;
                }
                if (footerEl) {
                    footerEl.hidden = listTotal === 0 && !filtered;
                }
                if (statsEl) {
                    statsEl.textContent = '共 ' + listTotal + ' 条';
                }
                var totalPages = Math.max(1, Math.ceil(listTotal / getPageSize()) || 1);
                renderPagerNums(listTotal === 0 ? 1 : totalPages);
            }).catch(function (err) {
                window.VS.showMessage((err && err.message) ? err.message : '加载失败', 'error');
            });
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
                    ? ('编辑' + kindLabel())
                    : ('发布' + kindLabel());
            }
            syncBindWrap(row);
            if (isAnnouncement || isMessage) {
                var rowKind = isMessage
                    ? 2
                    : (row && row.kind != null ? parseInt(row.kind, 10) : 0);
                if (rowKind !== 2) {
                    rowKind = 0;
                }
                setKind(rowKind, {
                    force: true,
                    skipAudReset: true,
                    audmode: row && row.audmode != null ? row.audmode : 1
                });
                if (rowKind === 2 || isMessage) {
                    setAudMode(row && row.audmode != null ? row.audmode : 1);
                }
            }
            if (form.body && form.body.dispatchEvent) {
                form.body.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        function rowFromEl(el) {
            return {
                id: parseInt(el.getAttribute('data-content-row'), 10) || 0,
                kind: parseInt(el.getAttribute('data-kind'), 10) || 0,
                kind_label: el.getAttribute('data-kind-label') || '',
                title: el.getAttribute('data-title') || '',
                summary: el.getAttribute('data-summary') || '',
                body: el.getAttribute('data-body') || '',
                cover: el.getAttribute('data-cover') || '',
                coverlayout: parseInt(el.getAttribute('data-coverlayout'), 10) || 0,
                status: parseInt(el.getAttribute('data-status'), 10) || 0,
                status_label: el.getAttribute('data-status-label') || '已发布',
                bindpage: parseInt(el.getAttribute('data-bindpage'), 10) || 0,
                ispinned: parseInt(el.getAttribute('data-ispinned'), 10) || 0,
                ispopup: parseInt(el.getAttribute('data-ispopup'), 10) || 0,
                audmode: parseInt(el.getAttribute('data-audmode'), 10) || 0,
                audmode_label: el.getAttribute('data-audmode-label') || '',
                views: parseInt(el.getAttribute('data-views'), 10) || 0,
                username: el.getAttribute('data-username') || '—',
                author_avatar: el.getAttribute('data-author-avatar') || '',
                createtime: el.getAttribute('data-createtime') || ''
            };
        }

        function dataAttrs(item) {
            var search = String(item.title || '') + ' ' + String(item.username || '')
                + ' ' + String(item.createtime || '') + ' #' + item.id
                + ' ' + String(item.kind_label || '')
                + ' ' + String(item.audmode_label || '');
            return ' data-content-row="' + item.id + '"'
                + ' data-search="' + esc(search.toLowerCase()) + '"'
                + ' data-kind="' + (item.kind != null ? item.kind : 0) + '"'
                + ' data-kind-label="' + esc(item.kind_label || '') + '"'
                + ' data-title="' + esc(item.title) + '"'
                + ' data-summary="' + esc(item.summary || '') + '"'
                + ' data-body="' + esc(item.body || '') + '"'
                + ' data-cover="' + esc(item.cover || '') + '"'
                + ' data-coverlayout="' + (item.coverlayout != null ? item.coverlayout : 0) + '"'
                + ' data-status="' + (item.status != null ? item.status : 1) + '"'
                + ' data-status-label="' + esc(item.status_label || '已发布') + '"'
                + ' data-bindpage="' + (item.bindpage != null ? item.bindpage : 0) + '"'
                + ' data-ispinned="' + (item.ispinned || 0) + '"'
                + ' data-ispopup="' + (item.ispopup || 0) + '"'
                + ' data-audmode="' + (item.audmode || 0) + '"'
                + ' data-audmode-label="' + esc(item.audmode_label || '') + '"'
                + ' data-views="' + (item.views || 0) + '"'
                + ' data-username="' + esc(item.username || '—') + '"'
                + ' data-author-avatar="' + esc(item.author_avatar || '') + '"'
                + ' data-createtime="' + esc(item.createtime || '') + '"';
        }

        function actionsHtml(item) {
            var html = '<div class="action-btns">';
            var siteMsg = isMessage || (isAnnouncement && Number(item.kind) === 2);
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="edit">编辑</button>';
            if (isAnnouncement || isMessage) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-content-act" data-act="pin">'
                    + (Number(item.ispinned) === 1 ? '取消置顶' : '置顶') + '</button>';
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="popup">'
                    + (Number(item.ispopup) === 1 ? '取消弹窗' : '设为弹窗') + '</button>';
            } else {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-content-act" data-act="pin">'
                    + (Number(item.ispinned) === 1 ? '取消置顶' : '置顶') + '</button>';
            }
            if (!isAnnouncement || siteMsg) {
                if (Number(item.status) === 1) {
                    html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="hide">隐藏</button>';
                } else {
                    html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-content-act" data-act="show">显示</button>';
                }
            }
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-content-act" data-act="delete">删除</button>';
            html += '</div>';
            return html;
        }

        function titleHtml(item) {
            var html = '<div class="content-title-cell">';
            html += '<span class="content-title-cell__text" data-field="title">' + esc(item.title) + '</span>';
            if (!isAnnouncement && !isMessage && Number(item.bindpage) === 1) {
                html += '<span class="vs-badge vs-badge--info" data-field="bind_label">关于</span>';
            }
            if (!isAnnouncement && !isMessage && Number(item.ispinned) === 1) {
                html += '<span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>';
            }
            html += '</div>';
            return html;
        }

        function desktopHtml(item) {
            var time = item.createtime || '—';
            var html = '<tr' + dataAttrs(item) + '>';
            html += '<td>' + titleHtml(item) + '</td>';
            if (isAnnouncement) {
                var isSite = Number(item.kind) === 2;
                html += '<td><span class="vs-badge ' + (isSite ? 'vs-badge--info' : 'vs-badge--default')
                    + '" data-field="kind_label">' + esc(item.kind_label || (isSite ? '站内公告' : '站外公告'))
                    + '</span></td>';
                html += '<td><span class="vs-badge ' + (isSite ? 'vs-badge--info' : 'vs-badge--default')
                    + '" data-field="audmode_label">'
                    + esc(isSite ? (item.audmode_label || '全部用户') : '—') + '</span></td>';
                html += '<td><span class="content-time-cell" data-field="createtime">' + esc(time) + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.ispinned) === 1 ? 'vs-badge--warning' : 'vs-badge--default')
                    + '" data-field="pin_label">' + (Number(item.ispinned) === 1 ? '置顶' : '—') + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.ispopup) === 1 ? 'vs-badge--info' : 'vs-badge--default')
                    + '" data-field="popup_label">' + (Number(item.ispopup) === 1 ? '弹窗' : '—') + '</span></td>';
            } else if (isMessage) {
                html += '<td><span class="vs-badge vs-badge--info" data-field="audmode_label">'
                    + esc(item.audmode_label || '全部用户') + '</span></td>';
                html += '<td><span class="content-time-cell" data-field="createtime">' + esc(time) + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.ispinned) === 1 ? 'vs-badge--warning' : 'vs-badge--default')
                    + '" data-field="pin_label">' + (Number(item.ispinned) === 1 ? '置顶' : '—') + '</span></td>';
                html += '<td><span class="vs-badge ' + (Number(item.ispopup) === 1 ? 'vs-badge--info' : 'vs-badge--default')
                    + '" data-field="popup_label">' + (Number(item.ispopup) === 1 ? '弹窗' : '—') + '</span></td>';
                html += '<td><span class="vs-badge ' + statusBadgeClass(item.status)
                    + '" data-field="status_label">' + esc(item.status_label || '已发布') + '</span></td>';
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
                html += '<td><span class="vs-badge ' + statusBadgeClass(item.status)
                    + '" data-field="status_label">' + esc(item.status_label || '已发布') + '</span></td>';
            }
            html += '<td class="vs-col-actions vs-content-actions-cell" data-field="actions">' + actionsHtml(item) + '</td></tr>';
            return html;
        }

        function mobileHtml(item) {
            var cls = isAnnouncement || isMessage ? 'ann-card' : 'art-card';
            var time = item.createtime || '—';
            var html = '<div class="' + cls + '"' + dataAttrs(item) + '>';
            html += '<div class="' + cls + '__header">';
            html += '<span class="' + cls + '__title" data-field="title">' + esc(item.title) + '</span>';
            html += '<div class="' + cls + '__tags">';
            if (!isAnnouncement && !isMessage && Number(item.bindpage) === 1) {
                html += '<span class="vs-badge vs-badge--info" data-field="bind_label">关于</span>';
            }
            if (!isAnnouncement && !isMessage && Number(item.ispinned) === 1) {
                html += '<span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>';
            }
            if (isAnnouncement) {
                html += '<span class="vs-badge ' + (Number(item.kind) === 2 ? 'vs-badge--info' : 'vs-badge--default')
                    + '" data-field="kind_label">'
                    + esc(item.kind_label || (Number(item.kind) === 2 ? '站内公告' : '站外公告'))
                    + '</span>';
                if (Number(item.kind) === 2) {
                    html += '<span class="vs-badge vs-badge--info" data-field="audmode_label">'
                        + esc(item.audmode_label || '全部用户') + '</span>';
                }
            }
            if (isMessage) {
                html += '<span class="vs-badge vs-badge--info" data-field="audmode_label">'
                    + esc(item.audmode_label || '全部用户') + '</span>';
            }
            if (isAnnouncement || isMessage) {
                if (Number(item.ispinned) === 1) {
                    html += '<span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>';
                }
                if (Number(item.ispopup) === 1) {
                    html += '<span class="vs-badge vs-badge--info" data-field="popup_label">弹窗</span>';
                }
            }
            if (!isAnnouncement || isMessage || Number(item.kind) === 2) {
                html += '<span class="vs-badge ' + statusBadgeClass(item.status)
                    + '" data-field="status_label">' + esc(item.status_label || '已发布') + '</span>';
            }
            html += '</div></div>';
            if (isAnnouncement || isMessage) {
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
            applyView();
        }

        function removePair(id) {
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
                if (isAnnouncement || isMessage) {
                    var saveKind = isMessage ? 2 : currentFormKind();
                    fd.set('kind', String(saveKind));
                    if (saveKind === 2) {
                        fd.set('audmode', String(audModeInput ? (audModeInput.value || '1') : '1'));
                    } else {
                        fd.set('audmode', '0');
                    }
                } else if (bindSelect && bindWrap && !bindWrap.hidden) {
                    fd.set('bindpage', String(bindSelect.value || '0'));
                } else {
                    fd.set('bindpage', '0');
                }
                saveBtn.disabled = true;
                post(fd).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '保存失败'));
                    }
                    window.VS.showMessage(msg(res, '已保存'), 'success');
                    closeOverlay();
                    syncAboutBoundFromRes(res);
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

            if (act === 'hide' || act === 'show') {
                var nextStatus = act === 'hide' ? 2 : 1;
                var fdStatus = new FormData();
                fdStatus.append('action', 'set_status');
                fdStatus.append('content_id', String(id));
                fdStatus.append('status', String(nextStatus));
                post(fdStatus).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '操作失败'));
                    }
                    if (res.item) {
                        upsertRow(res.item);
                    } else {
                        data.status = nextStatus;
                        data.status_label = nextStatus === 1 ? '已发布' : '已隐藏';
                        upsertRow(data);
                    }
                    window.VS.showMessage(msg(res, '已更新'), 'success');
                }).catch(function (err) {
                    window.VS.showMessage((err && err.message) ? err.message : '操作失败', 'error');
                });
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
                    if (res.item) {
                        upsertRow(res.item);
                    } else {
                        data.ispinned = nextPin;
                        upsertRow(data);
                    }
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
                    ? window.VsModal.confirm('确定删除该' + kindLabel() + '？', '删除确认', { danger: true })
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
                        syncAboutBoundFromRes(res);
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
