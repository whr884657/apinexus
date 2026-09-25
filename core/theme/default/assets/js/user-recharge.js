/**
 * 文件：assets/js/user-recharge.js
 * 作用：用户充值下单、自定义金额、扫码弹窗、状态轮询
 */
(function () {
    'use strict';
    if (!window.VS) {
        return;
    }

    var packageId = '';
    var customMoney = '';
    var currentOrder = '';
    var currentPayType = '';
    var pollTimer = null;
    var payOverlay = document.getElementById('rechargePayOverlay');
    var customOverlay = document.getElementById('rechargeCustomOverlay');
    var app = document.getElementById('rechargeApp');
    var rate = app ? parseFloat(app.getAttribute('data-rate') || '1000') : 1000;
    var customBonus = [];
    try {
        customBonus = app ? JSON.parse(app.getAttribute('data-custom-bonus') || '[]') : [];
        if (!Array.isArray(customBonus)) {
            customBonus = [];
        }
    } catch (eBonus) {
        customBonus = [];
    }
    var icons = {};
    try {
        var iconEl = document.getElementById('rechargePayIcons');
        icons = iconEl ? JSON.parse(iconEl.textContent || '{}') : {};
    } catch (e) {
        icons = {};
    }

    function openOverlay(el) {
        if (!el) {
            return;
        }
        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
    }

    function closeOverlay(el) {
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

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function qrUrl(content) {
        return 'https://api.2dcode.biz/v1/create-qr-code?data='
            + encodeURIComponent(content) + '&size=220x220';
    }

    /**
     * 支付码展示：image（data URI / 图片 URL）直接出图；content 再交 2dcode 编码
     * 兼容上游 B64 美化折行（空白/换行）与无 data 前缀的裸 B64（后端已归一化时前端仍做兜底）
     */
    function resolvePayQrSrc(qrcode, qrKind) {
        var s = String(qrcode || '').trim();
        if (!s) {
            return '';
        }
        var kind = String(qrKind || '').toLowerCase();
        if (kind === 'image' || /^data:image\//i.test(s)) {
            if (/^data:image\//i.test(s)) {
                var comma = s.indexOf(',');
                if (comma > 0) {
                    s = s.slice(0, comma + 1) + s.slice(comma + 1).replace(/\s+/g, '');
                }
            }
            return s;
        }
        return qrUrl(s);
    }

    function updatePayBtn() {
        var btn = document.getElementById('rechargeSubmitBtn');
        if (!btn) {
            return;
        }
        if (packageId) {
            btn.disabled = false;
            btn.textContent = '立即支付';
            return;
        }
        if (customMoney && parseFloat(customMoney) > 0) {
            btn.disabled = false;
            btn.textContent = '立即支付 ¥' + parseFloat(customMoney).toFixed(2);
            return;
        }
        btn.disabled = true;
        btn.textContent = '请先选择套餐';
    }

    function clearSelection() {
        document.querySelectorAll('.vs-recharge-card').forEach(function (el) {
            el.classList.remove('is-selected');
        });
    }

    function selectPkg(btn) {
        clearSelection();
        btn.classList.add('is-selected');
        packageId = btn.getAttribute('data-pkg') || '';
        customMoney = '';
        var hid = document.getElementById('rechargePackageId');
        if (hid) {
            hid.value = packageId;
        }
        var money = document.getElementById('rechargeMoney');
        if (money) {
            money.value = '';
        }
        updatePayBtn();
    }

    document.querySelectorAll('.vs-recharge-card[data-pkg]').forEach(function (btn) {
        if (btn.id === 'rechargeCustomCard') {
            return;
        }
        btn.addEventListener('click', function () {
            selectPkg(btn);
        });
    });

    var customCard = document.getElementById('rechargeCustomCard');
    if (customCard) {
        customCard.addEventListener('click', function () {
            clearSelection();
            customCard.classList.add('is-selected');
            packageId = '';
            var hid = document.getElementById('rechargePackageId');
            if (hid) {
                hid.value = '';
            }
            openOverlay(customOverlay);
            var money = document.getElementById('rechargeMoney');
            if (money) {
                money.focus();
            }
        });
    }

    function matchCustomBonus(money) {
        money = Math.round(money * 100) / 100;
        if (!(money >= 0.01)) {
            return null;
        }
        for (var i = 0; i < customBonus.length; i++) {
            var tier = customBonus[i] || {};
            var min = parseFloat(tier.min);
            var max = parseFloat(tier.max);
            var percent = parseInt(tier.percent, 10) || 0;
            if (!(min >= 0) || percent < 1) {
                continue;
            }
            if (money < min) {
                continue;
            }
            if (max > 0 && money >= max) {
                continue;
            }
            return { min: min, max: max, percent: percent };
        }
        return null;
    }

    function calcCustomPoints(money) {
        var base = Math.round(money * rate * 10000) / 10000;
        var tier = matchCustomBonus(money);
        var percent = tier ? tier.percent : 0;
        var points = percent > 0
            ? Math.round(base * (1 + percent / 100) * 10000) / 10000
            : base;
        return { points: points, percent: percent, base: base };
    }

    function updateCustomHint() {
        var moneyEl = document.getElementById('rechargeMoney');
        var ptsEl = document.getElementById('rechargeCustomHintPts');
        var giftEl = document.getElementById('rechargeCustomHintGift');
        if (!moneyEl || !ptsEl) {
            return;
        }
        var m = parseFloat(moneyEl.value || '0');
        if (m > 0) {
            var calc = calcCustomPoints(m);
            ptsEl.textContent = String(calc.points);
            if (giftEl) {
                giftEl.textContent = calc.percent >= 1 ? ('（含赠 ' + calc.percent + '%）') : '';
            }
        } else {
            ptsEl.textContent = '—';
            if (giftEl) {
                giftEl.textContent = '';
            }
        }
    }

    var moneyInput = document.getElementById('rechargeMoney');
    if (moneyInput) {
        moneyInput.addEventListener('input', updateCustomHint);
    }

    document.querySelectorAll('#rechargePayMethods .vs-pay-method-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#rechargePayMethods .vs-pay-method-btn').forEach(function (el) {
                var on = el === btn;
                el.classList.toggle('is-on', on);
                el.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            var hid = document.getElementById('rechargePaytype');
            if (hid) {
                hid.value = btn.getAttribute('data-paytype') || '';
            }
        });
    });

    function createOrder(extraMoney) {
        var paytype = document.getElementById('rechargePaytype');
        var submitBtn = document.getElementById('rechargeSubmitBtn');
        var fd = new FormData();
        fd.append('action', 'create');
        fd.append('paytype', paytype ? paytype.value : '');
        fd.append('package_id', packageId || '');
        fd.append('money', extraMoney != null ? String(extraMoney) : (customMoney || ''));
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        return VS.postForm(fd).then(function (data) {
            if (submitBtn) {
                updatePayBtn();
            }
            if (!data || data.code !== 1) {
                VS.showMessage((data && data.msg) || '下单失败', 'error');
                return;
            }
            currentOrder = data.orderno || '';
            currentPayType = (paytype && paytype.value) || '';
            document.getElementById('payOrderNo').textContent = currentOrder;
            document.getElementById('payMoney').textContent = data.money || '';
            document.getElementById('payTypeLabel').textContent = data.pay_label || '';
            document.getElementById('payPoints').textContent = data.points || '';
            var img = document.getElementById('payQrImg');
            if (img && data.qrcode) {
                img.src = resolvePayQrSrc(data.qrcode, data.qr_kind);
            }
            var logo = document.getElementById('payQrLogo');
            if (logo) {
                logo.innerHTML = icons[currentPayType] || '';
            }
            openOverlay(payOverlay);
            startPoll();
        }).catch(function () {
            if (submitBtn) {
                updatePayBtn();
            }
            VS.showMessage('网络异常', 'error');
        });
    }

    var customConfirm = document.getElementById('rechargeCustomConfirm');
    if (customConfirm) {
        customConfirm.addEventListener('click', function () {
            var moneyEl = document.getElementById('rechargeMoney');
            var m = moneyEl ? parseFloat(moneyEl.value || '0') : 0;
            if (!(m > 0)) {
                VS.showMessage('请输入有效金额', 'error');
                return;
            }
            customMoney = m.toFixed(2);
            packageId = '';
            updatePayBtn();
            closeOverlay(customOverlay);
            createOrder(customMoney);
        });
    }

    function checkStatus(manual) {
        if (!currentOrder) {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'status');
        fd.append('orderno', currentOrder);
        VS.postForm(fd).then(function (data) {
            if (!data || data.code !== 1) {
                if (manual) {
                    VS.showMessage((data && data.msg) || '查询失败', 'error');
                }
                return;
            }
            var st = parseInt(data.status, 10);
            if (st === 1) {
                stopPoll();
                closeOverlay(payOverlay);
                var bal = document.getElementById('rechargeBalance');
                if (bal && data.balance != null) {
                    bal.textContent = data.balance;
                }
                VS.showMessage('充值成功，积分已到账', 'success');
                return;
            }
            if (st === 2) {
                stopPoll();
                closeOverlay(payOverlay);
                currentOrder = '';
                VS.showMessage('订单已取消或超时未支付，请重新下单', 'info');
                return;
            }
            if (manual) {
                VS.showMessage('尚未支付，请完成支付后再试', 'info');
            }
        }).catch(function () {
            if (manual) {
                VS.showMessage('网络异常', 'error');
            }
        });
    }

    function startPoll() {
        stopPoll();
        pollTimer = setInterval(function () {
            checkStatus(false);
        }, 2000);
        setTimeout(function () {
            checkStatus(false);
        }, 800);
    }

    var submitBtn = document.getElementById('rechargeSubmitBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (packageId) {
                createOrder('');
                return;
            }
            if (customMoney) {
                createOrder(customMoney);
                return;
            }
            VS.showMessage('请先选择套餐', 'info');
        });
    }

    var checkBtn = document.getElementById('payCheckBtn');
    if (checkBtn) {
        checkBtn.addEventListener('click', function () {
            checkStatus(true);
        });
    }

    var cancelBtn = document.getElementById('payCancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (!currentOrder) {
                closeOverlay(payOverlay);
                return;
            }
            var fd = new FormData();
            fd.append('action', 'cancel');
            fd.append('orderno', currentOrder);
            VS.postForm(fd).finally(function () {
                closeOverlay(payOverlay);
                stopPoll();
                currentOrder = '';
            });
        });
    }

    if (payOverlay) {
        payOverlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeOverlay(payOverlay);
                stopPoll();
            });
        });
    }
    if (customOverlay) {
        customOverlay.querySelectorAll('[data-custom-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeOverlay(customOverlay);
                if (!customMoney) {
                    customCard && customCard.classList.remove('is-selected');
                    updatePayBtn();
                }
            });
        });
    }

    updatePayBtn();

    /* 充值方式 Tab：积分充值 / 卡密兑换 */
    function setRechargeTab(name) {
        document.querySelectorAll('.vs-recharge-tab').forEach(function (btn) {
            var on = btn.getAttribute('data-tab') === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.vs-recharge-pane').forEach(function (pane) {
            var on = pane.getAttribute('data-pane') === name;
            pane.hidden = !on;
        });
        var tips = document.getElementById('rechargeTips');
        if (tips) {
            tips.classList.toggle('is-cardkey-first', name === 'cardkey');
        }
    }
    var tabs = document.getElementById('rechargeTabs');
    if (tabs) {
        tabs.addEventListener('click', function (e) {
            var btn = e.target.closest('.vs-recharge-tab');
            if (!btn) return;
            setRechargeTab(btn.getAttribute('data-tab') || 'pay');
        });
        var def = app ? (app.getAttribute('data-default-tab') || 'pay') : 'pay';
        setRechargeTab(def);
    }

    var cardkeyInput = document.getElementById('cardkeyCodeInput');
    var cardkeyBtn = document.getElementById('cardkeyRedeemBtn');
    function doRedeem() {
        if (!cardkeyInput || !cardkeyBtn) {
            return;
        }
        var code = String(cardkeyInput.value || '').trim();
        if (!/^[A-Za-z0-9]{20}$/.test(code)) {
            if (VS.showMessage) VS.showMessage('请输入 20 位字母数字卡密', 'warning');
            return;
        }
        cardkeyBtn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'redeem');
        fd.append('code', code);
        VS.postForm(fd, window.location.href).then(function (data) {
            cardkeyBtn.disabled = false;
            if (!data || data.code !== 1) {
                if (VS.showMessage) VS.showMessage((data && data.msg) || '兑换失败', 'error');
                return;
            }
            if (VS.showMessage) VS.showMessage(data.msg || '兑换成功', 'success');
            cardkeyInput.value = '';
            var bal = document.getElementById('rechargeBalance');
            if (bal && data.balance != null) {
                bal.textContent = String(data.balance);
            }
        }).catch(function () {
            cardkeyBtn.disabled = false;
            if (VS.showMessage) VS.showMessage('网络异常', 'error');
        });
    }
    if (cardkeyBtn) {
        cardkeyBtn.addEventListener('click', doRedeem);
    }
    if (cardkeyInput) {
        cardkeyInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doRedeem();
            }
        });
    }
})();
