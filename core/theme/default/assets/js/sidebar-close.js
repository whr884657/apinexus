/**
 * 侧边栏：点击导航链接时先平滑关闭（带过渡动画），再跳转，避免瞬间收回的卡顿感
 */
(function() {
    function closeSidebar() {
        var overlay = document.getElementById('sidebar-overlay') || document.getElementById('sidebarOverlay');
        var sidebar = document.getElementById('mobile-sidebar') || document.getElementById('mobileSidebar');
        if (overlay) overlay.classList.remove('active');
        if (sidebar) sidebar.classList.remove('open');
    }

    window.closeSidebarNow = function() {
        closeSidebar();
    };
})();
