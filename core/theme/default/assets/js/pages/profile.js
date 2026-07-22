/**
 * 文件：profile.js
 * 作用：个人主页壁纸切换 + 接口延迟检测 + 头像晃动
 */
(function () {
    'use strict';

    var page = document.getElementById('profilePage');
    if (!page) {
        return;
    }

    var wallpaper = page.getAttribute('data-wallpaper') || '';
    var pingUrl = page.getAttribute('data-ping-url') || '/core/ping.php';

    // 背景交叉淡入（同一壁纸源加时间戳，适配随机图 API）
    (function () {
        var bg1 = document.getElementById('bgImg1');
        var bg2 = document.getElementById('bgImg2');
        if (!bg1 || !bg2 || !wallpaper) {
            return;
        }
        var currentIndex = 1;
        var timer;

        function withStamp(url) {
            var sep = url.indexOf('?') >= 0 ? '&' : '?';
            return url + sep + 't=' + Date.now();
        }

        function preloadNext() {
            var next = currentIndex === 1 ? bg2 : bg1;
            next.onerror = function () { this.style.opacity = '0'; };
            next.src = withStamp(wallpaper);
        }

        function switchBg() {
            var current = currentIndex === 1 ? bg1 : bg2;
            var next = currentIndex === 1 ? bg2 : bg1;
            next.style.opacity = '1';
            current.style.opacity = '0';
            currentIndex = currentIndex === 1 ? 2 : 1;
            setTimeout(preloadNext, 500);
        }

        preloadNext();
        timer = setInterval(switchBg, 30000);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                clearInterval(timer);
            } else {
                clearInterval(timer);
                timer = setInterval(switchBg, 30000);
            }
        });
    })();

    // 头像晃动
    (function () {
        var box = document.getElementById('avatarBox');
        var img = document.getElementById('avatarImg');
        if (!box || !img) {
            return;
        }
        function shake() {
            img.classList.remove('avatar-shake');
            void img.offsetWidth;
            img.classList.add('avatar-shake');
            setTimeout(function () { img.classList.remove('avatar-shake'); }, 600);
        }
        setTimeout(shake, 800);
        box.addEventListener('click', shake);
    })();

    // 延迟检测
    (function () {
        var cards = page.querySelectorAll('[data-domain]');
        if (!cards.length) {
            return;
        }
        var domainMap = {};
        cards.forEach(function (card) {
            var domain = card.getAttribute('data-domain');
            if (!domain) {
                return;
            }
            if (!domainMap[domain]) {
                domainMap[domain] = [];
            }
            domainMap[domain].push(card);
        });

        var queue = Object.keys(domainMap);
        var delay = 0;

        function setAllCards(domain, html) {
            domainMap[domain].forEach(function (card) {
                var el = card.querySelector('.api-latency-result');
                if (el) {
                    el.innerHTML = html;
                }
            });
        }

        function paint(ms) {
            var color = ms < 50 ? '#22c55e' : ms < 100 ? '#eab308' : ms < 200 ? '#f97316' : '#ef4444';
            var label = ms < 50 ? '极快' : ms < 100 ? '良好' : ms < 200 ? '一般' : '较慢';
            return '<span class="font-mono font-semibold" style="color:' + color + ';">' + ms + 'ms</span> <span style="font-size:0.6rem;color:var(--text-muted);">' + label + '</span>';
        }

        function pingNext() {
            if (!queue.length) {
                return;
            }
            var domain = queue.shift();
            domainMap[domain].forEach(function (card) {
                var old = card.querySelector('.api-latency');
                if (old) {
                    old.style.display = 'none';
                }
            });
            setAllCards(domain, '<span style="color:var(--text-muted);font-size:0.7rem;">检测中...</span>');

            fetch(pingUrl + (pingUrl.indexOf('?') >= 0 ? '&' : '?') + 'host=' + encodeURIComponent(domain))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && Number(data.ok) === 1 && Number(data.avg) > 0) {
                        setAllCards(domain, paint(Math.round(Number(data.avg))));
                        return;
                    }
                    setAllCards(domain, '<span style="color:var(--text-muted);font-size:0.7rem;">超时</span>');
                })
                .catch(function () {
                    setAllCards(domain, '<span style="color:var(--text-muted);font-size:0.7rem;">超时</span>');
                });

            delay += 120;
            setTimeout(pingNext, delay);
        }

        pingNext();
    })();
})();
