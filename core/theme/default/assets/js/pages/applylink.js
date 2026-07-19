/**
 * 申请友链页：AJAX 提交
 */
(function () {
    'use strict';

    var form = document.getElementById('applyLinkForm');
    if (!form || !window.VS || typeof VS.postForm !== 'function') {
        return;
    }

    var alertEl = document.getElementById('applyAlert');
    var btn = document.getElementById('applySubmitBtn');

    function showAlert(type, msg) {
        if (!alertEl) {
            return;
        }
        alertEl.hidden = false;
        alertEl.className = 'alert alert-' + (type === 'error' ? 'error' : 'success');
        alertEl.textContent = msg || '';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn) {
            btn.disabled = true;
        }
        VS.postForm(form, form.getAttribute('action') || window.location.href)
            .then(function (data) {
                if (!data || !data.code) {
                    throw new Error((data && data.msg) || '提交失败');
                }
                showAlert('success', data.msg || '申请已提交，请等待审核');
                if (window.VS && typeof VS.showMessage === 'function') {
                    VS.showMessage(data.msg || '申请已提交', 'success');
                }
                form.reset();
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : '提交失败';
                if (msg === 'invalid_json') {
                    msg = '提交失败，请稍后重试';
                }
                showAlert('error', msg);
                if (window.VS && typeof VS.showMessage === 'function') {
                    VS.showMessage(msg, 'error');
                }
            })
            .then(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
    });
})();
