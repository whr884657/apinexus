/**
 * 默认主题 · API 详情「快速上手」：切换鉴权 / 语言示例 + IDE 级高亮 + 复制
 * 示例内容来自 aidoc 的 :::qs 短码（AI / 人工），禁止写死填充
 *
 * 图标：优先样本字段 icon_gray/icon_color；缺失时用 window.detailQsLangIcons 兜底
 * （避免切换鉴权重绘 Tab 后图标丢失）
 */
(function () {
    'use strict';

    var root = document.getElementById('detailQuickstart');
    var authTabsEl = document.getElementById('detailQsAuthTabs');
    var tabsEl = document.getElementById('detailQsTabs');
    var codeWrap = document.getElementById('detailQsCode');
    var copyBtn = document.getElementById('detailQsCopy');
    // 每次读取 window，避免闭包拿到空对象
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
    var activeAuth = multiAuth ? String(bundle0.auths[0] || 'query') : String(
        (bundle0.auths && bundle0.auths[0]) || 'query'
    );
    window.detailQsActiveAuth = activeAuth;

    var codeNode = codeWrap.querySelector('code') || codeWrap;
    var active = 0;
    var langButtons = tabsEl.querySelectorAll('.detail-quickstart__tab');
    var authButtons = authTabsEl ? authTabsEl.querySelectorAll('.detail-quickstart__auth-tab') : [];

    function currentSamples() {
        var bundle = getBundle();
        if (multiAuth && bundle.byAuth && bundle.byAuth[activeAuth]) {
            return bundle.byAuth[activeAuth];
        }
        return samples;
    }

    function escapeHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function enrichItem(item) {
        var row = item && typeof item === 'object' ? item : {};
        var id = String(row.id || '').toLowerCase();
        var meta = getLangIcons()[id] || {};
        return {
            id: id,
            label: row.label || meta.label || id,
            code: row.code || '',
            syn: row.syn || meta.syn || 'javascript',
            icon_gray: row.icon_gray || meta.icon_gray || '',
            icon_color: row.icon_color || meta.icon_color || '',
            single_icon: (row.single_icon === 1 || row.single_icon === true || id === 'curl'
                || meta.single_icon === 1) ? 1 : 0
        };
    }

    function iconHtml(item) {
        var row = enrichItem(item);
        var gray = row.icon_gray;
        var color = row.icon_color;
        var single = !!row.single_icon;
        if (!gray && !color) {
            return '';
        }
        if (!gray) {
            gray = color;
        }
        var html = '<span class="detail-quickstart__icon' + (single ? ' is-single' : '') + '" aria-hidden="true">';
        html += '<img class="detail-quickstart__icon-img is-gray" src="' + escapeAttr(gray)
            + '" alt="" width="16" height="16" decoding="async">';
        if (!single && color) {
            html += '<img class="detail-quickstart__icon-img is-color" src="' + escapeAttr(color)
                + '" alt="" width="16" height="16" decoding="async">';
        }
        html += '</span>';
        return html;
    }

    function renderLangTabs(list) {
        if (!list || !list.length) {
            return;
        }
        var html = '';
        list.forEach(function (raw, idx) {
            var item = enrichItem(raw);
            html += '<button type="button"'
                + ' class="detail-quickstart__tab' + (idx === 0 ? ' is-active' : '') + '"'
                + ' role="tab"'
                + ' aria-selected="' + (idx === 0 ? 'true' : 'false') + '"'
                + ' data-qs-idx="' + idx + '"'
                + ' data-qs-id="' + escapeAttr(item.id || '') + '"'
                + ' data-qs-syn="' + escapeAttr(item.syn || 'javascript') + '">';
            html += iconHtml(item);
            html += '<span class="detail-quickstart__label">' + escapeHtml(item.label || item.id || '') + '</span>';
            html += '</button>';
        });
        tabsEl.innerHTML = html;
        langButtons = tabsEl.querySelectorAll('.detail-quickstart__tab');
        bindLangTabs();
        setActive(0, list);
    }

    function setActive(idx, listOverride) {
        var list = listOverride || currentSamples();
        if (idx < 0 || idx >= list.length) {
            return;
        }
        active = idx;
        var item = enrichItem(list[active]);
        var syn = item.syn || 'javascript';
        codeNode.textContent = item.code || '';
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
            btn.onclick = function () {
                var idx = parseInt(btn.getAttribute('data-qs-idx'), 10);
                if (isNaN(idx)) {
                    idx = 0;
                }
                setActive(idx);
            };
        });
    }

    if (multiAuth && authTabsEl) {
        Array.prototype.forEach.call(authButtons, function (btn) {
            btn.addEventListener('click', function () {
                var auth = String(btn.getAttribute('data-qs-auth') || 'query');
                var bundle = getBundle();
                if (!bundle.byAuth || !bundle.byAuth[auth]) {
                    return;
                }
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

    // 首屏：若已有 PHP 渲染的图标则只绑定事件；缺图标时用数据重绘
    var hasPhpIcons = !!tabsEl.querySelector('.detail-quickstart__icon-img');
    if (hasPhpIcons) {
        bindLangTabs();
        setActive(0);
    } else {
        renderLangTabs(currentSamples());
    }

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
