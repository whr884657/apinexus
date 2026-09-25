/**
 * 文件：assets/js/finance-payment.js
 * 作用：支付配置三 Tab、套餐/自定义优惠编辑、充值说明预览与一键生成
 */
(function () {
    'use strict';
    if (!window.VS) {
        return;
    }

    var form = document.getElementById('payConfigForm');
    var page = document.getElementById('payConfigPage');
    var packagesInput = document.getElementById('payPackages');
    var bonusInput = document.getElementById('payCustomBonus');
    var listEl = document.getElementById('payPkgList');
    var bonusListEl = document.getElementById('payBonusList');
    var overlay = document.getElementById('payPkgOverlay');
    var bonusOverlay = document.getElementById('payBonusOverlay');
    var rateInput = document.getElementById('payRate');
    var packages = [];
    var bonuses = [];
    var tabBtns = document.querySelectorAll('#payConfigTabs [data-pay-tab]');
    var tabPanes = document.querySelectorAll('#payConfigPage [data-pay-panel]');
    var tipDrafts = {};

    function switchPayTab(name) {
        var key = name === 'gateway' ? 'gateway' : (name === 'tips' ? 'tips' : 'packages');
        tabBtns.forEach(function (btn) {
            var on = btn.getAttribute('data-pay-tab') === key;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        tabPanes.forEach(function (pane) {
            var on = pane.getAttribute('data-pay-panel') === key;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;
        });
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchPayTab(btn.getAttribute('data-pay-tab') || 'packages');
        });
    });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function askConfirm(message, title) {
        if (window.VsModal && typeof VsModal.confirm === 'function') {
            return VsModal.confirm(message, title || '确认');
        }
        return Promise.resolve(window.confirm(message));
    }

    function currentRate() {
        var n = rateInput ? parseFloat(rateInput.value || '0') : 0;
        if (!(n > 0)) {
            n = page ? parseFloat(page.getAttribute('data-pay-rate') || '1000') : 1000;
        }
        return n > 0 ? n : 1000;
    }

    /** 与 PayConfig::giftPercent 一致：向下取整，&lt;1 为 0 */
    function giftPercent(money, points, rate) {
        money = parseFloat(money);
        points = parseFloat(points);
        rate = parseFloat(rate);
        if (!(rate > 0) || !(money > 0) || !(points > 0)) {
            return 0;
        }
        var base = money * rate;
        if (!(base > 0) || points <= base) {
            return 0;
        }
        var pct = Math.floor((points - base) / base * 100);
        return pct >= 1 ? pct : 0;
    }

    function readPackages() {
        try {
            var raw = packagesInput ? packagesInput.value : '[]';
            var data = JSON.parse(raw || '[]');
            packages = Array.isArray(data) ? data : [];
        } catch (e) {
            packages = [];
        }
    }

    function writePackages() {
        if (packagesInput) {
            packagesInput.value = JSON.stringify(packages);
        }
    }

    function readBonuses() {
        try {
            var raw = bonusInput ? bonusInput.value : '[]';
            var data = JSON.parse(raw || '[]');
            bonuses = Array.isArray(data) ? data : [];
        } catch (e) {
            bonuses = [];
        }
        sortBonuses();
    }

    function sortBonuses() {
        bonuses.sort(function (a, b) {
            return parseFloat(a.min) - parseFloat(b.min);
        });
    }

    function writeBonuses() {
        sortBonuses();
        if (bonusInput) {
            bonusInput.value = JSON.stringify(bonuses);
        }
    }

    function formatMoney(n) {
        return parseFloat(n).toFixed(2);
    }

    function bonusRangeLabel(tier) {
        var min = formatMoney(tier.min);
        var max = parseFloat(tier.max);
        if (!(max > 0)) {
            return '满 ' + min + ' 元起';
        }
        return '满 ' + min + ' 元且不足 ' + formatMoney(max) + ' 元';
    }

    function tiersOverlap(list) {
        var prevMax = null;
        for (var i = 0; i < list.length; i++) {
            var min = parseFloat(list[i].min);
            var max = parseFloat(list[i].max);
            if (prevMax !== null) {
                if (!(prevMax > 0)) {
                    return '不封顶档位之后不能再配置其它档位';
                }
                if (min < prevMax) {
                    return '优惠档位金额区间不能重叠';
                }
            }
            prevMax = max > 0 ? max : 0;
        }
        return '';
    }

    function renderPackages() {
        if (!listEl) {
            return;
        }
        if (!packages.length) {
            listEl.innerHTML = '<p class="vs-empty vs-pkg-empty">暂无套餐，点击「添加套餐」。</p>';
            return;
        }
        var rate = currentRate();
        listEl.innerHTML = packages.map(function (pkg, idx) {
            var gift = giftPercent(pkg.money, pkg.points, rate);
            var giftHtml = gift >= 1
                ? '<span class="vs-pkg-card__gift">赠' + gift + '%</span>'
                : '';
            return '<div class="vs-pkg-card' + (pkg.hot ? ' is-hot' : '') + (gift >= 1 ? ' has-gift' : '') + '">'
                + giftHtml
                + '<div class="vs-pkg-card__main">'
                + '<div class="vs-pkg-card__name">' + escapeHtml(pkg.name)
                + (pkg.hot ? '<span class="vs-pkg-card__badge">荐</span>' : '')
                + '</div>'
                + '<div class="vs-pkg-card__money">¥' + escapeHtml(pkg.money) + '</div>'
                + '<div class="vs-pkg-card__points">' + escapeHtml(pkg.points) + ' 积分</div>'
                + '</div>'
                + '<div class="vs-pkg-card__actions">'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-pkg-edit="' + idx + '">编辑</button>'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm vs-btn--danger-text" data-pkg-del="' + idx + '">删除</button>'
                + '</div></div>';
        }).join('');
    }

    function renderBonuses() {
        if (!bonusListEl) {
            return;
        }
        if (!bonuses.length) {
            bonusListEl.innerHTML = '<p class="vs-empty vs-bonus-empty">暂无档位，点击「添加一档」。留空则自定义金额仅按兑换比例到账。</p>';
            return;
        }
        bonusListEl.innerHTML = bonuses.map(function (tier, idx) {
            return '<div class="vs-bonus-card">'
                + '<div class="vs-bonus-card__main">'
                + '<div class="vs-bonus-card__range">' + escapeHtml(bonusRangeLabel(tier)) + '</div>'
                + '<div class="vs-bonus-card__pct">额外赠送 <strong>' + escapeHtml(String(tier.percent)) + '%</strong></div>'
                + '</div>'
                + '<div class="vs-bonus-card__actions">'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-bonus-edit="' + idx + '">编辑</button>'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm vs-btn--danger-text" data-bonus-del="' + idx + '">删除</button>'
                + '</div></div>';
        }).join('');
    }

    if (rateInput) {
        rateInput.addEventListener('input', renderPackages);
        rateInput.addEventListener('change', renderPackages);
    }

    function openOverlayEl(el) {
        if (!el) {
            return;
        }
        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
    }

    function closeOverlayEl(el) {
        if (!el) {
            return;
        }
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        el.classList.remove('is-open');
        if (!document.querySelector('.vs-overlay.is-open')) {
            document.body.classList.remove('is-overlay-open');
        }
    }

    function fillPkgForm(pkg, index) {
        document.getElementById('payPkgEditIndex').value = String(index);
        document.getElementById('payPkgName').value = pkg && pkg.name ? pkg.name : '';
        document.getElementById('payPkgMoney').value = pkg && pkg.money ? pkg.money : '';
        document.getElementById('payPkgPoints').value = pkg && pkg.points ? pkg.points : '';
        document.getElementById('payPkgHot').checked = !!(pkg && pkg.hot);
        document.getElementById('payPkgTitle').textContent = index >= 0 ? '编辑套餐' : '添加套餐';
    }

    function fillBonusForm(tier, index) {
        document.getElementById('payBonusEditIndex').value = String(index);
        document.getElementById('payBonusMin').value = tier && tier.min != null ? tier.min : '';
        document.getElementById('payBonusMax').value = tier && tier.max != null ? tier.max : '0';
        document.getElementById('payBonusPercent').value = tier && tier.percent != null ? tier.percent : '';
        document.getElementById('payBonusTitle').textContent = index >= 0 ? '编辑优惠档位' : '添加优惠档位';
    }

    document.querySelectorAll('.vs-pay-method-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var code = btn.getAttribute('data-method');
            var input = form ? form.querySelector('.vs-pay-method-input[value="' + code + '"]') : null;
            var on = !btn.classList.contains('is-on');
            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (input) {
                input.checked = on;
            }
        });
    });

    var addBtn = document.getElementById('payPkgAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            fillPkgForm(null, -1);
            openOverlayEl(overlay);
        });
    }

    var addBonusBtn = document.getElementById('payBonusAddBtn');
    if (addBonusBtn) {
        addBonusBtn.addEventListener('click', function () {
            fillBonusForm(null, -1);
            openOverlayEl(bonusOverlay);
        });
    }

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            var edit = e.target.closest('[data-pkg-edit]');
            var del = e.target.closest('[data-pkg-del]');
            if (edit) {
                var ei = parseInt(edit.getAttribute('data-pkg-edit'), 10);
                fillPkgForm(packages[ei] || null, ei);
                openOverlayEl(overlay);
            }
            if (del) {
                var di = parseInt(del.getAttribute('data-pkg-del'), 10);
                packages.splice(di, 1);
                writePackages();
                renderPackages();
            }
        });
    }

    if (bonusListEl) {
        bonusListEl.addEventListener('click', function (e) {
            var edit = e.target.closest('[data-bonus-edit]');
            var del = e.target.closest('[data-bonus-del]');
            if (edit) {
                var ei = parseInt(edit.getAttribute('data-bonus-edit'), 10);
                fillBonusForm(bonuses[ei] || null, ei);
                openOverlayEl(bonusOverlay);
            }
            if (del) {
                var di = parseInt(del.getAttribute('data-bonus-del'), 10);
                bonuses.splice(di, 1);
                writeBonuses();
                renderBonuses();
            }
        });
    }

    var savePkgBtn = document.getElementById('payPkgSaveBtn');
    if (savePkgBtn) {
        savePkgBtn.addEventListener('click', function () {
            var name = (document.getElementById('payPkgName').value || '').trim();
            var money = parseFloat(document.getElementById('payPkgMoney').value || '0');
            var points = parseFloat(document.getElementById('payPkgPoints').value || '0');
            var hot = document.getElementById('payPkgHot').checked ? 1 : 0;
            var idx = parseInt(document.getElementById('payPkgEditIndex').value, 10);
            if (!name) {
                VS.showMessage('请填写套餐名称', 'error');
                return;
            }
            if (!(money > 0) || !(points > 0)) {
                VS.showMessage('金额与积分须大于 0', 'error');
                return;
            }
            var row = {
                id: (idx >= 0 && packages[idx] && packages[idx].id) ? packages[idx].id : ('pkg' + Date.now()),
                name: name,
                money: money.toFixed(2),
                points: String(points),
                hot: hot
            };
            if (idx >= 0) {
                packages[idx] = row;
            } else {
                packages.push(row);
            }
            writePackages();
            renderPackages();
            closeOverlayEl(overlay);
        });
    }

    var saveBonusBtn = document.getElementById('payBonusSaveBtn');
    if (saveBonusBtn) {
        saveBonusBtn.addEventListener('click', function () {
            var min = parseFloat(document.getElementById('payBonusMin').value || '0');
            var maxRaw = document.getElementById('payBonusMax').value;
            var max = maxRaw === '' || maxRaw == null ? 0 : parseFloat(maxRaw);
            var percent = parseInt(document.getElementById('payBonusPercent').value || '0', 10);
            var idx = parseInt(document.getElementById('payBonusEditIndex').value, 10);
            if (!(min >= 0.01)) {
                VS.showMessage('起始金额须至少 0.01 元', 'error');
                return;
            }
            if (isNaN(max) || max < 0) {
                VS.showMessage('上限金额不能为负数（不封顶请填 0）', 'error');
                return;
            }
            if (max > 0 && max <= min) {
                VS.showMessage('上限须大于起始金额（或不设上限填 0）', 'error');
                return;
            }
            if (!(percent >= 1) || percent > 1000) {
                VS.showMessage('赠送比例须为 1～1000 的整数', 'error');
                return;
            }
            var row = {
                min: min.toFixed(2),
                max: (max > 0 ? max : 0).toFixed(2),
                percent: percent
            };
            var next = bonuses.slice();
            if (idx >= 0) {
                next[idx] = row;
            } else {
                next.push(row);
            }
            next.sort(function (a, b) {
                return parseFloat(a.min) - parseFloat(b.min);
            });
            var overlap = tiersOverlap(next);
            if (overlap) {
                VS.showMessage(overlap, 'error');
                return;
            }
            bonuses = next;
            writeBonuses();
            renderBonuses();
            closeOverlayEl(bonusOverlay);
        });
    }

    if (overlay) {
        overlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeOverlayEl(overlay);
            });
        });
    }
    if (bonusOverlay) {
        bonusOverlay.querySelectorAll('[data-bonus-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeOverlayEl(bonusOverlay);
            });
        });
    }

    function renderTipPreview(block, md) {
        var preview = block.querySelector('.vs-pay-tip-block__preview');
        if (!preview) {
            return;
        }
        var text = String(md || '').trim();
        if (!text) {
            preview.innerHTML = '<div class="vs-notice vs-notice--tip vs-pay-tip-preview vs-pay-tip-preview--empty">'
                + '<div class="vs-notice__text">暂未配置，点击「编辑」填写。留空保存后用户端不显示。</div></div>';
            return;
        }
        var body = '';
        if (window.VsMarkdown && typeof VsMarkdown.render === 'function') {
            body = VsMarkdown.render(text);
        } else {
            body = '<pre class="vs-pay-tip-preview__raw">' + escapeHtml(text) + '</pre>';
        }
        preview.innerHTML = '<div class="vs-notice vs-notice--tip vs-pay-tip-preview">'
            + '<div class="vs-notice__text">' + body + '</div></div>';
        if (window.VsMarkdown && typeof VsMarkdown.enhance === 'function') {
            VsMarkdown.enhance(preview);
        }
    }

    function setTipMode(block, editing) {
        var preview = block.querySelector('.vs-pay-tip-block__preview');
        var editor = block.querySelector('.vs-pay-tip-block__editor');
        var btnEdit = block.querySelector('[data-tip-edit]');
        if (preview) {
            preview.hidden = !!editing;
        }
        if (editor) {
            editor.hidden = !editing;
        }
        if (btnEdit) {
            btnEdit.hidden = !!editing;
        }
        block.classList.toggle('is-editing', !!editing);
    }

    function buildCustomTipMarkdown(rate, tiers) {
        var lines = [];
        lines.push('### 自定义充值说明');
        lines.push('');
        lines.push('当前兑换比例：1 元 = ' + String(rate) + ' 积分。选择「自定义金额」时，按实付金额自动匹配优惠档位；到账积分 = 金额 × 兑换比例 × (1 + 赠送%)。');
        lines.push('');
        tiers.forEach(function (tier) {
            var min = formatMoney(tier.min);
            var max = parseFloat(tier.max);
            var pct = parseInt(tier.percent, 10) || 0;
            if (max > 0) {
                lines.push('- 充值满 ' + min + ' 元且不足 ' + formatMoney(max) + ' 元：额外赠送 ' + pct + '% 积分');
            } else {
                lines.push('- 充值满 ' + min + ' 元起（不设上限）：额外赠送 ' + pct + '% 积分');
            }
        });
        lines.push('');
        lines.push('固定套餐不参与上述阶梯；实际到账以支付成功后的订单积分为准。');
        return lines.join('\n');
    }

    function applyCustomTipFill() {
        readBonuses();
        if (!bonuses.length) {
            VS.showMessage('请先在「积分与套餐」里配置自定义充值优惠', 'error');
            return;
        }
        var block = document.querySelector('.vs-pay-tip-block[data-tip-key="custom"]');
        if (!block) {
            return;
        }
        var ta = block.querySelector('textarea');
        if (!ta) {
            return;
        }
        var md = buildCustomTipMarkdown(currentRate(), bonuses);
        var doFill = function () {
            ta.value = md;
            tipDrafts.custom = md;
            setTipMode(block, true);
            if (window.VsMarkdownEditor && typeof VsMarkdownEditor.mount === 'function') {
                VsMarkdownEditor.mount(ta);
            }
            renderTipPreview(block, md);
            setTipMode(block, true);
            ta.focus();
            VS.showMessage('已按当前优惠档位生成说明，请核对后保存配置', 'success');
        };
        var cur = String(ta.value || '').trim();
        if (cur) {
            askConfirm('将覆盖当前自定义充值说明，是否继续？', '生成说明').then(function (ok) {
                if (ok) {
                    doFill();
                }
            });
        } else {
            doFill();
        }
    }

    var fillBonusBtn = document.getElementById('payTipFillBonusBtn');
    if (fillBonusBtn) {
        fillBonusBtn.addEventListener('click', applyCustomTipFill);
    }

    document.querySelectorAll('.vs-pay-tip-block').forEach(function (block) {
        var key = block.getAttribute('data-tip-key') || '';
        var ta = block.querySelector('textarea');
        if (ta) {
            tipDrafts[key] = ta.value;
        }

        var btnEdit = block.querySelector('[data-tip-edit]');
        if (btnEdit) {
            btnEdit.addEventListener('click', function () {
                if (ta) {
                    tipDrafts[key] = ta.value;
                }
                setTipMode(block, true);
                if (ta && window.VsMarkdownEditor && typeof VsMarkdownEditor.mount === 'function') {
                    VsMarkdownEditor.mount(ta);
                }
                if (ta) {
                    ta.focus();
                }
            });
        }
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            writePackages();
            writeBonuses();
            document.querySelectorAll('.vs-pay-tip-block.is-editing').forEach(function (block) {
                setTipMode(block, false);
                var ta = block.querySelector('textarea');
                renderTipPreview(block, ta ? ta.value : '');
            });
            var btn = document.getElementById('payConfigSaveBtn');
            if (btn) {
                btn.disabled = true;
            }
            var fd = new FormData(form);
            VS.postForm(fd).then(function (data) {
                if (btn) {
                    btn.disabled = false;
                }
                if (!data || data.code !== 1) {
                    VS.showMessage((data && data.msg) || '保存失败', 'error');
                    return;
                }
                VS.showMessage(data.msg || '已保存', 'success');
                document.querySelectorAll('.vs-pay-tip-block').forEach(function (block) {
                    var k = block.getAttribute('data-tip-key') || '';
                    var ta = block.querySelector('textarea');
                    if (ta) {
                        tipDrafts[k] = ta.value;
                    }
                });
                if (data.config && data.config.rate != null && page) {
                    page.setAttribute('data-pay-rate', String(data.config.rate));
                }
                if (data.config && Array.isArray(data.config.custom_bonus)) {
                    bonuses = data.config.custom_bonus;
                    writeBonuses();
                    renderBonuses();
                }
                renderPackages();
            }).catch(function () {
                if (btn) {
                    btn.disabled = false;
                }
                VS.showMessage('网络异常', 'error');
            });
        });
    }

    readPackages();
    renderPackages();
    readBonuses();
    renderBonuses();
})();
