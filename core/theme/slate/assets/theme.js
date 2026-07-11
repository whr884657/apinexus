/**
 * 云启风格主题 · 手机端右侧抽屉
 */
(function () {
    'use strict';

    var btn = document.getElementById('stMenuBtn');
    var drawer = document.getElementById('stDrawer');
    var mask = document.getElementById('stMask');

    if (!btn || !drawer || !mask) {
        return;
    }

    function openDrawer() {
        drawer.hidden = false;
        mask.hidden = false;
        drawer.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('st-drawer-open');
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('st-drawer-open');
        window.setTimeout(function () {
            if (!drawer.classList.contains('is-open')) {
                drawer.hidden = true;
                mask.hidden = true;
            }
        }, 240);
    }

    btn.addEventListener('click', function () {
        if (drawer.classList.contains('is-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    mask.addEventListener('click', closeDrawer);

    drawer.querySelectorAll('.st-drawer__link').forEach(function (link) {
        link.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
            closeDrawer();
        }
    });
})();
