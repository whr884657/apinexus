<?php
/**
 * 文件：admin/login.php
 * 作用：misc-api 管理员登录页面
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

$siteName = SiteContext::siteName();

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
                <h1><?php echo vs_e($siteName); ?></h1>
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

                <div class="row">
                    <label class="remember">
                        <input type="checkbox" id="rememberCredentials" value="1">
                        记住账号密码
                    </label>
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
    var rememberEl = document.getElementById('rememberCredentials');
    var expiredMsg = <?php echo json_encode($expiredMsg, JSON_UNESCAPED_UNICODE); ?>;
    var storageKey = 'vs_login_credentials';

    if (!form) return;

    function loadSavedCredentials() {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return;
            var saved = JSON.parse(raw);
            if (!saved || typeof saved.username !== 'string') return;
            form.username.value = saved.username;
            if (typeof saved.password === 'string') {
                form.password.value = saved.password;
            }
            if (rememberEl) rememberEl.checked = true;
        } catch (err) {
            localStorage.removeItem(storageKey);
        }
    }

    function saveCredentials(username, password, remember) {
        try {
            if (remember) {
                localStorage.setItem(storageKey, JSON.stringify({
                    username: username,
                    password: password
                }));
            } else {
                localStorage.removeItem(storageKey);
            }
        } catch (err) {
            /* 隐私模式等场景可能无法写入 */
        }
    }

    loadSavedCredentials();

    if (rememberEl) {
        rememberEl.addEventListener('change', function () {
            if (!rememberEl.checked) {
                localStorage.removeItem(storageKey);
            }
        });
    }

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

        var body = new FormData(form);
        body.append('action', 'login');

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (err) {
                        data = null;
                    }
                    if (!data || typeof data !== 'object') {
                        throw new Error('bad_json');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (data.csrf && form.csrf_token) {
                    form.csrf_token.value = data.csrf;
                }
                if (data.code === 1) {
                    saveCredentials(
                        username,
                        password,
                        rememberEl && rememberEl.checked
                    );
                    showMessage(data.msg || '登录成功', 'success');
                    if (data.url) {
                        setTimeout(function () { window.location.href = data.url; }, 800);
                    }
                } else {
                    showMessage(data.msg || '登录失败', 'error');
                }
            })
            .catch(function () {
                showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
            })
            .finally(function () {
                if (loginBtn) loginBtn.disabled = false;
            });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && document.activeElement && document.activeElement.tagName !== 'BUTTON') {
            form.requestSubmit();
        }
    });
})();
</script>

<?php vs_auth_foot(); ?>
