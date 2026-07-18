/**
 * 文件：assets/js/finance-payment.js
 * 作用：支付配置保存、支付方式按钮、套餐可视化编辑
 */
(function () {
    'use strict';
    if (!window.VS) {
        return;
    }

    var form = document.getElementById('payConfigForm');
    var packagesInput = document.getElementById('payPackages');
    var listEl = document.getElementById('payPkgList');
    var overlay = document.getElementById('payPkgOverlay');
    var packages = [];

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

    function renderPackages() {
        if (!listEl) {
            return;
        }
        if (!packages.length) {
            listEl.innerHTML = '<p class="vs-empty vs-pkg-empty">暂无套餐，点击「添加套餐」创建</p>';
            return;
        }
        listEl.innerHTML = packages.map(function (pkg, idx) {
            return '<div class="vs-pkg-card' + (pkg.hot ? ' is-hot' : '') + '">'
                + '<div class="vs-pkg-card__main">'
                + '<div class="vs-pkg-card__name">' + escapeHtml(pkg.name) + (pkg.hot ? '<span class="vs-pkg-card__badge">荐</span>' : '') + '</div>'
                + '<div class="vs-pkg-card__money">¥' + escapeHtml(pkg.money) + '</div>'
                + '<div class="vs-pkg-card__points">' + escapeHtml(pkg.points) + ' 积分</div>'
                + '</div>'
                + '<div class="vs-pkg-card__actions">'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-pkg-edit="' + idx + '">编辑</button>'
                + '<button type="button" class="vs-btn vs-btn--outline vs-btn--sm vs-btn--danger-text" data-pkg-del="' + idx + '">删除</button>'
                + '</div></div>';
        }).join('');
    }

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
    }

    function fillPkgForm(pkg, index) {
        document.getElementById('payPkgEditIndex').value = String(index);
        document.getElementById('payPkgName').value = pkg && pkg.name ? pkg.name : '';
        document.getElementById('payPkgMoney').value = pkg && pkg.money ? pkg.money : '';
        document.getElementById('payPkgPoints').value = pkg && pkg.points ? pkg.points : '';
        document.getElementById('payPkgHot').checked = !!(pkg && pkg.hot);
        document.getElementById('payPkgTitle').textContent = index >= 0 ? '编辑套餐' : '添加套餐';
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
            openOverlay();
        });
    }

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            var edit = e.target.closest('[data-pkg-edit]');
            var del = e.target.closest('[data-pkg-del]');
            if (edit) {
                var ei = parseInt(edit.getAttribute('data-pkg-edit'), 10);
                fillPkgForm(packages[ei] || null, ei);
                openOverlay();
            }
            if (del) {
                var di = parseInt(del.getAttribute('data-pkg-del'), 10);
                packages.splice(di, 1);
                writePackages();
                renderPackages();
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
            closeOverlay();
        });
    }

    if (overlay) {
        overlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
            el.addEventListener('click', closeOverlay);
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            writePackages();
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
})();
