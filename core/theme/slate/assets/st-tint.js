/**
 * 主题二 · 圆形浅色调色盘（仅淡色预设，对应调色板第 5～12 项）
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'st_theme_tint';
    var root = document.querySelector('.st-root');
    if (!root) {
        return;
    }

    // 与 assets/js/theme-picker.js PRESETS 第 5～12 项一致（1-based）
    var PRESETS = [
        { id: 'rose', hex: '#fef2f2', accent: '#e11d48', accentH: '#be123c' },
        { id: 'orange', hex: '#fff7ed', accent: '#ea580c', accentH: '#c2410c' },
        { id: 'yellow', hex: '#fefce8', accent: '#ca8a04', accentH: '#a16207' },
        { id: 'mint', hex: '#f0fdf4', accent: '#16a34a', accentH: '#15803d' },
        { id: 'sky', hex: '#eff6ff', accent: '#2563eb', accentH: '#1d4ed8' },
        { id: 'violet', hex: '#f5f3ff', accent: '#7c3aed', accentH: '#6d28d9' },
        { id: 'pink', hex: '#fdf4ff', accent: '#c026d3', accentH: '#a21caf' },
        { id: 'cyan', hex: '#ecfeff', accent: '#0891b2', accentH: '#0e7490' }
    ];

    var DEFAULT = {
        id: 'green',
        hex: '#eef6f1',
        accent: '#24a66a',
        accentH: '#168855'
    };

    function findPreset(id) {
        if (id === 'green' || !id) {
            return DEFAULT;
        }
        for (var i = 0; i < PRESETS.length; i += 1) {
            if (PRESETS[i].id === id) {
                return PRESETS[i];
            }
        }
        return DEFAULT;
    }

    function applyPreset(preset) {
        root.style.setProperty('--st-accent', preset.accent);
        root.style.setProperty('--st-accent-h', preset.accentH);
        root.style.setProperty('--st-accent-bg', preset.hex);
        root.style.setProperty('--st-bg', preset.hex);
        root.style.setProperty('--st-bg-alt', '#ffffff');
        root.style.setProperty('--st-border', 'color-mix(in srgb, ' + preset.accent + ' 18%, transparent)');
        root.style.setProperty('--st-card-grad', 'color-mix(in srgb, ' + preset.hex + ' 88%, white)');
        root.setAttribute('data-st-tint', preset.id);
        document.querySelectorAll('.st-tint__swatch').forEach(function (el) {
            el.classList.toggle('is-on', el.getAttribute('data-tint') === preset.id);
        });
    }

    function currentId() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                return saved;
            }
        } catch (e) { /* ignore */ }
        return root.getAttribute('data-st-default-tint') || 'green';
    }

    function setId(id) {
        var preset = findPreset(id);
        applyPreset(preset);
        try {
            localStorage.setItem(STORAGE_KEY, preset.id);
        } catch (e) { /* ignore */ }
    }

    function closePanel() {
        var panel = document.getElementById('stTintPanel');
        var btn = document.getElementById('stTintBtn');
        if (panel) {
            panel.hidden = true;
        }
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
    }

    function openPanel() {
        var panel = document.getElementById('stTintPanel');
        var btn = document.getElementById('stTintBtn');
        if (panel) {
            panel.hidden = false;
        }
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    applyPreset(findPreset(currentId()));

    var btn = document.getElementById('stTintBtn');
    var panel = document.getElementById('stTintPanel');
    if (!btn || !panel) {
        return;
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    panel.addEventListener('click', function (e) {
        var swatch = e.target.closest('.st-tint__swatch');
        if (!swatch) {
            return;
        }
        e.preventDefault();
        setId(swatch.getAttribute('data-tint') || 'green');
        closePanel();
    });

    document.addEventListener('click', function (e) {
        if (!panel.hidden && !e.target.closest('.st-tint')) {
            closePanel();
        }
    });
})();
