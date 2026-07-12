<?php
if (!defined('VS_THEME_RENDER')) { exit; }
$base = isset($base) ? $base : $vsBase;

ThemeManager::renderThemeAuthHead('用户注册');
vs_slate_auth_shell_start('注册账号', '使用邮箱验证注册，完成验证后即可使用');
?>

<div id="formMessage" class="st-auth__msg" role="alert" hidden></div>

<?php if (!$mailEnabled): ?>
    <div class="st-auth__msg st-auth__msg--error"><?php echo vs_e($mailDisabledMsg); ?></div>
<?php endif; ?>

<form id="registerForm" method="post" action="" novalidate>
    <?php vs_auth_csrf_field(); ?>
    <div class="st-auth__field">
        <label for="username">用户名</label>
        <input class="st-auth__input" id="username" name="username" type="text" placeholder="3～50 个字符" autocomplete="username" maxlength="50" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
    </div>
    <div class="st-auth__field">
        <label for="email">邮箱</label>
        <input class="st-auth__input" id="email" name="email" type="email" placeholder="请输入邮箱" autocomplete="email" maxlength="64" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
    </div>
    <div class="st-auth__field">
        <label for="code">验证码</label>
        <div class="st-auth__group">
            <input class="st-auth__input" id="code" name="code" type="text" placeholder="请输入验证码" autocomplete="one-time-code" maxlength="6" inputmode="numeric" pattern="[0-9]*" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
            <button type="button" class="st-auth__code-btn" id="sendCodeBtn" <?php echo $mailEnabled ? '' : 'disabled'; ?>>获取验证码</button>
        </div>
    </div>
    <div class="st-auth__field">
        <label for="password">密码</label>
        <div class="st-auth__pw-wrap">
            <input class="st-auth__input" id="password" name="password" type="password" placeholder="请设置密码（至少6位）" autocomplete="new-password" maxlength="64" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
            <button type="button" class="st-auth__pw-toggle" data-st-pw-toggle aria-label="显示密码">显示</button>
        </div>
    </div>
    <div class="st-auth__field">
        <label for="confirm_password">确认密码</label>
        <input class="st-auth__input" id="confirm_password" name="confirm_password" type="password" placeholder="请再次输入密码" autocomplete="new-password" maxlength="64" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
    </div>
    <button type="submit" class="st-auth__submit" id="submitBtn" <?php echo $mailEnabled ? '' : 'disabled'; ?>>立即注册</button>
    <div class="st-auth__foot">已有账号？<a href="<?php echo vs_e($base); ?>/user/login">返回登录</a></div>
</form>

<?php vs_slate_auth_shell_end(); ?>

<script>
(function () {
    'use strict';
    var form = document.getElementById('registerForm');
    var messageEl = document.getElementById('formMessage');
    var sendCodeBtn = document.getElementById('sendCodeBtn');
    var submitBtn = document.getElementById('submitBtn');
    var mailEnabled = <?php echo $mailEnabled ? 'true' : 'false'; ?>;
    var countdown = 0;
    var countdownTimer = null;
    if (!form) return;

    function showMessage(text, type) {
        if (text && window.VsToast) { VsToast.show(text, type === 'error' ? 'error' : 'success'); if (messageEl) messageEl.hidden = true; return; }
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = 'st-auth__msg st-auth__msg--' + type;
        messageEl.hidden = false;
        if (type === 'error' && window.stAuthShake) window.stAuthShake();
    }
    function hideMessage() { if (messageEl) messageEl.hidden = true; }

    function startCountdown(seconds) {
        countdown = seconds;
        if (!sendCodeBtn) return;
        sendCodeBtn.disabled = true;
        sendCodeBtn.textContent = countdown + 's 后重发';
        countdownTimer = setInterval(function () {
            countdown -= 1;
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = '获取验证码';
                return;
            }
            sendCodeBtn.textContent = countdown + 's 后重发';
        }, 1000);
    }

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', function () {
            hideMessage();
            if (!mailEnabled) return;
            var username = form.username.value.trim();
            var email = form.email.value.trim();
            if (!username) { showMessage('请先输入用户名', 'error'); form.username.focus(); return; }
            if (!email) { showMessage('请先输入邮箱', 'error'); form.email.focus(); return; }
            sendCodeBtn.disabled = true;
            var body = new FormData();
            body.append('action', 'send_code');
            body.append('username', username);
            body.append('email', email);
            if (form.csrf_token) body.append('csrf_token', form.csrf_token.value);
            fetch(window.location.href, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.code === 1) { showMessage(data.msg || '验证码已发送', 'success'); startCountdown(60); }
                    else { showMessage(data.msg || '发送失败', 'error'); sendCodeBtn.disabled = false; }
                })
                .catch(function () { showMessage('网络异常，请稍后重试', 'error'); sendCodeBtn.disabled = false; });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();
        if (!mailEnabled) return;
        var username = form.username.value.trim();
        var email = form.email.value.trim();
        var code = form.code.value.trim();
        var password = form.password.value;
        var confirm = form.confirm_password.value;
        if (!username) { showMessage('请输入用户名', 'error'); return; }
        if (!email) { showMessage('请输入邮箱', 'error'); return; }
        if (!code) { showMessage('请输入验证码', 'error'); return; }
        if (password.length < 6) { showMessage('密码至少 6 位', 'error'); return; }
        if (password !== confirm) { showMessage('两次输入的密码不一致', 'error'); return; }
        if (window.stAuthSetLoading) window.stAuthSetLoading(submitBtn, true);
        var body = new FormData(form);
        body.append('action', 'register');
        fetch(window.location.href, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code === 1) {
                    showMessage(data.msg || '注册成功', 'success');
                    if (data.url) setTimeout(function () { window.location.href = data.url; }, 1200);
                } else showMessage(data.msg || '注册失败', 'error');
            })
            .catch(function () { showMessage('网络异常，请稍后重试', 'error'); })
            .finally(function () { if (window.stAuthSetLoading) window.stAuthSetLoading(submitBtn, false); });
    });
})();
</script>

<?php ThemeManager::renderThemeAuthFoot(); ?>
