/**
 * 文件：assets/js/vs-syntax.js
 * 作用：本地轻量语法高亮（JSON / JS / bash·cURL），禁止 CDN
 */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function span(cls, text) {
        return '<span class="vs-syn vs-syn--' + cls + '">' + text + '</span>';
    }

    function highlightJson(src) {
        return esc(src).replace(
            /("(?:\\.|[^"\\])*"(?:\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+\-]?\d+)?)/g,
            function (match) {
                var cls = 'number';
                if (match.charAt(0) === '"') {
                    cls = /:$/.test(match) ? 'key' : 'string';
                } else if (/true|false|null/.test(match)) {
                    cls = 'literal';
                }
                return span(cls, match);
            }
        );
    }

    function highlightJs(src) {
        var out = '';
        var i = 0;
        var s = String(src);
        var kw = /^(?:const|let|var|function|return|if|else|for|while|new|typeof|await|async|true|false|null|undefined)\b/;
        while (i < s.length) {
            if (s[i] === '/' && s[i + 1] === '/') {
                var cEnd = s.indexOf('\n', i);
                if (cEnd < 0) {
                    cEnd = s.length;
                }
                out += span('comment', esc(s.slice(i, cEnd)));
                i = cEnd;
                continue;
            }
            if (s[i] === "'" || s[i] === '"' || s[i] === '`') {
                var q = s[i];
                var j = i + 1;
                while (j < s.length) {
                    if (s[j] === '\\') {
                        j += 2;
                        continue;
                    }
                    if (s[j] === q) {
                        j += 1;
                        break;
                    }
                    j += 1;
                }
                out += span('string', esc(s.slice(i, j)));
                i = j;
                continue;
            }
            if (/\d/.test(s[i]) && (i === 0 || /[^\w$]/.test(s[i - 1]))) {
                var n = i;
                while (n < s.length && /[\d.]/.test(s[n])) {
                    n += 1;
                }
                out += span('number', esc(s.slice(i, n)));
                i = n;
                continue;
            }
            if (/[A-Za-z_$]/.test(s[i])) {
                var w = i;
                while (w < s.length && /[\w$]/.test(s[w])) {
                    w += 1;
                }
                var word = s.slice(i, w);
                if (kw.test(word)) {
                    out += span('keyword', esc(word));
                } else if (s[w] === '(') {
                    out += span('func', esc(word));
                } else {
                    out += esc(word);
                }
                i = w;
                continue;
            }
            out += esc(s[i]);
            i += 1;
        }
        return out;
    }

    /**
     * Bash / cURL：必须在「纯文本」上分词高亮，禁止对已插入的 HTML 再做 replace。
     * 旧实现在 span class="vs-syn--keyword" 上二次匹配 --keyword，DOM 破碎后
     * textContent 变成 `-syn vs-syn--keyword">curl`（E177）。
     */
    function highlightBashLine(line) {
        var out = '';
        var i = 0;
        var s = String(line);
        while (i < s.length) {
            if (s[i] === "'" || s[i] === '"') {
                var q = s[i];
                var j = i + 1;
                while (j < s.length) {
                    if (s[j] === '\\') {
                        j += 2;
                        continue;
                    }
                    if (s[j] === q) {
                        j += 1;
                        break;
                    }
                    j += 1;
                }
                out += span('string', esc(s.slice(i, j)));
                i = j;
                continue;
            }
            // 仅空白/= 后的 -s / --header，绝不匹配 class 里的 --keyword
            if (s[i] === '-' && (i === 0 || /[\s=$(]/.test(s[i - 1]))) {
                var a = i + 1;
                if (a < s.length && s[a] === '-') {
                    a += 1;
                }
                if (a < s.length && /[A-Za-z0-9]/.test(s[a])) {
                    while (a < s.length && /[\w-]/.test(s[a])) {
                        a += 1;
                    }
                    out += span('attr', esc(s.slice(i, a)));
                    i = a;
                    continue;
                }
            }
            if (/[A-Za-z_]/.test(s[i])) {
                var w = i;
                while (w < s.length && /[\w]/.test(s[w])) {
                    w += 1;
                }
                var word = s.slice(i, w);
                // 勿高亮 http/https：会破坏 URL 字符串观感，且旧版在 HTML 二次 replace 时易炸
                if (/^(curl|wget)$/i.test(word)) {
                    out += span('keyword', esc(word));
                } else {
                    out += esc(word);
                }
                i = w;
                continue;
            }
            out += esc(s[i]);
            i += 1;
        }
        return out;
    }

    function highlightBash(src) {
        var lines = String(src).split('\n');
        return lines.map(function (line) {
            var m = line.match(/^(\s*)(#.*)$/);
            if (m) {
                return esc(m[1]) + span('comment', esc(m[2]));
            }
            return highlightBashLine(line);
        }).join('\n');
    }

    /** 剥离高亮泄漏碎片（可与 PHP ApiQuickstart::scrubHighlightLeak 对齐） */
    function scrubHighlightLeak(text) {
        text = String(text == null ? '' : text);
        var i;
        for (i = 0; i < 6; i += 1) {
            var next = text.replace(/<span[^>]*class\s*=\s*["'][^"']*vs-syn[^"']*["'][^>]*>([\s\S]*?)<\/span>/gi, '$1');
            if (next === text) {
                break;
            }
            text = next;
        }
        text = text.replace(/<\/?[a-zA-Z][^>]*>/g, '');
        text = text.replace(/-?syn\s+vs-syn--[\w-]*"\s*>?/gi, '');
        text = text.replace(/vs-syn--[\w-]*"\s*>?/gi, '');
        text = text.replace(/\bvs-syn--[\w-]+/gi, '');
        text = text.replace(/\sclass\s*=\s*["'][^"']*["']/gi, '');
        text = text.replace(/\sdata-vs-syn(?:-done)?\s*=\s*["'][^"']*["']/gi, '');
        return text;
    }

    function detectLang(el) {
        var explicit = (el.getAttribute('data-vs-syn') || '').toLowerCase();
        if (explicit) {
            return explicit;
        }
        var cls = el.className || '';
        var m = String(cls).match(/language-([a-z0-9_+-]+)/i);
        if (m) {
            return m[1].toLowerCase();
        }
        var head = el.closest('.code-block');
        if (head) {
            var langEl = head.querySelector('.code-block__lang');
            if (langEl) {
                return String(langEl.textContent || '').trim().toLowerCase();
            }
        }
        return '';
    }

    function highlightGeneric(src, keywords) {
        var out = '';
        var i = 0;
        var s = String(src);
        var kwRe = new RegExp('^(?:' + keywords.join('|') + ')\\b');
        while (i < s.length) {
            if (s[i] === '/' && s[i + 1] === '/') {
                var cEnd = s.indexOf('\n', i);
                if (cEnd < 0) cEnd = s.length;
                out += span('comment', esc(s.slice(i, cEnd)));
                i = cEnd;
                continue;
            }
            if (s[i] === '#' && (i === 0 || s[i - 1] === '\n')) {
                var hEnd = s.indexOf('\n', i);
                if (hEnd < 0) hEnd = s.length;
                out += span('comment', esc(s.slice(i, hEnd)));
                i = hEnd;
                continue;
            }
            if (s[i] === "'" || s[i] === '"' || s[i] === '`') {
                var q = s[i];
                var j = i + 1;
                while (j < s.length) {
                    if (s[j] === '\\') { j += 2; continue; }
                    if (s[j] === q) { j += 1; break; }
                    j += 1;
                }
                out += span('string', esc(s.slice(i, j)));
                i = j;
                continue;
            }
            if (/\d/.test(s[i]) && (i === 0 || /[^\w$]/.test(s[i - 1]))) {
                var n = i;
                while (n < s.length && /[\d.]/.test(s[n])) n += 1;
                out += span('number', esc(s.slice(i, n)));
                i = n;
                continue;
            }
            if (/[A-Za-z_$]/.test(s[i])) {
                var w = i;
                while (w < s.length && /[\w$]/.test(s[w])) w += 1;
                var word = s.slice(i, w);
                if (kwRe.test(word)) {
                    out += span('keyword', esc(word));
                } else if (w < s.length && s[w] === '(') {
                    out += span('func', esc(word));
                } else {
                    out += esc(word);
                }
                i = w;
                continue;
            }
            out += esc(s[i]);
            i += 1;
        }
        return out;
    }

    function normalizeLang(lang) {
        lang = String(lang || '').toLowerCase();
        if (lang === 'js' || lang === 'javascript' || lang === 'browser') {
            return 'javascript';
        }
        if (lang === 'ts' || lang === 'typescript') {
            return 'typescript';
        }
        if (lang === 'json') {
            return 'json';
        }
        if (lang === 'bash' || lang === 'shell' || lang === 'sh' || lang === 'curl' || lang === 'zsh') {
            return 'bash';
        }
        if (lang === 'py' || lang === 'python') {
            return 'python';
        }
        if (lang === 'golang') {
            return 'go';
        }
        if (lang === 'c++' || lang === 'cplusplus') {
            return 'cpp';
        }
        if (lang === 'rs') {
            return 'rust';
        }
        if (lang === 'html' || lang === 'xml' || lang === 'css') {
            return 'javascript';
        }
        return lang;
    }

    function highlight(src, lang) {
        lang = normalizeLang(lang);
        if (lang === 'json') {
            return highlightJson(src);
        }
        if (lang === 'javascript' || lang === 'typescript') {
            return highlightJs(src);
        }
        if (lang === 'bash') {
            return highlightBash(src);
        }
        if (lang === 'python') {
            return highlightGeneric(src, ['def','class','return','if','elif','else','for','while','import','from','as','with','try','except','True','False','None','async','await','print','in','not','and','or']);
        }
        if (lang === 'go') {
            return highlightGeneric(src, ['package','import','func','return','if','else','for','range','var','const','type','struct','interface','map','chan','go','defer','true','false','nil']);
        }
        if (lang === 'java') {
            return highlightGeneric(src, ['public','private','protected','class','interface','return','if','else','for','while','new','static','void','int','String','boolean','true','false','null','import','package']);
        }
        if (lang === 'php') {
            return highlightGeneric(src, ['function','return','if','else','elseif','foreach','for','while','echo','print','true','false','null','array','class','public','private','protected','new','use']);
        }
        if (lang === 'cpp' || lang === 'c') {
            return highlightGeneric(src, ['int','void','return','if','else','for','while','include','using','namespace','class','struct','true','false','nullptr','const','auto']);
        }
        if (lang === 'rust') {
            return highlightGeneric(src, ['fn','let','mut','return','if','else','for','while','match','use','pub','struct','enum','impl','true','false','Self','self','mod','crate']);
        }
        try {
            JSON.parse(String(src));
            return highlightJson(src);
        } catch (e) {
            return esc(src);
        }
    }

    function highlightElement(el) {
        if (!el || el.getAttribute('data-vs-syn-done') === '1') {
            return;
        }
        var lang = detectLang(el);
        // 优先 data-vs-plain（展示前写入的纯源码）；禁止用已高亮 DOM 的 textContent 当源
        var raw = el.getAttribute('data-vs-plain');
        if (raw == null || raw === '') {
            raw = el.textContent || '';
        }
        raw = scrubHighlightLeak(raw);
        el.setAttribute('data-vs-plain', raw);
        el.innerHTML = highlight(raw, lang);
        el.setAttribute('data-vs-syn-done', '1');
        el.classList.add('vs-syn-ready');
    }

    /** 复制/导出必须用纯文本，禁止读高亮后 textContent */
    function plainText(el) {
        if (!el) {
            return '';
        }
        var plain = el.getAttribute('data-vs-plain');
        if (plain != null && plain !== '') {
            return scrubHighlightLeak(plain);
        }
        return scrubHighlightLeak(el.textContent || '');
    }

    function highlightAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('pre code, .code-block__pre code').forEach(highlightElement);
    }

    global.VsSyntax = {
        highlight: highlight,
        highlightElement: highlightElement,
        highlightAll: highlightAll,
        scrubHighlightLeak: scrubHighlightLeak,
        plainText: plainText
    };
})(typeof window !== 'undefined' ? window : this);
