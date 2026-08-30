/**
 * 用户中心 · IP 配置（白名单 + 出口代理）
 * 添加/编辑使用 vs-overlay（桌面居中弹窗，窄屏底部抽屉）
 */
(function () {
    var page = document.getElementById('userIpPage');
    if (!page || !window.VS) {
        return;
    }

    var maxCount = parseInt(page.getAttribute('data-max') || '32', 10) || 32;
    var proxyMax = parseInt(page.getAttribute('data-proxy-max') || '5', 10) || 5;
    var proxyReady = page.getAttribute('data-proxy-ready') === '1';
    var clientIp = String(page.getAttribute('data-client-ip') || '');

    var tabs = document.getElementById('userIpTabs');
    var listEl = document.getElementById('userIpList');
    var emptyEl = document.getElementById('userIpEmpty');
    var countEl = document.getElementById('userIpCount');
    var openAllowBtn = document.getElementById('userIpOpenAdd');
    var allowOverlay = document.getElementById('userIpAllowOverlay');
    var allowForm = document.getElementById('userIpAddForm');
    var allowInput = document.getElementById('userIpInput');
    var fillCurrentBtn = document.getElementById('userIpFillCurrent');
    var allowSubmitBtn = document.getElementById('userIpAddBtn');

    var openProxyBtn = document.getElementById('userProxyOpenAdd');
    var proxyOverlay = document.getElementById('userProxyFormOverlay');
    var proxyForm = document.getElementById('userProxyForm');
    var proxyFormTitle = document.getElementById('userProxyFormTitle');
    var proxyMode = document.getElementById('userProxyMode');
    var proxyListEl = document.getElementById('userProxyList');
    var proxyEmptyEl = document.getElementById('userProxyEmpty');
    var proxyCountEl = document.getElementById('userProxyCount');
    var strategySel = document.getElementById('userProxyStrategy');
    var strategySave = document.getElementById('userProxyStrategySave');
    var proxySaveBtn = document.getElementById('userProxySaveBtn');

    var busy = false;
    var proxyCache = [];

    if (allowOverlay && allowOverlay.parentNode !== document.body) {
        document.body.appendChild(allowOverlay);
    }
    if (proxyOverlay && proxyOverlay.parentNode !== document.body) {
        document.body.appendChild(proxyOverlay);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function postAction(action, payload) {
        var fd = new FormData();
        fd.append('action', action);
        if (payload) {
            Object.keys(payload).forEach(function (key) {
                if (payload[key] === undefined || payload[key] === null) {
                    return;
                }
                fd.append(key, payload[key]);
            });
        }
        return window.VS.postForm(fd);
    }

    function openOverlay(el) {
        if (!el) {
            return;
        }
        el._returnFocus = document.activeElement;
        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
        var focusTarget = el.querySelector('[autofocus]')
            || el.querySelector('.vs-overlay__body input:not([type="hidden"]), .vs-overlay__body select, .vs-overlay__body textarea')
            || el.querySelector('.vs-overlay__close');
        if (focusTarget) {
            setTimeout(function () {
                focusTarget.focus();
            }, 50);
        }
    }

    function closeOverlay(el) {
        if (!el) {
            return;
        }
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        el.classList.remove('is-open');
        if (!document.querySelector('.vs-overlay.is-open')) {
            document.body.classList.remove('is-overlay-open');
        }
        if (el._returnFocus && typeof el._returnFocus.focus === 'function') {
            try {
                el._returnFocus.focus();
            } catch (err) { /* ignore */ }
            el._returnFocus = null;
        }
    }

    function bindOverlayClose(overlay) {
        if (!overlay) {
            return;
        }
        overlay.addEventListener('click', function (e) {
            var closer = e.target && e.target.closest
                ? e.target.closest('[data-overlay-close]')
                : null;
            if (closer && closer.getAttribute('data-overlay-close') === '1') {
                closeOverlay(overlay);
            }
        });
    }
    bindOverlayClose(allowOverlay);
    bindOverlayClose(proxyOverlay);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (proxyOverlay && proxyOverlay.classList.contains('is-open')) {
            closeOverlay(proxyOverlay);
            return;
        }
        if (allowOverlay && allowOverlay.classList.contains('is-open')) {
            closeOverlay(allowOverlay);
        }
    });

    function setBusy(on) {
        busy = !!on;
        if (allowSubmitBtn) {
            allowSubmitBtn.disabled = busy;
        }
        if (openAllowBtn) {
            var allowCount = listEl ? listEl.querySelectorAll('.vs-user-ip__item').length : 0;
            openAllowBtn.disabled = busy || allowCount >= maxCount;
        }
        if (proxySaveBtn) {
            proxySaveBtn.disabled = busy;
        }
        if (openProxyBtn) {
            openProxyBtn.disabled = busy || proxyCache.length >= proxyMax;
        }
        if (strategySave) {
            strategySave.disabled = busy;
        }
    }

    function switchTab(name) {
        var panels = page.querySelectorAll('[data-ip-panel]');
        var buttons = tabs ? tabs.querySelectorAll('[data-ip-tab]') : [];
        Array.prototype.forEach.call(buttons, function (btn) {
            var on = btn.getAttribute('data-ip-tab') === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(panels, function (panel) {
            panel.hidden = panel.getAttribute('data-ip-panel') !== name;
        });
    }

    if (tabs) {
        tabs.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ip-tab]');
            if (!btn) {
                return;
            }
            switchTab(btn.getAttribute('data-ip-tab'));
        });
    }

    function renderList(list) {
        list = Array.isArray(list) ? list : [];
        if (countEl) {
            countEl.textContent = list.length + ' / ' + maxCount;
        }
        if (emptyEl) {
            emptyEl.hidden = list.length > 0;
        }
        if (!listEl) {
            return;
        }
        listEl.hidden = list.length === 0;
        listEl.innerHTML = list.map(function (ip) {
            var safe = escapeHtml(ip);
            return '<li class="vs-user-ip__item" data-ip="' + safe + '">'
                + '<code class="vs-user-ip__item-ip">' + safe + '</code>'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-btn--sm vs-user-ip__remove" data-ip="' + safe + '">移除</button>'
                + '</li>';
        }).join('');
        if (openAllowBtn) {
            openAllowBtn.disabled = busy || list.length >= maxCount;
        }
    }

    function doAdd(ip) {
        if (busy) {
            return;
        }
        ip = String(ip || '').trim();
        if (!ip) {
            window.VS.showMessage('请输入 IP 地址', 'error');
            return;
        }
        setBusy(true);
        postAction('add', { ip: ip }).then(function (data) {
            setBusy(false);
            if (!data || data.code !== 1) {
                window.VS.showMessage((data && data.msg) || '添加失败', 'error');
                return;
            }
            window.VS.showMessage(data.msg || '已添加', 'success');
            if (allowInput) {
                allowInput.value = '';
            }
            renderList(data.list);
            closeOverlay(allowOverlay);
        }).catch(function () {
            setBusy(false);
            window.VS.showMessage('网络异常，请稍后重试', 'error');
        });
    }

    if (openAllowBtn) {
        openAllowBtn.addEventListener('click', function () {
            if (allowInput) {
                allowInput.value = '';
            }
            openOverlay(allowOverlay);
        });
    }

    if (fillCurrentBtn) {
        fillCurrentBtn.addEventListener('click', function () {
            if (!clientIp || !allowInput) {
                return;
            }
            allowInput.value = clientIp;
            allowInput.focus();
        });
    }

    if (allowForm) {
        allowForm.addEventListener('submit', function (e) {
            e.preventDefault();
            doAdd(allowInput ? allowInput.value : '');
        });
    }

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-user-ip__remove');
            if (!btn || busy) {
                return;
            }
            var ip = btn.getAttribute('data-ip') || '';
            if (!ip) {
                return;
            }
            var confirmRm = window.VsModal && window.VsModal.confirm
                ? window.VsModal.confirm('确定从白名单移除 ' + ip + '？', '移除 IP')
                : Promise.resolve(window.confirm('确定从白名单移除 ' + ip + '？'));
            confirmRm.then(function (ok) {
                if (!ok) {
                    return;
                }
                setBusy(true);
                return postAction('remove', { ip: ip }).then(function (data) {
                    setBusy(false);
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '移除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已移除', 'success');
                    renderList(data.list);
                });
            }).catch(function () {
                setBusy(false);
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        });
    }

    if (!proxyReady) {
        return;
    }

    function syncModeFields() {
        var isExtract = proxyMode && String(proxyMode.value) === '1';
        var root = proxyOverlay || document;
        Array.prototype.forEach.call(root.querySelectorAll('.vs-user-ip__field-tunnel'), function (el) {
            el.hidden = !!isExtract;
        });
        Array.prototype.forEach.call(root.querySelectorAll('.vs-user-ip__field-extract'), function (el) {
            el.hidden = !isExtract;
        });
    }

    function findProxy(id) {
        id = parseInt(id, 10) || 0;
        for (var i = 0; i < proxyCache.length; i++) {
            if (parseInt(proxyCache[i].id, 10) === id) {
                return proxyCache[i];
            }
        }
        return null;
    }

    function renderProxyList(list) {
        proxyCache = Array.isArray(list) ? list.slice() : [];
        if (proxyCountEl) {
            proxyCountEl.textContent = proxyCache.length + ' / ' + proxyMax;
        }
        if (proxyEmptyEl) {
            proxyEmptyEl.hidden = proxyCache.length > 0;
        }
        if (openProxyBtn) {
            openProxyBtn.disabled = busy || proxyCache.length >= proxyMax;
        }
        if (!proxyListEl) {
            return;
        }
        proxyListEl.hidden = proxyCache.length === 0;
        proxyListEl.innerHTML = proxyCache.map(function (p) {
            var id = parseInt(p.id, 10) || 0;
            var title = escapeHtml(p.title || ('#' + id));
            var modeLabel = escapeHtml(p.modelabel || p.mode_label || '');
            var protoLabel = escapeHtml(p.protolabel || p.proto_label || '');
            var statusOn = parseInt(p.status, 10) === 1;
            var endpoint = parseInt(p.mode, 10) === 1
                ? (p.extract || '—')
                : ((p.host || '') + (p.port ? (':' + p.port) : ''));
            endpoint = escapeHtml(endpoint || '—');
            return '<li class="vs-user-ip__proxy-item" data-id="' + id + '">'
                + '<div class="vs-user-ip__proxy-main">'
                + '<div class="vs-user-ip__proxy-title-row">'
                + '<strong class="vs-user-ip__proxy-title">' + title + '</strong>'
                + '<span class="vs-user-ip__proxy-badge">' + modeLabel + '</span>'
                + '<span class="vs-user-ip__proxy-badge">' + protoLabel + '</span>'
                + '<span class="vs-user-ip__proxy-badge ' + (statusOn ? 'is-on' : 'is-off') + '">'
                + (statusOn ? '启用' : '禁用') + '</span>'
                + '</div>'
                + '<code class="vs-user-ip__proxy-endpoint" title="' + endpoint + '">' + endpoint + '</code>'
                + '<p class="vs-user-ip__proxy-meta">ID ' + id + ' · 调用可加 vsproxyid=' + id + '</p>'
                + '</div>'
                + '<div class="vs-user-ip__proxy-actions">'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-proxy-act="test" data-id="' + id + '">测试</button>'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-proxy-act="edit" data-id="' + id + '">编辑</button>'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-btn--sm" data-proxy-act="delete" data-id="' + id + '">删除</button>'
                + '</div></li>';
        }).join('');
    }

    function resetProxyForm() {
        if (!proxyForm) {
            return;
        }
        proxyForm.reset();
        var idEl = document.getElementById('userProxyId');
        if (idEl) {
            idEl.value = '0';
        }
        var sortEl = document.getElementById('userProxySort');
        if (sortEl) {
            sortEl.value = '0';
        }
        var statusEl = document.getElementById('userProxyStatus');
        if (statusEl) {
            statusEl.value = '1';
        }
        var passEl = document.getElementById('userProxyPass');
        if (passEl) {
            passEl.value = '';
            passEl.placeholder = '留空表示不修改已保存密码';
        }
        if (proxyFormTitle) {
            proxyFormTitle.textContent = '添加出口代理';
        }
        syncModeFields();
        if (window.VS && typeof window.VS.refreshPick === 'function') {
            window.VS.refreshPick(proxyForm);
        }
    }

    function fillProxyForm(row) {
        if (!row || !proxyForm) {
            return;
        }
        document.getElementById('userProxyId').value = String(row.id || 0);
        document.getElementById('userProxyTitle').value = row.title || '';
        document.getElementById('userProxyMode').value = String(row.mode != null ? row.mode : 0);
        document.getElementById('userProxyProto').value = String(row.proto != null ? row.proto : 0);
        document.getElementById('userProxyHost').value = row.host || '';
        document.getElementById('userProxyPort').value = row.port ? String(row.port) : '';
        document.getElementById('userProxyUser').value = row.username || '';
        document.getElementById('userProxyExtract').value = row.extract || '';
        document.getElementById('userProxyExtfmt').value = String(row.extfmt != null ? row.extfmt : 0);
        document.getElementById('userProxyStatus').value = String(row.status != null ? row.status : 1);
        document.getElementById('userProxySort').value = String(row.sort != null ? row.sort : 0);
        var passEl = document.getElementById('userProxyPass');
        passEl.value = '';
        passEl.placeholder = row.haspass ? '已保存，留空则保持不变' : '可选';
        if (proxyFormTitle) {
            proxyFormTitle.textContent = '编辑出口代理 #' + row.id;
        }
        syncModeFields();
        if (window.VS && typeof window.VS.refreshPick === 'function') {
            window.VS.refreshPick(proxyForm);
        }
    }

    if (proxyMode) {
        proxyMode.addEventListener('change', syncModeFields);
        syncModeFields();
    }

    postAction('proxy_list', {}).then(function (data) {
        if (data && data.code === 1) {
            renderProxyList(data.list);
            if (strategySel && data.strategy != null) {
                strategySel.value = String(data.strategy);
            }
        }
    }).catch(function () { /* ignore */ });

    if (openProxyBtn) {
        openProxyBtn.addEventListener('click', function () {
            if (proxyCache.length >= proxyMax) {
                window.VS.showMessage('每个账号最多保存 ' + proxyMax + ' 条出口代理', 'error');
                return;
            }
            resetProxyForm();
            openOverlay(proxyOverlay);
        });
    }

    if (proxyForm) {
        proxyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (busy) {
                return;
            }
            var payload = {
                id: document.getElementById('userProxyId').value || '0',
                title: document.getElementById('userProxyTitle').value || '',
                mode: document.getElementById('userProxyMode').value || '0',
                proto: document.getElementById('userProxyProto').value || '0',
                host: document.getElementById('userProxyHost').value || '',
                port: document.getElementById('userProxyPort').value || '0',
                username: document.getElementById('userProxyUser').value || '',
                password: document.getElementById('userProxyPass').value || '',
                extract: document.getElementById('userProxyExtract').value || '',
                extfmt: document.getElementById('userProxyExtfmt').value || '0',
                status: document.getElementById('userProxyStatus').value || '1',
                sort: document.getElementById('userProxySort').value || '0'
            };
            setBusy(true);
            postAction('proxy_save', payload).then(function (data) {
                setBusy(false);
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '保存失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已保存', 'success');
                renderProxyList(data.list);
                closeOverlay(proxyOverlay);
                resetProxyForm();
            }).catch(function () {
                setBusy(false);
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        });
    }

    if (strategySave && strategySel) {
        strategySave.addEventListener('click', function () {
            if (busy) {
                return;
            }
            setBusy(true);
            postAction('proxy_strategy', { strategy: strategySel.value }).then(function (data) {
                setBusy(false);
                if (!data || data.code !== 1) {
                    window.VS.showMessage((data && data.msg) || '保存失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '策略已保存', 'success');
            }).catch(function () {
                setBusy(false);
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        });
    }

    if (proxyListEl) {
        proxyListEl.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-proxy-act]');
            if (!btn || busy) {
                return;
            }
            var act = btn.getAttribute('data-proxy-act');
            var id = btn.getAttribute('data-id') || '';
            if (!act || !id) {
                return;
            }
            if (act === 'edit') {
                var row = findProxy(id);
                if (!row) {
                    window.VS.showMessage('未找到该配置，请刷新后重试', 'error');
                    return;
                }
                fillProxyForm(row);
                openOverlay(proxyOverlay);
                return;
            }
            if (act === 'test') {
                setBusy(true);
                window.VS.showMessage('正在测试连通性…', 'info');
                postAction('proxy_test', { id: id }).then(function (data) {
                    setBusy(false);
                    if (!data || data.code !== 1) {
                        window.VS.showMessage((data && data.msg) || '测试失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '连通正常', 'success');
                }).catch(function () {
                    setBusy(false);
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                });
                return;
            }
            if (act === 'delete') {
                var confirmRm = window.VsModal && window.VsModal.confirm
                    ? window.VsModal.confirm('确定删除该出口代理配置？', '删除代理')
                    : Promise.resolve(window.confirm('确定删除该出口代理配置？'));
                confirmRm.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    setBusy(true);
                    return postAction('proxy_delete', { id: id }).then(function (data) {
                        setBusy(false);
                        if (!data || data.code !== 1) {
                            window.VS.showMessage((data && data.msg) || '删除失败', 'error');
                            return;
                        }
                        window.VS.showMessage(data.msg || '已删除', 'success');
                        renderProxyList(data.list);
                        var curId = document.getElementById('userProxyId');
                        if (curId && String(curId.value) === String(id)) {
                            closeOverlay(proxyOverlay);
                            resetProxyForm();
                        }
                    });
                }).catch(function () {
                    setBusy(false);
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                });
            }
        });
    }
})();
