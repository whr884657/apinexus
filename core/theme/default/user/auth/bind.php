<?php
if (!defined('VS_THEME_RENDER')) { exit; }
$base = isset($base) ? $base : $vsBase;

ThemeManager::renderThemeAuthHead('绑定' . $providerLabel);
?>

<div class="page">
    <?php vs_auth_left_panel(false); ?>

    <div class="right">
        <div class="form-box">
            <div class="header header-mobile">
                <div class="auth-kicker">OAuth Bind</div>
                <h1>绑定<?php echo vs_e($providerLabel); ?>账号</h1>
                <p class="header-sub">验证已注册账号</p>
            </div>
            <div class="header header-desktop">
                <div class="auth-kicker">OAuth Bind</div>
                <h1>绑定<?php echo vs_e($providerLabel); ?>账号</h1>
                <p class="header-sub">第三方账号尚未绑定，请使用已注册账号验证身份</p>
            </div>

            <?php
            vs_render_notice(
                'info',
                '仅支持已注册用户',
                '请使用您在本站注册的用户名/邮箱与密码完成绑定。未注册账号请先<a href="' . vs_e($base) . '/user/register" class="vs-notice__link">注册</a>。',
                array('allow_html' => true, 'compact' => true)
            );
            ?>

            <?php if ($displayName !== ''): ?>
                <?php vs_render_notice('tip', $providerLabel . ' 账号', vs_e($displayName), array('compact' => true)); ?>
            <?php endif; ?>

            <div id="formMessage" class="form-message" role="alert" hidden></div>

            <form id="bindForm" method="post" action="" novalidate>
                <?php vs_auth_csrf_field(); ?>
                <div class="field">
                    <input id="username" name="username" type="text" placeholder="请输入已注册账号" autocomplete="username" maxlength="64" required aria-label="用户名或邮箱">
                </div>

                <div class="field">
                    <div class="input-wrap">
                        <input id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required aria-label="密码">
                        <?php echo vs_auth_toggle_password_html(); ?>
                    </div>
                </div>

                <?php echo vs_auth_submit_btn('确认绑定并登录', 'bindBtn', 'login-btn'); ?>

                <div class="divider">
                    <a href="<?php echo vs_e($base); ?>/user/login">返回登录</a>
                </div>
                <a class="auth-home-link" href="<?php echo vs_e($base); ?>/">← 返回站点首页</a>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var form = document.getElementById('bindForm');
    var messageEl = document.getElementById('formMessage');
    var bindBtn = document.getElementById('bindBtn');
    if (!form) return;

    function showMessage(text, type) {
        if (text && window.VsToast) {
            VsToast.show(text, type === 'error' ? 'error' : 'success');
            if (messageEl) messageEl.hidden = true;
            return;
        }
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = 'form-message form-message--' + type;
        messageEl.hidden = false;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (bindBtn) bindBtn.disabled = true;

        fetch(window.location.href, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code === 1) {
                    showMessage(data.msg || '绑定成功', 'success');
                    if (data.url) {
                        setTimeout(function () { window.location.href = data.url; }, 800);
                    }
                } else {
                    showMessage(data.msg || '绑定失败', 'error');
                }
            })
            .catch(function () {
                showMessage('网络异常，请稍后重试', 'error');
            })
            .finally(function () {
                if (bindBtn) bindBtn.disabled = false;
            });
    });
})();
</script>

<?php ThemeManager::renderThemeAuthFoot(); ?>
