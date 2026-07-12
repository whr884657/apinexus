/**
 * front-theme.js
 * 默认主题：固定浅色模式，不提供昼夜切换
 */

(function () {
    function applyLightTheme() {
        document.documentElement.setAttribute('data-theme', 'light');
        if (document.body) {
            document.body.setAttribute('data-theme', 'light');
        }
    }

    window.toggleTheme = function () {};
    window.setTheme = function () {
        applyLightTheme();
    };
    window.updateIcons = function () {};

    try {
        localStorage.setItem('theme', 'light');
    } catch (e) {}

    applyLightTheme();
    document.addEventListener('DOMContentLoaded', applyLightTheme);
})();
