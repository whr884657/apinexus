/**
 * 文件：assets/js/admin.js
 * 作用：ApiNexus 后台框架交互（侧边栏展开/收缩）
 * @version 1.0.0
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
        bootReviewBadges();
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

                var gid = group.getAttribute('data-group');
                if (gid === 'sysmgmt' && window.VsUpdate && typeof window.VsUpdate.refreshSidebarBadgePlacement === 'function') {
                    window.VsUpdate.refreshSidebarBadgePlacement();
                }
                if (gid === 'api') {
                    refreshReviewBadgePlacement();
                }
            });
        });
    }

    function refreshReviewBadgePlacement() {
        var groupBadge = document.getElementById('vsReviewBadgeGroup');
        var reviewItem = document.getElementById('vsReviewBadgeItem');
        var feedbackItem = document.getElementById('vsFeedbackBadgeItem');
        var apiGroup = document.querySelector('.vs-sidebar__group[data-group="api"]');
        if (!groupBadge) {
            return;
        }
        var groupActive = groupBadge.getAttribute('data-active') === '1';
        var reviewActive = reviewItem && reviewItem.getAttribute('data-active') === '1';
        var feedbackActive = feedbackItem && feedbackItem.getAttribute('data-active') === '1';
        var isOpen = !!(apiGroup && apiGroup.classList.contains('is-open'));
        groupBadge.hidden = !(groupActive && !isOpen);
        if (reviewItem) {
            reviewItem.hidden = !(reviewActive && isOpen);
        }
        if (feedbackItem) {
            feedbackItem.hidden = !(feedbackActive && isOpen);
        }
    }

    function bootReviewBadges() {
        refreshReviewBadgePlacement();
    }

    document.addEventListener('DOMContentLoaded', initSidebar);
})();
