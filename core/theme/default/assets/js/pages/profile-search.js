/**
 * 文件：profile-search.js
 * 作用：个人主页接口列表搜索与排序（内存过滤，不改 URL）
 */
(function () {
    'use strict';

    var list = document.getElementById('apiList');
    var input = document.getElementById('apiSearch');
    if (!list) {
        return;
    }

    var cards = Array.prototype.slice.call(list.querySelectorAll('.api-card-stack'));
    var currentSort = 'random';

    function apply() {
        var q = input ? String(input.value || '').trim().toLowerCase() : '';
        var visible = cards.filter(function (card) {
            var name = (card.getAttribute('data-name') || '').toLowerCase();
            var show = !q || name.indexOf(q) !== -1;
            card.style.display = show ? '' : 'none';
            return show;
        });

        var ordered = visible.slice();
        if (currentSort === 'asc') {
            ordered.sort(function (a, b) {
                return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '', 'zh');
            });
        } else if (currentSort === 'desc') {
            ordered.sort(function (a, b) {
                return (b.getAttribute('data-name') || '').localeCompare(a.getAttribute('data-name') || '', 'zh');
            });
        } else {
            for (var i = ordered.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var t = ordered[i];
                ordered[i] = ordered[j];
                ordered[j] = t;
            }
        }
        ordered.forEach(function (card) {
            list.appendChild(card);
        });
    }

    if (input) {
        input.addEventListener('input', apply);
    }

    document.querySelectorAll('.sort-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.sort-btn').forEach(function (el) {
                el.classList.toggle('active', el === btn);
            });
            currentSort = btn.getAttribute('data-sort') || 'random';
            apply();
        });
    });
})();
