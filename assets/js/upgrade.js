/**
 * 文件：assets/js/upgrade.js
 * 作用：系统升级页面交互
 */

(function () {
    'use strict';

    var statusEl = document.getElementById('upgradeStatus');
    var checkBtn = document.getElementById('upgradeCheckBtn');
    var updateBtn = document.getElementById('upgradeApplyBtn');
    var migrateBtn = document.getElementById('upgradeMigrateBtn');
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

    function runMigrateSchema() {
        migrateBtn.disabled = true;
        setStatus('正在执行数据库结构更新…', 'info');
        var body = new FormData();
        body.append('action', 'migrate_schema');
        body.append('csrf_token', window.VS_CSRF_TOKEN || '');
        fetch((window.VS_BASE_URL || '') + '/admin/update.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
        })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res && res.code === 1) {
                    setStatus(res.msg || '结构更新完成', 'success');
                    if (window.VsModal && window.VsModal.alert) {
                        VsModal.alert(res.msg || '数据库结构更新已完成。', '结构更新完成');
                    }
                } else {
                    var errMsg = (res && res.msg) || '结构更新失败';
                    setStatus(errMsg, 'error');
                    if (window.VsModal && window.VsModal.alert) {
                        VsModal.alert(errMsg, '结构更新失败');
                    }
                }
            })
            .catch(function () {
                setStatus('网络异常，请稍后重试', 'error');
                if (window.VsModal && window.VsModal.alert) {
                    VsModal.alert('网络异常，请稍后重试', '结构更新失败');
                }
            })
            .finally(function () {
                migrateBtn.disabled = false;
            });
    }

    function confirmMigrateSchema() {
        var html =
            '<div class="vs-update-modal">' +
            '<p>此按钮是<strong>手动兜底</strong>：在「安装更新」之后，若本版需要改数据库结构，但自动结构更新失败，或后台提示库结构尚未就绪时，再单独点一次补跑结构更新。</p>' +
            '<p>正常安装更新时，若本版含数据库变更，系统会在更新流程里自动执行，<strong>平时不必点此按钮</strong>，以免误触。</p>' +
            '<p>继续前请确认：已备份数据库；且确因更新后库结构异常才使用本操作。</p>' +
            '</div>';

        if (window.VsModal && window.VsModal.confirm) {
            return VsModal.confirm('', '执行数据库结构更新？', {
                html: html,
                confirmText: '确认执行',
                cancelText: '取消',
                closeOnOverlay: false,
            });
        }
        return Promise.resolve(window.confirm(
            '此按钮用于安装更新后数据库结构失败时的手动兜底，平时不必点击。确定继续执行吗？'
        ));
    }

    if (migrateBtn) {
        migrateBtn.addEventListener('click', function () {
            confirmMigrateSchema().then(function (ok) {
                if (ok) {
                    runMigrateSchema();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (checkBtn) {
            checkBtn.click();
        }
    });
})();
