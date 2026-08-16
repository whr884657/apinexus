/**
 * 文件：assets/js/captcha.js
 * 作用：认证页验证码（本地图 / 极验3 / 极验4）
 *
 * 依赖：window.VS_CAPTCHA_BOOT
 */
(function (global) {
    'use strict';

    var boot = global.VS_CAPTCHA_BOOT || { enabled: 0 };
    var SCRIPT_TIMEOUT_MS = 12000;
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

    /**
     * 加载第三方脚本：若标签已在加载中，必须等 onload，禁止立刻 resolve（偶发 initGeetest 未就绪）
     */
    function loadScript(src) {
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
                if (el && el.parentNode) {
                    el.parentNode.removeChild(el);
                }
                reject(new Error(msg || '验证脚本加载失败'));
            }

            function armTimeout(el) {
                timer = setTimeout(function () {
                    fail(el, '验证脚本加载超时');
                }, SCRIPT_TIMEOUT_MS);
            }

            var exist = document.querySelector('script[data-vs-gt-src="' + src + '"]');
            if (exist) {
                if (exist.getAttribute('data-vs-gt-ready') === '1') {
                    // 脚本已就绪，再确认全局入口（防缓存残缺）
                    succeed(exist);
                    return;
                }
                if (exist.getAttribute('data-vs-gt-failed') === '1') {
                    if (exist.parentNode) {
                        exist.parentNode.removeChild(exist);
                    }
                    exist = null;
                } else {
                    armTimeout(exist);
                    exist.addEventListener('load', function () { succeed(exist); });
                    exist.addEventListener('error', function () { fail(exist, '验证脚本加载失败'); });
                    return;
                }
            }

            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.referrerPolicy = 'no-referrer';
            s.setAttribute('data-vs-gt-src', src);
            armTimeout(s);
            s.onload = function () { succeed(s); };
            s.onerror = function () { fail(s, '验证脚本加载失败'); };
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
                    language: 'zho',
                    nativeButton: {
                        width: '100%'
                    }
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
        // 仅首次聚焦换一张：解决首屏图与会话不同步；再聚焦不清空，避免改字时被刷掉
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
        // 加载中：复用同一 Promise，避免「loading 时 ensure 立刻成功却无组件」
        if (mountPromise) {
            return mountPromise;
        }
        state.loading = true;
        while (box.firstChild) {
            box.removeChild(box.firstChild);
        }
        var p = state.mode === 'gt3' ? mountGt3(box) : mountGt4(box);
        mountPromise = p.then(function () {
            state.loading = false;
        }).catch(function (err) {
            state.loading = false;
            mountPromise = null;
            state.captchaObj = null;
            box.textContent = (err && err.message) ? err.message : '验证加载失败';
            throw err;
        });
        return mountPromise;
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

    /**
     * 完整重置（发码/登录失败后）：本地换图；极验 reset
     */
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

    /**
     * 登录模式切换用：清极验票据，不刷本地图（避免限流导致偶发空白）
     */
    function clearChallenge(form) {
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
            // 首屏挂载失败时，切到验证码登录再试一次
            mount().catch(function () { /* 文案已写在挂载点 */ });
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
