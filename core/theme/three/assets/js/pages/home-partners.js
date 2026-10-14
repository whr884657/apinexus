/**
 * 主题3 · 首页底部合作伙伴 / 广告网格（数据：运营中心合作伙伴）
 */
(function () {
    'use strict';

    var section = document.getElementById('home-partners');
    var grid = document.getElementById('home-partners-grid');
    if (!section || !grid) {
        return;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function safeHttpUrl(u) {
        u = String(u || '').trim();
        if (/^https?:\/\//i.test(u)) {
            return u;
        }
        return '#';
    }

    function render(list) {
        if (!Array.isArray(list) || list.length === 0) {
            section.hidden = true;
            grid.innerHTML = '';
            return;
        }
        section.hidden = false;
        grid.innerHTML = list.map(function (partner) {
            var rawName = String(partner.name || '');
            var name = escapeHtml(rawName);
            var url = escapeHtml(safeHttpUrl(partner.siteurl || ''));
            var icon = String(partner.icon || '').trim();
            var initial = escapeHtml(partner.initial || (rawName ? rawName.charAt(0) : ''));
            var fallbackClass = icon ? '' : ' th3-partner-tile--fallback';
            var media = icon
                ? '<img src="' + escapeHtml(icon) + '" alt="' + name + '" loading="lazy" decoding="async" referrerpolicy="no-referrer">'
                : '<span class="th3-partner-tile__initial" aria-hidden="true">' + initial + '</span>';
            return '<a class="th3-partner-tile' + fallbackClass + '" href="' + url + '" target="_blank" rel="noopener noreferrer" title="' + name + '">'
                + media
                + '<span class="th3-partner-tile__name">' + name + '</span></a>';
        }).join('');
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            try {
                lucide.createIcons();
            } catch (e) { /* ignore */ }
        }
    }

    function boot(waitTry) {
        waitTry = Number(waitTry) || 0;
        if (!window.VS || typeof VS.fetchFrontCatalog !== 'function') {
            if (waitTry < 50) {
                setTimeout(function () { boot(waitTry + 1); }, 40);
            }
            return;
        }
        VS.fetchFrontCatalog({ partners: true }).then(function (data) {
            render(data && data.partners ? data.partners : []);
        }).catch(function () {
            section.hidden = true;
        });
    }

    boot(0);
})();
