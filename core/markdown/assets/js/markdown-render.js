/**
 * 文件：core/markdown/assets/js/markdown-render.js
 * 作用：浏览器端 Markdown + 短码预览（与 PHP Markdown::render 对齐）
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
            case 'indent':
                return '<p class="vs-md-indent">' + esc(body).replace(/\n/g, '<br>') + '</p>';
            default:
                return '';
        }
    }

    function render(src) {
        var text = String(src || '').replace(/\r\n?/g, '\n');
        var slots = [];
        text = text.replace(/^:::(card|tip|warning|success|danger|collapse|button|timeline|music|indent)([^\n]*)\n([\s\S]*?)^:::\s*$/gm, function (_, type, attrRaw, body) {
            var key = '<!--MDSLOT' + slots.length + '-->';
            slots.push(renderBlock(type, parseAttrs(attrRaw), String(body || '').trim()));
            return '\n\n' + key + '\n\n';
        });
        text = text.replace(/@\[video\]\((https?:\/\/[^\s)]+)\)/gi, function (_, url) {
            return '\n\n<div class="vs-md-video"><video controls preload="metadata" src="'
                + esc(url) + '"></video></div>\n\n';
        });
        var html = mdInline(text);
        slots.forEach(function (frag, i) {
            html = html.split('<!--MDSLOT' + i + '-->').join(frag);
        });
        return '<div class="vs-md-body markdown-body">' + html + '</div>';
    }

    global.VsMarkdown = { render: render };
})(window);
