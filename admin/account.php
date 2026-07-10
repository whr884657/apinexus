<?php
/**
 * 文件：admin/account.php
 * 作用：misc-api 后台账号设置（用户名、邮箱、头像、密码）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

$error = '';
$success = '';
$avatarUrl = $vsAdmin && isset($vsAdmin['avatar_url']) ? trim((string) $vsAdmin['avatar_url']) : '';
$avatarPreview = UserAvatar::resolve($vsAdmin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $avatarUrl = trim(isset($_POST['avatar_url']) ? $_POST['avatar_url'] : '');
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $newPassword2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';
    $oldPassword = isset($_POST['old_password']) ? $_POST['old_password'] : '';

    if ($newPassword !== '' && $newPassword !== $newPassword2) {
        $error = '两次输入的新密码不一致';
    } else {
        $result = Auth::updateAccount(
            $email,
            $newPassword !== '' ? $newPassword : null,
            $newPassword !== '' ? $oldPassword : null,
            $avatarUrl,
            $username
        );

        if ($result === true) {
            $success = '账号信息已保存';
            $vsAdmin = Auth::user();
            $avatarUrl = $vsAdmin && isset($vsAdmin['avatar_url']) ? trim((string) $vsAdmin['avatar_url']) : '';
            $avatarPreview = UserAvatar::resolve($vsAdmin);
        } else {
            $error = $result;
        }
    }
}

vs_admin_layout_start('账号设置', 'account');
?>

<div class="vs-panel">
    <?php if ($error): ?>
        <div class="vs-alert vs-alert--error"><?php echo vs_e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="vs-alert vs-alert--success"><?php echo vs_e($success); ?></div>
    <?php endif; ?>

    <form method="post" action="" class="vs-form vs-account-form" id="accountForm">
        <div class="vs-account-form__layout">
            <aside class="vs-account-form__aside">
                <div class="vs-account-avatar">
                    <img src="<?php echo vs_e($avatarPreview); ?>" alt="" class="vs-account-avatar__img" id="avatarPreview"
                         data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($vsAdmin ? (int) $vsAdmin['id'] : 0)); ?>">
                    <label class="vs-label vs-account-avatar__label">头像链接</label>
                    <input type="url" name="avatar_url" id="avatarUrlInput" class="vs-input"
                           value="<?php echo vs_e($avatarUrl); ?>" placeholder="https://example.com/avatar.jpg">
                    <?php vs_render_notice('tip', '', '输入图片 URL，留空则使用默认头像', array('field' => true, 'compact' => true)); ?>
                </div>
            </aside>

            <div class="vs-account-form__main">
                <div class="vs-form-section">
                    <h3 class="vs-form-section__title">基本信息</h3>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountUsername">用户名</label>
                        <div class="vs-form-row__field">
                            <input type="text" name="username" id="accountUsername" class="vs-input" required minlength="3" maxlength="50"
                                   value="<?php echo vs_e($vsAdmin ? $vsAdmin['username'] : ''); ?>" placeholder="至少 3 个字符">
                        </div>
                    </div>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountEmail">邮箱</label>
                        <div class="vs-form-row__field">
                            <input type="email" name="email" id="accountEmail" class="vs-input" required
                                   value="<?php echo vs_e($vsAdmin ? $vsAdmin['email'] : ''); ?>" placeholder="admin@example.com">
                            <?php vs_render_notice('tip', '', '用于找回密码；QQ 邮箱可自动匹配 QQ 头像', array('field' => true, 'compact' => true)); ?>
                        </div>
                    </div>
                </div>

                <div class="vs-form-section">
                    <h3 class="vs-form-section__title">修改密码</h3>
                    <div class="vs-form-row vs-form-row--account vs-form-row--notice">
                        <div class="vs-form-row__label vs-form-row__label--spacer" aria-hidden="true"></div>
                        <div class="vs-form-row__field">
                            <?php vs_render_notice('info', '', '如不需要修改密码，以下三项留空即可', array('compact' => true)); ?>
                        </div>
                    </div>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountOldPassword">当前密码</label>
                        <div class="vs-form-row__field">
                            <input type="password" name="old_password" id="accountOldPassword" class="vs-input" placeholder="修改密码时必填" autocomplete="current-password">
                        </div>
                    </div>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountNewPassword">新密码</label>
                        <div class="vs-form-row__field">
                            <input type="password" name="new_password" id="accountNewPassword" class="vs-input" placeholder="至少 6 个字符" minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountNewPassword2">确认新密码</label>
                        <div class="vs-form-row__field">
                            <input type="password" name="new_password2" id="accountNewPassword2" class="vs-input" placeholder="再次输入新密码" minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="vs-form-actions">
                    <button type="submit" class="vs-btn vs-btn--primary">保存修改</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php vs_admin_layout_end(array('account.js')); ?>
