/**
 * 文件：assets/js/api-review.js
 * 作用：后台接口审核（待审 / 通过 / 不通过；桌面表格 + 手机卡片双 DOM）
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
        var page = document.getElementById('apiReviewPage');
        if (!page) {
            return;
        }

        var tableWrapEl = document.getElementById('apiReviewTableWrap');
        var listEl = document.getElementById('apiReviewBody');
        var mobileEl = document.getElementById('apiReviewMobile');
        var emptyAll = document.getElementById('apiReviewEmpty');
        var emptyFilter = document.getElementById('apiReviewFilterEmpty');
        var searchInput = document.getElementById('apiReviewSearchInput');
        var pageSizeEl = document.getElementById('apiReviewPageSize');
        var footerEl = document.getElementById('apiReviewFooter');
        var pagerNumsEl = document.getElementById('apiReviewPagerNums');
        var statsEl = document.getElementById('apiReviewStats');
        var prevBtn = document.getElementById('apiReviewPrevBtn');
        var nextBtn = document.getElementById('apiReviewNextBtn');
        var overlay = document.getElementById('apiReviewRejectOverlay');
        var reasonInput = document.getElementById('apiReviewRejectReason');
        var confirmBtn = document.getElementById('apiReviewRejectConfirm');
        var listEditBase = page.getAttribute('data-list-edit-base') || '';
        var currentFilter = '0';
        var currentPage = 1;
        var rejectApiId = '';

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function badgeClass(audit) {
            var n = String(audit);
            if (n === '1') {
                return 'vs-badge--success';
            }
            if (n === '2') {
                return 'vs-badge--error';
            }
            return 'vs-badge--warning';
        }

        function badgeText(audit) {
            var n = String(audit);
            if (n === '1') {
                return '已通过';
            }
            if (n === '2') {
                return '未通过';
            }
            return '待审核';
        }

        function openOverlay() {
            if (!overlay) {
                return;
            }
            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
            if (reasonInput) {
                reasonInput.value = '';
                reasonInput.focus();
            }
        }

        function closeOverlay() {
            if (!overlay) {
                return;
            }
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            rejectApiId = '';
        }

        function defaultPageSize() {
            return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
        }

        function getPageSize() {
            var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 0;
            if (!n || n < 1) {
                n = defaultPageSize();
            }
            return n;
        }

        function getRowPair(apiId) {
            var id = String(apiId);
            return {
                desktop: listEl ? listEl.querySelector('tr[data-api-row="' + id + '"]') : null,
                mobile: mobileEl ? mobileEl.querySelector('.review-card[data-api-row="' + id + '"]') : null
            };
        }

        function syncRowOrder(rows) {
            if (!listEl) {
                return;
            }
            rows.forEach(function (row) {
                listEl.appendChild(row);
                if (mobileEl) {
                    var id = row.getAttribute('data-api-row');
                    var card = mobileEl.querySelector('.review-card[data-api-row="' + id + '"]');
                    if (card) {
                        mobileEl.appendChild(card);
                    }
                }
            });
        }

        function allDesktopRows() {
            if (!listEl) {
                return [];
            }
            return Array.prototype.slice.call(listEl.querySelectorAll('tr[data-api-row]'));
        }

        function matchedRows() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var all = allDesktopRows();
            var filtered = all.filter(function (row) {
                var audit = String(row.getAttribute('data-audit') || '');
                if (audit !== currentFilter) {
                    return false;
                }
                if (q) {
                    var hay = row.getAttribute('data-search') || '';
                    if (hay.indexOf(q) === -1) {
                        return false;
                    }
                }
                return true;
            });
            syncRowOrder(filtered);
            return filtered;
        }

        function refreshTabBadges() {
            var counts = { '0': 0, '1': 0, '2': 0 };
            allDesktopRows().forEach(function (row) {
                var a = String(row.getAttribute('data-audit') || '0');
                if (counts[a] != null) {
                    counts[a] += 1;
                }
            });
            document.querySelectorAll('.vs-api-review-tabs__badge[data-count]').forEach(function (el) {
                var key = String(el.getAttribute('data-count') || '');
                el.textContent = String(counts[key] != null ? counts[key] : 0);
            });
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
                var id = row.getAttribute('data-api-row');
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
                mobileEl.querySelectorAll('.review-card[data-api-row]').forEach(function (card) {
                    var id = card.getAttribute('data-api-row');
                    var matchedDesktop = listEl
                        ? listEl.querySelector('tr[data-api-row="' + id + '"]')
                        : null;
                    var inMatched = matchedDesktop && matched.indexOf(matchedDesktop) !== -1;
                    card.hidden = !(inMatched && visibleIds[id]);
                });
            }

            var hasAny = totalAll > 0;
            var hasVisible = matched.length > 0;
            if (emptyAll) {
                emptyAll.hidden = hasAny;
            }
            if (emptyFilter) {
                emptyFilter.hidden = !(hasAny && !hasVisible);
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
                    + (hasAny ? ('（全部投稿 ' + totalAll + '）') : '');
            }
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || matched.length === 0;
            }
            renderPagerNums(matched.length === 0 ? 0 : totalPages);
            refreshTabBadges();
        }

        function setFilter(filter) {
            var next = String(filter == null ? '0' : filter);
            if (next !== '0' && next !== '1' && next !== '2') {
                next = '0';
            }
            currentFilter = next;
            currentPage = 1;
            document.querySelectorAll('.vs-api-review-filter').forEach(function (btn) {
                var on = String(btn.getAttribute('data-filter')) === currentFilter;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            applyView();
        }

        function renderActionsHtml(audit, apiId) {
            var html = '<div class="action-btns">';
            if (listEditBase) {
                html += '<a class="vs-btn vs-btn--sm vs-btn--outline" href="'
                    + escapeHtml(listEditBase + encodeURIComponent(apiId)) + '">编辑</a>';
            }
            if (String(audit) !== '1') {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-review-action" data-audit="1">通过</button>';
            }
            if (String(audit) !== '2') {
                html += '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-review-deny" data-audit="2">不通过</button>';
            }
            html += '</div>';
            return html;
        }

        function updateRowAudit(apiId, audit, rejectreason, auditLabel) {
            var pair = getRowPair(apiId);
            var nodes = [pair.desktop, pair.mobile];
            var text = badgeText(audit);
            var cls = 'vs-badge ' + badgeClass(audit);
            if (auditLabel && String(audit) === '1' && String(auditLabel).indexOf('通过') !== -1) {
                text = '已通过';
            }
            nodes.forEach(function (el) {
                if (!el) {
                    return;
                }
                el.setAttribute('data-audit', String(audit));
                var label = el.querySelector('[data-field="audit_label"]');
                if (label) {
                    label.textContent = text;
                    label.className = cls;
                }
                var reasonEl = el.querySelector('[data-field="rejectreason"]');
                if (reasonEl) {
                    var r = rejectreason || '';
                    if (r) {
                        reasonEl.hidden = false;
                        reasonEl.textContent = '原因：' + r;
                    } else {
                        reasonEl.hidden = true;
                        reasonEl.textContent = '';
                    }
                }
                var actions = el.querySelector('[data-field="actions"]');
                if (actions) {
                    actions.innerHTML = renderActionsHtml(String(audit), apiId);
                }
            });
        }

        function postAudit(apiId, audit, reason) {
            var fd = new FormData();
            fd.append('action', 'set_audit');
            fd.append('api_id', apiId);
            fd.append('audit', audit);
            if (typeof reason === 'string') {
                fd.append('rejectreason', reason);
            }
            return window.VS.postForm(fd).then(function (data) {
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return null;
                }
                window.VS.showMessage(data.msg || '已更新', 'success');
                updateRowAudit(
                    apiId,
                    audit,
                    data.rejectreason || '',
                    data.audit_label || ''
                );
                applyView();
                return data;
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
                return null;
            });
        }

        document.addEventListener('click', function (e) {
            var closeEl = e.target.closest('[data-overlay-close]');
            if (closeEl && overlay && overlay.contains(closeEl)) {
                closeOverlay();
                return;
            }

            var filterBtn = e.target.closest('.vs-api-review-filter');
            if (filterBtn && page.contains(filterBtn)) {
                setFilter(filterBtn.getAttribute('data-filter') || '0');
                return;
            }

            var pageBtn = e.target.closest('.vs-api-pager__num[data-page]');
            if (pageBtn && pagerNumsEl && pagerNumsEl.contains(pageBtn)) {
                currentPage = parseInt(pageBtn.getAttribute('data-page'), 10) || 1;
                applyView();
                return;
            }

            var denyBtn = e.target.closest('.vs-api-review-deny');
            if (denyBtn && page.contains(denyBtn)) {
                var rowDeny = denyBtn.closest('[data-api-row]');
                if (!rowDeny) {
                    return;
                }
                rejectApiId = rowDeny.getAttribute('data-api-id') || rowDeny.getAttribute('data-api-row') || '';
                openOverlay();
                return;
            }

            var passBtn = e.target.closest('.vs-api-review-action');
            if (passBtn && page.contains(passBtn)) {
                var rowPass = passBtn.closest('[data-api-row]');
                if (!rowPass) {
                    return;
                }
                postAudit(
                    rowPass.getAttribute('data-api-id') || rowPass.getAttribute('data-api-row'),
                    passBtn.getAttribute('data-audit'),
                    ''
                );
            }
        });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (!rejectApiId) {
                    return;
                }
                var reason = reasonInput ? reasonInput.value : '';
                postAudit(rejectApiId, '2', reason).then(function (data) {
                    if (data) {
                        closeOverlay();
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

        if (pageSizeEl) {
            if (!pageSizeEl.value) {
                pageSizeEl.value = String(defaultPageSize());
            } else if (window.matchMedia('(max-width: 900px)').matches && pageSizeEl.value === '20') {
                pageSizeEl.value = '10';
            }
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

        setFilter(currentFilter);
    }

    boot();
})();
