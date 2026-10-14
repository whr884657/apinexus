/**
 * 令牌接口范围嵌套选择器（系统级唯一内核）
 * 暴露 window.VsTokenScopePicker
 *
 * 性能：分类索引一次建表；勾选/展开只补丁 DOM；搜索点按钮才过滤（不对 input 全量重绘）
 */
(function () {
    'use strict';

    var root = null;
    var listEl = null;
    var searchInput = null;
    var searchMode = null;
    var searchBtn = null;
    var titleEl = null;
    var postAction = null;
    var onConfirm = null;
    var catalog = null;
    var index = null;
    var loading = false;
    var draft = { open: true, c: {}, a: {}, staleA: {}, staleC: {} };
    var expanded = {};
    var appliedQ = '';
    var appliedMode = 'name';
    var renderScheduled = false;

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function sortIds(map) {
        return Object.keys(map).map(function (k) { return parseInt(k, 10); })
            .filter(function (n) { return n > 0; })
            .sort(function (a, b) { return a - b; });
    }

    function ensureDom() {
        root = $('userTokenScopePicker');
        if (!root) {
            return false;
        }
        listEl = root.querySelector('[data-scope-list]');
        searchInput = root.querySelector('[data-scope-search]');
        searchMode = root.querySelector('[data-scope-search-mode]');
        searchBtn = root.querySelector('[data-scope-search-btn]');
        titleEl = $('userTokenScopeTitle');
        if (root.parentNode !== document.body) {
            document.body.appendChild(root);
        }
        return true;
    }

    function isOpen() {
        return !!(root && !root.hidden && root.classList.contains('is-open'));
    }

    function setOpen(open) {
        if (!root) {
            return;
        }
        if (open) {
            if (root.parentNode !== document.body) {
                document.body.appendChild(root);
            }
            root.classList.add('vs-nested-picker--viewport');
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            root.classList.add('is-open');
            requestAnimationFrame(function () {
                if (root && !root.hidden) {
                    root.classList.add('is-visible');
                }
            });
            if (window.VSPick && typeof window.VSPick.init === 'function') {
                window.VSPick.init(root);
            }
        } else {
            root.classList.remove('is-visible');
            root.classList.remove('is-open');
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
        }
    }

    function buildIndex(raw) {
        var apiMap = {};
        var catMap = {};
        var byCat = {};
        var unc = [];
        (raw.categories || []).forEach(function (c) {
            if (!c || !c.id) {
                return;
            }
            var cid = (c.id | 0);
            catMap[cid] = c;
            byCat[cid] = [];
        });
        (raw.apis || []).forEach(function (api) {
            if (!api || !api.id) {
                return;
            }
            var aid = (api.id | 0);
            apiMap[aid] = api;
            var cid = (api.cid | 0);
            if (cid > 0 && byCat[cid]) {
                byCat[cid].push(api);
            } else if (cid > 0) {
                byCat[cid] = [api];
            } else {
                unc.push(api);
            }
        });
        return {
            categories: raw.categories || [],
            apis: raw.apis || [],
            apiMap: apiMap,
            catMap: catMap,
            byCat: byCat,
            unc: unc
        };
    }

    function loadCatalog() {
        if (catalog && index) {
            return Promise.resolve(catalog);
        }
        if (!postAction) {
            return Promise.reject(new Error('no postAction'));
        }
        if (loading) {
            return loading;
        }
        loading = postAction('scope_catalog', {}).then(function (data) {
            loading = false;
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '加载失败');
            }
            catalog = {
                categories: (data.categories || []).slice(),
                apis: (data.apis || []).slice()
            };
            index = buildIndex(catalog);
            return catalog;
        }).catch(function (err) {
            loading = false;
            throw err;
        });
        return loading;
    }

    function apisOfCat(cid) {
        if (!index) {
            return [];
        }
        return index.byCat[cid] || [];
    }

    function uncategorizedApis() {
        return index ? index.unc : [];
    }

    function categoryState(cid) {
        if (draft.c[cid]) {
            return 'full';
        }
        var apis = apisOfCat(cid);
        if (!apis.length) {
            return 'none';
        }
        var n = 0;
        var i;
        for (i = 0; i < apis.length; i++) {
            if (draft.a[apis[i].id]) {
                n++;
            }
        }
        if (n === 0) {
            return 'none';
        }
        if (n >= apis.length) {
            return 'full';
        }
        return 'partial';
    }

    function matchApi(api, q, mode) {
        if (!q) {
            return true;
        }
        if (mode === 'id') {
            if (!/^\d+$/.test(q)) {
                return false;
            }
            return String(api.id).indexOf(q) === 0;
        }
        return String(api.name || '').toLowerCase().indexOf(q) !== -1;
    }

    function isCatEnabled(cid) {
        if (!index || !index.catMap) {
            return true;
        }
        var cat = index.catMap[cid];
        if (!cat) {
            return false;
        }
        return Number(cat.status) !== 0;
    }

    function foldForSubmit() {
        var cMap = {};
        Object.keys(draft.c).forEach(function (id) {
            cMap[parseInt(id, 10)] = true;
        });
        Object.keys(draft.staleC).forEach(function (id) {
            cMap[parseInt(id, 10)] = true;
        });
        if (index) {
            index.categories.forEach(function (cat) {
                var cid = parseInt(cat.id, 10);
                if (!cid || cMap[cid]) {
                    return;
                }
                // 停用分类：勾满接口也不提升成 c（与服务端满勾 / 写侧一致）
                if (Number(cat.status) === 0) {
                    return;
                }
                var apis = apisOfCat(cid);
                if (!apis.length) {
                    return;
                }
                var all = true;
                var i;
                for (i = 0; i < apis.length; i++) {
                    if (!draft.a[apis[i].id]) {
                        all = false;
                        break;
                    }
                }
                if (all) {
                    cMap[cid] = true;
                }
            });
        }
        var aMap = {};
        Object.keys(draft.a).forEach(function (id) {
            var aid = parseInt(id, 10);
            var api = index && index.apiMap[aid];
            if (api && api.cid > 0 && cMap[api.cid]) {
                return;
            }
            aMap[aid] = true;
        });
        Object.keys(draft.staleA).forEach(function (id) {
            aMap[parseInt(id, 10)] = true;
        });
        return { c: sortIds(cMap), a: sortIds(aMap) };
    }

    function buildVisibleFolded() {
        if (!index) {
            return { c: [], a: [] };
        }
        var c = [];
        var a = [];
        // 与服务端满勾一致：启用分类进 c；停用分类下接口进 a
        index.categories.forEach(function (cat) {
            var apis = apisOfCat(cat.id);
            if (!apis.length) {
                return;
            }
            if (Number(cat.status) === 0) {
                apis.forEach(function (api) {
                    a.push(api.id);
                });
            } else {
                c.push(cat.id);
            }
        });
        uncategorizedApis().forEach(function (api) {
            a.push(api.id);
        });
        c.sort(function (x, y) { return x - y; });
        a.sort(function (x, y) { return x - y; });
        return { c: c, a: a };
    }

    function summaryText() {
        if (draft.open) {
            return '不限制（全部接口）';
        }
        var folded = foldForSubmit();
        var nc = folded.c.length;
        var na = folded.a.length;
        if (nc > 0 && na > 0) {
            return nc + ' 个分类 · ' + na + ' 个接口';
        }
        if (nc > 0) {
            return nc + ' 个分类';
        }
        if (na > 0) {
            return na + ' 个接口';
        }
        return '尚未选择';
    }

    function selectedCount() {
        return Object.keys(draft.c).length
            + Object.keys(draft.a).length
            + Object.keys(draft.staleC).length
            + Object.keys(draft.staleA).length;
    }

    function syncTitle() {
        if (titleEl) {
            titleEl.textContent = '选择接口 · 已选 ' + selectedCount();
        }
    }

    function syncSearchPlaceholder() {
        if (!searchInput || !searchMode) {
            return;
        }
        var mode = String(searchMode.value || 'name');
        searchInput.placeholder = mode === 'id' ? '输入接口 ID…' : '输入接口名…';
    }

    function chipHtml(api, cid, locked) {
        var on = !!(draft.c[cid] || draft.a[api.id]);
        var lock = !!locked || !!draft.c[cid];
        return '<label class="vs-scope-chip' + (on ? ' is-on' : '') + (lock ? ' is-locked' : '') + '">'
            + '<input type="checkbox" data-scope-api="' + api.id + '" data-cid="' + cid + '"'
            + (on ? ' checked' : '') + (lock ? ' disabled' : '') + '>'
            + '<span class="vs-scope-chip__name">' + escapeHtml(api.name || ('#' + api.id)) + '</span>'
            + '<span class="vs-scope-chip__id">#' + api.id + '</span>'
            + '</label>';
    }

    function chipsBlockHtml(apis, cid) {
        var locked = cid > 0 && !!draft.c[cid];
        var html = '<div class="vs-scope-chips" data-scope-chips="' + cid + '">';
        var i;
        for (i = 0; i < apis.length; i++) {
            html += chipHtml(apis[i], cid, locked);
        }
        html += '</div>';
        return html;
    }

    function catRowHtml(cat, apis, filtered, forceExp) {
        var cid = cat.id;
        var st = categoryState(cid);
        var isExp = forceExp || !!expanded[cid];
        var showApis = forceExp ? filtered : (isExp ? apis : null);
        var html = '<div class="vs-scope-cat' + (st === 'full' ? ' is-full' : '') + (st === 'partial' ? ' is-partial' : '')
            + '" data-cid="' + cid + '">';
        html += '<div class="vs-scope-cat__row">';
        html += '<button type="button" class="vs-scope-cat__exp' + (isExp ? ' is-open' : '')
            + '" data-scope-exp="' + cid + '" aria-label="展开">›</button>';
        html += '<label class="vs-scope-cat__check">';
        // 停用：仅已勾可选（取消勾选）；未勾过的禁止新勾整类
        var disabledNew = Number(cat.status) === 0 && st !== 'full';
        html += '<input type="checkbox" data-scope-cat="' + cid + '"'
            + (st === 'full' ? ' checked' : '')
            + (st === 'partial' ? ' data-indeterminate="1"' : '')
            + (disabledNew ? ' disabled' : '') + '>';
        html += '<span>' + escapeHtml(cat.name || ('分类#' + cid))
            + (cat.status === 0 ? ' <em class="vs-scope-muted">停用</em>' : '')
            + '</span></label>';
        html += '<span class="vs-scope-cat__n">' + apis.length + '</span>';
        html += '</div>';
        if (showApis && showApis.length) {
            html += chipsBlockHtml(showApis, cid);
        } else if (isExp && showApis && !showApis.length) {
            html += '<div class="vs-scope-chips" data-scope-chips="' + cid + '"></div>';
        }
        html += '</div>';
        return html;
    }

    function applyIndeterminate() {
        if (!listEl) {
            return;
        }
        var nodes = listEl.querySelectorAll('input[data-indeterminate="1"]');
        var i;
        for (i = 0; i < nodes.length; i++) {
            nodes[i].indeterminate = true;
        }
    }

    function render() {
        if (!listEl || !index) {
            return;
        }
        var q = appliedQ;
        var mode = appliedMode;
        var html = '';
        var shownAny = false;
        var cats = index.categories;
        var i;
        var j;

        for (i = 0; i < cats.length; i++) {
            var cat = cats[i];
            var apis = apisOfCat(cat.id);
            var filtered;
            if (!q) {
                filtered = apis;
            } else {
                filtered = [];
                for (j = 0; j < apis.length; j++) {
                    if (matchApi(apis[j], q, mode)) {
                        filtered.push(apis[j]);
                    }
                }
                if (!filtered.length) {
                    continue;
                }
            }
            shownAny = true;
            html += catRowHtml(cat, apis, filtered, !!q);
        }

        var unc = uncategorizedApis();
        var uncShow = [];
        for (i = 0; i < unc.length; i++) {
            if (matchApi(unc[i], q, mode)) {
                uncShow.push(unc[i]);
            }
        }
        if (uncShow.length || (!q && unc.length)) {
            var uncAll = q ? uncShow : unc;
            if (uncAll.length) {
                shownAny = true;
                html += '<div class="vs-scope-cat vs-scope-cat--unc" data-cid="0">';
                html += '<div class="vs-scope-cat__row"><span class="vs-scope-cat__unc-title">未分类</span>';
                html += '<span class="vs-scope-cat__n">' + unc.length + '</span></div>';
                html += chipsBlockHtml(uncAll, 0);
                html += '</div>';
            }
        }

        var staleBits = [];
        Object.keys(draft.staleC).forEach(function (id) {
            staleBits.push('<label class="vs-scope-chip is-stale is-on"><input type="checkbox" checked data-scope-stale-c="'
                + id + '"><span class="vs-scope-chip__name">失效分类 #' + id + '</span></label>');
        });
        Object.keys(draft.staleA).forEach(function (id) {
            staleBits.push('<label class="vs-scope-chip is-stale is-on"><input type="checkbox" checked data-scope-stale-a="'
                + id + '"><span class="vs-scope-chip__name">失效接口 #' + id + '</span></label>');
        });
        if (staleBits.length) {
            shownAny = true;
            html += '<div class="vs-scope-cat"><div class="vs-scope-cat__row"><span class="vs-scope-cat__unc-title">已失效（可取消）</span></div>';
            html += '<div class="vs-scope-chips">' + staleBits.join('') + '</div></div>';
        }

        if (!shownAny) {
            html = '<div class="vs-scope-empty">' + (q ? '无匹配项' : '暂无接口') + '</div>';
        }
        listEl.innerHTML = html;
        applyIndeterminate();
        syncTitle();
    }

    function scheduleRender() {
        if (renderScheduled) {
            return;
        }
        renderScheduled = true;
        requestAnimationFrame(function () {
            renderScheduled = false;
            render();
        });
    }

    function patchCatRow(cid) {
        if (!listEl || appliedQ) {
            scheduleRender();
            return;
        }
        var wrap = listEl.querySelector('.vs-scope-cat[data-cid="' + cid + '"]');
        if (!wrap) {
            scheduleRender();
            return;
        }
        var st = categoryState(cid);
        wrap.classList.toggle('is-full', st === 'full');
        wrap.classList.toggle('is-partial', st === 'partial');
        var cb = wrap.querySelector('input[data-scope-cat]');
        if (cb) {
            cb.checked = st === 'full';
            cb.indeterminate = st === 'partial';
            if (st === 'partial') {
                cb.setAttribute('data-indeterminate', '1');
            } else {
                cb.removeAttribute('data-indeterminate');
            }
        }
        var chips = wrap.querySelector('[data-scope-chips]');
        if (chips) {
            var apis = apisOfCat(cid);
            chips.outerHTML = chipsBlockHtml(apis, cid);
        }
        syncTitle();
    }

    function toggleExpand(cid) {
        expanded[cid] = !expanded[cid];
        if (appliedQ) {
            scheduleRender();
            return;
        }
        var wrap = listEl && listEl.querySelector('.vs-scope-cat[data-cid="' + cid + '"]');
        if (!wrap) {
            scheduleRender();
            return;
        }
        var expBtn = wrap.querySelector('[data-scope-exp]');
        if (expBtn) {
            expBtn.classList.toggle('is-open', !!expanded[cid]);
        }
        var chips = wrap.querySelector('[data-scope-chips]');
        if (expanded[cid]) {
            if (!chips) {
                wrap.insertAdjacentHTML('beforeend', chipsBlockHtml(apisOfCat(cid), cid));
            }
        } else if (chips) {
            chips.parentNode.removeChild(chips);
        }
    }

    function applyIncoming(state) {
        draft = { open: true, c: {}, a: {}, staleA: {}, staleC: {} };
        expanded = {};
        appliedQ = '';
        appliedMode = searchMode ? String(searchMode.value || 'name') : 'name';
        if (searchInput) {
            searchInput.value = '';
        }
        syncSearchPlaceholder();
        if (!state || state.open) {
            draft.open = true;
            return;
        }
        draft.open = false;
        var cList = state.c || [];
        var aList = state.a || [];
        var cmap = index ? index.catMap : {};
        var amap = index ? index.apiMap : {};
        cList.forEach(function (id) {
            id = parseInt(id, 10);
            if (id > 0) {
                if (cmap[id]) {
                    draft.c[id] = true;
                } else {
                    draft.staleC[id] = true;
                }
            }
        });
        aList.forEach(function (id) {
            id = parseInt(id, 10);
            if (id > 0) {
                if (amap[id]) {
                    var api = amap[id];
                    if (api.cid > 0 && draft.c[api.cid]) {
                        return;
                    }
                    draft.a[id] = true;
                } else {
                    draft.staleA[id] = true;
                }
            }
        });
        Object.keys(draft.c).forEach(function (cid) {
            apisOfCat(parseInt(cid, 10)).forEach(function (api) {
                delete draft.a[api.id];
            });
        });
    }

    function toggleCat(cid, checked) {
        draft.open = false;
        cid = parseInt(cid, 10);
        if (checked) {
            // 停用分类禁止新勾整类（本钥已有的仍可在 applyIncoming 后保持勾选）
            if (!isCatEnabled(cid) && !draft.c[cid] && !draft.staleC[cid]) {
                scheduleRender();
                return;
            }
            draft.c[cid] = true;
            apisOfCat(cid).forEach(function (api) {
                delete draft.a[api.id];
            });
        } else {
            delete draft.c[cid];
            delete draft.staleC[cid];
            apisOfCat(cid).forEach(function (api) {
                delete draft.a[api.id];
            });
        }
    }

    function toggleApi(aid, cid, checked) {
        draft.open = false;
        aid = parseInt(aid, 10);
        cid = parseInt(cid, 10) || 0;
        if (cid > 0 && draft.c[cid]) {
            return;
        }
        if (checked) {
            draft.a[aid] = true;
            if (cid > 0) {
                var apis = apisOfCat(cid);
                var all = apis.length > 0;
                var i;
                for (i = 0; i < apis.length; i++) {
                    if (!draft.a[apis[i].id]) {
                        all = false;
                        break;
                    }
                }
                // 停用分类勾满接口不提升成 c（接口仍留在 a）
                if (all && isCatEnabled(cid)) {
                    draft.c[cid] = true;
                    for (i = 0; i < apis.length; i++) {
                        delete draft.a[apis[i].id];
                    }
                }
            }
        } else {
            delete draft.a[aid];
        }
    }

    function runSearch() {
        appliedMode = searchMode ? String(searchMode.value || 'name') : 'name';
        var raw = searchInput ? String(searchInput.value || '').trim() : '';
        appliedQ = appliedMode === 'id' ? raw : raw.toLowerCase();
        // 搜索时默认展开匹配分类（由 render 的 forceExp 完成）
        scheduleRender();
    }

    function bindOnce() {
        if (!root || root.getAttribute('data-bound') === '1') {
            return;
        }
        root.setAttribute('data-bound', '1');
        root.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-scope-close') === '1') {
                e.preventDefault();
                setOpen(false);
                return;
            }
            var exp = e.target.closest('[data-scope-exp]');
            if (exp) {
                e.preventDefault();
                toggleExpand(parseInt(exp.getAttribute('data-scope-exp'), 10));
                return;
            }
            if (e.target.closest('[data-scope-search-btn]')) {
                e.preventDefault();
                runSearch();
                return;
            }
            if (e.target.closest('[data-scope-clear]')) {
                e.preventDefault();
                draft = { open: false, c: {}, a: {}, staleA: {}, staleC: {} };
                scheduleRender();
                return;
            }
            if (e.target.closest('[data-scope-ok]')) {
                e.preventDefault();
                var hadStale = Object.keys(draft.staleA).length > 0 || Object.keys(draft.staleC).length > 0;
                draft.staleA = {};
                draft.staleC = {};
                var folded = foldForSubmit();
                if (!draft.open && folded.c.length === 0 && folded.a.length === 0) {
                    if (window.VS && window.VS.showMessage) {
                        window.VS.showMessage(
                            hadStale
                                ? '失效项已移除，请重新选择接口或分类，或改选不限制'
                                : '请至少选择一个接口或分类，或改选不限制',
                            'error'
                        );
                    }
                    scheduleRender();
                    return;
                }
                if (hadStale && window.VS && window.VS.showMessage) {
                    window.VS.showMessage('已自动移除失效项', 'success');
                }
                var result = {
                    open: draft.open || (folded.c.length === 0 && folded.a.length === 0),
                    c: folded.c,
                    a: folded.a,
                    label: ''
                };
                if (result.open) {
                    result.c = [];
                    result.a = [];
                    result.label = '不限制（全部接口）';
                } else {
                    result.label = summaryText();
                }
                setOpen(false);
                if (typeof onConfirm === 'function') {
                    onConfirm(result);
                }
            }
        });
        root.addEventListener('change', function (e) {
            var t = e.target;
            if (!t) {
                return;
            }
            if (t.getAttribute('data-scope-cat')) {
                var cid = t.getAttribute('data-scope-cat');
                toggleCat(cid, t.checked);
                patchCatRow(parseInt(cid, 10));
                return;
            }
            if (t.getAttribute('data-scope-api')) {
                var aid = t.getAttribute('data-scope-api');
                var acid = t.getAttribute('data-cid');
                toggleApi(aid, acid, t.checked);
                if ((acid | 0) > 0) {
                    patchCatRow(parseInt(acid, 10));
                } else {
                    scheduleRender();
                }
                return;
            }
            if (t.getAttribute('data-scope-stale-c')) {
                if (!t.checked) {
                    delete draft.staleC[t.getAttribute('data-scope-stale-c')];
                    delete draft.c[t.getAttribute('data-scope-stale-c')];
                }
                scheduleRender();
                return;
            }
            if (t.getAttribute('data-scope-stale-a')) {
                if (!t.checked) {
                    delete draft.staleA[t.getAttribute('data-scope-stale-a')];
                }
                scheduleRender();
                return;
            }
            if (t.hasAttribute && t.hasAttribute('data-scope-search-mode')) {
                syncSearchPlaceholder();
            }
        });
        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    runSearch();
                }
            });
        }
    }

    function getState() {
        if (draft.open) {
            return { open: true, c: [], a: [], label: '不限制（全部接口）' };
        }
        var folded = foldForSubmit();
        return {
            open: false,
            c: folded.c,
            a: folded.a,
            label: summaryText()
        };
    }

    function buildPayload(mainOpen) {
        if (mainOpen) {
            return { scope_mode: 'all', scopejson: '', scope_visible: '' };
        }
        var folded = foldForSubmit();
        var visible = buildVisibleFolded();
        return {
            scope_mode: 'list',
            scopejson: JSON.stringify({ c: folded.c, a: folded.a }),
            scope_visible: JSON.stringify(visible)
        };
    }

    function open(state, confirmCb) {
        if (!ensureDom()) {
            if (window.VS && window.VS.showMessage) {
                window.VS.showMessage('范围选择器未就绪', 'error');
            }
            return;
        }
        bindOnce();
        onConfirm = confirmCb;
        setOpen(true);
        syncTitle();
        if (listEl) {
            listEl.innerHTML = '<div class="vs-scope-empty">加载中…</div>';
        }
        loadCatalog().then(function () {
            applyIncoming(state || { open: true });
            render();
            if (searchInput) {
                searchInput.focus();
            }
        }).catch(function (err) {
            if (listEl) {
                listEl.innerHTML = '<div class="vs-scope-empty">' + escapeHtml(err.message || '加载失败') + '</div>';
            }
            if (window.VS && window.VS.showMessage) {
                window.VS.showMessage(err.message || '加载失败', 'error');
            }
        });
    }

    function close() {
        setOpen(false);
    }

    function init(opts) {
        opts = opts || {};
        postAction = opts.postAction || null;
        ensureDom();
        bindOnce();
    }

    window.VsTokenScopePicker = {
        init: init,
        open: open,
        close: close,
        isOpen: isOpen,
        getState: getState,
        buildPayload: buildPayload,
        summaryText: function (state) {
            if (!state || state.open) {
                return '不限制（全部接口）';
            }
            var nc = (state.c || []).length;
            var na = (state.a || []).length;
            if (nc > 0 && na > 0) {
                return nc + ' 个分类 · ' + na + ' 个接口';
            }
            if (nc > 0) {
                return nc + ' 个分类';
            }
            if (na > 0) {
                return na + ' 个接口';
            }
            return '尚未选择';
        }
    };
})();
