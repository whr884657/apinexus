/**
 * 后台 · 开发者申请审核（AJAX + Tab + 详情抽屉/弹窗）
 */
(function () {
    'use strict';

    var page = document.getElementById('adminDevApplyPage');
    if (!page) {
        return;
    }

    var list = document.getElementById('devApplyList');
    var empty = document.getElementById('devApplyEmpty');
    var filters = document.getElementById('devApplyFilters');
    var activeTab = page.getAttribute('data-active-tab') || 'pending';
    var currentPage = 1;
    var prevBtn = document.getElementById('devApplyPrevBtn');
    var nextBtn = document.getElementById('devApplyNextBtn');
    var pagerNums = document.getElementById('devApplyPagerNums');
    var pageSizeEl = document.getElementById('devApplyPageSize');
    var footerEl = document.getElementById('devApplyFooter');
    var statsEl = document.getElementById('devApplyStats');
    var listTotal = 0;
    var overlay = document.getElementById('adminDevApplyDetailOverlay');
    var detailIdEl = document.getElementById('adminDevApplyDetailId');
    var detailIdLabel = document.getElementById('adminDevApplyDetailIdLabel');
    var detailStatus = document.getElementById('adminDevApplyDetailStatus');
    var detailUser = document.getElementById('adminDevApplyDetailUser');
    var detailEmail = document.getElementById('adminDevApplyDetailEmail');
    var detailContent = document.getElementById('adminDevApplyDetailContent');
    var detailReply = document.getElementById('adminDevApplyDetailReply');
    var detailReplyEditWrap = document.getElementById('adminDevApplyDetailReplyEditWrap');
    var detailReplyViewWrap = document.getElementById('adminDevApplyDetailReplyViewWrap');
    var detailReplyView = document.getElementById('adminDevApplyDetailReplyView');
    var detailApproveBtn = document.getElementById('adminDevApplyDetailApproveBtn');
    var detailRejectBtn = document.getElementById('adminDevApplyDetailRejectBtn');
    var returnFocusEl = null;
    var acting = false;

    if (overlay && overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if ([10, 20, 30, 50].indexOf(n) === -1) {
            n = 20;
        }
        return n;
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setCounts(counts) {
        if (!filters || !counts) {
            return;
        }
        ['pending', 'approved', 'rejected'].forEach(function (key) {
            var badge = filters.querySelector('[data-count="' + key + '"]');
            if (badge) {
                badge.textContent = String(counts[key] || 0);
            }
        });
    }

    function headHtml() {
        return '<div class="vs-link-row vs-link-row--head" aria-hidden="true">'
            + '<div class="vs-link-cell">用户</div>'
            + '<div class="vs-link-cell">联系 / 时间</div>'
            + '<div class="vs-link-cell">申请理由</div>'
            + '<div class="vs-link-cell">状态</div>'
            + '<div class="vs-link-cell">操作</div>'
            + '</div>';
    }

    function shortTime(t) {
        t = String(t || '');
        return t.length >= 16 ? t.slice(0, 16) : t;
    }

    function rowHtml(row) {
        var id = parseInt(row.id, 10) || 0;
        var status = parseInt(row.status, 10) || 0;
        var userid = parseInt(row.userid, 10) || 0;
        var name = row.name || row.username || ('用户 #' + userid);
        var contact = row.contact || row.email || '';
        var desc = row.description || row.content || '';
        var reply = row.reply || '';
        var avatarUrl = row.avatar_url || '';
        var label = row.status_label || '';
        var time = shortTime(row.createtime || '');
        var tab = status === 1 ? 'approved' : (status === 2 ? 'rejected' : 'pending');
        var statusClass = status === 1 ? 'is-on' : (status === 2 ? 'is-off' : 'is-pending');
        var initial = String(name).charAt(0) || 'U';
        var meta = 'ID ' + userid;
        if (contact) {
            meta += ' · ' + contact;
        }
        if (time) {
            meta += ' · ' + time;
        }
        var icon = avatarUrl
            ? '<img src="' + esc(avatarUrl) + '" alt="" width="36" height="36" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">'
            : '<span class="vs-link-row__initial">' + esc(initial) + '</span>';
        var actions = '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-dev-action="view" data-link-id="' + id + '">查看</button>';
        if (status === 0) {
            actions += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success" data-dev-action="approve" data-link-id="' + id + '">通过</button>'
                + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-dev-action="reject" data-link-id="' + id + '">拒绝</button>';
        }
        return '<div class="vs-link-row" data-link-row="' + id + '" data-link-tab="' + tab + '" data-link-status="' + status + '"'
            + ' data-username="' + esc(name) + '"'
            + ' data-email="' + esc(contact) + '"'
            + ' data-content="' + esc(desc) + '"'
            + ' data-reply="' + esc(reply) + '"'
            + ' data-time="' + esc(time) + '"'
            + ' data-avatar-url="' + esc(avatarUrl) + '">'
            + '<div class="vs-link-cell vs-link-c-site">'
            + '<div class="vs-link-row__icon">' + icon + '</div>'
            + '<div class="vs-link-row__name">' + esc(name) + '</div>'
            + '</div>'
            + '<div class="vs-link-cell vs-link-c-url"><div class="vs-link-row__url">' + esc(meta) + '</div></div>'
            + '<div class="vs-link-cell vs-link-c-desc"><div class="vs-link-row__desc vs-link-row__desc--box">' + esc(desc || '—') + '</div></div>'
            + '<div class="vs-link-cell vs-link-c-status"><span class="vs-link-status ' + statusClass + '">' + esc(label) + '</span></div>'
            + '<div class="vs-link-cell vs-link-c-actions">' + actions + '</div>'
            + '</div>';
    }

    function paintList(items, total, counts) {
        listTotal = parseInt(total, 10) || 0;
        if (counts) {
            setCounts(counts);
        }
        if (!list) {
            return;
        }
        var html = headHtml();
        (items || []).forEach(function (row) {
            html += rowHtml(row);
        });
        list.innerHTML = html;
        list.hidden = !items || !items.length;
        if (empty) {
            empty.hidden = !!(items && items.length);
        }
        if (window.VS && typeof VS.bindExternalImgFallback === 'function') {
            VS.bindExternalImgFallback(list);
        }
        renderPager();
    }

    function renderPager() {
        var ps = getPageSize();
        var totalPages = Math.max(1, Math.ceil(listTotal / ps) || 1);
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (footerEl) {
            footerEl.hidden = listTotal <= 0;
        }
        if (statsEl) {
            statsEl.textContent = '共 ' + listTotal + ' 条';
        }
        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages;
        }
        if (pagerNums && window.VS && VS.pagerNumsHtml) {
            pagerNums.innerHTML = VS.pagerNumsHtml(currentPage, totalPages);
        }
    }

    function loadList() {
        if (!window.VS || typeof VS.postForm !== 'function') {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('tab', activeTab);
        fd.append('page', String(currentPage));
        fd.append('pagesize', String(getPageSize()));
        VS.postForm(fd).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                if (VS.showMessage) {
                    VS.showMessage((data && data.msg) || '加载失败', 'error');
                }
                return;
            }
            currentPage = parseInt(data.page, 10) || currentPage;
            paintList(data.list || [], data.total, data.counts);
        }).catch(function () {
            if (VS.showMessage) {
                VS.showMessage('网络异常', 'error');
            }
        });
    }

    function postAction(action, id, extra) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('link_id', String(id));
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                fd.append(k, String(extra[k]));
            });
        }
        return VS.postForm(fd);
    }

    function getRow(id) {
        return list ? list.querySelector('[data-link-row="' + String(id) + '"]') : null;
    }

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
            var emptyReply = text === '';
            detailReplyView.textContent = emptyReply ? '（未填写回复）' : text;
            if (emptyReply) {
                detailReplyView.classList.add('is-empty-reply');
            } else {
                detailReplyView.classList.remove('is-empty-reply');
            }
        }
        if (detailApproveBtn) {
            detailApproveBtn.hidden = !pending;
        }
        if (detailRejectBtn) {
            detailRejectBtn.hidden = !pending;
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
        var src = getRow(id);
        if (!src) {
            return;
        }
        returnFocusEl = fromEl || null;
        var status = parseInt(src.getAttribute('data-link-status'), 10) || 0;
        var pending = status === 0;
        var replyText = src.getAttribute('data-reply') || '';
        if (detailIdEl) {
            detailIdEl.value = String(id);
        }
        if (detailIdLabel) {
            detailIdLabel.textContent = '#' + id;
        }
        if (detailStatus) {
            var label = pending ? '待审核' : (status === 1 ? '已通过' : '已拒绝');
            var badgeClass = pending ? 'vs-badge--warning' : (status === 1 ? 'vs-badge--success' : 'vs-badge--default');
            detailStatus.textContent = label;
            detailStatus.className = 'vs-badge ' + badgeClass;
        }
        if (detailUser) {
            var uname = src.getAttribute('data-username') || '—';
            var time = src.getAttribute('data-time') || '';
            detailUser.textContent = uname + (time ? (' · ' + time) : '');
        }
        if (detailEmail) {
            var email = (src.getAttribute('data-email') || '').trim();
            detailEmail.textContent = '';
            var emailOk = email !== ''
                && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
                && email.indexOf('\n') === -1
                && email.indexOf('\r') === -1;
            if (emailOk) {
                var mailLink = document.createElement('a');
                mailLink.href = 'mailto:' + email;
                mailLink.className = 'fb-modal__email-link';
                mailLink.textContent = email;
                detailEmail.appendChild(mailLink);
            } else if (email !== '') {
                detailEmail.textContent = email;
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

    function runStatus(id, status, withReply) {
        if (acting) {
            return;
        }
        acting = true;
        var extra = { status: status };
        if (withReply) {
            extra.reply = detailReply ? detailReply.value : '';
        }
        postAction('set_status', id, extra).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                VS.showMessage((data && data.msg) || '操作失败', 'error');
                return;
            }
            VS.showMessage(data.msg || '已完成', 'success');
            closeOverlay();
            loadList();
        }).catch(function () {
            VS.showMessage('网络异常', 'error');
        }).then(function () {
            acting = false;
        });
    }

    if (filters) {
        filters.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-dev-filter');
            if (!btn) {
                return;
            }
            var tab = btn.getAttribute('data-filter') || 'pending';
            if (tab === activeTab) {
                return;
            }
            activeTab = tab;
            page.setAttribute('data-active-tab', tab);
            filters.querySelectorAll('.vs-dev-filter').forEach(function (el) {
                var on = el === btn;
                el.classList.toggle('is-active', on);
                el.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            currentPage = 1;
            loadList();
        });
    }

    if (list) {
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-dev-action]');
            if (!btn) {
                return;
            }
            var id = parseInt(btn.getAttribute('data-link-id'), 10) || 0;
            var act = btn.getAttribute('data-dev-action') || '';
            if (!id || !act) {
                return;
            }
            if (act === 'view') {
                openDetail(id, btn, false);
                return;
            }
            if (act === 'approve') {
                openDetail(id, btn, true);
                return;
            }
            if (act === 'reject') {
                openDetail(id, btn, true);
                return;
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target.closest('[data-overlay-close]')) {
                closeOverlay();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeOverlay();
            }
        });
    }

    if (detailApproveBtn) {
        detailApproveBtn.addEventListener('click', function () {
            var id = detailIdEl ? parseInt(detailIdEl.value, 10) : 0;
            if (id) {
                runStatus(id, 1, true);
            }
        });
    }
    if (detailRejectBtn) {
        detailRejectBtn.addEventListener('click', function () {
            var id = detailIdEl ? parseInt(detailIdEl.value, 10) : 0;
            if (id) {
                runStatus(id, 2, true);
            }
        });
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage -= 1;
                loadList();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            currentPage += 1;
            loadList();
        });
    }
    if (pagerNums) {
        pagerNums.addEventListener('click', function (e) {
            var b = e.target.closest('[data-page]');
            if (!b) {
                return;
            }
            var p = parseInt(b.getAttribute('data-page'), 10) || 1;
            if (p !== currentPage) {
                currentPage = p;
                loadList();
            }
        });
    }
    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            currentPage = 1;
            loadList();
        });
    }

    renderPager();
})();
