/**
 * 文件：assets/js/theme-settings.js
 * 作用：后台主题设置：二级导航、主题切换、主题配置面板
 */
(function () {
    'use strict';

    var page = document.getElementById('themeSettingsPage');
    var form = document.getElementById('themeSettingsForm');
    var configForm = document.getElementById('themeConfigForm');
    var configBody = document.getElementById('themeConfigFormBody');
    var configName = document.getElementById('themeConfigActiveName');
    var configSaveBtn = document.getElementById('themeConfigSaveBtn');
    var switchPanel = document.getElementById('themeSwitchPanel');
    var configPanel = document.getElementById('themeConfigPanel');
    var tabButtons = document.querySelectorAll('.vs-product-tabs__btn');

    function getActiveThemeId() {
        return page ? (page.getAttribute('data-active-theme') || '') : '';
    }

    function setActiveThemeMeta(themeId, themeName) {
        if (page) {
            page.setAttribute('data-active-theme', themeId);
            page.setAttribute('data-active-name', themeName || themeId);
        }
        if (configName) {
            configName.textContent = themeName || themeId;
        }
    }

    /** AjaxResponse::success 将字段合并到响应顶层 */
    function pickPayload(data) {
        if (!data) {
            return {};
        }
        if (data.data && typeof data.data === 'object') {
            return data.data;
        }
        return data;
    }

    function refreshActiveBadges() {
        if (!form) {
            return;
        }
        var activeId = getActiveThemeId();

        form.querySelectorAll('.vs-theme-card').forEach(function (card) {
            var radio = card.querySelector('input[name="frontend_theme"]');
            var themeId = card.getAttribute('data-theme-id') || (radio ? radio.value : '');
            var isSavedActive = themeId === activeId;
            card.classList.toggle('is-active', isSavedActive);
            if (radio) {
                radio.checked = isSavedActive;
            }

            var preview = card.querySelector('.vs-theme-card__preview');
            if (!preview) {
                return;
            }
            var status = preview.querySelector('.vs-theme-card__status');
            if (isSavedActive && !status) {
                status = document.createElement('span');
                status.className = 'vs-theme-card__status';
                status.textContent = '当前使用';
                preview.appendChild(status);
            } else if (!isSavedActive && status) {
                status.remove();
            }
        });
    }

    function isDeskLayout() {
        return window.matchMedia('(min-width: 901px)').matches;
    }

    function applyDeskPanels() {
        if (!isDeskLayout()) {
            return false;
        }
        if (switchPanel) {
            switchPanel.classList.add('is-active');
            switchPanel.hidden = false;
        }
        if (configPanel) {
            configPanel.classList.add('is-active');
            configPanel.hidden = false;
        }
        if (configBody && configBody.getAttribute('data-needs-reload') === '1') {
            reloadConfigPanel();
        }
        return true;
    }

    function switchTab(tabId) {
        tabButtons.forEach(function (btn) {
            var on = btn.getAttribute('data-tab') === tabId;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        if (applyDeskPanels()) {
            return;
        }

        if (switchPanel) {
            switchPanel.classList.toggle('is-active', tabId === 'switch');
            switchPanel.hidden = tabId !== 'switch';
        }
        if (configPanel) {
            configPanel.classList.toggle('is-active', tabId === 'config');
            configPanel.hidden = tabId !== 'config';
        }

        // 首屏已由 PHP 渲染配置表单；仅在切换主题后标记需刷新时再 AJAX
        if (tabId === 'config' && configBody && configBody.getAttribute('data-needs-reload') === '1') {
            reloadConfigPanel();
        }
    }

    function reloadConfigPanel() {
        if (!configBody) {
            return;
        }
        configBody.innerHTML = '<p class="vs-theme-config-empty">加载中…</p>';
        if (configSaveBtn) {
            configSaveBtn.disabled = true;
        }

        var fd = new FormData();
        fd.append('action', 'load_theme_settings');
        fd.append('csrf_token', window.VS_CSRF_TOKEN || '');

        window.VS.postForm(fd, window.location.href)
            .then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '加载失败', 'error');
                    return;
                }
                var payload = pickPayload(data);
                if (payload.theme_name) {
                    setActiveThemeMeta(payload.theme_id || getActiveThemeId(), payload.theme_name);
                }
                configBody.innerHTML = payload.html || '<p class="vs-theme-config-empty">当前主题暂无可调整的项目</p>';
                configBody.removeAttribute('data-needs-reload');
                if (configSaveBtn) {
                    configSaveBtn.disabled = !payload.has_schema;
                }
            })
            .catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchTab(btn.getAttribute('data-tab') || 'switch');
        });
    });

    applyDeskPanels();
    window.addEventListener('resize', function () {
        if (isDeskLayout()) {
            applyDeskPanels();
        } else {
            var activeBtn = document.querySelector('.vs-product-tabs__btn.is-active');
            switchTab(activeBtn ? (activeBtn.getAttribute('data-tab') || 'switch') : 'switch');
        }
    });

    if (form) {
        function refreshSelectionState() {
            var activeId = getActiveThemeId();
            form.querySelectorAll('.vs-theme-card').forEach(function (card) {
                var r = card.querySelector('input[name="frontend_theme"]');
                var themeId = card.getAttribute('data-theme-id') || (r ? r.value : '');
                var isPending = !!(r && r.checked && themeId !== activeId);
                card.classList.toggle('is-selected', isPending);
            });
        }

        form.querySelectorAll('input[name="frontend_theme"]').forEach(function (radio) {
            radio.addEventListener('change', refreshSelectionState);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            window.VS.postForm(form)
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '保存失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已保存', 'success');
                    var payload = pickPayload(data);
                    if (payload.theme_id) {
                        setActiveThemeMeta(payload.theme_id, payload.theme_name || payload.theme_id);
                        if (configBody) {
                            configBody.setAttribute('data-needs-reload', '1');
                        }
                    }
                    refreshActiveBadges();
                    refreshSelectionState();
                })
                .catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });

        refreshActiveBadges();
        refreshSelectionState();
    }

    if (configForm) {
        configForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (configSaveBtn) {
                configSaveBtn.disabled = true;
            }
            window.VS.postForm(configForm)
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '保存失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已保存', 'success');
                })
                .catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (configSaveBtn) {
                        configSaveBtn.disabled = false;
                    }
                });
        });
    }
})();
