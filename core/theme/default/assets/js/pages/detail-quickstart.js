/**
 * 默认主题 · API 详情「快速上手」：切换鉴权 / 语言示例 + IDE 级高亮 + 复制
 * 示例内容来自 aidoc 的 :::qs 短码（AI / 人工），禁止写死填充
 */
(function () {
    'use strict';

    var root = document.getElementById('detailQuickstart');
    var authTabsEl = document.getElementById('detailQsAuthTabs');
    var tabsEl = document.getElementById('detailQsTabs');
    var codeWrap = document.getElementById('detailQsCode');
    var copyBtn = document.getElementById('detailQsCopy');
    var bundle = window.detailQsBundle || { auths: [], authLabels: {}, byAuth: {} };
    var samples = window.detailQsSamples;
    if (!root || !tabsEl || !codeWrap || !Array.isArray(samples) || samples.length === 0) {
        return;
    }

    var multiAuth = root.getAttribute('data-qs-multi-auth') === '1'
        && bundle.auths && bundle.auths.length > 1;
    var activeAuth = multiAuth ? String(bundle.auths[0] || 'query') : 'query';

    var codeNode = codeWrap.querySelector('code') || codeWrap;
    var active = 0;
    var langButtons = tabsEl.querySelectorAll('.detail-quickstart__tab');
    var authButtons = authTabsEl ? authTabsEl.querySelectorAll('.detail-quickstart__auth-tab') : [];

    function currentSamples() {
        if (multiAuth && bundle.byAuth && bundle.byAuth[activeAuth]) {
            return bundle.byAuth[activeAuth];
        }
        return samples;
    }

    function renderLangTabs(list) {
        if (!list || !list.length) {
            return;
        }
        var html = '';
        list.forEach(function (item, idx) {
            html += '<button type="button"'
                + ' class="detail-quickstart__tab' + (idx === 0 ? ' is-active' : '') + '"'
                + ' role="tab"'
                + ' aria-selected="' + (idx === 0 ? 'true' : 'false') + '"'
                + ' data-qs-idx="' + idx + '"'
                + ' data-qs-id="' + escapeAttr(item.id || '') + '"'
                + ' data-qs-syn="' + escapeAttr(item.syn || 'javascript') + '">';
            html += '<span class="detail-quickstart__label">' + escapeHtml(item.label || item.id || '') + '</span>';
            html += '</button>';
        });
        tabsEl.innerHTML = html;
        langButtons = tabsEl.querySelectorAll('.detail-quickstart__tab');
        bindLangTabs();
        setActive(0, list);
    }

    function escapeHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function setActive(idx, listOverride) {
        var list = listOverride || currentSamples();
        if (idx < 0 || idx >= list.length) {
            return;
        }
        active = idx;
        var item = list[active];
        var syn = (item && item.syn) ? String(item.syn) : 'javascript';
        codeNode.textContent = item && item.code ? item.code : '';
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

    function bindLangTabs() {
        Array.prototype.forEach.call(langButtons, function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-qs-idx'), 10);
                if (isNaN(idx)) {
                    idx = 0;
                }
                setActive(idx);
            });
        });
    }

    if (multiAuth && authTabsEl) {
        Array.prototype.forEach.call(authButtons, function (btn) {
            btn.addEventListener('click', function () {
                var auth = String(btn.getAttribute('data-qs-auth') || 'query');
                if (!bundle.byAuth || !bundle.byAuth[auth]) {
                    return;
                }
                activeAuth = auth;
                Array.prototype.forEach.call(authButtons, function (b) {
                    var on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                renderLangTabs(bundle.byAuth[auth]);
            });
        });
    }

    bindLangTabs();
    setActive(0);

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var text = codeNode.textContent || '';
            var done = function () {
                copyBtn.textContent = '已复制';
                copyBtn.classList.add('is-copied');
                if (window.VsToast && typeof window.VsToast.show === 'function') {
                    window.VsToast.show('已复制', 'success');
                }
                setTimeout(function () {
                    copyBtn.textContent = '复制';
                    copyBtn.classList.remove('is-copied');
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {});
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                done();
            } catch (e) { /* ignore */ }
            document.body.removeChild(ta);
        });
    }
})();
