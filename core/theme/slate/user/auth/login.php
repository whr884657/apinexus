<?php
/**
 * 青绿平台 · 用户登录
 * 变量由 ThemeManager::renderAuthPage extract 注入；此处全部兜底，避免静态分析误报
 *
 * @var string $vsBase
 * @var string $base
 * @var string $pageTitle
 * @var string $expiredMsg
 * @var string $oauthError
 * @var array $oauthProviders
 * @var string $loginRedirect
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

ThemeManager::renderThemeAuthHead($pageTitle);
vs_slate_auth_shell_start('用户登录', '欢迎回来，请登录您的账号');
?>

<div id="formMessage" class="st-auth__msg" role="alert" hidden></div>

<form id="loginForm" method="post" action="" novalidate>
    <?php vs_auth_csrf_field(); ?>
    <?php if ($loginRedirect !== ''): ?>
    <input type="hidden" name="redirect" value="<?php echo vs_e($loginRedirect); ?>">
    <?php endif; ?>
    <div class="st-auth__field">
        <input class="st-auth__input" id="username" name="username" type="text" placeholder="请输入用户名或邮箱" autocomplete="username" maxlength="64" required aria-label="用户名或邮箱">
    </div>
    <div class="st-auth__field">
        <div class="st-auth__pw-wrap">
            <input class="st-auth__input" id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required aria-label="密码">
            <?php echo vs_slate_pw_toggle_html(); ?>
        </div>
    </div>
    <?php vs_captcha_field(Captcha::SCENE_USER_LOGIN); ?>
    <div class="st-auth__row">
        <span></span>
        <a href="<?php echo vs_e($base); ?>/user/forgot">忘记密码？</a>
    </div>
                <button type="submit" class="st-auth__submit" id="loginBtn">登 录</button>

    <?php if (!empty($oauthProviders['qq']) || !empty($oauthProviders['gitee'])): ?>
    <div class="st-auth__oauth">
        <div class="st-auth__oauth-label">第三方登录</div>
        <div class="st-auth__oauth-icons">
            <?php if (!empty($oauthProviders['qq'])): ?>
                <a href="<?php echo vs_e($base); ?>/user/oauth/start.php?provider=qq" title="QQ 登录"><img src="<?php echo vs_e(SiteMedia::imgUrl('QQ.svg')); ?>" alt="QQ" width="22" height="22"></a>
            <?php endif; ?>
            <?php if (!empty($oauthProviders['gitee'])): ?>
                <a href="<?php echo vs_e($base); ?>/user/oauth/start.php?provider=gitee" title="Gitee 登录"><img src="<?php echo vs_e(SiteMedia::imgUrl('gitee.svg')); ?>" alt="Gitee" width="22" height="22"></a>
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
    var expiredMsg = <?php echo json_encode($expiredMsg, JSON_UNESCAPED_UNICODE); ?>;
    var oauthError = <?php echo json_encode($oauthError, JSON_UNESCAPED_UNICODE); ?>;
    if (!form) return;
    // v13.25.0：强制清除历史「记住密码」明文，禁止再读写 localStorage 凭证
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

    if (expiredMsg) showMessage(expiredMsg, 'error');
    if (oauthError) showMessage(oauthError, 'error');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
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
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            }).then(function (r) {
                return r.text().then(function (text) {
                    try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                });
            });
        post
            .then(function (data) {
                if (!data || typeof data !== 'object') { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); return; }
                if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
                if (data.code === 1) {
                    showMessage(data.msg || '登录成功', 'success');
                    if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
                } else {
                    showMessage(data.msg || '登录失败', 'error');
                    if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
                }
            })
            .catch(function () { showMessage('网络异常或会话已过期，请刷新页面后重试', 'error'); })
            .finally(function () { if (window.stAuthSetLoading) window.stAuthSetLoading(loginBtn, false); });
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
