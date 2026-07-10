/**
 * 文件：assets/js/upgrade.js
 * 作用：系统升级页面交互
 */

(function () {
    'use strict';

    var statusEl = document.getElementById('upgradeStatus');
    var checkBtn = document.getElementById('upgradeCheckBtn');
    var updateBtn = document.getElementById('upgradeApplyBtn');
    var versionEl = document.getElementById('upgradeVersionDisplay');
    var lastCheck = null;

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderVersionDisplay(res) {
        if (!versionEl || !res) {
            return;
        }

        var local = 'v' + (res.local_version || '');
        if (res.code === 1 && res.update_available && res.remote_version) {
            var remote = 'v' + res.remote_version;
            versionEl.innerHTML =
                '<span class="vs-version-display">' +
                '<span class="vs-version-display__current">' + escapeHtml(local) + '</span>' +
                '<span class="vs-version-display__arrow" aria-hidden="true">→</span>' +
                '<span class="vs-version-display__new vs-version-display__new--inline">' +
                '<span class="vs-version-display__badge">新</span>' +
                escapeHtml(remote) +
                '</span></span>';
        } else {
            versionEl.textContent = local;
        }
    }

    function setStatus(text, type) {
        type = type || 'info';
        if (text && window.VsToast) {
            var toastType = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
            var duration = type === 'warning' ? 4200 : 2600;
            VsToast.show(text, toastType, duration);
        }
        if (statusEl) {
            statusEl.hidden = true;
        }
    }

    function renderCheckResult(res) {
        lastCheck = res;
        renderVersionDisplay(res);

        if (res.code !== 1) {
            setStatus(res.msg || '检测失败', 'error');
            if (updateBtn) updateBtn.disabled = true;
            return;
        }

        if (res.update_available) {
            var tip = '发现新版本 v' + res.remote_version + '（当前 v' + res.local_version + '）';
            if (res.latest_remote_version && res.latest_remote_version !== res.remote_version) {
                tip += '，将逐版升级至 v' + res.latest_remote_version;
            }
            setStatus(tip, 'warning');
            if (updateBtn) updateBtn.disabled = false;
        } else if (res.ahead_of_remote) {
            setStatus('当前版本 v' + res.local_version + ' 高于仓库版本（测试环境）', 'info');
            if (updateBtn) updateBtn.disabled = true;
        } else {
            setStatus('当前已是最新版本 v' + res.local_version, 'success');
            if (updateBtn) updateBtn.disabled = true;
        }
    }

    if (checkBtn) {
        checkBtn.addEventListener('click', function () {
            checkBtn.disabled = true;
            setStatus('正在检测云端最新版本…', 'info');
            VsUpdate.check({ onResult: renderCheckResult })
                .catch(function () {
                    setStatus('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    checkBtn.disabled = false;
                });
        });
    }

    if (updateBtn) {
        updateBtn.disabled = true;
        updateBtn.addEventListener('click', function () {
            if (!lastCheck || !lastCheck.update_available) {
                setStatus('请先检测更新', 'error');
                return;
            }
            VsUpdate.showModal(lastCheck, {
                hideDismiss: true,
                cancelText: '取消',
                confirmText: '继续更新',
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (checkBtn) {
            checkBtn.click();
        }
    });
})();
