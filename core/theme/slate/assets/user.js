/**
 * 青绿平台 · 用户中心（右下角 FAB 展开侧边栏）
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'st_uc_sidebar_open';

    function initSidebar() {
        var shell = document.getElementById('stUcShell');
        var fab = document.getElementById('stUcFab');
        var mask = document.getElementById('stUcMask');
        var sidebar = document.getElementById('stUcSidebar');

        if (!shell || !fab) {
            return;
        }

        function setOpen(open) {
            shell.classList.toggle('is-sidebar-open', open);
            shell.classList.toggle('is-sidebar-closed', !open);
            fab.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('st-uc-sidebar-open', open);
            try {
                localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
            } catch (e) {}
        }

        function isOpen() {
            return shell.classList.contains('is-sidebar-open');
        }

        var saved = false;
        try {
            saved = localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {}

        setOpen(saved);

        fab.addEventListener('click', function () {
            setOpen(!isOpen());
        });

        if (mask) {
            mask.addEventListener('click', function () {
                setOpen(false);
            });
        }

        if (sidebar) {
            sidebar.querySelectorAll('.vs-sidebar__link, .vs-sidebar__logout').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        setOpen(false);
                    }
                });
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) {
                setOpen(false);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initSidebar);
})();
