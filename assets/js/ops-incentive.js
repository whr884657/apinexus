/**
 * 文件：assets/js/ops-incentive.js
 * 作用：用户激励·积分赠送（标签多选自渲染弹层 + 自定义弹窗 + VsModal 确认；禁原生 select/confirm/prompt）
 */
(function () {
    'use strict';

    var form = document.getElementById('incentiveGiftForm');
    if (!form || !window.VS || typeof VS.postForm !== 'function') {
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
        { id: 'custom', label: '自定义…', group: 'custom' }
    ];

    /** 默认「全部用户」 */
    var selected = { all: true };
    var customData = null;
    var draftSelected = {};
    var lastFilter = null;
    var filterToken = '';
    var giftToken = '';
    var busy = false;

    var els = {
        fold: document.getElementById('incentiveGiftFold'),
        foldBar: document.getElementById('incentiveGiftFoldBar'),
        amount: document.getElementById('giftAmount'),
        remark: document.getElementById('giftRemark'),
        filterText: document.getElementById('giftFilterText'),
        statusLine: document.getElementById('incentiveStatusLine'),
        tipBanner: document.getElementById('incentiveTipBanner'),
        filterBtn: document.getElementById('giftFilterBtn'),
        executeBtn: document.getElementById('giftExecuteBtn'),
        progressWrap: document.getElementById('incentiveProgressWrap'),
        progressFill: document.getElementById('incentiveProgressFill'),
        progressText: document.getElementById('incentiveProgressText'),
        trigger: document.getElementById('incentiveTagTrigger'),
        chips: document.getElementById('incentiveSelectedChips'),
        placeholder: document.getElementById('incentiveTagPlaceholder'),
        tagPop: document.getElementById('incentiveTagPop'),
        tagGrid: document.getElementById('incentiveTagGrid'),
        tagBackdrop: document.getElementById('incentiveTagPopBackdrop'),
        tagClose: document.getElementById('incentiveTagPopClose'),
        tagClear: document.getElementById('incentiveTagPopClear'),
        tagOk: document.getElementById('incentiveTagPopOk')
    };

    function showTipBanner(msg, kind) {
        if (!els.tipBanner) {
            return;
        }
        els.tipBanner.hidden = false;
        els.tipBanner.textContent = msg || '';
        els.tipBanner.className = 'vs-incentive-tip'
            + (kind === 'error' ? ' is-error' : '')
            + (kind === 'ok' ? ' is-ok' : '');
    }

    function hideTipBanner() {
        if (!els.tipBanner) {
            return;
        }
        els.tipBanner.hidden = true;
        els.tipBanner.textContent = '';
        els.tipBanner.className = 'vs-incentive-tip';
    }

    function modalAlert(msg, title) {
        showTipBanner(msg, 'error');
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

    var REMARK_MAX = 120;

    /** 超长直接截断，不展示 n/120 */
    function clampRemark() {
        if (!els.remark) {
            return;
        }
        var chars = Array.from(els.remark.value);
        if (chars.length > REMARK_MAX) {
            els.remark.value = chars.slice(0, REMARK_MAX).join('');
        }
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

    function renderSelectedChips() {
        if (!els.chips || !els.placeholder) {
            return;
        }
        els.chips.innerHTML = '';
        var ids = selectedIds();
        if (!ids.length) {
            els.placeholder.hidden = false;
            return;
        }
        els.placeholder.hidden = true;
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
    }

    function invalidateFilter() {
        lastFilter = null;
        filterToken = '';
        giftToken = '';
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
            els.filterBtn.disabled = busy;
        }
        if (els.trigger) {
            els.trigger.disabled = busy;
        }
        if (els.executeBtn) {
            // 忙碌时禁用；平时可点，未筛选则弹横幅提示（禁用会导致点不到、无提示）
            els.executeBtn.disabled = busy;
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
    }

    function appendFilter(fd) {
        fd.append('tags', selectedIds().join(','));
        if (customData) {
            Object.keys(customData).forEach(function (k) {
                if (k === 'summary') {
                    return;
                }
                if (customData[k] !== '' && customData[k] != null) {
                    fd.append(k, String(customData[k]));
                }
            });
        }
    }

    /* ---------- 标签弹层 ---------- */
    function openTagPop() {
        draftSelected = {};
        selectedIds().forEach(function (id) {
            draftSelected[id] = true;
        });
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

    function renderTagGrid() {
        if (!els.tagGrid) {
            return;
        }
        els.tagGrid.innerHTML = '';
        TAG_DEFS.forEach(function (def) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-incentive-tagchip' + (draftSelected[def.id] || (def.id === 'custom' && selected.custom) || (def.id === 'spent' && selected.spent) ? ' is-on' : '');
            btn.textContent = def.label;
            btn.setAttribute('data-tag', def.id);
            btn.addEventListener('click', function () {
                toggleDraftTag(def.id, def.group);
            });
            els.tagGrid.appendChild(btn);
        });
    }

    function applyDraftTags() {
        var next = {};
        Object.keys(draftSelected).forEach(function (k) {
            if (draftSelected[k]) {
                next[k] = true;
            }
        });
        // 自定义/消耗须已有填写结果；草稿里勾了但无数据则丢掉
        if (next.custom && !customData) {
            delete next.custom;
        }
        if (next.spent && !(customData && customData.pointsspent_min)) {
            delete next.spent;
        }
        if (!next.custom && !next.spent) {
            // 保留 customData 便于再次打开编辑；标签以 next 为准
        }
        // 未选体系标签时默认全部用户
        if (!next.all && !next.developer && !next.user) {
            next.all = true;
        }
        selected = next;
        renderSelectedChips();
        invalidateFilter();
        closeTagPop();
    }

    /* ---------- 自定义弹窗（自渲染：手机抽屉 / 电脑大窗） ---------- */
    var customRoot = null;

    function ensureCustomRoot() {
        if (customRoot) {
            return customRoot;
        }
        customRoot = document.createElement('div');
        customRoot.className = 'vs-incentive-custompop';
        customRoot.hidden = true;
        customRoot.innerHTML = ''
            + '<div class="vs-incentive-custompop__backdrop" data-close="1"></div>'
            + '<div class="vs-incentive-custompop__panel" role="dialog" aria-label="自定义筛选">'
            + '  <div class="vs-incentive-custompop__head">'
            + '    <span class="vs-incentive-custompop__title">自定义筛选</span>'
            + '    <button type="button" class="vs-incentive-custompop__close" data-close="1" aria-label="关闭">×</button>'
            + '  </div>'
            + '  <div class="vs-incentive-custompop__body">'
            + '    <div class="vs-form-row"><label class="vs-label" for="icLogin">最近多少天内登录过</label>'
            + '    <input type="number" class="vs-input" id="icLogin" min="1" max="365" step="1" placeholder="不填=不限制，例如 15"></div>'
            + '    <div class="vs-form-row vs-form-row--inline">'
            + '      <div><label class="vs-label" for="icCallsDays">最近几天有接口调用（最多 7 天）</label>'
            + '      <input type="number" class="vs-input" id="icCallsDays" min="1" max="7" step="1" placeholder="不填=不限制"></div>'
            + '      <div><label class="vs-label" for="icCallsMin">这几天内至少调用几次</label>'
            + '      <input type="number" class="vs-input" id="icCallsMin" min="1" step="1" placeholder="例如 1"></div>'
            + '    </div>'
            + '    <div class="vs-form-row"><label class="vs-label" for="icKeycalls">历史总调用次数至少多少</label>'
            + '    <input type="number" class="vs-input" id="icKeycalls" min="1" step="1" placeholder="不填=不限制"></div>'
            + '    <div class="vs-form-row"><label class="vs-label" for="icSpent">历史累计花掉的积分至少多少</label>'
            + '    <input type="number" class="vs-input" id="icSpent" min="0" step="0.0001" placeholder="不填=不限制"></div>'
            + '    <div class="vs-form-row"><label class="vs-label" for="icUserIds">指定用户编号（可选）</label>'
            + '    <input type="text" class="vs-input" id="icUserIds" placeholder="多个用逗号或空格分开，最多 500 个" autocomplete="off"></div>'
            + '  </div>'
            + '  <div class="vs-incentive-custompop__foot">'
            + '    <button type="button" class="vs-btn vs-btn--default" data-close="1">取消</button>'
            + '    <button type="button" class="vs-btn vs-btn--primary" id="icCustomOk">确认并加入标签</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(customRoot);
        customRoot.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-close') === '1') {
                closeCustomEditor();
            }
        });
        var ok = customRoot.querySelector('#icCustomOk');
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
        g('icLogin').value = d.login_within_days || '';
        g('icCallsDays').value = d.calls_within_days || '';
        g('icCallsMin').value = d.calls_min || '';
        g('icKeycalls').value = d.keycalls_min || '';
        g('icSpent').value = d.pointsspent_min || '';
        g('icUserIds').value = d.user_ids || '';
        root.hidden = false;
        document.body.classList.add('vs-incentive-custompop-open');
        if (opts.focusSpent && g('icSpent')) {
            setTimeout(function () {
                g('icSpent').focus();
            }, 50);
        }
        root._forceSpent = !!opts.forceSpent;
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
        var spent = (g('icSpent').value || '').trim();
        if (root._forceSpent && !spent) {
            modalAlert('请填写累计积分消耗下限', '自定义筛选');
            return;
        }
        var callsDays = (g('icCallsDays').value || '').trim();
        if (callsDays !== '' && Number(callsDays) > 7) {
            modalAlert('近 N 日调用仅支持 1～7 日', '筛选条件冲突');
            return;
        }
        var data = {
            login_within_days: (g('icLogin').value || '').trim(),
            calls_within_days: callsDays,
            calls_min: (g('icCallsMin').value || '').trim(),
            keycalls_min: (g('icKeycalls').value || '').trim(),
            pointsspent_min: spent,
            user_ids: (g('icUserIds').value || '').trim()
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
        if (data.user_ids) {
            parts.push('指定用户');
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
        // 自定义里的登录/调用与内置同维标签互斥：清掉冲突内置标签
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

    /* ---------- 筛选 / 赠送 ---------- */
    function applyFilterResult(data) {
        var n = data.matched_count || 0;
        lastFilter = {
            matched_count: n,
            can_execute: !!data.can_execute,
            sync_max: data.sync_max || 2000,
            sample_users: data.sample_users || [],
            warnings: data.warnings || []
        };
        filterToken = data.token || '';
        var lines = '命中 ' + n + ' 人（实数）';
        if (!lastFilter.can_execute && n > 0) {
            lines += ' · 超过一次上限 ' + lastFilter.sync_max + '，请收窄后再赠';
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
            if (!data || data.code !== 1) {
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
        hideTipBanner();
        setProgress('筛选中', 0, 0, 0);

        var fd = new FormData();
        fd.append('action', 'gift_filter_start');
        appendFilter(fd);

        VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || data.code !== 1) {
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
        }).catch(function (err) {
            setBusy(false);
            filterToken = '';
            lastFilter = null;
            hideProgress();
            var msg = (err && err.message) ? err.message : '筛选失败';
            if (els.filterText) {
                els.filterText.hidden = false;
                els.filterText.textContent = msg;
            }
            modalAlert(msg, '筛选失败');
        });
    }

    function tickGiftOnce() {
        var fd = new FormData();
        fd.append('action', 'gift_tick');
        fd.append('token', giftToken);
        return VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || data.code !== 1) {
                throw new Error((data && data.msg) || '推进失败');
            }
            setProgress('赠送中', data.done, data.total, data.percent);
            if (Number(data.finished) === 1) {
                return data;
            }
            return tickGiftOnce();
        });
    }

    function runExecute() {
        if (busy) {
            return;
        }
        if (!lastFilter || !filterToken) {
            modalAlert('请先点击「筛选」，确认命中人数后再赠送。', '尚未筛选');
            return;
        }
        if (!lastFilter.can_execute || !(lastFilter.matched_count > 0)) {
            modalAlert('当前筛选结果不可赠送，请收窄条件后重新筛选。', '无法赠送');
            return;
        }
        hideTipBanner();

        var remark = els.remark ? els.remark.value.trim() : '';
        var amount = els.amount ? els.amount.value : '';
        var n = lastFilter.matched_count || 0;
        if (!amount || Number(amount) < 1) {
            modalAlert('请填写有效的赠送积分', '提示');
            return;
        }
        if (!remark) {
            modalAlert('请填写赠送理由', '提示');
            return;
        }

        var perAmt = Number(amount) || 0;
        var totalPts = n * perAmt;
        // 与服务端 IncentiveManager::riskLevel 对齐（含合计积分阈值），避免前端弱确认而后端拒高危
        var needStrong = !!(selected.include_banned)
            || (n > 200 && !selected.developer && !selected.user)
            || n > 2000
            || (totalPts > 500000);
        var msg = '将向约 ' + n + ' 人各赠送 ' + amount + ' 积分（合计约 ' + totalPts + '）。\n理由：' + remark;

        modalConfirm(msg, needStrong ? '高危确认' : '确认赠送', {
            danger: needStrong,
            confirmText: '确认赠送',
            cancelText: '取消'
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            setBusy(true);
            setProgress('赠送中', 0, n, 0);

            var fd = new FormData();
            fd.append('action', 'gift_start');
            fd.append('amount', amount);
            fd.append('remark', remark);
            fd.append('filter_count', String(n));
            fd.append('filter_token', filterToken);
            // 高危必须带确认文案；非高危也带上无害，防前后端判定临界差一档
            fd.append('confirm_text', '确认赠送');
            appendFilter(fd);

            return VS.postForm(fd, window.location.href).then(function (data) {
                if (!data || data.code !== 1 || !data.token) {
                    throw new Error((data && data.msg) || '启动失败');
                }
                giftToken = data.token;
                filterToken = '';
                setProgress('赠送中', data.done || 0, data.total || n, data.percent || 0);
                return tickGiftOnce();
            }).then(function (data) {
                setBusy(false);
                giftToken = '';
                lastFilter = null;
                filterToken = '';
                var okN = (data && data.success_count) != null ? Number(data.success_count) : n;
                var failN = (data && data.fail_count) ? Number(data.fail_count) : 0;
                var per = Number(amount) || 0;
                var totalPts = okN * per;
                setProgress('赠送完成', (data && data.done) || n, (data && data.total) || n, 100);
                if (els.filterText) {
                    els.filterText.hidden = false;
                    var detail = '本次成功 ' + okN + ' 人'
                        + (failN > 0 ? ('，未入账 ' + failN) : '')
                        + ' · 共赠送积分 ' + totalPts
                        + ' · 请到「交易财务 → 积分变动」核对';
                    els.filterText.textContent = detail;
                }
                if (VS.showMessage) {
                    VS.showMessage((data && data.msg) || '赠送完成', 'success');
                }
            }).catch(function (err) {
                setBusy(false);
                giftToken = '';
                var errMsg = (err && err.message) ? err.message : '执行失败';
                if (els.filterText) {
                    els.filterText.hidden = false;
                    els.filterText.textContent = errMsg + '。已入账部分可在「积分变动」核对。';
                }
                modalAlert(errMsg, '赠送失败');
            });
        });
    }

    /* ---------- 折叠：手机/电脑均默认收起，点标题展开（E437 UI） ---------- */
    if (els.foldBar && els.fold) {
        els.fold.classList.remove('is-open');
        els.foldBar.setAttribute('aria-expanded', 'false');
        var giftPeek = document.getElementById('incentivePeek');
        if (giftPeek) {
            giftPeek.setAttribute('aria-hidden', 'false');
        }
        els.foldBar.addEventListener('click', function () {
            els.fold.classList.toggle('is-open');
            var open = els.fold.classList.contains('is-open');
            els.foldBar.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (giftPeek) {
                giftPeek.setAttribute('aria-hidden', open ? 'true' : 'false');
            }
        });
    }

    if (els.trigger) {
        els.trigger.addEventListener('click', function () {
            if (busy) {
                return;
            }
            openTagPop();
        });
    }
    if (els.tagBackdrop) {
        els.tagBackdrop.addEventListener('click', closeTagPop);
    }
    if (els.tagClose) {
        els.tagClose.addEventListener('click', closeTagPop);
    }
    if (els.tagClear) {
        els.tagClear.addEventListener('click', function () {
            draftSelected = {};
            renderTagGrid();
        });
    }
    if (els.tagOk) {
        els.tagOk.addEventListener('click', applyDraftTags);
    }
    if (els.filterBtn) {
        els.filterBtn.addEventListener('click', runFilter);
    }
    if (els.executeBtn) {
        els.executeBtn.addEventListener('click', runExecute);
    }
    if (els.remark) {
        els.remark.addEventListener('input', clampRemark);
        els.remark.addEventListener('blur', clampRemark);
    }

    renderSelectedChips();
})();


/* 模块二：全站优惠（滑动/双 Tab 互斥；一行表单；自渲染到期时间；默认折叠） */
(function () {
    'use strict';
    var form = document.getElementById('incentivePromoForm');
    if (!form || !window.VS || typeof VS.postForm !== 'function') {
        return;
    }

    var els = {
        fold: document.getElementById('incentivePromoFold'),
        foldBar: document.getElementById('incentivePromoFoldBar'),
        peek: document.getElementById('incentivePromoPeek'),
        status: document.getElementById('promoStatusBar'),
        foldMeta: document.getElementById('promoFoldMeta'),
        modeSwitch: document.getElementById('promoModeSwitch'),
        modeValue: document.getElementById('promoModeValue'),
        tabDiscount: document.getElementById('promoTabDiscount'),
        tabFree: document.getElementById('promoTabFree'),
        fieldsRow: document.getElementById('promoFieldsRow'),
        zheWrap: document.getElementById('promoZheWrap'),
        zhe: document.getElementById('promoZhe'),
        expire: document.getElementById('promoExpire'),
        expireBtn: document.getElementById('promoExpireBtn'),
        label: document.getElementById('promoLabel'),
        saveBtn: document.getElementById('promoSaveBtn'),
        resetBtn: document.getElementById('promoResetBtn'),
        dtPicker: document.getElementById('promoDatetimePicker'),
        dtTitle: document.getElementById('promoDtTitle'),
        dtDays: document.getElementById('promoDtDays'),
        dtHour: document.getElementById('promoExpireHour'),
        dtMinute: document.getElementById('promoExpireMinute'),
        dtOk: document.getElementById('promoDtOk'),
        dtClear: document.getElementById('promoDtClear')
    };
    var busy = false;
    var state = window.__VS_INCENTIVE_PROMO__ || { mode: 'off', active: false };
    var dtView = { y: 0, m: 0 };
    var dtSelected = null;

    function currentMode() {
        var v = els.modeValue ? String(els.modeValue.value || '') : 'discount';
        return v === 'free' ? 'free' : 'discount';
    }

    function setMode(mode) {
        mode = mode === 'free' ? 'free' : 'discount';
        if (els.modeValue) {
            els.modeValue.value = mode;
        }
        if (els.modeSwitch) {
            els.modeSwitch.classList.toggle('is-free', mode === 'free');
            els.modeSwitch.classList.toggle('is-discount', mode === 'discount');
        }
        if (els.tabDiscount) {
            els.tabDiscount.classList.toggle('is-active', mode === 'discount');
            els.tabDiscount.setAttribute('aria-selected', mode === 'discount' ? 'true' : 'false');
        }
        if (els.tabFree) {
            els.tabFree.classList.toggle('is-active', mode === 'free');
            els.tabFree.setAttribute('aria-selected', mode === 'free' ? 'true' : 'false');
        }
        if (els.fieldsRow) {
            els.fieldsRow.classList.toggle('is-free', mode === 'free');
        }
        if (els.zheWrap) {
            els.zheWrap.hidden = mode !== 'discount';
        }
        if (els.zhe) {
            els.zhe.disabled = mode !== 'discount';
        }
    }

    function formatUntilShort(expireAt) {
        var s = String(expireAt || '').trim().replace('T', ' ');
        if (!s) {
            return '';
        }
        var m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{2}))/);
        if (!m) {
            return s;
        }
        return parseInt(m[2], 10) + '月' + parseInt(m[3], 10) + '日'
            + (m[4] != null ? (' ' + pad2(m[4]) + ':' + pad2(m[5])) : '');
    }

    function formatZheFromPercent(pct) {
        pct = parseInt(pct, 10) || 0;
        if (!pct) {
            return '?';
        }
        return pct % 10 === 0 ? String(pct / 10) : String(pct / 10);
    }

    /** 折叠标题旁摘要：默认折叠时一眼看见生效优惠 */
    function renderFoldMeta(st) {
        if (!els.foldMeta) {
            return;
        }
        var hint = '';
        var kind = '';
        if (st && st.active) {
            var until = formatUntilShort(st.expire_at);
            if (st.mode === 'free') {
                hint = '全站免费' + (until ? ('至' + until) : '');
                kind = 'is-free';
            } else if (st.mode === 'discount') {
                hint = '打' + formatZheFromPercent(st.percent_pay) + '折' + (until ? ('至' + until) : '');
                kind = 'is-discount';
            }
        } else if (st && st.expired_hint) {
            hint = '已过期';
            kind = 'is-expired';
        }
        if (!hint) {
            els.foldMeta.hidden = true;
            els.foldMeta.className = 'vs-incentive-fold__meta';
            els.foldMeta.textContent = '';
            return;
        }
        els.foldMeta.hidden = false;
        els.foldMeta.className = 'vs-incentive-fold__meta is-on' + (kind ? (' ' + kind) : '');
        els.foldMeta.innerHTML = '<span class="vs-incentive-fold__meta-dash" aria-hidden="true">—</span>'
            + '<span class="vs-incentive-fold__meta-text"></span>';
        var textEl = els.foldMeta.querySelector('.vs-incentive-fold__meta-text');
        if (textEl) {
            textEl.textContent = hint;
        }
    }

    function renderStatus(st) {
        state = st || state;
        renderFoldMeta(st);
        if (!els.status) {
            return;
        }
        els.status.className = 'vs-incentive-promo__status';
        if (st && st.expired_hint) {
            els.status.classList.add('is-expired');
            els.status.textContent = '当前配置已过期，调用已按原价；可重新确认开启或点重置清空。';
            return;
        }
        if (st && st.active) {
            els.status.classList.add('is-active');
            if (st.mode === 'free') {
                els.status.textContent = '生效中：全站免费' + (st.expire_at ? ('，至 ' + st.expire_at) : '') + (st.label ? ('（' + st.label + '）') : '');
            } else if (st.mode === 'discount') {
                var z = formatZheFromPercent(st.percent_pay);
                els.status.textContent = '生效中：打' + z + '折' + (st.expire_at ? ('，至 ' + st.expire_at) : '') + (st.label ? ('（' + st.label + '）') : '');
            } else {
                els.status.textContent = '生效中';
            }
            return;
        }
        els.status.textContent = '未开启全站优惠，调用按各接口后台原价。';
    }

    function modalConfirm(msg, title, options) {
        if (window.VsModal && typeof VsModal.confirm === 'function') {
            return VsModal.confirm(msg, title || '操作确认', options || {});
        }
        return Promise.resolve(false);
    }

    function toast(msg, ok) {
        if (window.VS && typeof VS.showMessage === 'function') {
            VS.showMessage(msg, ok ? 'success' : 'error');
        }
    }

    function pad2(n) {
        n = parseInt(n, 10);
        if (isNaN(n) || n < 0) {
            n = 0;
        }
        return (n < 10 ? '0' : '') + n;
    }

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

    function syncExpireBtn() {
        if (!els.expireBtn) {
            return;
        }
        var v = els.expire ? normalizeExpireValue(els.expire.value) : '';
        els.expireBtn.textContent = v ? v : '点击选择到期时间';
    }

    function setSelectValue(sel, val) {
        if (!sel) {
            return;
        }
        sel.value = pad2(val);
        if (window.VSPick && typeof window.VSPick.refresh === 'function') {
            window.VSPick.refresh(sel);
        }
    }

    function closeExpirePanel() {
        if (!els.dtPicker) {
            return;
        }
        els.dtPicker.classList.remove('is-visible');
        els.dtPicker.classList.remove('is-open');
        els.dtPicker.hidden = true;
        els.dtPicker.setAttribute('aria-hidden', 'true');
        if (els.expireBtn) {
            els.expireBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function renderExpireDays() {
        if (!els.dtDays) {
            return;
        }
        var y = dtView.y;
        var m = dtView.m;
        if (els.dtTitle) {
            els.dtTitle.textContent = y + '年' + (m + 1) + '月';
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
            var isSel = dtSelected && dtSelected.y === y && dtSelected.m === m && dtSelected.d === i;
            html += '<button type="button" class="vs-datetime__day' + (isSel ? ' is-selected' : '') + '" data-promo-dt-day="'
                + i + '">' + i + '</button>';
        }
        els.dtDays.innerHTML = html;
    }

    function openExpirePanel() {
        if (!els.dtPicker || !els.expireBtn) {
            return;
        }
        var now = new Date();
        var raw = els.expire ? normalizeExpireValue(els.expire.value) : '';
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
        setSelectValue(els.dtHour, isNaN(hh) ? 0 : hh);
        setSelectValue(els.dtMinute, isNaN(mm) ? 0 : mm);
        renderExpireDays();
        if (els.dtPicker.parentNode !== document.body) {
            document.body.appendChild(els.dtPicker);
        }
        els.dtPicker.classList.add('vs-nested-picker--viewport');
        els.dtPicker.hidden = false;
        els.dtPicker.setAttribute('aria-hidden', 'false');
        els.dtPicker.classList.add('is-open');
        requestAnimationFrame(function () {
            els.dtPicker.classList.add('is-visible');
        });
        els.expireBtn.setAttribute('aria-expanded', 'true');
        if (window.VSPick && typeof window.VSPick.init === 'function') {
            window.VSPick.init(els.dtPicker);
        }
    }

    function applyExpireSelection() {
        if (!dtSelected) {
            return;
        }
        var hh = els.dtHour ? els.dtHour.value : '00';
        var mm = els.dtMinute ? els.dtMinute.value : '00';
        var val = dtSelected.y + '-' + pad2(dtSelected.m + 1) + '-' + pad2(dtSelected.d)
            + ' ' + pad2(hh) + ':' + pad2(mm);
        if (els.expire) {
            els.expire.value = val;
        }
        syncExpireBtn();
        closeExpirePanel();
    }

    function expireLocalValue() {
        var v = els.expire ? normalizeExpireValue(els.expire.value) : '';
        if (!v) {
            return '';
        }
        if (v.length === 16) {
            v += ':00';
        }
        return v;
    }

    function savePromo() {
        if (busy) {
            return;
        }
        var mode = currentMode();
        var expireAt = expireLocalValue();
        if (!expireAt) {
            toast('请选择到期时间', false);
            return;
        }
        var label = els.label ? String(els.label.value || '').trim() : '';
        var tip = mode === 'free'
            ? '确认开启全站免费？到期前所有收费接口调用将不扣积分（不改接口库价）。'
            : '确认开启全站折扣？到期前调用按折扣计价（不改接口库价）。';
        modalConfirm(tip, '确认全站优惠').then(function (ok) {
            if (!ok) {
                return;
            }
            busy = true;
            var fd = new FormData();
            fd.append('action', mode === 'free' ? 'promo_save_free' : 'promo_save_discount');
            fd.append('expire_at', expireAt);
            fd.append('label', label);
            if (mode === 'discount') {
                fd.append('zhe', els.zhe ? String(els.zhe.value || '').trim() : '');
            }
            VS.postForm(fd, window.location.href).then(function (res) {
                busy = false;
                if (!res || Number(res.code) !== 1) {
                    toast((res && res.msg) || '保存失败', false);
                    return;
                }
                toast(res.msg || '已保存', true);
                renderStatus(res);
                if (res.mode === 'free' || res.mode === 'discount') {
                    setMode(res.mode);
                }
            }).catch(function () {
                busy = false;
                toast('网络异常', false);
            });
        });
    }

    function resetPromo() {
        if (busy) {
            return;
        }
        modalConfirm('确认重置全站优惠？重置后立即按原价计费。', '重置优惠').then(function (ok) {
            if (!ok) {
                return;
            }
            busy = true;
            var fd = new FormData();
            fd.append('action', 'promo_off');
            VS.postForm(fd, window.location.href).then(function (res) {
                busy = false;
                if (!res || Number(res.code) !== 1) {
                    toast((res && res.msg) || '重置失败', false);
                    return;
                }
                toast(res.msg || '已重置', true);
                renderStatus(res);
                if (els.zhe) {
                    els.zhe.value = '';
                }
                if (els.label) {
                    els.label.value = '';
                }
                if (els.expire) {
                    els.expire.value = '';
                }
                syncExpireBtn();
            }).catch(function () {
                busy = false;
                toast('网络异常', false);
            });
        });
    }

    if (els.foldBar && els.fold) {
        els.fold.classList.remove('is-open');
        els.foldBar.setAttribute('aria-expanded', 'false');
        if (els.peek) {
            els.peek.setAttribute('aria-hidden', 'false');
        }
        els.foldBar.addEventListener('click', function () {
            els.fold.classList.toggle('is-open');
            var open = els.fold.classList.contains('is-open');
            els.foldBar.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (els.peek) {
                els.peek.setAttribute('aria-hidden', open ? 'true' : 'false');
            }
        });
    }

    if (els.tabDiscount) {
        els.tabDiscount.addEventListener('click', function () {
            setMode('discount');
        });
    }
    if (els.tabFree) {
        els.tabFree.addEventListener('click', function () {
            setMode('free');
        });
    }
    if (els.expireBtn) {
        els.expireBtn.addEventListener('click', openExpirePanel);
    }
    if (els.dtPicker) {
        els.dtPicker.addEventListener('click', function (e) {
            var t = e.target;
            if (!t) {
                return;
            }
            if (t.getAttribute && t.getAttribute('data-promo-dt-close') === '1') {
                closeExpirePanel();
                return;
            }
            var nav = t.getAttribute && t.getAttribute('data-promo-dt-nav');
            if (nav) {
                var step = parseInt(nav, 10) || 0;
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
            var dayBtn = t.closest ? t.closest('[data-promo-dt-day]') : null;
            if (dayBtn) {
                var day = parseInt(dayBtn.getAttribute('data-promo-dt-day'), 10);
                if (!isNaN(day)) {
                    dtSelected = { y: dtView.y, m: dtView.m, d: day };
                    renderExpireDays();
                }
            }
        });
    }
    if (els.dtOk) {
        els.dtOk.addEventListener('click', applyExpireSelection);
    }
    if (els.dtClear) {
        els.dtClear.addEventListener('click', function () {
            if (els.expire) {
                els.expire.value = '';
            }
            syncExpireBtn();
            closeExpirePanel();
        });
    }
    if (els.saveBtn) {
        els.saveBtn.addEventListener('click', savePromo);
    }
    if (els.resetBtn) {
        els.resetBtn.addEventListener('click', resetPromo);
    }

    setMode(currentMode());
    syncExpireBtn();
    renderStatus(state);
})();
