/**
 * 申请友链：一键获取 TDK + AJAX 提交
 * 须在壳 common.js（VS.postForm）之后加载；仍内置 fetch 兜底，防止整页提交出裸 JSON。
 */
(function () {
    'use strict';

    var form = document.getElementById('applyLinkForm');
    var alertEl = document.getElementById('applyAlert');
    var btn = document.getElementById('applySubmitBtn');
    var fetchBtn = document.getElementById('applyFetchBtn');
    var statusEl = document.getElementById('applyFetchStatus');
    var urlInput = document.getElementById('applyUrl');
    var nameInput = document.getElementById('applyName');
    var iconInput = document.getElementById('applyIcon');
    var descInput = document.getElementById('applyDesc');

    function showAlert(type, msg) {
        if (!alertEl) return;
        alertEl.hidden = false;
        alertEl.className = 'alert alert-' + (type === 'error' ? 'error' : 'success');
        alertEl.textContent = msg || '';
    }

    function setFetchStatus(msg, tone) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
        statusEl.className = 'applylink-fetch-status'
            + (tone === 'ok' ? ' is-ok' : '')
            + (tone === 'err' ? ' is-err' : '');
    }

    function parseJsonText(text) {
        var raw = (text == null) ? '' : String(text).trim();
        try {
            return JSON.parse(raw || '{}');
        } catch (e) {
            return null;
        }
    }

    function postApply(action) {
        if (window.VS && typeof VS.postForm === 'function') {
            return VS.postForm(form, action);
        }
        var body = new FormData(form);
        var csrf = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
        if (csrf && !body.get('csrf_token')) {
            body.append('csrf_token', csrf);
        }
        var headers = { 'Accept': 'application/json' };
        if (csrf) {
            headers['X-CSRF-Token'] = csrf;
        }
        return fetch(action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: headers
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = parseJsonText(text);
                if (!data) {
                    throw new Error('invalid_json');
                }
                return data;
            });
        });
    }

    if (fetchBtn && urlInput) {
        fetchBtn.addEventListener('click', function () {
            var url = String(urlInput.value || '').trim();
            if (!url) {
                setFetchStatus('请先填写网站链接', 'err');
                if (window.VS && typeof VS.showMessage === 'function') {
                    VS.showMessage('请先填写网站链接', 'error');
                }
                return;
            }
            var metaUrl = (typeof window.VS_LINK_META_URL === 'string' && window.VS_LINK_META_URL)
                ? window.VS_LINK_META_URL
                : ((window.VS_BASE_URL || '') + '/core/theme/default/api/sitemeta.php');
            var csrf = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('url', url);

            fetchBtn.disabled = true;
            setFetchStatus('正在获取网站信息…', '');

            fetch(metaUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: fd
            }).then(function (res) {
                return res.text().then(function (text) {
                    var data = parseJsonText(text);
                    if (!data || data.code !== 1) {
                        throw new Error((data && data.msg) ? data.msg : '获取失败');
                    }
                    return data;
                });
            }).then(function (data) {
                if (data.siteurl) urlInput.value = data.siteurl;
                if (data.name && nameInput) nameInput.value = data.name;
                if (data.icon && iconInput) iconInput.value = data.icon;
                if (data.description && descInput) descInput.value = data.description;
                setFetchStatus(data.msg || '获取成功，已自动填充', 'ok');
                if (window.VS && typeof VS.showMessage === 'function') {
                    VS.showMessage(data.msg || '获取成功', 'success');
                }
            }).catch(function (err) {
                var msg = (err && err.message) ? err.message : '获取失败';
                setFetchStatus(msg, 'err');
                if (window.VS && typeof VS.showMessage === 'function') {
                    VS.showMessage(msg, 'error');
                }
            }).then(function () {
                fetchBtn.disabled = false;
            });
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (btn) btn.disabled = true;
            var action = form.getAttribute('action') || window.location.href;
            postApply(action)
                .then(function (data) {
                    if (!data || !data.code) {
                        throw new Error((data && data.msg) || '提交失败');
                    }
                    showAlert('success', data.msg || '申请已提交，请等待审核');
                    if (window.VS && typeof VS.showMessage === 'function') {
                        VS.showMessage(data.msg || '申请已提交', 'success');
                    }
                    form.reset();
                    setFetchStatus('', '');
                })
                .catch(function (err) {
                    var msg = (err && err.message) ? err.message : '提交失败';
                    if (msg === 'invalid_json') msg = '提交失败，请稍后重试';
                    showAlert('error', msg);
                    if (window.VS && typeof VS.showMessage === 'function') {
                        VS.showMessage(msg, 'error');
                    }
                })
                .then(function () {
                    if (btn) btn.disabled = false;
                });
        });
    }
})();
