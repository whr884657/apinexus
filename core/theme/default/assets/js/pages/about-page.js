/**
 * 默认主题 · 关于页（粒子由 shell.js 统一绘制）
 * 正文已由服务端 Markdown SafeMode 渲染，禁止二次 decode+innerHTML（防破转义 XSS）
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var contentEl = document.getElementById('page-content');
        if (!contentEl) {
            return;
        }
        // data-type=html：服务端已输出安全 HTML，保持原样
        // 若将来需要客户端 markdown，应读 data 属性中的原始文本并用 DOMPurify，而非 decode 现有 innerHTML
        if (typeof window.hljs !== 'undefined') {
            window.hljs.highlightAll();
        }
    });
})();
