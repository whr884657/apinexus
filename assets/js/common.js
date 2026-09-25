/**
 * 文件：assets/js/common.js
 * 作用：ApiNexus 全局公共脚本（Toast、JSON 解析）
 * @version 1.0.0
 */

(function (global) {
    'use strict';

    global.VS = global.VS || {};
    global.VS.version = '2.0.0';

    /** 与 PHP VS_TRANSPORT_PREFIX 对齐；服务端仍兼容旧 VS64: */
    var VS_TRANSPORT_PREFIX = 'VS64B:';
    var VS_TRANSPORT_PREFIX_LEGACY = 'VS64:';
    var VS_TRANSPORT_MAX_BYTES = 300000;

    /**
     * 将可能含代码样例的字段编码为 VS64B:Base64，规避 WAF 语义分析误拦
     * 服务端用 vs_decode_transport_field 还原；空串不编码。
     *
     * @param {string} value
     * @returns {string}
     */
    global.VS.encodeTransportField = function (value) {
        if (value == null) {
            return '';
        }
        var s = String(value);
        if (s === '') {
            return '';
        }
        if (s.indexOf(VS_TRANSPORT_PREFIX) === 0 || s.indexOf(VS_TRANSPORT_PREFIX_LEGACY) === 0) {
            return s;
        }
        try {
            var b64 = btoa(unescape(encodeURIComponent(s)));
            var out = VS_TRANSPORT_PREFIX + b64;
            if (out.length > VS_TRANSPORT_MAX_BYTES) {
                throw new Error('字段内容过大（超过传输上限），请缩短后再保存');
            }
            return out;
        } catch (e) {
            if (e && e.message && e.message.indexOf('超过传输上限') >= 0) {
                throw e;
            }
            throw new Error('字段无法编码，请检查内容后重试');
        }
    };

    /**
     * 对 payload 中指定键做传输编码（原地修改并返回）
     *
     * @param {object} payload
     * @param {string[]} [keys]
     * @returns {object}
     */
    global.VS.encodeTransportFields = function (payload, keys) {
        if (!payload || typeof payload !== 'object') {
            return payload;
        }
        keys = keys || ['doc', 'aidoc', 'response', 'params', 'content', 'body'];
        keys.forEach(function (key) {
            if (Object.prototype.hasOwnProperty.call(payload, key) && payload[key] != null && payload[key] !== '') {
                payload[key] = global.VS.encodeTransportField(payload[key]);
            }
        });
        return payload;
    };

    /**
     * 为 FormData 自动附加 CSRF（若表单未含 csrf_token）
     *
     * @param {FormData} body
     * @returns {FormData}
     */
    global.VS.ensureCsrf = function (body) {
        if (body && !body.has('csrf_token') && global.VS_CSRF_TOKEN) {
            body.append('csrf_token', global.VS_CSRF_TOKEN);
        }
        return body;
    };

    /**
     * 安全 POST（同源 fetch + CSRF + JSON 解析）
     *
     * @param {HTMLFormElement|FormData} formOrData
     * @param {string} [url]
     * @param {{signal?: AbortSignal}} [opts]
     * @returns {Promise<object>}
     */
    global.VS.postForm = function (formOrData, url, opts) {
        var body = formOrData instanceof FormData ? formOrData : new FormData(formOrData);
        global.VS.ensureCsrf(body);
        opts = opts || {};

        var fetchOpts = {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        };
        if (opts.signal) {
            fetchOpts.signal = opts.signal;
        }

        return fetch(url || window.location.href, fetchOpts).then(function (res) {
            return res.text().then(function (text) {
                var data = global.VS.parseJsonResponse(text);
                if (!data) {
                    throw new Error('invalid_json');
                }
                return data;
            });
        });
    };

    /**
     * POST 并按 SSE 逐事件回调（AI 流式）
     * handlers: { meta?, delta?, done?, error?, ping? }
     *
     * @param {FormData} formData
     * @param {string} [url]
     * @param {object} [handlers]
     * @param {{signal?: AbortSignal}} [opts]
     * @returns {Promise<object>} done 事件 data；error 时 reject
     */
    global.VS.postFormSse = function (formData, url, handlers, opts) {
        global.VS.ensureCsrf(formData);
        handlers = handlers || {};
        opts = opts || {};
        var fetchOpts = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { Accept: 'text/event-stream' }
        };
        if (opts.signal) {
            fetchOpts.signal = opts.signal;
        }
        return fetch(url || window.location.href, fetchOpts).then(function (res) {
            var ctype = (res.headers.get('content-type') || '').toLowerCase();
            // 网关/PHP 未进入 SSE 时常见 502/504 HTML；先判状态，避免按流读到一半才炸
            if (!res.ok && ctype.indexOf('text/event-stream') < 0) {
                return res.text().then(function (text) {
                    var data = global.VS.parseJsonResponse(text);
                    var errMsg = (data && data.msg) ? String(data.msg) : ('请求失败（HTTP ' + res.status + '）');
                    if (handlers.error) {
                        handlers.error({ msg: errMsg, http: res.status });
                    }
                    var err = new Error(errMsg);
                    err.sseHandled = true;
                    throw err;
                });
            }
            if (!res.body || typeof res.body.getReader !== 'function') {
                return res.text().then(function (text) {
                    var data = global.VS.parseJsonResponse(text);
                    if (data && data.code === 1 && data.doc != null && handlers.done) {
                        handlers.done(data);
                        return data;
                    }
                    if (data && data.code === 0) {
                        var errMsg = data.msg || '生成失败';
                        if (handlers.error) {
                            handlers.error({ msg: errMsg });
                        }
                        var e0 = new Error(errMsg);
                        e0.sseHandled = true;
                        throw e0;
                    }
                    throw new Error('invalid_json');
                });
            }
            // 非 SSE 却返回 JSON（兼容旧路径）
            if (ctype.indexOf('text/event-stream') < 0 && ctype.indexOf('json') >= 0) {
                return res.text().then(function (text) {
                    var data = global.VS.parseJsonResponse(text);
                    if (data && data.code === 1 && (data.doc != null || data.piece != null)) {
                        if (handlers.delta && data.doc != null) {
                            handlers.delta({ text: String(data.doc) });
                        }
                        if (handlers.done) {
                            handlers.done(data);
                        }
                        return data;
                    }
                    throw new Error((data && data.msg) || 'invalid_json');
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            var donePayload = null;
            var errorPayload = null;

            function dispatchBlock(block) {
                var lines = block.split(/\r?\n/);
                var eventName = 'message';
                var dataLines = [];
                lines.forEach(function (line) {
                    if (line.indexOf('event:') === 0) {
                        eventName = line.slice(6).trim();
                    } else if (line.indexOf('data:') === 0) {
                        dataLines.push(line.slice(5).replace(/^\s/, ''));
                    }
                });
                if (!dataLines.length && eventName === 'message') {
                    return;
                }
                var raw = dataLines.join('\n');
                var payload = null;
                try {
                    payload = raw ? JSON.parse(raw) : {};
                } catch (e) {
                    payload = { text: raw };
                }
                if (eventName === 'meta' && handlers.meta) {
                    handlers.meta(payload);
                } else if (eventName === 'delta' && handlers.delta) {
                    handlers.delta(payload);
                } else if (eventName === 'done') {
                    donePayload = payload;
                    if (handlers.done) {
                        handlers.done(payload);
                    }
                } else if (eventName === 'error') {
                    errorPayload = payload;
                    if (handlers.error) {
                        handlers.error(payload);
                    }
                }
            }

            function pump() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        if (buffer.trim()) {
                            dispatchBlock(buffer);
                            buffer = '';
                        }
                        if (errorPayload) {
                            // 已回调 handlers.error：用可识别错误结束，避免外层再当「未处理崩溃」
                            var err = new Error((errorPayload && errorPayload.msg) || '生成失败');
                            err.sseHandled = true;
                            err.sseError = errorPayload;
                            throw err;
                        }
                        if (!donePayload) {
                            throw new Error('流式结束但未收到完成事件');
                        }
                        return donePayload;
                    }
                    buffer += decoder.decode(result.value, { stream: true });
                    var parts = buffer.split(/\n\n/);
                    buffer = parts.pop() || '';
                    parts.forEach(function (block) {
                        if (block && block.trim()) {
                            dispatchBlock(block);
                        }
                    });
                    return pump();
                });
            }
            return pump();
        });
    };

    /**
     * @param {string} message
     * @param {string} [type] success|error|info
     */
    global.VS.showMessage = function (message, type) {
        if (global.VsToast) {
            global.VsToast.show(message, type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success'));
        }
    };

    /**
     * 后台统一图标刷新按钮：开始/结束旋转。
     * 禁止用 disabled（Chromium 下禁用按钮子元素 CSS animation 常不转，手机端却正常）。
     */
    global.VsRefreshBtn = {
        start: function (el) {
            if (!el) {
                return;
            }
            el.classList.add('is-spinning');
            el.setAttribute('aria-busy', 'true');
            el.setAttribute('aria-disabled', 'true');
        },
        stop: function (el) {
            if (!el) {
                return;
            }
            el.classList.remove('is-spinning');
            el.removeAttribute('aria-busy');
            el.removeAttribute('aria-disabled');
        },
        isBusy: function (el) {
            return !!(el && el.classList.contains('is-spinning'));
        },
    };

    /**
     * http:// → https://，消除 HTTPS 页 Mixed Content 控制台噪音与弱 WebView 崩溃风险
     *
     * @param {string} url
     * @returns {string}
     */
    global.VS.upgradeInsecureUrl = function (url) {
        var s = String(url == null ? '' : url).trim();
        if (!s) {
            return '';
        }
        if (/^http:\/\//i.test(s)) {
            return 'https://' + s.slice(7);
        }
        return s;
    };

    /**
     * 外链图标加载失败时隐藏 img，避免弱 WebView 连环报错
     *
     * @param {ParentNode|null} root
     */
    global.VS.bindExternalImgFallback = function (root) {
        var scope = root && root.querySelectorAll ? root : document;
        var imgs = scope.querySelectorAll
            ? scope.querySelectorAll('img[data-ext-icon], img.link-avatar, .vs-link-row__icon img, .donate-sponsor-card__avatar, .th3-sponsor-card__avatar, .th3-contrib-card__avatar, .th3-cmt-avatar, .partner-tile img, .partners-grid img')
            : [];
        Array.prototype.forEach.call(imgs, function (img) {
            if (!img || img.getAttribute('data-ext-bound') === '1') {
                return;
            }
            img.setAttribute('data-ext-bound', '1');
            var src = img.getAttribute('src') || '';
            if (/^http:\/\//i.test(src)) {
                img.setAttribute('src', global.VS.upgradeInsecureUrl(src));
            }
            img.addEventListener('error', function onImgErr() {
                img.removeEventListener('error', onImgErr);
                img.removeAttribute('src');
                img.setAttribute('aria-hidden', 'true');
                img.style.display = 'none';
                var wrap = img.parentNode;
                if (wrap && !wrap.querySelector('[data-ext-icon-fallback]')) {
                    var fb = document.createElement('span');
                    fb.setAttribute('data-ext-icon-fallback', '1');
                    fb.className = img.className || 'link-avatar';
                    fb.textContent = (img.getAttribute('alt') || '?').charAt(0) || '?';
                    wrap.appendChild(fb);
                }
            });
        });
    };

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                global.VS.bindExternalImgFallback(document);
            });
        } else {
            global.VS.bindExternalImgFallback(document);
        }
    }

    /**
     * 从可能含 BOM / 杂讯的响应文本中解析 JSON
     *
     * @param {string} text
     * @returns {object|null}
     */
    global.VS.parseJsonResponse = function (text) {
        if (text == null) {
            return null;
        }
        var s = String(text).replace(/^\uFEFF/, '').trim();
        if (!s) {
            return null;
        }
        try {
            return JSON.parse(s);
        } catch (e1) {
            var start = s.indexOf('{');
            var end = s.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    return JSON.parse(s.substring(start, end + 1));
                } catch (e2) {}
            }
        }
        return null;
    };

    /**
     * 前台公开接口目录（POST + CSRF；首屏不灌大包）
     * 凭证/来源失败或偶发网络毛刺时自动重试一次（对齐登录页 VsAuthCsrf，E308）
     *
     * @param {{partners?: boolean}} [opts]
     * @param {number} [attempt]
     * @returns {Promise<object>}
     */
    global.VS.fetchFrontCatalog = function (opts, attempt) {
        opts = opts || {};
        attempt = Number(attempt) || 0;
        var url = global.VS_FRONT_CATALOG
            || ((global.VS_BASE_URL || '') + '/core/front/catalog.php');

        function buildBody() {
            var fd = new FormData();
            fd.append('action', 'list');
            if (opts.partners) {
                fd.append('partners', '1');
            }
            return fd;
        }

        function isCredFail(data) {
            if (!data || typeof data !== 'object' || Number(data.code) === 1) {
                return false;
            }
            if (data.csrf) {
                return true;
            }
            var msg = String(data.msg || '');
            return /凭证|csrf|刷新页面|来源无效/i.test(msg);
        }

        function isTransientErr(err) {
            if (!err) {
                return false;
            }
            var m = String(err.message || '');
            return m === 'invalid_json'
                || m === 'Failed to fetch'
                || m === 'NetworkError when attempting to fetch resource.'
                || err.name === 'TypeError';
        }

        return global.VS.postForm(buildBody(), url).then(function (data) {
            if (data && data.csrf) {
                global.VS_CSRF_TOKEN = data.csrf;
            }
            if (data && Number(data.code) === 1) {
                return data;
            }
            if (attempt < 1 && isCredFail(data)) {
                return global.VS.fetchFrontCatalog(opts, attempt + 1);
            }
            throw new Error((data && data.msg) ? data.msg : '目录加载失败');
        }).catch(function (err) {
            if (attempt < 1 && isTransientErr(err)) {
                return global.VS.fetchFrontCatalog(opts, attempt + 1);
            }
            throw err;
        });
    };

    /**
     * 前台友情链接（POST + CSRF；页脚/友链页不灌 SSR 列表，E355）
     *
     * @param {{action?: 'footer'|'page', limit?: number}} [opts]
     * @param {number} [attempt]
     * @returns {Promise<object>}
     */
    global.VS.fetchFrontLinks = function (opts, attempt) {
        opts = opts || {};
        attempt = Number(attempt) || 0;
        var url = global.VS_FRONT_LINKS
            || ((global.VS_BASE_URL || '') + '/core/front/links.php');
        var action = opts.action === 'page' ? 'page' : 'footer';

        function buildBody() {
            var fd = new FormData();
            fd.append('action', action);
            if (action === 'footer' && opts.limit != null) {
                fd.append('limit', String(opts.limit));
            }
            return fd;
        }

        function isCredFail(data) {
            if (!data || typeof data !== 'object' || Number(data.code) === 1) {
                return false;
            }
            if (data.csrf) {
                return true;
            }
            var msg = String(data.msg || '');
            return /凭证|csrf|刷新页面|来源无效/i.test(msg);
        }

        function isTransientErr(err) {
            if (!err) {
                return false;
            }
            var m = String(err.message || '');
            return m === 'invalid_json'
                || m === 'Failed to fetch'
                || m === 'NetworkError when attempting to fetch resource.'
                || err.name === 'TypeError';
        }

        return global.VS.postForm(buildBody(), url).then(function (data) {
            if (data && data.csrf) {
                global.VS_CSRF_TOKEN = data.csrf;
            }
            if (data && Number(data.code) === 1) {
                return data;
            }
            if (attempt < 1 && isCredFail(data)) {
                return global.VS.fetchFrontLinks(opts, attempt + 1);
            }
            throw new Error((data && data.msg) ? data.msg : '友链加载失败');
        }).catch(function (err) {
            if (attempt < 1 && isTransientErr(err)) {
                return global.VS.fetchFrontLinks(opts, attempt + 1);
            }
            throw err;
        });
    };

    function vsSafeHttpUrl(raw) {
        var u = String(raw == null ? '' : raw).trim();
        if (!/^https?:\/\//i.test(u)) {
            return '';
        }
        return u;
    }

    /**
     * 页脚 #friendLinks：异步填充友链项（保留已有「申请友链」等静态锚点）
     *
     * @param {HTMLElement} el
     */
    global.VS.mountFooterFriendLinks = function (el) {
        if (!el || el.getAttribute('data-vs-mounted') === '1') {
            return;
        }
        el.setAttribute('data-vs-mounted', '1');
        var limit = parseInt(el.getAttribute('data-limit') || '8', 10);
        if (isNaN(limit) || limit < 0) {
            limit = 8;
        }
        if (limit > 10) {
            limit = 10;
        }
        var linkClass = el.getAttribute('data-link-class') || 'footer-link-item';
        var moreClass = el.getAttribute('data-more-class') || (linkClass + ' footer-link-item--more');
        var linksUrl = el.getAttribute('data-links-url') || '';
        var onLinks = el.getAttribute('data-on-links') === '1';

        global.VS.fetchFrontLinks({ action: 'footer', limit: limit }).then(function (data) {
            var items = (data && Array.isArray(data.items)) ? data.items : [];
            var frag = document.createDocumentFragment();
            items.forEach(function (item) {
                if (!item || typeof item !== 'object') {
                    return;
                }
                var href = vsSafeHttpUrl(item.siteurl);
                var name = String(item.name == null ? '' : item.name).trim();
                if (!href || !name) {
                    return;
                }
                var a = document.createElement('a');
                a.href = href;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = linkClass;
                a.setAttribute('data-friend-link', '1');
                a.textContent = name;
                frag.appendChild(a);
            });
            if (data && data.has_more && linksUrl && !onLinks) {
                var more = document.createElement('a');
                more.href = linksUrl;
                more.className = moreClass;
                more.textContent = '查看更多';
                frag.appendChild(more);
            }
            var anchor = el.querySelector('[data-footer-links-anchor]');
            if (anchor) {
                el.insertBefore(frag, anchor);
            } else {
                el.appendChild(frag);
            }
        }).catch(function () {
            /* 静默：页脚友链失败不打断整页 */
        });
    };

    /**
     * 友链页网格异步填充（按 data-layout 适配各主题卡片结构）
     *
     * @param {HTMLElement} root
     */
    global.VS.mountLinksPage = function (root) {
        if (!root || root.getAttribute('data-vs-mounted') === '1') {
            return;
        }
        root.setAttribute('data-vs-mounted', '1');
        var layout = root.getAttribute('data-layout') || 'default';
        var grid = root.querySelector('[data-vs-links-grid]');
        var emptyEl = root.querySelector('[data-vs-links-empty]');
        var truncEl = root.querySelector('[data-vs-links-truncated]');
        var loadingEl = root.querySelector('[data-vs-links-loading]');
        if (loadingEl) {
            loadingEl.hidden = false;
        }
        if (emptyEl) {
            emptyEl.hidden = true;
        }
        if (truncEl) {
            truncEl.hidden = true;
        }
        if (grid) {
            grid.innerHTML = '';
            grid.hidden = true;
        }

        function buildCard(item) {
            var href = vsSafeHttpUrl(item.siteurl);
            var name = String(item.name == null ? '' : item.name).trim();
            if (!href || !name) {
                return null;
            }
            var initial = String(item.initial || name.charAt(0) || '?');
            var icon = String(item.icon == null ? '' : item.icon).trim();
            var hasIcon = icon && /^https?:\/\//i.test(icon);
            var desc = String(item.description == null ? '' : item.description).trim();
            var host = String(item.host || item.siteurl || '');
            var a = document.createElement('a');
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.setAttribute('data-friend-link', '1');

            if (layout === 'slate') {
                a.className = 'st-link-card';
                if (hasIcon) {
                    var imgS = document.createElement('img');
                    imgS.className = 'st-link-card__avatar';
                    imgS.src = icon;
                    imgS.alt = name;
                    imgS.width = 48;
                    imgS.height = 48;
                    imgS.loading = 'lazy';
                    imgS.decoding = 'async';
                    imgS.referrerPolicy = 'no-referrer';
                    imgS.setAttribute('data-ext-icon', '1');
                    a.appendChild(imgS);
                } else {
                    var avS = document.createElement('div');
                    avS.className = 'st-link-card__avatar st-link-card__avatar--text';
                    avS.textContent = initial;
                    a.appendChild(avS);
                }
                var bodyS = document.createElement('div');
                bodyS.className = 'st-link-card__body';
                var nS = document.createElement('strong');
                nS.className = 'st-link-card__name';
                nS.textContent = name;
                bodyS.appendChild(nS);
                if (desc) {
                    var dS = document.createElement('p');
                    dS.className = 'st-link-card__desc';
                    dS.textContent = desc;
                    bodyS.appendChild(dS);
                }
                var uS = document.createElement('p');
                uS.className = 'st-link-card__url';
                uS.textContent = host;
                bodyS.appendChild(uS);
                a.appendChild(bodyS);
                return a;
            }

            if (layout === 'three') {
                a.className = 'th3-link-card card';
                if (hasIcon) {
                    var img3 = document.createElement('img');
                    img3.className = 'th3-link-card__avatar';
                    img3.src = icon;
                    img3.alt = '';
                    img3.width = 56;
                    img3.height = 56;
                    img3.loading = 'lazy';
                    img3.decoding = 'async';
                    img3.referrerPolicy = 'no-referrer';
                    img3.setAttribute('data-ext-icon', '1');
                    a.appendChild(img3);
                } else {
                    var av3 = document.createElement('span');
                    av3.className = 'th3-link-card__avatar th3-link-card__avatar--text';
                    av3.textContent = initial;
                    a.appendChild(av3);
                }
                var body3 = document.createElement('div');
                body3.className = 'th3-link-card__body';
                var n3 = document.createElement('strong');
                n3.className = 'th3-link-card__name';
                n3.textContent = name;
                body3.appendChild(n3);
                if (desc) {
                    var d3 = document.createElement('p');
                    d3.className = 'th3-link-card__desc';
                    d3.textContent = desc;
                    body3.appendChild(d3);
                }
                var u3 = document.createElement('span');
                u3.className = 'th3-link-card__url font-mono';
                u3.textContent = host;
                body3.appendChild(u3);
                a.appendChild(body3);
                return a;
            }

            if (layout === 'muming') {
                a.className = 'th5-link-card card';
                if (hasIcon) {
                    var img5 = document.createElement('img');
                    img5.className = 'th5-link-card__avatar';
                    img5.src = icon;
                    img5.alt = '';
                    img5.width = 44;
                    img5.height = 44;
                    img5.loading = 'lazy';
                    img5.decoding = 'async';
                    img5.referrerPolicy = 'no-referrer';
                    img5.setAttribute('data-ext-icon', '1');
                    a.appendChild(img5);
                } else {
                    var av5 = document.createElement('span');
                    av5.className = 'th5-link-card__avatar th5-link-card__avatar--text';
                    av5.textContent = initial;
                    a.appendChild(av5);
                }
                var body5 = document.createElement('div');
                body5.className = 'th5-link-card__body';
                var n5 = document.createElement('strong');
                n5.textContent = name;
                body5.appendChild(n5);
                if (desc) {
                    var d5 = document.createElement('p');
                    d5.textContent = desc;
                    body5.appendChild(d5);
                }
                var u5 = document.createElement('span');
                u5.className = 'font-mono text-xs text-muted';
                u5.textContent = host;
                body5.appendChild(u5);
                a.appendChild(body5);
                return a;
            }

            // default / docs
            a.className = 'link-card';
            if (hasIcon) {
                var img = document.createElement('img');
                img.className = 'link-avatar';
                img.src = icon;
                img.alt = name;
                img.loading = 'lazy';
                img.decoding = 'async';
                img.referrerPolicy = 'no-referrer';
                img.setAttribute('data-ext-icon', '1');
                a.appendChild(img);
            } else {
                var av = document.createElement('div');
                av.className = 'link-avatar';
                av.textContent = initial;
                a.appendChild(av);
            }
            var info = document.createElement('div');
            info.className = 'link-info';
            var nameEl = document.createElement('span');
            nameEl.className = 'link-name';
            nameEl.textContent = name;
            info.appendChild(nameEl);
            if (desc) {
                var pDesc = document.createElement('p');
                pDesc.className = 'link-desc';
                pDesc.textContent = desc;
                info.appendChild(pDesc);
            }
            var pUrl = document.createElement('p');
            pUrl.className = 'link-url';
            pUrl.textContent = host;
            info.appendChild(pUrl);
            a.appendChild(info);
            return a;
        }

        global.VS.fetchFrontLinks({ action: 'page' }).then(function (data) {
            if (loadingEl) {
                loadingEl.hidden = true;
            }
            var items = (data && Array.isArray(data.items)) ? data.items : [];
            if (!items.length) {
                if (emptyEl) {
                    emptyEl.hidden = false;
                }
                return;
            }
            if (data.truncated && truncEl) {
                var total = Number(data.total) || 0;
                var lim = Number(data.limit) || 0;
                var tip = truncEl.querySelector('[data-vs-links-trunc-text]');
                if (tip) {
                    tip.textContent = '当前共 ' + total + ' 条，为避免页面卡顿仅展示前 ' + lim + ' 条。';
                }
                truncEl.hidden = false;
            }
            if (!grid) {
                return;
            }
            var frag = document.createDocumentFragment();
            items.forEach(function (item) {
                var card = buildCard(item);
                if (card) {
                    frag.appendChild(card);
                }
            });
            grid.appendChild(frag);
            grid.hidden = false;
            if (global.VS && typeof global.VS.bindExternalImgFallback === 'function') {
                global.VS.bindExternalImgFallback(root);
            }
            if (!document.getElementById('linkAvatarSwingStyle')) {
                var style = document.createElement('style');
                style.id = 'linkAvatarSwingStyle';
                style.textContent = '@keyframes linkSwing{0%{transform:rotate(0)}20%{transform:rotate(-12deg)}40%{transform:rotate(10deg)}60%{transform:rotate(-6deg)}80%{transform:rotate(3deg)}100%{transform:rotate(0)}}.link-avatar-swing{animation:linkSwing .9s ease-in-out;transform-origin:center}';
                document.head.appendChild(style);
            }
            root.querySelectorAll('.link-avatar, .st-link-card__avatar, .th3-link-card__avatar, .th5-link-card__avatar').forEach(function (avEl) {
                if (avEl.getAttribute('data-swing-bound') === '1') {
                    return;
                }
                avEl.setAttribute('data-swing-bound', '1');
                avEl.addEventListener('click', function () {
                    avEl.classList.remove('link-avatar-swing');
                    void avEl.offsetWidth;
                    avEl.classList.add('link-avatar-swing');
                });
            });
        }).catch(function () {
            if (loadingEl) {
                loadingEl.hidden = true;
            }
            if (emptyEl) {
                emptyEl.hidden = false;
                var p = emptyEl.querySelector('p, .st-card__desc');
                if (p) {
                    p.textContent = '友链加载失败，请刷新重试';
                }
            }
        });
    };

    function vsAutoMountFrontLinks() {
        var foot = document.getElementById('friendLinks');
        if (foot && foot.getAttribute('data-vs-footer-links') === '1') {
            global.VS.mountFooterFriendLinks(foot);
        }
        var page = document.querySelector('[data-vs-links-page]');
        if (page) {
            global.VS.mountLinksPage(page);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', vsAutoMountFrontLinks);
    } else {
        vsAutoMountFrontLinks();
    }

    /**
     * 数据加载动效 HTML（列表 / 详情面板统一用）
     *
     * @param {string} [label]
     * @param {boolean} [compact]
     * @returns {string}
     */
    global.VS.loadingHtml = function (label, compact) {
        var text = String(label == null || label === '' ? '正在加载' : label);
        if (text === '加载中' || text === '加载中…' || text === '加载中...') {
            text = '正在加载';
        }
        var safe = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        return '<div class="vs-loading' + (compact ? ' vs-loading--compact' : '') + '" role="status" aria-live="polite" aria-busy="true">'
            + '<div class="vs-loading__orbit" aria-hidden="true">'
            + '<span class="vs-loading__ring"></span><span class="vs-loading__dot"></span></div>'
            + '<p class="vs-loading__text">' + safe + '</p></div>';
    };

    /**
     * 将容器设为加载态
     *
     * @param {HTMLElement|null} el
     * @param {string} [label]
     * @param {boolean} [compact]
     */
    global.VS.setLoading = function (el, label, compact) {
        if (!el) {
            return;
        }
        el.innerHTML = global.VS.loadingHtml(label, compact);
    };

    var toastHost = null;

    function ensureToastHost() {
        if (toastHost && toastHost.parentNode) {
            return toastHost;
        }
        toastHost = document.getElementById('vsToastHost');
        if (!toastHost) {
            toastHost = document.createElement('div');
            toastHost.id = 'vsToastHost';
            toastHost.className = 'vs-toast-host';
            toastHost.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastHost);
        }
        return toastHost;
    }

    global.VsToast = {
        /**
         * @param {string} message
         * @param {string} type success|error|info
         * @param {number} duration ms
         */
        show: function (message, type, duration) {
            if (!message) {
                return;
            }
            type = type || 'success';
            duration = duration == null ? 2600 : duration;

            var host = ensureToastHost();
            var el = document.createElement('div');
            el.className = 'vs-toast vs-toast--' + type;
            var text = document.createElement('span');
            text.className = 'vs-toast__text';
            text.textContent = message;
            el.appendChild(text);
            host.appendChild(el);

            global.requestAnimationFrame(function () {
                el.classList.add('is-visible');
            });

            global.setTimeout(function () {
                el.classList.remove('is-visible');
                global.setTimeout(function () {
                    if (el.parentNode) {
                        el.parentNode.removeChild(el);
                    }
                }, 320);
            }, duration);
        }
    };
})(window);
