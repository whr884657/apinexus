<?php
/**
 * 青绿平台 · 用户登录
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$base = isset($base) ? $base : $vsBase;
$expiredMsg = isset($expiredMsg) ? $expiredMsg : '';
$oauthError = isset($oauthError) ? $oauthError : '';
$oauthProviders = isset($oauthProviders) ? $oauthProviders : array('qq' => false, 'gitee' => false);

ThemeManager::renderThemeAuthHead($pageTitle);
vs_slate_auth_shell_start('用户登录', '欢迎回来，请登录您的账号');
?>

<div id="formMessage" class="st-auth__msg" role="alert" hidden></div>

<form id="loginForm" method="post" action="" novalidate>
    <?php vs_auth_csrf_field(); ?>
    <div class="st-auth__field">
        <input class="st-auth__input" id="username" name="username" type="text" placeholder="请输入用户名或邮箱" autocomplete="username" maxlength="64" required aria-label="用户名或邮箱">
    </div>
    <div class="st-auth__field">
        <div class="st-auth__pw-wrap">
            <input class="st-auth__input" id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required aria-label="密码">
            <button type="button" class="st-auth__pw-toggle" data-st-pw-toggle aria-label="显示密码">显示</button>
        </div>
    </div>
    <div class="st-auth__row">
        <label><input type="checkbox" id="rememberCredentials" value="1"> 记住账号</label>
        <a href="<?php echo vs_e($base); ?>/user/forgot">忘记密码？</a>
    </div>
    <button type="submit" class="st-auth__submit" id="loginBtn">登 录</button>

    <?php if (!empty($oauthProviders['qq']) || !empty($oauthProviders['gitee'])): ?>
    <div class="st-auth__oauth">
        <div class="st-auth__oauth-label">第三方登录</div>
        <div class="st-auth__oauth-icons">
            <?php if (!empty($oauthProviders['qq'])): ?>
                <a href="<?php echo vs_e($base); ?>/user/oauth/start.php?provider=qq" title="QQ 登录"><img src="<?php echo vs_e($base); ?>/assets/img/QQ.svg" alt="QQ" width="22" height="22"></a>
            <?php endif; ?>
            <?php if (!empty($oauthProviders['gitee'])): ?>
                <a href="<?php echo vs_e($base); ?>/user/oauth/start.php?provider=gitee" title="Gitee 登录"><img src="<?php echo vs_e($base); ?>/assets/img/gitee.svg" alt="Gitee" width="22" height="22"></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="st-auth__foot">还没有账号？<a href="<?php echo vs_e($base); ?>/user/register">立即注册</a></div>
</form>

<?php vs_slate_auth_shell_end(); ?>

<script>
(function () {
    'use strict';
    var form = document.getElementById('loginForm');
    var messageEl = document.getElementById('formMessage');
    var loginBtn = document.getElementById('loginBtn');
    var rememberEl = document.getElementById('rememberCredentials');
    var expiredMsg = <?php echo json_encode($expiredMsg, JSON_UNESCAPED_UNICODE); ?>;
    var oauthError = <?php echo json_encode($oauthError, JSON_UNESCAPED_UNICODE); ?>;
    var storageKey = 'vs_user_login_credentials';
    if (!form) return;

    function loadSavedCredentials() {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return;
            var saved = JSON.parse(raw);
            if (!saved || typeof saved.username !== 'string') return;
            form.username.value = saved.username;
            if (typeof saved.password === 'string') form.password.value = saved.password;
            if (rememberEl) rememberEl.checked = true;
        } catch (err) { localStorage.removeItem(storageKey); }
    }
    function saveCredentials(username, password, remember) {
        try {
            if (remember) localStorage.setItem(storageKey, JSON.stringify({ username: username, password: password }));
            else localStorage.removeItem(storageKey);
        } catch (err) {}
    }
    loadSavedCredentials();
    if (rememberEl) rememberEl.addEventListener('change', function () { if (!rememberEl.checked) localStorage.removeItem(storageKey); });

    function showMessage(text, type) {
        if (text && window.VsToast) { VsToast.show(text, type === 'error' ? 'error' : 'success'); if (messageEl) messageEl.hidden = true; return; }
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = 'st-auth__msg st-auth__msg--' + type;
        messageEl.hidden = false;
        if (type === 'error' && window.stAuthShake) window.stAuthShake();
    }

    if (expiredMsg) showMessage(expiredMsg, 'error');
    if (oauthError) showMessage(oauthError, 'error');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var username = form.username.value.trim();
        var password = form.password.value;
        if (!username || !password) { showMessage('请完整填写账号和密码', 'error'); return; }
        if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, true);
        var body = new FormData(form);
        body.append('action', 'login');
        fetch(form.action || window.location.href, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                return r.text().then(function (text) {
                    try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                });
            })
            .then(function (data) {
                if (!data || typeof data !== 'object') { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); return; }
                if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
                if (data.code === 1) {
                    saveCredentials(username, password, rememberEl && rememberEl.checked);
                    showMessage(data.msg || '登录成功', 'success');
                    if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
                } else showMessage(data.msg || '登录失败', 'error');
            })
            .catch(function () { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); })
            .finally(function () { if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, false); });
    });
})();
</script>

<?php ThemeManager::renderThemeAuthFoot(); ?>
