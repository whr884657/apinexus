/**
 * 用户中心 · IP 配置（白名单）
 */
(function () {
    var page = document.getElementById('userIpPage');
    if (!page || !window.VS) {
        return;
    }

    var maxCount = parseInt(page.getAttribute('data-max') || '32', 10) || 32;
    var clientIp = String(page.getAttribute('data-client-ip') || '');
    var tabs = document.getElementById('userIpTabs');
    var listEl = document.getElementById('userIpList');
    var emptyEl = document.getElementById('userIpEmpty');
    var countEl = document.getElementById('userIpCount');
    var form = document.getElementById('userIpAddForm');
    var input = document.getElementById('userIpInput');
    var addCurrentBtn = document.getElementById('userIpAddCurrent');
    var busy = false;

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function postAction(action, payload) {
        var fd = new FormData();
        fd.append('action', action);
        if (payload) {
            Object.keys(payload).forEach(function (key) {
                fd.append(key, payload[key]);
            });
        }
        return window.VS.postForm(fd);
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
    }

    function setBusy(on) {
        busy = !!on;
        if (form) {
            var btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = busy;
            }
        }
        if (addCurrentBtn) {
            addCurrentBtn.disabled = busy || !clientIp;
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
            var on = panel.getAttribute('data-ip-panel') === name;
            panel.hidden = !on;
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
            if (input) {
                input.value = '';
            }
            renderList(data.list);
        }).catch(function () {
            setBusy(false);
            window.VS.showMessage('网络异常，请稍后重试', 'error');
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            doAdd(input ? input.value : '');
        });
    }

    if (addCurrentBtn) {
        addCurrentBtn.addEventListener('click', function () {
            if (!clientIp) {
                return;
            }
            doAdd(clientIp);
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
})();
