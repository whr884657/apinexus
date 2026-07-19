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

    /**
     * 同源中继结果渲染
     * @param {{http:number,contentType:string,body:string,encoding:string,msg?:string,code?:number}} data
     * @param {HTMLElement} outputEl
     */
    function renderRelayPayload(data, outputEl) {
        if (!outputEl || !data) return;
        revokeBlobUrls();
        var encoding = String(data.encoding || 'text');
        var ct = String(data.contentType || '').split(';')[0].trim().toLowerCase();
        var body = data.body == null ? '' : String(data.body);

        if (encoding === 'base64') {
            var kind = mediaKind(ct) || 'image';
            try {
                var bin = atob(body);
                var len = bin.length;
                var bytes = new Uint8Array(len);
                for (var i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
                var blob = new Blob([bytes], { type: ct || 'application/octet-stream' });
                if (blob.size > 40 * 1024 * 1024) {
                    outputEl.innerHTML = '<div class="pg-media-wrap pg-media-wrap--hint"><p>媒体文件过大，请直接访问接口地址。</p></div>';
                    return;
                }
                var url = trackBlob(URL.createObjectURL(blob));
                if (kind === 'image' || kind === 'audio' || kind === 'video') {
                    outputEl.innerHTML = renderMediaHtml(kind, url, (kind.toUpperCase()) + ' · ' + Math.round(blob.size / 1024) + ' KB');
                } else {
                    outputEl.innerHTML = renderBinaryHint(ct, url);
                }
            } catch (e) {
                outputEl.innerHTML = renderBinaryHint(ct || 'binary', '');
            }
            return;
        }

        if (isProbablyBinary(ct, body)) {
            outputEl.innerHTML = renderBinaryHint(ct || 'binary', '');
            return;
        }

        var display = body || '';
        var truncated = false;
        if (display.length > MAX_TEXT_CHARS) {
            display = display.slice(0, MAX_TEXT_CHARS);
            truncated = true;
        }
        try {
            var json = JSON.parse(body);
            var pretty = JSON.stringify(json, null, 2);
            if (pretty.length > MAX_TEXT_CHARS) {
                pretty = pretty.slice(0, MAX_TEXT_CHARS);
                truncated = true;
            }
            outputEl.innerHTML = syntaxHighlight(pretty)
                + (truncated ? '\n<span class="json-null">// …已截断</span>' : '');
        } catch (e2) {
            if (/html/.test(ct)) {
                var safeDoc = escapeHtml(display.slice(0, 80000));
                outputEl.innerHTML = '<div class="pg-media-tip">// HTML 响应（沙箱预览）</div>'
                    + '<iframe class="pg-html-frame" sandbox="" srcdoc="' + safeDoc.replace(/"/g, '&quot;') + '"></iframe>';
                return;
            }
            outputEl.innerHTML = '<pre class="response-pre">' + escapeHtml(display)
                + (truncated ? '\n// …已截断' : '') + '</pre>';
        }
    }

    /**
     * 调用同源中继
     */
    function relayRequest(opts) {
        var playUrl = (typeof window.VS_PLAY_URL === 'string' && window.VS_PLAY_URL)
            ? window.VS_PLAY_URL
            : ((window.VS_BASE_URL || '') + '/core/playground/relay.php');
        var csrf = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
        var payload = {
            csrf_token: csrf,
            api_id: opts.apiId,
            method: opts.method || 'GET',
            params: opts.params || {}
        };
        return fetch(playUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!data || typeof data !== 'object') {
                    throw new Error('无效响应');
                }
                return data;
            });
        });
    }

    global.VsPlaygroundResponse = {
        renderFetchResponse: renderFetchResponse,
        renderDirectMedia: renderDirectMedia,
        renderRelayPayload: renderRelayPayload,
        relayRequest: relayRequest,
        syntaxHighlight: syntaxHighlight,
        revokeBlobUrls: revokeBlobUrls,
        escapeHtml: escapeHtml
    };
})(typeof window !== 'undefined' ? window : this);
