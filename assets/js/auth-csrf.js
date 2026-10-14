/**
 * 认证页 CSRF 提交助手：失败时回填新凭证并自动重试一次
 * 解决 HTTP/HTTPS 双协议、CDN 缓存导致的「登录凭证已失效」
 */
(function (global) {
    'use strict';

    function applyCsrf(form, data) {
        if (!data || !data.csrf) {
            return;
        }
        var token = String(data.csrf);
        if (form && form.csrf_token) {
            form.csrf_token.value = token;
        }
        global.VS_CSRF_TOKEN = token;
        var nodes = document.querySelectorAll('input[name="csrf_token"]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].value = token;
        }
    }

    /**
     * 是否因 CSRF/同源失败而需要带新凭证重试一次。
     * 注意：vs_auth_json 几乎总会回填 csrf 字段，禁止仅凭「有 csrf」判定失败，
     * 否则密码错误等业务失败也会静默重试，把已消耗的 Turnstile/极验票烧成「人机验证失败」（E429）。
     */
    function isCsrfFail(data) {
        if (!data || typeof data !== 'object' || data.code === 1) {
            return false;
        }
        var msg = String(data.msg || '');
        return /凭证|csrf|刷新页面|来源无效/i.test(msg);
    }

    /**
     * @param {HTMLFormElement} form
     * @param {Object=} extraFields 额外 POST 字段（如 action=login）
     * @param {number=} attempt
     * @returns {Promise<object|null>}
     */
    function postForm(form, extraFields, attempt) {
        attempt = attempt || 0;
        if (global.VsCaptcha && typeof global.VsCaptcha.applyToForm === 'function') {
            global.VsCaptcha.applyToForm(form);
        }
        var body = new FormData(form);
        if (global.VsCaptcha && typeof global.VsCaptcha.appendToFormData === 'function') {
            global.VsCaptcha.appendToFormData(body);
        }
        if (extraFields && typeof extraFields === 'object') {
            Object.keys(extraFields).forEach(function (k) {
                body.set(k, extraFields[k]);
            });
        }
        return fetch(form.action || global.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : null;
                } catch (e) {
                    data = null;
                }
                return data;
            });
        }).then(function (data) {
            applyCsrf(form, data);
            if (isCsrfFail(data) && attempt < 1 && data && data.csrf) {
                return postForm(form, extraFields, attempt + 1);
            }
            return data;
        });
    }

    global.VsAuthCsrf = {
        postForm: postForm,
        applyCsrf: applyCsrf,
        isCsrfFail: isCsrfFail
    };
})(window);
