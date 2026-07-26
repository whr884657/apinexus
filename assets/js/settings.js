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
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        showFlash(data.msg || '连接成功', 'success');
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

    document.addEventListener('DOMContentLoaded', function () {
        bindAccordions();

        ['siteForm', 'registerForm', 'checkinForm', 'oauthForm', 'mailForm', 'testMailForm', 'apilogForm', 'dashboardForm', 'aiForm', 'iplocForm', 'iplocTestForm'].forEach(function (id) {
            bindAjaxForm(document.getElementById(id));
        });
        bindApilogCron();
        bindAiProvider();
        bindAiListModels();
        bindAiTest();
        bindIplocExtras();

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
