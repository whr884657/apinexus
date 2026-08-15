<?php
/**
 * 青绿平台 · 用户登录（账号密码 / 邮箱验证码）
 * 变量由 ThemeManager::renderAuthPage extract 注入；此处全部兜底，避免静态分析误报
 *
 * @var string $vsBase
 * @var string $base
 * @var string $pageTitle
 * @var string $expiredMsg
 * @var string $oauthError
 * @var array $oauthProviders
 * @var string $loginRedirect
 * @var bool   $registerOpen
 * @var bool   $mailEnabled
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? (string) $vsBase : rtrim(vs_base_url(), '/');
$base = (isset($base) && (string) $base !== '') ? (string) $base : $vsBase;
$pageTitle = isset($pageTitle) ? (string) $pageTitle : '用户登录';
$expiredMsg = isset($expiredMsg) ? (string) $expiredMsg : '';
$oauthError = isset($oauthError) ? (string) $oauthError : '';
$oauthProviders = isset($oauthProviders) && is_array($oauthProviders)
    ? $oauthProviders
    : array('qq' => false, 'gitee' => false);
$loginRedirect = isset($loginRedirect) ? (string) $loginRedirect : '';
$registerOpen = !isset($registerOpen) || !empty($registerOpen);
$mailEnabled = !isset($mailEnabled) || !empty($mailEnabled);

ThemeManager::renderThemeAuthHead($pageTitle);
vs_slate_auth_shell_start('用户登录', '欢迎回来，请登录您的账号');
?>

<div id="formMessage" class="st-auth__msg" role="alert" hidden></div>

<form id="loginForm" method="post" action="" novalidate data-login-mode="password">
    <?php vs_auth_csrf_field(); ?>
    <?php vs_auth_mail_ticket_field(AuthSecurity::MAIL_PURPOSE_USER_LOGIN); ?>
    <?php if ($loginRedirect !== ''): ?>
    <input type="hidden" name="redirect" value="<?php echo vs_e($loginRedirect); ?>">
    <?php endif; ?>

    <div id="modePassword" data-mode-panel="password">
        <div class="st-auth__field">
            <input class="st-auth__input" id="username" name="username" type="text" placeholder="请输入用户名或邮箱" autocomplete="username" maxlength="64" required aria-label="用户名或邮箱">
        </div>
        <div class="st-auth__field">
            <div class="st-auth__pw-wrap">
                <input class="st-auth__input" id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required aria-label="密码">
                <?php echo vs_slate_pw_toggle_html(); ?>
            </div>
        </div>
    </div>

    <div id="modeCode" data-mode-panel="code" hidden>
        <div class="st-auth__field">
            <input class="st-auth__input" id="email" name="email" type="email" placeholder="请输入注册邮箱" autocomplete="email" maxlength="64" aria-label="注册邮箱" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
        </div>
    </div>

    <?php vs_captcha_field(Captcha::SCENE_USER_LOGIN); ?>

    <div id="modeCodeCode" data-mode-panel="code" hidden>
        <div class="st-auth__field">
            <div class="st-auth__group">
                <input class="st-auth__input" id="code" name="code" type="text" placeholder="请输入邮箱验证码" autocomplete="one-time-code" maxlength="6" inputmode="numeric" pattern="[0-9]*" aria-label="邮箱验证码" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                <button type="button" class="st-auth__code-btn" id="sendCodeBtn" <?php echo $mailEnabled ? '' : 'disabled'; ?>>获取验证码</button>
            </div>
        </div>
    </div>

    <div class="st-auth__row">
        <a href="#" id="toggleLoginMode" role="button">验证码登录</a>
        <a href="<?php echo vs_e($base); ?>/user/forgot">忘记密码？</a>
    </div>
    <button type="submit" class="st-auth__submit" id="loginBtn">登 录</button>

    <?php if (!empty($oauthProviders['qq']) || !empty($oauthProviders['gitee'])): ?>
    <div class="st-auth__oauth">
        <div class="st-auth__oauth-label">第三方登录</div>
        <div class="st-auth__oauth-icons">
            <?php if (!empty($oauthProviders['qq'])): ?>
                <a href="<?php echo vs_e($base); ?>/user/oauth/start?provider=qq" title="QQ 登录"><img src="<?php echo vs_e(SiteMedia::imgUrl('QQ.svg')); ?>" alt="QQ" width="22" height="22"></a>
            <?php endif; ?>
            <?php if (!empty($oauthProviders['gitee'])): ?>
                <a href="<?php echo vs_e($base); ?>/user/oauth/start?provider=gitee" title="Gitee 登录"><img src="<?php echo vs_e(SiteMedia::imgUrl('gitee.svg')); ?>" alt="Gitee" width="22" height="22"></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($registerOpen): ?>
    <div class="st-auth__foot">还没有账号？<a href="<?php echo vs_e($base); ?>/user/register">立即注册</a></div>
    <?php endif; ?>
</form>

<?php vs_slate_auth_shell_end(); ?>

<script>
(function () {
    'use strict';
    var form = document.getElementById('loginForm');
    var messageEl = document.getElementById('formMessage');
    var loginBtn = document.getElementById('loginBtn');
    var sendCodeBtn = document.getElementById('sendCodeBtn');
    var toggleEl = document.getElementById('toggleLoginMode');
    var expiredMsg = <?php echo json_encode($expiredMsg, JSON_UNESCAPED_UNICODE); ?>;
    var oauthError = <?php echo json_encode($oauthError, JSON_UNESCAPED_UNICODE); ?>;
    var mailEnabled = <?php echo $mailEnabled ? 'true' : 'false'; ?>;
    var mode = 'password';
    var countdown = 0;
    var countdownTimer = null;
    if (!form) return;

    try {
        localStorage.removeItem('vs_user_login_credentials');
        localStorage.removeItem('vs_login_credentials');
    } catch (err) {}

    function showMessage(text, type) {
        if (text && window.VsToast) { VsToast.show(text, type === 'error' ? 'error' : 'success'); if (messageEl) messageEl.hidden = true; return; }
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = 'st-auth__msg st-auth__msg--' + type;
        messageEl.hidden = false;
        if (type === 'error' && window.stAuthShake) window.stAuthShake();
    }
    function hideMessage() { if (messageEl) messageEl.hidden = true; }

    function applyMailTicket(data) {
        var el = document.getElementById('mailTicket');
        if (el && data && data.mail_ticket) el.value = data.mail_ticket;
    }
    function parseWaitSeconds(msg) {
        var match = /请\s*(\d+)\s*秒/.exec(msg || '');
        return match ? parseInt(match[1], 10) : 0;
    }
    function startCountdown(seconds) {
        countdown = seconds;
        if (!sendCodeBtn) return;
        sendCodeBtn.disabled = true;
        sendCodeBtn.textContent = countdown + 's 后重发';
        if (countdownTimer) clearInterval(countdownTimer);
        countdownTimer = setInterval(function () {
            countdown -= 1;
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = '获取验证码';
                return;
            }
            sendCodeBtn.textContent = countdown + 's 后重发';
        }, 1000);
    }
    function resetSendCodeBtn() {
        if (!sendCodeBtn || countdown > 0) return;
        sendCodeBtn.disabled = false;
        sendCodeBtn.textContent = '获取验证码';
    }
    function setMode(next) {
        mode = next === 'code' ? 'code' : 'password';
        form.setAttribute('data-login-mode', mode);
        var panels = form.querySelectorAll('[data-mode-panel]');
        for (var i = 0; i < panels.length; i++) {
            panels[i].hidden = panels[i].getAttribute('data-mode-panel') !== mode;
        }
        if (form.username) form.username.required = mode === 'password';
        if (form.password) form.password.required = mode === 'password';
        if (form.email) form.email.required = mode === 'code';
        if (form.code) form.code.required = mode === 'code';
        if (toggleEl) toggleEl.textContent = mode === 'code' ? '密码登录' : '验证码登录';
        hideMessage();
        if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
    }

    if (toggleEl) {
        toggleEl.addEventListener('click', function (e) {
            e.preventDefault();
            if (mode === 'password' && !mailEnabled) {
                showMessage('邮箱发信功能尚未配置，无法使用验证码登录', 'error');
                return;
            }
            setMode(mode === 'password' ? 'code' : 'password');
        });
    }

    if (expiredMsg) showMessage(expiredMsg, 'error');
    if (oauthError) showMessage(oauthError, 'error');

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', function () {
            hideMessage();
            if (!mailEnabled || mode !== 'code') {
                if (!mailEnabled) showMessage('邮箱发信功能尚未配置', 'error');
                return;
            }
            var email = form.email ? form.email.value.trim() : '';
            if (!email) { showMessage('请先输入邮箱', 'error'); if (form.email) form.email.focus(); return; }
            sendCodeBtn.disabled = true;
            var body = new FormData();
            body.append('action', 'send_code');
            body.append('email', email);
            if (form.csrf_token) body.append('csrf_token', form.csrf_token.value);
            var mailTicketEl = document.getElementById('mailTicket');
            if (mailTicketEl) body.append('mail_ticket', mailTicketEl.value);
            var doSend = function () {
                if (window.VsCaptcha && window.VsCaptcha.appendToFormData) window.VsCaptcha.appendToFormData(body);
                return fetch(window.location.href, {
                    method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                }).then(function (res) {
                    return res.text().then(function (text) {
                        try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                    });
                }).then(function (data) {
                    if (!data || typeof data !== 'object') { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); resetSendCodeBtn(); return; }
                    applyMailTicket(data);
                    if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
                    if (data.code === 1) {
                        showMessage(data.msg || '验证码已发送', 'success');
                        startCountdown(120);
                        if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
                    } else {
                        showMessage(data.msg || '发送失败', 'error');
                        var waitSec = parseWaitSeconds(data.msg);
                        if (waitSec > 0) startCountdown(waitSec); else resetSendCodeBtn();
                        if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
                    }
                }).catch(function () { showMessage('网络异常，请稍后重试', 'error'); resetSendCodeBtn(); });
            };
            if (window.VsCaptcha && window.VsCaptcha.enabled && window.VsCaptcha.ensure) {
                window.VsCaptcha.ensure(form).then(doSend).catch(function (err) {
                    showMessage((err && err.message) || '请先完成行为验证', 'error');
                    resetSendCodeBtn();
                });
            } else { doSend(); }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();

        if (mode === 'code') {
            if (!mailEnabled) { showMessage('邮箱发信功能尚未配置', 'error'); return; }
            var email = form.email ? form.email.value.trim() : '';
            var code = form.code ? form.code.value.trim() : '';
            if (!email || !code) { showMessage('请完整填写邮箱和验证码', 'error'); return; }
            if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, true);
            var postCode = (window.VsAuthCsrf && VsAuthCsrf.postForm)
                ? VsAuthCsrf.postForm(form, { action: 'login_code' })
                : fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: (function () { var b = new FormData(form); b.append('action', 'login_code'); return b; })(),
                    credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' }
                }).then(function (r) {
                    return r.text().then(function (text) {
                        try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                    });
                });
            postCode.then(function (data) {
                if (!data || typeof data !== 'object') { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); return; }
                if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
                applyMailTicket(data);
                if (data.code === 1) {
                    showMessage(data.msg || '登录成功', 'success');
                    if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
                } else {
                    showMessage(data.msg || '登录失败', 'error');
                }
            }).catch(function () {
                showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
            }).finally(function () { if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, false); });
            return;
        }

        var username = form.username.value.trim();
        var password = form.password.value;
        if (!username || !password) { showMessage('请完整填写账号和密码', 'error'); return; }
        if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, true);
        var runLogin = function () {
        var post = (window.VsAuthCsrf && VsAuthCsrf.postForm)
            ? VsAuthCsrf.postForm(form, { action: 'login' })
            : fetch(form.action || window.location.href, {
                method: 'POST',
                body: (function () { var b = new FormData(form); b.append('action', 'login'); return b; })(),
                credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' }
            }).then(function (r) {
                return r.text().then(function (text) {
                    try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                });
            });
        post.then(function (data) {
            if (!data || typeof data !== 'object') { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); return; }
            if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
            if (data.code === 1) {
                showMessage(data.msg || '登录成功', 'success');
                if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
            } else {
                showMessage(data.msg || '登录失败', 'error');
                if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
            }
        }).catch(function () {
            showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
        }).finally(function () { if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, false); });
        };
        if (window.VsCaptcha && window.VsCaptcha.enabled && window.VsCaptcha.ensure) {
            window.VsCaptcha.ensure(form).then(runLogin).catch(function (err) {
                showMessage((err && err.message) || '请先完成行为验证', 'error');
                if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, false);
            });
        } else { runLogin(); }
    });
})();
</script>

<?php ThemeManager::renderThemeAuthFoot(); ?>
