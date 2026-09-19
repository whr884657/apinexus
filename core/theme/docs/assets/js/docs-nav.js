(function () {
    window.toggleMobile = function () {
        var overlay = document.getElementById('sidebar-overlay');
        var sidebar = document.getElementById('mobile-sidebar');
        if (overlay) overlay.classList.toggle('active');
        if (sidebar) sidebar.classList.toggle('open');
    };
    window.closeSidebarNow = function () {
        var overlay = document.getElementById('sidebar-overlay');
        var sidebar = document.getElementById('mobile-sidebar');
        if (overlay) overlay.classList.remove('active');
        if (sidebar) sidebar.classList.remove('open');
    };
})();
