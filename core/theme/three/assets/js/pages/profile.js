/**
 * 主题三 · 贡献者主页：#apiSearch 过滤 #apiList；可选 ping 延迟检测
 */
(function () {
    'use strict';

    var page = document.getElementById('profilePage');
    var list = document.getElementById('apiList');
    var input = document.getElementById('apiSearch');

    if (list && input) {
        var cards = Array.prototype.slice.call(list.querySelectorAll('.th3-profile-api, [data-name]'));
        input.addEventListener('input', function () {
            var q = String(input.value || '').trim().toLowerCase();
            cards.forEach(function (card) {
                var name = (card.getAttribute('data-name') || '').toLowerCase();
                var domain = (card.getAttribute('data-domain') || '').toLowerCase();
                var show = !q || name.indexOf(q) !== -1 || domain.indexOf(q) !== -1;
                card.style.display = show ? '' : 'none';
            });
        });
    }

    if (!page) {
        return;
    }

    var pingUrl = page.getAttribute('data-ping-url') || '';
    if (!pingUrl) {
        return;
    }

    var latencyCards = page.querySelectorAll('[data-domain]');
    if (!latencyCards.length) {
        return;
    }

    var domainMap = {};
    latencyCards.forEach(function (card) {
        var domain = card.getAttribute('data-domain');
        if (!domain) return;
        if (!domainMap[domain]) domainMap[domain] = [];
        domainMap[domain].push(card);
    });

    var queue = Object.keys(domainMap);
    var delay = 0;

    function setAllCards(domain, html) {
        domainMap[domain].forEach(function (card) {
            var el = card.querySelector('.api-latency-result');
            if (el) el.innerHTML = html;
        });
    }

    function paint(ms) {
        var color = ms < 50 ? '#0D9488' : ms < 100 ? '#F59E0B' : ms < 200 ? '#FF4D2D' : '#ef4444';
        var label = ms < 50 ? '极快' : ms < 100 ? '良好' : ms < 200 ? '一般' : '较慢';
        return ' · <span class="font-mono" style="color:' + color + ';font-weight:600;">' + ms + 'ms</span> <span style="font-size:0.65rem;color:var(--muted);">' + label + '</span>';
    }

    function pingNext() {
        if (!queue.length) return;
        var domain = queue.shift();
        setAllCards(domain, ' · <span style="color:var(--muted);font-size:0.7rem;">检测中…</span>');

        fetch(pingUrl + (pingUrl.indexOf('?') >= 0 ? '&' : '?') + 'host=' + encodeURIComponent(domain))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && Number(data.ok) === 1 && Number(data.avg) > 0) {
                    setAllCards(domain, paint(Math.round(Number(data.avg))));
                    return;
                }
                setAllCards(domain, ' · <span style="color:var(--muted);font-size:0.7rem;">超时</span>');
            })
            .catch(function () {
                setAllCards(domain, ' · <span style="color:var(--muted);font-size:0.7rem;">超时</span>');
            });

        delay += 120;
        setTimeout(pingNext, delay);
    }

    pingNext();
})();
