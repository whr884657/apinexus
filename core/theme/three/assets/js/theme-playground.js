/**
 * 主题三 · 首页在线演示（浏览器直连，返回访问者真实 IP）
 */
(function () {
    'use strict';

    var catalogApis = [];
    var currentApi = null;
    var demoSearchQ = '';
    var _keyFetch = null;

    function $(id) { return document.getElementById(id); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getKeyCtx() {
        var d = {
            loggedIn: false,
            apiKeyCount: 0,
            userCenterUrl: '/user/index',
            loginUrl: '/user/login',
            keysUrl: '/core/front/playground-key.php'
        };
        if (typeof window.playgroundKeyContext === 'object' && window.playgroundKeyContext) {
            Object.assign(d, window.playgroundKeyContext);
        }
        return d;
    }

    function getUserKey() {
        var k = window.playgroundUserApiKey;
        return (typeof k === 'string' && k.trim()) ? k.trim() : '';
    }

    function ensureUserKey() {
        var existing = getUserKey();
        if (existing) {
            return Promise.resolve(existing);
        }
        var ctx = getKeyCtx();
        if (!ctx.loggedIn || (parseInt(ctx.apiKeyCount, 10) || 0) <= 0) {
            return Promise.resolve('');
        }
        if (_keyFetch) {
            return _keyFetch;
        }
        if (!window.VS || typeof window.VS.postForm !== 'function') {
            return Promise.resolve('');
        }
        var fd = new FormData();
        fd.append('csrf_token', window.VS_CSRF_TOKEN || '');
        _keyFetch = window.VS.postForm(fd, ctx.keysUrl || ((window.VS_BASE_URL || '') + '/core/front/playground-key.php'))
            .then(function (data) {
                var key = (data && data.apiKey) ? String(data.apiKey).trim() : '';
                if (key) {
                    window.playgroundUserApiKey = key;
                }
                return key;
            })
            .catch(function () { return ''; })
            .finally(function () { _keyFetch = null; });
        return _keyFetch;
    }

    function parseParams(api) {
        var params = [];
        if (!api || !api.params) {
            return params;
        }
        try {
            params = typeof api.params === 'string' ? JSON.parse(api.params) : api.params;
        } catch (e) {
            params = [];
        }
        if (!Array.isArray(params)) {
            params = [];
        }
        var need = parseInt(api.needkey, 10) || 0;
        if (need === 1 || need === 2) {
            var hasKey = params.some(function (p) {
                var n = String(p && p.name ? p.name : '').toLowerCase();
                return n === 'key' || n === 'api_key' || n === 'apikey';
            });
            if (!hasKey) {
                params.push({
                    name: 'key',
                    type: 'string',
                    required: need === 1,
                    description: need === 1 ? '平台 API 访问密钥（必填）' : '平台 API 访问密钥（选填）',
                    placeholder: 'sk_...'
                });
            }
        }
        return params;
    }

    function getMethod(api) {
        if (!api) {
            return 'GET';
        }
        if (api.methods && api.methods.length) {
            return String(api.methods[0]).toUpperCase();
        }
        return String(api.method || 'GET').toUpperCase();
    }

    function renderParams(api) {
        var form = $('demoForm');
        if (!form) {
            return;
        }
        var params = parseParams(api);
        if (!params.length) {
            form.innerHTML = '<p class="text-sm text-muted">此接口无需参数</p>';
            return;
        }
        form.innerHTML = params.map(function (p) {
            var name = esc(p.name || '');
            var ph = esc(p.example || p.placeholder || p.description || '');
            var req = p.required ? ' <span style="color:var(--accent);">*</span>' : '';
            return '<div class="th3-pg-field">'
                + '<label class="text-xs font-medium text-fg-2">' + name + req + '</label>'
                + '<input class="input th3-pg-input" type="text" data-param="' + name + '" placeholder="' + ph + '">'
                + '</div>';
        }).join('');
    }

    function collectParams() {
        var out = {};
        var form = $('demoForm');
        if (!form) {
            return out;
        }
        form.querySelectorAll('[data-param]').forEach(function (el) {
            var k = el.getAttribute('data-param');
            var v = String(el.value || '').trim();
            if (k && v) {
                out[k] = v;
            }
        });
        return out;
    }

    function selectApi(api) {
        if (!api) {
            return;
        }
        currentApi = api;
        var demoName = $('demoName');
        var demoPath = $('demoPath');
        var demoDesc = $('demoDesc');
        var demoIconBox = $('demoIconBox');
        var demoResponse = $('demoResponse');
        var demoStatus = $('demoStatus');
        var demoLatency = $('demoLatency');

        if (demoName) {
            demoName.textContent = api.name || '接口';
        }
        if (demoPath) {
            demoPath.textContent = getMethod(api) + ' ' + (api.call_path || api.endpoint || '');
        }
        if (demoDesc) {
            demoDesc.textContent = api.desc || api.description || '';
        }
        if (demoIconBox) {
            var url = String(api.icon || '').trim();
            demoIconBox.innerHTML = url
                ? '<img class="api-icon-img" src="' + esc(url) + '" alt="" referrerpolicy="no-referrer">'
                : '<i data-lucide="zap"></i>';
            if (window.threeIconsRefresh) {
                window.threeIconsRefresh(demoIconBox);
            }
        }
        renderParams(api);
        if (demoResponse) {
            demoResponse.textContent = '// 填写参数后点击「发起调用」';
        }
        if (demoStatus) {
            demoStatus.textContent = '就绪';
        }
        if (demoLatency) {
            demoLatency.textContent = '—';
        }

        var list = $('demoApiList');
        if (list) {
            list.querySelectorAll('.demo-tab').forEach(function (c) {
                c.classList.toggle('active', String(c.getAttribute('data-id')) === String(api.id));
            });
        }
    }

    function filteredApis() {
        if (!demoSearchQ) {
            return catalogApis;
        }
        var q = demoSearchQ.toLowerCase();
        return catalogApis.filter(function (a) {
            var blob = [a.name, a.desc, a.call_path, a.endpoint, a.method].join(' ').toLowerCase();
            return blob.indexOf(q) >= 0;
        });
    }

    function renderApiList() {
        var list = $('demoApiList');
        if (!list) {
            return;
        }
        var items = filteredApis().slice(0, 50);
        if (!items.length) {
            list.innerHTML = '<p class="text-muted" style="padding:12px;">暂无匹配的接口</p>';
            return;
        }
        list.innerHTML = items.map(function (a, i) {
            var url = String(a.icon || '').trim();
            var icon = url
                ? '<img class="api-icon-img" src="' + esc(url) + '" alt="" referrerpolicy="no-referrer">'
                : '<i data-lucide="zap"></i>';
            var active = currentApi && String(currentApi.id) === String(a.id);
            if (!currentApi && i === 0) {
                active = true;
            }
            return '<button type="button" class="demo-tab' + (active ? ' active' : '') + '" data-id="' + esc(a.id) + '" role="option" aria-selected="' + (active ? 'true' : 'false') + '">'
                + '<div class="api-icon-box shrink-0 ' + ['', 'green', 'yellow'][i % 3] + '">' + icon + '</div>'
                + '<div class="flex-1 min-w-0 overflow-hidden">'
                + '<div class="text-sm font-medium truncate">' + esc(a.name || '') + '</div>'
                + '<div class="demo-tab-path text-[11px] font-mono text-muted truncate">' + esc(a.call_path || a.endpoint || '') + '</div>'
                + '</div><div class="dot" aria-hidden="true"></div></button>';
        }).join('');
        if (window.threeIconsRefresh) {
            window.threeIconsRefresh(list);
        }
        list.querySelectorAll('.demo-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var found = catalogApis.filter(function (x) { return String(x.id) === String(id); })[0];
                if (found) {
                    selectApi(found);
                }
            });
        });
        if (!currentApi && items[0]) {
            selectApi(items[0]);
        }
    }

    function sendRequest() {
        var api = currentApi;
        var demoResponse = $('demoResponse');
        var demoStatus = $('demoStatus');
        var demoLatency = $('demoLatency');
        var demoLoading = $('demoLoading');

        if (!api) {
            if (demoStatus) {
                demoStatus.textContent = '请先选择接口';
            }
            return;
        }
        if (api.maintenance == 1 || api.maintenance === '1') {
            if (demoResponse) {
                demoResponse.textContent = '维护中';
            }
            if (demoStatus) {
                demoStatus.textContent = '维护中';
            }
            return;
        }

        if (demoLoading) {
            demoLoading.classList.remove('hidden');
        }
        if (demoStatus) {
            demoStatus.textContent = '请求中…';
        }
        if (demoResponse) {
            demoResponse.textContent = '// 正在发送请求...';
        }

        var method = getMethod(api);
        var params = collectParams();
        var VsPR = window.VsPlaygroundResponse;

        ensureUserKey().then(function (keyVal) {
            var need = parseInt(api.needkey, 10) || 0;
            if ((need === 1 || need === 2) && keyVal) {
                var hasKey = false;
                Object.keys(params).forEach(function (k) {
                    var n = String(k).toLowerCase();
                    if (n === 'key' || n === 'api_key' || n === 'apikey') {
                        hasKey = true;
                    }
                });
                if (!hasKey) {
                    params.key = keyVal;
                }
            }

            if (!VsPR || !VsPR.directRequest) {
                throw new Error('测试模块未加载');
            }

            var keyways = Array.isArray(api.keyways) ? api.keyways : [];
            var authWay = VsPR.resolvePlaygroundAuthWay
                ? VsPR.resolvePlaygroundAuthWay(keyways, '')
                : (keyways.length ? String(keyways[0]).toLowerCase() : 'query');
            var endpoint = String(api.endpoint || api.call_path || '').trim();
            var start = performance.now();

            return VsPR.directRequest({
                endpoint: endpoint,
                method: method,
                params: params,
                authWay: authWay || 'query',
                keyways: keyways
            }).then(function (res) {
                var ms = Math.round(performance.now() - start);
                if (VsPR.inspectFetchStatus) {
                    return VsPR.inspectFetchStatus(res).then(function () {
                        return res;
                    });
                }
                return res;
            }).then(function (res) {
                var ms = Math.round(performance.now() - start);
                var ok = (res.status || 0) >= 200 && (res.status || 0) < 400;
                if (demoLatency) {
                    demoLatency.textContent = ms + ' ms';
                }
                if (demoStatus) {
                    demoStatus.textContent = ok ? '成功' : '失败';
                }
                return VsPR.renderFetchResponse(res, demoResponse);
            });
        }).catch(function (err) {
            var msg = (err && err.message) ? String(err.message) : '请求失败';
            if (/failed to fetch|networkerror|load failed/i.test(msg)) {
                msg = '请求失败（常见于跨域或上游未允许跨域）';
            }
            if (demoResponse) {
                demoResponse.textContent = '// ' + msg;
            }
            if (demoStatus) {
                demoStatus.textContent = '失败';
            }
        }).finally(function () {
            if (demoLoading) {
                demoLoading.classList.add('hidden');
            }
        });
    }

    function initSearch() {
        var search = $('demoApiSearch');
        if (!search) {
            return;
        }
        var t = null;
        search.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(function () {
                demoSearchQ = String(search.value || '').trim();
                renderApiList();
            }, 160);
        });
    }

    function initRun() {
        var btn = $('demoRun');
        if (btn) {
            btn.addEventListener('click', sendRequest);
        }
    }

    function onCatalog(list) {
        catalogApis = Array.isArray(list) ? list : [];
        renderApiList();
    }

    function boot() {
        initSearch();
        initRun();
        if (window.TH3_CATALOG_APIS && window.TH3_CATALOG_APIS.length) {
            onCatalog(window.TH3_CATALOG_APIS);
        }
        document.addEventListener('th3:catalog', function (ev) {
            var list = (ev && ev.detail && ev.detail.apis) ? ev.detail.apis : window.TH3_CATALOG_APIS;
            onCatalog(list);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
