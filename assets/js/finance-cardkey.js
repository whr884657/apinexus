/**
 * 文件：assets/js/finance-cardkey.js
 * 作用：管理员卡密列表（桌面分列对齐令牌 + 手机卡右上角徽章 + 复选双向）
 */
(function () {
    'use strict';
    var pageRoot = document.getElementById('cardkeyPage');
    var body = document.getElementById('cardkeyListBody');
    var footer = document.getElementById('cardkeyFooter');
    var pagerNums = document.getElementById('cardkeyPagerNums');
    var prevBtn = document.getElementById('cardkeyPrevBtn');
    var nextBtn = document.getElementById('cardkeyNextBtn');
    var totalEl = document.getElementById('cardkeyTotal');
    var pageSizeEl = document.getElementById('cardkeyPageSize');
    var refreshBtn = document.getElementById('cardkeyRefreshBtn');
    var searchInput = document.getElementById('cardkeySearchInput');
    var searchBtn = document.getElementById('cardkeySearchBtn');
    var addBtn = document.getElementById('cardkeyAddBtn');
    var exportBtn = document.getElementById('cardkeyExportBtn');
    var voidBtn = document.getElementById('cardkeyVoidBtn');
    var overlay = document.getElementById('cardkeyFormOverlay');
    var genResult = document.getElementById('cardkeyGenResult');
    var genCodes = document.getElementById('cardkeyGenCodes');
    var genCountEl = document.getElementById('cardkeyGenCount');
    var generateBtn = document.getElementById('cardkeyGenerateBtn');
    var copyAllBtn = document.getElementById('cardkeyCopyAllBtn');

    var page = 1;
    var q = '';
    var statusBucket = 'all';
    var hasMore = false;
    var totalCount = 0;
    var totalPages = 1;
    var loadSeq = 0;
    var listAbort = null;
    var lastList = [];
    var selected = {};
    var genBusy = false;

    var COPY_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getPageSize() {
        var n = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 20;
        if (!n || n < 1) n = 20;
        return Math.min(50, n);
    }

    function resetList() {
        page = 1;
        hasMore = false;
        totalPages = 1;
        selected = {};
        syncSelectionUi();
    }

    function syncFilterButtons() {
        document.querySelectorAll('#cardkeyToolbar .vs-points-seg__btn').forEach(function (btn) {
            var on = btn.getAttribute('data-status') === statusBucket;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function setControlsDisabled(disabled) {
        if (refreshBtn) {
            if (disabled) {
                refreshBtn.setAttribute('aria-disabled', 'true');
            } else if (window.VsRefreshBtn) {
                VsRefreshBtn.stop(refreshBtn);
            } else {
                refreshBtn.disabled = false;
                refreshBtn.classList.remove('is-spinning');
                refreshBtn.removeAttribute('aria-disabled');
            }
        }
        if (searchBtn) searchBtn.disabled = !!disabled;
        if (searchInput) searchInput.disabled = !!disabled;
        if (pageSizeEl) pageSizeEl.disabled = !!disabled;
        if (addBtn) addBtn.disabled = !!disabled;
        document.querySelectorAll('#cardkeyToolbar .vs-points-seg__btn').forEach(function (btn) {
            btn.disabled = !!disabled;
        });
    }

    function openOverlay() {
        if (!overlay) return;
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
        genBusy = false;
        if (generateBtn) generateBtn.disabled = false;
        if (genResult) genResult.hidden = true;
        if (genCodes) genCodes.value = '';
        if (genCountEl) genCountEl.textContent = '0';
    }

    function closeOverlay() {
        if (!overlay) return;
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-open');
        if (!document.querySelector('.vs-overlay.is-open')) {
            document.body.classList.remove('is-overlay-open');
        }
    }

    function copyText(text, okMsg) {
        var t = String(text || '');
        if (!t) {
            if (VS.showMessage) VS.showMessage('没有可复制内容', 'warning');
            return;
        }
        function ok() {
            if (VS.showMessage) VS.showMessage(okMsg || '已复制', 'success');
        }
        function fail() {
            if (VS.showMessage) VS.showMessage('复制失败，请手动选择', 'error');
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(ok).catch(fail);
            return;
        }
        try {
            var ta = document.createElement('textarea');
            ta.value = t;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            ok();
        } catch (e) {
            fail();
        }
    }

    function selectedCount() {
        return Object.keys(selected).length;
    }

    function selectedRows() {
        return lastList.filter(function (row) {
            return selected[String(row.id)];
        });
    }

    function pageAllSelected() {
        if (!lastList.length) return false;
        for (var i = 0; i < lastList.length; i++) {
            if (!selected[String(lastList[i].id)]) return false;
        }
        return true;
    }

    function syncSelectionUi() {
        var n = selectedCount();
        if (exportBtn) exportBtn.disabled = n === 0;
        if (voidBtn) voidBtn.disabled = n === 0;
        var all = document.getElementById('cardkeyCheckAll');
        if (all) {
            all.checked = pageAllSelected();
            all.indeterminate = n > 0 && !all.checked && lastList.some(function (r) {
                return selected[String(r.id)];
            });
        }
    }

    function exportTxt() {
        var rows = selectedRows();
        if (!rows.length) {
            if (VS.showMessage) VS.showMessage('请先勾选卡密', 'warning');
            return;
        }
        var lines = rows.map(function (r) {
            return '卡密 ' + r.code + ' 积分 ' + r.points;
        });
        var blob = new Blob([lines.join('\n') + '\n'], { type: 'text/plain;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'cardkeys-' + Date.now() + '.txt';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(a.href);
            document.body.removeChild(a);
        }, 0);
        if (VS.showMessage) VS.showMessage('已导出 ' + rows.length + ' 条', 'success');
    }

    function voidSelected() {
        var rows = selectedRows().filter(function (r) {
            return r.status === 1 || r.status === 4;
        });
        if (!rows.length) {
            if (VS.showMessage) VS.showMessage('请勾选未使用或已发放的卡密', 'warning');
            return;
        }
        var doVoid = function () {
            var fd = new FormData();
            fd.append('action', 'void');
            rows.forEach(function (r) {
                fd.append('ids[]', String(r.id));
            });
            VS.postForm(fd, window.location.href).then(function (data) {
                if (!data || data.code !== 1) {
                    if (VS.showMessage) VS.showMessage((data && data.msg) || '作废失败', 'error');
                    return;
                }
                if (VS.showMessage) VS.showMessage(data.msg || '已作废', 'success');
                selected = {};
                syncSelectionUi();
                loadStats();
                load();
            }).catch(function () {
                if (VS.showMessage) VS.showMessage('网络异常', 'error');
            });
        };
        if (window.VsModal && VsModal.confirm) {
            VsModal.confirm('将作废 ' + rows.length + ' 张卡密，确认？', '作废卡密', { danger: true }).then(function (ok) {
                if (ok) doVoid();
            });
        } else if (window.confirm('将作废 ' + rows.length + ' 张卡密，确认？')) {
            doVoid();
        }
    }

    function voidOne(id) {
        var row = null;
        for (var i = 0; i < lastList.length; i++) {
            if (String(lastList[i].id) === String(id)) {
                row = lastList[i];
                break;
            }
        }
        if (!row || (row.status !== 1 && row.status !== 4)) {
            if (VS.showMessage) VS.showMessage('仅未使用或已发放卡密可作废', 'warning');
            return;
        }
        selected = {};
        selected[String(id)] = true;
        syncSelectionUi();
        voidSelected();
    }

    function loadStats() {
        var fd = new FormData();
        fd.append('action', 'stats');
        VS.postForm(fd, window.location.href).then(function (data) {
            if (!data || data.code !== 1) return;
            var root = document.getElementById('cardkeyStats');
            if (!root) return;
            var unused = root.querySelector('[data-stat="unused"] strong');
            var issued = root.querySelector('[data-stat="issued"] strong');
            var used = root.querySelector('[data-stat="used"] strong');
            var points = root.querySelector('[data-stat="points"] strong');
            if (unused) unused.textContent = String(data.unused || 0);
            if (issued) issued.textContent = String(data.issued || 0);
            if (used) used.textContent = String(data.used || 0);
            if (points) points.textContent = String(data.unused_points || 0);
        }).catch(function () { /* ignore */ });
    }

    function emptyHint() {
        if (q) return '未找到匹配的卡密';
        if (statusBucket === 'unused') return '暂无未使用卡密';
        if (statusBucket === 'issued') return '暂无已发放卡密';
        if (statusBucket === 'used') return '暂无已使用卡密';
        if (statusBucket === 'void') return '暂无已作废卡密';
        return '暂无卡密，点击右上角「添加」生成';
    }

    function statusBadgesHtml(row) {
        var st = Number(row.status);
        if (st === 4) {
            // 已发放 = 仍未兑 + 已从库存出库给商城（双标签）
            return '<span class="vs-cardkey-status-tags">'
                + '<span class="vs-badge vs-badge--success">未使用</span>'
                + '<span class="vs-badge vs-badge--warning">已发放</span>'
                + '</span>';
        }
        var mod = 'vs-badge--default';
        var label = row.status_label || '';
        if (st === 1) {
            mod = 'vs-badge--success';
            label = label || '未使用';
        } else if (st === 2) {
            mod = 'vs-badge--default';
            label = label || '已使用';
        } else if (st === 3) {
            mod = 'vs-badge--error';
            label = label || '已作废';
        }
        return '<span class="vs-badge ' + mod + '">' + escapeHtml(label) + '</span>';
    }

    function copyBtnHtml(code) {
        return '<button type="button" class="key-cell__copy vs-key-copy vs-cardkey-copy" data-copy="'
            + escapeHtml(code) + '" title="复制" aria-label="复制卡密">' + COPY_SVG + '</button>';
    }

    function desktopHead() {
        return '<thead><tr>'
            + '<th class="vs-cardkey-th-check"><input type="checkbox" id="cardkeyCheckAll" aria-label="全选本页"></th>'
            + '<th>卡密</th><th>积分</th><th>状态</th><th>用户</th><th>生成时间</th><th>兑换时间</th><th class="vs-col-actions">操作</th>'
            + '</tr></thead>';
    }

    function desktopRow(row) {
        var id = String(row.id);
        var checked = selected[id] ? ' checked' : '';
        var userLabel = row.userid ? escapeHtml(row.username || ('#' + row.userid)) : '—';
        var voidBtnHtml = (row.status === 1 || row.status === 4)
            ? '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cardkey-void-one" data-id="' + id + '">作废</button>'
            : '—';
        return '<tr data-id="' + id + '">'
            + '<td class="vs-cardkey-td-check"><input type="checkbox" class="vs-cardkey-row-check" data-id="' + id + '"' + checked + ' aria-label="选择卡密"></td>'
            + '<td><div class="key-cell"><div class="key-cell__row">'
            + '<code class="key-cell__code vs-cardkey-secret" tabindex="0" data-copy="' + escapeHtml(row.code) + '">' + escapeHtml(row.code) + '</code>'
            + copyBtnHtml(row.code)
            + '</div></div></td>'
            + '<td class="vs-cardkey-stat-cell"><span class="vs-ledger-amount is-inc">+' + escapeHtml(row.points) + '</span></td>'
            + '<td>' + statusBadgesHtml(row) + '</td>'
            + '<td><span class="vs-cardkey-user">' + userLabel + '</span></td>'
            + '<td class="vs-cardkey-stat-cell"><span class="time-cell">' + escapeHtml(row.createtime || '—') + '</span></td>'
            + '<td class="vs-cardkey-stat-cell"><span class="time-cell">' + escapeHtml(row.usetime || '—') + '</span></td>'
            + '<td class="vs-col-actions"><div class="action-btns">' + voidBtnHtml + '</div></td>'
            + '</tr>';
    }

    function mobileCard(row) {
        var id = String(row.id);
        var voidHtml = (row.status === 1 || row.status === 4)
            ? '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cardkey-void-one" data-id="' + id + '">作废</button>'
            : '';
        var footMeta = escapeHtml(row.createtime || '—');
        if (row.userid) {
            footMeta += ' · ' + escapeHtml(row.username || ('#' + row.userid));
        }
        return '<article class="vs-cardkey-mcard" data-id="' + id + '">'
            + '<div class="vs-cardkey-mcard__top">'
            + '<code class="vs-cardkey-mcard__code vs-cardkey-secret" tabindex="0" data-copy="' + escapeHtml(row.code) + '">' + escapeHtml(row.code) + '</code>'
            + '<span class="vs-cardkey-mcard__pts vs-ledger-amount is-inc">+' + escapeHtml(row.points) + '</span>'
            + statusBadgesHtml(row)
            + '</div>'
            + '<div class="vs-cardkey-mcard__foot">'
            + '<span class="vs-cardkey-mcard__time">' + footMeta + '</span>'
            + '<div class="vs-cardkey-mcard__actions">'
            + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-cardkey-copy-text" data-copy="'
            + escapeHtml(row.code) + '">复制</button>'
            + voidHtml
            + '</div>'
            + '</div>'
            + '</article>';
    }

    function renderPagerNums() {
        if (!pagerNums) return;
        if (totalPages <= 1) {
            pagerNums.innerHTML = '';
            return;
        }
        // 中间最多 3 个页码：当前尽量居中（首尾贴边）
        var start = Math.max(1, page - 1);
        var end = Math.min(totalPages, start + 2);
        start = Math.max(1, end - 2);
        var html = '';
        var i;
        for (i = start; i <= end; i += 1) {
            html += '<button type="button" class="vs-api-pager__num'
                + (i === page ? ' is-active' : '')
                + '" data-page="' + i + '">' + i + '</button>';
        }
        pagerNums.innerHTML = html;
    }

    function renderPager() {
        if (footer) footer.hidden = false;
        if (totalEl) totalEl.textContent = '共 ' + (totalCount || 0) + ' 条';
        totalPages = Math.max(1, Math.ceil((totalCount || 0) / getPageSize()) || 1);
        if (page > totalPages) page = totalPages;
        hasMore = page < totalPages;
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = !hasMore;
        renderPagerNums();
    }

    function bindListEvents() {
        if (!body) return;
        var all = document.getElementById('cardkeyCheckAll');
        if (all) {
            all.addEventListener('change', function () {
                var on = !!all.checked;
                lastList.forEach(function (row) {
                    if (on) selected[String(row.id)] = true;
                    else delete selected[String(row.id)];
                });
                body.querySelectorAll('.vs-cardkey-row-check').forEach(function (cb) {
                    cb.checked = on;
                });
                syncSelectionUi();
            });
        }
        body.querySelectorAll('.vs-cardkey-row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var id = cb.getAttribute('data-id');
                if (cb.checked) selected[id] = true;
                else delete selected[id];
                syncSelectionUi();
            });
        });
        body.querySelectorAll('.vs-cardkey-copy, .vs-key-copy, .vs-cardkey-copy-text').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                copyText(btn.getAttribute('data-copy') || '', '卡密已复制');
            });
        });
        body.querySelectorAll('.vs-cardkey-void-one').forEach(function (btn) {
            btn.addEventListener('click', function () {
                voidOne(btn.getAttribute('data-id'));
            });
        });
    }

    function load() {
        if (!body) return;
        if (!window.VS) {
            setTimeout(load, 40);
            return;
        }
        if (listAbort) {
            try { listAbort.abort(); } catch (e) { /* ignore */ }
        }
        listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var seq = ++loadSeq;
        var pagesize = getPageSize();
        setControlsDisabled(true);
        if (VS.setLoading) VS.setLoading(body, '正在加载卡密');

        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('pagesize', String(pagesize));
        fd.append('before_id', '0');
        fd.append('status', statusBucket);
        if (q) fd.append('q', q);

        var opts = listAbort ? { signal: listAbort.signal } : {};
        VS.postForm(fd, window.location.href, opts).then(function (data) {
            if (seq !== loadSeq) return;
            setControlsDisabled(false);
            if (!data || data.code !== 1) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml((data && data.msg) || '加载失败') + '</p>';
                return;
            }
            hasMore = !!data.has_more;
            totalCount = parseInt(data.total, 10) || 0;
            lastList = data.list || [];
            if (!lastList.length) {
                body.innerHTML = '<p class="vs-empty vs-finance-empty">' + escapeHtml(emptyHint()) + '</p>';
            } else {
                body.innerHTML = '<div class="vs-cardkey-desktop">'
                    + '<div class="vs-api-list-table-card vs-api-list-table-wrap"><table class="vs-table vs-cardkey-table">'
                    + desktopHead() + '<tbody>' + lastList.map(desktopRow).join('') + '</tbody></table></div></div>'
                    + '<div class="vs-cardkey-mobile">' + lastList.map(mobileCard).join('') + '</div>';
                bindListEvents();
            }
            syncSelectionUi();
            renderPager();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') return;
            if (seq !== loadSeq) return;
            setControlsDisabled(false);
            body.innerHTML = '<p class="vs-empty vs-finance-empty">网络异常</p>';
        });
    }

    function goPage(target) {
        var p = parseInt(target, 10);
        if (!p || p < 1) return;
        if (p === page) return;
        if (totalPages > 0 && p > totalPages) return;
        page = p;
        load();
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (prevBtn.disabled) return;
            goPage(page - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (nextBtn.disabled) return;
            goPage(page + 1);
        });
    }
    if (pagerNums) {
        pagerNums.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-page]');
            if (!btn) return;
            goPage(btn.getAttribute('data-page'));
        });
    }

    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            resetList();
            load();
        });
    }

    document.querySelectorAll('#cardkeyToolbar .vs-points-seg__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var st = btn.getAttribute('data-status') || 'all';
            if (st === statusBucket) return;
            statusBucket = st;
            syncFilterButtons();
            resetList();
            load();
        });
    });

    function doSearch() {
        q = searchInput ? String(searchInput.value || '').trim() : '';
        resetList();
        load();
    }
    if (searchBtn) searchBtn.addEventListener('click', doSearch);
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            if (window.VsRefreshBtn) VsRefreshBtn.start(refreshBtn);
            loadStats();
            load();
        });
    }

    if (addBtn) addBtn.addEventListener('click', openOverlay);
    if (exportBtn) exportBtn.addEventListener('click', exportTxt);
    if (voidBtn) voidBtn.addEventListener('click', voidSelected);

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target.closest('[data-overlay-close]')) {
                closeOverlay();
            }
        });
    }

    function runGenerate() {
        if (genBusy) return;
        var countEl = document.getElementById('cardkeyCount');
        var pointsEl = document.getElementById('cardkeyPoints');
        var count = countEl ? parseInt(countEl.value, 10) : 0;
        var points = pointsEl ? parseInt(pointsEl.value, 10) : 0;
        genBusy = true;
        if (generateBtn) generateBtn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'generate');
        fd.append('count', String(count));
        fd.append('points', String(points));
        VS.postForm(fd, window.location.href).then(function (data) {
            genBusy = false;
            if (generateBtn) generateBtn.disabled = false;
            if (!data || data.code !== 1) {
                if (VS.showMessage) VS.showMessage((data && data.msg) || '生成失败', 'error');
                return;
            }
            var list = data.list || [];
            if (genCountEl) genCountEl.textContent = String(list.length);
            if (genCodes) {
                genCodes.value = list.map(function (r) {
                    return '卡密 ' + r.code + ' 积分 ' + r.points;
                }).join('\n');
            }
            if (genResult) genResult.hidden = false;
            if (VS.showMessage) VS.showMessage(data.msg || '生成成功', 'success');
            loadStats();
            resetList();
            load();
        }).catch(function () {
            genBusy = false;
            if (generateBtn) generateBtn.disabled = false;
            if (VS.showMessage) VS.showMessage('网络异常', 'error');
        });
    }

    if (generateBtn) {
        generateBtn.addEventListener('click', function () {
            if (genBusy) return;
            var already = genResult && !genResult.hidden && genCodes && String(genCodes.value || '').trim() !== '';
            if (already) {
                var doRun = function () { runGenerate(); };
                if (window.VsModal && typeof VsModal.confirm === 'function') {
                    VsModal.confirm(
                        '将再生成一批新卡密（不会覆盖库里已有卡密）。确定继续？',
                        '再次生成',
                        { confirmText: '继续生成', cancelText: '取消' }
                    ).then(function (ok) {
                        if (ok) doRun();
                    });
                } else if (window.confirm('将再生成一批新卡密，确定继续？')) {
                    doRun();
                }
                return;
            }
            runGenerate();
        });
    }

    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function () {
            copyText(genCodes ? genCodes.value : '', '已复制全部卡密');
        });
    }

    if (pageRoot) {
        syncFilterButtons();
        syncSelectionUi();
        loadStats();
        load();
    }
})();
