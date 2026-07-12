/**
 * 文件：assets/js/theme-settings.js
 * 作用：后台主题设置：保存主题、主题专属配置弹窗
 */
(function () {
    'use strict';

    var form = document.getElementById('themeSettingsForm');
    var modal = document.getElementById('themeSettingsModal');
    var configForm = document.getElementById('themeConfigForm');
    var configBody = document.getElementById('themeConfigFormBody');
    var configThemeId = document.getElementById('themeConfigThemeId');
    var modalTitle = document.getElementById('themeSettingsModalTitle');
    var modalClose = document.getElementById('themeSettingsModalClose');
    var modalCancel = document.getElementById('themeSettingsModalCancel');
    var configSaveBtn = document.getElementById('themeConfigSaveBtn');

    function refreshActiveBadges() {
        if (!form) {
            return;
        }
        var activeId = form.getAttribute('data-active-theme') || '';

        form.querySelectorAll('.vs-theme-card').forEach(function (card) {
            var radio = card.querySelector('input[name="frontend_theme"]');
            var themeId = card.getAttribute('data-theme-id') || (radio ? radio.value : '');
            var isSavedActive = themeId === activeId;
            card.classList.toggle('is-active', isSavedActive);

            var settingsBtn = card.querySelector('.vs-theme-card__settings');
            if (settingsBtn) {
                settingsBtn.setAttribute('data-enabled', isSavedActive ? '1' : '0');
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

    function openModal() {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('vs-modal-open');
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.hidden = true;
        document.body.classList.remove('vs-modal-open');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderConfigForm(schema, values) {
        if (!configBody) {
            return;
        }
        if (!schema || !schema.length) {
            configBody.innerHTML = '<p class="vs-theme-config-empty">该主题暂无可配置项，请在 theme.json 中声明 settings 字段。</p>';
            if (configSaveBtn) {
                configSaveBtn.disabled = true;
            }
            return;
        }
        if (configSaveBtn) {
            configSaveBtn.disabled = false;
        }

        var html = '';
        schema.forEach(function (field) {
            var key = field.key;
            var label = escapeHtml(field.label || key);
            var val = values && Object.prototype.hasOwnProperty.call(values, key) ? values[key] : field.default;
            html += '<div class="vs-theme-config-field">';

            if (field.type === 'checkbox') {
                var checked = val === true || val === 1 || val === '1' || val === 'true';
                html += '<label class="vs-theme-config-check">';
                html += '<input type="checkbox" name="settings[' + escapeHtml(key) + ']" value="1"' + (checked ? ' checked' : '') + '>';
                html += '<span>' + label + '</span></label>';
            } else if (field.type === 'textarea') {
                html += '<label class="vs-label" for="ts_' + escapeHtml(key) + '">' + label + '</label>';
                html += '<textarea class="vs-textarea" id="ts_' + escapeHtml(key) + '" name="settings[' + escapeHtml(key) + ']" rows="3" placeholder="' + escapeHtml(field.placeholder || '') + '">' + escapeHtml(val == null ? '' : val) + '</textarea>';
            } else {
                var inputType = field.type === 'number' ? 'number' : 'text';
                html += '<label class="vs-label" for="ts_' + escapeHtml(key) + '">' + label + '</label>';
                html += '<input type="' + inputType + '" class="vs-input" id="ts_' + escapeHtml(key) + '" name="settings[' + escapeHtml(key) + ']" value="' + escapeHtml(val == null ? '' : val) + '" placeholder="' + escapeHtml(field.placeholder || '') + '">';
            }

            html += '</div>';
        });
        configBody.innerHTML = html;
    }

    function loadThemeSettings(themeId, themeName) {
        if (!configForm || !configThemeId) {
            return;
        }
        configThemeId.value = themeId;
        if (modalTitle) {
            modalTitle.textContent = themeName + ' · 主题设置';
        }
        configBody.innerHTML = '<p class="vs-theme-config-empty">加载中…</p>';
        if (configSaveBtn) {
            configSaveBtn.disabled = true;
        }
        openModal();

        var fd = new FormData();
        fd.append('action', 'load_theme_settings');
        fd.append('theme_id', themeId);
        fd.append('csrf_token', window.VS_CSRF_TOKEN || '');

        window.VS.postForm(fd, configForm.getAttribute('action') || form.getAttribute('action') || '')
            .then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '加载失败', 'error');
                    closeModal();
                    return;
                }
                var payload = data.data || {};
                renderConfigForm(payload.schema || [], payload.values || {});
            })
            .catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
                closeModal();
            });
    }

    if (form) {
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
                    if (data.data && data.data.theme_id) {
                        form.setAttribute('data-active-theme', data.data.theme_id);
                    }
                    refreshActiveBadges();
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
    }

    document.querySelectorAll('.vs-theme-card__settings').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var enabled = btn.getAttribute('data-enabled') === '1';
            if (!enabled) {
                window.VS.showMessage('该主题未启用，不可以进行设置', 'error');
                return;
            }
            var themeId = btn.getAttribute('data-theme-id') || '';
            var themeName = btn.getAttribute('data-theme-name') || themeId;
            if (!themeId) {
                return;
            }
            loadThemeSettings(themeId, themeName);
        });
    });

    if (configForm) {
        configForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (configSaveBtn) {
                configSaveBtn.disabled = true;
            }
            window.VS.postForm(configForm, form ? form.getAttribute('action') : '')
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '保存失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已保存', 'success');
                    closeModal();
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

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    if (modalCancel) {
        modalCancel.addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
