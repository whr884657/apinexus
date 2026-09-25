/**
 * 主题三 · 申请友链：一键获取 sitemeta + 提交 applyLinkForm
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
        alertEl.className = 'th3-alert th3-alert--' + (type === 'error' ? 'error' : 'success');
        alertEl.textContent = msg || '';
    }

    function setFetchStatus(msg, tone) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
        statusEl.className = 'th3-field-hint'
            + (tone === 'ok' ? ' is-ok' : '')
            + (tone === 'err' ? ' is-err' : '');
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
                : '';
            if (!metaUrl) {
                setFetchStatus('未配置获取地址', 'err');
                return;
            }
            var csrf = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('url', url);

            fetchBtn.disabled = true;
            setFetchStatus('正在获取网站信息…', '');

            fetch(metaUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf },
                body: fd
            }).then(function (res) {
                return res.text().then(function (text) {
                    var raw = (text == null) ? '' : String(text).trim();
                    var data;
                    try {
                        data = JSON.parse(raw || '{}');
                    } catch (e) {
                        throw new Error('获取失败，请稍后重试');
                    }
                    if (!data || data.code !== 1) {
                        throw new Error((data && data.msg) ? data.msg : '获取失败');
                    }
                    return data;
                });
            }).then(function (data) {
                if (data.siteurl && urlInput) urlInput.value = data.siteurl;
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
            var chain;
            if (window.VS && typeof VS.postForm === 'function') {
                chain = VS.postForm(form, action);
            } else {
                var body = new FormData(form);
                var csrfFb = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
                if (csrfFb && !body.get('csrf_token')) {
                    body.append('csrf_token', csrfFb);
                }
                var headersFb = { 'Accept': 'application/json' };
                if (csrfFb) {
                    headersFb['X-CSRF-Token'] = csrfFb;
                }
                chain = fetch(action, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: headersFb
                }).then(function (res) {
                    return res.text().then(function (text) {
                        try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                    });
                });
            }
            Promise.resolve(chain)
                .then(function (data) {
                    if (data && data.submit_ticket) {
                        var tIn = form.querySelector('input[name="submit_ticket"]');
                        if (tIn) tIn.value = data.submit_ticket;
                    }
                    if (!data || !data.code) {
                        if (window.VsCaptcha && typeof window.VsCaptcha.reset === 'function') {
                            window.VsCaptcha.reset(form);
                        }
                        throw new Error((data && data.msg) || '提交失败');
                    }
                    showAlert('success', data.msg || '申请已提交，请等待审核');
                    if (window.VS && typeof VS.showMessage === 'function') {
                        VS.showMessage(data.msg || '申请已提交', 'success');
                    }
                    var newTicket = data.submit_ticket || '';
                    var csrfKeep = (typeof window.VS_CSRF_TOKEN === 'string') ? window.VS_CSRF_TOKEN : '';
                    form.reset();
                    var ticketInput = form.querySelector('input[name="submit_ticket"]');
                    if (ticketInput && newTicket) ticketInput.value = newTicket;
                    var csrfInput = form.querySelector('input[name="csrf_token"]');
                    if (csrfInput && csrfKeep) csrfInput.value = csrfKeep;
                    if (window.VsCaptcha && typeof window.VsCaptcha.reset === 'function') {
                        window.VsCaptcha.reset(form);
                    }
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
