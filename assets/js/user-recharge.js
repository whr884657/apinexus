/**
 * 文件：assets/js/user-recharge.js
 * 作用：用户充值下单、扫码弹窗、状态轮询
 */
(function () {
    'use strict';
    if (!window.VS) {
        return;
    }

    var packageId = '';
    var currentOrder = '';
    var pollTimer = null;
    var overlay = document.getElementById('rechargePayOverlay');

    function openOverlay() {
        if (!overlay) {
            return;
        }
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-open');
        document.body.classList.add('is-overlay-open');
    }

    function closeOverlay() {
        if (!overlay) {
            return;
        }
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-open');
        document.body.classList.remove('is-overlay-open');
        stopPoll();
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function qrUrl(content) {
        return 'https://api.2dcode.biz/v1/create-qr-code?data='
            + encodeURIComponent(content) + '&size=200x200';
    }

    function selectPkg(btn) {
        packageId = btn.getAttribute('data-pkg') || '';
        document.querySelectorAll('.vs-recharge-pkg').forEach(function (el) {
            el.classList.toggle('is-selected', el === btn);
        });
        var money = document.getElementById('rechargeMoney');
        if (money) {
            money.value = '';
        }
        var hid = document.getElementById('rechargePackageId');
        if (hid) {
            hid.value = packageId;
        }
    }

    document.querySelectorAll('.vs-recharge-pkg').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectPkg(btn);
        });
    });

    var moneyInput = document.getElementById('rechargeMoney');
    if (moneyInput) {
        moneyInput.addEventListener('input', function () {
            packageId = '';
            var hid = document.getElementById('rechargePackageId');
            if (hid) {
                hid.value = '';
            }
            document.querySelectorAll('.vs-recharge-pkg').forEach(function (el) {
                el.classList.remove('is-selected');
            });
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
                closeOverlay();
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
            var paytype = document.getElementById('rechargePaytype');
            var money = document.getElementById('rechargeMoney');
            var fd = new FormData();
            fd.append('action', 'create');
            fd.append('paytype', paytype ? paytype.value : '');
            fd.append('package_id', packageId || '');
            fd.append('money', money ? money.value : '');
            submitBtn.disabled = true;
            VS.postForm(fd).then(function (data) {
                submitBtn.disabled = false;
                if (!data || data.code !== 1) {
                    VS.showMessage((data && data.msg) || '下单失败', 'error');
                    return;
                }
                currentOrder = data.orderno || '';
                document.getElementById('payOrderNo').textContent = currentOrder;
                document.getElementById('payMoney').textContent = data.money || '';
                document.getElementById('payTypeLabel').textContent = data.pay_label || '';
                document.getElementById('payPoints').textContent = data.points || '';
                var img = document.getElementById('payQrImg');
                if (img && data.qrcode) {
                    img.src = qrUrl(data.qrcode);
                }
                openOverlay();
                startPoll();
            }).catch(function () {
                submitBtn.disabled = false;
                VS.showMessage('网络异常', 'error');
            });
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
                closeOverlay();
                return;
            }
            var fd = new FormData();
            fd.append('action', 'cancel');
            fd.append('orderno', currentOrder);
            VS.postForm(fd).finally(function () {
                closeOverlay();
                currentOrder = '';
            });
        });
    }

    if (overlay) {
        overlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', closeOverlay);
        });
    }
})();
