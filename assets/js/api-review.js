/**
 * 文件：assets/js/api-review.js
 * 作用：后台接口审核（筛选、通过、不通过弹窗）
 */
(function () {
    'use strict';

    var page = document.getElementById('apiReviewPage');
    if (!page || !window.VS) {
        return;
    }

    var table = document.getElementById('apiReviewTable');
    var emptyAll = document.getElementById('apiReviewEmpty');
    var emptyFilter = document.getElementById('apiReviewFilterEmpty');
    var overlay = document.getElementById('apiReviewRejectOverlay');
    var reasonInput = document.getElementById('apiReviewRejectReason');
    var confirmBtn = document.getElementById('apiReviewRejectConfirm');
    // 审核队列默认看待审；「全部」仍可切换
    var currentFilter = '0';
    var rejectApiId = '';

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

    function applyFilter(filter) {
        currentFilter = String(filter == null ? '0' : filter);
        document.querySelectorAll('.vs-api-review-filter').forEach(function (btn) {
            var on = String(btn.getAttribute('data-filter')) === currentFilter;
            btn.classList.toggle('vs-btn--primary', on);
            btn.classList.toggle('vs-btn--default', !on);
            btn.classList.toggle('is-active', on);
        });
        if (!table) {
            if (emptyAll) {
                emptyAll.hidden = false;
            }
            if (emptyFilter) {
                emptyFilter.hidden = true;
            }
            return;
        }
        var visible = 0;
        var total = 0;
        table.querySelectorAll('.vs-api-review-row').forEach(function (row) {
            total += 1;
            var audit = String(row.getAttribute('data-audit') || '');
            var show = currentFilter === 'all' || audit === currentFilter;
            row.hidden = !show;
            if (show) {
                visible += 1;
            }
        });
        if (emptyFilter) {
            emptyFilter.hidden = !(total > 0 && visible === 0);
        }
        table.hidden = total === 0 || visible === 0;
        if (emptyAll) {
            emptyAll.hidden = total > 0;
        }
    }

    function renderActions(actions, audit) {
        if (!actions) {
            return;
        }
        var html = '';
        if (audit !== '1') {
            html += '<button type="button" class="vs-btn vs-btn--primary vs-api-review-action" data-audit="1">通过</button>';
        }
        if (audit !== '2') {
            html += '<button type="button" class="vs-btn vs-btn--danger vs-api-review-deny" data-audit="2">不通过</button>';
        }
        actions.innerHTML = html;
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
            var row = table ? table.querySelector('.vs-api-review-row[data-api-id="' + apiId + '"]') : null;
            if (!row) {
                return data;
            }
            row.setAttribute('data-audit', String(audit));
            var label = row.querySelector('[data-field="audit_label"]');
            if (label) {
                label.textContent = data.audit_label || '';
                label.className = 'vs-api-list-audit ' + (data.audit_class || '');
            }
            var reasonEl = row.querySelector('[data-field="rejectreason"]');
            if (reasonEl) {
                var r = data.rejectreason || '';
                if (r) {
                    reasonEl.hidden = false;
                    reasonEl.textContent = '原因：' + r;
                } else {
                    reasonEl.hidden = true;
                    reasonEl.textContent = '';
                }
            }
            renderActions(row.querySelector('.vs-api-review-row__actions'), String(audit));
            applyFilter(currentFilter);
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
        if (filterBtn) {
            applyFilter(filterBtn.getAttribute('data-filter') || '0');
            return;
        }

        var denyBtn = e.target.closest('.vs-api-review-deny');
        if (denyBtn && page.contains(denyBtn)) {
            var row = denyBtn.closest('.vs-api-review-row');
            if (!row) {
                return;
            }
            rejectApiId = row.getAttribute('data-api-id') || '';
            openOverlay();
            return;
        }

        var btn = e.target.closest('.vs-api-review-action');
        if (btn && page.contains(btn)) {
            var rowPass = btn.closest('.vs-api-review-row');
            if (!rowPass) {
                return;
            }
            postAudit(rowPass.getAttribute('data-api-id'), btn.getAttribute('data-audit'), '');
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

    applyFilter(currentFilter);
})();
