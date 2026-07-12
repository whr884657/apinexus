/**
 * 青绿平台 · 用户中心（顶栏 + 移动 sheet）
 */
(function () {
    'use strict';

    var btn = document.getElementById('stDashMenuBtn');
    var sheet = document.getElementById('stDashSheet');
    var mask = document.getElementById('stDashMask');

    if (!btn || !sheet || !mask) {
        return;
    }

    function openSheet() {
        sheet.hidden = false;
        mask.hidden = false;
        requestAnimationFrame(function () {
            sheet.classList.add('is-open');
        });
        btn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('st-dash-menu-open');
    }

    function closeSheet() {
        sheet.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('st-dash-menu-open');
        window.setTimeout(function () {
            if (!sheet.classList.contains('is-open')) {
                sheet.hidden = true;
                mask.hidden = true;
            }
        }, 220);
    }

    btn.addEventListener('click', function () {
        if (sheet.classList.contains('is-open')) {
            closeSheet();
        } else {
            openSheet();
        }
    });

    mask.addEventListener('click', closeSheet);

    sheet.querySelectorAll('.st-dash-sheet__link').forEach(function (link) {
        link.addEventListener('click', closeSheet);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('is-open')) {
            closeSheet();
        }
    });
})();
