/**
 * 文件：assets/js/upgrade.js
 * 作用：系统升级页面交互（含数据库维护：全量对齐 / 版本升级）
 */

(function () {
    'use strict';

    var statusEl = document.getElementById('upgradeStatus');
    var checkBtn = document.getElementById('upgradeCheckBtn');
    var updateBtn = document.getElementById('upgradeApplyBtn');
    var migrateBtn = document.getElementById('upgradeMigrateBtn');
    var versionEl = document.getElementById('upgradeVersionDisplay');
    var lastCheck = null;
    var pendingCache = null;

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

    function postUpdate(action, fields) {
        var body = new FormData();
        body.append('action', action);
        body.append('csrf_token', window.VS_CSRF_TOKEN || '');
        if (fields) {
            Object.keys(fields).forEach(function (k) {
                body.append(k, fields[k]);
            });
        }
        return fetch((window.VS_BASE_URL || '') + '/admin/update.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
        }).then(function (res) { return res.json(); });
    }

    function fetchPendingVersions() {
        return postUpdate('migrate_schema_info').then(function (res) {
            if (res && res.code === 1 && Array.isArray(res.pending)) {
                pendingCache = res.pending;
                return pendingCache;
            }
            pendingCache = [];
            return pendingCache;
        }).catch(function () {
            pendingCache = [];
            return pendingCache;
        });
    }

    function chooserHtml() {
        return (
            '<div class="vs-db-maintain">' +
            '<p class="vs-db-maintain__lead">本页提供两种处理方式，请先选择，不要两个都乱点：</p>' +
            '<ul class="vs-db-maintain__list">' +
            '<li><strong>全量对齐</strong>：对照完整标准库表，' +
            '<span class="vs-db-emph vs-db-emph--safe">只补缺失的表/字段</span>，' +
            '<span class="vs-db-emph vs-db-emph--safe">不修改业务数据</span>，' +
            '<span class="vs-db-emph vs-db-emph--safe">不删除</span>已有内容。</li>' +
            '<li><strong>版本升级</strong>：按系统版本执行' +
            '<span class="vs-db-emph vs-db-emph--key">尚未完成</span>的数据库升级；' +
            '<span class="vs-db-emph vs-db-emph--danger">可能修改结构，也可能修改配置或数据</span>。</li>' +
            '</ul>' +
            '<p class="vs-db-maintain__hint">不确定时：有「某版本数据库未执行」→ 选' +
            '<strong>版本升级</strong>；只是缺字段报错 → 选<strong>全量对齐</strong>。</p>' +
            '</div>'
        );
    }

    function fullConfirmHtml() {
        return (
            '<div class="vs-db-maintain">' +
            '<p><strong>即将对照安装包内的完整标准库表进行结构补齐。</strong></p>' +
            '<ul class="vs-db-maintain__list">' +
            '<li><strong>会做：</strong>补缺失的表、字段、必要默认值等</li>' +
            '<li><strong>不会做：</strong>' +
            '<span class="vs-db-emph vs-db-emph--safe">不修改业务数据</span>；' +
            '不按版本跑升级脚本；' +
            '<span class="vs-db-emph vs-db-emph--safe">不删除</span>线上多出来的表/字段</li>' +
            '</ul>' +
            '<p class="vs-db-maintain__warn">请确认已备份数据库后再执行。</p>' +
            '</div>'
        );
    }

    function versionConfirmHtml(pending) {
        var listHtml;
        if (!pending || pending.length === 0) {
            listHtml = '<p class="vs-db-maintain__empty">当前<strong>没有</strong>待执行的版本升级。</p>';
        } else {
            listHtml =
                '<p><strong>待执行版本：</strong> ' +
                pending.map(function (v) {
                    return '<span class="vs-db-emph vs-db-emph--key">' + escapeHtml(v) + '</span>';
                }).join('、') +
                '</p>';
        }
        return (
            '<div class="vs-db-maintain">' +
            '<p><strong>即将按版本顺序执行尚未完成的数据库升级。</strong></p>' +
            listHtml +
            '<ul class="vs-db-maintain__list">' +
            '<li><strong>会做：</strong>执行上述版本附带的库脚本（结构变更' +
            '<span class="vs-db-emph vs-db-emph--danger">和/或配置、数据修正</span>）</li>' +
            '<li><strong>请注意：</strong>' +
            '<span class="vs-db-emph vs-db-emph--danger">本操作可能修改配置或业务相关数据</span>，请确认后再执行</li>' +
            '</ul>' +
            '<p class="vs-db-maintain__warn">请确认已备份数据库后再执行。</p>' +
            '</div>'
        );
    }

    function openDbChooser() {
        if (!window.VsModal || !window.VsModal.open) {
            window.alert('弹窗组件不可用');
            return;
        }
        VsModal.open({
            title: '数据库维护',
            html: chooserHtml(),
            size: 'dbmaintain',
            closeOnOverlay: false,
            buttons: [
                {
                    text: '取消',
                    action: function () { VsModal.close(false); },
                },
                {
                    text: '全量对齐',
                    action: function () { openFullConfirm(); },
                },
                {
                    text: '版本升级',
                    primary: true,
                    action: function () {
                        fetchPendingVersions().then(function (pending) {
                            openVersionConfirm(pending);
                        });
                    },
                },
            ],
        });
    }

    function openFullConfirm() {
        VsModal.open({
            title: '全量对齐 · 确认',
            html: fullConfirmHtml(),
            size: 'dbmaintain',
            closeOnOverlay: false,
            buttons: [
                {
                    text: '返回',
                    action: function () { openDbChooser(); },
                },
                {
                    text: '确认执行',
                    primary: true,
                    action: function () { runMaintain('full'); },
                },
            ],
        });
    }

    function openVersionConfirm(pending) {
        var hasPending = pending && pending.length > 0;
        VsModal.open({
            title: '版本升级 · 确认',
            html: versionConfirmHtml(pending || []),
            size: 'dbmaintain',
            closeOnOverlay: false,
            buttons: [
                {
                    text: '返回',
                    action: function () { openDbChooser(); },
                },
                {
                    text: '确认执行',
                    primary: true,
                    danger: true,
                    disabled: !hasPending,
                    action: function () {
                        if (!hasPending) {
                            return;
                        }
                        runMaintain('version');
                    },
                },
            ],
        });
    }

    function runMaintain(mode) {
        if (migrateBtn) {
            migrateBtn.disabled = true;
        }
        var doing = mode === 'full' ? '正在全量对齐，请勿关闭…' : '正在执行版本升级，请勿关闭…';
        setStatus(doing, 'info');
        VsModal.open({
            title: mode === 'full' ? '全量对齐' : '版本升级',
            html: '<div class="vs-db-maintain"><p class="vs-db-maintain__busy">' + escapeHtml(doing) + '</p></div>',
            size: 'dbmaintain',
            closeOnOverlay: false,
            closeOnEscape: false,
            buttons: [],
        });

        postUpdate('migrate_schema', { mode: mode })
            .then(function (res) {
                if (res && res.code === 1) {
                    var okMsg = res.msg || (mode === 'full' ? '全量对齐已完成' : '版本升级已完成');
                    setStatus(okMsg, 'success');
                    if (window.VsModal && window.VsModal.alert) {
                        VsModal.alert(okMsg, mode === 'full' ? '全量对齐完成' : '版本升级完成');
                    }
                } else {
                    var errMsg = (res && res.msg) || (mode === 'full' ? '全量对齐失败' : '版本升级失败');
                    setStatus(errMsg, 'error');
                    if (window.VsModal && window.VsModal.alert) {
                        VsModal.alert(errMsg, mode === 'full' ? '全量对齐失败' : '版本升级失败');
                    }
                }
            })
            .catch(function () {
                setStatus('网络异常，请稍后重试', 'error');
                if (window.VsModal && window.VsModal.alert) {
                    VsModal.alert('网络异常，请稍后重试', '操作失败');
                }
            })
            .finally(function () {
                if (migrateBtn) {
                    migrateBtn.disabled = false;
                }
            });
    }

    if (migrateBtn) {
        migrateBtn.addEventListener('click', function () {
            openDbChooser();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (checkBtn) {
            checkBtn.click();
        }
    });
})();
