/**
 * 主题 five · 快速上手代码示例（语言 tab / 鉴权切换 / 复制）
 */
(function () {
    'use strict';

    var root = document.getElementById('detailQuickstart');
    var authTabsEl = document.getElementById('detailQsAuthTabs');
    var tabsEl = document.getElementById('detailQsTabs');
    var codeWrap = document.getElementById('detailQsCode');
    var copyBtn = document.getElementById('detailQsCopy');

    function getBundle() {
        return window.detailQsBundle || { auths: [], authLabels: {}, byAuth: {} };
    }

    function getLangIcons() {
        return window.detailQsLangIcons || {};
    }

    var samples = window.detailQsSamples;
    if (!root || !tabsEl || !codeWrap || !Array.isArray(samples) || samples.length === 0) {
        return;
    }

    var bundle0 = getBundle();
    var multiAuth = root.getAttribute('data-qs-multi-auth') === '1'
        && bundle0.auths && bundle0.auths.length > 1;
    var activeAuth = multiAuth
        ? String(bundle0.auths[0] || 'query')
        : String((bundle0.auths && bundle0.auths[0]) || 'query');
    window.detailQsActiveAuth = activeAuth;

    var codeNode = codeWrap.querySelector('code') || codeWrap;
    var active = 0;
    var langButtons = tabsEl.querySelectorAll('[data-qs-idx]');
    var authButtons = authTabsEl ? authTabsEl.querySelectorAll('[data-qs-auth]') : [];

    function currentSamples() {
        var bundle = getBundle();
        if (multiAuth && bundle.byAuth && bundle.byAuth[activeAuth]) {
            return bundle.byAuth[activeAuth];
        }
        return samples;
    }

    function esc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function enrich(item) {
        var row = item && typeof item === 'object' ? item : {};
        var id = String(row.id || '').toLowerCase();
        var meta = getLangIcons()[id] || {};
        return {
            id: id,
            label: row.label || meta.label || id,
            code: row.code || '',
            syn: row.syn || meta.syn || 'javascript',
            icon_gray: row.icon_gray || meta.icon_gray || '',
            icon_color: row.icon_color || meta.icon_color || ''
        };
    }

    function setActive(idx, listOverride) {
        var list = listOverride || currentSamples();
        if (idx < 0 || idx >= list.length) return;
        active = idx;
        var item = enrich(list[active]);
        var syn = item.syn || 'javascript';
        var plain = item.code || '';
        if (window.VsSyntax && typeof window.VsSyntax.scrubHighlightLeak === 'function') {
            plain = window.VsSyntax.scrubHighlightLeak(plain);
        }
        codeNode.setAttribute('data-vs-plain', plain);
        codeNode.textContent = plain;
        codeNode.className = 'language-' + syn;
        codeNode.setAttribute('data-vs-syn', syn);
        codeNode.removeAttribute('data-vs-syn-done');
        if (window.VsSyntax && typeof window.VsSyntax.highlightElement === 'function') {
            window.VsSyntax.highlightElement(codeNode);
        }
        Array.prototype.forEach.call(langButtons, function (btn, i) {
            var on = i === active;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function renderLangTabs(list) {
        if (!list || !list.length) return;
        var html = '';
        list.forEach(function (raw, idx) {
            var item = enrich(raw);
            html += '<button type="button" class="th5-pill' + (idx === 0 ? ' is-active' : '') + '"'
                + ' role="tab" aria-selected="' + (idx === 0 ? 'true' : 'false') + '"'
                + ' data-qs-idx="' + idx + '"'
                + ' data-qs-id="' + esc(item.id) + '"'
                + ' data-qs-syn="' + esc(item.syn) + '">';
            if (item.icon_gray) {
                html += '<img src="' + esc(item.icon_gray) + '" alt="" width="14" height="14">';
            }
            html += '<span>' + esc(item.label) + '</span></button>';
        });
        tabsEl.innerHTML = html;
        langButtons = tabsEl.querySelectorAll('[data-qs-idx]');
        bindLangTabs();
        setActive(0, list);
    }

    function bindLangTabs() {
        Array.prototype.forEach.call(langButtons, function (btn) {
            btn.onclick = function () {
                var idx = parseInt(btn.getAttribute('data-qs-idx'), 10);
                if (isNaN(idx)) idx = 0;
                setActive(idx);
            };
        });
    }

    bindLangTabs();
    setActive(0);

    if (multiAuth && authTabsEl) {
        Array.prototype.forEach.call(authButtons, function (btn) {
            btn.addEventListener('click', function () {
                var auth = String(btn.getAttribute('data-qs-auth') || 'query');
                var bundle = getBundle();
                if (!bundle.byAuth || !bundle.byAuth[auth]) return;
                activeAuth = auth;
                window.detailQsActiveAuth = auth;
                Array.prototype.forEach.call(authButtons, function (b) {
                    var on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                renderLangTabs(bundle.byAuth[auth]);
            });
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var plain = codeNode.getAttribute('data-vs-plain') || codeNode.textContent || '';
            var done = function () {
                if (window.VS && typeof window.VS.showMessage === 'function') {
                    window.VS.showMessage('已复制', 'success');
                } else if (window.VsToast && typeof window.VsToast.show === 'function') {
                    window.VsToast.show('已复制', 'success');
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(plain).then(done).catch(function () {});
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = plain;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
            document.body.removeChild(ta);
        });
    }
})();
