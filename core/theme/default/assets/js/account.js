/**
 * 文件：assets/js/account.js
 * 作用：账号设置顶卡 Tab 切换 + 头像预览 + AJAX 保存/解绑/开发者申请
 */
(function () {
    'use strict';

    var page = document.getElementById('accountPage');
    var preview = document.getElementById('avatarPreview');
    var heroAvatar = document.getElementById('accountHeroAvatar');
    var input = document.getElementById('avatarUrlInput');
    var fallback = '';
    if (preview) {
        fallback = preview.getAttribute('data-fallback') || '';
    }
    if (!fallback && heroAvatar) {
        fallback = heroAvatar.getAttribute('data-fallback') || '';
    }
    var timer = null;
    var avatarBroken = false;

    function eachNode(list, fn) {
        if (!list || !list.length) {
            return;
        }
        var i;
        for (i = 0; i < list.length; i++) {
            fn(list[i], i);
        }
    }

    function updatePreview(url) {
        var value = (url != null ? url : (input ? input.value : '')).trim();
        var src = value === '' ? fallback : value;
        avatarBroken = false;
        if (preview) {
            preview.src = src || fallback;
        }
        if (heroAvatar) {
            heroAvatar.src = src || fallback;
        }
    }

    if (input) {
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                updatePreview();
            }, 200);
        });
    }

    function bindImgFallback(img) {
        if (!img) {
            return;
        }
        img.addEventListener('error', function () {
            if (avatarBroken || !fallback) {
                return;
            }
            // 用绝对地址比较，避免相对/绝对 URL 不一致导致 error 死循环卡死页面
            var absFallback = fallback;
            try {
                var a = document.createElement('a');
                a.href = fallback;
                absFallback = a.href;
            } catch (ignore) {
                absFallback = fallback;
            }
            if (img.src === absFallback) {
                avatarBroken = true;
                return;
            }
            avatarBroken = true;
            img.src = fallback;
        });
    }
    bindImgFallback(preview);
    bindImgFallback(heroAvatar);

    function firstAccountPane() {
        if (!page) {
            return '';
        }
        var pane = page.querySelector('[data-account-pane]');
        return pane ? (pane.getAttribute('data-account-pane') || '') : '';
    }

    function switchAccountTab(tab) {
        if (!page || !tab) {
            return;
        }
        if (!page.querySelector('[data-account-pane="' + tab + '"]')) {
            tab = firstAccountPane() || 'oauth';
        }
        page.setAttribute('data-active-tab', tab);
        eachNode(page.querySelectorAll('[data-account-tab]'), function (btn) {
            var on = btn.getAttribute('data-account-tab') === tab;
            if (on) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
            if (btn.getAttribute('role') === 'tab') {
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            }
        });
        eachNode(page.querySelectorAll('[data-account-pane]'), function (pane) {
            var on = pane.getAttribute('data-account-pane') === tab;
            if (on) {
                pane.removeAttribute('hidden');
            } else {
                pane.setAttribute('hidden', '');
            }
        });
    }

    if (page) {
        // 事件委托：只响应 Tab 栏按钮，避免其它 data-account-tab 干扰
        var tabs = document.getElementById('accountTabs');
        var tabRoot = tabs || page;
        tabRoot.addEventListener('click', function (e) {
            var el = e.target;
            var btn = null;
            while (el && el !== tabRoot) {
                if (el.getAttribute && el.getAttribute('data-account-tab') != null) {
                    btn = el;
                    break;
                }
                el = el.parentNode;
            }
            if (!btn) {
                return;
            }
            e.preventDefault();
            switchAccountTab(btn.getAttribute('data-account-tab') || '');
        });
        var initial = page.getAttribute('data-active-tab') || firstAccountPane() || 'oauth';
        switchAccountTab(initial);
    }

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateAdminBindUI(user) {
        var statusEl = document.getElementById('adminBindStatus');
        var bindForm = document.getElementById('bindUserForm');
        if (!statusEl || !user) {
            return;
        }
        var text = 'ID ' + escHtml(user.id) + ' · ' + escHtml(user.username) + ' · ' + escHtml(user.email);
        statusEl.innerHTML = '<div class="vs-admin-bind-card">'
            + '<div class="vs-admin-bind-card__info">'
            + '<span class="vs-admin-bind-card__label">已绑定用户</span>'
            + '<span class="vs-admin-bind-card__value" id="adminBindUserText">' + text + '</span>'
            + '</div>'
            + '<form method="post" action="" class="vs-admin-unbind-form" id="unbindUserForm" data-ajax="1">'
            + '<input type="hidden" name="action" value="unbind_user">'
            + '<button type="submit" class="vs-btn vs-btn--text vs-btn--oauth-action">解除绑定</button>'
            + '</form></div>';
        if (bindForm) {
            bindForm.hidden = true;
            var accountInput = document.getElementById('bindUserAccount');
            if (accountInput) {
                accountInput.value = '';
            }
        }
        var unbindForm = document.getElementById('unbindUserForm');
        if (unbindForm) {
            bindAjaxForm(unbindForm);
        }
    }

    function clearAdminBindUI() {
        var statusEl = document.getElementById('adminBindStatus');
        var bindForm = document.getElementById('bindUserForm');
        if (statusEl) {
            statusEl.innerHTML = '<p class="vs-admin-bind-empty" id="adminBindEmpty">当前未绑定用户账号，后台发布内容前请先完成绑定。</p>';
        }
        if (bindForm) {
            bindForm.hidden = false;
        }
    }

    function syncHeroFromProfile(data) {
        var nameEl = document.getElementById('accountHeroName');
        var emailEl = document.getElementById('accountHeroEmail');
        if (nameEl && data.username) {
            nameEl.textContent = data.username;
        }
        if (emailEl && data.email) {
            emailEl.textContent = data.email;
        }
        if (data.avatar_preview) {
            updatePreview(data.avatar_preview);
        } else if (data.avatar != null && input) {
            input.value = data.avatar;
            updatePreview(data.avatar);
        }
    }

    function bindAjaxForm(form) {
        if (!form || form.getAttribute('data-ajax') !== '1') {
            return;
        }
        if (form.getAttribute('data-account-bound') === '1') {
            return;
        }
        form.setAttribute('data-account-bound', '1');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            if (form.id === 'accountPasswordForm' || form.id === 'accountForm') {
                var newPwd = form.querySelector('[name="new_password"]');
                var newPwd2 = form.querySelector('[name="new_password2"]');
                var oldPwd = form.querySelector('[name="old_password"]');
                var np = newPwd ? newPwd.value : '';
                var np2 = newPwd2 ? newPwd2.value : '';
                var op = oldPwd ? oldPwd.value : '';
                if (form.id === 'accountPasswordForm' || np !== '' || np2 !== '' || op !== '') {
                    if (np.length < 6) {
                        window.VS.showMessage('新密码至少 6 个字符', 'error');
                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }
                    if (np !== np2) {
                        window.VS.showMessage('两次输入的新密码不一致', 'error');
                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }
                    if (!op) {
                        window.VS.showMessage('修改密码需输入当前密码', 'error');
                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }
                }
            }

            var done = function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            };

            window.VS.postForm(form)
                .then(function (data) {
                    if (Number(data.code) !== 1) {
                        window.VS.showMessage(data.msg || '操作失败', 'error');
                        done();
                        return;
                    }

                    window.VS.showMessage(data.msg || '操作成功', 'success');

                    if (form.id === 'accountProfileForm' || form.id === 'accountForm') {
                        syncHeroFromProfile(data);
                        if (input && data.avatar != null) {
                            input.value = data.avatar;
                        }
                        if (form.id === 'accountForm') {
                            eachNode(form.querySelectorAll('input[type="password"]'), function (el) {
                                el.value = '';
                            });
                        }
                        done();
                        return;
                    }

                    if (form.id === 'accountPasswordForm') {
                        eachNode(form.querySelectorAll('input[type="password"]'), function (el) {
                            el.value = '';
                        });
                        done();
                        return;
                    }

                    if (form.id === 'accountDevForm') {
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 600);
                        done();
                        return;
                    }

                    if (form.id === 'bindUserForm' && data.bound_user) {
                        updateAdminBindUI(data.bound_user);
                        done();
                        return;
                    }

                    if (form.id === 'unbindUserForm') {
                        clearAdminBindUI();
                        done();
                        return;
                    }

                    var item = form.closest('.vs-oauth-bind-item');
                    if (item && data.provider) {
                        var statusEl = item.querySelector('.vs-oauth-bind-item__status');
                        if (statusEl) {
                            statusEl.textContent = '未绑定';
                        }
                        var actionEl = item.querySelector('.vs-oauth-bind-item__action');
                        if (actionEl && window.VS_BASE_URL) {
                            var provider = data.provider;
                            var itemId = data.item_id != null ? String(data.item_id) : '';
                            var bindUrl = window.VS_BASE_URL + '/user/oauth/start?provider='
                                + encodeURIComponent(provider) + '&intent=bind';
                            if (itemId !== '') {
                                bindUrl += '&id=' + encodeURIComponent(itemId);
                            }
                            actionEl.innerHTML = '<a href="' + bindUrl
                                + '" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>';
                            item.setAttribute('data-oauth-bound', '0');
                        }
                    }
                    done();
                }, function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                    done();
                });
        });
    }

    eachNode(document.querySelectorAll('form[data-ajax="1"]'), bindAjaxForm);
})();
