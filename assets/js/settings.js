/**
 * 文件：assets/js/settings.js
 * 作用：系统设置页 AJAX 保存与折叠板块
 * @version 1.6.1
 */

(function () {
    'use strict';

    var flashEl = document.getElementById('settingsFlash');

    function showFlash(text, type) {
        if (window.VsToast) {
            VsToast.show(text, type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success'));
            return;
        }
        if (!flashEl) return;
        flashEl.textContent = text;
        flashEl.className = 'vs-settings-flash vs-alert vs-alert--' + type;
        flashEl.hidden = false;
        flashEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function postForm(form) {
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        return window.VS.postForm(form)
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    function bindAjaxForm(form) {
        if (!form || form.getAttribute('data-ajax') !== '1') return;
        if (form.getAttribute('data-ajax-bound') === '1') return;
        form.setAttribute('data-ajax-bound', '1');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtns = form.querySelectorAll('[type="submit"]');
            var bi;
            for (bi = 0; bi < submitBtns.length; bi++) {
                submitBtns[bi].disabled = true;
            }
            var body;
            if (form.id === 'mailForm' && typeof window.__vsBuildMailSmtp === 'function') {
                var mailJson = window.__vsBuildMailSmtp();
                if (mailJson === null) {
                    for (bi = 0; bi < submitBtns.length; bi++) {
                        submitBtns[bi].disabled = false;
                    }
                    return;
                }
                body = new FormData(form);
                body.set('mailsmtp', mailJson);
            } else {
                body = form;
            }
            window.VS.postForm(body)
                .then(function (data) {
                    if (data.code === 1) {
                        var msg = data.msg || '操作成功';
                        if (data.iploc) {
                            msg += '：' + data.iploc;
                        }
                        showFlash(msg, 'success');
                        if (form.id === 'mailForm' && typeof window.__vsRefreshMailTestOptions === 'function') {
                            window.__vsRefreshMailTestOptions();
                        }
                    } else {
                        showFlash(data.msg || '操作失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    for (bi = 0; bi < submitBtns.length; bi++) {
                        submitBtns[bi].disabled = false;
                    }
                });
        });
    }

    /**
     * 系统 API / 邮箱账号细条：事件委托 + is-open 控显（E413）
     * 邮箱与系统 API 共用同一套展开逻辑，按 data-fold-group 互斥
     */
    function bindSettingsFolds() {
        if (document.documentElement.getAttribute('data-vs-fold-bound') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-vs-fold-bound', '1');

        function walkHasMailOn(el) {
            var n = el;
            while (n && n !== document && n !== document.body) {
                if (n.getAttribute && n.getAttribute('data-mail-field') === 'on') {
                    return true;
                }
                if (n.classList && n.classList.contains('vs-settings-mail-on')) {
                    return true;
                }
                n = n.parentNode;
            }
            return false;
        }

        function closestBar(el) {
            var n = el;
            while (n && n !== document && n !== document.body) {
                if (n.classList && n.classList.contains('vs-settings-api-fold__bar')) {
                    return n;
                }
                n = n.parentNode;
            }
            return null;
        }

        function closestFold(el) {
            var n = el;
            while (n && n !== document && n !== document.body) {
                if (n.getAttribute && (n.getAttribute('data-api-fold') !== null || n.getAttribute('data-mail-fold') !== null)) {
                    return n;
                }
                n = n.parentNode;
            }
            return null;
        }

        function setFoldOpen(fold, open) {
            var bar = fold.querySelector('.vs-settings-api-fold__bar');
            var body = fold.querySelector('.vs-settings-api-fold__body');
            if (open) {
                fold.classList.add('is-open');
            } else {
                fold.classList.remove('is-open');
            }
            if (bar) {
                bar.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            if (body) {
                if (open) {
                    body.removeAttribute('hidden');
                } else {
                    body.setAttribute('hidden', '');
                }
            }
        }

        function closeGroup(fold) {
            var group = fold.getAttribute('data-fold-group') || '';
            var all = document.querySelectorAll('[data-api-fold], [data-mail-fold]');
            var i;
            for (i = 0; i < all.length; i++) {
                if (all[i] === fold) {
                    continue;
                }
                var g = all[i].getAttribute('data-fold-group') || '';
                if (group !== '' && g !== '' && group !== g) {
                    continue;
                }
                setFoldOpen(all[i], false);
            }
        }

        document.addEventListener('click', function (e) {
            var t = e.target || e.srcElement;
            if (!t) {
                return;
            }
            if (walkHasMailOn(t)) {
                return;
            }
            var bar = closestBar(t);
            if (!bar) {
                return;
            }
            var fold = closestFold(bar);
            if (!fold) {
                return;
            }
            if (e.preventDefault) {
                e.preventDefault();
            }
            var open = fold.classList.contains('is-open');
            if (open) {
                setFoldOpen(fold, false);
                return;
            }
            closeGroup(fold);
            setFoldOpen(fold, true);
        }, false);
    }

    function bindSystemApiFolds() {
        bindSettingsFolds();
    }

    function bindMailAccounts() {
        var listEl = document.getElementById('mailAccountList');
        var addBtn = document.getElementById('mailAddAccountBtn');
        var modeEl = document.getElementById('mailModeSelect');
        var jsonEl = document.getElementById('mailsmtpJson');
        var testSel = document.getElementById('testMailAccount');
        var countHint = document.getElementById('mailAccountCountHint');
        var emptyHint = document.getElementById('mailAccountEmptyHint');
        if (!listEl) {
            return;
        }
        /* 折叠只走 bindSettingsFolds 事件委托；禁止再给 bar 绑第二套 click（会双触发导致展不开） */
        bindSettingsFolds();
        var maxAcc = parseInt(listEl.getAttribute('data-mail-max') || '5', 10) || 5;

        function randId() {
            var s = '';
            try {
                var arr = new Uint8Array(4);
                if (window.crypto && window.crypto.getRandomValues) {
                    window.crypto.getRandomValues(arr);
                    var i;
                    for (i = 0; i < arr.length; i++) {
                        s += ('0' + arr[i].toString(16)).slice(-2);
                    }
                    return s;
                }
            } catch (e) { /* ignore */ }
            return ('m' + Date.now().toString(16) + Math.floor(Math.random() * 0xffff).toString(16)).slice(0, 8);
        }

        function folds() {
            return listEl.querySelectorAll('[data-mail-fold]');
        }

        function isCheckboxOn(el) {
            if (!el) {
                return false;
            }
            var typ = (el.type || el.getAttribute('type') || '').toLowerCase();
            if (typ === 'checkbox') {
                return !!el.checked;
            }
            return false;
        }

        function fieldVal(fold, name) {
            var el = fold.querySelector('[data-mail-field="' + name + '"]');
            if (!el) {
                return '';
            }
            var typ = (el.type || el.getAttribute('type') || '').toLowerCase();
            if (typ === 'checkbox') {
                return el.checked ? 1 : 0;
            }
            return String(el.value || '').trim();
        }

        function syncBadge(fold) {
            var onEl = fold.querySelector('[data-mail-field="on"]');
            var badge = fold.querySelector('[data-mail-badge]');
            var on = isCheckboxOn(onEl);
            if (badge) {
                badge.style.display = on ? '' : 'none';
            }
        }

        function updateTitle(fold) {
            var titleEl = fold.querySelector('[data-mail-title]');
            if (!titleEl) {
                return;
            }
            var name = fieldVal(fold, 'name');
            var from = fieldVal(fold, 'fromemail');
            titleEl.textContent = name || from || '未命名账号';
        }

        function collectList() {
            var all = folds();
            var out = [];
            var i;
            for (i = 0; i < all.length && out.length < maxAcc; i++) {
                var fold = all[i];
                var ssl = parseInt(fieldVal(fold, 'ssl'), 10);
                if (isNaN(ssl) || ssl < 0 || ssl > 2) {
                    ssl = 0;
                }
                var port = parseInt(fieldVal(fold, 'port'), 10);
                if (isNaN(port) || port < 1 || port > 65535) {
                    port = 465;
                }
                var onEl = fold.querySelector('[data-mail-field="on"]');
                var id = fieldVal(fold, 'id') || randId();
                var passEl = fold.querySelector('[data-mail-field="pass"]');
                out.push({
                    id: id,
                    name: fieldVal(fold, 'name'),
                    on: isCheckboxOn(onEl) ? 1 : 0,
                    host: fieldVal(fold, 'host'),
                    port: port,
                    ssl: ssl,
                    user: fieldVal(fold, 'user'),
                    pass: passEl ? String(passEl.value || '') : '',
                    fromemail: fieldVal(fold, 'fromemail'),
                    fromname: fieldVal(fold, 'fromname')
                });
            }
            return out;
        }

        function syncToolbar() {
            var n = folds().length;
            if (addBtn) {
                addBtn.disabled = n >= maxAcc;
            }
            if (countHint) {
                countHint.textContent = '已添加 ' + n + ' / ' + maxAcc + ' 个账号';
            }
            if (emptyHint) {
                if (n === 0) {
                    emptyHint.removeAttribute('hidden');
                } else {
                    emptyHint.setAttribute('hidden', '');
                }
            }
        }

        function refreshTestOptions() {
            if (!testSel) {
                return;
            }
            var cur = testSel.value;
            while (testSel.options.length) {
                testSel.remove(0);
            }
            var o0 = document.createElement('option');
            o0.value = '';
            o0.textContent = '按发信策略自动选号';
            testSel.appendChild(o0);
            var list = collectList();
            var i;
            for (i = 0; i < list.length; i++) {
                var a = list[i];
                var o = document.createElement('option');
                o.value = a.id;
                o.textContent = (a.on ? '' : '（未启用）') + (a.name || a.fromemail || a.id);
                testSel.appendChild(o);
            }
            if (cur) {
                testSel.value = cur;
            }
            if (window.VSPick && typeof window.VSPick.refresh === 'function') {
                window.VSPick.refresh(testSel);
            }
        }

        /** 与后端 Mailer::hasControlChars 对齐 */
        function hasControlChars(s) {
            return /[\x00-\x1F\x7F]/.test(String(s || ''));
        }

        /** 与后端 Mailer::isValidSmtpHost 对齐：仅域名，禁止 IP */
        function isValidSmtpHost(host) {
            host = String(host || '').replace(/^\s+|\s+$/g, '');
            if (!host || host.length > 255 || hasControlChars(host)) {
                return false;
            }
            if (host.indexOf('://') !== -1 || host.indexOf('/') !== -1
                || host.indexOf('@') !== -1 || host.indexOf(' ') !== -1
                || host.indexOf('\\') !== -1) {
                return false;
            }
            host = host.replace(/\.+$/, '');
            if (!host) {
                return false;
            }
            if (/^(\d{1,3}\.){3}\d{1,3}$/.test(host) || host.indexOf(':') !== -1) {
                return false;
            }
            return /^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$/.test(host);
        }

        /**
         * @returns {string|null} mailsmtp JSON；失败返回 null
         */
        window.__vsBuildMailSmtp = function () {
            if (!modeEl) {
                showFlash('邮箱表单结构异常', 'error');
                return null;
            }
            var mode = parseInt(modeEl.value, 10);
            if (isNaN(mode) || mode < 0 || mode > 2) {
                mode = 0;
            }
            var list = collectList();
            if (list.length > maxAcc) {
                showFlash('发信账号最多 ' + maxAcc + ' 个', 'error');
                return null;
            }
            var i;
            for (i = 0; i < list.length; i++) {
                var a = list[i];
                var host = String(a.host || '').replace(/^\s+|\s+$/g, '');
                var user = String(a.user || '').replace(/^\s+|\s+$/g, '');
                var from = String(a.fromemail || '').replace(/^\s+|\s+$/g, '');
                var fromName = String(a.fromname || '').replace(/^\s+|\s+$/g, '');
                if (host !== '' && !isValidSmtpHost(host)) {
                    showFlash('SMTP 服务器请填写域名（不允许 IP 或非法字符）', 'error');
                    return null;
                }
                if (user !== '' && hasControlChars(user)) {
                    showFlash('SMTP 用户名含非法控制字符', 'error');
                    return null;
                }
                if (from !== '' && (hasControlChars(from) || from.indexOf('@') < 1)) {
                    showFlash('发件邮箱格式无效', 'error');
                    return null;
                }
                if (fromName !== '' && hasControlChars(fromName)) {
                    showFlash('发件人名称含非法控制字符', 'error');
                    return null;
                }
                if (a.on) {
                    if (host === '' || user === '' || from === '') {
                        showFlash('已启用的账号须填写 SMTP 主机、用户名与发件邮箱', 'error');
                        return null;
                    }
                }
            }
            var payload = JSON.stringify({ mode: mode, list: list });
            if (jsonEl) {
                jsonEl.value = payload;
            }
            return payload;
        };
        /* 兼容旧钩子名 */
        window.__vsPrepareMailSmtp = function () {
            return window.__vsBuildMailSmtp() !== null;
        };
        window.__vsRefreshMailTestOptions = function () {
            var all = folds();
            var i;
            for (i = 0; i < all.length; i++) {
                syncBadge(all[i]);
            }
            refreshTestOptions();
            syncToolbar();
        };

        function bindFoldExtras(fold) {
            var removeBtn = fold.querySelector('[data-mail-remove]');
            if (removeBtn && removeBtn.getAttribute('data-bound') !== '1') {
                removeBtn.setAttribute('data-bound', '1');
                removeBtn.addEventListener('click', function () {
                    if (!window.confirm('确定删除此发信账号？删除后请再点底部「保存邮箱设置」。')) {
                        return;
                    }
                    if (fold.parentNode) {
                        fold.parentNode.removeChild(fold);
                    }
                    syncToolbar();
                    refreshTestOptions();
                });
            }
            var onEl = fold.querySelector('[data-mail-field="on"]');
            if (onEl && onEl.getAttribute('data-on-bound') !== '1') {
                onEl.setAttribute('data-on-bound', '1');
                onEl.addEventListener('change', function () {
                    syncBadge(fold);
                    refreshTestOptions();
                });
                onEl.addEventListener('click', function (e) {
                    if (e && e.stopPropagation) {
                        e.stopPropagation();
                    }
                });
            }
            var titleInputs = fold.querySelectorAll('[data-mail-field="name"], [data-mail-field="fromemail"]');
            var t;
            for (t = 0; t < titleInputs.length; t++) {
                if (titleInputs[t].getAttribute('data-title-bound') === '1') {
                    continue;
                }
                titleInputs[t].setAttribute('data-title-bound', '1');
                titleInputs[t].addEventListener('input', function () {
                    updateTitle(fold);
                });
            }
            syncBadge(fold);
        }

        function setFoldOpen(fold, open) {
            var bar = fold.querySelector('.vs-settings-api-fold__bar');
            var body = fold.querySelector('.vs-settings-api-fold__body');
            if (open) {
                fold.classList.add('is-open');
            } else {
                fold.classList.remove('is-open');
            }
            if (bar) {
                bar.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            if (body) {
                if (open) {
                    body.removeAttribute('hidden');
                } else {
                    body.setAttribute('hidden', '');
                }
            }
        }

        function closeOtherMailFolds(except) {
            var all = folds();
            var j;
            for (j = 0; j < all.length; j++) {
                if (all[j] !== except) {
                    setFoldOpen(all[j], false);
                }
            }
        }

        function buildEmptyFold() {
            var id = randId();
            var wrap = document.createElement('div');
            wrap.className = 'vs-settings-api-fold';
            wrap.setAttribute('data-mail-fold', '');
            wrap.setAttribute('data-api-fold', '');
            wrap.setAttribute('data-fold-group', 'mail');
            wrap.id = 'mail-fold-' + id;
            wrap.innerHTML =
                '<div class="vs-settings-mail-head">' +
                '<label class="vs-checkbox vs-settings-mail-on" title="启用后参与发信">' +
                '<input type="checkbox" data-mail-field="on" value="1"><span>启用</span></label>' +
                '<button type="button" class="vs-settings-api-fold__bar" aria-expanded="false" aria-controls="mail-fold-' + id + '-body">' +
                '<span class="vs-settings-api-fold__title"><span data-mail-title>未命名账号</span>' +
                '<span class="vs-form-hint" data-mail-badge style="margin-left:8px;display:none;">已启用</span></span>' +
                '<i class="vs-icon vs-icon--chevron" aria-hidden="true"></i></button></div>' +
                '<div class="vs-settings-api-fold__body" id="mail-fold-' + id + '-body" hidden>' +
                '<input type="hidden" data-mail-field="id" value="' + id + '">' +
                '<div class="vs-form-row"><label class="vs-label">显示名称</label>' +
                '<input type="text" class="vs-input" data-mail-field="name" value="" maxlength="32" placeholder="如：主号腾讯"></div>' +
                '<div class="vs-form-row"><label class="vs-label">SMTP 服务器</label>' +
                '<input type="text" class="vs-input" data-mail-field="host" value="" autocomplete="off" placeholder="smtp.qq.com（仅域名，勿填 IP）">' +
                '<p class="vs-form-hint">请填邮箱服务商提供的域名，不允许直接填 IP。</p></div>' +
                '<div class="vs-form-row vs-form-row--inline"><div class="vs-form-col"><label class="vs-label">端口</label>' +
                '<input type="text" class="vs-input" data-mail-field="port" value="465"></div>' +
                '<div class="vs-form-col"><label class="vs-label">加密</label>' +
                '<select class="vs-input vs-select" data-mail-field="ssl" data-vs-pick>' +
                '<option value="0" selected>SSL（常见 465）</option>' +
                '<option value="1">STARTTLS（常见 587）</option>' +
                '<option value="2">无加密</option></select></div></div>' +
                '<div class="vs-form-row"><label class="vs-label">SMTP 用户名</label>' +
                '<input type="text" class="vs-input" data-mail-field="user" value="" autocomplete="off"></div>' +
                '<div class="vs-form-row"><label class="vs-label">SMTP 密码</label>' +
                '<input type="password" class="vs-input" data-mail-field="pass" value="" autocomplete="new-password" placeholder="留空则不修改已保存密码"></div>' +
                '<div class="vs-form-row vs-form-row--inline"><div class="vs-form-col"><label class="vs-label">发件人邮箱</label>' +
                '<input type="email" class="vs-input" data-mail-field="fromemail" value=""></div>' +
                '<div class="vs-form-col"><label class="vs-label">发件人名称</label>' +
                '<input type="text" class="vs-input" data-mail-field="fromname" value=""></div></div>' +
                '<div class="vs-form-actions"><button type="button" class="vs-btn vs-btn--default" data-mail-remove>删除此账号</button></div>' +
                '<p class="vs-form-hint">本卡内没有「确认添加」。填完后请点「保存邮箱设置」才会写入。</p>' +
                '</div>';
            return wrap;
        }

        var existing = folds();
        var k;
        for (k = 0; k < existing.length; k++) {
            bindFoldExtras(existing[k]);
        }
        syncToolbar();
        refreshTestOptions();

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                if (folds().length >= maxAcc) {
                    showFlash('发信账号最多 ' + maxAcc + ' 个，请先删除多余账号', 'error');
                    return;
                }
                var fold = buildEmptyFold();
                listEl.appendChild(fold);
                bindFoldExtras(fold);
                if (window.VSPick && typeof window.VSPick.init === 'function') {
                    window.VSPick.init(fold);
                }
                closeOtherMailFolds(fold);
                setFoldOpen(fold, true);
                syncToolbar();
                refreshTestOptions();
                showFlash('已增加空白邮箱卡（尚未入库）。填完后请点底部「保存邮箱设置」才会生效', 'info');
                if (fold.scrollIntoView) {
                    try {
                        fold.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    } catch (err) {
                        fold.scrollIntoView(true);
                    }
                }
            });
        }
    }

    function bindAccordions() {
        document.querySelectorAll('[data-accordion]').forEach(function (section) {
            var trigger = section.querySelector('.vs-accordion__trigger');
            if (!trigger) return;

            trigger.addEventListener('click', function () {
                var isOpen = section.classList.contains('is-open');
                section.classList.toggle('is-open', !isOpen);
                trigger.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
            });
        });
    }

    function copyText(text, okMsg) {
        text = String(text || '').trim();
        if (!text) {
            showFlash('暂无可复制内容', 'error');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showFlash(okMsg || '已复制', 'success');
            }).catch(function () {
                showFlash('复制失败，请手动选中复制', 'error');
            });
            return;
        }
        showFlash('复制失败，请手动选中复制', 'error');
    }

    function secretEyeIconSvg(revealed) {
        if (revealed) {
            return '<svg class="vs-secret-field__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
                + '<path fill="currentColor" d="M12 7a5 5 0 0 1 5 5c0 .7-.15 1.36-.4 1.96l1.48 1.48A9.8 9.8 0 0 0 21 12c-1.73-4.39-6-7.5-9-7.5-1.1 0-2.16.3-3.12.82l1.5 1.5c.5-.2 1.05-.32 1.62-.32zm-7.03-.61 1.66 1.66A9.8 9.8 0 0 0 3 12c1.73 4.39 6 7.5 9 7.5 1.55 0 3.03-.45 4.3-1.22l1.7 1.7 1.27-1.27L5.24 4.12 3.97 5.39zm5.5 5.5 3.25 3.25A3 3 0 0 1 9 12c0-.2.02-.4.06-.58l1.41 1.41zM12 9a3 3 0 0 1 2.83 4.01l-3.84-3.84c.32-.1.66-.17 1.01-.17z"/>'
                + '</svg>';
        }
        return '<svg class="vs-secret-field__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
            + '<path fill="currentColor" d="M12 5c-5 0-9.27 3.11-11 7 1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 .001 6.001A3 3 0 0 0 12 9z"/>'
            + '</svg>';
    }

    /** 设置页密钥框：默认 password，点眼睛切换 text（无二次验证） */
    function bindSecretFieldToggles() {
        document.querySelectorAll('.vs-secret-field__toggle[data-secret-target]').forEach(function (btn) {
            var id = btn.getAttribute('data-secret-target') || '';
            var input = id ? document.getElementById(id) : null;
            if (!input) {
                return;
            }
            btn.innerHTML = secretEyeIconSvg(false);
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var on = btn.getAttribute('aria-pressed') === 'true';
                var next = !on;
                input.type = next ? 'text' : 'password';
                btn.setAttribute('aria-pressed', next ? 'true' : 'false');
                btn.setAttribute('aria-label', next ? '隐藏密钥' : '显示密钥');
                btn.innerHTML = secretEyeIconSvg(next);
            });
        });
    }

    function bindSystemApiKey() {
        var genBtn = document.getElementById('systemApiKeyGenBtn');
        var copyKeyBtn = document.getElementById('systemApiKeyCopyBtn');
        var keyInput = document.getElementById('systemApiKeyInput');

        if (genBtn) {
            genBtn.addEventListener('click', function () {
                if (!window.confirm('生成新系统密钥后，旧密钥立即失效（卡密对接、签到、用户信息、调用日志清理、接口查询、调用排行、全站概况等系统 API 均须更新），是否继续？')) {
                    return;
                }
                genBtn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'generate_system_api_key');
                window.VS.postForm(fd, window.location.href)
                    .then(function (data) {
                        if (data.code === 1) {
                            if (keyInput) {
                                keyInput.value = data.system_api_key || '';
                                keyInput.type = 'password';
                                var eye = document.querySelector('.vs-secret-field__toggle[data-secret-target="systemApiKeyInput"]');
                                if (eye) {
                                    eye.setAttribute('aria-pressed', 'false');
                                    eye.setAttribute('aria-label', '显示密钥');
                                    eye.innerHTML = secretEyeIconSvg(false);
                                }
                            }
                            showFlash(data.msg || '系统密钥已生成', 'success');
                        } else {
                            showFlash((data && data.msg) || '生成失败', 'error');
                        }
                    })
                    .catch(function () {
                        showFlash('网络异常，请稍后重试', 'error');
                    })
                    .finally(function () {
                        genBtn.disabled = false;
                    });
            });
        }

        if (copyKeyBtn) {
            copyKeyBtn.addEventListener('click', function () {
                var key = keyInput ? String(keyInput.value || '').trim() : '';
                if (!key) {
                    showFlash('请先生成系统密钥', 'error');
                    return;
                }
                copyText(key, '系统密钥已复制');
            });
        }
    }

    function collectAiFormData(form) {
        var fd = new FormData(form);
        return fd;
    }

    function bindAiProvider() {
        var provider = document.getElementById('aiProvider');
        var baseurl = document.getElementById('aiBaseurl');
        if (!provider || !baseurl) {
            return;
        }
        var presets = {};
        try {
            presets = JSON.parse(baseurl.getAttribute('data-presets') || '{}') || {};
        } catch (e) {
            presets = {};
        }
        provider.addEventListener('change', function () {
            var key = String(provider.value || '');
            if (key === 'custom') {
                return;
            }
            if (presets[key]) {
                baseurl.value = presets[key];
            }
        });
    }

    /** @type {string[]} */
    var aiPulledModels = [];

    function renderAiModelPickerList(filterQ) {
        var input = document.getElementById('aiModel');
        var pickerList = document.getElementById('aiModelPickerList');
        var pickerTitle = document.getElementById('aiModelPickerTitle');
        var emptyEl = document.getElementById('aiModelPickerEmpty');
        if (!pickerList) {
            return;
        }
        var q = String(filterQ || '').trim().toLowerCase();
        var models = aiPulledModels;
        var shown = q === ''
            ? models.slice()
            : models.filter(function (id) {
                return String(id).toLowerCase().indexOf(q) !== -1;
            });
        pickerList.innerHTML = '';
        var current = input ? String(input.value || '').trim() : '';
        shown.forEach(function (id) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-ai-model-pick' + (id === current ? ' is-active' : '');
            btn.textContent = id;
            btn.title = id;
            btn.addEventListener('click', function () {
                if (input) {
                    input.value = id;
                }
                pickerList.querySelectorAll('.vs-ai-model-pick').forEach(function (el) {
                    el.classList.toggle('is-active', el === btn);
                });
                showFlash('已选择模型：' + id, 'success');
            });
            pickerList.appendChild(btn);
        });
        if (pickerTitle) {
            if (q !== '') {
                pickerTitle.textContent = '可用模型（匹配 ' + shown.length + ' / 共 ' + models.length + '）';
            } else {
                pickerTitle.textContent = '可用模型（共 ' + models.length + ' 个，点击选择）';
            }
        }
        if (emptyEl) {
            emptyEl.hidden = shown.length > 0;
        }
    }

    function fillModelDatalist(models) {
        var list = document.getElementById('aiModelList');
        var input = document.getElementById('aiModel');
        var picker = document.getElementById('aiModelPicker');
        var pickerList = document.getElementById('aiModelPickerList');
        var searchEl = document.getElementById('aiModelPickerSearch');
        models = Array.isArray(models) ? models.filter(function (id) { return !!String(id || '').trim(); }) : [];
        aiPulledModels = models;

        if (list) {
            list.innerHTML = '';
            models.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = id;
                list.appendChild(opt);
            });
        }

        if (models.length === 0) {
            if (picker) {
                picker.hidden = true;
            }
            if (pickerList) {
                pickerList.innerHTML = '';
            }
            if (searchEl) {
                searchEl.value = '';
            }
            showFlash('未获取到可用模型', 'error');
            return;
        }

        if (models.length === 1) {
            if (input) {
                input.value = models[0];
            }
            if (picker) {
                picker.hidden = true;
            }
            if (pickerList) {
                pickerList.innerHTML = '';
            }
            if (searchEl) {
                searchEl.value = '';
            }
            showFlash('已自动填入唯一模型：' + models[0], 'success');
            return;
        }

        if (input && !String(input.value || '').trim()) {
            input.value = models[0];
        }
        if (searchEl) {
            searchEl.value = '';
        }
        renderAiModelPickerList('');
        if (picker) {
            picker.hidden = false;
        }
        showFlash('已拉取 ' + models.length + ' 个模型，可搜索后点选', 'success');
    }

    function bindAiModelSearch() {
        var searchEl = document.getElementById('aiModelPickerSearch');
        if (!searchEl || searchEl.getAttribute('data-bound') === '1') {
            return;
        }
        searchEl.setAttribute('data-bound', '1');
        searchEl.addEventListener('input', function () {
            renderAiModelPickerList(searchEl.value);
        });
    }

    function bindOutboundUaPreview() {
        var brandEl = document.getElementById('outboundUaBrand');
        var box = document.getElementById('outboundUaSamples');
        if (!brandEl || !box) {
            return;
        }
        var ver = String(box.getAttribute('data-version') || '1').trim() || '1';
        if (/[\r\n\/\\\s]/.test(ver)) {
            ver = '1';
        }

        function normalizeBrand(raw) {
            var s = String(raw || '').trim();
            if (s === '') {
                return 'ApiNexus';
            }
            if (s.length > 32) {
                s = s.slice(0, 32);
            }
            if (/[\r\n\/\\\s]/.test(s) || !/^[A-Za-z0-9][A-Za-z0-9_-]{0,31}$/.test(s)) {
                return 'ApiNexus';
            }
            return s;
        }

        function buildUa(brand, component) {
            if (!component) {
                return brand + '/' + ver;
            }
            return brand + '-' + component + '/' + ver;
        }

        function refresh() {
            var brand = normalizeBrand(brandEl.value);
            var map = {
                plain: buildUa(brand, ''),
                proxy: buildUa(brand, 'Proxy'),
                iplocator: buildUa(brand, 'IpLocator'),
                oauth: buildUa(brand, 'OAuth')
            };
            Object.keys(map).forEach(function (key) {
                var el = box.querySelector('[data-ua-sample="' + key + '"]');
                if (el) {
                    el.textContent = map[key];
                }
            });
        }

        brandEl.addEventListener('input', refresh);
        brandEl.addEventListener('change', refresh);
        refresh();
    }

    function bindAiListModels() {
        var btn = document.getElementById('aiListModelsBtn');
        var form = document.getElementById('aiForm');
        if (!btn || !form) {
            return;
        }
        btn.addEventListener('click', function () {
            var baseurl = document.getElementById('aiBaseurl');
            if (!baseurl || !String(baseurl.value || '').trim()) {
                showFlash('请填写接口根地址', 'error');
                return;
            }
            btn.disabled = true;
            showFlash('正在拉取模型列表…', 'info');
            var fd = collectAiFormData(form);
            fd.set('action', 'list_ai_models');
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        fillModelDatalist(data.models || []);
                    } else {
                        showFlash((data && data.msg) || '拉取失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    function bindAiTest() {
        var btn = document.getElementById('aiTestBtn');
        var form = document.getElementById('aiForm');
        if (!btn || !form) {
            return;
        }
        btn.addEventListener('click', function () {
            var baseurl = document.getElementById('aiBaseurl');
            if (!baseurl || !String(baseurl.value || '').trim()) {
                showFlash('请填写接口根地址', 'error');
                return;
            }
            btn.disabled = true;
            showFlash('正在测试连接…', 'info');
            var fd = collectAiFormData(form);
            fd.set('action', 'test_ai');
            var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var timer = null;
            if (ctrl) {
                timer = setTimeout(function () {
                    try { ctrl.abort(); } catch (eAbort) { /* ignore */ }
                }, 55000);
            }
            window.VS.postForm(fd, window.location.href, ctrl ? { signal: ctrl.signal } : {})
                .then(function (data) {
                    if (data && data.code === 1) {
                        showFlash(data.msg || '连接成功', 'success');
                    } else {
                        showFlash((data && data.msg) || '连接失败', 'error');
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        showFlash('连接测试超时，请检查上游地址或稍后重试', 'error');
                        return;
                    }
                    showFlash('连接失败或网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (timer) {
                        clearTimeout(timer);
                    }
                    btn.disabled = false;
                });
        });
    }

    function bindIplocTest() {
        var form = document.getElementById('iplocTestForm');
        var main = document.getElementById('iplocForm');
        if (!form || !main || form.getAttribute('data-ajax') !== '1') {
            return;
        }
        form.setAttribute('data-ajax-bound', '1');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            var fd = new FormData();
            fd.append('action', 'test_iploc');
            var testIp = document.getElementById('iplocTestIp');
            fd.append('test_ip', testIp ? String(testIp.value || '').trim() : '');
            // 带上当前表单草稿，避免「未保存就测」永远失败
            var enabled = document.getElementById('ipLocEnabled');
            fd.append('ip_loc_enabled', (enabled && enabled.checked) ? '1' : '0');
            ['ip_loc_mode', 'ip_loc_url', 'ip_loc_method', 'ip_loc_ip_param',
                'ip_loc_auth', 'ip_loc_auth_name', 'ip_loc_auth_value', 'ip_loc_field', 'ip_loc_extras'
            ].forEach(function (name) {
                var el = main.querySelector('[name="' + name + '"]');
                if (el) {
                    fd.append(name, el.value != null ? String(el.value) : '');
                }
            });
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        var msg = data.msg || '解析成功';
                        if (data.iploc) {
                            msg += '：' + data.iploc;
                        }
                        showFlash(msg, 'success');
                    } else {
                        showFlash((data && data.msg) || '解析失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    }

    function bindIplocExtras() {
        var list = document.getElementById('ipLocExtraList');
        var hidden = document.getElementById('ipLocExtrasJson');
        var addBtn = document.getElementById('ipLocExtraAdd');
        var form = document.getElementById('iplocForm');
        if (!list || !hidden || !addBtn || !form) {
            return;
        }

        function readRows() {
            try {
                var raw = JSON.parse(hidden.value || '[]');
                return Array.isArray(raw) ? raw : [];
            } catch (e) {
                return [];
            }
        }

        function writeRows(rows) {
            hidden.value = JSON.stringify(rows);
        }

        function render() {
            var rows = readRows();
            list.innerHTML = '';
            rows.forEach(function (row, idx) {
                var wrap = document.createElement('div');
                wrap.className = 'vs-form-row vs-iploc-extra-row';
                wrap.innerHTML = ''
                    + '<div class="vs-iploc-extra-grid">'
                    + '<input type="text" class="vs-input" data-k="name" placeholder="参数名" value="">'
                    + '<input type="text" class="vs-input" data-k="value" placeholder="参数值" value="">'
                    + '<select class="vs-input" data-k="via"><option value="query">Query</option><option value="header">Header</option></select>'
                    + '<button type="button" class="vs-btn vs-btn--ghost" data-del="' + idx + '">删除</button>'
                    + '</div>';
                var nameEl = wrap.querySelector('[data-k="name"]');
                var valEl = wrap.querySelector('[data-k="value"]');
                var viaEl = wrap.querySelector('[data-k="via"]');
                if (nameEl) nameEl.value = row.name || '';
                if (valEl) valEl.value = row.value || '';
                if (viaEl) viaEl.value = row.via === 'header' ? 'header' : 'query';
                list.appendChild(wrap);
            });
        }

        function syncFromDom() {
            var rows = [];
            list.querySelectorAll('.vs-iploc-extra-row').forEach(function (wrap) {
                var name = (wrap.querySelector('[data-k="name"]') || {}).value || '';
                var value = (wrap.querySelector('[data-k="value"]') || {}).value || '';
                var via = (wrap.querySelector('[data-k="via"]') || {}).value || 'query';
                name = String(name).trim();
                if (!name) return;
                rows.push({ name: name, value: String(value), via: via === 'header' ? 'header' : 'query' });
            });
            writeRows(rows);
        }

        addBtn.addEventListener('click', function () {
            syncFromDom();
            var rows = readRows();
            rows.push({ name: '', value: '', via: 'query' });
            writeRows(rows);
            render();
        });

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-del]');
            if (!btn) return;
            syncFromDom();
            var idx = parseInt(btn.getAttribute('data-del'), 10);
            var rows = readRows();
            if (!isNaN(idx)) {
                rows.splice(idx, 1);
                writeRows(rows);
                render();
            }
        });

        form.addEventListener('submit', function () {
            syncFromDom();
        }, true);

        render();
    }

    function syncPanelProviderHidden() {
        var sel = document.getElementById('panelmonitor_provider');
        var hidden = document.getElementById('panelmonitor_provider_hidden');
        if (sel && hidden) {
            hidden.value = String(sel.value || '');
        }
    }

    function bindPanelMonitorTest() {
        var btn = document.getElementById('panelMonitorTestBtn');
        var form = document.getElementById('dashboardForm');
        var providerSel = document.getElementById('panelmonitor_provider');
        if (providerSel) {
            providerSel.addEventListener('change', syncPanelProviderHidden);
            syncPanelProviderHidden();
        }
        if (form) {
            form.addEventListener('submit', function () {
                syncPanelProviderHidden();
            }, true);
        }
        if (!btn || !form || !window.VS || !window.VS.postForm) {
            return;
        }
        btn.addEventListener('click', function () {
            syncPanelProviderHidden();
            var provider = document.getElementById('panelmonitor_provider');
            var baseurl = document.getElementById('panelmonitor_baseurl');
            var apikey = document.getElementById('panelmonitor_apikey');
            var hidden = document.getElementById('panelmonitor_provider_hidden');
            var providerVal = hidden ? String(hidden.value || '').trim()
                : (provider ? String(provider.value || '').trim() : '');
            if (!providerVal) {
                showFlash('请选择面板类型', 'error');
                return;
            }
            if (!baseurl || !String(baseurl.value || '').trim()) {
                showFlash('请填写面板地址', 'error');
                return;
            }
            var keyTyped = apikey && String(apikey.value || '').trim();
            var ph = apikey ? String(apikey.getAttribute('placeholder') || '') : '';
            if (!keyTyped && ph.indexOf('已保存') < 0) {
                showFlash('请填写接口密钥', 'error');
                return;
            }
            btn.disabled = true;
            showFlash('正在测试面板连接…', 'info');
            var fd = new FormData(form);
            fd.set('action', 'test_panelmonitor');
            fd.set('persist', '1');
            fd.set('panelmonitor_provider', providerVal);
            fd.set('panelmonitor_enabled', '1');
            var enabled = form.querySelector('input[type="checkbox"][name="panelmonitor_enabled"]');
            if (enabled) {
                enabled.checked = true;
            }
            window.VS.postForm(fd, window.location.href)
                .then(function (data) {
                    if (data && data.code === 1) {
                        showFlash(data.msg || '连接成功（已保存并启用）', 'success');
                        if (apikey) {
                            apikey.value = '';
                            apikey.setAttribute('placeholder', '已保存，留空则保持不变');
                        }
                        if (enabled) {
                            enabled.checked = true;
                        }
                        var enHidden = form.querySelector('input[type="hidden"][name="panelmonitor_enabled"]');
                        if (enHidden) {
                            enHidden.value = '0';
                        }
                    } else {
                        showFlash((data && data.msg) || '连接失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    function bindCopyButtons() {
        document.querySelectorAll('[data-copy-from]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-copy-from') || '';
                var input = id ? document.getElementById(id) : null;
                var text = input ? String(input.value || '').trim() : '';
                if (!text) {
                    showFlash('没有可复制的内容', 'error');
                    return;
                }
                function ok() {
                    showFlash('已复制到剪贴板', 'success');
                }
                function fail() {
                    if (input) {
                        input.focus();
                        input.select();
                    }
                    showFlash('复制失败，请手动选中复制', 'error');
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(ok).catch(fail);
                } else if (input) {
                    input.select();
                    try {
                        if (document.execCommand('copy')) {
                            ok();
                        } else {
                            fail();
                        }
                    } catch (e) {
                        fail();
                    }
                } else {
                    fail();
                }
            });
        });
    }

    function bindRedisSettingsForm() {
        var form = document.getElementById('redisSettingsForm');
        if (!form) {
            return;
        }
        form.setAttribute('data-ajax-bound', '1');
        var forceEl = document.getElementById('redisPrefixForce');
        var conflictEl = document.getElementById('redisPrefixConflict');
        var checkBtn = document.getElementById('redisPrefixCheckBtn');
        var testBtn = document.getElementById('redisTestBtn');
        var input = document.getElementById('settings_redis_prefix');
        var pwdInput = document.getElementById('settings_redis_password');
        var clearEl = document.getElementById('settings_redis_clear_password');
        var hostEl = document.getElementById('settings_redis_host');
        var portEl = document.getElementById('settings_redis_port');
        var dbEl = document.getElementById('settings_redis_database');

        function setConflictTip(text, isBad) {
            if (!conflictEl) {
                return;
            }
            if (!text) {
                conflictEl.hidden = true;
                conflictEl.textContent = '';
                return;
            }
            conflictEl.hidden = false;
            conflictEl.textContent = text;
            conflictEl.style.color = isBad ? '#b91c1c' : '';
        }

        function appendConnFields(fd) {
            fd.append('redis_host', hostEl ? hostEl.value : '127.0.0.1');
            fd.append('redis_port', portEl ? portEl.value : '6379');
            fd.append('redis_database', dbEl ? dbEl.value : '0');
            fd.append('redis_password', pwdInput ? pwdInput.value : '');
            if (clearEl && clearEl.checked) {
                fd.append('clear_password', '1');
            }
        }

        function runCheck() {
            var fd = new FormData();
            fd.append('action', 'check_redis_prefix');
            fd.append('redis_prefix', input ? input.value : '');
            return window.VS.postForm(fd).then(function (data) {
                if (data.code === 1) {
                    setConflictTip(data.msg || '', !!data.conflict);
                    return data;
                }
                setConflictTip(data.msg || '检测失败', true);
                return data;
            });
        }

        function runTest() {
            var fd = new FormData();
            fd.append('action', 'test_redis_connection');
            appendConnFields(fd);
            return window.VS.postForm(fd);
        }

        if (checkBtn) {
            checkBtn.addEventListener('click', function () {
                checkBtn.disabled = true;
                runCheck().finally(function () {
                    checkBtn.disabled = false;
                });
            });
        }

        if (testBtn) {
            testBtn.addEventListener('click', function () {
                testBtn.disabled = true;
                runTest().then(function (data) {
                    if (data.code === 1) {
                        showFlash(data.msg || 'Redis 连接成功', 'success');
                    } else {
                        showFlash(data.msg || 'Redis 连接失败', 'error');
                    }
                }).catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                }).finally(function () {
                    testBtn.disabled = false;
                });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (forceEl) {
                forceEl.value = '0';
            }
            postForm(form).then(function (data) {
                if (data.code === 1) {
                    showFlash(data.msg || '已保存', 'success');
                    if (input && data.prefix) {
                        input.value = data.prefix;
                    }
                    if (pwdInput) {
                        pwdInput.value = '';
                        pwdInput.placeholder = data.has_password
                            ? '已设置，留空不修改'
                            : '无密码请留空';
                    }
                    if (clearEl) {
                        clearEl.checked = false;
                    }
                    if (hostEl && data.host) {
                        hostEl.value = data.host;
                    }
                    if (portEl && data.port) {
                        portEl.value = String(data.port);
                    }
                    if (dbEl && typeof data.database !== 'undefined') {
                        dbEl.value = String(data.database);
                    }
                    setConflictTip('', false);
                    return;
                }
                if (data.need_confirm) {
                    var ask = window.VsModal && window.VsModal.confirm
                        ? window.VsModal.confirm(data.msg + '\n\n仍要强制使用此前缀吗？', '前缀冲突确认', {
                            confirmText: '仍要保存',
                            danger: true
                        })
                        : Promise.resolve(window.confirm(data.msg + '\n仍要强制保存吗？'));
                    ask.then(function (ok) {
                        if (!ok) {
                            showFlash(data.msg || '已取消', 'error');
                            return;
                        }
                        if (forceEl) {
                            forceEl.value = '1';
                        }
                        postForm(form).then(function (data2) {
                            if (forceEl) {
                                forceEl.value = '0';
                            }
                            if (data2.code === 1) {
                                showFlash(data2.msg || '已保存', 'success');
                                if (input && data2.prefix) {
                                    input.value = data2.prefix;
                                }
                                if (pwdInput) {
                                    pwdInput.value = '';
                                    pwdInput.placeholder = data2.has_password
                                        ? '已设置，留空不修改'
                                        : '无密码请留空';
                                }
                                if (clearEl) {
                                    clearEl.checked = false;
                                }
                                setConflictTip('', false);
                            } else {
                                showFlash(data2.msg || '保存失败', 'error');
                            }
                        }).catch(function () {
                            if (forceEl) {
                                forceEl.value = '0';
                            }
                            showFlash('网络异常，请稍后重试', 'error');
                        });
                    });
                    return;
                }
                showFlash(data.msg || '保存失败', 'error');
            }).catch(function () {
                showFlash('网络异常，请稍后重试', 'error');
            });
        });
    }

    /**
     * 注册策略：总闸「开放注册」= 全选普通用户 + 开发者
     */
    function bindRegisterRoleSelectAll() {
        var master = document.getElementById('registerEnabledMaster');
        var allowUser = document.getElementById('registerAllowUser');
        var allowDev = document.getElementById('registerAllowDeveloper');
        if (!master || !allowUser || !allowDev) {
            return;
        }

        var syncing = false;

        function syncMasterFromChildren() {
            if (syncing) {
                return;
            }
            syncing = true;
            master.checked = allowUser.checked && allowDev.checked;
            syncing = false;
        }

        master.addEventListener('change', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            allowUser.checked = master.checked;
            allowDev.checked = master.checked;
            syncing = false;
        });

        allowUser.addEventListener('change', syncMasterFromChildren);
        allowDev.addEventListener('change', syncMasterFromChildren);
        syncMasterFromChildren();
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAccordions();
        bindRedisSettingsForm();
        bindSecretFieldToggles();
        bindSystemApiKey();
        bindAiProvider();
        bindAiListModels();
        bindAiModelSearch();
        bindOutboundUaPreview();
        bindSystemApiFolds();
        bindMailAccounts();
        bindAiTest();
        bindIplocTest();
        bindIplocExtras();
        bindPanelMonitorTest();
        bindCopyButtons();
        bindRegisterRoleSelectAll();

        var siteExtra = document.getElementById('siteExtraForm');
        if (siteExtra) {
            siteExtra.setAttribute('data-ajax-bound', '1');
            siteExtra.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(siteExtra);
                var submitBtn = siteExtra.querySelector('[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                window.VS.postForm(fd)
                    .then(function (data) {
                        if (data && data.code === 1) {
                            showFlash(data.msg || '操作成功', 'success');
                        } else {
                            showFlash((data && data.msg) || '操作失败', 'error');
                        }
                    })
                    .catch(function () {
                        showFlash('网络异常，请稍后重试', 'error');
                    })
                    .finally(function () {
                        if (submitBtn) submitBtn.disabled = false;
                    });
            });
        }

        document.querySelectorAll('form[data-ajax="1"]').forEach(function (form) {
            bindAjaxForm(form);
        });

        initAggPills();
    });

    function initAggPills() {
        var root = document.getElementById('aggPillRoot');
        var form = document.getElementById('oauthForm');
        var hidden = document.getElementById('aggItemsJson');
        if (!root || !form || !hidden) {
            return;
        }

        var items = [];
        try {
            items = JSON.parse(root.getAttribute('data-items') || '[]');
        } catch (e) {
            items = [];
        }
        if (!Array.isArray(items)) {
            items = [];
        }

        var builtinEl = document.getElementById('aggPillsBuiltin');
        var customEl = document.getElementById('aggPillsCustom');
        var editSlot = document.getElementById('aggEditSlot');
        var editName = document.getElementById('aggEditName');
        var editType = document.getElementById('aggEditType');
        var editApply = document.getElementById('aggEditApplyBtn');
        var editingId = '';

        var gearSvg = '<svg class="vs-agg-pill__gear-icon" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.14 12.94c.04-.31.06-.63.06-.94s-.02-.63-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.03 7.03 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 13.9 2h-3.8a.5.5 0 0 0-.49.42l-.36 2.54c-.59.24-1.13.55-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.71 8.48a.5.5 0 0 0 .12.64l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94L2.83 14.58a.5.5 0 0 0-.12.64l1.92 3.32c.14.24.43.34.68.22l2.39-.96c.5.39 1.04.7 1.63.94l.36 2.54c.05.24.25.42.49.42h3.8c.24 0 .44-.18.49-.42l.36-2.54c.59-.24 1.13-.55 1.63-.94l2.39.96c.25.1.54 0 .68-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58zM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7z"/></svg>';

        function syncHidden() {
            hidden.value = JSON.stringify(items);
        }

        function findItem(id) {
            for (var i = 0; i < items.length; i++) {
                if (items[i] && items[i].id === id) {
                    return items[i];
                }
            }
            return null;
        }

        function closeEdit() {
            editingId = '';
            if (editSlot) {
                editSlot.hidden = true;
            }
        }

        function openEdit(id) {
            var item = findItem(id);
            if (!item || !editSlot || !editName || !editType) {
                return;
            }
            editingId = id;
            editName.textContent = item.name || id;
            editType.value = item.type || '';
            editSlot.hidden = false;
            editType.focus();
        }

        function renderPills() {
            if (!builtinEl || !customEl) {
                return;
            }
            builtinEl.innerHTML = '';
            customEl.innerHTML = '';
            items.forEach(function (item) {
                if (!item || !item.id) {
                    return;
                }
                var kind = item.kind === 'custom' ? 'custom' : 'builtin';
                var pill = document.createElement('div');
                pill.className = 'vs-agg-pill' + (item.on ? ' is-on' : '') + (kind === 'custom' ? ' vs-agg-pill--custom' : '');
                pill.setAttribute('data-id', item.id);

                var main = document.createElement('button');
                main.type = 'button';
                main.className = 'vs-agg-pill__main';
                main.setAttribute('aria-pressed', item.on ? 'true' : 'false');
                main.title = (item.on ? '停用' : '启用') + ' ' + (item.name || '');

                var iconWrap = document.createElement('span');
                iconWrap.className = 'vs-agg-pill__icon';
                if (item.icon_url) {
                    var img = document.createElement('img');
                    img.src = item.icon_url;
                    img.alt = '';
                    img.width = 16;
                    img.height = 16;
                    iconWrap.appendChild(img);
                }
                main.appendChild(iconWrap);

                var label = document.createElement('span');
                label.className = 'vs-agg-pill__name';
                label.textContent = item.name || item.id;
                main.appendChild(label);

                main.addEventListener('click', function () {
                    item.on = !item.on;
                    syncHidden();
                    renderPills();
                });

                var gear = document.createElement('button');
                gear.type = 'button';
                gear.className = 'vs-agg-pill__gear';
                gear.setAttribute('aria-label', '修改接口对应值');
                gear.innerHTML = gearSvg;
                gear.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (editingId === item.id && editSlot && !editSlot.hidden) {
                        closeEdit();
                    } else {
                        openEdit(item.id);
                    }
                });

                pill.appendChild(main);
                pill.appendChild(gear);
                (kind === 'custom' ? customEl : builtinEl).appendChild(pill);
            });
            syncHidden();
        }

        if (editApply && editType) {
            editApply.addEventListener('click', function () {
                var item = findItem(editingId);
                if (!item) {
                    return;
                }
                var type = String(editType.value || '').trim();
                if (!/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(type)) {
                    showFlash('接口对应值须为字母开头的英文/数字/下划线', 'error');
                    return;
                }
                item.type = type;
                syncHidden();
                showFlash('已应用到「' + (item.name || item.id) + '」，请再点底部「保存 OAuth 设置」', 'success');
                closeEdit();
            });
        }

        var addBtn = document.getElementById('aggCustomAddBtn');
        var nameEl = document.getElementById('aggCustomName');
        var typeEl = document.getElementById('aggCustomType');
        var iconEl = document.getElementById('aggCustomIcon');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var name = String(nameEl && nameEl.value || '').trim();
                var type = String(typeEl && typeEl.value || '').trim();
                var icon = String(iconEl && iconEl.value || '').trim();
                if (name === '') {
                    showFlash('请填写自定义名称', 'error');
                    return;
                }
                if (!/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(type)) {
                    showFlash('接口对应值须为字母开头的英文/数字/下划线', 'error');
                    return;
                }
                if (icon !== '' && !/^https?:\/\//i.test(icon)) {
                    showFlash('图标请使用 http(s) 链接', 'error');
                    return;
                }
                var id = 'c' + String(Date.now()).slice(-8);
                items.push({
                    id: id,
                    name: name.slice(0, 32),
                    type: type,
                    icon: icon,
                    icon_url: icon,
                    on: true,
                    kind: 'custom'
                });
                if (nameEl) nameEl.value = '';
                if (typeEl) typeEl.value = '';
                if (iconEl) iconEl.value = '';
                syncHidden();
                renderPills();
                showFlash('已添加自定义方式，请再点底部保存', 'success');
            });
        }

        form.addEventListener('submit', function () {
            syncHidden();
        });

        renderPills();
    }
})();
