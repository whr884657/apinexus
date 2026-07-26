/**
 * 默认主题 · API 详情「快速上手」：切换语言示例 + 复制（Tab 由 PHP 渲染）
 */
(function () {
    'use strict';

    var root = document.getElementById('detailQuickstart');
    var tabsEl = document.getElementById('detailQsTabs');
    var codeWrap = document.getElementById('detailQsCode');
    var copyBtn = document.getElementById('detailQsCopy');
    var samples = window.detailQsSamples;
    if (!root || !tabsEl || !codeWrap || !Array.isArray(samples) || samples.length === 0) {
        return;
    }

    var codeNode = codeWrap.querySelector('code') || codeWrap;
    var active = 0;
    var buttons = tabsEl.querySelectorAll('.detail-quickstart__tab');

    function setActive(idx) {
        if (idx < 0 || idx >= samples.length) {
            return;
        }
        active = idx;
        var item = samples[active];
        codeNode.textContent = item && item.code ? item.code : '';
        if (window.VsSyntax && typeof window.VsSyntax.highlightElement === 'function') {
            codeNode.removeAttribute('data-vs-syn-done');
            window.VsSyntax.highlightElement(codeNode);
        }
        Array.prototype.forEach.call(buttons, function (btn, i) {
            var on = i === active;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    Array.prototype.forEach.call(buttons, function (btn) {
        btn.addEventListener('click', function () {
            var idx = parseInt(btn.getAttribute('data-qs-idx'), 10);
            if (isNaN(idx)) {
                idx = 0;
            }
            setActive(idx);
        });
    });

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
