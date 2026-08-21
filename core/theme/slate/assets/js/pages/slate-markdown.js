/**
 * 主题二客户端 Markdown 增强：代码块复制后补 st-md 交互；专属短码预览对齐说明见 SlateMarkdown.php
 */
(function (global) {
    function enhance(root) {
        if (!root) {
            return;
        }
        if (global.VsMarkdown && typeof global.VsMarkdown.enhance === 'function') {
            global.VsMarkdown.enhance(root);
        }
        var marks = root.querySelectorAll('.st-md-mark');
        marks.forEach(function (el) {
            el.setAttribute('title', '主题二高亮');
        });
    }

    global.SlateMarkdown = {
        enhance: enhance
    };
})(window);
