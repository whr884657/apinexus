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

    function highlightBash(src) {
        var lines = String(src).split('\n');
        return lines.map(function (line) {
            var m = line.match(/^(\s*)(#.*)$/);
            if (m) {
                return esc(m[1]) + span('comment', esc(m[2]));
            }
            return esc(line)
                .replace(/\b(curl|wget|http|https)\b/g, function (t) {
                    return span('keyword', t);
                })
                .replace(/(-{1,2}[A-Za-z0-9][\w-]*)/g, function (t) {
                    return span('attr', t);
                })
                .replace(/(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?')/g, function (t) {
                    return span('string', t);
                });
        }).join('\n');
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

    function normalizeLang(lang) {
        lang = String(lang || '').toLowerCase();
        if (lang === 'js' || lang === 'javascript' || lang === 'ts' || lang === 'typescript') {
            return 'javascript';
        }
        if (lang === 'json') {
            return 'json';
        }
        if (lang === 'bash' || lang === 'shell' || lang === 'sh' || lang === 'curl' || lang === 'zsh') {
            return 'bash';
        }
        if (lang === 'html' || lang === 'xml' || lang === 'php' || lang === 'css') {
            return 'javascript';
        }
        return lang;
    }

    function highlight(src, lang) {
        lang = normalizeLang(lang);
        if (lang === 'json') {
            return highlightJson(src);
        }
        if (lang === 'javascript') {
            return highlightJs(src);
        }
        if (lang === 'bash') {
            return highlightBash(src);
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
        var raw = el.textContent || '';
        el.innerHTML = highlight(raw, lang);
        el.setAttribute('data-vs-syn-done', '1');
        el.classList.add('vs-syn-ready');
    }

    function highlightAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('pre code, .code-block__pre code').forEach(highlightElement);
    }

    global.VsSyntax = {
        highlight: highlight,
        highlightElement: highlightElement,
        highlightAll: highlightAll
    };
})(typeof window !== 'undefined' ? window : this);
