/**
 * 接口详情页交互：复制 / 简易 Markdown 渲染
 */
(function () {
    'use strict';

    var page = document.getElementById('apiDetailPage');
    if (!page) {
        return;
    }

    var toast = document.getElementById('detailCopyToast');
    var toastTimer = null;

    function showToast(msg) {
        if (!toast) {
            return;
        }
        toast.textContent = msg || '已复制';
        toast.hidden = false;
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = setTimeout(function () {
            toast.hidden = true;
        }, 1400);
    }

    function copyText(text) {
        text = String(text || '');
        if (!text) {
            return Promise.reject();
        }
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
        if (!btn) {
            return;
        }
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
            if (title) {
                parts.push(title.textContent.trim());
            }
            if (desc) {
                parts.push(desc.textContent.trim());
            }
            if (endpoint) {
                parts.push(endpoint);
            }
            copyText(parts.join('\n')).then(function () {
                showToast('已复制全部');
            }).catch(function () {});
        });
    }

    function looksLikeMarkdown(text) {
        return /(^|\n)\s{0,3}#{1,6}\s|(^|\n)\s*[-*+]\s|```|\*\*[^*]+\*\*/.test(text);
    }

    function decodeEntities(html) {
        var ta = document.createElement('textarea');
        ta.innerHTML = html;
        return ta.value;
    }

    document.querySelectorAll('[data-detail-md]').forEach(function (el) {
        var raw = decodeEntities(el.innerHTML || '');
        if (!raw.trim()) {
            return;
        }
        if (typeof marked !== 'undefined' && looksLikeMarkdown(raw)) {
            try {
                el.innerHTML = marked.parse(raw);
                el.classList.add('is-parsed');
            } catch (e) {
                el.textContent = raw;
            }
        } else {
            el.textContent = raw;
        }
    });
})();
