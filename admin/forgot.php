<?php
/**
 * 文件：admin/forgot.php
 * 作用：ApiNexus 管理员忘记密码（邮箱验证码重置）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';
require_once __DIR__ . '/includes/auth_layout.php';

InstallChecker::requireInstalled();
Auth::redirectIfLoggedIn();

$base = vs_base_url();
$siteName = SiteContext::siteName();
$mailEnabled = Config::isMailEnabled();
$codeTtl = 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    vs_auth_require_post();

    $action = (string) $_POST['action'];

    if ($action === 'send_code') {
        $mailPurpose = AuthSecurity::MAIL_PURPOSE_ADMIN_FORGOT;

        if (!$mailEnabled) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '邮箱发信功能尚未配置，请联系管理员在后台「系统设置」中配置邮箱'));
        }

        $ticket = isset($_POST['mail_ticket']) ? (string) $_POST['mail_ticket'] : '';
        if (!AuthSecurity::validateAndConsumeMailTicket($mailPurpose, $ticket)) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '请求无效，请刷新页面后重试'));
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
            $_SESSION['reset_admin_id'] = (int) $admin['id'];
            $_SESSION['reset_email'] = $emailCanonical;
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_code_expires'] = time() + $codeTtl;

            $body = '<div style="font-family:sans-serif;line-height:1.8;">';
            $body .= '<p>您好，' . htmlspecialchars($admin['username']) . '：</p>';
            $body .= '<p>您正在申请重置 ' . htmlspecialchars($siteName) . ' 管理员密码，验证码为：</p>';
            $body .= '<p style="font-size:24px;font-weight:bold;margin:16px 0;">' . htmlspecialchars($code) . '</p>';
            $body .= '<p>验证码 ' . (int) ($codeTtl / 60) . ' 分钟内有效，请勿泄露给他人。</p>';
            $body .= '<p>如非本人操作，请忽略此邮件。</p></div>';

            Mailer::send($emailCanonical, $siteName . ' 密码重置验证码', $body);

            vs_auth_json_mail($mailPurpose, array(
                'code' => 1,
                'msg'  => '验证码已发送，请查收邮箱（含垃圾箱）',
            ));
        } catch (Exception $e) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '发送失败，请稍后重试'));
        }
    }

    if ($action === 'reset_password') {
        $resetLimitMsg = AuthSecurity::checkResetSubmitAllowed();
        if ($resetLimitMsg !== null) {
            vs_auth_json(array('code' => 0, 'msg' => $resetLimitMsg));
        }

        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $code = trim(isset($_POST['code']) ? $_POST['code'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if ($email === '') {
            vs_auth_json(array('code' => 0, 'msg' => '请输入邮箱'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            vs_auth_json(array('code' => 0, 'msg' => '请输入有效的邮箱地址'));
        }
        $email = vs_normalize_email($email);
        if ($code === '') {
            vs_auth_json(array('code' => 0, 'msg' => '请输入验证码'));
        }
        if (strlen($password) < 6) {
            vs_auth_json(array('code' => 0, 'msg' => '新密码至少 6 位'));
        }
        if ($password !== $confirm) {
            vs_auth_json(array('code' => 0, 'msg' => '两次输入的密码不一致'));
        }

        $savedEmail = isset($_SESSION['reset_email']) ? vs_normalize_email($_SESSION['reset_email']) : '';
        $savedCode = isset($_SESSION['reset_code']) ? (string) $_SESSION['reset_code'] : '';
        $expires = isset($_SESSION['reset_code_expires']) ? (int) $_SESSION['reset_code_expires'] : 0;
        $adminId = isset($_SESSION['reset_admin_id']) ? (int) $_SESSION['reset_admin_id'] : 0;

        if ($savedEmail === '' || $savedCode === '' || $expires < time() || $adminId <= 0) {
            vs_auth_json(array('code' => 0, 'msg' => '验证码已过期，请重新获取'));
        }
        if ($email !== $savedEmail || !hash_equals($savedCode, $code)) {
            vs_auth_json(array('code' => 0, 'msg' => '邮箱或验证码错误'));
        }

        if (!Auth::resetPasswordById($adminId, $password)) {
            vs_auth_json(array('code' => 0, 'msg' => '重置失败，请稍后重试'));
        }

        AuthSecurity::recordResetSubmit();

        unset(
            $_SESSION['reset_admin_id'],
            $_SESSION['reset_email'],
            $_SESSION['reset_code'],
            $_SESSION['reset_code_expires']
        );

        vs_auth_json(array(
            'code' => 1,
            'msg'  => '密码重置成功，请使用新密码登录',
            'url'  => $base . '/admin/login',
        ));
    }

    vs_auth_json(array('code' => 0, 'msg' => '未知操作'), 400);
}

vs_auth_head('忘记密码');
?>

<div class="page">
    <?php vs_auth_left_panel(false); ?>

    <div class="right">
        <div class="form-box">
            <div class="header header-desktop">
                <h1>重置密码</h1>
                <p class="header-sub">输入邮箱获取验证码，然后设置新密码</p>
            </div>

            <div id="formMessage" class="form-message" role="alert" hidden></div>

            <?php if (!$mailEnabled): ?>
                <div class="form-message form-message--error" style="display:block;">
                    邮箱发信功能尚未配置，无法使用忘记密码。请在后台「系统设置」中完成邮箱配置。
                </div>
            <?php endif; ?>

            <form id="forgotForm" method="post" action="" novalidate>
                <?php vs_auth_csrf_field(); ?>
                <?php vs_auth_mail_ticket_field(AuthSecurity::MAIL_PURPOSE_ADMIN_FORGOT); ?>
                <div class="field"><input id="email" name="email" type="email" placeholder="请输入注册邮箱" autocomplete="email" maxlength="64" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                </div>

                <div class="field"><div class="input-group">
                        <input id="code" name="code" type="text" placeholder="请输入验证码" autocomplete="one-time-code" maxlength="6" inputmode="numeric" pattern="[0-9]*" <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                        <button type="button" class="code-btn" id="sendCodeBtn" <?php echo $mailEnabled ? '' : 'disabled'; ?>>获取验证码</button>
                    </div>
                </div>

                <div class="field"><div class="input-wrap">
                        <input id="password" name="password" type="password" placeholder="请输入新密码（至少6位）" autocomplete="new-password" maxlength="64" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                        <?php echo vs_auth_toggle_password_html(); ?>
                    </div>
                </div>

                <div class="field"><input id="confirm_password" name="confirm_password" type="password" placeholder="请再次输入新密码" autocomplete="new-password" maxlength="64" required <?php echo $mailEnabled ? '' : 'disabled'; ?>>
                </div>

                <?php echo vs_auth_submit_btn('确认重置', 'submitBtn'); ?>

                <div class="divider">
                    想起密码了？<a href="<?php echo vs_e($base); ?>/admin/login">返回登录</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var form = document.getElementById('forgotForm');
    var messageEl = document.getElementById('formMessage');
    var sendCodeBtn = document.getElementById('sendCodeBtn');
    var submitBtn = document.getElementById('submitBtn');
    var mailEnabled = <?php echo $mailEnabled ? 'true' : 'false'; ?>;
    var countdown = 0;
    var countdownTimer = null;

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

    function hideMessage() {
        if (messageEl) messageEl.hidden = true;
    }

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
        if (el && data && data.mail_ticket) {
            el.value = data.mail_ticket;
        }
    }

    function parseResponse(res) {
        return res.text().then(function (text) {
            if (window.VS && typeof window.VS.parseJsonResponse === 'function') {
                var data = window.VS.parseJsonResponse(text);
                if (!data) {
                    throw new Error('invalid_json');
                }
                return data;
            }
            return JSON.parse(text);
        });
    }

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', function () {
            hideMessage();

            if (!mailEnabled) {
                showMessage('邮箱发信功能尚未配置', 'error');
                return;
            }

            var email = form.email.value.trim();

            if (!email) {
                showMessage('请先输入邮箱', 'error');
                form.email.focus();
                return;
            }

            sendCodeBtn.disabled = true;

            var body = new FormData();
            body.append('action', 'send_code');
            body.append('email', email);
            if (form.csrf_token) {
                body.append('csrf_token', form.csrf_token.value);
            }
            var mailTicketEl = document.getElementById('mailTicket');
            if (mailTicketEl) {
                body.append('mail_ticket', mailTicketEl.value);
            }

            fetch(window.location.href, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
                .then(parseResponse)
                .then(function (data) {
                    applyMailTicket(data);
                    if (data.code === 1) {
                        showMessage(data.msg || '验证码已发送', 'success');
                        startCountdown(120);
                    } else {
                        showMessage(data.msg || '发送失败', 'error');
                        startCountdown(parseWaitSeconds(data.msg));
                    }
                })
                .catch(function () {
                    showMessage('网络异常，请稍后重试', 'error');
                    sendCodeBtn.disabled = false;
                });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();

        if (!mailEnabled) {
            showMessage('邮箱发信功能尚未配置', 'error');
            return;
        }

        var email = form.email.value.trim();
        var code = form.code.value.trim();
        var password = form.password.value;
        var confirm = form.confirm_password.value;

        if (!email) {
            showMessage('请输入邮箱', 'error');
            form.email.focus();
            return;
        }
        if (!code) {
            showMessage('请输入验证码', 'error');
            form.code.focus();
            return;
        }
        if (password.length < 6) {
            showMessage('新密码至少 6 位', 'error');
            form.password.focus();
            return;
        }
        if (password !== confirm) {
            showMessage('两次输入的密码不一致', 'error');
            form.confirm_password.focus();
            return;
        }

        if (submitBtn) submitBtn.disabled = true;

        var body = new FormData(form);
        body.append('action', 'reset_password');

        fetch(window.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(parseResponse)
            .then(function (data) {
                if (data.code === 1) {
                    showMessage(data.msg || '重置成功', 'success');
                    if (data.url) {
                        setTimeout(function () { window.location.href = data.url; }, 1200);
                    }
                } else {
                    showMessage(data.msg || '重置失败', 'error');
                }
            })
            .catch(function () {
                showMessage('网络异常，请稍后重试', 'error');
            })
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    });
})();
</script>

<?php vs_auth_foot('window.CHARACTER_OPTIONS = { usernameInputId: \'email\', passwordInputId: \'password\' };'); ?>
