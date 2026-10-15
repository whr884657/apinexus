/**
 * 文件：assets/js/admin-mailtpl.js
 * 作用：系统管理 · 邮箱模板（双 DOM + 搜索分页 + HTML/占位符编辑）
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
        var page = document.getElementById('mailtplPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('mailtplTableWrap');
        var listEl = document.getElementById('mailtplBody');
        var mobileEl = document.getElementById('mailtplMobile');
        var emptyEl = document.getElementById('mailtplEmpty');
        var searchEmptyEl = document.getElementById('mailtplSearchEmpty');
        var searchInput = document.getElementById('mailtplSearchInput');
        var pageSizeEl = document.getElementById('mailtplPageSize');
        var footerEl = document.getElementById('mailtplFooter');
        var pagerNumsEl = document.getElementById('mailtplPagerNums');
        var statsEl = document.getElementById('mailtplStats');
        var prevBtn = document.getElementById('mailtplPrevBtn');
        var nextBtn = document.getElementById('mailtplNextBtn');
        var overlay = document.getElementById('mailtplOverlay');
        var form = document.getElementById('mailtplForm');
        var addBtn = document.getElementById('mailtplAddBtn');
        var saveBtn = document.getElementById('mailtplSaveBtn');
        var formTitle = document.getElementById('mailtplFormTitle');
        var phBar = document.getElementById('mailtplPhBar');
        var editorRoot = document.getElementById('mailtplEditor');
        var previewToggle = document.getElementById('mailtplPreviewToggle');
        var previewSubjectEl = document.getElementById('mailtplPreviewSubject');
        var previewBodyEl = document.getElementById('mailtplPreviewBody');
        var aiFold = document.getElementById('mailtplAiFold');
        var aiFoldBar = document.getElementById('mailtplAiFoldBar');
        var aiCopyBtn = document.getElementById('mailtplAiCopyBtn');
        var aiBlock = document.getElementById('mailtplAiBlock');
        var currentPage = 1;
        var returnFocusEl = null;
        var phFocusEl = null;
        var listTotal = parseInt(page.getAttribute('data-content-total') || '0', 10) || 0;
        var previewTimer = null;

        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
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

        function renderPagerNums(totalPages) {
            if (window.VS && VS.applyLogsPager) {
                VS.applyLogsPager(currentPage, Math.max(1, totalPages), prevBtn, nextBtn, pagerNumsEl);
            }
        }

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
            if (window.VSPick) {
                VSPick.init(overlay);
            }
        }

        function closeOverlay() {
            if (!overlay) {
                return;
            }
            setPreviewMode(false);
            overlay.classList.remove('is-open');
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-overlay-open');
            if (returnFocusEl && typeof returnFocusEl.focus === 'function') {
                returnFocusEl.focus();
            }
            returnFocusEl = null;
        }

        function loadPhCatalog() {
            var el = document.getElementById('mailtplPhCatalog');
            if (!el) {
                return { items: [] };
            }
            try {
                var raw = JSON.parse(el.textContent || '{}');
                return {
                    items: Array.isArray(raw.items) ? raw.items : []
                };
            } catch (e) {
                return { items: [] };
            }
        }

        var phCatalog = loadPhCatalog();

        function escAttr(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;');
        }

        function escHtml(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function refreshPhBar() {
            if (!phBar) {
                return;
            }
            var items = phCatalog.items || [];
            var html = '';
            var j;
            var key;
            var label;
            var ph;
            for (j = 0; j < items.length; j++) {
                key = String((items[j] && items[j].key) || '').trim();
                label = String((items[j] && items[j].label) || key).trim();
                if (key === '' || !/^[a-z][a-z0-9]*$/.test(key)) {
                    continue;
                }
                ph = '{{' + key + '}}';
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-ph="'
                    + escAttr(ph) + '" title="' + escAttr(ph) + '">' + escHtml(label) + '</button>';
            }
            phBar.innerHTML = html;
        }

        function fillForm(row) {
            if (!form) {
                return;
            }
            form.content_id.value = row ? String(row.id || 0) : '0';
            form.title.value = row ? (row.title || '') : '';
            form.summary.value = row ? (row.summary || '') : '';
            form.body.value = row ? (row.body || '') : '';
            form.bindpage.value = row ? String(row.bindpage != null ? row.bindpage : 0) : '0';
            form.status.value = row ? String(row.status != null ? row.status : 1) : '1';
            if (formTitle) {
                formTitle.textContent = row && row.id ? '编辑模板' : '新建模板';
            }
            if (window.VSPick && VSPick.refresh) {
                if (form.bindpage) {
                    VSPick.refresh(form.bindpage);
                }
                if (form.status) {
                    VSPick.refresh(form.status);
                }
            }
            refreshPhBar();
            setPreviewMode(false);
            if (aiFold) {
                aiFold.classList.remove('is-open');
                if (aiFoldBar) {
                    aiFoldBar.setAttribute('aria-expanded', 'false');
                }
                var aiBody = document.getElementById('mailtplAiFoldBody');
                if (aiBody) {
                    aiBody.setAttribute('hidden', '');
                }
            }
            schedulePreview();
        }

        function rowFromEl(el) {
            return {
                id: parseInt(el.getAttribute('data-content-row'), 10) || 0,
                title: el.getAttribute('data-title') || '',
                summary: el.getAttribute('data-summary') || '',
                body: '',
                bindpage: parseInt(el.getAttribute('data-bindpage'), 10) || 0,
                status: parseInt(el.getAttribute('data-status'), 10) || 0
            };
        }

        function loadItem(id) {
            var fd = new FormData();
            fd.append('action', 'get');
            fd.append('content_id', String(id));
            return VS.postForm(fd, window.location.href).then(function (res) {
                if (!ok(res) || !res.item) {
                    throw new Error(msg(res, '加载失败'));
                }
                return res.item;
            });
        }

        function resolvePhTarget() {
            var ids = (phBar && phBar.getAttribute('data-ph-targets') || '').split(',');
            var i;
            var el;
            if (phFocusEl && phFocusEl.isConnected) {
                return phFocusEl;
            }
            for (i = 0; i < ids.length; i++) {
                el = document.getElementById(String(ids[i] || '').trim());
                if (el) {
                    return el;
                }
            }
            return form && form.body ? form.body : null;
        }

        function insertPlaceholder(ph) {
            var ta = resolvePhTarget();
            if (!ta || !ph) {
                return;
            }
            var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : String(ta.value || '').length;
            var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : start;
            var v = String(ta.value || '');
            ta.value = v.slice(0, start) + ph + v.slice(end);
            var pos = start + ph.length;
            ta.focus();
            if (typeof ta.setSelectionRange === 'function') {
                ta.setSelectionRange(pos, pos);
            }
            phFocusEl = ta;
            schedulePreview();
        }

        function demoReplace(text) {
            var map = {
                browsertitle: '示例浏览器标题',
                systemname: 'ApiNexus',
                siteurl: 'https://example.com',
                username: '示例用户',
                userid: '10001',
                useremail: 'user@example.com',
                userrole: '普通用户',
                useravatar: '<span style="display:inline-block;width:48px;height:48px;border-radius:50%;background:#e5e7eb;"></span>',
                useravatarurl: 'https://example.com/avatar.png',
                balance: '12.5',
                date: '2026-10-15',
                time: '20:00:00',
                datetime: '2026-10-15 20:00:00',
                year: '2026',
                month: '10',
                day: '15',
                weekday: '星期三',
                hour: '20',
                minute: '00',
                second: '00',
                apiname: '示例接口',
                apiid: '88',
                apiurl: 'https://example.com/detail?id=88',
                submitter: '开发者A',
                rejectreason: '资料不完整',
                linkname: '示例友链站',
                linkurl: 'https://friend.example.com',
                linkcontact: 'mail@friend.example.com',
                linkid: '9',
                feedbackid: '31',
                feedbackbody: '这是一段反馈示例。',
                feedbackreply: '已处理，请查收。',
                commentid: '55',
                articletitle: '示例文章',
                articleurl: 'https://example.com/articles/1',
                commenter: '评论用户',
                commentbody: '这是一条评论示例。',
                quotebody: '原评论内容',
                isquote: '否',
                adminreply: '管理员回复示例',
                notifytype: '评论被引用',
                keyname: '测试令牌',
                keyid: '7',
                keyquota: '100',
                keyquotafallback: '是',
                pointsneed: '3',
                pointscredit: '50',
                orderno: 'ORD20261015001',
                orderincome: '¥10.00',
                orderincometoday: '¥88.00',
                applyid: '12',
                applyreason: '希望投稿接口',
                applicant: '申请人C',
                handlereply: '审核通过，欢迎加入。',
                otpcode: '123456',
                otpttl: '5',
                otpaction: '登录'
            };
            return String(text || '').replace(/\{\{\s*([^}]+?)\s*\}\}/g, function (m, key) {
                var k = String(key || '').trim();
                if (Object.prototype.hasOwnProperty.call(map, k)) {
                    return map[k];
                }
                return '';
            });
        }

        function sanitizePreviewHtml(html) {
            // 先文本层剥危险块/事件，避免 detached innerHTML 解析时 onerror 等抢先执行
            var raw = String(html || '')
                .replace(/<(script|iframe|object|embed|form|svg|math|link|meta|base|style)(\s[^>]*)?>[\s\S]*?<\/\1>/gi, '')
                .replace(/<(script|iframe|object|embed|form|svg|math|link|meta|base|style)(\s[^>]*)?\/?>/gi, '')
                .replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '')
                .replace(/\s(href|src|xlink:href)\s*=\s*(['"])\s*(javascript|vbscript|data):[^'"]*\2/gi, '')
                .replace(/\s(href|src|xlink:href)\s*=\s*(javascript|vbscript|data):[^\s>]*/gi, '');
            var wrap = document.createElement('div');
            wrap.innerHTML = raw;
            var kill = wrap.querySelectorAll('script,iframe,object,embed,form,svg,math,link,meta,base,style');
            var i;
            for (i = 0; i < kill.length; i++) {
                if (kill[i].parentNode) {
                    kill[i].parentNode.removeChild(kill[i]);
                }
            }
            var all = wrap.querySelectorAll('*');
            for (i = 0; i < all.length; i++) {
                var el = all[i];
                var attrs = el.attributes ? Array.prototype.slice.call(el.attributes) : [];
                var j;
                for (j = 0; j < attrs.length; j++) {
                    var name = String(attrs[j].name || '').toLowerCase();
                    var val = String(attrs[j].value || '');
                    if (name.indexOf('on') === 0 || /^(javascript|vbscript|data):/i.test(val.trim())) {
                        el.removeAttribute(attrs[j].name);
                    }
                }
            }
            return wrap.innerHTML;
        }

        function updatePreview() {
            if (!form) {
                return;
            }
            var subject = demoReplace(form.summary ? form.summary.value : '');
            var body = demoReplace(form.body ? form.body.value : '');
            if (previewSubjectEl) {
                previewSubjectEl.textContent = subject.trim() !== '' ? subject : '—';
            }
            if (previewBodyEl) {
                previewBodyEl.innerHTML = '<div class="vs-mailtpl-preview-card__html">'
                    + sanitizePreviewHtml(body) + '</div>';
            }
        }

        function schedulePreview() {
            if (previewTimer) {
                clearTimeout(previewTimer);
            }
            previewTimer = setTimeout(updatePreview, 120);
        }

        function setPreviewMode(on) {
            if (!editorRoot) {
                return;
            }
            editorRoot.setAttribute('data-mode', on ? 'preview' : 'edit');
            if (previewToggle) {
                previewToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
                previewToggle.textContent = on ? '返回编辑' : '预览';
            }
            if (on) {
                updatePreview();
            }
        }

        if (form) {
            form.addEventListener('focusin', function (e) {
                var t = e.target;
                if (!t || !t.id) {
                    return;
                }
                if (t.id === 'mailtplSummary' || t.id === 'mailtplBodyText' || t.id === 'mailtplTitle') {
                    phFocusEl = t;
                }
            });
        }

        if (phBar) {
            // mousedown 阻止失焦，否则 selectionStart 丢失导致「相邻第二次插入无效」
            phBar.addEventListener('mousedown', function (e) {
                if (e.target.closest('[data-ph]')) {
                    e.preventDefault();
                }
            });
            phBar.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ph]');
                if (!btn) {
                    return;
                }
                insertPlaceholder(btn.getAttribute('data-ph') || '');
            });
        }

        if (form && form.bindpage) {
            form.bindpage.addEventListener('change', function () {
                refreshPhBar();
                schedulePreview();
            });
        }
        if (form) {
            form.addEventListener('input', function (e) {
                var t = e.target;
                if (!t || !t.id) {
                    return;
                }
                if (t.id === 'mailtplSummary' || t.id === 'mailtplBodyText') {
                    schedulePreview();
                }
            });
        }
        refreshPhBar();
        schedulePreview();

        if (previewToggle) {
            previewToggle.addEventListener('click', function () {
                var on = editorRoot && editorRoot.getAttribute('data-mode') === 'preview';
                setPreviewMode(!on);
            });
        }

        if (aiFoldBar && aiFold) {
            aiFoldBar.addEventListener('click', function () {
                var open = !aiFold.classList.contains('is-open');
                aiFold.classList.toggle('is-open', open);
                aiFoldBar.setAttribute('aria-expanded', open ? 'true' : 'false');
                var body = document.getElementById('mailtplAiFoldBody');
                if (body) {
                    if (open) {
                        body.removeAttribute('hidden');
                    } else {
                        body.setAttribute('hidden', '');
                    }
                }
            });
        }

        if (aiCopyBtn && aiBlock) {
            aiCopyBtn.addEventListener('click', function () {
                var text = aiBlock.textContent || '';
                var done = function () {
                    window.VS.showMessage('已复制占位符说明', 'success');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        window.VS.showMessage('复制失败，请手动选中复制', 'error');
                    });
                } else {
                    window.VS.showMessage('请手动选中代码块复制', 'warning');
                }
            });
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
                // 完整 HTML 易被 WAF 拦成 403，前端只见 invalid_json；body 须 VS64B 传输（E481）
                try {
                    if (form.body && window.VS && typeof window.VS.encodeTransportField === 'function') {
                        fd.set('body', window.VS.encodeTransportField(form.body.value || ''));
                    }
                } catch (encErr) {
                    window.VS.showMessage((encErr && encErr.message) ? encErr.message : '正文编码失败', 'error');
                    return;
                }
                saveBtn.disabled = true;
                VS.postForm(fd, window.location.href).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '保存失败'));
                    }
                    window.VS.showMessage(msg(res, '已保存'), 'success');
                    closeOverlay();
                    applyView();
                }).catch(function (err) {
                    var em = (err && err.message) ? String(err.message) : '保存失败';
                    if (em === 'invalid_json') {
                        em = '保存被拦截或返回异常，请缩短正文或刷新后重试';
                    }
                    window.VS.showMessage(em, 'error');
                }).then(function () {
                    saveBtn.disabled = false;
                });
            });
        }

        page.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-mailtpl-act');
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
                loadItem(id).then(function (item) {
                    fillForm(item);
                    openOverlay();
                }).catch(function (err) {
                    window.VS.showMessage((err && err.message) ? err.message : '加载失败', 'error');
                });
                return;
            }

            if (act === 'off' || act === 'on') {
                var nextStatus = act === 'off' ? 2 : 1;
                var fdStatus = new FormData();
                fdStatus.append('action', 'set_status');
                fdStatus.append('content_id', String(id));
                fdStatus.append('status', String(nextStatus));
                VS.postForm(fdStatus, window.location.href).then(function (res) {
                    if (!ok(res)) {
                        throw new Error(msg(res, '操作失败'));
                    }
                    window.VS.showMessage(msg(res, '已更新'), 'success');
                    applyView();
                }).catch(function (err) {
                    window.VS.showMessage((err && err.message) ? err.message : '操作失败', 'error');
                });
                return;
            }

            if (act === 'delete') {
                var ask = (window.VsModal && typeof window.VsModal.confirm === 'function')
                    ? window.VsModal.confirm('确定删除该邮箱模板？', '删除确认', { danger: true })
                    : Promise.resolve(false);
                ask.then(function (yes) {
                    if (!yes) {
                        return;
                    }
                    var fdDel = new FormData();
                    fdDel.append('action', 'delete');
                    fdDel.append('content_id', String(id));
                    VS.postForm(fdDel, window.location.href).then(function (res) {
                        if (!ok(res)) {
                            throw new Error(msg(res, '删除失败'));
                        }
                        window.VS.showMessage(msg(res, '已删除'), 'success');
                        applyView();
                    }).catch(function (err) {
                        window.VS.showMessage((err && err.message) ? err.message : '删除失败', 'error');
                    });
                });
            }
        });

        if (searchInput) {
            var searchTimer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    currentPage = 1;
                    applyView();
                }, 280);
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

        if (pageSizeEl && !pageSizeEl.value) {
            pageSizeEl.value = String(defaultPageSize());
        }
        renderPagerNums(Math.max(1, Math.ceil(listTotal / getPageSize()) || 1));
    }

    boot();
})();
