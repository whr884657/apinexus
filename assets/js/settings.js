/**
 * 文件：assets/js/settings.js
 * 作用：系统设置页 AJAX 保存与折叠板块
 * @version 1.4.0
 */

(function () {
    'use strict';

    var flashEl = document.getElementById('settingsFlash');

    function showFlash(text, type) {
        if (window.VsToast) {
            VsToast.show(text, type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success'));
            return;
        }
        if (!flashEl) return;
        flashEl.textContent = text;
        flashEl.className = 'vs-settings-flash vs-alert vs-alert--' + type;
        flashEl.hidden = false;
        flashEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function postForm(form) {
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        return window.VS.postForm(form)
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    function bindAjaxForm(form) {
        if (!form || form.getAttribute('data-ajax') !== '1') return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            postForm(form)
                .then(function (data) {
                    if (data.code === 1) {
                        var msg = data.msg || '操作成功';
                        if (data.iploc) {
                            msg += '：' + data.iploc;
                        }
                        showFlash(msg, 'success');
                    } else {
                        showFlash(data.msg || '操作失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                });
        });
    }

    function bindAccordions() {
        document.querySelectorAll('[data-accordion]').forEach(function (section) {
            var trigger = section.querySelector('.vs-accordion__trigger');
            if (!trigger) return;

            trigger.addEventListener('click', function () {
                var isOpen = section.classList.contains('is-open');
                section.classList.toggle('is-open', !isOpen);
                trigger.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
            });
        });
    }

    function bindApilogCron() {
        var genBtn = document.getElementById('apilogGenCronKeyBtn');
        var copyBtn = document.getElementById('apilogCopyCronUrlBtn');
        var keyInput = document.getElementById('apilogCronKey');
        var urlInput = document.getElementById('apilogCronUrl');
        var archiveChk = document.getElementById('apilog_archive_enabled');
        var hotRow = document.getElementById('apilogHotDaysRow');
        var shardRow = document.getElementById('apilogShardRowsRow');
        var cronBox = document.getElementById('apilogCronBox');

        function syncArchiveUi() {
            var on = !!(archiveChk && archiveChk.checked);
            if (hotRow) hotRow.hidden = !on;
            if (shardRow) shardRow.hidden = !on;
            if (cronBox) cronBox.hidden = !on;
        }
        if (archiveChk) {
            archiveChk.addEventListener('change', syncArchiveUi);
            syncArchiveUi();
        }

        if (genBtn) {
            genBtn.addEventListener('click', function () {
                if (!window.confirm('生成新密钥后，旧的计划任务链接将立即失效，是否继续？')) {
                    return;
                }
                genBtn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'generate_apilog_cron_key');
                window.VS.postForm(fd, window.location.href)
                    .then(function (data) {
                        if (data.code === 1) {
                            if (keyInput) keyInput.value = data.cron_key || '';
                            if (urlInput) urlInput.value = data.cron_url || '';
                            showFlash(data.msg || '密钥已生成', 'success');
                        } else {
                            showFlash((data && data.msg) || '生成失败', 'error');
                        }
                    })
                    .catch(function () {
                        showFlash('网络异常，请稍后重试', 'error');
                    })
                    .finally(function () {
                        genBtn.disabled = false;
                    });
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var url = urlInput ? String(urlInput.value || '').trim() : '';
                if (!url || url.indexOf('key=') < 0) {
                    showFlash('请先生成密钥', 'error');
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        showFlash('任务链接已复制', 'success');
                    }).catch(function () {
                        showFlash('复制失败，请手动选中复制', 'error');
                    });
                } else if (urlInput) {
                    urlInput.select();
                    try {
                        document.execCommand('copy');
                        showFlash('任务链接已复制', 'success');
                    } catch (e) {
                        showFlash('复制失败，请手动选中复制', 'error');
                    }
                }
            });
        }
    }

    function collectAiFormData(form) {
        var fd = new FormData(form);
        return fd;
    }

    function bindAiProvider() {
        var provider = document.getElementById('aiProvider');
        var baseurl = document.getElementById('aiBaseurl');
        if (!provider || !baseurl) {
            return;
        }
        var presets = {};
        try {
            presets = JSON.parse(baseurl.getAttribute('data-presets') || '{}') || {};
        } catch (e) {
            presets = {};
        }
        provider.addEventListener('change', function () {
            var key = String(provider.value || '');
            if (key === 'custom') {
                return;
            }
            if (presets[key]) {
                baseurl.value = presets[key];
            }
        });
    }

    function fillModelDatalist(models) {
        var list = document.getElementById('aiModelList');
        var input = document.getElementById('aiModel');
        var picker = document.getElementById('aiModelPicker');
        var pickerList = document.getElementById('aiModelPickerList');
        var pickerTitle = document.getElementById('aiModelPickerTitle');
        models = Array.isArray(models) ? models.filter(function (id) { return !!String(id || '').trim(); }) : [];

        if (list) {
            list.innerHTML = '';
            models.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = id;
                list.appendChild(opt);
            });
        }

        if (models.length === 0) {
            if (picker) {
                picker.hidden = true;
            }
            if (pickerList) {
                pickerList.innerHTML = '';
            }
            showFlash('未获取到可用模型', 'error');
            return;
        }

        if (models.length === 1) {
            if (input) {
                input.value = models[0];
            }
            if (picker) {
                picker.hidden = true;
            }
            if (pickerList) {
                pickerList.innerHTML = '';
            }
            showFlash('已自动填入唯一模型：' + models[0], 'success');
            return;
        }

        if (input && !String(input.value || '').trim()) {
            input.value = models[0];
        }
        if (pickerTitle) {
            pickerTitle.textContent = '可用模型（共 ' + models.length + ' 个，点击选择）';
        }
        if (pickerList) {
            pickerList.innerHTML = '';
            var current = input ? String(input.value || '').trim() : '';
            models.forEach(function (id) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'vs-ai-model-pick' + (id === current ? ' is-active' : '');
                btn.textContent = id;
                btn.title = id;
                btn.addEventListener('click', function () {
                    if (input) {
                        input.value = id;
                    }
                    pickerList.querySelectorAll('.vs-ai-model-pick').forEach(function (el) {
                        el.classList.toggle('is-active', el === btn);
                    });
                    showFlash('已选择模型：' + id, 'success');
                });
                pickerList.appendChild(btn);
            });
        }
        if (picker) {
            picker.hidden = false;
        }
        showFlash('已拉取 ' + models.length + ' 个模型，请在列表中选择', 'success');
    }

    function bindAiListModels() {
        var btn = document.getElementById('aiListModelsBtn');
        var form = document.getElementById('aiForm');
        if (!btn || !form) {
            return;
        }
        btn.addEventListener('click', function () {
            var baseurl = document.getElementById('aiBaseurl');
            if (!baseurl || !String(baseurl.value || '').trim()) {
                showFlash('请填写接口根地址', 'error');
                return;
            }
            btn.disabled = true;
            showFlash('正在拉取模型列表…', 'info');
            var fd = collectAiFormData(form);
            fd.set('action', 'list_ai_models');
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        fillModelDatalist(data.models || []);
                    } else {
                        showFlash((data && data.msg) || '拉取失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    function bindAiTest() {
        var btn = document.getElementById('aiTestBtn');
        var form = document.getElementById('aiForm');
        if (!btn || !form) {
            return;
        }
        btn.addEventListener('click', function () {
            var baseurl = document.getElementById('aiBaseurl');
            if (!baseurl || !String(baseurl.value || '').trim()) {
                showFlash('请填写接口根地址', 'error');
                return;
            }
            btn.disabled = true;
            showFlash('正在测试连接…', 'info');
            var fd = collectAiFormData(form);
            fd.set('action', 'test_ai');
            var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var timer = null;
            if (ctrl) {
                timer = setTimeout(function () {
                    try { ctrl.abort(); } catch (eAbort) { /* ignore */ }
                }, 55000);
            }
            window.VS.postForm(fd, window.location.href, ctrl ? { signal: ctrl.signal } : {})
                .then(function (data) {
                    if (data && data.code === 1) {
                        showFlash(data.msg || '连接成功', 'success');
                    } else {
                        showFlash((data && data.msg) || '连接失败', 'error');
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        showFlash('连接测试超时，请检查上游地址或稍后重试', 'error');
                        return;
                    }
                    showFlash('连接失败或网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (timer) {
                        clearTimeout(timer);
                    }
                    btn.disabled = false;
                });
        });
    }

    function bindIplocTest() {
        var form = document.getElementById('iplocTestForm');
        var main = document.getElementById('iplocForm');
        if (!form || !main || form.getAttribute('data-ajax') !== '1') {
            return;
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            var fd = new FormData();
            fd.append('action', 'test_iploc');
            var testIp = document.getElementById('iplocTestIp');
            fd.append('test_ip', testIp ? String(testIp.value || '').trim() : '');
            // 带上当前表单草稿，避免「未保存就测」永远失败
            var enabled = document.getElementById('ipLocEnabled');
            fd.append('ip_loc_enabled', (enabled && enabled.checked) ? '1' : '0');
            ['ip_loc_mode', 'ip_loc_url', 'ip_loc_method', 'ip_loc_ip_param',
                'ip_loc_auth', 'ip_loc_auth_name', 'ip_loc_auth_value', 'ip_loc_field', 'ip_loc_extras'
            ].forEach(function (name) {
                var el = main.querySelector('[name="' + name + '"]');
                if (el) {
                    fd.append(name, el.value != null ? String(el.value) : '');
                }
            });
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        var msg = data.msg || '解析成功';
                        if (data.iploc) {
                            msg += '：' + data.iploc;
                        }
                        showFlash(msg, 'success');
                    } else {
                        showFlash((data && data.msg) || '解析失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    }

    function bindIplocExtras() {
        var list = document.getElementById('ipLocExtraList');
        var hidden = document.getElementById('ipLocExtrasJson');
        var addBtn = document.getElementById('ipLocExtraAdd');
        var form = document.getElementById('iplocForm');
        if (!list || !hidden || !addBtn || !form) {
            return;
        }

        function readRows() {
            try {
                var raw = JSON.parse(hidden.value || '[]');
                return Array.isArray(raw) ? raw : [];
            } catch (e) {
                return [];
            }
        }

        function writeRows(rows) {
            hidden.value = JSON.stringify(rows);
        }

        function render() {
            var rows = readRows();
            list.innerHTML = '';
            rows.forEach(function (row, idx) {
                var wrap = document.createElement('div');
                wrap.className = 'vs-form-row vs-iploc-extra-row';
                wrap.innerHTML = ''
                    + '<div class="vs-iploc-extra-grid">'
                    + '<input type="text" class="vs-input" data-k="name" placeholder="参数名" value="">'
                    + '<input type="text" class="vs-input" data-k="value" placeholder="参数值" value="">'
                    + '<select class="vs-input" data-k="via"><option value="query">Query</option><option value="header">Header</option></select>'
                    + '<button type="button" class="vs-btn vs-btn--ghost" data-del="' + idx + '">删除</button>'
                    + '</div>';
                var nameEl = wrap.querySelector('[data-k="name"]');
                var valEl = wrap.querySelector('[data-k="value"]');
                var viaEl = wrap.querySelector('[data-k="via"]');
                if (nameEl) nameEl.value = row.name || '';
                if (valEl) valEl.value = row.value || '';
                if (viaEl) viaEl.value = row.via === 'header' ? 'header' : 'query';
                list.appendChild(wrap);
            });
        }

        function syncFromDom() {
            var rows = [];
            list.querySelectorAll('.vs-iploc-extra-row').forEach(function (wrap) {
                var name = (wrap.querySelector('[data-k="name"]') || {}).value || '';
                var value = (wrap.querySelector('[data-k="value"]') || {}).value || '';
                var via = (wrap.querySelector('[data-k="via"]') || {}).value || 'query';
                name = String(name).trim();
                if (!name) return;
                rows.push({ name: name, value: String(value), via: via === 'header' ? 'header' : 'query' });
            });
            writeRows(rows);
        }

        addBtn.addEventListener('click', function () {
            syncFromDom();
            var rows = readRows();
            rows.push({ name: '', value: '', via: 'query' });
            writeRows(rows);
            render();
        });

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-del]');
            if (!btn) return;
            syncFromDom();
            var idx = parseInt(btn.getAttribute('data-del'), 10);
            var rows = readRows();
            if (!isNaN(idx)) {
                rows.splice(idx, 1);
                writeRows(rows);
                render();
            }
        });

        form.addEventListener('submit', function () {
            syncFromDom();
        }, true);

        render();
    }

    function syncPanelProviderHidden() {
        var sel = document.getElementById('panelmonitor_provider');
        var hidden = document.getElementById('panelmonitor_provider_hidden');
        if (sel && hidden) {
            hidden.value = String(sel.value || '');
        }
    }

    function bindPanelMonitorTest() {
        var btn = document.getElementById('panelMonitorTestBtn');
        var form = document.getElementById('dashboardForm');
        var providerSel = document.getElementById('panelmonitor_provider');
        if (providerSel) {
            providerSel.addEventListener('change', syncPanelProviderHidden);
            syncPanelProviderHidden();
        }
        if (form) {
            form.addEventListener('submit', function () {
                syncPanelProviderHidden();
            }, true);
        }
        if (!btn || !form || !window.VS || !window.VS.postForm) {
            return;
        }
        btn.addEventListener('click', function () {
            syncPanelProviderHidden();
            var provider = document.getElementById('panelmonitor_provider');
            var baseurl = document.getElementById('panelmonitor_baseurl');
            var apikey = document.getElementById('panelmonitor_apikey');
            var hidden = document.getElementById('panelmonitor_provider_hidden');
            var providerVal = hidden ? String(hidden.value || '').trim()
                : (provider ? String(provider.value || '').trim() : '');
            if (!providerVal) {
                showFlash('请选择面板类型', 'error');
                return;
            }
            if (!baseurl || !String(baseurl.value || '').trim()) {
                showFlash('请填写面板地址', 'error');
                return;
            }
            var keyTyped = apikey && String(apikey.value || '').trim();
            var ph = apikey ? String(apikey.getAttribute('placeholder') || '') : '';
            if (!keyTyped && ph.indexOf('已保存') < 0) {
                showFlash('请填写接口密钥', 'error');
                return;
            }
            btn.disabled = true;
            showFlash('正在测试面板连接…', 'info');
            var fd = new FormData(form);
            fd.set('action', 'test_panelmonitor');
            fd.set('persist', '1');
            fd.set('panelmonitor_provider', providerVal);
            fd.set('panelmonitor_enabled', '1');
            var enabled = form.querySelector('input[type="checkbox"][name="panelmonitor_enabled"]');
            if (enabled) {
                enabled.checked = true;
            }
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        showFlash(data.msg || '连接成功（已保存并启用）', 'success');
                        if (apikey) {
                            apikey.value = '';
                            apikey.setAttribute('placeholder', '已保存，留空则保持不变');
                        }
                        if (enabled) {
                            enabled.checked = true;
                        }
                        var enHidden = form.querySelector('input[type="hidden"][name="panelmonitor_enabled"]');
                        if (enHidden) {
                            enHidden.value = '0';
                        }
                    } else {
                        showFlash((data && data.msg) || '连接失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    function bindCopyButtons() {
        document.querySelectorAll('[data-copy-from]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-copy-from') || '';
                var input = id ? document.getElementById(id) : null;
                var text = input ? String(input.value || '').trim() : '';
                if (!text) {
                    showFlash('没有可复制的内容', 'error');
                    return;
                }
                function ok() {
                    showFlash('已复制到剪贴板', 'success');
                }
                function fail() {
                    if (input) {
                        input.focus();
                        input.select();
                    }
                    showFlash('复制失败，请手动选中复制', 'error');
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(ok).catch(fail);
                } else if (input) {
                    input.select();
                    try {
                        if (document.execCommand('copy')) {
                            ok();
                        } else {
                            fail();
                        }
                    } catch (e) {
                        fail();
                    }
                } else {
                    fail();
                }
            });
        });
    }

    function bindRedisPrefixForm() {
        var form = document.getElementById('redisPrefixForm');
        if (!form) {
            return;
        }
        var forceEl = document.getElementById('redisPrefixForce');
        var conflictEl = document.getElementById('redisPrefixConflict');
        var checkBtn = document.getElementById('redisPrefixCheckBtn');
        var input = document.getElementById('settings_redis_prefix');

        function setConflictTip(text, isBad) {
            if (!conflictEl) {
                return;
            }
            if (!text) {
                conflictEl.hidden = true;
                conflictEl.textContent = '';
                return;
            }
            conflictEl.hidden = false;
            conflictEl.textContent = text;
            conflictEl.style.color = isBad ? '#b91c1c' : '';
        }

        function runCheck() {
            var fd = new FormData();
            fd.append('action', 'check_redis_prefix');
            fd.append('redis_prefix', input ? input.value : '');
            return window.VS.postForm(fd).then(function (data) {
                if (data.code === 1) {
                    setConflictTip(data.msg || '', !!data.conflict);
                    return data;
                }
                setConflictTip(data.msg || '检测失败', true);
                return data;
            });
        }

        if (checkBtn) {
            checkBtn.addEventListener('click', function () {
                checkBtn.disabled = true;
                runCheck().finally(function () {
                    checkBtn.disabled = false;
                });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (forceEl) {
                forceEl.value = '0';
            }
            postForm(form).then(function (data) {
                if (data.code === 1) {
                    showFlash(data.msg || '已保存', 'success');
                    if (input && data.prefix) {
                        input.value = data.prefix;
                    }
                    setConflictTip('', false);
                    return;
                }
                if (data.need_confirm) {
                    var ask = window.VsModal && window.VsModal.confirm
                        ? window.VsModal.confirm(data.msg + '\n\n仍要强制使用此前缀吗？', '前缀冲突确认', {
                            confirmText: '仍要保存',
                            danger: true
                        })
                        : Promise.resolve(window.confirm(data.msg + '\n仍要强制保存吗？'));
                    ask.then(function (ok) {
                        if (!ok) {
                            showFlash(data.msg || '已取消', 'error');
                            return;
                        }
                        if (forceEl) {
                            forceEl.value = '1';
                        }
                        postForm(form).then(function (data2) {
                            if (forceEl) {
                                forceEl.value = '0';
                            }
                            if (data2.code === 1) {
                                showFlash(data2.msg || '已保存', 'success');
                                if (input && data2.prefix) {
                                    input.value = data2.prefix;
                                }
                                setConflictTip('', false);
                            } else {
                                showFlash(data2.msg || '保存失败', 'error');
                            }
                        }).catch(function () {
                            if (forceEl) {
                                forceEl.value = '0';
                            }
                            showFlash('网络异常，请稍后重试', 'error');
                        });
                    });
                    return;
                }
                showFlash(data.msg || '保存失败', 'error');
            }).catch(function () {
                showFlash('网络异常，请稍后重试', 'error');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAccordions();

        ['siteForm', 'registerForm', 'captchaForm', 'checkinForm', 'oauthForm', 'mailForm', 'testMailForm', 'apilogForm', 'dashboardForm', 'aiForm', 'iplocForm'].forEach(function (id) {
            bindAjaxForm(document.getElementById(id));
        });
        bindRedisPrefixForm();
        bindApilogCron();
        bindAiProvider();
        bindAiListModels();
        bindAiTest();
        bindIplocTest();
        bindIplocExtras();
        bindPanelMonitorTest();
        bindCopyButtons();

        var siteExtra = document.getElementById('siteExtraForm');
        if (siteExtra) {
            siteExtra.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(siteExtra);
                if (window.VS && window.VS.encodeTransportField && fd.has('api_disclaimer')) {
                    fd.set('api_disclaimer', window.VS.encodeTransportField(String(fd.get('api_disclaimer') || '')));
                }
                var submitBtn = siteExtra.querySelector('[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                window.VS.postForm(fd)
                    .then(function (data) {
                        if (data && data.code === 1) {
                            showFlash(data.msg || '操作成功', 'success');
                        } else {
                            showFlash((data && data.msg) || '操作失败', 'error');
                        }
                    })
                    .catch(function () {
                        showFlash('网络异常，请稍后重试', 'error');
                    })
                    .finally(function () {
                        if (submitBtn) submitBtn.disabled = false;
                    });
            });
        }
    });
})();
