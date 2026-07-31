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
                        throw new Error(errMsg);
                    }
                    throw new Error('invalid_json');
                });
            }
            // 非 SSE 却返回 JSON（兼容旧路径）
            if (ctype.indexOf('text/event-stream') < 0 && ctype.indexOf('json') >= 0) {
                return res.text().then(function (text) {
                    var data = global.VS.parseJsonResponse(text);
                    if (data && data.code === 1 && data.doc != null) {
                        if (handlers.delta) {
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
                            throw new Error((errorPayload && errorPayload.msg) || '生成失败');
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
            ? scope.querySelectorAll('img[data-ext-icon], img.link-avatar, .vs-link-row__icon img, .donate-sponsor-card__avatar, .partner-tile img, .partners-grid img')
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
