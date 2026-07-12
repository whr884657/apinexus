/**
 * 背景调色盘 - 实时预览 + localStorage 持久化
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'login_page_bg';
    var DEFAULT_BG = '#ffffff';

    var FORBIDDEN = [
        { r: 108, g: 63, b: 245, name: '紫色小人' },
        { r: 45, g: 45, b: 45, name: '黑色小人' },
        { r: 255, g: 155, b: 107, name: '橙色小人' },
        { r: 232, g: 215, b: 84, name: '黄色小人' }
    ];

    var FORBIDDEN_THRESHOLD = 28;

    var PRESETS = [
        /* 浅色 */
        '#ffffff', '#f8fafc', '#f1f5f9', '#e2e8f0',
        '#fef2f2', '#fff7ed', '#fefce8', '#f0fdf4',
        '#eff6ff', '#f5f3ff', '#fdf4ff', '#ecfeff',
        /* 对应浅色加深版 */
        '#e5e7eb', '#d1d8e3', '#bcc8d9', '#a8b8cc',
        '#f5caca', '#fdd5b0', '#f5e99e', '#b8ebd0',
        '#b3d4fc', '#d4c6fd', '#efcef5', '#a8eef5'
    ];

    var currentColor = DEFAULT_BG;
    var savedColor = DEFAULT_BG;
    var panelOpen = false;

    function hexToRgb(hex) {
        var h = (hex || '').replace('#', '').trim();
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length === 8) {
            h = h.slice(0, 6);
        }
        if (h.length !== 6) return null;
        var r = parseInt(h.slice(0, 2), 16);
        var g = parseInt(h.slice(2, 4), 16);
        var b = parseInt(h.slice(4, 6), 16);
        if (isNaN(r) || isNaN(g) || isNaN(b)) return null;
        return { r: r, g: g, b: b };
    }

    function rgbToHex(r, g, b) {
        function toHex(n) {
            var s = Math.max(0, Math.min(255, Math.round(n))).toString(16);
            return s.length === 1 ? '0' + s : s;
        }
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    function normalizeHex(hex) {
        if (!hex || typeof hex !== 'string') return null;
        var rgb = hexToRgb(hex);
        return rgb ? rgbToHex(rgb.r, rgb.g, rgb.b) : null;
    }

    function colorDistance(c1, c2) {
        return Math.sqrt(
            Math.pow(c1.r - c2.r, 2) +
            Math.pow(c1.g - c2.g, 2) +
            Math.pow(c1.b - c2.b, 2)
        );
    }

    function getForbiddenMatch(hex) {
        var rgb = hexToRgb(hex);
        if (!rgb) return null;
        for (var i = 0; i < FORBIDDEN.length; i++) {
            if (colorDistance(rgb, FORBIDDEN[i]) < FORBIDDEN_THRESHOLD) {
                return FORBIDDEN[i];
            }
        }
        return null;
    }

    function isForbidden(hex) {
        return getForbiddenMatch(hex) !== null;
    }

    function getSafePresets() {
        return PRESETS.filter(function (color) {
            return !isForbidden(color);
        });
    }

    function isThemePickerDisabled() {
        if (!document.body) {
            return false;
        }
        if (document.body.getAttribute('data-theme-picker') === 'off') {
            return true;
        }
        return document.body.classList.contains('st-uc-body');
    }

    function isAdminPage() {
        return document.body && document.body.classList.contains('vs-admin-body') && !isThemePickerDisabled();
    }

    function paintPage(color) {
        var normalized = normalizeHex(color);
        if (!normalized) return false;
        document.documentElement.style.setProperty('--page-bg', normalized);
        document.documentElement.style.backgroundColor = normalized;
        if (document.body) {
            document.body.style.backgroundColor = normalized;
        }
        return true;
    }

    function applyBackground(color) {
        var normalized = normalizeHex(color);
        if (!normalized || isForbidden(normalized)) return false;
        currentColor = normalized;
        paintPage(normalized);
        return true;
    }

    function readSavedColor() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            var normalized = normalizeHex(saved);
            if (normalized && !isForbidden(normalized)) {
                return normalized;
            }
        } catch (e) {}
        return DEFAULT_BG;
    }

    function loadSavedColor() {
        savedColor = readSavedColor();
        currentColor = savedColor;
        paintPage(savedColor);
        return savedColor;
    }

    function saveColor(color) {
        var normalized = normalizeHex(color);
        if (!normalized || isForbidden(normalized)) return false;
        try {
            localStorage.setItem(STORAGE_KEY, normalized);
            savedColor = normalized;
            currentColor = normalized;
            paintPage(normalized);
            return true;
        } catch (e) {
            return false;
        }
    }

    function resetColor() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
        savedColor = DEFAULT_BG;
        currentColor = DEFAULT_BG;
        document.documentElement.style.removeProperty('--page-bg');
        document.documentElement.style.backgroundColor = '';
        if (document.body) {
            document.body.style.backgroundColor = '';
        }
        paintPage(DEFAULT_BG);
    }

    function paletteIconSvg() {
        return '<svg class="theme-trigger-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<path d="M12 3C7.03 3 3 7.03 3 12c0 2.2 1.02 4.16 2.62 5.45.42.33.68.84.68 1.38v1.17c0 .55.45 1 1 1h1.17c.54 0 1.05.26 1.38.68C10.84 22.98 12.8 24 15 24c4.97 0 9-4.03 9-9S19.97 3 15 3h-3z" stroke="#374151" stroke-width="1.5"/>' +
            '<circle cx="8.5" cy="10.5" r="1.5" fill="#ef4444"/>' +
            '<circle cx="12" cy="8" r="1.5" fill="#3b82f6"/>' +
            '<circle cx="15.5" cy="11" r="1.5" fill="#22c55e"/>' +
            '<circle cx="13" cy="15" r="1.5" fill="#eab308"/>' +
            '</svg>';
    }

    function createUI(mode) {
        mode = mode || (isAdminPage() ? 'admin' : 'auth');

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = 'themeTrigger';
        trigger.setAttribute('aria-label', '打开背景调色盘');

        if (mode === 'admin') {
            trigger.className = 'theme-trigger-circle-btn';
            trigger.innerHTML = paletteIconSvg();
            var mount = document.getElementById('vsThemePickerMount');
            if (mount) {
                mount.appendChild(trigger);
            } else {
                trigger.className = 'theme-trigger-wrap theme-trigger-wrap--admin-fallback';
                trigger.innerHTML = '<span class="theme-trigger-circle">' + paletteIconSvg() + '</span>';
                document.body.appendChild(trigger);
            }
        } else {
            trigger.className = 'theme-trigger-wrap';
            trigger.innerHTML =
                '<span class="theme-trigger-oval">' +
                    '<span class="dot dot-red"></span>' +
                    '<span class="dot dot-yellow"></span>' +
                    '<span class="dot dot-green"></span>' +
                '</span>' +
                '<span class="theme-trigger-circle">' + paletteIconSvg() + '</span>';
            document.body.appendChild(trigger);
        }

        var overlay = document.createElement('div');
        overlay.className = 'theme-overlay';
        overlay.id = 'themeOverlay';

        var panel = document.createElement('div');
        panel.className = 'theme-panel';
        panel.id = 'themePanel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'themePanelTitle');
        panel.innerHTML =
            '<div class="theme-panel-header">' +
                '<h3 id="themePanelTitle">背景颜色</h3>' +
                '<button type="button" class="theme-panel-close" id="themePanelClose" aria-label="关闭">&times;</button>' +
            '</div>' +
            '<div class="theme-preview" id="themePreview"></div>' +
            '<div class="theme-picker-row">' +
                '<label for="themeColorInput">调色盘</label>' +
                '<input type="color" id="themeColorInput" value="' + currentColor + '">' +
                '<span class="theme-picker-value" id="themeColorValue">' + currentColor + '</span>' +
            '</div>' +
            '<div class="theme-presets" id="themePresets"></div>' +
            '<p class="theme-tip">禁止使用四个小人的颜色（紫、黑、橙、黄）</p>' +
            '<div class="theme-message" id="themeMessage"></div>' +
            '<div class="theme-actions">' +
                '<button type="button" class="theme-btn theme-btn-reset" id="themeResetBtn">重置</button>' +
                '<button type="button" class="theme-btn theme-btn-save" id="themeSaveBtn">保存</button>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        var presetsEl = document.getElementById('themePresets');
        getSafePresets().forEach(function (color) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'theme-preset';
            btn.style.backgroundColor = color;
            btn.setAttribute('data-color', color);
            btn.setAttribute('aria-label', '选择颜色 ' + color);
            presetsEl.appendChild(btn);
        });

        return {
            trigger: trigger,
            overlay: overlay,
            panel: panel,
            preview: document.getElementById('themePreview'),
            colorInput: document.getElementById('themeColorInput'),
            colorValue: document.getElementById('themeColorValue'),
            message: document.getElementById('themeMessage'),
            saveBtn: document.getElementById('themeSaveBtn'),
            resetBtn: document.getElementById('themeResetBtn'),
            closeBtn: document.getElementById('themePanelClose'),
            presetsEl: presetsEl
        };
    }

    function showMessage(el, text, type) {
        if (text && window.VsToast) {
            VsToast.show(text, type === 'error' ? 'error' : 'success');
            if (el) {
                el.textContent = '';
                el.className = 'theme-message';
                el.hidden = true;
            }
            return;
        }
        if (!el) return;
        el.hidden = false;
        el.textContent = text;
        el.className = 'theme-message' + (type ? ' theme-message--' + type : '');
    }

    function updatePreview(ui) {
        ui.preview.style.backgroundColor = currentColor;
        ui.colorInput.value = currentColor;
        ui.colorValue.textContent = currentColor;

        var presets = ui.presetsEl.querySelectorAll('.theme-preset');
        for (var i = 0; i < presets.length; i++) {
            var preset = presets[i];
            var presetColor = preset.getAttribute('data-color').toLowerCase();
            if (presetColor === currentColor.toLowerCase()) {
                preset.classList.add('is-active');
            } else {
                preset.classList.remove('is-active');
            }
        }

        ui.saveBtn.disabled = isForbidden(currentColor);
    }

    function trySetColor(ui, color) {
        var normalized = normalizeHex(color);
        if (!normalized) {
            showMessage(ui.message, '颜色格式无效', 'error');
            return false;
        }

        var forbidden = getForbiddenMatch(normalized);
        if (forbidden) {
            showMessage(ui.message, '不能使用' + forbidden.name + '的颜色', 'error');
            ui.saveBtn.disabled = true;
            ui.colorInput.value = currentColor;
            paintPage(currentColor);
            updatePreview(ui);
            return false;
        }

        currentColor = normalized;
        paintPage(normalized);
        updatePreview(ui);
        showMessage(ui.message, '', '');
        return true;
    }

    function openPanel(ui) {
        panelOpen = true;
        ui.overlay.classList.add('is-open');
        ui.panel.classList.add('is-open');
        showMessage(ui.message, '', '');
        updatePreview(ui);
    }

    function closePanel(ui) {
        panelOpen = false;
        ui.overlay.classList.remove('is-open');
        ui.panel.classList.remove('is-open');
        currentColor = savedColor;
        paintPage(savedColor);
        updatePreview(ui);
        showMessage(ui.message, '', '');
    }

    function bindEvents(ui) {
        ui.trigger.addEventListener('click', function () {
            if (panelOpen) {
                closePanel(ui);
            } else {
                openPanel(ui);
            }
        });

        ui.closeBtn.addEventListener('click', function () {
            closePanel(ui);
        });

        ui.overlay.addEventListener('click', function () {
            closePanel(ui);
        });

        ui.panel.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        function onColorPick() {
            trySetColor(ui, ui.colorInput.value);
        }

        ui.colorInput.addEventListener('input', onColorPick);
        ui.colorInput.addEventListener('change', onColorPick);

        ui.presetsEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.theme-preset');
            if (!btn) return;
            trySetColor(ui, btn.getAttribute('data-color'));
        });

        ui.saveBtn.addEventListener('click', function () {
            if (isForbidden(currentColor)) {
                showMessage(ui.message, '当前颜色不可保存', 'error');
                return;
            }
            if (saveColor(currentColor)) {
                showMessage(ui.message, '已保存，下次打开将自动应用', 'success');
                setTimeout(function () {
                    panelOpen = false;
                    ui.overlay.classList.remove('is-open');
                    ui.panel.classList.remove('is-open');
                    showMessage(ui.message, '', '');
                }, 800);
            } else {
                showMessage(ui.message, '保存失败，请检查浏览器设置', 'error');
            }
        });

        ui.resetBtn.addEventListener('click', function () {
            resetColor();
            updatePreview(ui);
            showMessage(ui.message, '已恢复默认背景', 'success');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panelOpen) {
                closePanel(ui);
            }
        });
    }

    function init() {
        if (isThemePickerDisabled()) {
            return;
        }

        savedColor = readSavedColor();
        currentColor = savedColor;
        paintPage(savedColor);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                var ui = createUI();
                updatePreview(ui);
                bindEvents(ui);
            });
        } else {
            var ui = createUI();
            updatePreview(ui);
            bindEvents(ui);
        }
    }

    window.applyPageBackground = paintPage;
    window.readPageBackground = readSavedColor;

    init();
})();
