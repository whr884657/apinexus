<?php
/**
 * 文件：user/forgot.php
 * 作用：用户忘记密码（邮箱验证码重置）
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';
require_once VS_ROOT . '/admin/includes/auth_layout.php';

InstallChecker::requireInstalled();
UserAuth::redirectIfLoggedIn();

$base = vs_base_url();
$siteName = SiteContext::siteName();
$mailEnabled = Config::isMailEnabled();
$codeTtl = 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    vs_auth_require_post();

    $action = (string) $_POST['action'];

    if ($action === 'send_code') {
        $mailPurpose = AuthSecurity::MAIL_PURPOSE_USER_FORGOT;

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

        $mailLimitMsg = AuthSecurity::checkMailCodeAllowed($email);
        if ($mailLimitMsg !== null) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => $mailLimitMsg));
        }

        AuthSecurity::recordMailCodeAttempt($email);

        try {
            $user = UserAuth::findByEmail($email);

            if ($user) {
                $code = (string) random_int(100000, 999999);
                $_SESSION['user_reset_id'] = (int) $user['id'];
                $_SESSION['user_reset_email'] = $email;
                $_SESSION['user_reset_code'] = $code;
                $_SESSION['user_reset_code_expires'] = time() + $codeTtl;

                $body = '<div style="font-family:sans-serif;line-height:1.8;">';
                $body .= '<p>您好，' . htmlspecialchars($user['username']) . '：</p>';
                $body .= '<p>您正在申请重置 ' . htmlspecialchars($siteName) . ' 用户密码，验证码为：</p>';
                $body .= '<p style="font-size:24px;font-weight:bold;margin:16px 0;">' . htmlspecialchars($code) . '</p>';
                $body .= '<p>验证码 ' . (int) ($codeTtl / 60) . ' 分钟内有效，请勿泄露给他人。</p>';
                $body .= '<p>如非本人操作，请忽略此邮件。</p></div>';

                Mailer::send($email, $siteName . ' 密码重置验证码', $body);
            }

            vs_auth_json_mail($mailPurpose, array(
                'code' => 1,
                'msg'  => '如果该邮箱已注册，验证码将发送到您的邮箱，请查收（含垃圾箱）',
            ));
        } catch (Exception $e) {
            vs_auth_json_mail($mailPurpose, array('code' => 0, 'msg' => '发送失败：' . $e->getMessage()));
        }
    }

    if ($action === 'reset_password') {
        if (!$mailEnabled) {
            vs_auth_json(array('code' => 0, 'msg' => '邮箱发信功能尚未配置'));
        }

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
        if ($code === '') {
            vs_auth_json(array('code' => 0, 'msg' => '请输入验证码'));
        }
        if (strlen($password) < 6) {
            vs_auth_json(array('code' => 0, 'msg' => '新密码至少 6 位'));
        }
        if ($password !== $confirm) {
            vs_auth_json(array('code' => 0, 'msg' => '两次输入的密码不一致'));
        }

        $savedEmail = isset($_SESSION['user_reset_email']) ? $_SESSION['user_reset_email'] : '';
        $savedCode = isset($_SESSION['user_reset_code']) ? $_SESSION['user_reset_code'] : '';
        $expires = isset($_SESSION['user_reset_code_expires']) ? (int) $_SESSION['user_reset_code_expires'] : 0;
        $userId = isset($_SESSION['user_reset_id']) ? (int) $_SESSION['user_reset_id'] : 0;

        if ($savedEmail === '' || $savedCode === '' || $expires < time() || $userId <= 0) {
            vs_auth_json(array('code' => 0, 'msg' => '验证码已过期，请重新获取'));
        }
        if ($email !== $savedEmail || $code !== $savedCode) {
            vs_auth_json(array('code' => 0, 'msg' => '邮箱或验证码错误'));
        }

        if (!UserAuth::resetPasswordById($userId, $password)) {
            vs_auth_json(array('code' => 0, 'msg' => '重置失败，请稍后重试'));
        }

        AuthSecurity::recordResetSubmit();

        unset(
            $_SESSION['user_reset_id'],
            $_SESSION['user_reset_email'],
            $_SESSION['user_reset_code'],
            $_SESSION['user_reset_code_expires']
        );

        vs_auth_json(array(
            'code' => 1,
            'msg'  => '密码重置成功，请使用新密码登录',
            'url'  => $base . '/user/login.php',
        ));
    }

    vs_auth_json(array('code' => 0, 'msg' => '未知操作'), 400);
}


ThemeManager::renderAuthPage('forgot', '忘记密码', array(
    'base' => $base,
    'mailEnabled' => $mailEnabled,
));
