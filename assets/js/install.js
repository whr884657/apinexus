/**
 * 文件：assets/js/install.js
 * 作用：ApiNexus 安装向导交互脚本（含开源许可强制确认）
 * @version 1.1.0
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

    function unlockInstallAfterLicense() {
        var hint = document.getElementById('licenseGateHint');
        if (hint) {
            hint.hidden = true;
        }
        var nginxPanel = document.getElementById('installNginxPanel');
        if (nginxPanel) {
            nginxPanel.hidden = false;
        }
        var checks = document.getElementById('installEnvChecks');
        if (checks) {
            checks.hidden = false;
        }
        var next = document.getElementById('installEnvNext');
        if (next) {
            next.hidden = false;
        }
        if (window.VS_INSTALL_LICENSE) {
            window.VS_INSTALL_LICENSE.accepted = 1;
        }
    }

    function bindNginxCopy() {
        var btn = document.getElementById('nginxCopyBtn');
        var pre = document.getElementById('nginxSnippetPre');
        if (!btn || !pre) {
            return;
        }
        btn.addEventListener('click', function () {
            var text = pre.textContent || '';
            function ok() {
                if (window.VsToast) {
                    VsToast.show('已复制到剪贴板', 'success');
                } else {
                    showAlert('已复制到剪贴板');
                }
            }
            function fail() {
                if (window.VsToast) {
                    VsToast.show('复制失败，请手动选中复制', 'error');
                } else {
                    showAlert('复制失败，请手动选中复制');
                }
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(ok).catch(fail);
            } else {
                try {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    var done = document.execCommand('copy');
                    document.body.removeChild(ta);
                    if (done) {
                        ok();
                    } else {
                        fail();
                    }
                } catch (e) {
                    fail();
                }
            }
        });
    }

    function openLicenseGate() {
        var boot = window.VS_INSTALL_LICENSE || {};
        if (boot.accepted) {
            return;
        }
        if (!window.VsModal) {
            showAlert('请先同意开源许可协议后继续安装');
            return;
        }

        var html = ''
            + '<p class="vs-license-hint">请滚动阅读完整条款。未读完并勾选同意前，无法关闭本弹窗，也无法继续安装。</p>'
            + (boot.html || '<p>许可内容加载失败</p>')
            + '<label class="vs-license-agree" id="vsLicenseAgreeLabel">'
            + '<input type="checkbox" id="vsLicenseAgreeChk" disabled>'
            + '<span>我已完整阅读并同意《ApiNexus 开源许可与部署使用条款》及 MIT License；理解本软件按原样提供、无任何担保，部署与运营中出现的任何问题由我自行承担，与作者无关。</span>'
            + '</label>';

        VsModal.open({
            title: '开源许可与部署使用条款',
            size: 'license',
            html: html,
            closeOnOverlay: false,
            closeOnEscape: false,
            buttons: [
                {
                    id: 'vsLicenseAcceptBtn',
                    text: '请先阅读到底并勾选同意',
                    primary: true,
                    disabled: true,
                    action: function (btn) {
                        var chk = document.getElementById('vsLicenseAgreeChk');
                        if (!chk || !chk.checked) {
                            return;
                        }
                        btn.disabled = true;
                        btn.textContent = '提交中…';
                        var body = new FormData();
                        body.append('action', 'accept_license');
                        body.append('agree', '1');
                        fetch(window.location.href, {
                            method: 'POST',
                            body: body,
                            credentials: 'same-origin'
                        })
                            .then(function (res) { return res.json(); })
                            .then(function (data) {
                                if (data && data.code === 1) {
                                    unlockInstallAfterLicense();
                                    VsModal.close(true);
                                    return;
                                }
                                btn.disabled = false;
                                btn.textContent = '我已知晓并同意，开始安装';
                                showAlert((data && data.msg) || '确认失败，请重试');
                            })
                            .catch(function () {
                                btn.disabled = false;
                                btn.textContent = '我已知晓并同意，开始安装';
                                showAlert('网络异常，请稍后重试');
                            });
                    }
                }
            ]
        });

        var bodyEl = document.getElementById('vsModalBody');
        var chk = document.getElementById('vsLicenseAgreeChk');
        var btn = document.getElementById('vsLicenseAcceptBtn');
        var scrolled = false;

        function refreshAcceptState() {
            if (!btn || !chk) {
                return;
            }
            var ok = scrolled && chk.checked;
            btn.disabled = !ok;
            btn.textContent = ok
                ? '我已知晓并同意，开始安装'
                : (scrolled ? '请勾选同意后再继续' : '请先阅读到底并勾选同意');
            chk.disabled = !scrolled;
        }

        function onScroll() {
            if (!bodyEl || scrolled) {
                return;
            }
            var remain = bodyEl.scrollHeight - bodyEl.scrollTop - bodyEl.clientHeight;
            if (remain <= 24) {
                scrolled = true;
                refreshAcceptState();
            }
        }

        if (bodyEl) {
            bodyEl.addEventListener('scroll', onScroll);
            // 内容过短（无需滚动）直接视为读完
            setTimeout(function () {
                if (bodyEl.scrollHeight <= bodyEl.clientHeight + 8) {
                    scrolled = true;
                    refreshAcceptState();
                }
            }, 80);
        }
        if (chk) {
            chk.addEventListener('change', refreshAcceptState);
        }
        refreshAcceptState();
    }

    document.addEventListener('DOMContentLoaded', function () {
        openLicenseGate();
        bindNginxCopy();

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
                        showDbMessage('网络异常，请稍后重试');
                    })
                    .finally(function () {
                        testBtn.disabled = false;
                    });
            });
        }
    });
})();
