/**
 * 青绿平台 · 用户中心交互
 */
(function () {
    'use strict';

    var MOBILE_BREAK = 768;
    var STORAGE_KEY = 'st_uc_sidebar_collapsed';

    function isMobile() {
        return window.innerWidth <= MOBILE_BREAK;
    }

    function initSidebar() {
        var shell = document.getElementById('stUcShell');
        var toggle = document.getElementById('stUcToggle');
        var mask = document.getElementById('stUcMask');

        if (!shell || !toggle) {
            return;
        }

        function applyDesktopState() {
            var collapsed = localStorage.getItem(STORAGE_KEY) === '1';
            shell.classList.toggle('is-collapsed', collapsed);
            shell.classList.remove('is-mobile-open');
        }

        function applyMobileState() {
            shell.classList.remove('is-collapsed');
            shell.classList.remove('is-mobile-open');
        }

        function refreshLayout() {
            if (isMobile()) {
                applyMobileState();
            } else {
                applyDesktopState();
            }
        }

        toggle.addEventListener('click', function () {
            if (isMobile()) {
                shell.classList.toggle('is-mobile-open');
            } else {
                shell.classList.toggle('is-collapsed');
                localStorage.setItem(
                    STORAGE_KEY,
                    shell.classList.contains('is-collapsed') ? '1' : '0'
                );
            }
        });

        if (mask) {
            mask.addEventListener('click', function () {
                shell.classList.remove('is-mobile-open');
            });
        }

        window.addEventListener('resize', refreshLayout);
        refreshLayout();
    }

    document.addEventListener('DOMContentLoaded', initSidebar);
})();
