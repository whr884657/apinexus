/**
 * 文件：assets/js/user-messages.js
 * 作用：用户中心 · 全部站内公告（搜索 + 横幅列表 + 底部分页；详情走 VSUserNotify / VsModal）
 */
(function () {
    'use strict';

    var TITLE_MAX = 36;
    var pageRoot = document.getElementById('ucMsgPage');
    if (!pageRoot) {
        return;
    }

    var endpoint = pageRoot.getAttribute('data-endpoint') || '';
    var listEl = document.getElementById('ucMsgList');
    var emptyEl = document.getElementById('ucMsgEmpty');
    var inputEl = document.getElementById('ucMsgSearchInput');
    var btnEl = document.getElementById('ucMsgSearchBtn');
    var footer = document.getElementById('ucMsgFooter');
    var pageSizeEl = document.getElementById('ucMsgPageSize');
    var prevBtn = document.getElementById('ucMsgPrevBtn');
    var nextBtn = document.getElementById('ucMsgNextBtn');
    var pagerNums = document.getElementById('ucMsgPagerNums');
    var totalEl = document.getElementById('ucMsgTotal');

    var page = 1;
    var totalCount = 0;
    var busy = false;
    var loadSeq = 0;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtTime(t) {
        t = String(t || '');
        return t.length >= 16 ? t.slice(0, 16) : (t || '—');
    }

    function truncTitle(title) {
        title = String(title || '（无标题）');
        if (typeof title.length === 'number' && title.length > TITLE_MAX) {
            return title.slice(0, TITLE_MAX) + '…';
        }
        return title;
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if (!n || n < 1) {
            n = 20;
        }
        return Math.min(50, n);
    }

    function pagerTotalPages() {
        return Math.max(1, Math.ceil((totalCount || 0) / getPageSize()) || 1);
    }

    function renderPager() {
        if (footer) {
            footer.hidden = totalCount <= 0;
        }
        if (totalEl) {
            totalEl.textContent = '共 ' + (totalCount || 0) + ' 条';
        }
        if (window.VS && typeof VS.applyLogsPager === 'function') {
            VS.applyLogsPager(page, pagerTotalPages(), prevBtn, nextBtn, pagerNums);
        }
    }

    function render(items) {
        items = Array.isArray(items) ? items : [];
        if (!listEl) {
            return;
        }
        if (items.length === 0) {
            listEl.innerHTML = '';
            listEl.hidden = true;
            if (emptyEl) {
                emptyEl.hidden = false;
            }
            return;
        }
        if (emptyEl) {
            emptyEl.hidden = true;
        }
        listEl.hidden = false;
        var html = '';
        items.forEach(function (it) {
            var id = parseInt(it && it.id, 10) || 0;
            if (id <= 0) {
                return;
            }
            var full = String(it.title || '（无标题）');
            var pin = Number(it.ispinned) === 1;
            html += '<div class="uc-msg-banner" role="listitem" data-msg-id="' + esc(id) + '">';
            html += '<div class="uc-msg-banner__main">';
            html += '<span class="uc-msg-banner__title" title="' + esc(full) + '">'
                + esc(truncTitle(full)) + '</span>';
            if (pin) {
                html += '<span class="uc-msg-banner__pin">置顶</span>';
            }
            html += '<span class="uc-msg-banner__time">' + esc(fmtTime(it.createtime)) + '</span>';
            html += '</div>';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline uc-msg-banner__view"'
                + ' data-msg-id="' + esc(id) + '">查看</button>';
            html += '</div>';
        });
        listEl.innerHTML = html;
    }

    function openDetail(id) {
        id = parseInt(id, 10) || 0;
        if (id <= 0) {
            return;
        }
        if (window.VSUserNotify && typeof window.VSUserNotify.openDetail === 'function') {
            window.VSUserNotify.openDetail(id);
            return;
        }
        if (!endpoint || !window.VS || typeof VS.postForm !== 'function' || !window.VsModal) {
            if (window.VS && VS.showMessage) {
                VS.showMessage('弹窗组件未就绪', 'error');
            }
            return;
        }
        var fd = new FormData();
        fd.append('action', 'detail');
        fd.append('id', String(id));
        VS.postForm(fd, endpoint).then(function (res) {
            if (!res || Number(res.code) !== 1) {
                throw new Error((res && res.msg) ? res.msg : '加载失败');
            }
            var item = (res.data && res.data.item) || res.item || null;
            if (!item) {
                throw new Error('公告不存在或无权查看');
            }
            var bodyHtml = item.body_html
                ? String(item.body_html)
                : ('<pre class="uc-msg-detail__plain">' + esc(item.body || '') + '</pre>');
            VsModal.open({
                title: item.title || '站内公告',
                html: '<div class="uc-msg-detail"><div class="uc-msg-detail__body markdown-body vs-md-body">'
                    + bodyHtml + '</div>'
                    + '<div class="uc-msg-detail__foot"><span class="uc-msg-detail__time">'
                    + esc(fmtTime(item.createtime)) + '</span></div></div>',
                size: 'lg',
                closeOnOverlay: true,
                closeOnEscape: true,
                buttons: [{ text: '关闭', primary: true, action: function () { VsModal.close(true); } }]
            });
        }).catch(function (err) {
            if (window.VS && VS.showMessage) {
                VS.showMessage((err && err.message) ? err.message : '加载失败', 'error');
            }
        });
    }

    function setControlsDisabled(disabled) {
        if (pageSizeEl) {
            pageSizeEl.disabled = !!disabled;
        }
        if (btnEl) {
            btnEl.disabled = !!disabled;
        }
        if (inputEl) {
            inputEl.disabled = !!disabled;
        }
    }

    function loadList() {
        if (!endpoint || !window.VS || typeof VS.postForm !== 'function') {
            return;
        }
        if (busy) {
            return;
        }
        busy = true;
        var seq = ++loadSeq;
        setControlsDisabled(true);
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('q', inputEl ? String(inputEl.value || '').trim() : '');
        fd.append('page', String(page));
        fd.append('pagesize', String(getPageSize()));
        VS.postForm(fd, endpoint).then(function (res) {
            if (seq !== loadSeq) {
                return;
            }
            busy = false;
            setControlsDisabled(false);
            if (!res || Number(res.code) !== 1) {
                throw new Error((res && res.msg) ? res.msg : '加载失败');
            }
            var items = res.items || (res.data && res.data.items) || [];
            totalCount = parseInt(res.total != null ? res.total : (res.data && res.data.total), 10) || 0;
            var serverPage = parseInt(res.page != null ? res.page : (res.data && res.data.page), 10) || page;
            if (serverPage > 0) {
                page = serverPage;
            }
            render(items);
            renderPager();
        }).catch(function (err) {
            if (seq !== loadSeq) {
                return;
            }
            busy = false;
            setControlsDisabled(false);
            if (window.VS && VS.showMessage) {
                VS.showMessage((err && err.message) ? err.message : '加载失败', 'error');
            }
        });
    }

    function resetAndLoad() {
        page = 1;
        loadList();
    }

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            var hit = e.target.closest('[data-msg-id]');
            if (!hit || !listEl.contains(hit)) {
                return;
            }
            e.preventDefault();
            openDetail(hit.getAttribute('data-msg-id'));
        });
    }
    if (btnEl) {
        btnEl.addEventListener('click', function () {
            resetAndLoad();
        });
    }
    if (inputEl) {
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resetAndLoad();
            }
        });
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (prevBtn.disabled || page <= 1) {
                return;
            }
            page -= 1;
            loadList();
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (nextBtn.disabled || page >= pagerTotalPages()) {
                return;
            }
            page += 1;
            loadList();
        });
    }
    if (pagerNums) {
        pagerNums.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-page]');
            if (!btn || !pagerNums.contains(btn)) {
                return;
            }
            var next = parseInt(btn.getAttribute('data-page'), 10) || 0;
            if (!next || next === page) {
                return;
            }
            page = next;
            loadList();
        });
    }
    if (pageSizeEl) {
        if (!pageSizeEl.value) {
            pageSizeEl.value = '20';
        }
        pageSizeEl.addEventListener('change', function () {
            resetAndLoad();
        });
    }

    if (window.VS) {
        loadList();
    } else {
        setTimeout(loadList, 40);
    }
})();
