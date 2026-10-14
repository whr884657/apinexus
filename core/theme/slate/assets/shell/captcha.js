/**
 * 文件：assets/js/captcha.js
 * 作用：认证页验证码（本地图 / 极验3 / 极验4）
 *
 * 官方接入（弹窗滑块）：
 * - gt3：product=popup，appendTo 条内官方按钮 → 点击弹出 slide 滑块
 * - gt4：product=popup，appendTo 条内官方按钮 → 点击弹出 popup 滑块
 *
 * 禁止：bind、提交时 showCaptcha、挂载点占位文案、自定义 nativeButton 尺寸
 *
 * @version 3.0.0
 */
(function (global) {
    'use strict';

    var boot = global.VS_CAPTCHA_BOOT || { enabled: 0 };
    var SCRIPT_TIMEOUT_MS = 30000;
    var state = {
        ready: false,
        loading: false,
        mode: String(boot.mode || 'local'),
        result: null,
        captchaObj: null
    };
    var mountPromise = null;

    function $(id) {
        return document.getElementById(id);
    }

    function assetBase() {
        return String(boot.assetBase || '').replace(/\/$/, '');
    }

    function gtProduct() {
        if (boot.product) {
            return String(boot.product);
        }
        return 'popup';
    }

    function resolveForm(form) {
        if (form) {
            return form;
        }
        var box = $('vsCaptchaBox');
        if (box) {
            var f = box.closest('form');
            if (f) {
                return f;
            }
        }
        return document.querySelector('form');
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

    function isValidateComplete(result) {
        if (!result || result === false || typeof result !== 'object') {
            return false;
        }
        if (state.mode === 'gt3') {
            return !!(result.geetest_validate && result.geetest_challenge && result.geetest_seccode);
        }
        if (state.mode === 'gt4') {
            return !!(result.lot_number && result.captcha_output && result.pass_token && result.gen_time);
        }
        return false;
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

    function captureValidate(captcha, form) {
        if (!captcha || typeof captcha.getValidate !== 'function') {
            state.ready = false;
            state.result = null;
            return false;
        }
        var raw = captcha.getValidate();
        if (!isValidateComplete(raw)) {
            state.ready = false;
            state.result = null;
            clearFields(form);
            return false;
        }
        state.result = raw;
        state.ready = true;
        applyResultToForm(form);
        return true;
    }

    function showBoxError(box, text) {
        if (!box) {
            return;
        }
        box.innerHTML = '';
        var tip = document.createElement('div');
        tip.className = 'vs-captcha-hint vs-captcha-hint--error';
        tip.setAttribute('role', 'alert');
        tip.textContent = text;
        box.appendChild(tip);
    }

    function clearBox(box) {
        if (box) {
            box.innerHTML = '';
        }
    }

    function wireCaptchaEvents(captcha, form) {
        captcha.onSuccess(function () {
            captureValidate(captcha, form);
        });
        captcha.onError(function () {
            state.ready = false;
            state.result = null;
            clearFields(form);
        });
        if (typeof captcha.onFail === 'function') {
            captcha.onFail(function () {
                state.ready = false;
                state.result = null;
                clearFields(form);
            });
        }
        if (typeof captcha.onClose === 'function') {
            captcha.onClose(function () {
                if (!captureValidate(captcha, form)) {
                    state.result = null;
                    state.ready = false;
                }
            });
        }
    }

    function loadScriptOnce(src) {
        return new Promise(function (resolve, reject) {
            var finished = false;
            var timer = null;

            function succeed(el) {
                if (finished) {
                    return;
                }
                finished = true;
                if (timer) {
                    clearTimeout(timer);
                }
                if (el) {
                    el.setAttribute('data-vs-gt-ready', '1');
                    el.removeAttribute('data-vs-gt-failed');
                }
                resolve();
            }

            function fail(el, msg) {
                if (finished) {
                    return;
                }
                finished = true;
                if (timer) {
                    clearTimeout(timer);
                }
                if (el) {
                    el.setAttribute('data-vs-gt-failed', '1');
                    if (el.parentNode) {
                        el.parentNode.removeChild(el);
                    }
                }
                reject(new Error(msg || '验证脚本加载失败'));
            }

            var exist = document.querySelector('script[data-vs-gt-src="' + src + '"]');
            if (exist) {
                if (exist.getAttribute('data-vs-gt-ready') === '1') {
                    succeed(exist);
                    return;
                }
                if (exist.getAttribute('data-vs-gt-failed') === '1') {
                    if (exist.parentNode) {
                        exist.parentNode.removeChild(exist);
                    }
                    exist = null;
                } else {
                    timer = setTimeout(function () {
                        fail(exist, '验证脚本加载超时');
                    }, SCRIPT_TIMEOUT_MS);
                    exist.addEventListener('load', function () { succeed(exist); });
                    exist.addEventListener('error', function () { fail(exist, '验证脚本加载失败'); });
                    return;
                }
            }

            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.setAttribute('data-vs-gt-src', src);
            timer = setTimeout(function () {
                fail(s, '验证脚本加载超时');
            }, SCRIPT_TIMEOUT_MS);
            s.onload = function () { succeed(s); };
            s.onerror = function () { fail(s, '验证脚本加载失败'); };
            document.head.appendChild(s);
        });
    }

    function loadScriptWithFallback(localSrc, cdnSrc) {
        if (!localSrc) {
            return loadScriptOnce(cdnSrc);
        }
        return loadScriptOnce(localSrc).catch(function () {
            return loadScriptOnce(cdnSrc);
        });
    }

    function appendCaptcha(captcha, box) {
        if (!captcha || !box || typeof captcha.appendTo !== 'function') {
            return;
        }
        clearBox(box);
        if (box.id) {
            try {
                captcha.appendTo('#' + box.id);
                return;
            } catch (e1) {
                // fallthrough
            }
        }
        captcha.appendTo(box);
    }

    function waitCaptchaReady(captcha, readyTimer, settledRef, resolve, reject) {
        if (typeof captcha.onReady === 'function') {
            captcha.onReady(function () {
                if (settledRef.done) {
                    return;
                }
                settledRef.done = true;
                clearTimeout(readyTimer);
                resolve();
            });
        } else {
            settledRef.done = true;
            clearTimeout(readyTimer);
            resolve();
        }
    }

    function mountGt4(box) {
        var base = assetBase();
        var localSrc = base ? (base + '/assets/js/geetest/gt4.js') : '';
        var cdnSrc = 'https://static.geetest.com/v4/gt4.js';
        var form = resolveForm(null);
        clearBox(box);

        return loadScriptWithFallback(localSrc, cdnSrc).then(function () {
            return new Promise(function (resolve, reject) {
                if (typeof global.initGeetest4 !== 'function') {
                    reject(new Error('验证组件不可用'));
                    return;
                }
                var captchaId = String(boot.captchaId || '');
                if (!captchaId) {
                    reject(new Error('未配置验证 ID'));
                    return;
                }
                var settledRef = { done: false };
                var readyTimer = setTimeout(function () {
                    if (!settledRef.done) {
                        settledRef.done = true;
                        reject(new Error('验证加载超时，请刷新重试'));
                    }
                }, SCRIPT_TIMEOUT_MS);

                global.initGeetest4({
                    captchaId: captchaId,
                    product: gtProduct(),
                    language: 'zho',
                    timeout: SCRIPT_TIMEOUT_MS,
                    https: true
                }, function (captcha) {
                    state.captchaObj = captcha;
                    appendCaptcha(captcha, box);
                    wireCaptchaEvents(captcha, form);
                    waitCaptchaReady(captcha, readyTimer, settledRef, resolve, reject);
                });
            });
        });
    }

    function mountGt3(box) {
        var base = assetBase();
        var localSrc = base ? (base + '/assets/js/geetest/gt.js') : '';
        var cdnSrc = 'https://static.geetest.com/static/tools/gt.js';
        var registerUrl = String(boot.register || '');
        var form = resolveForm(null);
        clearBox(box);

        return loadScriptWithFallback(localSrc, cdnSrc).then(function () {
            return new Promise(function (resolve, reject) {
                if (typeof global.initGeetest !== 'function') {
                    reject(new Error('验证组件不可用'));
                    return;
                }
                if (!registerUrl) {
                    reject(new Error('验证初始化地址缺失'));
                    return;
                }
                var url = registerUrl + (registerUrl.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
                fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('验证初始化失败');
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data || !data.gt || !data.challenge) {
                            throw new Error('验证初始化数据无效');
                        }
                        var settledRef = { done: false };
                        var readyTimer = setTimeout(function () {
                            if (!settledRef.done) {
                                settledRef.done = true;
                                reject(new Error('验证加载超时，请刷新重试'));
                            }
                        }, SCRIPT_TIMEOUT_MS);

                        global.initGeetest({
                            gt: data.gt,
                            challenge: data.challenge,
                            offline: !data.success,
                            new_captcha: data.new_captcha !== false,
                            product: gtProduct(),
                            width: '100%',
                            https: true,
                            lang: 'zh-cn'
                        }, function (captcha) {
                            state.captchaObj = captcha;
                            appendCaptcha(captcha, box);
                            wireCaptchaEvents(captcha, form);
                            waitCaptchaReady(captcha, readyTimer, settledRef, resolve, reject);
                        });
                    })
                    .catch(function (err) {
                        reject(err instanceof Error ? err : new Error('验证初始化失败'));
                    });
            });
        });
    }

    function refreshLocal() {
        var img = $('vsCaptchaImg');
        if (!img || !boot.image) {
            return;
        }
        if (img._vsCaptchaOnDone) {
            img.removeEventListener('load', img._vsCaptchaOnDone);
            img.removeEventListener('error', img._vsCaptchaOnDone);
            img._vsCaptchaOnDone = null;
        }
        img.style.opacity = '0.45';
        var onDone = function () {
            img.style.opacity = '1';
            img.removeEventListener('load', onDone);
            img.removeEventListener('error', onDone);
            if (img._vsCaptchaOnDone === onDone) {
                img._vsCaptchaOnDone = null;
            }
        };
        img._vsCaptchaOnDone = onDone;
        img.addEventListener('load', onDone);
        img.addEventListener('error', onDone);
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
        var input = $('captchaCode');
        if (input && !input.getAttribute('data-focus-refresh-bound')) {
            input.setAttribute('data-focus-refresh-bound', '1');
            input.addEventListener('focus', function () {
                if (input.getAttribute('data-focus-refreshed') === '1') {
                    return;
                }
                input.setAttribute('data-focus-refreshed', '1');
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
        if (state.captchaObj) {
            return Promise.resolve();
        }
        if (mountPromise) {
            return mountPromise;
        }
        state.loading = true;
        var p = state.mode === 'gt3' ? mountGt3(box) : mountGt4(box);
        mountPromise = p.then(function () {
            state.loading = false;
        }).catch(function (err) {
            state.loading = false;
            mountPromise = null;
            state.captchaObj = null;
            showBoxError(box, (err && err.message) ? err.message : '验证加载失败，请刷新重试');
            throw err;
        });
        return mountPromise;
    }

    function ensure(form) {
        if (!boot.enabled) {
            return Promise.resolve(true);
        }
        form = resolveForm(form);
        if (state.mode === 'local') {
            var input = form ? form.querySelector('[name="captcha_code"]') : $('captchaCode');
            var val = input ? String(input.value || '').trim() : '';
            if (!val) {
                return Promise.reject(new Error('请输入验证码'));
            }
            return Promise.resolve(true);
        }
        return mount().then(function () {
            if (state.captchaObj && captureValidate(state.captchaObj, form)) {
                return true;
            }
            if (state.ready && state.result && isValidateComplete(state.result)) {
                applyResultToForm(form);
                return true;
            }
            return Promise.reject(new Error('请先点击验证按钮并完成滑块验证'));
        });
    }

    function reset(form) {
        form = resolveForm(form);
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

    function clearChallenge(form) {
        form = resolveForm(form);
        if (state.mode === 'local') {
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
        } else if (!state.captchaObj && !state.loading) {
            mount().catch(function () { /* 错误已写入挂载点 */ });
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
        if (state.captchaObj) {
            captureValidate(state.captchaObj, resolveForm(null));
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
        clearChallenge: clearChallenge,
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
