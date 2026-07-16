/**
 * 主题二 · 圆形浅色调色盘（全页变量驱动；淡色对应调色板第 5～12 项）
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'st_theme_tint';
    var root = document.querySelector('.st-root');
    if (!root) {
        return;
    }

    var PRESETS = {
        green: {
            id: 'green',
            accent: '#24a66a',
            accentH: '#168855',
            bg: '#eef6f1',
            bgHi: '#f4fbf7',
            bgLo: '#dff0e8',
            accentBg: '#e8f5ee',
            washA: 'rgba(36, 166, 106, 0.14)',
            washB: 'rgba(72, 193, 134, 0.10)',
            barEnd: 'rgba(240, 250, 245, 0.94)',
            footStart: 'rgba(244, 250, 247, 0.95)',
            footEnd: 'rgba(223, 240, 232, 0.98)',
            drawer: '#f0faf5',
            cardGrad: 'rgba(236, 248, 242, 0.88)',
            border: 'rgba(36, 166, 106, 0.18)',
            searchBg: 'rgba(240, 250, 245, 0.95)',
            pillBg: 'rgba(232, 245, 238, 0.96)'
        },
        rose: {
            id: 'rose', accent: '#e11d48', accentH: '#be123c',
            bg: '#fef2f2', bgHi: '#fff7f7', bgLo: '#fce4e4', accentBg: '#ffe4e6',
            washA: 'rgba(225, 29, 72, 0.14)', washB: 'rgba(251, 113, 133, 0.10)',
            barEnd: 'rgba(254, 242, 242, 0.94)', footStart: 'rgba(255, 247, 247, 0.95)', footEnd: 'rgba(252, 228, 228, 0.98)',
            drawer: '#fff1f2', cardGrad: 'rgba(255, 228, 230, 0.88)', border: 'rgba(225, 29, 72, 0.18)',
            searchBg: 'rgba(254, 242, 242, 0.95)', pillBg: 'rgba(255, 228, 230, 0.96)'
        },
        orange: {
            id: 'orange', accent: '#ea580c', accentH: '#c2410c',
            bg: '#fff7ed', bgHi: '#fffaf5', bgLo: '#ffedd5', accentBg: '#ffedd5',
            washA: 'rgba(234, 88, 12, 0.14)', washB: 'rgba(251, 146, 60, 0.10)',
            barEnd: 'rgba(255, 247, 237, 0.94)', footStart: 'rgba(255, 250, 245, 0.95)', footEnd: 'rgba(255, 237, 213, 0.98)',
            drawer: '#fff7ed', cardGrad: 'rgba(255, 237, 213, 0.88)', border: 'rgba(234, 88, 12, 0.18)',
            searchBg: 'rgba(255, 247, 237, 0.95)', pillBg: 'rgba(255, 237, 213, 0.96)'
        },
        yellow: {
            id: 'yellow', accent: '#ca8a04', accentH: '#a16207',
            bg: '#fefce8', bgHi: '#fefce8', bgLo: '#fef08a', accentBg: '#fef9c3',
            washA: 'rgba(202, 138, 4, 0.14)', washB: 'rgba(250, 204, 21, 0.10)',
            barEnd: 'rgba(254, 252, 232, 0.94)', footStart: 'rgba(254, 252, 232, 0.95)', footEnd: 'rgba(254, 240, 138, 0.98)',
            drawer: '#fefce8', cardGrad: 'rgba(254, 249, 195, 0.88)', border: 'rgba(202, 138, 4, 0.18)',
            searchBg: 'rgba(254, 252, 232, 0.95)', pillBg: 'rgba(254, 249, 195, 0.96)'
        },
        mint: {
            id: 'mint', accent: '#16a34a', accentH: '#15803d',
            bg: '#f0fdf4', bgHi: '#f7fef9', bgLo: '#dcfce7', accentBg: '#dcfce7',
            washA: 'rgba(22, 163, 74, 0.14)', washB: 'rgba(74, 222, 128, 0.10)',
            barEnd: 'rgba(240, 253, 244, 0.94)', footStart: 'rgba(247, 254, 249, 0.95)', footEnd: 'rgba(220, 252, 231, 0.98)',
            drawer: '#f0fdf4', cardGrad: 'rgba(220, 252, 231, 0.88)', border: 'rgba(22, 163, 74, 0.18)',
            searchBg: 'rgba(240, 253, 244, 0.95)', pillBg: 'rgba(220, 252, 231, 0.96)'
        },
        sky: {
            id: 'sky', accent: '#2563eb', accentH: '#1d4ed8',
            bg: '#eff6ff', bgHi: '#f8fbff', bgLo: '#dbeafe', accentBg: '#dbeafe',
            washA: 'rgba(37, 99, 235, 0.14)', washB: 'rgba(96, 165, 250, 0.10)',
            barEnd: 'rgba(239, 246, 255, 0.94)', footStart: 'rgba(248, 251, 255, 0.95)', footEnd: 'rgba(219, 234, 254, 0.98)',
            drawer: '#eff6ff', cardGrad: 'rgba(219, 234, 254, 0.88)', border: 'rgba(37, 99, 235, 0.18)',
            searchBg: 'rgba(239, 246, 255, 0.95)', pillBg: 'rgba(219, 234, 254, 0.96)'
        },
        violet: {
            id: 'violet', accent: '#7c3aed', accentH: '#6d28d9',
            bg: '#f5f3ff', bgHi: '#faf8ff', bgLo: '#ede9fe', accentBg: '#ede9fe',
            washA: 'rgba(124, 58, 237, 0.14)', washB: 'rgba(167, 139, 250, 0.10)',
            barEnd: 'rgba(245, 243, 255, 0.94)', footStart: 'rgba(250, 248, 255, 0.95)', footEnd: 'rgba(237, 233, 254, 0.98)',
            drawer: '#f5f3ff', cardGrad: 'rgba(237, 233, 254, 0.88)', border: 'rgba(124, 58, 237, 0.18)',
            searchBg: 'rgba(245, 243, 255, 0.95)', pillBg: 'rgba(237, 233, 254, 0.96)'
        },
        pink: {
            id: 'pink', accent: '#c026d3', accentH: '#a21caf',
            bg: '#fdf4ff', bgHi: '#fef8ff', bgLo: '#fae8ff', accentBg: '#fae8ff',
            washA: 'rgba(192, 38, 211, 0.14)', washB: 'rgba(232, 121, 249, 0.10)',
            barEnd: 'rgba(253, 244, 255, 0.94)', footStart: 'rgba(254, 248, 255, 0.95)', footEnd: 'rgba(250, 232, 255, 0.98)',
            drawer: '#fdf4ff', cardGrad: 'rgba(250, 232, 255, 0.88)', border: 'rgba(192, 38, 211, 0.18)',
            searchBg: 'rgba(253, 244, 255, 0.95)', pillBg: 'rgba(250, 232, 255, 0.96)'
        },
        cyan: {
            id: 'cyan', accent: '#0891b2', accentH: '#0e7490',
            bg: '#ecfeff', bgHi: '#f5feff', bgLo: '#cffafe', accentBg: '#cffafe',
            washA: 'rgba(8, 145, 178, 0.14)', washB: 'rgba(34, 211, 238, 0.10)',
            barEnd: 'rgba(236, 254, 255, 0.94)', footStart: 'rgba(245, 254, 255, 0.95)', footEnd: 'rgba(207, 250, 254, 0.98)',
            drawer: '#ecfeff', cardGrad: 'rgba(207, 250, 254, 0.88)', border: 'rgba(8, 145, 178, 0.18)',
            searchBg: 'rgba(236, 254, 255, 0.95)', pillBg: 'rgba(207, 250, 254, 0.96)'
        }
    };

    function findPreset(id) {
        return PRESETS[id] || PRESETS.green;
    }

    function applyPreset(preset) {
        root.style.setProperty('--st-accent', preset.accent);
        root.style.setProperty('--st-accent-h', preset.accentH);
        root.style.setProperty('--st-accent-bg', preset.accentBg);
        root.style.setProperty('--st-bg', preset.bg);
        root.style.setProperty('--st-bg-hi', preset.bgHi);
        root.style.setProperty('--st-bg-lo', preset.bgLo);
        root.style.setProperty('--st-bg-alt', '#ffffff');
        root.style.setProperty('--st-border', preset.border);
        root.style.setProperty('--st-wash-a', preset.washA);
        root.style.setProperty('--st-wash-b', preset.washB);
        root.style.setProperty('--st-bar-end', preset.barEnd);
        root.style.setProperty('--st-foot-start', preset.footStart);
        root.style.setProperty('--st-foot-end', preset.footEnd);
        root.style.setProperty('--st-drawer-bg', preset.drawer);
        root.style.setProperty('--st-card-grad', preset.cardGrad);
        root.style.setProperty('--st-search-bg', preset.searchBg);
        root.style.setProperty('--st-pill-bg', preset.pillBg);
        root.setAttribute('data-st-tint', preset.id);
        document.querySelectorAll('.st-tint__swatch').forEach(function (el) {
            el.classList.toggle('is-on', el.getAttribute('data-tint') === preset.id);
        });
    }

    function currentId() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved && PRESETS[saved]) {
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
