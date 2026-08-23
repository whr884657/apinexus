/**
 * 主题 three · API 详情页（tabs / 复制 MD / 参数模式 / playground / 反馈）
 */
(function () {
    'use strict';

    var page = document.getElementById('apiDetailPage');
    if (!page) return;

    var api = window.detailApiData || null;

    function toast(msg, type) {
        if (window.VsToast && typeof window.VsToast.show === 'function') {
            window.VsToast.show(msg, type || 'info');
            return;
        }
        if (window.VS && typeof window.VS.showMessage === 'function') {
            window.VS.showMessage(msg, type || 'info');
            return;
        }
        try {
            var el = document.getElementById('detailCopyToast');
            if (el) {
                el.hidden = false;
                el.textContent = msg;
                setTimeout(function () { el.hidden = true; }, 1400);
                return;
            }
        } catch (e) { /* ignore */ }
        window.alert(msg);
    }

    function copyText(text) {
        text = String(text == null ? '' : text);
        if (!text) return Promise.reject(new Error('empty'));
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (err) {
                reject(err);
            }
            document.body.removeChild(ta);
        });
    }

    /* —— Tabs —— */
    (function initTabs() {
        var tabs = page.querySelectorAll('[data-th3-tab]');
        var panels = page.querySelectorAll('[data-th3-panel]');
        if (!tabs.length) return;

        function show(id) {
            if (!id) return;
            var ok = false;
            Array.prototype.forEach.call(tabs, function (t) {
                if (t.getAttribute('data-th3-tab') === id) ok = true;
            });
            if (!ok) return;
            Array.prototype.forEach.call(tabs, function (t) {
                var on = t.getAttribute('data-th3-tab') === id;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            Array.prototype.forEach.call(panels, function (p) {
                p.hidden = p.getAttribute('data-th3-panel') !== id;
            });
            try {
                if (history.replaceState) history.replaceState(null, '', '#' + id);
                else location.hash = id;
            } catch (e) { /* ignore */ }
        }

        Array.prototype.forEach.call(tabs, function (t) {
            t.addEventListener('click', function () {
                show(t.getAttribute('data-th3-tab'));
            });
        });

        var hash = (location.hash || '').replace(/^#/, '').toLowerCase();
        if (hash === 'play' || hash === 'test') hash = 'playground';
        if (hash) show(hash);
    })();

    /* —— 复制 Markdown —— */
    var mdBtn = document.getElementById('detailCopyMdBtn');
    if (mdBtn) {
        mdBtn.addEventListener('click', function () {
            var node = document.getElementById('detailPageMarkdownJson');
            var md = '';
            if (node) {
                try { md = JSON.parse(node.textContent || '""'); } catch (e) { md = ''; }
            }
            copyText(md).then(function () {
                toast('已复制 Markdown', 'success');
            }).catch(function () {
                toast('复制失败', 'error');
            });
        });
    }

    /* —— 通用 data-copy —— */
    page.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn || !page.contains(btn)) return;
        copyText(btn.getAttribute('data-copy') || '').then(function () {
            toast('已复制', 'success');
        }).catch(function () {
            toast('复制失败', 'error');
        });
    });

    /* —— 参数表 / JSON —— */
    (function initParamsMode() {
        var tableMode = document.getElementById('paramsTableMode');
        var jsonMode = document.getElementById('paramsJsonMode');
        if (!tableMode || !jsonMode) return;
        var modeBtns = page.querySelectorAll('[data-params-mode]');
        Array.prototype.forEach.call(modeBtns, function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-params-mode');
                Array.prototype.forEach.call(modeBtns, function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                tableMode.hidden = mode !== 'table';
                jsonMode.hidden = mode !== 'json';
            });
        });
    })();

    /* —— Playground —— */
    var sendBtn = document.getElementById('pgSendBtn');
    var responseEl = document.getElementById('pgResponse');
    var statusEl = document.getElementById('pgStatus');
    var paramsWrap = document.getElementById('pgParamsWrap');
    var urlPreview = document.getElementById('pgUrlPreview');
    var methodSelector = document.getElementById('pgMethodSelector');
    var methodHidden = document.getElementById('pgMethodHidden');
    var keyFetchPromise = null;

    function setStatus(label, kind) {
        if (!statusEl) return;
        statusEl.textContent = label || '';
        statusEl.className = 'tag' + (kind === 'ok' ? ' free' : (kind === 'err' ? ' disabled' : ''));
    }

    function getMethod() {
        if (methodSelector) {
            var active = methodSelector.querySelector('.th3-pill.is-active,[data-method].is-active');
            if (active) return String(active.getAttribute('data-method') || 'GET').toUpperCase();
        }
        if (methodHidden) return String(methodHidden.value || 'GET').toUpperCase();
        return (api && api.method) ? String(api.method).toUpperCase() : 'GET';
    }

    if (methodSelector) {
        methodSelector.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-method]');
            if (!btn) return;
            Array.prototype.forEach.call(methodSelector.querySelectorAll('[data-method]'), function (b) {
                b.classList.toggle('is-active', b === btn);
            });
        });
    }

    function hostPath(url) {
        try {
            var u = new URL(url, window.location.origin);
            return u.host + u.pathname + (u.search || '');
        } catch (e) {
            return String(url || '');
        }
    }

    function collectParams() {
        var params = {};
        if (!paramsWrap) return params;
        Array.prototype.forEach.call(paramsWrap.querySelectorAll('.param-input[data-param]'), function (input) {
            var name = input.getAttribute('data-param');
            if (!name || input.type === 'file') return;
            if (input.value) params[name] = input.value;
        });
        return params;
    }

    function keysUrl() {
        var ctx = window.playgroundKeyContext || {};
        if (ctx.keysUrl) return String(ctx.keysUrl);
        return (window.VS_BASE_URL || '') + '/core/front/playground-key.php';
    }

    function ensureApiKey() {
        var existing = (typeof window.playgroundUserApiKey === 'string') ? window.playgroundUserApiKey.trim() : '';
        if (existing) return Promise.resolve(existing);
        var ctx = window.playgroundKeyContext || {};
        if (!ctx.loggedIn) return Promise.resolve('');
        var keyCount = parseInt(String(ctx.apiKeyCount != null ? ctx.apiKeyCount : 0), 10) || 0;
        if (keyCount <= 0) return Promise.resolve('');
        if (keyFetchPromise) return keyFetchPromise;
        if (!window.VS || typeof window.VS.postForm !== 'function') return Promise.resolve('');

        var fd = new FormData();
        fd.append('action', 'get');
        keyFetchPromise = window.VS.postForm(fd, keysUrl()).then(function (data) {
            if (data && data.csrf) window.VS_CSRF_TOKEN = data.csrf;
            if (!data || Number(data.code) !== 1) {
                keyFetchPromise = null;
                return '';
            }
            if (data.apiKeyCount != null && window.playgroundKeyContext) {
                window.playgroundKeyContext.apiKeyCount = Number(data.apiKeyCount) || 0;
            }
            var key = data.apiKey != null ? String(data.apiKey).trim() : '';
            window.playgroundUserApiKey = key;
            return key;
        }).catch(function () {
            keyFetchPromise = null;
            return '';
        });
        return keyFetchPromise;
    }

    function preferredAuthWay() {
        var preferred = (typeof window.detailQsActiveAuth === 'string' && window.detailQsActiveAuth)
            ? String(window.detailQsActiveAuth).toLowerCase()
            : '';
        var keyways = (api && Array.isArray(api.keyways)) ? api.keyways : [];
        if (preferred && keyways.indexOf(preferred) >= 0) return preferred;
        return keyways.length ? String(keyways[0] || 'query').toLowerCase() : 'query';
    }

    function buildRequest(endpoint, method, params, authWay) {
        var url = endpoint;
        var headers = { Accept: 'application/json, text/plain, */*' };
        var body = null;
        var key = '';
        var rest = {};
        Object.keys(params || {}).forEach(function (k) {
            var n = String(k).toLowerCase();
            if (n === 'key' || n === 'api_key' || n === 'apikey') {
                key = params[k];
            } else {
                rest[k] = params[k];
            }
        });

        if (authWay === 'header' && key) {
            headers['X-API-Key'] = key;
        } else if (authWay === 'bearer' && key) {
            headers.Authorization = 'Bearer ' + key;
        } else if (key) {
            rest.key = key;
        }

        var m = String(method || 'GET').toUpperCase();
        if (m === 'GET' || m === 'HEAD') {
            var u = new URL(url, window.location.origin);
            Object.keys(rest).forEach(function (k) {
                u.searchParams.set(k, rest[k]);
            });
            url = u.toString();
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(rest);
        }
        return { url: url, method: m, headers: headers, body: body };
    }

    function showResponseText(text) {
        if (!responseEl) return;
        var raw = String(text == null ? '' : text);
        try {
            var obj = JSON.parse(raw);
            responseEl.textContent = JSON.stringify(obj, null, 2);
            return;
        } catch (e) { /* plain */ }
        responseEl.textContent = raw || '(空响应)';
    }

    if (sendBtn && responseEl) {
        sendBtn.addEventListener('click', function () {
            var pageApi = window.detailApiData;
            var pageId = parseInt(page.getAttribute('data-api-id') || '0', 10) || 0;
            if ((!pageApi || !pageApi.id) && pageId > 0) {
                pageApi = { id: pageId, endpoint: page.getAttribute('data-endpoint') || '' };
            }
            if (!pageApi || !pageApi.id) {
                responseEl.textContent = '接口无效';
                setStatus('Error', 'err');
                return;
            }
            api = pageApi;

            if (page.getAttribute('data-maintenance') === '1' || api.maintenance) {
                responseEl.textContent = '维护中，暂不可测试';
                setStatus('维护中', 'err');
                return;
            }
            if (page.getAttribute('data-disabled') === '1' || api.disabled) {
                responseEl.textContent = '接口已禁用';
                setStatus('已禁用', 'err');
                return;
            }

            var hasFiles = false;
            if (paramsWrap) {
                Array.prototype.forEach.call(paramsWrap.querySelectorAll('input[type="file"]'), function (f) {
                    if (f.files && f.files.length) hasFiles = true;
                });
            }
            if (hasFiles) {
                responseEl.textContent = '// 含文件上传的请求暂不支持在线调试';
                setStatus('Skip', 'wait');
                return;
            }

            ensureApiKey().then(function (keyVal) {
                var method = getMethod();
                var params = collectParams();
                var need = parseInt(api.needkey, 10) || 0;
                if ((need === 1 || need === 2) && keyVal) {
                    var hasKey = false;
                    Object.keys(params).forEach(function (k) {
                        var n = String(k).toLowerCase();
                        if (n === 'key' || n === 'api_key' || n === 'apikey') hasKey = true;
                    });
                    if (!hasKey) params.key = keyVal;
                }

                var endpoint = String(api.endpoint || page.getAttribute('data-endpoint') || '').trim();
                if (!endpoint) {
                    responseEl.textContent = '// 缺少接口地址';
                    setStatus('Error', 'err');
                    return;
                }

                if (urlPreview) urlPreview.textContent = hostPath(endpoint);
                responseEl.textContent = '// 正在发送请求...';
                setStatus('处理中', 'wait');

                var VsPR = window.VsPlaygroundResponse;
                if (!VsPR || !VsPR.directRequest) {
                    responseEl.textContent = '// 测试模块未加载，请刷新页面';
                    setStatus('Error', 'err');
                    return;
                }

                var keyways = Array.isArray(api.keyways) ? api.keyways : [];
                var authWay = VsPR.resolvePlaygroundAuthWay
                    ? VsPR.resolvePlaygroundAuthWay(keyways, preferredAuthWay())
                    : preferredAuthWay();
                var apiId = parseInt(api.id, 10) || 0;
                var useRelay = (authWay === 'header' || authWay === 'bearer') && apiId > 0;
                var start = performance.now();

                if (useRelay && VsPR.relayRequest && VsPR.renderRelayPayload) {
                    VsPR.relayRequest({
                        apiId: apiId,
                        method: method,
                        params: params,
                        authWay: authWay,
                        keyways: keyways
                    }).then(function (data) {
                        var ms = Math.round(performance.now() - start);
                        var ok = !!(data && (data.ok || data.code === 1));
                        var http = data && data.http != null ? parseInt(data.http, 10) : 0;
                        var label = ok ? ('OK ' + (http || 200)) : ((data && data.msg) ? String(data.msg) : 'Error');
                        setStatus(label + ' ' + ms + 'ms', ok ? 'ok' : 'err');
                        VsPR.renderRelayPayload(data, responseEl);
                    }).catch(function (err) {
                        setStatus('Error', 'err');
                        var raw = err && err.message ? String(err.message) : 'network error';
                        responseEl.textContent = '// 请求失败: ' + raw;
                    });
                    return;
                }

                VsPR.directRequest({
                    endpoint: endpoint,
                    method: method,
                    params: params,
                    authWay: authWay || 'query',
                    keyways: keyways
                }).then(function (res) {
                    var ms = Math.round(performance.now() - start);
                    var info = { ok: (res.status || 0) >= 200 && (res.status || 0) < 400, label: String(res.status || 0) };
                    if (VsPR.inspectFetchStatus) {
                        return VsPR.inspectFetchStatus(res).then(function (inf) {
                            info = inf;
                            return res;
                        });
                    }
                    return res;
                }).then(function (res) {
                    var ms = Math.round(performance.now() - start);
                    var ok = (res.status || 0) >= 200 && (res.status || 0) < 400;
                    setStatus(String(res.status || 0) + (ok ? ' OK' : ' Error') + ' ' + ms + 'ms', ok ? 'ok' : 'err');
                    return VsPR.renderFetchResponse(res, responseEl);
                }).catch(function (err) {
                    setStatus('Error', 'err');
                    var raw = err && err.message ? String(err.message) : 'network error';
                    var msg = /failed to fetch|networkerror|load failed/i.test(raw)
                        ? '请求失败（常见于跨域或上游未允许跨域）'
                        : raw;
                    responseEl.textContent = '// 请求失败: ' + msg;
                });
            });
        });
    }

    /* —— 反馈 —— */
    (function initFeedback() {
        var card = document.getElementById('detailFeedbackCard');
        var form = document.getElementById('detailFeedbackForm');
        if (!card || !form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var loggedIn = card.getAttribute('data-logged-in') === '1'
                || (window.playgroundKeyContext && window.playgroundKeyContext.loggedIn);
            if (!loggedIn) {
                var loginUrl = card.getAttribute('data-login-url')
                    || (window.playgroundKeyContext && window.playgroundKeyContext.loginUrl)
                    || ((window.VS_BASE_URL || '') + '/user/login');
                toast('请登录后提交反馈', 'warning');
                setTimeout(function () { window.location.href = loginUrl; }, 500);
                return;
            }
            if (card.getAttribute('data-feedback-ready') === '0') {
                toast('反馈功能暂未开放', 'error');
                return;
            }

            var ta = document.getElementById('detailFeedbackContent');
            var content = ta ? String(ta.value || '').trim() : '';
            if (content.length < 5) {
                toast('反馈内容至少 5 个字', 'error');
                return;
            }
            if (content.length > 500) {
                toast('反馈内容不能超过 500 字', 'error');
                return;
            }

            var btn = document.getElementById('detailFeedbackBtn');
            if (btn) btn.disabled = true;

            var fd = new FormData(form);
            if (window.VS_CSRF_TOKEN) fd.set('csrf_token', window.VS_CSRF_TOKEN);
            if (!fd.get('apiid')) fd.set('apiid', String(page.getAttribute('data-api-id') || '0'));

            var postUrl = window.location.pathname || window.location.href.split('?')[0];
            var req = (window.VS && typeof window.VS.postForm === 'function')
                ? window.VS.postForm(fd, postUrl)
                : fetch(postUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); });

            req.then(function (data) {
                if (data && data.csrf) window.VS_CSRF_TOKEN = data.csrf;
                if (!data || Number(data.code) !== 1) {
                    toast((data && data.msg) || '提交失败', 'error');
                    return;
                }
                toast(data.msg || '反馈已提交', 'success');
                if (ta) ta.value = '';
            }).catch(function () {
                toast('网络异常，请稍后重试', 'error');
            }).then(function () {
                if (btn) btn.disabled = false;
            });
        });
    })();
})();
