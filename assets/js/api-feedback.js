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
        var statusFilter = document.getElementById('adminFbStatusFilter');
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
        var detailContent = document.getElementById('adminFbDetailContent');
        var detailReply = document.getElementById('adminFbDetailReply');
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

        function allDesktopRows() {
            if (!listEl) {
                return [];
            }
            return Array.prototype.slice.call(listEl.querySelectorAll('tr[data-feedback-row]'));
        }

        function getPair(id) {
            var sid = String(id);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-feedback-row="' + sid + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.feedback-card[data-feedback-row="' + sid + '"]') : null
            };
        }

        function syncRowOrder(rows) {
            if (!listEl) {
                return;
            }
            rows.forEach(function (row) {
                listEl.appendChild(row);
                if (mobileEl) {
                    var id = row.getAttribute('data-feedback-row');
                    var card = mobileEl.querySelector('.feedback-card[data-feedback-row="' + id + '"]');
                    if (card) {
                        mobileEl.appendChild(card);
                    }
                }
            });
        }

        function matchedRows() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var st = statusFilter ? String(statusFilter.value || '') : '';
            var all = allDesktopRows();
            var filtered = all.filter(function (row) {
                if (st !== '' && row.getAttribute('data-feedback-status') !== st) {
                    return false;
                }
                if (!q) {
                    return true;
                }
                var hay = (row.getAttribute('data-search') || '').toLowerCase();
                return hay.indexOf(q) !== -1;
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

            matched.forEach(function (row, idx) {
                var show = idx >= start && idx < end;
                var id = row.getAttribute('data-feedback-row');
                row.hidden = !show;
                if (show) {
                    visibleIds[id] = true;
                }
            });
            allDesktopRows().forEach(function (row) {
                if (matched.indexOf(row) === -1) {
                    row.hidden = true;
                }
            });
            if (mobileEl) {
                mobileEl.querySelectorAll('.feedback-card[data-feedback-row]').forEach(function (card) {
                    var id = card.getAttribute('data-feedback-row');
                    var desk = listEl ? listEl.querySelector('tr[data-feedback-row="' + id + '"]') : null;
                    var inMatched = desk && matched.indexOf(desk) !== -1;
                    card.hidden = !(inMatched && visibleIds[id]);
                });
            }

            var hasAny = totalAll > 0;
            var hasVisible = matched.length > 0;
            var q = searchInput ? String(searchInput.value || '').trim() : '';
            var st = statusFilter ? String(statusFilter.value || '') : '';
            var filtered = q !== '' || st !== '';

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
                if (detailMarkBtn) {
                    detailMarkBtn.hidden = true;
                }
            }
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

        function openOverlay() {
            if (!overlay) {
                return;
            }
            overlay.hidden = false;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-overlay-open');
            if (detailReply) {
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

        function openDetail(id, fromEl) {
            var pair = getPair(id);
            var src = pair.desktop || pair.mobile;
            if (!src) {
                return;
            }
            returnFocusEl = fromEl || null;
            var pending = src.getAttribute('data-feedback-status') === '0';
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
            if (detailContent) {
                detailContent.textContent = src.getAttribute('data-content') || '';
            }
            if (detailReply) {
                detailReply.value = src.getAttribute('data-reply') || '';
            }
            if (detailMarkBtn) {
                detailMarkBtn.hidden = !pending;
            }
            openOverlay();
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
                var pair = getPair(id);
                [pair.desktop, pair.mobile].forEach(function (el) {
                    if (el) {
                        el.setAttribute('data-reply', payload.reply);
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
                openDetail(viewBtn.getAttribute('data-feedback-id'), viewBtn);
                return;
            }
            var markBtn = e.target.closest('.vs-fb-mark');
            if (markBtn) {
                e.preventDefault();
                // 列表快捷标记：无回复正文，直接处理并通知用户
                markDone(markBtn.getAttribute('data-feedback-id'), '');
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
        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
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
