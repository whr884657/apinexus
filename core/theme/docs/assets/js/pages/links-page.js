/**
 * 友情链接页轻交互兜底（主逻辑已迁 common.js：VS.mountLinksPage）
 */
(function () {
    'use strict';
    if (window.VS && typeof window.VS.mountLinksPage === 'function') {
        var root = document.querySelector('[data-vs-links-page]');
        if (root && root.getAttribute('data-vs-mounted') !== '1') {
            window.VS.mountLinksPage(root);
        }
    }
})();
