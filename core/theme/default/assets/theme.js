/**
 * 默认主题 · 独立脚本（右侧抽屉，点击遮罩关闭）
 */
(function () {
    'use strict';

    var btn = document.getElementById('dtMenuBtn');
    var drawer = document.getElementById('dtDrawer');
    var mask = document.getElementById('dtDrawerMask');

    if (!btn || !drawer || !mask) {
        return;
    }

    function openDrawer() {
        drawer.hidden = false;
        mask.hidden = false;
        drawer.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('dt-drawer-open');
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('dt-drawer-open');
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

    drawer.querySelectorAll('.dt-drawer__link').forEach(function (link) {
        link.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
            closeDrawer();
        }
    });
})();
