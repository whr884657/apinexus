'use strict';

function toggleMobile() {
    var overlay = document.getElementById('sidebar-overlay');
    var sidebar = document.getElementById('mobile-sidebar');
    if (!overlay || !sidebar) return;
    overlay.classList.toggle('active');
    sidebar.classList.toggle('open');
}
