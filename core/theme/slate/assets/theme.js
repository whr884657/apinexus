/**
 * 青绿平台主题 · 抽屉 / 首页搜索与统计
 */
(function () {
    'use strict';

    /* ── 手机端抽屉 ── */
    var btn = document.getElementById('stMenuBtn');
    var drawer = document.getElementById('stDrawer');
    var mask = document.getElementById('stMask');

    if (btn && drawer && mask) {
        function openDrawer() {
            drawer.hidden = false;
            mask.hidden = false;
            drawer.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            document.body.classList.add('st-drawer-open');
        }

        function closeDrawer() {
            drawer.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('st-drawer-open');
            window.setTimeout(function () {
                if (!drawer.classList.contains('is-open')) {
                    drawer.hidden = true;
                    mask.hidden = true;
                }
            }, 240);
        }

        btn.addEventListener('click', function () {
            if (drawer.classList.contains('is-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });

        mask.addEventListener('click', closeDrawer);

        drawer.querySelectorAll('.st-drawer__link').forEach(function (link) {
            link.addEventListener('click', closeDrawer);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeDrawer();
            }
        });
    }

    /* ── 首页：统计 / 搜索 / 分类 ── */
    var home = document.getElementById('stHome');
    if (!home) {
        return;
    }

    function animateNum(el, target, duration) {
        if (!el) {
            return;
        }
        var start = performance.now();
        function step(now) {
            var p = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) {
                requestAnimationFrame(step);
            }
        }
        requestAnimationFrame(step);
    }

    animateNum(document.getElementById('stStatTotal'), 0, 600);
    animateNum(document.getElementById('stStatToday'), 0, 800);
    animateNum(document.getElementById('stStatAll'), 0, 1000);

    var searchInput = document.getElementById('stSearchInput');
    var searchClear = document.getElementById('stSearchClear');
    var catBar = document.getElementById('stCatBar');
    var currentCat = '全部';

    function syncSearchClear() {
        if (!searchInput || !searchClear) {
            return;
        }
        searchClear.hidden = searchInput.value === '';
    }

    if (searchInput) {
        searchInput.addEventListener('input', syncSearchClear);
    }

    if (searchClear && searchInput) {
        searchClear.addEventListener('click', function () {
            searchInput.value = '';
            syncSearchClear();
            searchInput.focus();
        });
    }

    if (catBar) {
        catBar.addEventListener('click', function (e) {
            var tag = e.target.closest('.st-cat-tag');
            if (!tag) {
                return;
            }
            currentCat = tag.getAttribute('data-cat') || '全部';
            catBar.querySelectorAll('.st-cat-tag').forEach(function (el) {
                el.classList.toggle('is-on', el === tag);
            });
        });
    }

    var backTop = document.getElementById('stBackTop');
    if (backTop) {
        window.addEventListener('scroll', function () {
            backTop.classList.toggle('is-show', window.scrollY > 400);
        });
        backTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
})();
