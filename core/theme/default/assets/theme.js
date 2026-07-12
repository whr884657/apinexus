/**
 * 默认主题 · 前台脚本（抽屉、接口筛选、统计动画、返回顶部）
 */
(function () {
    'use strict';

    /* ── 移动端抽屉 ── */
    var btn = document.getElementById('dtMenuBtn');
    var drawer = document.getElementById('dtDrawer');
    var mask = document.getElementById('dtDrawerMask');

    if (btn && drawer && mask) {
        function openDrawer() {
            drawer.hidden = false;
            mask.hidden = false;
            drawer.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            document.body.classList.add('dt-drawer-open');
        }

        function closeDrawer() {
            drawer.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('dt-drawer-open');
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

        drawer.querySelectorAll('.dt-drawer__link').forEach(function (link) {
            link.addEventListener('click', closeDrawer);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeDrawer();
            }
        });
    }

    /* ── 接口搜索与分类 ── */
    document.querySelectorAll('[data-dt-api-toolbar]').forEach(function (toolbar) {
        var grid = toolbar.parentElement ? toolbar.parentElement.querySelector('[data-dt-api-grid]') : null;
        if (!grid) {
            return;
        }

        var searchInput = toolbar.querySelector('[data-dt-api-search]');
        var resetBtn = toolbar.querySelector('[data-dt-api-reset]');
        var catWrap = toolbar.querySelector('[data-dt-api-cats]');
        var catButtons = catWrap ? catWrap.querySelectorAll('.dt-api-cat') : [];
        var moreBtn = catWrap ? catWrap.querySelector('[data-dt-cat-more]') : null;
        var cards = grid.querySelectorAll('.dt-api-card');
        var totalEl = document.querySelector('[data-dt-api-total]');
        var activeCategory = '';

        if (moreBtn && catWrap) {
            moreBtn.addEventListener('click', function () {
                catWrap.classList.toggle('is-expanded');
            });
        }

        function setActiveCat(button) {
            catButtons.forEach(function (b) {
                b.classList.remove('is-active');
            });
            if (button) {
                button.classList.add('is-active');
            }
        }

        function applyFilter() {
            var keyword = searchInput ? searchInput.value.trim().toLowerCase() : '';
            var visible = 0;

            cards.forEach(function (card) {
                var name = card.getAttribute('data-name') || '';
                var desc = card.getAttribute('data-desc') || '';
                var cat = card.getAttribute('data-category') || '';
                var matchKeyword = keyword === '' || name.indexOf(keyword) !== -1 || desc.indexOf(keyword) !== -1;
                var matchCat = activeCategory === '' || cat === activeCategory;
                var show = matchKeyword && matchCat;

                if (show) {
                    card.classList.remove('is-hidden');
                    visible += 1;
                } else {
                    card.classList.add('is-hidden');
                }
            });

            if (resetBtn) {
                resetBtn.hidden = keyword === '' && activeCategory === '';
            }
            if (totalEl) {
                totalEl.textContent = String(visible);
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilter);
        }

        catButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeCategory = button.getAttribute('data-category') || '';
                setActiveCat(button);
                applyFilter();
            });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (searchInput) {
                    searchInput.value = '';
                }
                activeCategory = '';
                var first = catWrap ? catWrap.querySelector('.dt-api-cat') : null;
                setActiveCat(first);
                applyFilter();
            });
        }
    });

    /* ── 统计数字动画 ── */
    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-target') || '0', 10);
        if (isNaN(target) || target <= 0) {
            el.textContent = '0';
            return;
        }
        var duration = 900;
        var start = 0;
        var startTime = null;

        function step(ts) {
            if (!startTime) {
                startTime = ts;
            }
            var progress = Math.min((ts - startTime) / duration, 1);
            var value = Math.floor(start + (target - start) * progress);
            el.textContent = String(value);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = String(target);
            }
        }

        window.requestAnimationFrame(step);
    }

    if ('IntersectionObserver' in window) {
        var counterObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !entry.target.dataset.counted) {
                    entry.target.dataset.counted = '1';
                    animateCounter(entry.target);
                    counterObs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        document.querySelectorAll('.dt-counter').forEach(function (el) {
            counterObs.observe(el);
        });
    } else {
        document.querySelectorAll('.dt-counter').forEach(animateCounter);
    }

    /* ── 返回顶部 ── */
    var backTop = document.getElementById('dtBackTop');
    if (backTop) {
        function toggleBackTop() {
            if (window.scrollY > 320) {
                backTop.hidden = false;
            } else {
                backTop.hidden = true;
            }
        }

        window.addEventListener('scroll', toggleBackTop, { passive: true });
        toggleBackTop();

        backTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
})();
