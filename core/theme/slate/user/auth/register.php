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
    <?php vs_auth_mail_ticket_field(AuthSecurity::MAIL_PURPOSE_USER_REGISTER); ?>
    <div class="st-auth__field st-auth__field--role-segment">
        <div class="st-role-segment" id="roleSegment">
            <div class="st-role-segment__track" id="roleSegmentTrack" data-role="user" role="radiogroup" aria-label="账号类型">
                <span class="st-role-segment__thumb" aria-hidden="true"></span>
                <button type="button" class="st-role-segment__btn is-active" data-role="user" aria-pressed="true" <?php echo $mailEnabled ? '' : 'disabled'; ?>>普通用户</button>
                <button type="button" class="st-role-segment__btn" data-role="developer" aria-pressed="false" <?php echo $mailEnabled ? '' : 'disabled'; ?>>开发者</button>
            </div>
            <input type="hidden" name="role" id="roleInput" value="user" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
        </div>
    </div>
    <div class="st-auth__field">
        <input class="st-auth__input" id="username" name="username" type="text" placeholder="请输入用户名（3～50 个字符）" autocomplete="username" maxlength="50" required aria-label="用户名" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
    </div>
    <div class="st-auth__field">
        <input class="st-auth__input" id="email" name="email" type="email" placeholder="请输入邮箱" autocomplete="email" maxlength="64" required aria-label="邮箱" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
    </div>
    <div class="st-auth__field">
        <div class="st-auth__group">
            <input class="st-auth__input" id="code" name="code" type="text" placeholder="请输入验证码" autocomplete="one-time-code" maxlength="6" inputmode="numeric" pattern="[0-9]*" aria-label="验证码" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
            <button type="button" class="st-auth__code-btn" id="sendCodeBtn" <?php echo $mailEnabled ? '' : 'disabled'; ?>>获取验证码</button>
        </div>
    </div>
    <div class="st-auth__field">
        <div class="st-auth__pw-wrap">
            <input class="st-auth__input" id="password" name="password" type="password" placeholder="请设置密码（至少6位）" autocomplete="new-password" maxlength="64" required aria-label="密码" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
            <button type="button" class="st-auth__pw-toggle" data-st-pw-toggle aria-label="显示密码">显示</button>
        </div>
    </div>
    <div class="st-auth__field">
        <input class="st-auth__input" id="confirm_password" name="confirm_password" type="password" placeholder="请再次输入密码" autocomplete="new-password" maxlength="64" required aria-label="确认密码" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
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

    var roleTrack = document.getElementById('roleSegmentTrack');
    var roleInput = document.getElementById('roleInput');
    var ROLE_HINTS = {
        user: '普通用户：可在用户中心生成密钥，调用平台全部公开接口（含站长与其他用户发布的接口）。',
        developer: '开发者：拥有普通用户全部能力，并可在「API 管理」中发布自己的接口，为平台提供技能与支持。'
    };

    function normalizeRole(role) {
        return role === 'developer' ? 'developer' : 'user';
    }

    function setRole(role, showHint) {
        if (!roleTrack || !roleInput) return;
        role = normalizeRole(role);
        roleTrack.dataset.role = role;
        roleInput.value = role;
        roleTrack.querySelectorAll('.st-role-segment__btn').forEach(function (btn) {
            var active = btn.dataset.role === role;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (showHint && ROLE_HINTS[role]) {
            showMessage(ROLE_HINTS[role], 'success');
        }
    }

    if (roleTrack) {
        roleTrack.querySelectorAll('.st-role-segment__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var role = normalizeRole(btn.dataset.role);
                if (roleInput && roleInput.value !== role) {
                    setRole(role, true);
                }
            });
        });
    }

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

    function parseWaitSeconds(msg) {
        var match = /请\s*(\d+)\s*秒/.exec(msg || '');
        return match ? parseInt(match[1], 10) : 120;
    }

    function applyMailTicket(data) {
        var el = document.getElementById('mailTicket');
        if (el && data && data.mail_ticket) el.value = data.mail_ticket;
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
            var mailTicketEl = document.getElementById('mailTicket');
            if (mailTicketEl) body.append('mail_ticket', mailTicketEl.value);
            fetch(window.location.href, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    applyMailTicket(data);
                    if (data.code === 1) { showMessage(data.msg || '验证码已发送', 'success'); startCountdown(120); }
                    else { showMessage(data.msg || '发送失败', 'error'); startCountdown(parseWaitSeconds(data.msg)); }
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
