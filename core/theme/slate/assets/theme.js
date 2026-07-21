/**
 * 主题二 · 抽屉 / FAB 导航 / 首页 / 返回顶部
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

    function initHeroTypewriter() {
        var lead = document.getElementById('stHeroLead');
        if (!lead) {
            return;
        }
        var textEl = lead.querySelector('.st-hero__lead-text');
        var cursor = lead.querySelector('.st-hero__cursor');
        var fullText = lead.getAttribute('data-typewriter') || '';
        if (!textEl || fullText === '') {
            if (textEl) {
                textEl.textContent = fullText;
            }
            if (cursor) {
                cursor.classList.add('is-done');
            }
            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            textEl.textContent = fullText;
            if (cursor) {
                cursor.classList.add('is-done');
            }
            return;
        }

        var index = 0;
        var delay = 42;

        function tick() {
            textEl.textContent = fullText.slice(0, index);
            index += 1;
            if (index <= fullText.length) {
                window.setTimeout(tick, delay);
            } else if (cursor) {
                cursor.classList.add('is-done');
            }
        }

        tick();
    }

    initDrawerNav();
    initFabNav();
    initHeroTypewriter();

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

    function formatStatNumber(num, format) {
        num = Math.max(0, Math.round(Number(num) || 0));
        if (format === 'compact') {
            if (num >= 10000) {
                return (num / 10000).toFixed(1).replace(/\.0$/, '') + 'W';
            }
            if (num >= 1000) {
                return Math.floor(num / 1000) + 'K';
            }
        }
        return num.toLocaleString();
    }

    function animateNum(el, target, duration) {
        if (!el) {
            return;
        }
        var parsed = parseInt(el.getAttribute('data-target'), 10);
        if (!isNaN(parsed)) {
            target = parsed;
        }
        var format = (el.getAttribute('data-format') || 'full') === 'compact' ? 'compact' : 'full';
        var start = performance.now();
        function step(now) {
            var p = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            var current = Math.round(target * eased);
            el.textContent = formatStatNumber(current, format);
            if (p < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = formatStatNumber(target, format);
            }
        }
        requestAnimationFrame(step);
    }

    function bindSearchClear(input, clearBtn) {
        if (!input || !clearBtn) {
            return;
        }
        function sync() {
            clearBtn.hidden = input.value === '';
        }
        input.addEventListener('input', sync);
        clearBtn.addEventListener('click', function () {
            input.value = '';
            sync();
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        sync();
    }

    function bindCatBar(catBar, onSelect) {
        if (!catBar) {
            return;
        }
        catBar.addEventListener('click', function (e) {
            var moreBtn = e.target.closest('.st-cat-tag-more');
            if (moreBtn) {
                var expanded = moreBtn.getAttribute('data-expanded') === '1';
                catBar.querySelectorAll('.st-cat-tag-hidden').forEach(function (el) {
                    el.classList.toggle('is-show', !expanded);
                });
                var label = moreBtn.querySelector('span');
                if (label) {
                    label.textContent = expanded ? '更多' : '收起';
                }
                moreBtn.setAttribute('data-expanded', expanded ? '0' : '1');
                return;
            }
            var tag = e.target.closest('.st-cat-tag');
            if (!tag || tag.classList.contains('st-cat-tag-more')) {
                return;
            }
            catBar.querySelectorAll('.st-cat-tag').forEach(function (el) {
                if (!el.classList.contains('st-cat-tag-more')) {
                    el.classList.toggle('is-on', el === tag);
                }
            });
            if (typeof onSelect === 'function') {
                onSelect(tag.getAttribute('data-cat') || 'all');
            }
        });
    }

    function buildApiCardHtml(api) {
        var methods = api.methods && api.methods.length ? api.methods : ['GET'];
        var methodHtml = methods.slice(0, 2).map(function (m) {
            var method = String(m || 'GET').toUpperCase();
            var methodClass = method.toLowerCase();
            return '<span class="st-api-card__method st-api-card__method--' + escapeHtml(methodClass) + '">' + escapeHtml(method) + '</span>';
        }).join('');
        var endpoint = String(api.endpoint || '').trim();
        var detailUrl = String(api.detail_url || '').trim();
        var base = (window.VS_BASE_URL || '').replace(/\/$/, '');
        if (!detailUrl && api.id) {
            detailUrl = base + '/detail/' + api.id;
        }
        if (!detailUrl) {
            detailUrl = base + '/apis';
        }
        return '<article class="st-api-card" data-category="' + escapeHtml(String(api.category || '')) + '" data-name="' + escapeHtml((api.name || '').toLowerCase()) + '" data-desc="' + escapeHtml((api.desc || '').toLowerCase()) + '">' +
            '<a class="st-api-card__link" href="' + escapeHtml(detailUrl) + '">' +
            '<div class="st-api-card__head">' +
            '<div class="st-api-card__methods">' + methodHtml + '</div>' +
            '<span class="st-api-card__badge">免费</span></div>' +
            '<h3 class="st-api-card__title">' + escapeHtml(api.name || '') + '</h3>' +
            '<code class="st-api-card__endpoint">' + (endpoint ? escapeHtml(endpoint) : '&nbsp;') + '</code>' +
            '</a></article>';
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initHomeApiList() {
        var grid = document.getElementById('stApiGrid');
        var payload = window.stApiPayload;
        if (!grid || !payload || !Array.isArray(payload.apiData)) {
            return;
        }
        var limit = window.stHomePreviewLimit || 8;
        var currentCat = 'all';
        var currentSearch = '';

        function render() {
            var keyword = currentSearch.toLowerCase().trim();
            var filtered = payload.apiData.filter(function (api) {
                if (currentCat !== 'all' && String(api.category) !== String(currentCat)) {
                    return false;
                }
                if (keyword) {
                    var name = String(api.name || '').toLowerCase();
                    var desc = String(api.desc || '').toLowerCase();
                    if (name.indexOf(keyword) === -1 && desc.indexOf(keyword) === -1) {
                        return false;
                    }
                }
                return true;
            });
            var slice = filtered.slice(0, limit);
            if (slice.length === 0) {
                grid.innerHTML = '<div class="st-api-empty st-api-empty--inline"><p class="st-api-empty__title">没有找到相关接口</p></div>';
                return;
            }
            grid.innerHTML = slice.map(buildApiCardHtml).join('');
        }

        bindCatBar(document.getElementById('stCatBar'), function (cat) {
            currentCat = cat || 'all';
            render();
        });
        bindSearchClear(document.getElementById('stSearchInput'), document.getElementById('stSearchClear'));
        var searchInput = document.getElementById('stSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentSearch = searchInput.value || '';
                render();
            });
        }
        render();
    }

    function initApisPage() {
        var page = document.getElementById('stApisPage');
        var grid = document.getElementById('stApisGrid');
        var pagination = document.getElementById('stApisPagination');
        var totalEl = document.getElementById('stApiTotalCount');
        if (!page || !grid) {
            return;
        }
        var allCards = Array.from(grid.querySelectorAll('.st-api-card'));
        var currentCat = 'all';
        var currentPage = 1;
        var pageSize = 20;

        function applyFilter() {
            var searchInput = document.getElementById('stApisSearchInput');
            var keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
            var filtered = allCards.filter(function (card) {
                if (currentCat !== 'all' && card.getAttribute('data-category') !== String(currentCat)) {
                    return false;
                }
                if (keyword) {
                    var name = card.getAttribute('data-name') || '';
                    var desc = card.getAttribute('data-desc') || '';
                    if (name.indexOf(keyword) === -1 && desc.indexOf(keyword) === -1) {
                        return false;
                    }
                }
                return true;
            });
            if (totalEl) {
                totalEl.textContent = String(filtered.length);
            }
            var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            var start = (currentPage - 1) * pageSize;
            var pageCards = filtered.slice(start, start + pageSize);
            allCards.forEach(function (card) { card.style.display = 'none'; });
            pageCards.forEach(function (card) { card.style.display = ''; });
            var emptyEl = grid.querySelector('.st-api-empty--inline');
            if (pageCards.length === 0) {
                if (!emptyEl) {
                    emptyEl = document.createElement('div');
                    emptyEl.className = 'st-api-empty st-api-empty--inline';
                    emptyEl.innerHTML = '<p class="st-api-empty__title">没有找到相关接口</p>';
                    grid.appendChild(emptyEl);
                }
                emptyEl.style.display = '';
            } else if (emptyEl) {
                emptyEl.style.display = 'none';
            }
            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            if (!pagination) {
                return;
            }
            if (totalPages <= 1) {
                pagination.style.display = 'none';
                return;
            }
            pagination.style.display = '';
            var html = '';
            if (currentPage > 1) {
                html += '<a href="javascript:void(0)" data-page="' + (currentPage - 1) + '">上一页</a>';
            }
            var start = Math.max(1, currentPage - 2);
            var end = Math.min(totalPages, currentPage + 2);
            for (var i = start; i <= end; i++) {
                if (i === currentPage) {
                    html += '<span class="is-active">' + i + '</span>';
                } else {
                    html += '<a href="javascript:void(0)" data-page="' + i + '">' + i + '</a>';
                }
            }
            if (currentPage < totalPages) {
                html += '<a href="javascript:void(0)" data-page="' + (currentPage + 1) + '">下一页</a>';
            }
            pagination.innerHTML = html;
            pagination.querySelectorAll('[data-page]').forEach(function (link) {
                link.addEventListener('click', function () {
                    currentPage = parseInt(link.getAttribute('data-page'), 10) || 1;
                    applyFilter();
                    window.scrollTo({ top: page.offsetTop - 80, behavior: 'smooth' });
                });
            });
        }

        bindCatBar(document.getElementById('stApisCatBar'), function (cat) {
            currentCat = cat || 'all';
            currentPage = 1;
            applyFilter();
        });
        bindSearchClear(document.getElementById('stApisSearchInput'), document.getElementById('stApisSearchClear'));
        var apisSearch = document.getElementById('stApisSearchInput');
        if (apisSearch) {
            apisSearch.addEventListener('input', function () {
                currentPage = 1;
                applyFilter();
            });
        }
        applyFilter();
    }

    var home = document.getElementById('stHome');
    if (home) {
        var nums = home.querySelectorAll('.st-stat-num');
        nums.forEach(function (el, i) {
            animateNum(el, 0, 600 + i * 120);
        });
        initHomeApiList();
        return;
    }

    if (document.getElementById('stApisPage')) {
        initApisPage();
    }
})();
