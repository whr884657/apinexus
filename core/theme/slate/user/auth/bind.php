<?php
if (!defined('VS_THEME_RENDER')) { exit; }
$base = isset($base) ? $base : $vsBase;

ThemeManager::renderThemeAuthHead('绑定' . $providerLabel);
vs_slate_auth_page_start();
vs_slate_auth_header('绑定' . $providerLabel . '账号', '请使用已注册账号验证身份');
?>

<div class="st-auth__msg st-auth__msg--info">仅支持已注册用户。请使用本站用户名/邮箱与密码完成绑定；未注册请先 <a href="<?php echo vs_e($base); ?>/user/register">注册</a>。</div>

<?php if ($displayName !== ''): ?>
    <div class="st-auth__msg st-auth__msg--info"><?php echo vs_e($providerLabel); ?> 账号：<?php echo vs_e($displayName); ?></div>
<?php endif; ?>

<div id="formMessage" class="st-auth__msg" role="alert" hidden></div>

<form id="bindForm" method="post" action="" novalidate>
    <?php vs_auth_csrf_field(); ?>
    <div class="st-auth__field">
        <label for="username">用户名或邮箱</label>
        <input class="st-auth__input" id="username" name="username" type="text" placeholder="请输入已注册账号" autocomplete="username" maxlength="64" required>
    </div>
    <div class="st-auth__field">
        <label for="password">密码</label>
        <div class="st-auth__pw-wrap">
            <input class="st-auth__input" id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required>
            <button type="button" class="st-auth__pw-toggle" data-st-pw-toggle aria-label="显示密码">显示</button>
        </div>
    </div>
    <button type="submit" class="st-auth__submit" id="bindBtn">确认绑定并登录</button>
    <div class="st-auth__foot"><a href="<?php echo vs_e($base); ?>/user/login">返回登录</a></div>
</form>

<?php vs_slate_auth_page_end(); ?>

<script>
(function () {
    'use strict';
    var form = document.getElementById('bindForm');
    var messageEl = document.getElementById('formMessage');
    var bindBtn = document.getElementById('bindBtn');
    if (!form) return;

    function showMessage(text, type) {
        if (text && window.VsToast) { VsToast.show(text, type === 'error' ? 'error' : 'success'); if (messageEl) messageEl.hidden = true; return; }
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = 'st-auth__msg st-auth__msg--' + type;
        messageEl.hidden = false;
        if (type === 'error' && window.stAuthShake) window.stAuthShake();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (window.stAuthSetLoading) window.stAuthSetLoading(bindBtn, true);
        fetch(window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code === 1) {
                    showMessage(data.msg || '绑定成功', 'success');
                    if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
                } else showMessage(data.msg || '绑定失败', 'error');
            })
            .catch(function () { showMessage('网络异常，请稍后重试', 'error'); })
            .finally(function () { if (window.stAuthSetLoading) window.stAuthSetLoading(bindBtn, false); });
    });
})();
</script>

<?php ThemeManager::renderThemeAuthFoot(); ?>
