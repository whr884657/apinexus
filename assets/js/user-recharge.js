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

    function updateCustomHint() {
        var moneyEl = document.getElementById('rechargeMoney');
        var hint = document.getElementById('rechargeCustomHint');
        if (!moneyEl || !hint) {
            return;
        }
        var m = parseFloat(moneyEl.value || '0');
        if (m > 0) {
            var pts = Math.round(m * rate * 10000) / 10000;
            hint.textContent = '预计到账 ' + pts + ' 积分';
        } else {
            hint.textContent = '预计到账 — 积分';
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
                img.src = qrUrl(data.qrcode);
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
})();
