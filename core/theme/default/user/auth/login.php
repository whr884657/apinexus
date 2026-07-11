<?php
/**
 * 默认主题 · 用户登录页视图
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$base = isset($base) ? $base : $vsBase;
$expiredMsg = isset($expiredMsg) ? $expiredMsg : '';
$oauthError = isset($oauthError) ? $oauthError : '';
$oauthProviders = isset($oauthProviders) ? $oauthProviders : array('qq' => false, 'gitee' => false);

ThemeManager::renderThemeAuthHead($pageTitle);
?>

<div class="page">
    <?php vs_auth_left_panel(true); ?>

    <div class="right">
        <div class="form-box">
            <div class="header header-desktop">
                <h1><?php echo vs_e($siteName); ?></h1>
                <p class="header-sub">用户登录</p>
            </div>

            <div id="formMessage" class="form-message" role="alert" hidden></div>

            <form id="loginForm" method="post" action="" novalidate>
                <?php vs_auth_csrf_field(); ?>
                <div class="field">
                    <label for="username">用户名或邮箱</label>
                    <input id="username" name="username" type="text" placeholder="请输入用户名或邮箱" autocomplete="username" maxlength="64" required>
                </div>

                <div class="field">
                    <label for="password">密码</label>
                    <div class="input-wrap">
                        <input id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required>
                        <?php echo vs_auth_toggle_password_html(); ?>
                    </div>
                </div>

                <div class="row">
                    <label class="remember">
                        <input type="checkbox" id="rememberCredentials" value="1">
                        记住账号密码
                    </label>
                    <a href="<?php echo vs_e($base); ?>/user/forgot">忘记密码？</a>
                </div>

                <?php echo vs_auth_submit_btn('登 录', 'loginBtn', 'login-btn'); ?>

                <?php if (!empty($oauthProviders['qq']) || !empty($oauthProviders['gitee'])): ?>
                <div class="oauth-section">
                    <div class="oauth-section__label">第三方登录</div>
                    <div class="oauth-section__icons">
                        <?php if (!empty($oauthProviders['qq'])): ?>
                            <a href="<?php echo vs_e($base); ?>/user/oauth/start.php?provider=qq" class="oauth-icon" title="QQ 登录" aria-label="QQ 登录">
                                <img src="<?php echo vs_e($base); ?>/assets/img/QQ.svg" alt="" width="22" height="22">
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($oauthProviders['gitee'])): ?>
                            <a href="<?php echo vs_e($base); ?>/user/oauth/start.php?provider=gitee" class="oauth-icon" title="Gitee 登录" aria-label="Gitee 登录">
                                <img src="<?php echo vs_e($base); ?>/assets/img/gitee.svg" alt="" width="22" height="22">
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="divider">
                    还没有账号？<a href="<?php echo vs_e($base); ?>/user/register">立即注册</a>
                </div>
            </form>
        </div>
    </div>
</div>

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
        messageEl.className = 'form-message form-message--' + type;
        messageEl.hidden = false;
    }
    function hideMessage() { if (messageEl) messageEl.hidden = true; }
    if (expiredMsg) showMessage(expiredMsg, 'error');
    if (oauthError) showMessage(oauthError, 'error');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();
        var username = form.username.value.trim();
        var password = form.password.value;
        if (!username) { showMessage('请输入用户名或邮箱', 'error'); form.username.focus(); return; }
        if (!password) { showMessage('请输入密码', 'error'); form.password.focus(); return; }
        if (loginBtn) loginBtn.disabled = true;
        var body = new FormData(form);
        body.append('action', 'login');
        fetch(form.action || window.location.href, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code === 1) {
                    saveCredentials(username, password, rememberEl && rememberEl.checked);
                    showMessage(data.msg || '登录成功', 'success');
                    if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
                } else showMessage(data.msg || '登录失败', 'error');
            })
            .catch(function () { showMessage('网络异常，请稍后重试', 'error'); })
            .finally(function () { if (loginBtn) loginBtn.disabled = false; });
    });
})();
</script>

<?php ThemeManager::renderThemeAuthFoot(); ?>
