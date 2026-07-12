/**
 * 文件：assets/js/api-review.js
 * 作用：后台接口审核操作
 */
(function () {
    'use strict';

    var page = document.getElementById('apiReviewPage');
    var rejectModal = document.getElementById('apiRejectModal');
    var rejectReason = document.getElementById('apiRejectReason');
    var rejectConfirmBtn = document.getElementById('apiRejectConfirmBtn');
    var pendingRejectId = 0;

    if (!page) {
        return;
    }

    function postAction(action, apiId, extra) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('api_id', String(apiId));
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                fd.append(key, extra[key]);
            });
        }
        return window.VS.postForm(fd, window.location.pathname + window.location.search);
    }

    function statusBadgeHtml(label, status) {
        var cls = 'vs-api-status';
        if (status === 'pending') {
            cls += ' vs-api-status--pending';
        } else if (status === 'approved') {
            cls += ' vs-api-status--approved';
        } else if (status === 'rejected') {
            cls += ' vs-api-status--rejected';
        } else {
            cls += ' vs-api-status--offline';
        }
        return '<span class="' + cls + '">' + label + '</span>';
    }

    function removeRow(apiId) {
        var row = page.querySelector('[data-api-row="' + apiId + '"]');
        if (row) {
            row.remove();
        }
        if (!page.querySelector('[data-api-row]')) {
            window.location.reload();
        }
    }

    function openRejectModal(apiId) {
        pendingRejectId = apiId;
        if (rejectReason) {
            rejectReason.value = '';
        }
        if (rejectModal) {
            rejectModal.hidden = false;
            document.body.classList.add('vs-modal-open');
        }
    }

    function closeRejectModal() {
        pendingRejectId = 0;
        if (rejectModal) {
            rejectModal.hidden = true;
            document.body.classList.remove('vs-modal-open');
        }
    }

    if (rejectModal) {
        rejectModal.querySelectorAll('[data-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeRejectModal);
        });
    }

    if (rejectConfirmBtn) {
        rejectConfirmBtn.addEventListener('click', function () {
            if (!pendingRejectId) {
                return;
            }
            var reason = rejectReason ? rejectReason.value.trim() : '';
            if (!reason) {
                window.VS.showMessage('请填写拒绝原因', 'error');
                return;
            }
            rejectConfirmBtn.disabled = true;
            postAction('reject', pendingRejectId, { reject_reason: reason })
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '操作失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已拒绝', 'success');
                    closeRejectModal();
                    if (window.location.search.indexOf('status=pending') !== -1) {
                        removeRow(pendingRejectId);
                    } else {
                        window.location.reload();
                    }
                })
                .catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    rejectConfirmBtn.disabled = false;
                });
        });
    }

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('.vs-api-action-btn');
        if (!btn) {
            return;
        }
        var action = btn.getAttribute('data-api-action');
        var apiId = parseInt(btn.getAttribute('data-api-id'), 10);
        if (!action || !apiId) {
            return;
        }

        if (action === 'reject') {
            openRejectModal(apiId);
            return;
        }

        if (action === 'offline' && !window.confirm('确定下线该接口？')) {
            return;
        }

        btn.disabled = true;
        postAction(action, apiId)
            .then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '操作成功', 'success');
                if (window.location.search.indexOf('status=pending') !== -1 || window.location.search.indexOf('status=approved') !== -1) {
                    removeRow(apiId);
                } else {
                    var row = page.querySelector('[data-api-row="' + apiId + '"]');
                    if (row && data.status_label) {
                        var cell = row.querySelector('.vs-api-review-status-cell');
                        if (cell) {
                            cell.innerHTML = statusBadgeHtml(data.status_label, data.status || '');
                        }
                        row.setAttribute('data-api-status', data.status || '');
                        var actions = row.querySelector('.vs-api-review-actions');
                        if (actions) {
                            actions.innerHTML = '<span class="vs-api-review-meta">—</span>';
                        }
                    }
                }
            })
            .catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            })
            .finally(function () {
                btn.disabled = false;
            });
    });
})();
