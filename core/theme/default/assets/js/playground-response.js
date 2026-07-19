/**
 * 在线测试响应渲染：安全处理 JSON / 文本 / 图片 / 音视频，避免二进制塞进 DOM 导致卡死
 */
(function (global) {
    'use strict';

    var MAX_TEXT_CHARS = 200000;
    var lastBlobUrls = [];

    function revokeBlobUrls() {
        lastBlobUrls.forEach(function (u) {
            try { URL.revokeObjectURL(u); } catch (e) { /* ignore */ }
        });
        lastBlobUrls = [];
    }

    function trackBlob(url) {
        if (url) lastBlobUrls.push(url);
        return url;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syntaxHighlight(json) {
        return String(json)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                var cls = 'json-number';
                if (/^"/.test(match)) {
                    cls = /:$/.test(match) ? 'json-key' : 'json-string';
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
    }

    function isProbablyBinary(ct, sample) {
        var t = String(ct || '').toLowerCase();
        if (/^(image|audio|video)\//.test(t)) return true;
        if (/octet-stream|application\/pdf|application\/zip|application\/x-|font\//.test(t)) return true;
        if (sample && /[\x00-\x08\x0e-\x1f]/.test(sample.slice(0, 64))) return true;
        return false;
    }

    function mediaKind(ct) {
        var t = String(ct || '').toLowerCase();
        if (t.indexOf('image/') === 0) return 'image';
        if (t.indexOf('audio/') === 0) return 'audio';
        if (t.indexOf('video/') === 0) return 'video';
        return '';
    }

    function renderMediaHtml(kind, objectUrl, note) {
        var safe = escapeHtml(objectUrl);
        var tip = note ? '<div class="pg-media-tip">' + escapeHtml(note) + '</div>' : '';
        if (kind === 'video') {
            return '<div class="pg-media-wrap"><video controls preload="metadata" playsinline class="pg-media-el"><source src="' + safe + '"></video>' + tip + '</div>';
        }
        if (kind === 'audio') {
            return '<div class="pg-media-wrap"><audio controls preload="metadata" class="pg-media-el pg-media-el--audio"><source src="' + safe + '"></audio>' + tip + '</div>';
        }
        return '<div class="pg-media-wrap"><img src="' + safe + '" alt="" class="pg-media-el pg-media-el--img" loading="lazy" decoding="async">' + tip + '</div>';
    }

    function renderBinaryHint(ct, objectUrl) {
        var link = objectUrl
            ? '<a href="' + escapeHtml(objectUrl) + '" target="_blank" rel="noopener noreferrer">在新窗口打开 / 下载</a>'
            : '';
        return '<div class="pg-media-wrap pg-media-wrap--hint">'
            + '<p>响应为二进制内容（' + escapeHtml(ct || 'unknown') + '），已跳过文本渲染，避免页面卡顿。</p>'
            + (link ? '<p>' + link + '</p>' : '')
            + '</div>';
    }

    /**
     * @param {Response} response
     * @param {HTMLElement} outputEl
     * @returns {Promise<void>}
     */
    function renderFetchResponse(response, outputEl) {
        if (!outputEl) return Promise.resolve();
        revokeBlobUrls();

        var ct = (response.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
        var kind = mediaKind(ct);

        if (kind) {
            return response.blob().then(function (blob) {
                if (blob.size > 40 * 1024 * 1024) {
                    outputEl.innerHTML = '<div class="pg-media-wrap pg-media-wrap--hint"><p>媒体文件过大（&gt;40MB），请直接访问接口地址。</p></div>';
                    return;
                }
                var url = trackBlob(URL.createObjectURL(blob));
                outputEl.innerHTML = renderMediaHtml(kind, url, kind.toUpperCase() + ' · ' + Math.round(blob.size / 1024) + ' KB');
            });
        }

        if (/octet-stream|application\/pdf|application\/zip|application\/x-|font\//.test(ct)) {
            return response.blob().then(function (blob) {
                var url = trackBlob(URL.createObjectURL(blob));
                outputEl.innerHTML = renderBinaryHint(ct, url);
            });
        }

        return response.text().then(function (text) {
            if (isProbablyBinary(ct, text)) {
                outputEl.innerHTML = renderBinaryHint(ct || 'binary', '');
                return;
            }
            var display = text || '';
            var truncated = false;
            if (display.length > MAX_TEXT_CHARS) {
                display = display.slice(0, MAX_TEXT_CHARS);
                truncated = true;
            }
            try {
                var json = JSON.parse(text);
                var pretty = JSON.stringify(json, null, 2);
                if (pretty.length > MAX_TEXT_CHARS) {
                    pretty = pretty.slice(0, MAX_TEXT_CHARS);
                    truncated = true;
                }
                outputEl.innerHTML = syntaxHighlight(pretty)
                    + (truncated ? '\n<span class="json-null">// …已截断</span>' : '');
            } catch (e) {
                if (/html/.test(ct)) {
                    var safeDoc = escapeHtml(display.slice(0, 80000));
                    outputEl.innerHTML = '<div class="pg-media-tip">// HTML 响应（沙箱预览）</div>'
                        + '<iframe class="pg-html-frame" sandbox="" srcdoc="' + safeDoc.replace(/"/g, '&quot;') + '"></iframe>';
                    return;
                }
                outputEl.innerHTML = '<pre class="response-pre">' + escapeHtml(display)
                    + (truncated ? '\n// …已截断' : '') + '</pre>';
            }
        });
    }

    /**
     * 已知媒体类型时用直链流式展示（不 fetch 整包）
     */
    function renderDirectMedia(outputEl, url, kind) {
        if (!outputEl || !url) return false;
        revokeBlobUrls();
        var k = String(kind || '').toLowerCase();
        if (k !== 'image' && k !== 'audio' && k !== 'video') return false;
        outputEl.innerHTML = renderMediaHtml(k, url, '流式加载');
        return true;
    }

    global.VsPlaygroundResponse = {
        renderFetchResponse: renderFetchResponse,
        renderDirectMedia: renderDirectMedia,
        syntaxHighlight: syntaxHighlight,
        revokeBlobUrls: revokeBlobUrls,
        escapeHtml: escapeHtml
    };
})(typeof window !== 'undefined' ? window : this);
