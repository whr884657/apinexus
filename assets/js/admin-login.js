/**
 * 文件：assets/js/admin-login.js
 * 作用：ApiNexus 管理员登录页交互脚本
 * @version 1.0.0
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('loginForm');
        if (!form) return;

        form.addEventListener('submit', function () {
            var btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '登录中...';
            }
        });
    });
})();
