<?php
/**
 * 文件：admin/login.php
 * 作用：ApiNexus 管理员登录页面
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';
require_once __DIR__ . '/includes/auth_layout.php';

InstallChecker::requireInstalled();

$base = vs_base_url();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    vs_redirect($base . '/admin/login');
}

Auth::redirectIfLoggedIn();

$systemName = SiteContext::systemName();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    vs_auth_require_post();

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        vs_auth_json(array('code' => 0, 'msg' => '请输入账号和密码'));
    }

    $loginBlocked = AuthSecurity::checkLoginAllowed($username);
    if ($loginBlocked !== null) {
        vs_auth_json(array('code' => 0, 'msg' => $loginBlocked));
    }

    $captchaErr = Captcha::requireValid(Captcha::SCENE_ADMIN_LOGIN, $_POST);
    if ($captchaErr !== true) {
        vs_auth_json(array('code' => 0, 'msg' => $captchaErr));
    }

    if (Auth::login($username, $password)) {
        vs_auth_json(array(
            'code' => 1,
            'msg'  => '登录成功',
            'url'  => $base . '/admin/index',
        ));
    }

    AuthSecurity::recordLoginFailure($username);
    vs_auth_json(array('code' => 0, 'msg' => '用户名/邮箱或密码错误'));
}

if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    $expiredMsg = '登录已超时，请重新登录';
} else {
    $expiredMsg = '';
}

vs_auth_head('登录');
?>

<div class="page">
    <?php vs_auth_left_panel(true); ?>

    <div class="right">
        <div class="form-box">
            <div class="header header-desktop">
                <h1><?php echo vs_e($systemName); ?></h1>
            </div>

            <div id="formMessage" class="form-message" role="alert" hidden></div>

            <form id="loginForm" method="post" action="" novalidate>
                <?php vs_auth_csrf_field(); ?>
                <div class="field"><input id="username" name="username" type="text" placeholder="请输入用户名或邮箱" autocomplete="username" maxlength="64" required>
                </div>

                <div class="field"><div class="input-wrap">
                        <input id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required>
                        <?php echo vs_auth_toggle_password_html(); ?>
                    </div>
                </div>

                <?php vs_captcha_field(Captcha::SCENE_ADMIN_LOGIN); ?>

                <div class="row">
                    <span></span>
                    <a href="<?php echo vs_e($base); ?>/admin/forgot">忘记密码？</a>
                </div>

                <?php echo vs_auth_submit_btn('登 录', 'loginBtn', 'login-btn'); ?>
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
    var expiredMsg = <?php echo json_encode($expiredMsg, JSON_UNESCAPED_UNICODE); ?>;

    if (!form) return;

    // v13.25.0：强制清除历史「记住密码」明文，禁止再读写 localStorage 凭证
    try {
        localStorage.removeItem('vs_login_credentials');
        localStorage.removeItem('vs_user_login_credentials');
    } catch (err) {}

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

    function hideMessage() {
        if (messageEl) messageEl.hidden = true;
    }

    if (expiredMsg) {
        showMessage(expiredMsg, 'error');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();

        var username = form.username.value.trim();
        var password = form.password.value;

        if (!username) {
            showMessage('请输入用户名或邮箱', 'error');
            form.username.focus();
            return;
        }
        if (!password) {
            showMessage('请输入密码', 'error');
            form.password.focus();
            return;
        }

        if (loginBtn) loginBtn.disabled = true;

        var runLogin = function () {
        var post = (window.VsAuthCsrf && VsAuthCsrf.postForm)
            ? VsAuthCsrf.postForm(form, { action: 'login' })
            : fetch(form.action || window.location.href, {
                method: 'POST',
                body: (function () {
                    var b = new FormData(form);
                    b.append('action', 'login');
                    return b;
                })(),
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            }).then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (err) {
                        data = null;
                    }
                    return data;
                });
            });

        post
            .then(function (data) {
                if (!data || typeof data !== 'object') {
                    showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
                    return;
                }
                if (data.csrf && form.csrf_token) {
                    form.csrf_token.value = data.csrf;
                }
                if (data.code === 1) {
                    showMessage(data.msg || '登录成功', 'success');
                    if (data.url) {
                        setTimeout(function () { window.location.href = data.url; }, 800);
                    }
                } else {
                    showMessage(data.msg || '登录失败', 'error');
                    if (window.VsCaptcha && typeof window.VsCaptcha.reset === 'function') {
                        window.VsCaptcha.reset(form);
                    }
                }
            })
            .catch(function () {
                showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
            })
            .finally(function () {
                if (loginBtn) loginBtn.disabled = false;
            });
        };

        if (window.VsCaptcha && window.VsCaptcha.enabled && typeof window.VsCaptcha.ensure === 'function') {
            window.VsCaptcha.ensure(form).then(runLogin).catch(function (err) {
                showMessage((err && err.message) ? err.message : '请先完成行为验证', 'error');
                if (loginBtn) loginBtn.disabled = false;
            });
        } else {
            runLogin();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && document.activeElement && document.activeElement.tagName !== 'BUTTON') {
            form.requestSubmit();
        }
    });
})();
</script>

<?php vs_auth_foot(); ?>
