/**
 * 文件：core/markdown/assets/js/markdown-render.js
 * 作用：浏览器端 Markdown + 短码预览（与 PHP Markdown::render 对齐）；代码块复制增强
 */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function parseAttrs(raw) {
        var attrs = {};
        String(raw || '').replace(/(\w+)=([^\s]+)/g, function (_, k, v) {
            attrs[k] = String(v).replace(/^["']|["']$/g, '');
        });
        return attrs;
    }

    function mdInline(text) {
        if (global.marked && typeof global.marked.parse === 'function') {
            var html = global.marked.parse(text, { breaks: true, gfm: true });
            if (global.DOMPurify) {
                return global.DOMPurify.sanitize(html);
            }
            return html;
        }
        return '<p>' + esc(text).replace(/\n/g, '<br>') + '</p>';
    }

    function renderBlock(type, attrs, body) {
        var color, title, url, text;
        switch (type) {
            case 'card':
                color = attrs.color || '';
                title = attrs.title || '';
                return '<div class="vs-md-card"' + (color ? ' style="border-color:' + esc(color) + ';"' : '') + '>'
                    + (title ? '<div class="vs-md-card__title"' + (color ? ' style="color:' + esc(color) + ';"' : '') + '>' + esc(title) + '</div>' : '')
                    + '<div class="vs-md-card__body">' + mdInline(body) + '</div></div>';
            case 'tip':
            case 'warning':
            case 'success':
            case 'danger':
                return '<div class="vs-md-alert vs-md-alert--' + esc(type) + '">' + mdInline(body) + '</div>';
            case 'collapse':
                title = attrs.title || '详情';
                return '<details class="vs-md-collapse"><summary>' + esc(title)
                    + '</summary><div class="vs-md-collapse__body">' + mdInline(body) + '</div></details>';
            case 'button':
                color = attrs.color || '';
                text = attrs.text || '按钮';
                url = attrs.url || attrs.text_url || '#';
                return '<p class="vs-md-btn-wrap"><a class="vs-md-btn" href="' + esc(url) + '"'
                    + (color ? ' style="background:' + esc(color) + ';"' : '')
                    + ' target="_blank" rel="noopener noreferrer">' + esc(text) + '</a></p>';
            case 'timeline':
                return '<ul class="vs-md-timeline">' + String(body).split(/\n+/).map(function (line) {
                    line = line.trim();
                    if (!line || line.charAt(0) !== '-') return '';
                    line = line.slice(1).trim();
                    var parts = line.split('|');
                    return '<li><span class="vs-md-timeline__time">' + esc((parts[0] || '').trim())
                        + '</span><span class="vs-md-timeline__desc">' + esc((parts[1] || '').trim()) + '</span></li>';
                }).join('') + '</ul>';
            case 'music':
                url = attrs.url || '';
                title = attrs.title || '音频';
                if (!url) return '';
                return '<div class="vs-md-music"><div class="vs-md-music__title">' + esc(title)
                    + '</div><audio controls preload="none" src="' + esc(url) + '"></audio></div>';
            default:
                return '';
        }
    }

    function render(src) {
        var text = String(src || '').replace(/\r\n?/g, '\n');
        var slots = [];
        text = text.replace(/^:::(card|tip|warning|success|danger|collapse|button|timeline|music)([^\n]*)\n([\s\S]*?)^:::\s*$/gm, function (_, type, attrRaw, body) {
            var key = '<!--MDSLOT' + slots.length + '-->';
            slots.push(renderBlock(type, parseAttrs(attrRaw), String(body || '').trim()));
            return '\n\n' + key + '\n\n';
        });
        text = text.replace(/@\[video\]\((https?:\/\/[^\s\)]+)\)/gi, function (_, url) {
            var key = '<!--MDSLOT' + slots.length + '-->';
            slots.push('<div class="vs-md-video"><video controls preload="metadata" src="'
                + esc(url) + '"></video></div>');
            return '\n\n' + key + '\n\n';
        });
        var html = mdInline(text);
        slots.forEach(function (frag, i) {
            html = html.split('<!--MDSLOT' + i + '-->').join(frag);
        });
        return '<div class="vs-md-body markdown-body">' + html + '</div>';
    }

    function copyPlain(text) {
        text = String(text || '');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;left:-9999px';
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

    function enhanceCodeBlocks(root) {
        root = root || document;
        var list = root.querySelectorAll ? root.querySelectorAll('.vs-md-body pre, .vs-md-preview pre') : [];
        Array.prototype.forEach.call(list, function (pre) {
            if (!pre || pre.getAttribute('data-vs-md-copy') === '1') {
                return;
            }
            pre.setAttribute('data-vs-md-copy', '1');
            pre.classList.add('vs-md-pre');
            if (window.getComputedStyle(pre).position === 'static') {
                pre.style.position = 'relative';
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-md-copy-btn';
            btn.setAttribute('aria-label', '复制代码');
            btn.textContent = '复制';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var code = pre.querySelector('code');
                var plain = code ? code.textContent : pre.textContent;
                copyPlain(plain).then(function () {
                    btn.textContent = '已复制';
                    btn.classList.add('is-copied');
                    setTimeout(function () {
                        btn.textContent = '复制';
                        btn.classList.remove('is-copied');
                    }, 1600);
                }).catch(function () {});
            });
            pre.appendChild(btn);
            if (global.VsSyntax && typeof global.VsSyntax.highlightElement === 'function') {
                var codeEl = pre.querySelector('code');
                if (codeEl) {
                    global.VsSyntax.highlightElement(codeEl);
                }
            }
        });
    }

    function bootEnhance() {
        enhanceCodeBlocks(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootEnhance);
    } else {
        bootEnhance();
    }

    global.VsMarkdown = {
        render: render,
        enhance: enhanceCodeBlocks
    };
})(window);
