/**
 * 文件：assets/js/captcha.js
 * 作用：认证页验证码（本地图 / 极验3 / 极验4）
 *
 * 依赖：window.VS_CAPTCHA_BOOT
 */
(function (global) {
    'use strict';

    var boot = global.VS_CAPTCHA_BOOT || { enabled: 0 };
    var state = {
        ready: false,
        loading: false,
        mode: String(boot.mode || 'local'),
        result: null,
        captchaObj: null
    };

    function $(id) {
        return document.getElementById(id);
    }

    function ensureHidden(form, name, value) {
        if (!form) {
            return;
        }
        var el = form.querySelector('input[name="' + name + '"]');
        if (!el) {
            el = document.createElement('input');
            el.type = 'hidden';
            el.name = name;
            form.appendChild(el);
        }
        el.value = value == null ? '' : String(value);
    }

    function clearFields(form) {
        if (!form) {
            return;
        }
        ['lot_number', 'captcha_output', 'pass_token', 'gen_time',
            'geetest_challenge', 'geetest_validate', 'geetest_seccode'].forEach(function (n) {
            var el = form.querySelector('input[name="' + n + '"]');
            if (el) {
                el.value = '';
            }
        });
    }

    function applyResultToForm(form) {
        if (!form || !state.result) {
            return;
        }
        var r = state.result;
        if (state.mode === 'gt3') {
            ensureHidden(form, 'geetest_challenge', r.geetest_challenge || '');
            ensureHidden(form, 'geetest_validate', r.geetest_validate || '');
            ensureHidden(form, 'geetest_seccode', r.geetest_seccode || '');
        } else if (state.mode === 'gt4') {
            ensureHidden(form, 'lot_number', r.lot_number || '');
            ensureHidden(form, 'captcha_output', r.captcha_output || '');
            ensureHidden(form, 'pass_token', r.pass_token || '');
            ensureHidden(form, 'gen_time', r.gen_time || '');
        }
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var exist = document.querySelector('script[data-vs-gt-src="' + src + '"]');
            if (exist) {
                resolve();
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.setAttribute('data-vs-gt-src', src);
            s.onload = function () { resolve(); };
            s.onerror = function () { reject(new Error('验证脚本加载失败')); };
            document.head.appendChild(s);
        });
    }

    function mountGt4(box) {
        return loadScript('https://static.geetest.com/v4/gt4.js').then(function () {
            return new Promise(function (resolve, reject) {
                if (typeof global.initGeetest4 !== 'function') {
                    reject(new Error('验证组件不可用'));
                    return;
                }
                global.initGeetest4({
                    captchaId: String(boot.captchaId || ''),
                    product: boot.product || 'float',
                    language: 'zho'
                }, function (captcha) {
                    state.captchaObj = captcha;
                    captcha.appendTo(box);
                    captcha.onSuccess(function () {
                        state.result = captcha.getValidate() || {};
                        state.ready = true;
                        applyResultToForm(box.closest('form') || document.querySelector('form'));
                    });
                    captcha.onError(function () {
                        state.ready = false;
                        state.result = null;
                    });
                    resolve();
                });
            });
        });
    }

    function mountGt3(box) {
        var registerUrl = String(boot.register || '');
        return loadScript('https://static.geetest.com/static/tools/gt.js').then(function () {
            return new Promise(function (resolve, reject) {
                if (typeof global.initGeetest !== 'function') {
                    reject(new Error('验证组件不可用'));
                    return;
                }
                var url = registerUrl + (registerUrl.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
                fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        global.initGeetest({
                            gt: data.gt,
                            challenge: data.challenge,
                            offline: !data.success,
                            new_captcha: data.new_captcha !== false,
                            product: boot.product || 'float',
                            width: '100%'
                        }, function (captcha) {
                            state.captchaObj = captcha;
                            captcha.appendTo(box);
                            captcha.onSuccess(function () {
                                state.result = captcha.getValidate() || {};
                                state.ready = true;
                                applyResultToForm(box.closest('form') || document.querySelector('form'));
                            });
                            captcha.onError(function () {
                                state.ready = false;
                                state.result = null;
                            });
                            resolve();
                        });
                    })
                    .catch(function () {
                        reject(new Error('验证初始化失败'));
                    });
            });
        });
    }

    function refreshLocal() {
        var img = $('vsCaptchaImg');
        if (!img || !boot.image) {
            return;
        }
        img.src = String(boot.image) + (boot.image.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
        var input = $('captchaCode');
        if (input) {
            input.value = '';
        }
        state.ready = false;
    }

    function mountLocal() {
        var btn = $('vsCaptchaRefresh');
        if (btn && !btn.getAttribute('data-bound')) {
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                refreshLocal();
            });
        }
        state.ready = true;
        return Promise.resolve();
    }

    function mount() {
        if (!boot.enabled) {
            return Promise.resolve();
        }
        if (state.mode === 'local') {
            return mountLocal();
        }
        var box = $('vsCaptchaBox');
        if (!box) {
            return Promise.resolve();
        }
        if (state.loading || state.captchaObj) {
            return Promise.resolve();
        }
        state.loading = true;
        var p = state.mode === 'gt3' ? mountGt3(box) : mountGt4(box);
        return p.then(function () {
            state.loading = false;
        }).catch(function (err) {
            state.loading = false;
            box.textContent = (err && err.message) ? err.message : '验证加载失败';
            throw err;
        });
    }

    function ensure(form) {
        if (!boot.enabled) {
            return Promise.resolve(true);
        }
        if (state.mode === 'local') {
            var input = form ? form.querySelector('[name="captcha_code"]') : $('captchaCode');
            var val = input ? String(input.value || '').trim() : '';
            if (!val) {
                return Promise.reject(new Error('请输入验证码'));
            }
            return Promise.resolve(true);
        }
        return mount().then(function () {
            if (state.ready && state.result) {
                applyResultToForm(form);
                return true;
            }
            return Promise.reject(new Error('请先完成行为验证'));
        });
    }

    function reset(form) {
        if (state.mode === 'local') {
            refreshLocal();
            return;
        }
        state.ready = false;
        state.result = null;
        clearFields(form);
        if (state.captchaObj && typeof state.captchaObj.reset === 'function') {
            try {
                state.captchaObj.reset();
            } catch (e) {
                // ignore
            }
        }
    }

    function appendToFormData(fd) {
        if (!boot.enabled) {
            return fd;
        }
        if (state.mode === 'local') {
            var input = $('captchaCode');
            if (input && input.value) {
                fd.append('captcha_code', String(input.value).trim());
            }
            return fd;
        }
        if (!state.result) {
            return fd;
        }
        var r = state.result;
        if (state.mode === 'gt3') {
            fd.append('geetest_challenge', r.geetest_challenge || '');
            fd.append('geetest_validate', r.geetest_validate || '');
            fd.append('geetest_seccode', r.geetest_seccode || '');
        } else {
            fd.append('lot_number', r.lot_number || '');
            fd.append('captcha_output', r.captcha_output || '');
            fd.append('pass_token', r.pass_token || '');
            fd.append('gen_time', r.gen_time || '');
        }
        return fd;
    }

    function withPayload(form, body) {
        if (!boot.enabled) {
            return Promise.resolve(body);
        }
        return ensure(form).then(function () {
            appendToFormData(body);
            return body;
        });
    }

    global.VsCaptcha = {
        enabled: !!boot.enabled,
        mode: state.mode,
        mount: mount,
        ensure: ensure,
        reset: reset,
        refresh: refreshLocal,
        appendToFormData: appendToFormData,
        applyToForm: applyResultToForm,
        withPayload: withPayload
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); });
    } else {
        mount();
    }
})(window);
