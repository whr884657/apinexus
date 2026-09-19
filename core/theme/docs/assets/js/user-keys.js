/**
 * 用户中心 · 令牌管理
 */
(function () {
    var page = document.getElementById('userTokenPage');
    if (!page || !window.VS) {
        return;
    }

    var listEl = document.getElementById('userTokenList');
    var emptyEl = document.getElementById('userTokenEmpty');
    var footerEl = document.getElementById('userTokenFooter');
    var statsEl = document.getElementById('userTokenStats');
    var addBtn = document.getElementById('userTokenAddBtn');
    var formOverlay = document.getElementById('userTokenFormOverlay');
    var form = document.getElementById('userTokenForm');
    var formTitle = document.getElementById('userTokenFormTitle');
    var formId = document.getElementById('userTokenFormId');
    var remarkInput = document.getElementById('userTokenFormRemark');
    var quotaInput = document.getElementById('userTokenFormQuota');
    var expireInput = document.getElementById('userTokenFormExpire');
    var expireBtn = document.getElementById('userTokenFormExpireBtn');
    var expirePicker = document.getElementById('userTokenDatetimePicker');
    var expireHour = document.getElementById('userTokenExpireHour');
    var expireMinute = document.getElementById('userTokenExpireMinute');
    var expireDays = expirePicker ? expirePicker.querySelector('[data-dt-days]') : null;
    var expireTitle = expirePicker ? expirePicker.querySelector('[data-dt-title]') : null;
    var fallbackInput = document.getElementById('userTokenFormFallback');
    var submitBtn = document.getElementById('userTokenFormSubmitBtn');
    var formMode = 'create';
    var maxTokens = parseInt(page.getAttribute('data-token-max') || '3', 10) || 3;
    var dtView = { y: 0, m: 0 };
    var dtSelected = null;

    if (formOverlay && formOverlay.parentNode !== document.body) {
        document.body.appendChild(formOverlay);
    }
    if (expirePicker && expirePicker.parentNode !== document.body) {
        document.body.appendChild(expirePicker);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /** 密钥累计消耗展示（与服务端 PayConfig::fmtPoints 观感接近） */
    function fmtSpent(v) {
        var n = parseFloat(v);
        if (isNaN(n) || n < 0) n = 0;
        if (Math.abs(n - Math.round(n)) < 0.00005) {
            return String(Math.round(n));
        }
        return (Math.round(n * 10000) / 10000).toString();
    }

    function fmtQuotaLabel(token) {
        var quota = parseFloat(token && token.quota != null ? token.quota : 0);
        if (isNaN(quota) || quota <= 0) {
            return '不限';
        }
        var left = token.quotaleft != null ? parseFloat(token.quotaleft) : (quota - parseFloat(token.quotaused || 0));
        if (isNaN(left) || left < 0) left = 0;
        return fmtSpent(left) + '/' + fmtSpent(quota);
    }

    function expireLabelOf(token) {
        if (token && token.expire_label) {
            return String(token.expire_label);
        }
        var raw = token && token.expiretime ? String(token.expiretime) : '';
        return raw ? raw : '永不过期';
    }

    function shortTime(raw) {
        var s = String(raw || '');
        if (s.length >= 16) {
            return s.slice(0, 16);
        }
        return s || '—';
    }

    function pad2(n) {
        n = parseInt(n, 10);
        if (isNaN(n) || n < 0) n = 0;
        return (n < 10 ? '0' : '') + n;
    }

    /** 服务端 Y-m-d H:i:s → 隐藏域 Y-m-d H:i */
    function normalizeExpireValue(raw) {
        var s = String(raw || '').trim().replace('T', ' ');
        if (!s) {
            return '';
        }
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(s)) {
            return s.slice(0, 16);
        }
        return '';
    }

    function syncExpireTrigger() {
        if (!expireBtn) {
            return;
        }
        var v = expireInput ? String(expireInput.value || '').trim() : '';
        expireBtn.textContent = v ? v : '永不过期';
    }

    function setSelectValue(sel, val) {
        if (!sel) {
            return;
        }
        sel.value = pad2(val);
        if (window.VSPick && typeof window.VSPick.refresh === 'function') {
            window.VSPick.refresh(sel);
        } else {
            var ev = document.createEvent('HTMLEvents');
            ev.initEvent('change', true, false);
            sel.dispatchEvent(ev);
        }
    }

    function closeExpirePanel() {
        if (!expirePicker) {
            return;
        }
        expirePicker.classList.remove('is-visible');
        expirePicker.classList.remove('is-open');
        expirePicker.hidden = true;
        expirePicker.setAttribute('aria-hidden', 'true');
        if (expireBtn) {
            expireBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function renderExpireDays() {
        if (!expireDays) {
            return;
        }
        var y = dtView.y;
        var m = dtView.m;
        if (expireTitle) {
            expireTitle.textContent = y + '年' + (m + 1) + '月';
        }
        var first = new Date(y, m, 1);
        var startPad = first.getDay();
        var daysInMonth = new Date(y, m + 1, 0).getDate();
        var html = '';
        var i;
        for (i = 0; i < startPad; i++) {
            html += '<span class="vs-datetime__day is-empty"></span>';
        }
        for (i = 1; i <= daysInMonth; i++) {
            var isSel = dtSelected
                && dtSelected.y === y
                && dtSelected.m === m
                && dtSelected.d === i;
            html += '<button type="button" class="vs-datetime__day' + (isSel ? ' is-selected' : '') + '" data-dt-day="'
                + i + '">' + i + '</button>';
        }
        expireDays.innerHTML = html;
    }

    function openExpirePanel() {
        if (!expirePicker || !expireBtn) {
            return;
        }
        var now = new Date();
        var raw = expireInput ? normalizeExpireValue(expireInput.value) : '';
        var y;
        var m;
        var d;
        var hh = now.getHours();
        var mm = now.getMinutes();
        if (raw) {
            var parts = raw.split(/[\sT]/);
            var dp = parts[0].split('-');
            var tp = (parts[1] || '00:00').split(':');
            y = parseInt(dp[0], 10);
            m = parseInt(dp[1], 10) - 1;
            d = parseInt(dp[2], 10);
            hh = parseInt(tp[0], 10);
            mm = parseInt(tp[1], 10);
            if (isNaN(y) || isNaN(m) || isNaN(d)) {
                y = now.getFullYear();
                m = now.getMonth();
                d = now.getDate();
            }
            dtSelected = { y: y, m: m, d: d };
        } else {
            y = now.getFullYear();
            m = now.getMonth();
            d = now.getDate();
            dtSelected = { y: y, m: m, d: d };
        }
        dtView = { y: y, m: m };
        setSelectValue(expireHour, isNaN(hh) ? 0 : hh);
        setSelectValue(expireMinute, isNaN(mm) ? 0 : mm);
        renderExpireDays();
        if (expirePicker.parentNode !== document.body) {
            document.body.appendChild(expirePicker);
        }
        expirePicker.classList.add('vs-nested-picker--viewport');
        expirePicker.hidden = false;
        expirePicker.setAttribute('aria-hidden', 'false');
        expirePicker.classList.add('is-open');
        requestAnimationFrame(function () {
            expirePicker.classList.add('is-visible');
        });
        expireBtn.setAttribute('aria-expanded', 'true');
        if (window.VSPick && typeof window.VSPick.init === 'function') {
            window.VSPick.init(expirePicker);
        }
    }

    function applyExpireSelection() {
        if (!dtSelected) {
            return;
        }
        var hh = expireHour ? expireHour.value : '00';
        var mm = expireMinute ? expireMinute.value : '00';
        var val = dtSelected.y + '-' + pad2(dtSelected.m + 1) + '-' + pad2(dtSelected.d)
            + ' ' + pad2(hh) + ':' + pad2(mm);
        if (expireInput) {
            expireInput.value = val;
        }
        syncExpireTrigger();
        closeExpirePanel();
    }

    function clearExpireSelection() {
        if (expireInput) {
            expireInput.value = '';
        }
        dtSelected = null;
        syncExpireTrigger();
        closeExpirePanel();
    }

    function setExpireFromRaw(raw) {
        if (expireInput) {
            expireInput.value = normalizeExpireValue(raw);
        }
        syncExpireTrigger();
        closeExpirePanel();
    }

    function postAction(action, payload) {
        var fd = new FormData();
        fd.append('action', action);
        if (payload) {
            Object.keys(payload).forEach(function (key) {
                fd.append(key, payload[key]);
            });
        }
        return window.VS.postForm(fd);
    }

    function openOverlay() {
        if (!formOverlay) {
            return;
        }
        formOverlay.hidden = false;
        formOverlay.setAttribute('aria-hidden', 'false');
        formOverlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
        if (remarkInput) {
            setTimeout(function () {
                remarkInput.focus();
            }, 50);
        }
    }

    function closeOverlay() {
        if (!formOverlay) {
            return;
        }
        closeExpirePanel();
        formOverlay.hidden = true;
        formOverlay.setAttribute('aria-hidden', 'true');
        formOverlay.classList.remove('is-open');
        document.body.classList.remove('is-overlay-open');
    }

    function tokenCount() {
        return listEl ? listEl.querySelectorAll('.vs-token-row').length : 0;
    }

    function applyMaxFromResponse(data) {
        if (!data || data.max == null) {
            return;
        }
        var n = parseInt(String(data.max), 10);
        if (!isFinite(n) || n < 1) {
            return;
        }
        maxTokens = n;
        page.setAttribute('data-token-max', String(n));
    }
    function syncEmptyAndStats() {
        var count = tokenCount();
        page.setAttribute('data-token-count', String(count));
        if (emptyEl) {
            emptyEl.hidden = count > 0;
        }
        if (listEl) {
            listEl.hidden = count === 0;
        }
        if (footerEl) {
            footerEl.hidden = count === 0;
        }
        if (statsEl) {
            statsEl.textContent = '共 ' + count + ' 个令牌（上限 ' + maxTokens + '）';
        }
        if (addBtn) {
            addBtn.disabled = count >= maxTokens;
            if (count >= maxTokens) {
                addBtn.setAttribute('title', '已达上限');
            } else {
                addBtn.removeAttribute('title');
            }
        }
    }

    function listBody() {
        if (!listEl) {
            return null;
        }
        return listEl.querySelector('.vs-api-list-table__body') || listEl;
    }

    function buildRowHtml(token) {
        var id = parseInt(token.id, 10) || 0;
        var enabled = parseInt(token.status, 10) === 1;
        var statusClass = enabled ? 'is-enabled' : 'is-disabled';
        var quota = parseFloat(token.quota != null ? token.quota : 0) || 0;
        var quotaused = parseFloat(token.quotaused != null ? token.quotaused : 0) || 0;
        var quotafallback = parseInt(token.quotafallback, 10) === 1 ? 1 : 0;
        var expireRaw = token.expiretime != null ? String(token.expiretime) : '';
        var html = '';
        html += '<div class="vs-api-item vs-token-row' + (enabled ? '' : ' is-token-disabled') + '"'
            + ' data-token-row="' + id + '" data-token-status="' + (enabled ? '1' : '0') + '"'
            + ' data-quota="' + escapeHtml(String(quota)) + '"'
            + ' data-quotaused="' + escapeHtml(String(quotaused)) + '"'
            + ' data-quotafallback="' + quotafallback + '"'
            + ' data-expiretime="' + escapeHtml(expireRaw) + '">';
        html += '<div class="vs-api-item__icon vs-token-row__icon" aria-hidden="true"><span class="vs-token-row__icon-mark">SK</span></div>';
        html += '<div class="vs-api-item__title">';
        html += '<span class="vs-api-item__name" data-field="remark">' + escapeHtml(token.remark || '') + '</span>';
        html += '<span class="vs-token-row__created" data-field="createtime" title="创建时间">'
            + escapeHtml(shortTime(token.createtime)) + '</span>';
        html += '</div>';
        html += '<div class="vs-api-item__endpoint vs-token-row__secret">';
        html += '<code class="vs-token-row__code uc-token-secret" data-field="secret" title="悬停查看明文">'
            + escapeHtml(token.secret || '') + '</code>';
        html += '<button type="button" class="vs-token-row__copy vs-key-copy" data-copy="'
            + escapeHtml(token.secret || '')
            + '" title="复制" aria-label="复制令牌">'
            + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
            + '</button>';
        html += '</div>';
        html += '<div class="vs-token-row__quota" data-field="quota_label" title="配额（剩余/上限）">'
            + '<span class="vs-token-row__lbl">配额</span> ' + escapeHtml(fmtQuotaLabel(token)) + '</div>';
        html += '<div class="vs-token-row__expire" data-field="expire_label" title="有效期">'
            + '<span class="vs-token-row__lbl">有效期</span> ' + escapeHtml(expireLabelOf(token)) + '</div>';
        html += '<div class="vs-api-item__calls vs-token-row__calls" title="调用次数">调用 <strong data-field="calls">'
            + (parseInt(token.calls, 10) || 0) + '</strong></div>';
        html += '<div class="vs-api-item__spent vs-token-row__spent" title="累计消耗积分">消耗 <strong data-field="pointsspent">'
            + escapeHtml(fmtSpent(token.pointsspent)) + '</strong></div>';
        html += '<div class="vs-api-item__tags">';
        html += '<span class="vs-api-tag vs-api-tag--status ' + statusClass + '" data-field="status_label">'
            + escapeHtml(token.status_label || '') + '</span>';
        if (quota > 0 && quotafallback === 1) {
            html += '<span class="vs-api-tag vs-api-tag--fallback" data-field="fallback_label" title="配额用尽后改用账户总积分">回落</span>';
        }
        html += '</div>';
        html += '<div class="vs-api-item__actions vs-token-row__actions">';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-copy-btn vs-key-copy" data-copy="'
            + escapeHtml(token.secret || '') + '">复制</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-edit" data-token-id="' + id + '">编辑</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-reset" data-token-id="' + id + '">重置</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-token-toggle" data-token-id="' + id
            + '" data-status="' + (enabled ? '0' : '1') + '">' + (enabled ? '禁用' : '启用') + '</button>';
        html += '<button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-token-delete" data-token-id="'
            + id + '">删除</button>';
        html += '</div></div>';
        return html;
    }

    function upsertRow(token) {
        var body = listBody();
        if (!body || !token) {
            return;
        }
        var id = String(token.id);
        var existing = body.querySelector('.vs-token-row[data-token-row="' + id + '"]');
        var wrap = document.createElement('div');
        wrap.innerHTML = buildRowHtml(token);
        var node = wrap.firstChild;
        if (existing && node) {
            existing.parentNode.replaceChild(node, existing);
        } else if (node) {
            body.insertBefore(node, body.firstChild);
        }
        syncEmptyAndStats();
    }

    function resetFormFields() {
        if (remarkInput) {
            remarkInput.value = '';
        }
        if (quotaInput) {
            quotaInput.value = '0';
        }
        setExpireFromRaw('');
        if (fallbackInput) {
            fallbackInput.checked = false;
        }
    }

    function openCreate() {
        if (tokenCount() >= maxTokens) {
            window.VS.showMessage('每个账号最多 ' + maxTokens + ' 个令牌', 'error');
            return;
        }
        formMode = 'create';
        if (formTitle) {
            formTitle.textContent = '添加令牌';
        }
        if (formId) {
            formId.value = '';
        }
        resetFormFields();
        if (submitBtn) {
            submitBtn.textContent = '确定';
        }
        openOverlay();
    }

    function openEdit(tokenId) {
        var row = listEl && listEl.querySelector('.vs-token-row[data-token-row="' + tokenId + '"]');
        if (!row) {
            return;
        }
        var remarkEl = row.querySelector('[data-field="remark"]');
        formMode = 'update';
        if (formTitle) {
            formTitle.textContent = '编辑令牌';
        }
        if (formId) {
            formId.value = String(tokenId);
        }
        if (remarkInput) {
            remarkInput.value = remarkEl ? remarkEl.textContent : '';
        }
        if (quotaInput) {
            quotaInput.value = row.getAttribute('data-quota') || '0';
        }
        setExpireFromRaw(row.getAttribute('data-expiretime') || '');
        if (fallbackInput) {
            fallbackInput.checked = row.getAttribute('data-quotafallback') === '1';
        }
        if (submitBtn) {
            submitBtn.textContent = '保存';
        }
        openOverlay();
    }

    document.addEventListener('click', function (e) {
        if (!expirePicker || expirePicker.hidden) {
            return;
        }
        if (expirePicker.contains(e.target) || (expireBtn && expireBtn.contains(e.target))) {
            return;
        }
        closeExpirePanel();
    });

    if (addBtn) {
        addBtn.addEventListener('click', openCreate);
    }

    if (expireBtn) {
        expireBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (expirePicker && !expirePicker.hidden) {
                closeExpirePanel();
            } else {
                openExpirePanel();
            }
        });
    }

    if (expirePicker) {
        expirePicker.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-dt-close') === '1') {
                e.preventDefault();
                closeExpirePanel();
                return;
            }
            var nav = e.target.closest('[data-dt-nav]');
            if (nav) {
                e.preventDefault();
                var step = parseInt(nav.getAttribute('data-dt-nav'), 10) || 0;
                dtView.m += step;
                if (dtView.m < 0) {
                    dtView.m = 11;
                    dtView.y -= 1;
                } else if (dtView.m > 11) {
                    dtView.m = 0;
                    dtView.y += 1;
                }
                renderExpireDays();
                return;
            }
            var dayBtn = e.target.closest('[data-dt-day]');
            if (dayBtn) {
                e.preventDefault();
                dtSelected = {
                    y: dtView.y,
                    m: dtView.m,
                    d: parseInt(dayBtn.getAttribute('data-dt-day'), 10) || 1
                };
                renderExpireDays();
                return;
            }
            if (e.target.closest('[data-dt-clear]')) {
                e.preventDefault();
                clearExpireSelection();
                return;
            }
            if (e.target.closest('[data-dt-ok]')) {
                e.preventDefault();
                applyExpireSelection();
            }
        });
    }

    if (formOverlay) {
        formOverlay.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-overlay-close') === '1') {
                closeOverlay();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (expirePicker && !expirePicker.hidden) {
            closeExpirePanel();
            return;
        }
        if (formOverlay && formOverlay.classList.contains('is-open')) {
            closeOverlay();
        }
    });

    function copySecret(text) {
        var value = String(text || '');
        if (!value) {
            return;
        }
        function ok() {
            window.VS.showMessage('已复制令牌', 'success');
        }
        function fail() {
            window.VS.showMessage('复制失败，请手动选择', 'error');
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(ok).catch(fail);
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = value;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            if (document.execCommand('copy')) {
                ok();
            } else {
                fail();
            }
        } catch (err) {
            fail();
        }
        document.body.removeChild(ta);
    }

    page.addEventListener('click', function (e) {
        var copyEl = e.target.closest('.vs-key-copy');
        if (copyEl && page.contains(copyEl)) {
            e.preventDefault();
            copySecret(copyEl.getAttribute('data-copy') || copyEl.textContent);
            return;
        }

        var editBtn = e.target.closest('.vs-token-edit');
        if (editBtn && page.contains(editBtn)) {
            openEdit(editBtn.getAttribute('data-token-id'));
            return;
        }

        var resetBtn = e.target.closest('.vs-token-reset');
        if (resetBtn && page.contains(resetBtn)) {
            var resetId = resetBtn.getAttribute('data-token-id');
            var resetConfirm = window.VsModal && window.VsModal.confirm
                ? window.VsModal.confirm('重置后旧密钥立即失效，确定继续？', '重置令牌')
                : Promise.resolve(window.confirm('确定重置该令牌？'));
            resetConfirm.then(function (ok) {
                if (!ok) {
                    return;
                }
                return postAction('reset', { token_id: resetId }).then(function (data) {
                    if (!data || Number(data.code) !== 1) {
                        window.VS.showMessage((data && data.msg) || '重置失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已重置', 'success');
                    if (data.token) {
                        upsertRow(data.token);
                    }
                });
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        var toggleBtn = e.target.closest('.vs-token-toggle');
        if (toggleBtn && page.contains(toggleBtn)) {
            var toggleId = toggleBtn.getAttribute('data-token-id');
            var nextStatus = toggleBtn.getAttribute('data-status') || '0';
            postAction('set_status', { token_id: toggleId, status: nextStatus }).then(function (data) {
                if (!data || Number(data.code) !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已更新', 'success');
                if (data.token) {
                    upsertRow(data.token);
                }
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
            return;
        }

        var delBtn = e.target.closest('.vs-token-delete');
        if (delBtn && page.contains(delBtn)) {
            var delId = delBtn.getAttribute('data-token-id');
            var delConfirm = window.VsModal && window.VsModal.confirm
                ? window.VsModal.confirm('删除后不可恢复，确定删除该令牌？', '删除令牌')
                : Promise.resolve(window.confirm('确定删除该令牌？'));
            delConfirm.then(function (ok) {
                if (!ok) {
                    return;
                }
                return postAction('delete', { token_id: delId }).then(function (data) {
                    if (!data || Number(data.code) !== 1) {
                        window.VS.showMessage((data && data.msg) || '删除失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已删除', 'success');
                    var row = listEl && listEl.querySelector('.vs-token-row[data-token-row="' + delId + '"]');
                    if (row && row.parentNode) {
                        row.parentNode.removeChild(row);
                    }
                    applyMaxFromResponse(data);
                    syncEmptyAndStats();
                });
            }).catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        }
    });

    page.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var copyEl = e.target.closest('.vs-key-copy');
        if (!copyEl || !page.contains(copyEl)) {
            return;
        }
        e.preventDefault();
        copySecret(copyEl.getAttribute('data-copy') || copyEl.textContent);
    });

    if (form) {
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var remark = remarkInput ? String(remarkInput.value || '').trim() : '';
            if (!remark) {
                window.VS.showMessage('请填写令牌名称', 'error');
                if (remarkInput) {
                    remarkInput.focus();
                }
                return;
            }
            var quotaVal = quotaInput ? String(quotaInput.value || '').trim() : '0';
            if (quotaVal === '' || isNaN(Number(quotaVal)) || Number(quotaVal) < 0) {
                window.VS.showMessage('请填写有效的配额（0 表示不限制）', 'error');
                if (quotaInput) {
                    quotaInput.focus();
                }
                return;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            var action = formMode === 'update' ? 'update' : 'create';
            var payload = {
                remark: remark,
                quota: quotaVal,
                expiretime: expireInput ? String(expireInput.value || '').trim() : '',
                quotafallback: (fallbackInput && fallbackInput.checked) ? '1' : '0'
            };
            if (action === 'update') {
                payload.token_id = formId ? formId.value : '';
            }
            postAction(action, payload).then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (!data || Number(data.code) !== 1) {
                    window.VS.showMessage((data && data.msg) || '操作失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已保存', 'success');
                closeOverlay();
                if (data.token) {
                    upsertRow(data.token);
                }
                applyMaxFromResponse(data);
                syncEmptyAndStats();
            }).catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            });
        });
    }

    syncExpireTrigger();
    syncEmptyAndStats();
})();
