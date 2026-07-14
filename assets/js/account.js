/**
 * 文件：assets/js/account.js
 * 作用：账号设置页头像预览 + AJAX 保存/解绑（避免 POST 重复提交）
 */
(function () {
    'use strict';

    var preview = document.getElementById('avatarPreview');
    var input = document.getElementById('avatarUrlInput');
    var fallback = preview ? (preview.getAttribute('data-fallback') || preview.src) : '';
    var timer = null;

    function updatePreview(url) {
        if (!preview) {
            return;
        }
        var value = (url != null ? url : (input ? input.value : '')).trim();
        preview.src = value === '' ? fallback : value;
    }

    if (preview && input) {
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                updatePreview();
            }, 200);
        });

        preview.addEventListener('error', function () {
            if (preview.src !== fallback) {
                preview.src = fallback;
            }
        });
    }

    function updateAdminBindUI(user) {
        var statusEl = document.getElementById('adminBindStatus');
        var bindForm = document.getElementById('bindUserForm');
        if (!statusEl || !user) {
            return;
        }
        var text = 'ID ' + user.id + ' · ' + user.username + ' · ' + user.email;
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

    function bindAjaxForm(form) {
        if (!form || form.getAttribute('data-ajax') !== '1') {
            return;
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            window.VS.postForm(form)
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '操作失败', 'error');
                        return;
                    }

                    window.VS.showMessage(data.msg || '操作成功', 'success');

                    if (form.id === 'accountForm') {
                        if (data.avatar_preview) {
                            updatePreview(data.avatar_preview);
                        }
                        if (input && data.avatar != null) {
                            input.value = data.avatar;
                        }
                        form.querySelectorAll('input[type="password"]').forEach(function (el) {
                            el.value = '';
                        });
                        return;
                    }

                    if (form.id === 'bindUserForm' && data.bound_user) {
                        updateAdminBindUI(data.bound_user);
                        return;
                    }

                    if (form.id === 'unbindUserForm') {
                        clearAdminBindUI();
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
                            var label = provider === 'qq' ? 'QQ' : 'Gitee';
                            actionEl.innerHTML = '<a href="' + window.VS_BASE_URL + '/user/oauth/start.php?provider='
                                + encodeURIComponent(provider) + '&amp;intent=bind" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>';
                            item.setAttribute('data-oauth-bound', '0');
                        }
                    }
                })
                .catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    }

    document.querySelectorAll('form[data-ajax="1"]').forEach(bindAjaxForm);
})();
