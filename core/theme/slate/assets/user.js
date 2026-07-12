/**
 * 青绿平台 · 用户中心（右下角圆形 FAB 向上弹出导航）
 */
(function () {
    'use strict';

    function initFabMenu() {
        var wrap = document.getElementById('stUcFabWrap');
        var fab = document.getElementById('stUcFab');
        var pop = document.getElementById('stUcPop');
        var mask = document.getElementById('stUcMask');

        if (!wrap || !fab || !pop) {
            return;
        }

        function setOpen(open) {
            wrap.classList.toggle('is-open', open);
            fab.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('st-uc-menu-open', open);
            if (mask) {
                mask.hidden = !open;
                mask.classList.toggle('is-show', open);
            }
            if (!open) {
                window.setTimeout(function () {
                    if (!wrap.classList.contains('is-open')) {
                        pop.hidden = true;
                    }
                }, 240);
            } else {
                pop.hidden = false;
            }
        }

        function isOpen() {
            return wrap.classList.contains('is-open');
        }

        fab.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!isOpen());
        });

        if (mask) {
            mask.addEventListener('click', function () {
                setOpen(false);
            });
        }

        pop.querySelectorAll('.st-uc-pop__link').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('click', function (e) {
            if (!isOpen()) {
                return;
            }
            if (!wrap.contains(e.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) {
                setOpen(false);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initFabMenu);
})();
