/**
 * 文件：assets/js/common.js
 * 作用：misc-api 全局公共脚本（Toast、JSON 解析）
 * @version 1.0.0
 */

(function (global) {
    'use strict';

    global.VS = global.VS || {};
    global.VS.version = '2.0.0';

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
     * @returns {Promise<object>}
     */
    global.VS.postForm = function (formOrData, url) {
        var body = formOrData instanceof FormData ? formOrData : new FormData(formOrData);
        global.VS.ensureCsrf(body);

        return fetch(url || window.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (res) {
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
