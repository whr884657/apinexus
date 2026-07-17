/**
 * 文件：core/theme/default/assets/js/front-runtime.js
 * 作用：默认主题 · 网站运行时间计数（仅本主题使用；依赖 window.runtimeStartDate）
 */

(function () {
    'use strict';

    var startTime = window.runtimeStartDate;
    if (!startTime) {
        return;
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function updateRuntime() {
        var el = document.getElementById('runtime-display');
        if (!el) {
            return;
        }

        var now = new Date().getTime();
        var diff = now - startTime;

        if (diff < 0) {
            el.textContent = '网站即将上线';
            return;
        }

        var years = Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000));
        var months = Math.floor((diff % (365.25 * 24 * 60 * 60 * 1000)) / (30.44 * 24 * 60 * 60 * 1000));
        var days = Math.floor((diff % (30.44 * 24 * 60 * 60 * 1000)) / (24 * 60 * 60 * 1000));
        var hours = Math.floor((diff % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));
        var minutes = Math.floor((diff % (60 * 60 * 1000)) / (60 * 1000));
        var seconds = Math.floor((diff % (60 * 1000)) / 1000);

        var parts = [];
        if (years > 0) {
            parts.push(years + '年');
        }
        if (months > 0) {
            parts.push(months + '月');
        }
        if (days > 0) {
            parts.push(days + '天');
        }
        parts.push(pad2(hours) + '时');
        parts.push(pad2(minutes) + '分');
        parts.push(pad2(seconds) + '秒');

        el.textContent = '网站已运行: ' + parts.join('');
    }

    updateRuntime();
    setInterval(updateRuntime, 1000);
})();
