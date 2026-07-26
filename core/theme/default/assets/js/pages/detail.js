/**
 * 接口详情页：复制 / 参数表JSON切换 / Markdown / 在线测试 / JSON 高亮
 */
(function () {
    'use strict';

    var page = document.getElementById('apiDetailPage');
    if (!page) {
        return;
    }

    var toast = document.getElementById('detailCopyToast');
    var toastTimer = null;
    var VsPR = window.VsPlaygroundResponse || null;

    function showToast(msg) {
        if (window.VsToast && typeof window.VsToast.show === 'function') {
            window.VsToast.show(msg || '已复制', 'success');
            return;
        }
        if (!toast) return;
        toast.textContent = msg || '已复制';
        toast.hidden = false;
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.hidden = true; }, 1400);
    }

    function copyText(text) {
        text = String(text || '');
        if (!text) return Promise.reject();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            }
            document.body.removeChild(ta);
        });
    }

    page.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) return;
        copyText(btn.getAttribute('data-copy') || '').then(function () {
            showToast('已复制');
        }).catch(function () {});
    });

    var copyAll = document.getElementById('detailCopyAllBtn');
    if (copyAll) {
        copyAll.addEventListener('click', function () {
            var title = page.querySelector('.detail-title');
            var desc = page.querySelector('.detail-desc');
            var endpoint = page.getAttribute('data-endpoint') || '';
            var parts = [];
            if (title) parts.push(title.textContent.trim());
            if (desc) parts.push(desc.textContent.trim());
            if (endpoint) parts.push(endpoint);
            copyText(parts.join('\n')).then(function () {
                showToast('已复制全部');
            }).catch(function () {});
        });
    }

    /* ---- 参数表格 / JSON ---- */
    var tableMode = document.getElementById('paramsTableMode');
    var jsonMode = document.getElementById('paramsJsonMode');
    page.querySelectorAll('[data-params-mode]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-params-mode');
            page.querySelectorAll('[data-params-mode]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            if (mode === 'json') {
                if (tableMode) tableMode.hidden = true;
                if (jsonMode) jsonMode.hidden = false;
            } else {
                if (tableMode) tableMode.hidden = false;
                if (jsonMode) jsonMode.hidden = true;
            }
        });
    });

    /* ---- JSON 语法高亮（静态示例） ---- */
    function highlightJsonBlocks() {
        if (!VsPR || !VsPR.syntaxHighlight) return;
        page.querySelectorAll('pre.json-hl').forEach(function (pre) {
            var raw = pre.textContent || '';
            var trimmed = raw.trim();
            if (!trimmed) return;
            try {
                var obj = JSON.parse(trimmed);
                pre.innerHTML = VsPR.syntaxHighlight(JSON.stringify(obj, null, 2));
            } catch (e) {
                /* 非 JSON 保持纯文本 */
            }
        });
    }
    highlightJsonBlocks();

    /* ---- Markdown 代码块复制 / 高亮 ---- */
    function enhanceMarkdown() {
        if (window.VsMarkdown && typeof window.VsMarkdown.enhance === 'function') {
            window.VsMarkdown.enhance(page);
        } else if (window.VsSyntax && typeof window.VsSyntax.highlightAll === 'function') {
            window.VsSyntax.highlightAll(page);
        }
    }
    setTimeout(enhanceMarkdown, 0);

    /* ---- Playground ---- */
    var api = window.detailApiData;
    var sendBtn = document.getElementById('pgSendBtn');
    var responseEl = document.getElementById('pgResponse');
    var statusEl = document.getElementById('pgStatus');
    var urlPreview = document.getElementById('pgUrlPreview');
    var paramsWrap = document.getElementById('pgParamsWrap');

    function setStatus(text, kind) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'status-badge' + (kind ? ' is-' + kind : '');
    }

    function getMethod() {
        var active = document.querySelector('#pgMethodSelector .method-option.is-active');
        if (active) return (active.getAttribute('data-method') || 'GET').toUpperCase();
        var hidden = document.getElementById('pgMethodHidden');
        if (hidden) return (hidden.value || 'GET').toUpperCase();
        return (api && api.method) ? String(api.method).toUpperCase() : 'GET';
    }

    var methodSelector = document.getElementById('pgMethodSelector');
    if (methodSelector) {
        methodSelector.addEventListener('click', function (e) {
            var opt = e.target.closest('.method-option');
            if (!opt) return;
            methodSelector.querySelectorAll('.method-option').forEach(function (o) {
                o.classList.toggle('is-active', o === opt);
            });
        });
    }

    function collectParams() {
        var params = {};
        if (!paramsWrap) return params;
        paramsWrap.querySelectorAll('.param-input').forEach(function (input) {
            var name = input.getAttribute('data-param');
            if (!name || input.type === 'file') return;
            if (input.value) params[name] = input.value;
        });
        return params;
    }

    function autofillKey() {
        if (!paramsWrap || !api) return;
        var need = parseInt(api.needkey, 10) || 0;
        if (need !== 1 && need !== 2) return;
        var keyVal = (typeof window.playgroundUserApiKey === 'string') ? window.playgroundUserApiKey.trim() : '';
        var ctx = window.playgroundKeyContext || {};
        var input = null;
        paramsWrap.querySelectorAll('.param-input[data-param]').forEach(function (el) {
            var n = String(el.getAttribute('data-param') || '').toLowerCase();
            if (n === 'key' || n === 'api_key' || n === 'apikey') input = el;
        });
        if (keyVal && input && !String(input.value || '').trim()) {
            input.value = keyVal;
        }
        var old = paramsWrap.querySelector('.playground-key-hint');
        if (old) old.remove();
        var hint = document.createElement('p');
        hint.className = 'playground-key-hint';
        if (ctx.loggedIn && keyVal) {
            hint.innerHTML = '已填入可用 KEY，可直接测试。管理见 <a href="' + (ctx.userCenterUrl || '#') + '">用户中心</a>。';
        } else if (ctx.loggedIn) {
            hint.innerHTML = '账户暂无 KEY，请至 <a href="' + (ctx.userCenterUrl || '#') + '">用户中心</a> 创建。';
        } else if (need === 1) {
            hint.innerHTML = '需 KEY：请先 <a href="' + (ctx.loginUrl || '#') + '">登录</a> 后在用户中心创建。';
        } else {
            hint.innerHTML = '可选 KEY：登录后可在用户中心创建。';
        }
        paramsWrap.insertBefore(hint, paramsWrap.firstChild);
    }

    autofillKey();

    var pgAbort = null;

    if (sendBtn && responseEl) {
        sendBtn.addEventListener('click', function () {
            // 每次发送以页面 data + detailApiData 为准，避免被推荐卡污染
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
            if (pageId > 0 && parseInt(pageApi.id, 10) !== pageId) {
                pageApi.id = pageId;
                pageApi.endpoint = page.getAttribute('data-endpoint') || pageApi.endpoint || '';
            }
            api = pageApi;

            if (page.getAttribute('data-maintenance') === '1' || api.maintenance) {
                responseEl.textContent = '维护中，暂不可测试';
                setStatus('维护中', 'err');
                return;
            }

            autofillKey();
            var method = getMethod();
            var params = collectParams();
            var need = parseInt(api.needkey, 10) || 0;
            if (need === 1 || need === 2) {
                var hasKey = false;
                Object.keys(params).forEach(function (k) {
                    var n = String(k).toLowerCase();
                    if (n === 'key' || n === 'api_key' || n === 'apikey') hasKey = true;
                });
                if (!hasKey) {
                    var keyVal = (typeof window.playgroundUserApiKey === 'string') ? window.playgroundUserApiKey.trim() : '';
                    if (keyVal) params.key = keyVal;
                }
            }

            var hasFiles = false;
            if (paramsWrap) {
                paramsWrap.querySelectorAll('input[type="file"]').forEach(function (f) {
                    if (f.files && f.files.length) hasFiles = true;
                });
            }
            if (hasFiles) {
                responseEl.textContent = '// 含文件上传的请求暂不支持在线调试';
                setStatus('Skip', 'wait');
                return;
            }

            // 请求地址展示保持接口信息中的公开地址，不拼接参数、不跳转到上游
            if (urlPreview) {
                urlPreview.textContent = String(api.endpoint || page.getAttribute('data-endpoint') || '');
            }

            responseEl.textContent = '// 正在发送请求...';
            setStatus('处理中', 'wait');

            if (pgAbort) {
                try { pgAbort.abort(); } catch (e) { /* ignore */ }
            }

            if (!VsPR || !VsPR.directRequest || !VsPR.renderFetchResponse) {
                responseEl.textContent = '// 测试模块未加载，请刷新页面';
                setStatus('Error', 'err');
                return;
            }

            var endpoint = String(api.endpoint || page.getAttribute('data-endpoint') || '').trim();
            if (!endpoint) {
                responseEl.textContent = '// 缺少接口地址';
                setStatus('Error', 'err');
                return;
            }

            var authWay = (typeof window.detailQsActiveAuth === 'string' && window.detailQsActiveAuth)
                ? String(window.detailQsActiveAuth).toLowerCase()
                : '';
            var keyways = Array.isArray(api.keyways) ? api.keyways : [];
            if (!authWay && keyways.length) {
                authWay = String(keyways[0] || 'query').toLowerCase();
            }
            VsPR.directRequest({
                endpoint: endpoint,
                method: method,
                params: params,
                authWay: authWay || 'query',
                keyways: keyways
            }).then(function (res) {
                if (urlPreview) {
                    urlPreview.textContent = String(api.endpoint || page.getAttribute('data-endpoint') || '');
                }
                var statusFn = VsPR.inspectFetchStatus
                    ? VsPR.inspectFetchStatus(res)
                    : Promise.resolve({
                        ok: (res.status || 0) >= 200 && (res.status || 0) < 400,
                        label: String(res.status || '') + (((res.status || 0) >= 200 && (res.status || 0) < 400) ? ' OK' : ' Error')
                    });
                return statusFn.then(function (info) {
                    setStatus(info.label || 'Error', info.ok ? 'ok' : 'err');
                    return VsPR.renderFetchResponse(res, responseEl);
                });
            }).catch(function (err) {
                setStatus('Error', 'err');
                var raw = err && err.message ? String(err.message) : 'network error';
                var msg = /failed to fetch|networkerror|load failed/i.test(raw)
                    ? '请求失败（浏览器无法完成，常见于跨域或上游未允许跨域）'
                    : raw;
                responseEl.textContent = '// 请求失败: ' + msg;
            });
        });
    }

    /* —— 详细文档半折叠预览（默认收起但可见毛玻璃） —— */
    (function initDocFold() {
        var card = document.getElementById('detailDocCard');
        var btn = document.getElementById('detailDocToggle');
        var body = document.getElementById('detailDocBody');
        var hint = document.getElementById('detailDocHint');
        if (!card || !btn || !body) {
            return;
        }
        function setOpen(open) {
            card.classList.toggle('is-open', open);
            card.classList.toggle('is-collapsed', !open);
            body.hidden = false;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (hint) {
                hint.textContent = open ? '点击收起' : '预览 · 点击展开';
                hint.hidden = false;
            }
        }
        setOpen(false);
        btn.addEventListener('click', function () {
            setOpen(!card.classList.contains('is-open'));
        });
        body.addEventListener('click', function (e) {
            if (card.classList.contains('is-collapsed')) {
                e.preventDefault();
                setOpen(true);
            }
        });
    })();

    /* —— 接口反馈 —— */
    (function initFeedback() {
        var card = document.getElementById('detailFeedbackCard');
        var form = document.getElementById('detailFeedbackForm');
        if (!card || !form) {
            return;
        }

        function toast(msg, type) {
            if (window.VsToast && typeof window.VsToast.show === 'function') {
                window.VsToast.show(msg, type || 'info');
                return;
            }
            if (window.VS && typeof window.VS.showMessage === 'function') {
                window.VS.showMessage(msg, type || 'info');
                return;
            }
            showToast(msg);
        }

        function goLogin() {
            var url = card.getAttribute('data-login-url')
                || (window.playgroundKeyContext && window.playgroundKeyContext.loginUrl)
                || ((window.VS_BASE_URL || '') + '/user/login');
            toast('请登录后提交反馈问题', 'warning');
            setTimeout(function () {
                window.location.href = url;
            }, 600);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var loggedIn = card.getAttribute('data-logged-in') === '1'
                || (window.playgroundKeyContext && window.playgroundKeyContext.loggedIn);
            if (!loggedIn) {
                goLogin();
                return;
            }

            var ready = card.getAttribute('data-feedback-ready') !== '0';
            if (!ready) {
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
            if (btn) {
                btn.disabled = true;
            }

            var csrf = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
            var fd = new FormData(form);
            fd.set('csrf_token', csrf);
            if (!fd.get('apiid')) {
                fd.set('apiid', String(page.getAttribute('data-api-id') || '0'));
            }

            var postUrl = window.location.pathname || (window.location.href.split('?')[0]);
            var doPost = (window.VS && typeof window.VS.postForm === 'function')
                ? window.VS.postForm(fd, postUrl)
                : fetch(postUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': csrf },
                    body: fd
                }).then(function (res) {
                    return res.json().then(function (data) {
                        if (data && typeof data.csrf === 'string') {
                            window.VS_CSRF_TOKEN = data.csrf;
                        }
                        return data;
                    });
                });

            Promise.resolve(doPost).then(function (data) {
                if (btn) {
                    btn.disabled = false;
                }
                if (!data || data.code !== 1) {
                    if (data && (data.need_login === 1 || data.need_login === true)) {
                        goLogin();
                        return;
                    }
                    toast((data && data.msg) ? data.msg : '提交失败', 'error');
                    return;
                }
                toast(data.msg || '反馈已提交', 'success');
                if (ta) {
                    ta.value = '';
                }
            }).catch(function () {
                if (btn) {
                    btn.disabled = false;
                }
                toast('网络异常，请稍后重试', 'error');
            });
        });
    })();
})();
