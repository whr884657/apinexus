/**
 * 青绿平台 · 用户中心导航（FAB 弹出 / 顶部三横线右侧抽屉）
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

    function initDrawerMenu() {
        var btn = document.getElementById('stUcMenuBtn');
        var drawer = document.getElementById('stUcDrawer');
        var mask = document.getElementById('stUcMask');

        if (!btn || !drawer) {
            return;
        }

        function setOpen(open) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('st-uc-menu-open', open);
            document.body.classList.toggle('st-uc-drawer-open', open);
            drawer.classList.toggle('is-open', open);
            if (open) {
                drawer.hidden = false;
            } else {
                window.setTimeout(function () {
                    if (!drawer.classList.contains('is-open')) {
                        drawer.hidden = true;
                    }
                }, 240);
            }
            if (mask) {
                mask.hidden = !open;
                mask.classList.toggle('is-show', open);
            }
        }

        function isOpen() {
            return drawer.classList.contains('is-open');
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!isOpen());
        });

        if (mask) {
            mask.addEventListener('click', function () {
                setOpen(false);
            });
        }

        drawer.querySelectorAll('.st-uc-drawer__link').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) {
                setOpen(false);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var mode = document.body.getAttribute('data-nav-mode') || 'top_drawer';
        if (mode === 'fab_popup') {
            initFabMenu();
        } else {
            initDrawerMenu();
        }
    });
})();
