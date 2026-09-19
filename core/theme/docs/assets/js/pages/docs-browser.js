(function () {
    var page = document.getElementById('apiDocsPage');
    if (!page) return;

    var tree = document.getElementById('docsTree');
    var openBtn = document.getElementById('docs4Open');
    var mask = document.getElementById('docs4Mask');
    var searchInput = document.getElementById('apiDocsSearchInput');
    var searchEmpty = document.getElementById('apiDocsSearchEmpty');
    var content = document.getElementById('docsContent');
    var nameSuffix = document.getElementById('docsTreeNameSuffix');
    var selectedNameEl = document.getElementById('docsTreeSelectedName');
    var playBox = document.getElementById('docs4Play');
    var urlPreview = document.getElementById('pgUrlPreview');
    var methodBox = document.getElementById('pgMethodSelector');
    var paramsWrap = document.getElementById('pgParamsWrap');
    var sendBtn = document.getElementById('pgSendBtn');
    var responseEl = document.getElementById('pgResponse');
    var statusEl = document.getElementById('pgStatus');
    var activePlay = null;
    var playgroundKeyFetch = null;

    function setTree(open) {
        if (!tree) return;
        tree.classList.toggle('is-open', open);
        if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (mask) mask.classList.toggle('is-on', open);
    }
    if (openBtn) openBtn.addEventListener('click', function () { setTree(true); });
    if (mask) mask.addEventListener('click', function () { setTree(false); });

    function setSelectedTitle(name) {
        var n = String(name || '').trim();
        if (selectedNameEl) selectedNameEl.textContent = n;
        if (nameSuffix) nameSuffix.hidden = !n;
    }

    function highlightPanel(panel) {
        if (!panel || !window.VsSyntax || typeof window.VsSyntax.highlightAll !== 'function') return;
        window.VsSyntax.highlightAll(panel);
    }

    function readPlay(panel) {
        var el = panel ? panel.querySelector('.d4-play-data') : null;
        if (!el) return null;
        try { return JSON.parse(el.textContent || ''); } catch (e) { return null; }
    }

    function playgroundKeysFetchUrl() {
        var ctx = window.playgroundKeyContext || {};
        return ctx.keysUrl || '';
    }

    function ensurePlaygroundUserApiKey() {
        var existing = (typeof window.playgroundUserApiKey === 'string') ? window.playgroundUserApiKey.trim() : '';
        if (existing) return Promise.resolve(existing);
        var ctx = window.playgroundKeyContext || {};
        if (!ctx.loggedIn) return Promise.resolve('');
        var keyCount = parseInt(String(ctx.apiKeyCount != null ? ctx.apiKeyCount : 0), 10) || 0;
        if (keyCount <= 0) return Promise.resolve('');
        if (playgroundKeyFetch) return playgroundKeyFetch;
        if (!window.VS || typeof window.VS.postForm !== 'function') return Promise.resolve('');
        var url = playgroundKeysFetchUrl();
        if (!url) return Promise.resolve('');
        var fd = new FormData();
        fd.append('action', 'get');
        playgroundKeyFetch = window.VS.postForm(fd, url).then(function (data) {
            if (data && data.csrf) window.VS_CSRF_TOKEN = data.csrf;
            if (!data || Number(data.code) !== 1) {
                playgroundKeyFetch = null;
                return '';
            }
            if (data.apiKeyCount != null && window.playgroundKeyContext) {
                window.playgroundKeyContext.apiKeyCount = Number(data.apiKeyCount) || 0;
            }
            var key = data.apiKey != null ? String(data.apiKey).trim() : '';
            window.playgroundUserApiKey = key;
            return key;
        }).catch(function () {
            playgroundKeyFetch = null;
            return '';
        });
        return playgroundKeyFetch;
    }

    function autofillKey() {
        if (!paramsWrap || !activePlay) return Promise.resolve();
        var need = parseInt(activePlay.needkey, 10) || 0;
        if (need !== 1 && need !== 2) return Promise.resolve();
        var ctx = window.playgroundKeyContext || {};
        var input = null;
        paramsWrap.querySelectorAll('.param-input[data-param]').forEach(function (el) {
            var n = String(el.getAttribute('data-param') || '').toLowerCase();
            if (n === 'key' || n === 'api_key' || n === 'apikey') input = el;
        });
        return ensurePlaygroundUserApiKey().then(function (keyVal) {
            if (keyVal && input && !String(input.value || '').trim()) input.value = keyVal;
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
        });
    }

    function fillPlay(panel) {
        activePlay = readPlay(panel);
        if (!activePlay || !playBox) return;
        var slot = panel.querySelector('[data-docs-slot="play"]');
        if (slot && playBox.parentNode !== slot) slot.appendChild(playBox);
        playBox.hidden = false;
        if (urlPreview) urlPreview.textContent = activePlay.endpointShow || activePlay.endpoint || '—';
        if (methodBox) {
            methodBox.innerHTML = '';
            var methods = activePlay.methods && activePlay.methods.length ? activePlay.methods : ['GET'];
            methods.forEach(function (m, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = i === 0 ? 'is-on' : '';
                btn.setAttribute('data-method', String(m).toUpperCase());
                btn.textContent = String(m).toUpperCase();
                btn.addEventListener('click', function () {
                    methodBox.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
                });
                methodBox.appendChild(btn);
            });
        }
        if (paramsWrap) {
            paramsWrap.innerHTML = '';
            var params = activePlay.params || [];
            if (!params.length) {
                paramsWrap.innerHTML = '<p class="doc-empty-hint">无声明参数，可直接发送。</p>';
            }
            params.forEach(function (p) {
                var label = document.createElement('label');
                label.className = 'd4-field';
                var span = document.createElement('span');
                span.textContent = (p.name || '') + (p.required ? ' *' : '');
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'param-input';
                input.setAttribute('data-param', p.name || '');
                input.placeholder = p.example || p.description || p.name || '';
                label.appendChild(span);
                label.appendChild(input);
                paramsWrap.appendChild(label);
            });
        }
        autofillKey();
    }

    function selectApi(id, nameHint) {
        var sid = String(id || '');
        if (!sid) return;
        var activeName = String(nameHint || '');
        page.querySelectorAll('.docs-tree__item').forEach(function (el) {
            var on = el.getAttribute('data-docs-item') === sid;
            el.classList.toggle('is-active', on);
            if (on) {
                activeName = el.getAttribute('data-docs-name') || activeName;
                var group = el.closest('[data-docs-group]');
                if (group) group.classList.add('is-open');
            }
        });
        setSelectedTitle(activeName);
        page.querySelectorAll('.doc-panel[data-docs-panel]').forEach(function (panel) {
            var show = panel.getAttribute('data-docs-panel') === sid;
            panel.hidden = !show;
            if (show) {
                panel.querySelectorAll('.doc-tabs__btn').forEach(function (btn, idx) {
                    btn.classList.toggle('is-active', idx === 0);
                });
                panel.querySelectorAll('.doc-tab-pane').forEach(function (pane, idx) {
                    var active = idx === 0;
                    pane.classList.toggle('is-active', active);
                    pane.hidden = !active;
                });
                highlightPanel(panel);
            }
        });
        if (playBox) playBox.hidden = true;
        if (window.matchMedia('(max-width: 768px)').matches) setTree(false);
    }

    function applySearch() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        var anyVisible = false;
        var firstVisibleId = '';
        var firstVisibleName = '';
        page.querySelectorAll('[data-docs-group]').forEach(function (group) {
            var groupVisible = false;
            group.querySelectorAll('.docs-tree__item').forEach(function (item) {
                var hay = (item.getAttribute('data-search') || '').toLowerCase();
                var show = !q || hay.indexOf(q) !== -1;
                item.hidden = !show;
                if (show) {
                    groupVisible = true;
                    anyVisible = true;
                    if (!firstVisibleId) {
                        firstVisibleId = item.getAttribute('data-docs-item') || '';
                        firstVisibleName = item.getAttribute('data-docs-name') || '';
                    }
                }
            });
            group.hidden = !groupVisible;
            if (groupVisible && q) group.classList.add('is-open');
        });
        if (searchEmpty) searchEmpty.hidden = anyVisible || !q;
        if (q && firstVisibleId) {
            var active = page.querySelector('.docs-tree__item.is-active:not([hidden])');
            if (!active) selectApi(firstVisibleId, firstVisibleName);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (err) { /* ignore */ }
        document.body.removeChild(ta);
    }

    page.addEventListener('click', function (e) {
        var groupBtn = e.target.closest('.docs-tree__group-btn');
        if (groupBtn) {
            var group = groupBtn.closest('[data-docs-group]');
            if (group) group.classList.toggle('is-open');
            return;
        }
        var item = e.target.closest('.docs-tree__item[data-docs-item]');
        if (item) {
            selectApi(item.getAttribute('data-docs-item'), item.getAttribute('data-docs-name'));
            return;
        }
        var qsAuth = e.target.closest('.docs-qs__auth-tab[data-qs-auth]');
        if (qsAuth) {
            var qsRoot = qsAuth.closest('[data-docs-qs]');
            if (!qsRoot) return;
            var auth = qsAuth.getAttribute('data-qs-auth');
            qsRoot.querySelectorAll('.docs-qs__auth-tab').forEach(function (btn) {
                var on = btn === qsAuth;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            qsRoot.querySelectorAll('[data-qs-auth-pane]').forEach(function (pane) {
                var show = pane.getAttribute('data-qs-auth-pane') === auth;
                pane.classList.toggle('is-active', show);
                pane.hidden = !show;
            });
            highlightPanel(qsRoot);
            return;
        }
        var tabBtn = e.target.closest('.doc-tabs__btn[data-docs-tab]');
        if (tabBtn) {
            var panel = tabBtn.closest('.doc-panel');
            if (!panel) return;
            var tab = tabBtn.getAttribute('data-docs-tab');
            panel.querySelectorAll('.doc-tabs__btn').forEach(function (btn) {
                btn.classList.toggle('is-active', btn === tabBtn);
            });
            panel.querySelectorAll('.doc-tab-pane').forEach(function (pane) {
                var show = pane.getAttribute('data-docs-pane') === tab;
                pane.classList.toggle('is-active', show);
                pane.hidden = !show;
            });
            if (tab === 'play') fillPlay(panel);
            else if (playBox) playBox.hidden = true;
            highlightPanel(panel);
            return;
        }
        var mdBtn = e.target.closest('[data-docs-copy-md]');
        if (mdBtn) {
            var mdPanel = mdBtn.closest('.doc-panel');
            var mdNode = mdPanel ? mdPanel.querySelector('.d4-md-data') : null;
            var md = '';
            if (mdNode) {
                try { md = String(JSON.parse(mdNode.textContent || '""') || ''); } catch (err) { md = ''; }
            }
            if (!md) return;
            var mdDone = function () {
                if (window.VS && typeof window.VS.showMessage === 'function') {
                    window.VS.showMessage('已复制整页 Markdown', 'success');
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(md).then(mdDone).catch(function () { fallbackCopy(md); mdDone(); });
            } else {
                fallbackCopy(md);
                mdDone();
            }
            return;
        }
        var copyBtn = e.target.closest('[data-copy-endpoint]');
        if (copyBtn) {
            var text = copyBtn.getAttribute('data-copy') || '';
            if (!text) return;
            var done = function () {
                if (window.VS && typeof window.VS.showMessage === 'function') {
                    window.VS.showMessage('已复制接口地址', 'success');
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text); done(); });
            } else {
                fallbackCopy(text);
                done();
            }
        }
    });

    if (searchInput) searchInput.addEventListener('input', applySearch);

    var firstActive = page.querySelector('.docs-tree__item.is-active');
    if (firstActive) {
        setSelectedTitle(firstActive.getAttribute('data-docs-name') || page.getAttribute('data-first-name') || '');
        var firstGroup = firstActive.closest('[data-docs-group]');
        if (firstGroup) firstGroup.classList.add('is-open');
        highlightPanel(page.querySelector('.doc-panel:not([hidden])'));
    }
    if (window.VsSyntax && typeof window.VsSyntax.highlightAll === 'function') {
        window.VsSyntax.highlightAll(page);
    }

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            var panel = page.querySelector('.doc-panel:not([hidden])');
            if (!panel) return;
            var data = activePlay || readPlay(panel);
            if (!data || !data.id) return;
            if (data.disabled) { setStatus('已禁用'); if (responseEl) responseEl.textContent = '接口已禁用，暂不可测试。'; return; }
            if (data.maintenance) { setStatus('维护中'); if (responseEl) responseEl.textContent = '维护中，暂不可测试。'; return; }
            autofillKey().then(function () {
                var methodBtn = methodBox ? methodBox.querySelector('button.is-on') : null;
                var method = methodBtn ? methodBtn.getAttribute('data-method') : 'GET';
                var params = {};
                if (paramsWrap) {
                    paramsWrap.querySelectorAll('.param-input').forEach(function (input) {
                        var n = input.getAttribute('data-param');
                        if (n && input.value) params[n] = input.value;
                    });
                }
                var need = parseInt(data.needkey, 10) || 0;
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
                var VsPR = window.VsPlaygroundResponse;
                if (!VsPR || !VsPR.playgroundRequest) {
                    if (responseEl) responseEl.textContent = '测试模块未加载，请刷新页面';
                    return;
                }
                setStatus('处理中');
                if (responseEl) responseEl.textContent = '正在发送请求...';
                VsPR.playgroundRequest({
                    apiId: data.id,
                    method: method,
                    params: params,
                    endpoint: data.endpoint,
                    keyways: data.keyways || []
                }).then(function (result) {
                    setStatus('完成');
                    if (result && result.headers && typeof result.text === 'function') {
                        return VsPR.renderFetchResponse(result, responseEl);
                    }
                    if (VsPR.renderRelayPayload) VsPR.renderRelayPayload(result, responseEl);
                }).catch(function (err) {
                    setStatus('失败');
                    if (responseEl) responseEl.textContent = err && err.message ? err.message : '请求失败';
                });
            });
        });
    }
})();
