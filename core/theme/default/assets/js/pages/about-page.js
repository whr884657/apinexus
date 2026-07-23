/**
 * 默认主题 · 关于页（粒子由 shell.js 统一绘制，本文件仅保留正文解析）
 */
(function () {
    'use strict';

    function decodeHtmlEntities(html) {
        if (!html) {
            return '';
        }
        var textarea = document.createElement('textarea');
        textarea.innerHTML = html;
        return textarea.value;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var contentEl = document.getElementById('page-content');
        if (!contentEl) {
            return;
        }
        var contentType = contentEl.getAttribute('data-type') || 'html';
        var content = decodeHtmlEntities(contentEl.innerHTML);

        if (contentType === 'markdown' && typeof window.feerMarkdownParse === 'function') {
            contentEl.innerHTML = window.feerMarkdownParse(content, { fromInnerHtml: false });
        } else if (contentType === 'markdown' && typeof window.marked !== 'undefined') {
            if (typeof window.feerMarkdownConfigure === 'function') {
                window.feerMarkdownConfigure();
            }
            contentEl.innerHTML = window.marked.parse(content);
        } else {
            contentEl.innerHTML = content;
        }

        if (typeof window.hljs !== 'undefined') {
            window.hljs.highlightAll();
        }
    });
})();
