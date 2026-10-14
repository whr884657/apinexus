/**
 * 文件：assets/js/api-feedback.js
 * 作用：接口反馈（桌面表格 + 手机卡片；搜索/状态/分页/详情）
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
        var page = document.getElementById('apiFeedbackPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('adminFbTableWrap');
        var listEl = document.getElementById('adminFbBody');
        var mobileEl = document.getElementById('adminFbMobile');
        var emptyEl = document.getElementById('adminFbEmpty');
        var searchEmptyEl = document.getElementById('adminFbSearchEmpty');
        var searchInput = document.getElementById('adminFbSearchInput');
        var statusFilter = null;
        var statusFilterValue = '0';
        var filterBtns = page.querySelectorAll('.vs-api-fb-filter');
        function getStatusFilter() {
            return statusFilterValue;
        }
        function setStatusFilter(v) {
            statusFilterValue = String(v == null ? '0' : v);
            filterBtns.forEach(function (btn) {
                var on = btn.getAttribute('data-filter') === statusFilterValue;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        var pageSizeEl = document.getElementById('adminFbPageSize');
        var footerEl = document.getElementById('adminFbFooter');
        var pagerNumsEl = document.getElementById('adminFbPagerNums');
        var statsEl = document.getElementById('adminFbStats');
        var prevBtn = document.getElementById('adminFbPrevBtn');
        var nextBtn = document.getElementById('adminFbNextBtn');
        var overlay = document.getElementById('adminFbDetailOverlay');
        var detailIdEl = document.getElementById('adminFbDetailId');
        var detailIdLabel = document.getElementById('adminFbDetailIdLabel');
        var detailStatus = document.getElementById('adminFbDetailStatus');
        var detailApi = document.getElementById('adminFbDetailApi');
        var detailUser = document.getElementById('adminFbDetailUser');
        var detailEmail = document.getElementById('adminFbDetailEmail');
        var detailContent = document.getElementById('adminFbDetailContent');
        var detailReply = document.getElementById('adminFbDetailReply');
        var detailReplyEditWrap = document.getElementById('adminFbDetailReplyEditWrap');
        var detailReplyViewWrap = document.getElementById('adminFbDetailReplyViewWrap');
        var detailReplyView = document.getElementById('adminFbDetailReplyView');
        var detailMarkBtn = document.getElementById('adminFbDetailMarkBtn');
        var detailDeleteBtn = document.getElementById('adminFbDetailDeleteBtn');
        var currentPage = 1;
        var returnFocusEl = null;
        var marking = false;

        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }

        function postAction(action, payload) {
            var fd = new FormData();
            fd.append('action', action);
            if (payload) {
                Object.keys(payload).forEach(function (key) {
                    fd.append(key, payload[key]);
                });
            }
            return window.VS.postForm(fd);
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

        function getPair(id) {
            var sid = String(id);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-feedback-row="' + sid + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.feedback-card[data-feedback-row="' + sid + '"]') : null
            };
        }

        function renderPagerNums(totalPages) {
            if (window.VS && VS.applyLogsPager) {
                VS.applyLogsPager(currentPage, Math.max(1, totalPages), prevBtn, nextBtn, pagerNumsEl);
            }
        }

        function paintCounts(counts) {
            if (!counts) {
                return;
            }
            var n0 = parseInt(counts['0'], 10) || 0;
            var n1 = parseInt(counts['1'], 10) || 0;
            page.querySelectorAll('[data-count]').forEach(function (el) {
                var k = el.getAttribute('data-count');
                if (k === '0') {
                    el.textContent = String(n0);
                }
                if (k === '1') {
                    el.textContent = String(n1);
                }
            });
        }

        var listTotal = parseInt(page.getAttribute('data-feedback-total') || '0', 10) || 0;

        function applyView() {
            var fd = new FormData();
            fd.append('action', 'list');
            fd.append('q', searchInput ? String(searchInput.value || '').trim() : '');
            fd.append('status', String(getStatusFilter()));
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
                page.setAttribute('data-feedback-total', String(listTotal));
                paintCounts(data.counts);
                if (listEl) {
                    listEl.innerHTML = data.desktop_html || '';
                }
                if (mobileEl) {
                    mobileEl.innerHTML = data.mobile_html || '';
                }
                var q = searchInput ? String(searchInput.value || '').trim() : '';
                var filtered = q !== '';
                var pageItems = listEl ? listEl.querySelectorAll('tr[data-feedback-row]').length : 0;
                var n0 = data.counts ? (parseInt(data.counts['0'], 10) || 0) : 0;
                var n1 = data.counts ? (parseInt(data.counts['1'], 10) || 0) : 0;
                var hasRepo = n0 + n1 > 0;
                if (emptyEl) {
                    emptyEl.hidden = hasRepo || filtered;
                }
                if (searchEmptyEl) {
                    searchEmptyEl.hidden = !(pageItems === 0 && (filtered || hasRepo));
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

        function buildActions(id, pending) {
            var html = '<div class="action-btns">';
            html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-fb-view" data-feedback-id="'
                + id + '">查看</button>';
            if (pending) {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-fb-mark" data-feedback-id="'
                    + id + '">标记已处理</button>';
            }
            html += '</div>';
            return html;
        }

        function setPairDone(pair) {
            [pair.desktop, pair.mobile].forEach(function (el) {
                if (!el) {
                    return;
                }
                el.setAttribute('data-feedback-status', '1');
                var badge = el.querySelector('[data-field="status_label"]');
                if (badge) {
                    badge.textContent = '已处理';
                    badge.className = 'vs-badge vs-badge--success';
                }
                var id = el.getAttribute('data-feedback-row');
                var actions = el.querySelector('[data-field="actions"]');
                if (actions) {
                    actions.innerHTML = buildActions(id, false);
                }
            });
            var openId = detailIdEl ? String(detailIdEl.value) : '';
            var rowId = pair.desktop
                ? pair.desktop.getAttribute('data-feedback-row')
                : (pair.mobile ? pair.mobile.getAttribute('data-feedback-row') : '');
            if (openId && rowId && openId === String(rowId)) {
                if (detailStatus) {
                    detailStatus.textContent = '已处理';
                    detailStatus.className = 'vs-badge vs-badge--success';
                }
                var replyText = '';
                if (pair.desktop) {
                    replyText = pair.desktop.getAttribute('data-reply') || '';
                } else if (pair.mobile) {
                    replyText = pair.mobile.getAttribute('data-reply') || '';
                }
                setReplyMode(false, replyText);
            }
        }

        function removePair(id) {
            applyView();
        }

        /**
         * 待处理：可编辑回复 + 显示「标记已处理」
         * 已处理：只读展示已保存回复 + 隐藏标记按钮
         */
        function setReplyMode(pending, replyText) {
            var text = replyText == null ? '' : String(replyText);
            if (detailReplyEditWrap) {
                detailReplyEditWrap.hidden = !pending;
            }
            if (detailReplyViewWrap) {
                detailReplyViewWrap.hidden = !!pending;
            }
            if (pending) {
                if (detailReply) {
                    detailReply.value = text;
                }
            } else if (detailReplyView) {
                var empty = text === '';
                detailReplyView.textContent = empty ? '（未填写回复）' : text;
                if (empty) {
                    detailReplyView.classList.add('is-empty-reply');
                } else {
                    detailReplyView.classList.remove('is-empty-reply');
                }
            }
            if (detailMarkBtn) {
                detailMarkBtn.hidden = !pending;
                detailMarkBtn.setAttribute('aria-hidden', pending ? 'false' : 'true');
                if (pending) {
                    detailMarkBtn.removeAttribute('tabindex');
                } else {
                    detailMarkBtn.setAttribute('tabindex', '-1');
                }
            }
        }

        function openOverlay(focusReply) {
            if (!overlay) {
                return;
            }
            overlay.hidden = false;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-overlay-open');
            var canFocus = focusReply && detailReply && (!detailReplyEditWrap || !detailReplyEditWrap.hidden);
            if (canFocus) {
                setTimeout(function () {
                    detailReply.focus();
                }, 50);
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

        function openDetail(id, fromEl, preferFocusReply) {
            var pair = getPair(id);
            var src = pair.desktop || pair.mobile;
            if (!src) {
                return;
            }
            returnFocusEl = fromEl || null;
            var pending = src.getAttribute('data-feedback-status') === '0';
            var replyText = src.getAttribute('data-reply') || '';
            if (detailIdEl) {
                detailIdEl.value = String(id);
            }
            if (detailIdLabel) {
                detailIdLabel.textContent = '#' + id;
            }
            if (detailStatus) {
                detailStatus.textContent = pending ? '待处理' : '已处理';
                detailStatus.className = 'vs-badge ' + (pending ? 'vs-badge--warning' : 'vs-badge--success');
            }
            if (detailApi) {
                detailApi.textContent = src.getAttribute('data-api-name') || '—';
            }
            if (detailUser) {
                var uname = src.getAttribute('data-username') || '—';
                var time = src.getAttribute('data-time') || '';
                detailUser.textContent = uname + (time ? (' · ' + time) : '');
            }
            if (detailEmail) {
                var email = (src.getAttribute('data-email') || '').trim();
                detailEmail.textContent = '';
                if (email !== '') {
                    var mailLink = document.createElement('a');
                    mailLink.href = 'mailto:' + email;
                    mailLink.className = 'fb-modal__email-link';
                    mailLink.textContent = email;
                    detailEmail.appendChild(mailLink);
                } else {
                    detailEmail.textContent = '（未填写邮箱）';
                }
            }
            if (detailContent) {
                detailContent.textContent = src.getAttribute('data-content') || '';
            }
            setReplyMode(pending, replyText);
            openOverlay(!!preferFocusReply && pending);
        }

        function markDone(id, replyText) {
            if (marking) {
                return Promise.resolve();
            }
            marking = true;
            var payload = { feedback_id: id, reply: replyText == null ? '' : String(replyText) };
            return postAction('mark_done', payload).then(function (res) {
                if (!ok(res)) {
                    throw new Error(msg(res, '操作失败'));
                }
                var savedReply = payload.reply;
                if (res && res.feedback && res.feedback.reply != null) {
                    savedReply = String(res.feedback.reply);
                }
                var pair = getPair(id);
                [pair.desktop, pair.mobile].forEach(function (el) {
                    if (el) {
                        el.setAttribute('data-reply', savedReply);
                    }
                });
                setPairDone(pair);
                applyView();
                window.VS.showMessage(msg(res, '已标记为已处理'), 'success');
                return res;
            }).catch(function (err) {
                window.VS.showMessage((err && err.message) ? err.message : '操作失败', 'error');
            }).then(function () {
                marking = false;
            });
        }

        page.addEventListener('click', function (e) {
            var viewBtn = e.target.closest('.vs-fb-view');
            if (viewBtn) {
                e.preventDefault();
                openDetail(viewBtn.getAttribute('data-feedback-id'), viewBtn, false);
                return;
            }
            var markBtn = e.target.closest('.vs-fb-mark');
            if (markBtn) {
                e.preventDefault();
                // 列表「标记已处理」必须先打开抽屉，再在弹窗内确认（可留空回复）
                openDetail(markBtn.getAttribute('data-feedback-id'), markBtn, true);
            }
        });

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

        if (detailMarkBtn) {
            detailMarkBtn.addEventListener('click', function () {
                var id = detailIdEl ? detailIdEl.value : '';
                if (!id || marking) {
                    return;
                }
                var reply = detailReply ? detailReply.value : '';
                detailMarkBtn.disabled = true;
                markDone(id, reply).then(function () {
                    detailMarkBtn.disabled = false;
                    if (overlay && overlay.classList.contains('is-open')) {
                        var pair = getPair(id);
                        var src = pair.desktop || pair.mobile;
                        if (src && src.getAttribute('data-feedback-status') === '1') {
                            closeOverlay();
                        }
                    }
                });
            });
        }

        if (detailDeleteBtn) {
            detailDeleteBtn.addEventListener('click', function () {
                var id = detailIdEl ? detailIdEl.value : '';
                if (!id) {
                    return;
                }
                var doDelete = function () {
                    detailDeleteBtn.disabled = true;
                    postAction('delete', { feedback_id: id }).then(function (res) {
                        if (!ok(res)) {
                            throw new Error(msg(res, '删除失败'));
                        }
                        removePair(id);
                        closeOverlay();
                        window.VS.showMessage(msg(res, '反馈已删除'), 'success');
                    }).catch(function (err) {
                        window.VS.showMessage((err && err.message) ? err.message : '删除失败', 'error');
                    }).then(function () {
                        detailDeleteBtn.disabled = false;
                    });
                };
                var ask = (window.VsModal && typeof window.VsModal.confirm === 'function')
                    ? window.VsModal.confirm('确定删除该反馈？', '删除反馈', { danger: true })
                    : Promise.resolve(window.confirm('确定删除该反馈？'));
                ask.then(function (yes) {
                    if (yes) {
                        doDelete();
                    }
                });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentPage = 1;
                applyView();
            });
        }
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setStatusFilter(btn.getAttribute('data-filter') || '0');
                currentPage = 1;
                applyView();
            });
        });
        setStatusFilter('0');
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
                var btn = e.target.closest('[data-page]');
                if (!btn) {
                    return;
                }
                currentPage = parseInt(btn.getAttribute('data-page'), 10) || 1;
                applyView();
            });
        }

        applyView();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
