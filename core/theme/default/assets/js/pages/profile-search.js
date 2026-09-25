/**
 * 文件：profile-search.js
 * 作用：个人主页接口列表 · 真正随机 / 正序 / 倒序 + 分页（对齐全部接口页）
 */
(function () {
    'use strict';

    var list = document.getElementById('apiList');
    var input = document.getElementById('apiSearch');
    var pagination = document.getElementById('profileApiPagination');
    if (!list) {
        return;
    }

    var cards = Array.prototype.slice.call(list.querySelectorAll('.api-card-stack'));
    if (!cards.length) {
        return;
    }

    var currentSort = 'random';
    var currentPage = 1;
    var pageSize = 20;
    var orderedVisible = [];
    /** 随机模式下跨翻页保留的打乱顺序 */
    var randomOrder = null;

    function cardId(card) {
        return card.getAttribute('data-id') || '';
    }

    function shuffleInPlace(arr) {
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = arr[i];
            arr[i] = arr[j];
            arr[j] = t;
        }
        return arr;
    }

    function renderPagination(totalPages) {
        if (!pagination) {
            return;
        }
        if (totalPages <= 1) {
            pagination.style.display = 'none';
            pagination.innerHTML = '';
            return;
        }
        pagination.style.display = '';
        var html = '';
        if (currentPage > 1) {
            html += '<a href="javascript:void(0)" data-page="1">首页</a>';
            html += '<a href="javascript:void(0)" data-page="' + (currentPage - 1) + '">上一页</a>';
        }
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);
        for (var i = start; i <= end; i++) {
            if (i === currentPage) {
                html += '<span class="active">' + i + '</span>';
            } else {
                html += '<a href="javascript:void(0)" data-page="' + i + '">' + i + '</a>';
            }
        }
        if (currentPage < totalPages) {
            html += '<a href="javascript:void(0)" data-page="' + (currentPage + 1) + '">下一页</a>';
        }
        pagination.innerHTML = html;
    }

    function buildRandomOrder(visible, reshuffle) {
        if (reshuffle || !randomOrder || randomOrder.length === 0) {
            randomOrder = shuffleInPlace(visible.slice());
            return randomOrder.slice();
        }
        var visMap = {};
        visible.forEach(function (c) {
            visMap[cardId(c)] = c;
        });
        var next = [];
        randomOrder.forEach(function (c) {
            var id = cardId(c);
            if (visMap[id]) {
                next.push(c);
                delete visMap[id];
            }
        });
        Object.keys(visMap).forEach(function (id) {
            next.push(visMap[id]);
        });
        randomOrder = next;
        return randomOrder.slice();
    }

    function apply(opts) {
        opts = opts || {};
        var resetPage = !!opts.resetPage;
        var reshuffle = opts.reshuffle !== false;

        var q = input ? String(input.value || '').trim().toLowerCase() : '';
        var visible = cards.filter(function (card) {
            var name = (card.getAttribute('data-name') || '').toLowerCase();
            return !q || name.indexOf(q) !== -1;
        });

        if (currentSort === 'asc') {
            randomOrder = null;
            orderedVisible = visible.slice().sort(function (a, b) {
                return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '', 'zh');
            });
        } else if (currentSort === 'desc') {
            randomOrder = null;
            orderedVisible = visible.slice().sort(function (a, b) {
                return (b.getAttribute('data-name') || '').localeCompare(a.getAttribute('data-name') || '', 'zh');
            });
        } else {
            orderedVisible = buildRandomOrder(visible, reshuffle);
        }

        var totalPages = Math.ceil(orderedVisible.length / pageSize) || 1;
        if (resetPage) {
            currentPage = 1;
        }
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }

        var start = (currentPage - 1) * pageSize;
        var pageItems = orderedVisible.slice(start, start + pageSize);

        cards.forEach(function (card) {
            card.style.display = 'none';
        });
        pageItems.forEach(function (card) {
            card.style.display = '';
            list.appendChild(card);
        });

        if (orderedVisible.length === 0) {
            var empty = list.querySelector('.profile-api-empty');
            if (!empty) {
                empty = document.createElement('p');
                empty.className = 'upf-text-muted text-sm text-center py-8 profile-api-empty';
                empty.textContent = '没有匹配的接口';
                list.appendChild(empty);
            }
            empty.style.display = '';
        } else {
            var emptyEl = list.querySelector('.profile-api-empty');
            if (emptyEl) {
                emptyEl.style.display = 'none';
            }
        }

        renderPagination(totalPages);
    }

    if (input) {
        input.addEventListener('input', function () {
            apply({ resetPage: true, reshuffle: currentSort === 'random' });
        });
    }

    document.querySelectorAll('.sort-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.sort-btn').forEach(function (el) {
                el.classList.toggle('active', el === btn);
            });
            currentSort = btn.getAttribute('data-sort') || 'random';
            apply({ resetPage: true, reshuffle: true });
        });
    });

    if (pagination) {
        pagination.addEventListener('click', function (e) {
            var a = e.target.closest('a[data-page]');
            if (!a) {
                return;
            }
            e.preventDefault();
            currentPage = parseInt(a.getAttribute('data-page'), 10) || 1;
            apply({ resetPage: false, reshuffle: false });
            var top = list.getBoundingClientRect().top + (window.pageYOffset || 0) - 80;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    }

    // 首屏：默认「随机」必须真正打乱（此前只标了 active，从未 apply，看起来像倒序）
    apply({ resetPage: true, reshuffle: true });
})();
