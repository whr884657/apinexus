/**
 * 文件：assets/js/frontend.js
 * 作用：前台手机端侧边栏展开/收起
 */
(function () {
    'use strict';

    var toggle = document.getElementById('frontendMenuToggle');
    var sidebar = document.getElementById('frontendSidebar');
    var mask = document.getElementById('frontendSidebarMask');
    var closeBtn = document.getElementById('frontendSidebarClose');

    if (!toggle || !sidebar || !mask) {
        return;
    }

    function openSidebar() {
        sidebar.hidden = false;
        mask.hidden = false;
        sidebar.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('vs-frontend-sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('vs-frontend-sidebar-open');
        window.setTimeout(function () {
            if (!sidebar.classList.contains('is-open')) {
                sidebar.hidden = true;
                mask.hidden = true;
            }
        }, 240);
    }

    toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    mask.addEventListener('click', closeSidebar);
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    sidebar.querySelectorAll('.vs-frontend-sidebar__link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
})();
