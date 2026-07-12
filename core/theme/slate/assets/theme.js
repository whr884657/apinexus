/**
 * 青绿平台主题 · 抽屉 / FAB 导航 / 首页 / 返回顶部
 */
(function () {
    'use strict';

    function initDrawerNav() {
        var btn = document.getElementById('stMenuBtn');
        var drawer = document.getElementById('stDrawer');
        var mask = document.getElementById('stMask');

        if (!btn || !drawer || !mask || drawer.getAttribute('data-nav-disabled') === '1') {
            return;
        }

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

    function initFabNav() {
        var wrap = document.getElementById('stNavFabWrap');
        var fab = document.getElementById('stNavFab');
        var pop = document.getElementById('stNavPop');
        var mask = document.getElementById('stNavMask');

        if (!wrap || !fab || !pop) {
            return;
        }

        function setOpen(open) {
            wrap.classList.toggle('is-open', open);
            fab.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('st-nav-fab-open', open);
            if (mask) {
                mask.hidden = !open;
                mask.classList.toggle('is-show', open);
            }
            if (!open) {
                window.setTimeout(function () {
                    if (!wrap.classList.contains('is-open')) {
                        pop.hidden = true;
                    }
                }, 240);
            } else {
                pop.hidden = false;
            }
        }

        function isOpen() {
            return wrap.classList.contains('is-open');
        }

        fab.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!isOpen());
        });

        if (mask) {
            mask.addEventListener('click', function () {
                setOpen(false);
            });
        }

        pop.querySelectorAll('.st-nav-pop__link').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('click', function (e) {
            if (!isOpen()) {
                return;
            }
            if (!wrap.contains(e.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) {
                setOpen(false);
            }
        });
    }

    initDrawerNav();
    initFabNav();

    /* ── 返回顶部（全站） ── */
    var backTop = document.getElementById('stBackTop');
    if (backTop) {
        backTop.hidden = false;
        var scrollTicking = false;
        function syncBackTop() {
            backTop.classList.toggle('is-show', window.scrollY > 400);
            scrollTicking = false;
        }
        window.addEventListener('scroll', function () {
            if (!scrollTicking) {
                scrollTicking = true;
                requestAnimationFrame(syncBackTop);
            }
        }, { passive: true });
        syncBackTop();
        backTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
            catBar.querySelectorAll('.st-cat-tag').forEach(function (el) {
                el.classList.toggle('is-on', el === tag);
            });
        });
    }
})();
