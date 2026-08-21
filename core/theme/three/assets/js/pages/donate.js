/**
 * 主题三 · 赞助页二维码 Tab 切换（donateQrTab）
 */
'use strict';

(function () {
    var tabs = document.querySelectorAll('[data-donate-qr-tab]');
    var img = document.getElementById('donateQrImg');
    var labelEl = document.getElementById('donateQrLabel');
    var panel = document.getElementById('donateQrPanel');
    var dataEl = document.getElementById('donateQrData');
    if (!img || !tabs.length) {
        return;
    }

    var list = [];
    if (dataEl) {
        try {
            list = JSON.parse(dataEl.textContent || '[]');
        } catch (e) {
            list = [];
        }
    }
    if (!list.length) {
        tabs.forEach(function (tab) {
            list.push({
                id: tab.getAttribute('data-donate-qr-tab') || '',
                label: tab.textContent || '',
                url: ''
            });
        });
    }

    var index = 0;
    var busy = false;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function findIndexById(id) {
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].id) === String(id)) return i;
        }
        return -1;
    }

    function apply(i) {
        if (busy || i === index || !list[i] || !list[i].url) {
            return;
        }
        var item = list[i];
        index = i;
        busy = true;

        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-donate-qr-tab') === String(item.id);
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        function swap() {
            img.src = item.url;
            img.alt = (item.label || '') + '收款码';
            if (labelEl) {
                labelEl.textContent = item.label || '';
            }
            if (panel && item.id) {
                panel.setAttribute('aria-labelledby', 'donateQrTab-' + item.id);
            }
            img.classList.remove('is-fading');
            busy = false;
        }

        if (reduceMotion) {
            swap();
            return;
        }
        img.classList.add('is-fading');
        window.setTimeout(swap, 180);
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var id = tab.getAttribute('data-donate-qr-tab') || '';
            var i = findIndexById(id);
            if (i >= 0) apply(i);
        });
        tab.addEventListener('mouseenter', function () {
            if (window.matchMedia && window.matchMedia('(min-width: 901px)').matches) {
                var id = tab.getAttribute('data-donate-qr-tab') || '';
                var i = findIndexById(id);
                if (i >= 0) apply(i);
            }
        });
    });
})();
