'use strict';

(function () {
    var root = document.querySelector('[data-donate-qr-switch]');
    if (root) {
        initQrSwitch(root);
    }

    var cards = document.querySelectorAll('.donate-sponsor-card');
    if (cards.length && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        cards.forEach(function (el) {
            io.observe(el);
        });
    }

    function initQrSwitch(wrap) {
        var dataEl = document.getElementById('donateQrData');
        var img = document.getElementById('donateQrImg');
        var labelEl = document.getElementById('donateQrLabel');
        var panel = document.getElementById('donateQrPanel');
        var tabs = wrap.querySelectorAll('[data-donate-qr-tab]');
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
                    id: tab.getAttribute('data-qr-id') || '',
                    label: tab.getAttribute('data-qr-label') || '',
                    url: tab.getAttribute('data-qr-url') || ''
                });
            });
        }

        var index = 0;
        var busy = false;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function apply(i) {
            if (busy || i === index || !list[i]) {
                return;
            }
            var item = list[i];
            if (!item.url) {
                return;
            }
            index = i;
            busy = true;

            tabs.forEach(function (tab, ti) {
                var on = ti === i;
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

        tabs.forEach(function (tab, ti) {
            tab.addEventListener('click', function () {
                apply(ti);
            });
            // 电脑端：鼠标移入标签即可切换
            tab.addEventListener('mouseenter', function () {
                if (window.matchMedia && window.matchMedia('(min-width: 901px)').matches) {
                    apply(ti);
                }
            });
        });
    }
})();
