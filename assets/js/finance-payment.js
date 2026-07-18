/**
 * 文件：assets/js/finance-payment.js
 * 作用：支付配置保存
 */
(function () {
    'use strict';
    var form = document.getElementById('payConfigForm');
    if (!form || !window.VS) {
        return;
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('payConfigSaveBtn');
        if (btn) {
            btn.disabled = true;
        }
        VS.postForm(form).then(function (data) {
            if (btn) {
                btn.disabled = false;
            }
            if (!data || data.code !== 1) {
                VS.showMessage((data && data.msg) || '保存失败', 'error');
                return;
            }
            VS.showMessage(data.msg || '已保存', 'success');
        }).catch(function () {
            if (btn) {
                btn.disabled = false;
            }
            VS.showMessage('网络异常', 'error');
        });
    });
})();
