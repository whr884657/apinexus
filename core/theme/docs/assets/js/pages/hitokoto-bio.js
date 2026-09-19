/**
 * 默认主题：未配置个人简介时拉取一言（纯随机，无分类参数）
 * 文档：https://developer.hitokoto.cn/sentence/
 * 仅填充 [data-vs-hitokoto="1"]；用 textContent，禁止 innerHTML
 */
(function () {
    'use strict';

    var ENDPOINTS = [
        'https://international.v1.hitokoto.cn/?encode=json',
        'https://v1.hitokoto.cn/?encode=json'
    ];
    var FALLBACK = '独立开发者 / 接口贡献者';
    var MAX_LEN = 120;
    var GAP_MS = 400;
    var TIMEOUT_MS = 4000;

    function sanitize(text) {
        text = String(text || '').replace(/[\u0000-\u001F\u007F]/g, '').trim();
        if (text.length > MAX_LEN) {
            text = text.slice(0, MAX_LEN);
        }
        return text;
    }

    function fetchOne(attempt) {
        attempt = attempt || 0;
        if (attempt >= ENDPOINTS.length) {
            return Promise.reject(new Error('exhausted'));
        }

        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) {
                ctrl.abort();
            }
        }, TIMEOUT_MS);

        var opts = {
            method: 'GET',
            credentials: 'omit',
            cache: 'no-store',
            mode: 'cors'
        };
        if (ctrl) {
            opts.signal = ctrl.signal;
        }

        return fetch(ENDPOINTS[attempt], opts)
            .then(function (res) {
                clearTimeout(timer);
                if (!res || !res.ok) {
                    throw new Error('http');
                }
                return res.json();
            })
            .then(function (data) {
                var t = sanitize(data && data.hitokoto);
                if (!t) {
                    throw new Error('empty');
                }
                return t;
            })
            .catch(function () {
                clearTimeout(timer);
                return fetchOne(attempt + 1);
            });
    }

    function fillQueue(nodes) {
        var i = 0;

        function next() {
            if (i >= nodes.length) {
                return;
            }
            var el = nodes[i++];
            if (!el || !el.getAttribute || el.getAttribute('data-vs-hitokoto') !== '1') {
                setTimeout(next, 0);
                return;
            }
            fetchOne()
                .then(function (t) {
                    el.textContent = t;
                })
                .catch(function () {
                    el.textContent = FALLBACK;
                })
                .then(function () {
                    el.removeAttribute('data-vs-hitokoto');
                    setTimeout(next, GAP_MS);
                });
        }

        next();
    }

    function run() {
        var nodes = document.querySelectorAll('[data-vs-hitokoto="1"]');
        if (!nodes.length) {
            return;
        }
        fillQueue(Array.prototype.slice.call(nodes));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
