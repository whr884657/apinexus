/**
 * 文件：core/theme/default/assets/user.js
 * 作用：默认主题用户中心壳层 + 控制台轻交互
 */

(function () {
    'use strict';

    /** 与管理端折叠键隔离，避免控制台/用户中心互相抢状态导致侧栏频闪 */
    var STORAGE_KEY = 'vs_user_sidebar_collapsed';
    var mqMobile = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;

    function isMobile() {
        return !!(mqMobile && mqMobile.matches);
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

        // 用 matchMedia change，避免窗口宽度在滚动条出现/消失时反复跨 768 触发 resize 频闪
        if (mqMobile) {
            if (typeof mqMobile.addEventListener === 'function') {
                mqMobile.addEventListener('change', refreshLayout);
            } else if (typeof mqMobile.addListener === 'function') {
                mqMobile.addListener(refreshLayout);
            }
        }
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
