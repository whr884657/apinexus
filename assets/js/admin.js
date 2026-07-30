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

    /** 全后台轻按下反馈（面板/卡片/按钮），尊重减少动态偏好 */
    function initPressFeedback() {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        var shell = document.getElementById('vsAdminShell') || document.body;
        if (!shell) return;
        var SEL = '.vs-panel, .vs-card, .vs-stat-card, .vs-list-card, .vs-feedback-card, .vs-mobile-card, .vs-btn';

        function clearPressed() {
            var list = shell.querySelectorAll('.is-pressed');
            for (var i = 0; i < list.length; i++) {
                list[i].classList.remove('is-pressed');
            }
        }

        shell.addEventListener('pointerdown', function (ev) {
            var t = ev.target;
            if (!t || !t.closest) return;
            var el = t.closest(SEL);
            if (!el || !shell.contains(el)) return;
            if (el.disabled || el.getAttribute('aria-disabled') === 'true') return;
            el.classList.add('is-pressed');
        });
        shell.addEventListener('pointerup', clearPressed);
        shell.addEventListener('pointercancel', clearPressed);
        shell.addEventListener('pointerleave', clearPressed, true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initPressFeedback();
    });
})();
