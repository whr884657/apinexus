/**
 * 文件：assets/js/install.js
 * 作用：ApiNexus 安装向导交互脚本
 * @version 1.0.0
 */

(function () {
    'use strict';

    function showAlert(message) {
        if (window.VsModal) {
            return VsModal.alert(message);
        }
        alert(message);
        return Promise.resolve();
    }

    function showConfirm(message, title, options) {
        if (window.VsModal) {
            return VsModal.confirm(message, title, options);
        }
        return Promise.resolve(confirm(message));
    }

    document.addEventListener('DOMContentLoaded', function () {
        var adminForm = document.getElementById('adminForm');
        if (adminForm) {
            adminForm.addEventListener('submit', function (e) {
                var pwd = adminForm.querySelector('[name="admin_password"]');
                var pwd2 = adminForm.querySelector('[name="admin_password2"]');
                if (pwd && pwd2 && pwd.value !== pwd2.value) {
                    e.preventDefault();
                    showAlert('两次输入的密码不一致');
                }
            });
        }

        var clearDbForm = document.getElementById('clearDbForm');
        var clearDbBtn = document.getElementById('clearDbBtn');
        if (clearDbForm && clearDbBtn) {
            clearDbBtn.addEventListener('click', function () {
                showConfirm(
                    '确定要清空所有相关数据表并重新创建吗？此操作不可恢复！',
                    '清空数据库确认',
                    { confirmText: '清空并重建', danger: true }
                ).then(function (ok) {
                    if (ok) {
                        clearDbForm.submit();
                    }
                });
            });
        }

        var dbForm = document.getElementById('dbForm');
        var testBtn = document.getElementById('testDbBtn');
        var nextBtn = document.getElementById('dbNextBtn');
        var messageEl = document.getElementById('dbTestMessage');

        function showDbMessage(text, type) {
            if (window.VsToast) {
                VsToast.show(text, type === 'error' ? 'error' : 'success');
                if (messageEl) messageEl.hidden = true;
                return;
            }
            if (!messageEl) return;
            messageEl.textContent = text;
            messageEl.className = 'vs-alert vs-alert--' + type;
            messageEl.hidden = false;
        }

        function hideDbMessage() {
            if (messageEl) messageEl.hidden = true;
        }

        function markDbUntested() {
            if (nextBtn) nextBtn.style.display = 'none';
        }

        if (dbForm && testBtn) {
            dbForm.querySelectorAll('input').forEach(function (input) {
                input.addEventListener('input', markDbUntested);
            });

            testBtn.addEventListener('click', function () {
                hideDbMessage();
                markDbUntested();

                var username = dbForm.querySelector('[name="username"]');
                var dbname = dbForm.querySelector('[name="dbname"]');
                if (!username.value.trim() || !dbname.value.trim()) {
                    showDbMessage('请填写数据库用户名和数据库名', 'error');
                    return;
                }

                testBtn.disabled = true;
                var body = new FormData(dbForm);
                body.append('action', 'test_db');

                fetch(window.location.href, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.code === 1) {
                            showDbMessage(data.msg || '数据库连接成功！', 'success');
                            if (nextBtn) nextBtn.style.display = '';
                        } else {
                            showDbMessage(data.msg || '连接失败', 'error');
                        }
                    })
                    .catch(function () {
                        showDbMessage('网络异常，请稍后重试', 'error');
                    })
                    .finally(function () {
                        testBtn.disabled = false;
                    });
            });
        }
    });
})();
