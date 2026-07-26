/**
 * 文件：assets/js/common.js
 * 作用：ApiNexus 全局公共脚本（Toast、JSON 解析）
 * @version 1.0.0
 */

(function (global) {
    'use strict';

    global.VS = global.VS || {};
    global.VS.version = '2.0.0';

    /**
     * 将可能含代码样例的字段编码为 VS64:Base64，规避 WAF 语义分析误拦
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
        if (s.indexOf('VS64:') === 0) {
            return s;
        }
        try {
            var b64 = btoa(unescape(encodeURIComponent(s)));
            return 'VS64:' + b64;
        } catch (e) {
            return s;
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
        keys = keys || ['doc', 'aidoc', 'response', 'params', 'description', 'content', 'body'];
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
     * @param {string} message
     * @param {string} [type] success|error|info
     */
    global.VS.showMessage = function (message, type) {
        if (global.VsToast) {
            global.VsToast.show(message, type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success'));
        }
    };

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
