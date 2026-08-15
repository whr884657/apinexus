/**
 * 文件：core/theme/default/assets/user.js
 * 作用：默认主题用户中心壳层 + 控制台轻交互
 */

(function () {
    'use strict';

    var MOBILE_BREAK = 768;
    var STORAGE_KEY = 'vs_admin_sidebar_collapsed';

    function isMobile() {
        return window.innerWidth <= MOBILE_BREAK;
    }

    function initSidebar() {
        var shell = document.getElementById('vsAdminShell');
        var toggle = document.getElementById('vsSidebarToggle');
        var mask = document.getElementById('vsSidebarMask');

        if (!shell || !toggle) {
            return;
        }

        function applyDesktopState() {
            var collapsed = localStorage.getItem(STORAGE_KEY) === '1';
            if (collapsed) {
                shell.classList.add('is-collapsed');
            } else {
                shell.classList.remove('is-collapsed');
            }
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

        initSidebarGroups();
    }

    function initSidebarGroups() {
        var groups = document.querySelectorAll('.vs-sidebar__group');
        if (!groups.length) return;

        groups.forEach(function (group) {
            var btn = group.querySelector('.vs-sidebar__group-btn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var isOpen = group.classList.contains('is-open');
                groups.forEach(function (g) {
                    g.classList.remove('is-open');
                    var b = g.querySelector('.vs-sidebar__group-btn');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                    group.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                }

                if (group.getAttribute('data-group') === 'system' && window.VsUpdate) {
                    VsUpdate.refreshSidebarBadgePlacement();
                }
            });
        });
    }

    function initDashPress() {
        var cards = document.querySelectorAll('#ucDashboard [data-uc-press]');
        if (!cards.length) return;
        cards.forEach(function (el) {
            el.addEventListener('pointerdown', function () {
                el.classList.add('is-pressed');
            });
            el.addEventListener('pointerup', function () {
                el.classList.remove('is-pressed');
            });
            el.addEventListener('pointerleave', function () {
                el.classList.remove('is-pressed');
            });
            el.addEventListener('pointercancel', function () {
                el.classList.remove('is-pressed');
            });
        });
    }

    function initDashAvatarShake() {
        var box = document.getElementById('ucDashAvatarBox');
        var img = document.getElementById('ucDashAvatarImg');
        if (!box || !img) return;
        function shake() {
            img.classList.remove('uc-dash__avatar--shake');
            void img.offsetWidth;
            img.classList.add('uc-dash__avatar--shake');
            window.setTimeout(function () {
                img.classList.remove('uc-dash__avatar--shake');
            }, 600);
        }
        window.setTimeout(shake, 800);
        box.addEventListener('click', shake);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initDashPress();
        initDashAvatarShake();
    });
})();
