/**
 * 文件：assets/js/ops-mail.js
 * 作用：运营中心·邮件群发（双 Tab、激励同款筛选、嵌套选人、分片发送、发送记录）
 */
(function () {
    'use strict';

    var page = document.getElementById('opsMailPage');
    if (!page || !window.VS || typeof VS.postForm !== 'function') {
        return;
    }

    var TAG_DEFS = [
        { id: 'all', label: '全部用户', group: 'audience' },
        { id: 'developer', label: '仅开发者', group: 'audience' },
        { id: 'user', label: '仅普通用户', group: 'audience' },
        { id: 'login_7', label: '近 7 日登录', group: 'login' },
        { id: 'login_30', label: '近 30 日登录', group: 'login' },
        { id: 'calls_7', label: '近 7 日有调用', group: 'calls' },
        { id: 'calls_7_active', label: '近 7 日活跃调用', group: 'calls' },
        { id: 'spent', label: '累计消耗达标', group: 'spent' },
        { id: 'include_banned', label: '包含封禁用户', group: 'status' },
        { id: 'custom', label: '自定义…', group: 'custom' },
        { id: 'picked', label: '指定用户', group: 'picked' }
    ];

    var selected = { all: true };
    var customData = null;
    var pickedUsers = {};
    var draftSelected = {};
    var draftPicked = {};
    var lastFilter = null;
    var filterToken = '';
    var sendToken = '';
    var busy = false;
    var tempPhFocus = null;
    var logPage = 1;
    var logTotal = parseInt(page.getAttribute('data-log-total') || '0', 10) || 0;

    var els = {
        tabs: document.getElementById('opsMailTabs'),
        paneSend: document.getElementById('opsMailPaneSend'),
        paneLogs: document.getElementById('opsMailPaneLogs'),
        tplSelect: document.getElementById('opsMailTplSelect'),
        tplPreview: document.getElementById('opsMailTplPreview'),
        previewSubject: document.getElementById('opsMailPreviewSubject'),
        previewBody: document.getElementById('opsMailPreviewBody'),
        tempWrap: document.getElementById('opsMailTempWrap'),
        tempSubject: document.getElementById('opsMailTempSubject'),
        tempBody: document.getElementById('opsMailTempBody'),
        trigger: document.getElementById('opsMailTagTrigger'),
        chips: document.getElementById('opsMailSelectedChips'),
        placeholder: document.getElementById('opsMailTagPlaceholder'),
        filterBtn: document.getElementById('opsMailFilterBtn'),
        sendBtn: document.getElementById('opsMailSendBtn'),
        tipBanner: document.getElementById('opsMailTipBanner'),
        progressWrap: document.getElementById('opsMailProgressWrap'),
        progressFill: document.getElementById('opsMailProgressFill'),
        progressText: document.getElementById('opsMailProgressText'),
        filterText: document.getElementById('opsMailFilterText'),
        tempPhBar: document.getElementById('opsMailTempPhBar'),
        pickedWrap: document.getElementById('opsMailPickedUsers'),
        pickedChips: document.getElementById('opsMailPickedChips'),
        tagPop: document.getElementById('opsMailTagPop'),
        tagGrid: document.getElementById('opsMailTagGrid'),
        tagBackdrop: document.getElementById('opsMailTagPopBackdrop'),
        tagClose: document.getElementById('opsMailTagPopClose'),
        tagClear: document.getElementById('opsMailTagPopClear'),
        tagOk: document.getElementById('opsMailTagPopOk'),
        userPicker: document.getElementById('opsMailUserPicker'),
        userSearch: document.getElementById('opsMailUserSearch'),
        userList: document.getElementById('opsMailUserList'),
        userPickerOk: document.getElementById('opsMailUserPickerOk'),
        logSearch: document.getElementById('opsMailLogSearch'),
        logList: document.getElementById('opsMailLogList'),
        logEmpty: document.getElementById('opsMailLogEmpty'),
        logTableWrap: document.getElementById('opsMailLogTableWrap'),
        logFooter: document.getElementById('opsMailLogFooter'),
        logStats: document.getElementById('opsMailLogStats'),
        logPrev: document.getElementById('opsMailLogPrevBtn'),
        logNext: document.getElementById('opsMailLogNextBtn'),
        logPagerNums: document.getElementById('opsMailLogPagerNums'),
        logPageSize: document.getElementById('opsMailLogPageSize')
    };

    if (els.userPicker && els.userPicker.parentNode !== document.body) {
        document.body.appendChild(els.userPicker);
    }

    function showTip(msg, kind) {
        if (!els.tipBanner) {
            return;
        }
        els.tipBanner.hidden = false;
        els.tipBanner.textContent = msg || '';
        els.tipBanner.className = 'vs-incentive-tip'
            + (kind === 'error' ? ' is-error' : '')
            + (kind === 'ok' ? ' is-ok' : '');
    }

    function hideTip() {
        if (!els.tipBanner) {
            return;
        }
        els.tipBanner.hidden = true;
        els.tipBanner.textContent = '';
        els.tipBanner.className = 'vs-incentive-tip';
    }

    function modalAlert(msg, title) {
        showTip(msg, 'error');
        if (window.VS && typeof VS.showMessage === 'function') {
            VS.showMessage(msg, 'error');
        }
        if (window.VsModal && typeof VsModal.alert === 'function') {
            return VsModal.alert(msg, title || '提示');
        }
        return Promise.resolve();
    }

    function modalConfirm(msg, title, options) {
        if (window.VsModal && typeof VsModal.confirm === 'function') {
            return VsModal.confirm(msg, title || '操作确认', options || {});
        }
        return Promise.resolve(false);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function tagLabel(id) {
        for (var i = 0; i < TAG_DEFS.length; i++) {
            if (TAG_DEFS[i].id === id) {
                return TAG_DEFS[i].label;
            }
        }
        return id;
    }

    function selectedIds() {
        return Object.keys(selected).filter(function (k) {
            return selected[k];
        });
    }

    function pickedIds() {
        return Object.keys(pickedUsers).map(function (k) {
            return parseInt(k, 10);
        }).filter(function (n) {
            return n > 0;
        });
    }

    function renderSelectedChips() {
        if (!els.chips || !els.placeholder) {
            return;
        }
        els.chips.innerHTML = '';
        var ids = selectedIds().filter(function (id) {
            return id !== 'picked';
        });
        if (!ids.length && !pickedIds().length) {
            els.placeholder.hidden = false;
        } else {
            els.placeholder.hidden = true;
        }
        ids.forEach(function (id) {
            var chip = document.createElement('span');
            chip.className = 'vs-incentive-chip';
            if (id === 'custom' && customData && customData.summary) {
                chip.textContent = customData.summary;
            } else if (id === 'spent' && customData && customData.pointsspent_min) {
                chip.textContent = '消耗≥' + customData.pointsspent_min;
            } else {
                chip.textContent = tagLabel(id).replace(/…$/, '');
            }
            els.chips.appendChild(chip);
        });
        if (pickedIds().length) {
            var pchip = document.createElement('span');
            pchip.className = 'vs-incentive-chip';
            pchip.textContent = '指定用户×' + pickedIds().length;
            els.chips.appendChild(pchip);
        }
        renderPickedChips();
    }

    function renderPickedChips() {
        if (!els.pickedWrap || !els.pickedChips) {
            return;
        }
        var ids = pickedIds();
        if (!ids.length) {
            els.pickedWrap.hidden = true;
            els.pickedChips.innerHTML = '';
            return;
        }
        els.pickedWrap.hidden = false;
        els.pickedChips.innerHTML = '';
        ids.forEach(function (id) {
            var u = pickedUsers[id];
            if (!u) {
                return;
            }
            var chip = document.createElement('span');
            chip.className = 'vs-ops-mail-userchip';
            chip.title = u.email || '';
            var av = u.avatar
                ? '<img class="vs-ops-mail-userchip__av" src="' + esc(u.avatar) + '" alt="" width="20" height="20">'
                : '<span class="vs-ops-mail-userchip__av vs-ops-mail-userchip__av--fallback">'
                    + esc((u.username || '?').charAt(0)) + '</span>';
            chip.innerHTML = av + '<span class="vs-ops-mail-userchip__name">' + esc(u.username || ('用户#' + id))
                + '</span><button type="button" class="vs-ops-mail-userchip__x" data-uid="' + id + '" aria-label="移除">×</button>';
            els.pickedChips.appendChild(chip);
        });
    }

    function filterSummaryText() {
        var parts = [];
        selectedIds().forEach(function (id) {
            if (id === 'picked' || id === 'custom' || id === 'spent') {
                return;
            }
            parts.push(tagLabel(id).replace(/…$/, ''));
        });
        if (customData && customData.summary) {
            parts.push(customData.summary);
        } else if (selected.spent && customData && customData.pointsspent_min) {
            parts.push('消耗≥' + customData.pointsspent_min);
        }
        if (pickedIds().length) {
            parts.push('指定用户' + pickedIds().length + '人');
        }
        if (!parts.length) {
            parts.push('全部用户');
        }
        return parts.join(' · ');
    }

    function invalidateFilter() {
        lastFilter = null;
        filterToken = '';
        sendToken = '';
        if (els.filterText) {
            els.filterText.hidden = true;
            els.filterText.textContent = '';
        }
        if (!busy) {
            hideProgress();
        }
    }

    function setBusy(on) {
        busy = !!on;
        if (els.filterBtn) {
            els.filterBtn.disabled = busy || page.getAttribute('data-redis-ok') !== '1';
        }
        if (els.trigger) {
            els.trigger.disabled = busy || page.getAttribute('data-redis-ok') !== '1';
        }
        if (els.sendBtn) {
            els.sendBtn.disabled = busy
                || page.getAttribute('data-redis-ok') !== '1'
                || page.getAttribute('data-mail-ready') !== '1';
        }
        if (els.tplSelect) {
            els.tplSelect.disabled = busy;
        }
    }

    function setProgress(label, done, total, percent) {
        done = Number(done) || 0;
        total = Number(total) || 0;
        percent = Number(percent);
        if (isNaN(percent)) {
            percent = total > 0 ? Math.floor((done * 100) / total) : 0;
        }
        if (els.progressWrap) {
            els.progressWrap.hidden = false;
        }
        if (els.progressFill) {
            els.progressFill.style.width = Math.max(0, Math.min(100, percent)) + '%';
        }
        if (els.progressText) {
            els.progressText.textContent = (label ? (label + ' · ') : '')
                + done + ' / ' + total + ' · ' + percent + '%';
        }
    }

    function hideProgress() {
        if (els.progressWrap) {
            els.progressWrap.hidden = true;
        }
        if (els.progressFill) {
            els.progressFill.style.width = '0%';
        }
        if (els.progressText) {
            els.progressText.textContent = '';
        }
        if (els.filterText) {
            els.filterText.hidden = true;
            els.filterText.textContent = '';
        }
    }

    function insertPhInto(el, ph) {
        if (!el || !ph) {
            return;
        }
        var start = typeof el.selectionStart === 'number' ? el.selectionStart : String(el.value || '').length;
        var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
        var v = String(el.value || '');
        el.value = v.slice(0, start) + ph + v.slice(end);
        var pos = start + ph.length;
        el.focus();
        if (typeof el.setSelectionRange === 'function') {
            el.setSelectionRange(pos, pos);
        }
        tempPhFocus = el;
    }

    function bindTempPhBar() {
        var bar = els.tempPhBar;
        if (!bar) {
            return;
        }
        var ids = String(bar.getAttribute('data-ph-targets') || '')
            .split(',')
            .map(function (s) { return s.trim(); })
            .filter(Boolean);
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) {
                return;
            }
            el.addEventListener('focus', function () {
                tempPhFocus = el;
            });
        });
        // mousedown 防失焦，避免相邻连点第二个占位符插不进
        bar.addEventListener('mousedown', function (e) {
            if (e.target.closest('[data-ph]')) {
                e.preventDefault();
            }
        });
        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ph]');
            if (!btn) {
                return;
            }
            var ph = btn.getAttribute('data-ph') || '';
            var el = tempPhFocus;
            if (!el || !document.body.contains(el)) {
                el = ids.length ? document.getElementById(ids[0]) : null;
            }
            insertPhInto(el, ph);
        });
    }

    function appendFilter(fd) {
        var tags = selectedIds().filter(function (id) {
            return id !== 'picked';
        });
        if (pickedIds().length && tags.indexOf('custom') < 0) {
            tags.push('custom');
        }
        fd.append('tags', tags.join(','));
        if (customData) {
            Object.keys(customData).forEach(function (k) {
                if (k === 'summary' || k === 'user_ids') {
                    return;
                }
                if (customData[k] !== '' && customData[k] != null) {
                    fd.append(k, String(customData[k]));
                }
            });
        }
        if (pickedIds().length) {
            fd.append('user_ids', pickedIds().join(','));
        }
    }

    /* ---------- Tabs ---------- */
    var logsFetchedOnce = false;
    var tabSwitchLock = false;

    function setTab(tab, opts) {
        tab = tab === 'logs' ? 'logs' : 'send';
        opts = opts || {};
        if (page.getAttribute('data-active-tab') === tab && !opts.force) {
            return;
        }
        page.setAttribute('data-active-tab', tab);
        if (els.paneSend) {
            els.paneSend.hidden = tab !== 'send';
        }
        if (els.paneLogs) {
            els.paneLogs.hidden = tab !== 'logs';
        }
        if (els.tabs) {
            var btns = els.tabs.querySelectorAll('[data-tab]');
            Array.prototype.forEach.call(btns, function (btn) {
                var on = btn.getAttribute('data-tab') === tab;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        // 首进记录再拉列表；反复点 Tab 勿重刷整段 DOM（易闪）
        if (tab === 'logs' && page.getAttribute('data-log-ready') === '1' && !logsFetchedOnce) {
            logsFetchedOnce = true;
            applyLogView();
        }
    }

    if (els.tabs) {
        els.tabs.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-tab]');
            if (!btn || tabSwitchLock) {
                return;
            }
            tabSwitchLock = true;
            setTab(btn.getAttribute('data-tab'));
            window.setTimeout(function () {
                tabSwitchLock = false;
            }, 200);
        });
    }

    /* ---------- Template ---------- */
    function syncTplUi() {
        var val = els.tplSelect ? String(els.tplSelect.value || '0') : '0';
        var isTemp = val === 'temp';
        if (els.tempWrap) {
            els.tempWrap.hidden = !isTemp;
        }
        if (els.tplPreview) {
            els.tplPreview.hidden = isTemp || val === '0';
        }
        if (!isTemp && val !== '0') {
            loadTplPreview(parseInt(val, 10) || 0);
        }
    }

    function loadTplPreview(id) {
        if (id <= 0) {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'tpl_get');
        fd.append('tpl_id', String(id));
        VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || Number(data.code) !== 1 || !data.item) {
                throw new Error((data && data.msg) || '模板不可用');
            }
            if (els.previewSubject) {
                els.previewSubject.textContent = data.item.summary || '—';
            }
            if (els.previewBody) {
                // 服务端已 sanitize；用可随内容收缩的容器（超出 max-height 再滚动）
                var safe = String(data.item.body || '');
                els.previewBody.innerHTML = '<div class="vs-ops-mail-preview__html">' + safe + '</div>';
            }
            if (els.tplPreview) {
                els.tplPreview.hidden = false;
            }
        }).catch(function (err) {
            if (els.tplPreview) {
                els.tplPreview.hidden = true;
            }
            VS.showMessage((err && err.message) ? err.message : '模板加载失败', 'error');
        });
    }

    if (els.tplSelect) {
        els.tplSelect.addEventListener('change', syncTplUi);
    }

    /* ---------- Tag pop ---------- */
    function renderTagGrid() {
        if (!els.tagGrid) {
            return;
        }
        els.tagGrid.innerHTML = '';
        TAG_DEFS.forEach(function (def) {
            var btn = document.createElement('button');
            btn.type = 'button';
            // 与激励页一致：样式类为 .vs-incentive-tagchip（勿用未定义的 __tag）
            btn.className = 'vs-incentive-tagchip'
                + (draftSelected[def.id] ? ' is-on' : '');
            btn.setAttribute('data-tag', def.id);
            btn.setAttribute('data-group', def.group);
            btn.textContent = def.label;
            els.tagGrid.appendChild(btn);
        });
    }

    function openTagPop() {
        draftSelected = {};
        selectedIds().forEach(function (id) {
            draftSelected[id] = true;
        });
        if (pickedIds().length) {
            draftSelected.picked = true;
        }
        renderTagGrid();
        if (els.tagPop) {
            els.tagPop.hidden = false;
        }
        if (els.trigger) {
            els.trigger.setAttribute('aria-expanded', 'true');
        }
        document.body.classList.add('vs-incentive-tagpop-open');
    }

    function closeTagPop() {
        if (els.tagPop) {
            els.tagPop.hidden = true;
        }
        if (els.trigger) {
            els.trigger.setAttribute('aria-expanded', 'false');
        }
        document.body.classList.remove('vs-incentive-tagpop-open');
    }

    function toggleDraftTag(id, group) {
        if (id === 'custom') {
            closeTagPop();
            openCustomEditor();
            return;
        }
        if (id === 'picked') {
            closeTagPop();
            openUserPicker();
            return;
        }
        if (id === 'spent') {
            closeTagPop();
            openCustomEditor({ focusSpent: true, forceSpent: true });
            return;
        }
        if (draftSelected[id]) {
            delete draftSelected[id];
        } else {
            if (group === 'audience' || group === 'login' || group === 'calls') {
                TAG_DEFS.forEach(function (d) {
                    if (d.group === group) {
                        delete draftSelected[d.id];
                    }
                });
            }
            draftSelected[id] = true;
        }
        renderTagGrid();
    }

    if (els.trigger) {
        els.trigger.addEventListener('click', function () {
            if (busy || els.trigger.disabled) {
                return;
            }
            openTagPop();
        });
    }
    if (els.tagGrid) {
        els.tagGrid.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-tag]');
            if (!btn) {
                return;
            }
            toggleDraftTag(btn.getAttribute('data-tag'), btn.getAttribute('data-group'));
        });
    }
    if (els.tagClose) {
        els.tagClose.addEventListener('click', closeTagPop);
    }
    if (els.tagBackdrop) {
        els.tagBackdrop.addEventListener('click', closeTagPop);
    }
    if (els.tagClear) {
        els.tagClear.addEventListener('click', function () {
            draftSelected = { all: true };
            renderTagGrid();
        });
    }
    if (els.tagOk) {
        els.tagOk.addEventListener('click', function () {
            var next = {};
            Object.keys(draftSelected).forEach(function (k) {
                if (draftSelected[k] && k !== 'picked') {
                    next[k] = true;
                }
            });
            if (draftSelected.picked && pickedIds().length) {
                next.picked = true;
            }
            if (!Object.keys(next).length) {
                next = { all: true };
            }
            if (next.custom && !(customData && customData.summary)) {
                delete next.custom;
            }
            if (next.spent && !(customData && customData.pointsspent_min)) {
                delete next.spent;
            }
            selected = next;
            renderSelectedChips();
            invalidateFilter();
            closeTagPop();
        });
    }

    /* ---------- Custom editor ---------- */
    var customRoot = null;

    function ensureCustomRoot() {
        if (customRoot) {
            return customRoot;
        }
        customRoot = document.createElement('div');
        customRoot.className = 'vs-incentive-custompop';
        customRoot.id = 'opsMailCustomPop';
        customRoot.hidden = true;
        customRoot.innerHTML = ''
            + '<div class="vs-incentive-custompop__backdrop" data-close="1"></div>'
            + '<div class="vs-incentive-custompop__panel" role="dialog" aria-label="自定义筛选">'
            + '  <div class="vs-incentive-custompop__head">'
            + '    <span class="vs-incentive-custompop__title">自定义筛选</span>'
            + '    <button type="button" class="vs-incentive-custompop__close" data-close="1" aria-label="关闭">×</button>'
            + '  </div>'
            + '  <div class="vs-incentive-custompop__body">'
            + '    <div class="vs-form-row"><label class="vs-label" for="omLogin">最近多少天内登录过</label>'
            + '    <input type="number" class="vs-input" id="omLogin" min="1" max="365" step="1" placeholder="不填=不限制"></div>'
            + '    <div class="vs-form-row vs-form-row--inline">'
            + '      <div><label class="vs-label" for="omCallsDays">最近几天有接口调用（最多 7 天）</label>'
            + '      <input type="number" class="vs-input" id="omCallsDays" min="1" max="7" step="1"></div>'
            + '      <div><label class="vs-label" for="omCallsMin">这几天内至少调用几次</label>'
            + '      <input type="number" class="vs-input" id="omCallsMin" min="1" step="1"></div>'
            + '    </div>'
            + '    <div class="vs-form-row"><label class="vs-label" for="omKeycalls">历史总调用次数至少多少</label>'
            + '    <input type="number" class="vs-input" id="omKeycalls" min="1" step="1"></div>'
            + '    <div class="vs-form-row"><label class="vs-label" for="omSpent">历史累计花掉的积分至少多少</label>'
            + '    <input type="number" class="vs-input" id="omSpent" min="0" step="0.0001"></div>'
            + '    <p class="vs-form-hint">指定用户请用筛选标签「指定用户」点选，勿在此手填编号。</p>'
            + '  </div>'
            + '  <div class="vs-incentive-custompop__foot">'
            + '    <button type="button" class="vs-btn vs-btn--default" data-close="1">取消</button>'
            + '    <button type="button" class="vs-btn vs-btn--primary" id="omCustomOk">确认并加入标签</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(customRoot);
        customRoot.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-close') === '1') {
                closeCustomEditor();
            }
        });
        var ok = customRoot.querySelector('#omCustomOk');
        if (ok) {
            ok.addEventListener('click', commitCustomEditor);
        }
        return customRoot;
    }

    function openCustomEditor(opts) {
        opts = opts || {};
        var root = ensureCustomRoot();
        var g = function (id) {
            return root.querySelector('#' + id);
        };
        var d = customData || {};
        g('omLogin').value = d.login_within_days || '';
        g('omCallsDays').value = d.calls_within_days || '';
        g('omCallsMin').value = d.calls_min || '';
        g('omKeycalls').value = d.keycalls_min || '';
        g('omSpent').value = d.pointsspent_min || '';
        root.hidden = false;
        document.body.classList.add('vs-incentive-custompop-open');
        root._forceSpent = !!opts.forceSpent;
        if (opts.focusSpent && g('omSpent')) {
            setTimeout(function () {
                g('omSpent').focus();
            }, 50);
        }
    }

    function closeCustomEditor() {
        if (customRoot) {
            customRoot.hidden = true;
        }
        document.body.classList.remove('vs-incentive-custompop-open');
    }

    function commitCustomEditor() {
        var root = ensureCustomRoot();
        var g = function (id) {
            return root.querySelector('#' + id);
        };
        var spent = (g('omSpent').value || '').trim();
        if (root._forceSpent && !spent) {
            modalAlert('请填写累计积分消耗下限', '自定义筛选');
            return;
        }
        var callsDays = (g('omCallsDays').value || '').trim();
        if (callsDays !== '' && Number(callsDays) > 7) {
            modalAlert('近 N 日调用仅支持 1～7 日', '筛选条件冲突');
            return;
        }
        var data = {
            login_within_days: (g('omLogin').value || '').trim(),
            calls_within_days: callsDays,
            calls_min: (g('omCallsMin').value || '').trim(),
            keycalls_min: (g('omKeycalls').value || '').trim(),
            pointsspent_min: spent
        };
        var parts = [];
        if (data.login_within_days) {
            parts.push('近' + data.login_within_days + '天登录');
        }
        if (data.calls_within_days || data.calls_min) {
            parts.push('近' + (data.calls_within_days || '7') + '天调用≥' + (data.calls_min || '1'));
        }
        if (data.keycalls_min) {
            parts.push('总调用≥' + data.keycalls_min);
        }
        if (data.pointsspent_min) {
            parts.push('花掉积分≥' + data.pointsspent_min);
        }
        if (!parts.length && !root._forceSpent) {
            modalAlert('请至少填写一项自定义条件', '自定义筛选');
            return;
        }
        data.summary = parts.length ? ('自定义·' + parts.join('·')) : '自定义';
        customData = data;
        selected.custom = true;
        if (data.pointsspent_min) {
            selected.spent = true;
        }
        if (data.login_within_days) {
            delete selected.login_7;
            delete selected.login_30;
        }
        if (data.calls_within_days || data.calls_min) {
            delete selected.calls_7;
            delete selected.calls_7_active;
        }
        renderSelectedChips();
        invalidateFilter();
        closeCustomEditor();
    }

    /* ---------- User picker ---------- */
    function openUserPicker() {
        draftPicked = {};
        Object.keys(pickedUsers).forEach(function (k) {
            draftPicked[k] = pickedUsers[k];
        });
        if (els.userPicker) {
            els.userPicker.hidden = false;
            els.userPicker.classList.add('is-open');
            els.userPicker.setAttribute('aria-hidden', 'false');
        }
        document.body.classList.add('is-overlay-open');
        if (els.userSearch) {
            els.userSearch.value = '';
        }
        searchUsers('');
    }

    function closeUserPicker() {
        if (els.userPicker) {
            els.userPicker.classList.remove('is-open');
            els.userPicker.hidden = true;
            els.userPicker.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('is-overlay-open');
    }

    function searchUsers(q) {
        if (!els.userList) {
            return;
        }
        els.userList.innerHTML = '<p class="vs-form-hint">加载中…</p>';
        var fd = new FormData();
        fd.append('action', 'user_search');
        fd.append('q', q || '');
        fd.append('page', '1');
        VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '搜索失败');
            }
            var list = data.list || [];
            if (!list.length) {
                els.userList.innerHTML = '<p class="vs-form-hint">暂无匹配用户</p>';
                return;
            }
            els.userList.innerHTML = '';
            list.forEach(function (u) {
                var id = Number(u.id) || 0;
                var row = document.createElement('label');
                row.className = 'vs-ops-mail-userrow'
                    + (Number(u.has_mail) !== 1 ? ' is-disabled' : '');
                var checked = !!draftPicked[id];
                var av = u.avatar
                    ? '<img src="' + esc(u.avatar) + '" alt="" width="32" height="32">'
                    : '<span class="vs-ops-mail-userrow__fallback">' + esc((u.username || '?').charAt(0)) + '</span>';
                row.innerHTML = '<input type="checkbox" ' + (checked ? 'checked ' : '')
                    + (Number(u.has_mail) !== 1 ? 'disabled ' : '')
                    + 'data-uid="' + id + '">'
                    + '<span class="vs-ops-mail-userrow__av">' + av + '</span>'
                    + '<span class="vs-ops-mail-userrow__meta">'
                    + '<span class="vs-ops-mail-userrow__name">' + esc(u.username || ('用户#' + id)) + '</span>'
                    + '<span class="vs-ops-mail-userrow__email">'
                    + esc(u.email || '未绑定邮箱') + '</span></span>';
                if (Number(u.has_mail) === 1) {
                    var cb = row.querySelector('input');
                    cb.addEventListener('change', function () {
                        if (cb.checked) {
                            if (Object.keys(draftPicked).length >= 500) {
                                cb.checked = false;
                                modalAlert('指定用户最多 500 人', '上限');
                                return;
                            }
                            draftPicked[id] = {
                                id: id,
                                username: u.username,
                                email: u.email,
                                avatar: u.avatar || ''
                            };
                        } else {
                            delete draftPicked[id];
                        }
                    });
                }
                els.userList.appendChild(row);
            });
        }).catch(function (err) {
            els.userList.innerHTML = '<p class="vs-form-hint">'
                + esc((err && err.message) || '搜索失败') + '</p>';
        });
    }

    if (els.userSearch) {
        var userSearchTimer = null;
        els.userSearch.addEventListener('input', function () {
            clearTimeout(userSearchTimer);
            userSearchTimer = setTimeout(function () {
                searchUsers(String(els.userSearch.value || '').trim());
            }, 280);
        });
    }
    if (els.userPicker) {
        els.userPicker.addEventListener('click', function (e) {
            if (e.target.closest('[data-user-picker-close]')) {
                closeUserPicker();
            }
        });
    }
    if (els.userPickerOk) {
        els.userPickerOk.addEventListener('click', function () {
            var keys = Object.keys(draftPicked);
            if (!keys.length) {
                modalAlert('请至少选择一位用户', '指定用户');
                return;
            }
            pickedUsers = {};
            keys.forEach(function (k) {
                pickedUsers[k] = draftPicked[k];
            });
            selected.picked = true;
            renderSelectedChips();
            invalidateFilter();
            closeUserPicker();
        });
    }
    if (els.pickedChips) {
        els.pickedChips.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-uid]');
            if (!btn) {
                return;
            }
            var uid = parseInt(btn.getAttribute('data-uid'), 10) || 0;
            delete pickedUsers[uid];
            if (!pickedIds().length) {
                delete selected.picked;
            }
            renderSelectedChips();
            invalidateFilter();
        });
    }

    /* ---------- Filter / Send ---------- */
    function applyFilterResult(data) {
        var n = data.matched_count || 0;
        lastFilter = {
            matched_count: n,
            can_execute: !!data.can_execute || n > 0 && n <= 2000,
            sync_max: data.sync_max || 2000,
            sample_users: data.sample_users || [],
            warnings: data.warnings || []
        };
        filterToken = data.token || filterToken || '';
        var lines = '命中 ' + n + ' 人（实数）';
        if (n > lastFilter.sync_max) {
            lines += ' · 超过一次上限 ' + lastFilter.sync_max + '，请收窄后再发';
            lastFilter.can_execute = false;
        }
        if (lastFilter.sample_users.length) {
            var names = lastFilter.sample_users.map(function (u) {
                return u.username || ('#' + u.id);
            }).slice(0, 6);
            lines += ' · 名称抽样：' + names.join('、')
                + (n > names.length ? ' 等' : '');
        }
        if (lastFilter.warnings.length) {
            lines += ' · ' + lastFilter.warnings.join('；');
        }
        if (els.filterText) {
            els.filterText.hidden = false;
            els.filterText.textContent = lines;
        }
    }

    function tickFilterOnce() {
        var fd = new FormData();
        fd.append('action', 'gift_filter_tick');
        fd.append('token', filterToken);
        return VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '筛选推进失败');
            }
            setProgress('筛选中', data.scanned || data.done, data.candidate || data.total, data.percent);
            if (Number(data.finished) === 1) {
                return data;
            }
            return tickFilterOnce();
        });
    }

    function runFilter() {
        if (busy || !els.filterBtn || els.filterBtn.disabled) {
            return;
        }
        setBusy(true);
        lastFilter = null;
        filterToken = '';
        hideTip();
        setProgress('筛选中', 0, 0, 0);
        var fd = new FormData();
        fd.append('action', 'gift_filter_start');
        appendFilter(fd);
        VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '筛选失败');
            }
            if (Number(data.finished) === 1) {
                return data;
            }
            filterToken = data.token || '';
            if (!filterToken) {
                throw new Error('筛选令牌无效');
            }
            setProgress('筛选中', data.scanned || 0, data.candidate || data.total || 0, data.percent || 0);
            return tickFilterOnce();
        }).then(function (data) {
            setBusy(false);
            setProgress('筛选完成', data.candidate || data.total || 0, data.candidate || data.total || 0, 100);
            applyFilterResult(data);
            showTip('筛选完成：命中 ' + (data.matched_count || data.candidate || 0) + ' 人', 'ok');
        }).catch(function (err) {
            setBusy(false);
            filterToken = '';
            lastFilter = null;
            hideProgress();
            modalAlert((err && err.message) || '筛选失败', '筛选失败');
        });
    }

    function sleep(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function tickSendOnce() {
        var fd = new FormData();
        fd.append('action', 'mail_tick');
        fd.append('token', sendToken);
        return VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '发送推进失败');
            }
            var done = data.done != null ? data.done : 0;
            var total = data.total != null ? data.total : 0;
            var percent = total > 0 ? Math.floor((done * 100) / total) : 0;
            setProgress('发送中', done, total, percent);
            if (Number(data.finished) === 1) {
                return data;
            }
            var wait = parseInt(data.tick_min_ms, 10);
            if (!(wait > 0)) {
                wait = 250;
            }
            if (Number(data.throttled) === 1) {
                wait = Math.max(wait, 250);
            }
            return sleep(wait).then(tickSendOnce);
        });
    }

    function startSend(confirmText) {
        var tplVal = els.tplSelect ? String(els.tplSelect.value || '0') : '0';
        var isTemp = tplVal === 'temp';
        var fd = new FormData();
        fd.append('action', 'mail_start');
        fd.append('filter_token', filterToken);
        fd.append('filtersum', filterSummaryText());
        fd.append('confirm_text', confirmText || '');
        if (isTemp) {
            fd.append('tpl_mode', 'temp');
            fd.append('tpl_id', '0');
            fd.append('temp_subject', els.tempSubject ? els.tempSubject.value : '');
            fd.append('temp_body', els.tempBody ? els.tempBody.value : '');
        } else {
            fd.append('tpl_mode', 'saved');
            fd.append('tpl_id', tplVal);
        }
        return VS.postForm(fd, window.location.href).then(function (data) {
            if (data && Number(data.code) !== 1 && data.need_confirm) {
                return modalConfirm(
                    data.msg || ('将向 ' + (data.matched_count || lastFilter.matched_count) + ' 人发送，请确认'),
                    '高危确认',
                    { danger: true, confirmText: '确认发送', cancelText: '取消' }
                ).then(function (yes) {
                    if (!yes) {
                        return null;
                    }
                    return startSend('确认发送');
                });
            }
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '启动失败');
            }
            return data;
        });
    }

    function runSend() {
        if (busy) {
            return;
        }
        if (!lastFilter || !filterToken) {
            modalAlert('请先点击「筛选」，确认命中人数后再发送。', '尚未筛选');
            return;
        }
        if (!lastFilter.can_execute || !(lastFilter.matched_count > 0)) {
            modalAlert('当前筛选结果不可发送，请收窄条件后重新筛选。', '无法发送');
            return;
        }
        var tplVal = els.tplSelect ? String(els.tplSelect.value || '0') : '0';
        if (tplVal === '0') {
            modalAlert('请先选择邮件模板，或使用临时撰写。', '未选模板');
            return;
        }
        if (tplVal === 'temp') {
            if (!els.tempSubject || !String(els.tempSubject.value || '').trim()
                || !els.tempBody || !String(els.tempBody.value || '').trim()) {
                modalAlert('请填写临时撰写的发信标题和正文。', '临时撰写');
                return;
            }
        }
        hideTip();
        var n = lastFilter.matched_count || 0;
        var needStrong = n > 200;
        var msg = '将向约 ' + n + ' 人发送邮件。\n筛选：' + filterSummaryText();
        modalConfirm(msg, needStrong ? '高危确认' : '确认发送', {
            danger: needStrong,
            confirmText: '确认发送',
            cancelText: '取消'
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            setBusy(true);
            setProgress('发送中', 0, n, 0);
            return startSend(needStrong ? '确认发送' : '').then(function (data) {
                if (!data) {
                    setBusy(false);
                    hideProgress();
                    return;
                }
                sendToken = data.token || '';
                if (!sendToken) {
                    throw new Error('发送令牌无效');
                }
                filterToken = '';
                setProgress('发送中', data.done || 0, data.total || n, 0);
                return tickSendOnce();
            }).then(function (data) {
                if (!data) {
                    return;
                }
                setBusy(false);
                sendToken = '';
                lastFilter = null;
                filterToken = '';
                var okN = data.oknum != null ? Number(data.oknum) : 0;
                var failN = data.failnum != null ? Number(data.failnum) : 0;
                hideProgress();
                showTip('发送完成：成功 ' + okN + '，失败 ' + failN + ' · 可到「发送记录」查看明细', 'ok');
                VS.showMessage('发送完成', 'success');
                logPage = 1;
            }).catch(function (err) {
                setBusy(false);
                sendToken = '';
                hideProgress();
                modalAlert((err && err.message) || '发送失败', '发送失败');
            });
        });
    }

    if (els.filterBtn) {
        els.filterBtn.addEventListener('click', runFilter);
    }
    if (els.sendBtn) {
        els.sendBtn.addEventListener('click', runSend);
    }
    bindTempPhBar();

    /* ---------- Logs ---------- */
    function getLogPageSize() {
        var n = els.logPageSize ? parseInt(els.logPageSize.value, 10) : 20;
        return n > 0 ? n : 20;
    }

    function applyLogView() {
        if (page.getAttribute('data-log-ready') !== '1') {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'log_list');
        fd.append('q', els.logSearch ? String(els.logSearch.value || '').trim() : '');
        fd.append('page', String(logPage));
        fd.append('pagesize', String(getLogPageSize()));
        VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || Number(data.code) !== 1) {
                throw new Error((data && data.msg) || '加载失败');
            }
            logPage = parseInt(data.page, 10) || logPage;
            logTotal = parseInt(data.total, 10) || 0;
            page.setAttribute('data-log-total', String(logTotal));
            if (els.logList) {
                els.logList.innerHTML = data.list_html || '';
            }
            var pageItems = els.logList
                ? els.logList.querySelectorAll('[data-maillog-row]').length
                : 0;
            if (els.logEmpty) {
                els.logEmpty.hidden = logTotal > 0;
            }
            if (els.logTableWrap) {
                els.logTableWrap.hidden = pageItems === 0;
            }
            if (els.logFooter) {
                els.logFooter.hidden = logTotal === 0;
            }
            if (els.logStats) {
                els.logStats.textContent = '共 ' + logTotal + ' 条';
            }
            var totalPages = Math.max(1, Math.ceil(logTotal / getLogPageSize()) || 1);
            if (window.VS && VS.applyLogsPager) {
                VS.applyLogsPager(logPage, totalPages, els.logPrev, els.logNext, els.logPagerNums);
            }
        }).catch(function (err) {
            VS.showMessage((err && err.message) || '加载失败', 'error');
        });
    }

    function bindLogNav() {
        if (els.logSearch) {
            var t = null;
            els.logSearch.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    logPage = 1;
                    applyLogView();
                }, 280);
            });
        }
        if (els.logPageSize) {
            els.logPageSize.addEventListener('change', function () {
                logPage = 1;
                applyLogView();
            });
        }
        if (els.logPrev) {
            els.logPrev.addEventListener('click', function () {
                if (logPage > 1) {
                    logPage -= 1;
                    applyLogView();
                }
            });
        }
        if (els.logNext) {
            els.logNext.addEventListener('click', function () {
                logPage += 1;
                applyLogView();
            });
        }
        if (els.logPagerNums) {
            els.logPagerNums.addEventListener('click', function (e) {
                var num = e.target.closest('[data-page]');
                if (!num) {
                    return;
                }
                logPage = parseInt(num.getAttribute('data-page'), 10) || 1;
                applyLogView();
            });
        }
    }

    renderSelectedChips();
    syncTplUi();
    bindLogNav();
})();
