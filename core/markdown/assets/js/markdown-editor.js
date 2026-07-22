/**
 * 文件：core/markdown/assets/js/markdown-editor.js
 * 作用：可复用 Markdown 编辑器（工具栏插入短码 + 实时预览）
 *
 * 用法：VsMarkdownEditor.mount(textareaEl, { height: 320 })
 */
(function (global) {
    'use strict';

    var TOOLS = [
        { label: 'H1', tip: '一级标题', snip: '# 标题\n' },
        { label: 'H2', tip: '二级标题', snip: '## 标题\n' },
        { label: 'H3', tip: '三级标题', snip: '### 标题\n' },
        { sep: true },
        { label: '粗体', snip: '**粗体**' },
        { label: '斜体', snip: '*斜体*' },
        { label: '引用', snip: '> 引用内容\n' },
        { label: '代码', snip: '```\n代码\n```\n' },
        { sep: true },
        { label: '无序', snip: '- 项目\n' },
        { label: '有序', snip: '1. 项目\n' },
        { label: '表格', snip: '| 列1 | 列2 |\n| --- | --- |\n| A | B |\n' },
        { label: '分割线', snip: '\n---\n' },
        { sep: true },
        { label: '链接', snip: '[链接文字](https://)\n' },
        { label: '图片', snip: '![说明](https://example.com/a.jpg)\n' },
        { label: '视频', snip: '@[video](https://example.com/a.mp4)\n' },
        { label: '音乐', snip: ':::music url=https://example.com/a.mp3 title=歌名\n:::\n' },
        { sep: true },
        { label: '卡片', snip: ':::card color=#059669 title=卡片标题\n卡片内容\n:::\n' },
        { label: '提示', snip: ':::tip\n提示内容\n:::\n' },
        { label: '警告', snip: ':::warning\n警告内容\n:::\n' },
        { label: '折叠', snip: ':::collapse title=点击展开\n隐藏内容\n:::\n' },
        { label: '按钮', snip: ':::button color=#059669 text=立即前往 url=https://\n:::\n' },
        { label: '时间轴', snip: ':::timeline\n- 2024.01 | 节点说明\n- 2024.06 | 节点说明\n:::\n' },
        { label: '缩进', snip: ':::indent\n首行缩进段落\n:::\n' },
        { label: '居中', snip: '<p style="text-align:center">居中文字</p>\n' }
    ];

    function insertAtCursor(ta, text) {
        var start = ta.selectionStart || 0;
        var end = ta.selectionEnd || 0;
        var val = ta.value || '';
        ta.value = val.slice(0, start) + text + val.slice(end);
        var pos = start + text.length;
        ta.selectionStart = ta.selectionEnd = pos;
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function mount(textarea, opts) {
        opts = opts || {};
        if (!textarea || textarea.getAttribute('data-vs-md-ready') === '1') {
            return null;
        }
        textarea.setAttribute('data-vs-md-ready', '1');
        textarea.classList.add('vs-md-input');
        if (opts.height) {
            textarea.style.minHeight = String(opts.height) + 'px';
        }

        var wrap = document.createElement('div');
        wrap.className = 'vs-md-editor';
        textarea.parentNode.insertBefore(wrap, textarea);

        var bar = document.createElement('div');
        bar.className = 'vs-md-toolbar';
        TOOLS.forEach(function (t) {
            if (t.sep) {
                var sep = document.createElement('span');
                sep.className = 'vs-md-toolbar__sep';
                bar.appendChild(sep);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = t.label;
            btn.title = t.tip || t.label;
            btn.addEventListener('click', function () {
                insertAtCursor(textarea, t.snip);
            });
            bar.appendChild(btn);
        });
        wrap.appendChild(bar);

        var panes = document.createElement('div');
        panes.className = 'vs-md-panes';
        panes.appendChild(textarea);

        var preview = document.createElement('div');
        preview.className = 'vs-md-preview';
        panes.appendChild(preview);
        wrap.appendChild(panes);

        function refresh() {
            if (global.VsMarkdown && typeof global.VsMarkdown.render === 'function') {
                preview.innerHTML = global.VsMarkdown.render(textarea.value || '');
            } else {
                preview.textContent = textarea.value || '';
            }
        }
        textarea.addEventListener('input', refresh);
        refresh();

        return { wrap: wrap, preview: preview, refresh: refresh };
    }

    function mountAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll('textarea[data-vs-md]');
        for (var i = 0; i < nodes.length; i++) {
            mount(nodes[i]);
        }
    }

    global.VsMarkdownEditor = { mount: mount, mountAll: mountAll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mountAll(document); });
    } else {
        mountAll(document);
    }
})(window);
