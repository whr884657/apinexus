<?php
/**
 * 文件：user/oauth/bind.php
 * 作用：将第三方账号绑定到已有用户（须先注册）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';
require_once VS_ROOT . '/admin/includes/auth_layout.php';

InstallChecker::requireInstalled();
UserAuth::redirectIfLoggedIn();

$base = vs_base_url();
$pending = OAuthService::getBindPending();

if ($pending === null) {
    vs_redirect($base . '/user/login.php?oauth_error=' . rawurlencode('绑定会话已过期，请重新发起第三方登录'));
}

$provider = $pending['provider'];
$providerLabel = $provider === 'qq' ? 'QQ' : 'Gitee';
$identity = $pending['identity'];
$displayName = '';
if ($provider === 'qq') {
    $displayName = isset($identity['nickname']) ? trim((string) $identity['nickname']) : '';
} else {
    $displayName = isset($identity['name']) ? trim((string) $identity['name']) : '';
    if ($displayName === '' && isset($identity['login'])) {
        $displayName = (string) $identity['login'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_auth_require_post();

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $result = OAuthService::bindPendingToAccount($username, $password);
    if ($result['ok']) {
        vs_auth_json(array(
            'code' => 1,
            'msg'  => $result['msg'],
            'url'  => $base . '/user/index.php',
        ));
    }

    vs_auth_json(array('code' => 0, 'msg' => $result['msg']));
}

vs_auth_head('绑定' . $providerLabel);
?>

<div class="page">
    <?php vs_auth_left_panel(false); ?>

    <div class="right">
        <div class="form-box">
            <div class="header header-desktop">
                <h1>绑定<?php echo vs_e($providerLabel); ?>账号</h1>
                <p class="header-sub">第三方账号尚未绑定，请使用已注册账号验证身份</p>
            </div>

            <?php
            vs_render_notice(
                'info',
                '仅支持已注册用户',
                '请使用您在本站注册的用户名/邮箱与密码完成绑定。未注册账号请先<a href="' . vs_e($base) . '/user/register.php" class="vs-notice__link">注册</a>。',
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
                    <label for="username">用户名或邮箱</label>
                    <input id="username" name="username" type="text" placeholder="请输入已注册账号" autocomplete="username" maxlength="64" required>
                </div>

                <div class="field">
                    <label for="password">密码</label>
                    <div class="input-wrap">
                        <input id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required>
                        <?php echo vs_auth_toggle_password_html(); ?>
                    </div>
                </div>

                <?php echo vs_auth_submit_btn('确认绑定并登录', 'bindBtn', 'login-btn'); ?>

                <div class="divider">
                    <a href="<?php echo vs_e($base); ?>/user/login.php">返回登录</a>
                </div>
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

<?php vs_auth_foot(); ?>
