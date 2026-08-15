<?php
/**
 * 文件：admin/login.php
 * 作用：ApiNexus 管理员登录页面（账号密码 / 邮箱验证码）
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
$mailEnabled = Config::isMailEnabled();
$codeTtl = 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    vs_auth_require_post();
    $action = (string) $_POST['action'];

    if ($action === 'send_code') {
        $mailPurpose = AuthSecurity::MAIL_PURPOSE_ADMIN_LOGIN;

        if (!$mailEnabled) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '邮箱发信功能尚未配置，请联系管理员在后台「系统设置」中配置邮箱'));
        }

        $ticket = isset($_POST['mail_ticket']) ? (string) $_POST['mail_ticket'] : '';
        if (!AuthSecurity::validateAndConsumeMailTicket($mailPurpose, $ticket)) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请求无效，请刷新页面后重试'));
        }

        $captchaErr = Captcha::requireValid(Captcha::SCENE_ADMIN_LOGIN, $_POST);
        if ($captchaErr !== true) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => $captchaErr));
        }

        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        if ($email === '') {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请输入邮箱'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请输入有效的邮箱地址'));
        }
        $email = vs_normalize_email($email);

        $mailLimitMsg = AuthSecurity::checkMailCodeAllowed($email);
        if ($mailLimitMsg !== null) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => $mailLimitMsg));
        }

        AuthSecurity::recordMailCodeAttempt($email);

        try {
            $pdo = Database::connect();
            $table = Database::table('admin');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email` FROM `' . $table . '` WHERE LOWER(`email`) = ? AND `status` = 1 LIMIT 1'
            );
            $stmt->execute(array($email));
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                vs_auth_json_mail($mailPurpose, array(
                    'code' => 0,
                    'msg'  => '该邮箱未在本站注册，无法发送验证码',
                ));
            }

            $code = (string) random_int(100000, 999999);
            $emailCanonical = vs_normalize_email(isset($admin['email']) ? $admin['email'] : $email);
            $_SESSION['admin_login_id'] = (int) $admin['id'];
            $_SESSION['admin_login_email'] = $emailCanonical;
            $_SESSION['admin_login_code'] = $code;
            $_SESSION['admin_login_code_expires'] = time() + $codeTtl;
            AuthSecurity::resetOtpFailCount('admin_login');

            $body = Mailer::otpMailBody(
                $admin['username'],
                $systemName,
                '登录',
                $code,
                $codeTtl
            );
            Mailer::send($emailCanonical, $systemName . ' 登录验证码', $body);

            vs_auth_json_mail($mailPurpose, array(
                'code' => 1,
                'msg'  => '验证码已发送，请查收邮箱（含垃圾箱）',
            ));
        } catch (Exception $e) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '发送失败，请稍后重试'));
        }
    }

    if ($action === 'login_code') {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $code = trim(isset($_POST['code']) ? $_POST['code'] : '');

        if ($email === '' || $code === '') {
            vs_auth_json(array('code' => 0, 'msg' => '请输入邮箱和验证码'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            vs_auth_json(array('code' => 0, 'msg' => '请输入有效的邮箱地址'));
        }
        $email = vs_normalize_email($email);

        $loginBlocked = AuthSecurity::checkLoginAllowed($email);
        if ($loginBlocked !== null) {
            vs_auth_json(array('code' => 0, 'msg' => $loginBlocked));
        }

        $savedEmail = isset($_SESSION['admin_login_email']) ? vs_normalize_email($_SESSION['admin_login_email']) : '';
        $savedCode = isset($_SESSION['admin_login_code']) ? (string) $_SESSION['admin_login_code'] : '';
        $expires = isset($_SESSION['admin_login_code_expires']) ? (int) $_SESSION['admin_login_code_expires'] : 0;
        $adminId = isset($_SESSION['admin_login_id']) ? (int) $_SESSION['admin_login_id'] : 0;

        if ($savedEmail === '' || $savedCode === '' || $expires < time() || $adminId <= 0) {
            vs_auth_json(array('code' => 0, 'msg' => '验证码已过期，请重新获取'));
        }
        if ($email !== $savedEmail || !hash_equals($savedCode, $code)) {
            AuthSecurity::recordLoginFailure($email);
            vs_auth_json(array('code' => 0, 'msg' => AuthSecurity::recordOtpFailure('admin_login')));
        }

        if (!Auth::loginById($adminId)) {
            AuthSecurity::recordLoginFailure($email);
            vs_auth_json(array('code' => 0, 'msg' => '登录失败，请稍后重试'));
        }

        AuthSecurity::clearOtpSession('admin_login');
        vs_auth_json(array(
            'code' => 1,
            'msg'  => '登录成功',
            'url'  => $base . '/admin/index',
        ));
    }

    if ($action === 'login') {
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

    vs_auth_json(array('code' => 0, 'msg' => '未知操作'), 400);
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

            <form id="loginForm" method="post" action="" novalidate data-login-mode="password">
                <?php vs_auth_csrf_field(); ?>
                <?php vs_auth_mail_ticket_field(AuthSecurity::MAIL_PURPOSE_ADMIN_LOGIN); ?>

                <div id="modePassword" data-mode-panel="password">
                    <div class="field"><input id="username" name="username" type="text" placeholder="请输入用户名或邮箱" autocomplete="username" maxlength="64" required aria-label="用户名或邮箱">
                    </div>

                    <div class="field"><div class="input-wrap">
                            <input id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" maxlength="64" required aria-label="密码">
                            <?php echo vs_auth_toggle_password_html(); ?>
                        </div>
                    </div>
                </div>

                <div id="modeCode" data-mode-panel="code" hidden>
                    <div class="field"><input id="email" name="email" type="email" placeholder="请输入注册邮箱" autocomplete="email" maxlength="64" aria-label="注册邮箱" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                    </div>
                </div>

                <?php vs_captcha_field(Captcha::SCENE_ADMIN_LOGIN); ?>

                <div id="modeCodeCode" data-mode-panel="code" hidden>
                    <div class="field"><div class="input-group">
                            <input id="code" name="code" type="text" placeholder="请输入邮箱验证码" autocomplete="one-time-code" maxlength="6" inputmode="numeric" pattern="[0-9]*" aria-label="邮箱验证码" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                            <button type="button" class="code-btn" id="sendCodeBtn" <?php echo $mailEnabled ? '' : 'disabled'; ?>>获取验证码</button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <a href="#" id="toggleLoginMode" role="button">验证码登录</a>
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
    var sendCodeBtn = document.getElementById('sendCodeBtn');
    var toggleEl = document.getElementById('toggleLoginMode');
    var expiredMsg = <?php echo json_encode($expiredMsg, JSON_UNESCAPED_UNICODE); ?>;
    var mailEnabled = <?php echo $mailEnabled ? 'true' : 'false'; ?>;
    var mode = 'password';
    var countdown = 0;
    var countdownTimer = null;

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

    function applyMailTicket(data) {
        var el = document.getElementById('mailTicket');
        if (el && data && data.mail_ticket) {
            el.value = data.mail_ticket;
        }
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
        if (!sendCodeBtn) return;
        if (countdown > 0) return;
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
        if (toggleEl) {
            toggleEl.textContent = mode === 'code' ? '密码登录' : '验证码登录';
        }
        hideMessage();
        if (window.VsCaptcha && typeof window.VsCaptcha.reset === 'function') {
            window.VsCaptcha.reset(form);
        }
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

    if (expiredMsg) {
        showMessage(expiredMsg, 'error');
    }

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', function () {
            hideMessage();
            if (!mailEnabled) {
                showMessage('邮箱发信功能尚未配置', 'error');
                return;
            }
            if (mode !== 'code') return;
            var email = form.email ? form.email.value.trim() : '';
            if (!email) {
                showMessage('请先输入邮箱', 'error');
                if (form.email) form.email.focus();
                return;
            }
            sendCodeBtn.disabled = true;

            var body = new FormData();
            body.append('action', 'send_code');
            body.append('email', email);
            if (form.csrf_token) body.append('csrf_token', form.csrf_token.value);
            var mailTicketEl = document.getElementById('mailTicket');
            if (mailTicketEl) body.append('mail_ticket', mailTicketEl.value);

            var doSend = function () {
                if (window.VsCaptcha && window.VsCaptcha.appendToFormData) {
                    window.VsCaptcha.appendToFormData(body);
                }
                return fetch(window.location.href, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (res) {
                        return res.text().then(function (text) {
                            try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                        });
                    })
                    .then(function (data) {
                        if (!data || typeof data !== 'object') {
                            showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
                            resetSendCodeBtn();
                            return;
                        }
                        applyMailTicket(data);
                        if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
                        if (data.code === 1) {
                            showMessage(data.msg || '验证码已发送', 'success');
                            startCountdown(120);
                            if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
                        } else {
                            showMessage(data.msg || '发送失败', 'error');
                            var waitSec = parseWaitSeconds(data.msg);
                            if (waitSec > 0) startCountdown(waitSec);
                            else resetSendCodeBtn();
                            if (window.VsCaptcha && window.VsCaptcha.reset) window.VsCaptcha.reset(form);
                        }
                    })
                    .catch(function () {
                        showMessage('网络异常，请稍后重试', 'error');
                        resetSendCodeBtn();
                    });
            };

            if (window.VsCaptcha && window.VsCaptcha.enabled && window.VsCaptcha.ensure) {
                window.VsCaptcha.ensure(form).then(doSend).catch(function (err) {
                    showMessage((err && err.message) ? err.message : '请先完成行为验证', 'error');
                    resetSendCodeBtn();
                });
            } else {
                doSend();
            }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();

        if (mode === 'code') {
            if (!mailEnabled) {
                showMessage('邮箱发信功能尚未配置', 'error');
                return;
            }
            var email = form.email ? form.email.value.trim() : '';
            var code = form.code ? form.code.value.trim() : '';
            if (!email) { showMessage('请输入邮箱', 'error'); if (form.email) form.email.focus(); return; }
            if (!code) { showMessage('请输入验证码', 'error'); if (form.code) form.code.focus(); return; }
            if (loginBtn) loginBtn.disabled = true;

            var postCode = (window.VsAuthCsrf && VsAuthCsrf.postForm)
                ? VsAuthCsrf.postForm(form, { action: 'login_code' })
                : fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: (function () {
                        var b = new FormData(form);
                        b.append('action', 'login_code');
                        return b;
                    })(),
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                }).then(function (res) {
                    return res.text().then(function (text) {
                        try { return text ? JSON.parse(text) : null; } catch (err) { return null; }
                    });
                });

            postCode
                .then(function (data) {
                    if (!data || typeof data !== 'object') {
                        showMessage('网络异常或会话已过期，请刷新页面后重试', 'error');
                        return;
                    }
                    if (data.csrf && form.csrf_token) form.csrf_token.value = data.csrf;
                    applyMailTicket(data);
                    if (data.code === 1) {
                        showMessage(data.msg || '登录成功', 'success');
                        if (data.url) setTimeout(function () { window.location.href = data.url; }, 800);
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
            return;
        }

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
