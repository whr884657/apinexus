<?php
/**
 * 默认主题 · 用户注册
 * 变量由 ThemeManager::renderAuthPage extract 注入；此处全部兜底
 *
 * @var string $vsBase
 * @var string $base
 * @var bool   $mailEnabled
 * @var string $mailDisabledMsg
 * @var bool   $registerOpen
 * @var bool   $emailVerify
 * @var bool   $formEnabled
 * @var string $registerClosedMsg
 * @var string $registerClosedSub
 * @var string $registerClosedDetail
 */
if (!defined('VS_THEME_RENDER')) { exit; }
$vsBase = isset($vsBase) ? (string) $vsBase : rtrim(vs_base_url(), '/');
$base = (isset($base) && (string) $base !== '') ? (string) $base : $vsBase;
$mailEnabled = !empty($mailEnabled);
$mailDisabledMsg = isset($mailDisabledMsg) && (string) $mailDisabledMsg !== ''
    ? (string) $mailDisabledMsg
    : '管理员尚未配置邮箱发信，请联系管理员在后台「系统设置」中配置邮箱后方可注册。';
$registerOpen = !isset($registerOpen) || !empty($registerOpen);
$emailVerify = !isset($emailVerify) || !empty($emailVerify);
$formEnabled = isset($formEnabled) ? !empty($formEnabled) : ($registerOpen && (!$emailVerify || $mailEnabled));
$registerClosedMsg = isset($registerClosedMsg) && (string) $registerClosedMsg !== ''
    ? (string) $registerClosedMsg
    : '已停止注册，如有问题请联系管理员';
$registerClosedSub = isset($registerClosedSub) && (string) $registerClosedSub !== ''
    ? (string) $registerClosedSub
    : '如有问题请联系管理员';
$registerClosedDetail = isset($registerClosedDetail) && (string) $registerClosedDetail !== ''
    ? (string) $registerClosedDetail
    : '本站已暂停开放新用户注册。如有账号问题或合作需求，请联系站点管理员。请勿通过篡改地址或重复提交尝试绕过。';
$subTitle = $emailVerify ? '使用邮箱验证注册，完成验证后即可使用' : '填写账号信息即可注册';
$dis = $formEnabled ? '' : 'disabled';

ThemeManager::renderThemeAuthHead('用户注册');
?>

<div class="page">
    <?php vs_auth_left_panel(false); ?>

    <div class="right">
        <div class="form-box">
            <div class="header header-desktop">
                <h1><?php echo $registerOpen ? '注册账号' : '已停止注册'; ?></h1>
                <p class="header-sub"><?php echo vs_e($registerOpen ? $subTitle : $registerClosedSub); ?></p>
            </div>

            <div id="formMessage" class="form-message" role="alert" hidden></div>

            <?php if (!$registerOpen): ?>
                <div class="form-message form-message--error" style="display:block;" role="status">
                    <?php echo vs_e($registerClosedDetail); ?>
                </div>
                <div class="divider">
                    <a href="<?php echo vs_e($base); ?>/user/login">返回登录</a>
                </div>
            <?php else: ?>
                <?php if ($emailVerify && !$mailEnabled): ?>
                    <?php vs_render_notice('warning', '暂无法注册', $mailDisabledMsg); ?>
                <?php endif; ?>

                <form id="registerForm" method="post" action="" novalidate>
                    <?php vs_auth_csrf_field(); ?>
                    <?php if ($emailVerify): ?>
                        <?php vs_auth_mail_ticket_field(AuthSecurity::MAIL_PURPOSE_USER_REGISTER); ?>
                    <?php endif; ?>
                    <div class="field field--role-segment">
                        <div class="vs-role-segment" id="roleSegment">
                            <div class="vs-role-segment__track" id="roleSegmentTrack" data-role="user" role="radiogroup" aria-label="账号类型">
                                <span class="vs-role-segment__thumb" aria-hidden="true"></span>
                                <button type="button" class="vs-role-segment__btn is-active" data-role="user" aria-pressed="true" <?php echo $dis; ?>>普通用户</button>
                                <button type="button" class="vs-role-segment__btn" data-role="developer" aria-pressed="false" <?php echo $dis; ?>>开发者</button>
                            </div>
                            <input type="hidden" name="role" id="roleInput" value="user" <?php echo $dis; ?>>
                        </div>
                    </div>

                    <div class="field">
                        <input id="username" name="username" type="text" placeholder="请输入用户名（3～50 个字符）" autocomplete="username" maxlength="50" required aria-label="用户名" <?php echo $dis; ?>>
                    </div>

                    <div class="field">
                        <input id="email" name="email" type="email" placeholder="请输入邮箱" autocomplete="email" maxlength="64" required aria-label="邮箱" <?php echo $dis; ?>>
                    </div>

                    <?php vs_captcha_field(Captcha::SCENE_USER_REGISTER, 'local'); ?>

                    <?php if ($emailVerify): ?>
                    <div class="field">
                        <div class="input-group">
                            <input id="code" name="code" type="text" placeholder="请输入邮箱验证码" autocomplete="one-time-code" maxlength="6" inputmode="numeric" pattern="[0-9]*" aria-label="邮箱验证码" <?php echo $dis; ?>>
                            <button type="button" class="code-btn" id="sendCodeBtn" <?php echo $dis; ?>>获取验证码</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php vs_captcha_field(Captcha::SCENE_USER_REGISTER, 'gt'); ?>

                    <div class="field">
                        <div class="input-wrap">
                            <input id="password" name="password" type="password" placeholder="请设置密码（至少6位）" autocomplete="new-password" maxlength="64" required aria-label="密码" <?php echo $dis; ?>>
                            <?php echo vs_auth_toggle_password_html(); ?>
                        </div>
                    </div>

                    <div class="field">
                        <input id="confirm_password" name="confirm_password" type="password" placeholder="请再次输入密码" autocomplete="new-password" maxlength="64" required aria-label="确认密码" <?php echo $dis; ?>>
                    </div>

                    <?php
                    $regBtn = vs_auth_submit_btn('立即注册', 'submitBtn');
                    if (!$formEnabled) {
                        $regBtn = str_replace('<button type="submit"', '<button type="submit" disabled', $regBtn);
                    }
                    echo $regBtn;
                    ?>

                    <div class="divider">
                        已有账号？<a href="<?php echo vs_e($base); ?>/user/login">返回登录</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($registerOpen): ?>
<script>
(function () {
    'use strict';

    var form = document.getElementById('registerForm');
    var messageEl = document.getElementById('formMessage');
    var sendCodeBtn = document.getElementById('sendCodeBtn');
    var submitBtn = document.getElementById('submitBtn');
    var formEnabled = <?php echo $formEnabled ? 'true' : 'false'; ?>;
    var emailVerify = <?php echo $emailVerify ? 'true' : 'false'; ?>;
    var countdown = 0;
    var countdownTimer = null;

    if (!form) return;

    var roleTrack = document.getElementById('roleSegmentTrack');
    var roleInput = document.getElementById('roleInput');
    var ROLE_HINTS = {
        user: '普通用户：可在用户中心生成密钥，调用平台全部公开接口（含站长与其他用户发布的接口）。',
        developer: '开发者：拥有普通用户全部能力，并可在「API 管理」中发布自己的接口，为平台提供技能与支持。'
    };

    function normalizeRole(role) {
        return role === 'developer' ? 'developer' : 'user';
    }

    function setRole(role, showHint) {
        if (!roleTrack || !roleInput) return;
        role = normalizeRole(role);
        roleTrack.dataset.role = role;
        roleInput.value = role;
        roleTrack.querySelectorAll('.vs-role-segment__btn').forEach(function (btn) {
            var active = btn.dataset.role === role;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (showHint && ROLE_HINTS[role]) {
            showMessage(ROLE_HINTS[role], 'success');
        }
    }

    if (roleTrack) {
        roleTrack.querySelectorAll('.vs-role-segment__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var role = normalizeRole(btn.dataset.role);
                if (roleInput && roleInput.value !== role) {
                    setRole(role, true);
                }
            });
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
        return match ? parseInt(match[1], 10) : 0;
    }

    function resetSendCodeBtn() {
        if (!sendCodeBtn) return;
        sendCodeBtn.disabled = false;
        sendCodeBtn.textContent = '获取验证码';
    }

    function applyMailTicket(data) {
        var el = document.getElementById('mailTicket');
        if (el && data && data.mail_ticket) {
            el.value = data.mail_ticket;
        }
    }

    if (sendCodeBtn && emailVerify) {
        sendCodeBtn.addEventListener('click', function () {
            hideMessage();
            if (!formEnabled) return;

            var username = form.username.value.trim();
            var email = form.email.value.trim();

            if (!username) {
                showMessage('请先输入用户名', 'error');
                form.username.focus();
                return;
            }
            if (!email) {
                showMessage('请先输入邮箱', 'error');
                form.email.focus();
                return;
            }

            sendCodeBtn.disabled = true;

            var body = new FormData();
            body.append('action', 'send_code');
            body.append('username', username);
            body.append('email', email);
            if (form.csrf_token) {
                body.append('csrf_token', form.csrf_token.value);
            }
            var mailTicketEl = document.getElementById('mailTicket');
            if (mailTicketEl) {
                body.append('mail_ticket', mailTicketEl.value);
            }

            var doSendCaptcha = function () {
                if (window.VsCaptcha && window.VsCaptcha.appendToFormData) {
                    window.VsCaptcha.appendToFormData(body);
                }
                return fetch(window.location.href, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin'
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    applyMailTicket(data);
                    if (data.code === 1) {
                        showMessage(data.msg || '验证码已发送', 'success');
                        startCountdown(120);
                    } else {
                        showMessage(data.msg || '发送失败', 'error');
                        var waitSec = parseWaitSeconds(data.msg);
                        if (waitSec > 0) {
                            startCountdown(waitSec);
                        } else {
                            resetSendCodeBtn();
                        }
                    }
                })
                .catch(function () {
                    showMessage('网络异常，请稍后重试', 'error');
                    sendCodeBtn.disabled = false;
                });
            };
            if (window.VsCaptcha && window.VsCaptcha.enabled && window.VsCaptcha.ensure) {
                window.VsCaptcha.ensure(form).then(doSendCaptcha).catch(function (err) {
                    showMessage((err && err.message) || '请先完成行为验证', 'error');
                    resetSendCodeBtn();
                });
            } else {
                doSendCaptcha();
            }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessage();

        if (!formEnabled) return;

        var username = form.username.value.trim();
        var email = form.email.value.trim();
        var codeEl = form.code;
        var code = codeEl ? String(codeEl.value || '').trim() : '';
        var password = form.password.value;
        var confirm = form.confirm_password.value;

        if (!username) {
            showMessage('请输入用户名', 'error');
            return;
        }
        if (!email) {
            showMessage('请输入邮箱', 'error');
            return;
        }
        if (emailVerify && !code) {
            showMessage('请输入验证码', 'error');
            return;
        }
        if (password.length < 6) {
            showMessage('密码至少 6 位', 'error');
            return;
        }
        if (password !== confirm) {
            showMessage('两次输入的密码不一致', 'error');
            return;
        }

        if (submitBtn) submitBtn.disabled = true;

        var runRegister = function () {
            var body = new FormData(form);
            body.append('action', 'register');
            if (window.VsCaptcha && window.VsCaptcha.appendToFormData && !emailVerify) {
                window.VsCaptcha.appendToFormData(body);
            }

            fetch(window.location.href, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.code === 1) {
                        showMessage(data.msg || '注册成功', 'success');
                        if (data.url) {
                            setTimeout(function () { window.location.href = data.url; }, 1200);
                        }
                    } else {
                        showMessage(data.msg || '注册失败', 'error');
                        if (!emailVerify && window.VsCaptcha && window.VsCaptcha.reset) {
                            window.VsCaptcha.reset(form);
                        }
                    }
                })
                .catch(function () {
                    showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
        };

        if (!emailVerify && window.VsCaptcha && window.VsCaptcha.enabled && window.VsCaptcha.ensure) {
            window.VsCaptcha.ensure(form).then(runRegister).catch(function (err) {
                showMessage((err && err.message) || '请先完成行为验证', 'error');
                if (submitBtn) submitBtn.disabled = false;
            });
        } else {
            runRegister();
        }
    });
})();
</script>
<?php endif; ?>

<?php ThemeManager::renderThemeAuthFoot(); ?>
