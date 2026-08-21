/**
 * 文件：assets/js/console-brand.js
 * 作用：全站浏览器控制台品牌信息（系统级，不属于任何主题）
 *
 * 铁律：
 * - 固定文案不可由后台/主题改写；仅版本取 window.VS_VERSION。
 * - 不展示技术栈、开源协议、「仓库」标题；不打印裸 URL。
 * - 由 vs_console_brand_script() 以外链 script 引入（非 PHP 内嵌）。
 */
(function () {
    'use strict';
    if (window.__VS_CONSOLE_BRAND__) {
        return;
    }
    window.__VS_CONSOLE_BRAND__ = 1;

    var version = '';
    try {
        version = String(window.VS_VERSION || '').trim();
    } catch (e) {
        version = '';
    }

    var d = {
        project: 'ApiNexus',
        tagline: '可自部署的开放 API 接口平台',
        developer: '尋鯨錄',
        version: version,
        repos: [
            { name: 'Gitee', bg: '#e5484d', fg: '#ffffff' },
            { name: 'GitCode', bg: '#f0762b', fg: '#ffffff' },
            { name: 'GitHub', bg: '#3f3f46', fg: '#fafafa' }
        ],
        tease: '别看了，这里什么都没有。',
        epilogue: '—— 世间总有些遗憾，是后来的自以为是，弄丢了当初的人。若你认识一位姓秦的姑娘，愿她一切安好。'
    };

    try {
        console.log(
            '%c ' + d.project + ' ',
            'background:#1e293b;color:#f8fafc;font-weight:800;font-size:15px;padding:10px 18px;border-radius:10px;letter-spacing:0.04em'
        );
        console.log(
            '%c ' + d.tagline + ' ',
            'background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;padding:8px 14px;border-radius:8px;border:1px solid #e2e8f0'
        );
        console.log(
            '%c开发者%c ' + d.developer + ' %c版本%c v' + d.version + ' ',
            'background:#334155;color:#f1f5f9;font-size:11px;font-weight:600;padding:6px 10px;border-radius:8px 0 0 8px',
            'background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;padding:6px 12px;margin-right:8px;border-radius:0 8px 8px 0;border:1px solid #e2e8f0',
            'background:#334155;color:#f1f5f9;font-size:11px;font-weight:600;padding:6px 10px;border-radius:8px 0 0 8px',
            'background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;padding:6px 12px;border-radius:0 8px 8px 0;border:1px solid #e2e8f0'
        );
        if (d.repos && d.repos.length) {
            var fmt = '';
            var css = [];
            var i;
            var r;
            for (i = 0; i < d.repos.length; i++) {
                r = d.repos[i];
                fmt += '%c ' + r.name + ' ';
                css.push(
                    'background:' + (r.bg || '#3f3f46') +
                    ';color:' + (r.fg || '#fff') +
                    ';font-weight:700;font-size:12px;padding:7px 14px;margin:0 5px 0 0;border-radius:8px'
                );
            }
            css.unshift(fmt);
            console.log.apply(console, css);
        }
        console.log(
            '%c ' + d.tease + ' ',
            'background:#fafafa;color:#737373;font-size:12px;font-weight:500;padding:8px 12px;border-radius:8px;border:1px solid #e5e5e5'
        );
        console.log(
            '%c ' + d.epilogue + ' ',
            'background:#fffbeb;color:#92400e;font-size:11px;padding:10px 14px;border-radius:8px;border:1px solid #fcd34d;line-height:1.6'
        );
    } catch (err) {
        /* ignore */
    }
})();
