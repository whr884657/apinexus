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
        var t = String(ct || '').toLowerCase().split(';')[0].trim();
        if (t.indexOf('image/') === 0) return 'image';
        if (t.indexOf('audio/') === 0) return 'audio';
        if (t.indexOf('video/') === 0) return 'video';
        return '';
    }

    /** 从二进制头嗅探（Content-Type 缺失/错误时） */
    function sniffKindFromBytes(u8) {
        if (!u8 || u8.length < 12) return '';
        // JPEG
        if (u8[0] === 0xFF && u8[1] === 0xD8 && u8[2] === 0xFF) return 'image';
        // PNG
        if (u8[0] === 0x89 && u8[1] === 0x50 && u8[2] === 0x4E && u8[3] === 0x47) return 'image';
        // GIF
        if (u8[0] === 0x47 && u8[1] === 0x49 && u8[2] === 0x46) return 'image';
        // WEBP
        if (u8[0] === 0x52 && u8[1] === 0x49 && u8[2] === 0x46 && u8[3] === 0x46
            && u8[8] === 0x57 && u8[9] === 0x45 && u8[10] === 0x42 && u8[11] === 0x50) return 'image';
        // WAVE
        if (u8[0] === 0x52 && u8[1] === 0x49 && u8[2] === 0x46 && u8[3] === 0x46
            && u8[8] === 0x57 && u8[9] === 0x41 && u8[10] === 0x56 && u8[11] === 0x45) return 'audio';
        // OGG
        if (u8[0] === 0x4F && u8[1] === 0x67 && u8[2] === 0x67 && u8[3] === 0x53) return 'audio';
        // ID3 / MP3
        if ((u8[0] === 0x49 && u8[1] === 0x44 && u8[2] === 0x33) || (u8[0] === 0xFF && (u8[1] & 0xE0) === 0xE0)) return 'audio';
        // MP4 / M4A ftyp
        if (u8[4] === 0x66 && u8[5] === 0x74 && u8[6] === 0x79 && u8[7] === 0x70) {
            var brand = String.fromCharCode(u8[8], u8[9], u8[10], u8[11]).toLowerCase();
            if (brand.indexOf('m4a') !== -1 || brand === 'mp4a') return 'audio';
            return 'video';
        }
        // WebM / Matroska
        if (u8[0] === 0x1A && u8[1] === 0x45 && u8[2] === 0xDF && u8[3] === 0xA3) return 'video';
        return '';
    }

    function sniffKindFromBase64(b64) {
        try {
            var bin = atob(String(b64 || '').slice(0, 64));
            var u8 = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
            return sniffKindFromBytes(u8);
        } catch (e) {
            return '';
        }
    }

    function resolveMediaKind(data, ct, b64Body) {
        var k = String(data && data.mediaKind ? data.mediaKind : '').toLowerCase();
        if (k === 'image' || k === 'audio' || k === 'video') return k;
        k = mediaKind(ct);
        if (k) return k;
        if (b64Body) {
            k = sniffKindFromBase64(b64Body);
            if (k) return k;
        }
        return '';
    }

    function renderMediaHtml(kind, objectUrl, note) {
        var safe = escapeHtml(objectUrl);
        var tip = note ? '<div class="pg-media-tip">' + escapeHtml(note) + '</div>' : '';
        if (kind === 'video') {
            return '<div class="pg-media-wrap"><video controls preload="metadata" playsinline class="pg-media-el" src="' + safe + '"></video>' + tip + '</div>';
        }
        if (kind === 'audio') {
            return '<div class="pg-media-wrap"><audio controls preload="metadata" class="pg-media-el pg-media-el--audio" src="' + safe + '"></audio>' + tip + '</div>';
        }
        return '<div class="pg-media-wrap"><img src="' + safe + '" alt="预览" class="pg-media-el pg-media-el--img" loading="lazy" decoding="async" referrerpolicy="no-referrer">' + tip + '</div>';
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

        if (encoding === 'omit') {
            var tip = (data.msg && String(data.msg)) || '媒体体积较大，在线预览已跳过，请直接访问接口地址';
            outputEl.innerHTML = '<div class="pg-media-wrap pg-media-wrap--hint"><p>' + escapeHtml(tip) + '</p></div>';
            return;
        }

        if (encoding === 'url') {
            var mediaUrl = body;
            var kindUrl = resolveMediaKind(data, ct, '');
            if (!kindUrl) {
                // url 模式无魔数，按 contentType；仍未知则给可打开链接，勿默认当图片
                outputEl.innerHTML = renderBinaryHint(ct || 'binary', mediaUrl);
                return;
            }
            outputEl.innerHTML = renderMediaHtml(kindUrl, mediaUrl, kindUrl.toUpperCase() + ' 预览');
            return;
        }

        if (encoding === 'base64') {
            var kind = resolveMediaKind(data, ct, body);
            try {
                var bin = atob(body);
                var len = bin.length;
                var bytes = new Uint8Array(len);
                for (var i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
                if (!kind) {
                    kind = sniffKindFromBytes(bytes);
                }
                var blobType = ct;
                if (kind === 'image' && (!blobType || blobType.indexOf('image/') !== 0)) blobType = 'image/jpeg';
                if (kind === 'video' && (!blobType || blobType.indexOf('video/') !== 0)) blobType = 'video/mp4';
                if (kind === 'audio' && (!blobType || blobType.indexOf('audio/') !== 0)) blobType = 'audio/mpeg';
                var blob = new Blob([bytes], { type: blobType || 'application/octet-stream' });
                if (blob.size > 40 * 1024 * 1024) {
                    outputEl.innerHTML = '<div class="pg-media-wrap pg-media-wrap--hint"><p>媒体文件过大，请直接访问接口地址。</p></div>';
                    return;
                }
                var url = trackBlob(URL.createObjectURL(blob));
                if (kind === 'image' || kind === 'audio' || kind === 'video') {
                    outputEl.innerHTML = renderMediaHtml(kind, url, kind.toUpperCase() + ' · ' + Math.round(blob.size / 1024) + ' KB');
                } else {
                    outputEl.innerHTML = renderBinaryHint(ct || 'binary', url);
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
            return res.text().then(function (text) {
                var raw = (text == null) ? '' : String(text).trim();
                if (!raw) {
                    throw new Error('中继返回空响应（HTTP ' + res.status + '）');
                }
                var data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    throw new Error('中继返回非 JSON（HTTP ' + res.status + '）');
                }
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
