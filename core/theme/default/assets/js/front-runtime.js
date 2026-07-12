/**
 * front-runtime.js
 * 来源: includes/site-footer.php
 * 前台运行时间计数器脚本
 */

// === from: includes/site-footer.php ===
(function() {
        var startTime = window.runtimeStartDate;

        if (!startTime) return;

        function updateRuntime() {
            var now = new Date().getTime();
            var diff = now - startTime;

            if (diff < 0) {
                document.getElementById('runtime-display').textContent = '网站即将上线';
                return;
            }

            var years = Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000));
            var months = Math.floor((diff % (365.25 * 24 * 60 * 60 * 1000)) / (30.44 * 24 * 60 * 60 * 1000));
            var days = Math.floor((diff % (30.44 * 24 * 60 * 60 * 1000)) / (24 * 60 * 60 * 1000));
            var hours = Math.floor((diff % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));
            var minutes = Math.floor((diff % (60 * 60 * 1000)) / (60 * 1000));
            var seconds = Math.floor((diff % (60 * 1000)) / 1000);

            var parts = [];
            if (years > 0) parts.push(years + '年');
            if (months > 0) parts.push(months + '月');
            if (days > 0) parts.push(days + '天');
            parts.push(String(hours).padStart(2, '0') + '时');
            parts.push(String(minutes).padStart(2, '0') + '分');
            parts.push(String(seconds).padStart(2, '0') + '秒');

            document.getElementById('runtime-display').textContent = '网站已运行: ' + parts.join('');
        }

        updateRuntime();
        setInterval(updateRuntime, 1000);
    })();

