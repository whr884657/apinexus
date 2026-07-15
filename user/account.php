<?php
/**
 * 文件：user/account.php
 * 作用：用户账号设置（用户名、邮箱、头像、密码）
 */

require_once __DIR__ . '/init.php';

$error = '';
$success = '';
$avatarUrl = $vsUser && isset($vsUser['avatar']) ? trim((string) $vsUser['avatar']) : '';
$avatarPreview = is_array($vsUserProfile) ? $vsUserProfile['avatar'] : UserAvatar::resolve($vsUser);
$roleLabel = is_array($vsUserProfile) ? $vsUserProfile['role_label'] : UserRole::label(UserRole::ROLE_USER);
$oauthProviders = OAuthService::enabledProviders();
$oauthBindings = OAuthService::bindingsForUser((int) $vsUser['id']);

if (isset($_GET['oauth_error']) && trim((string) $_GET['oauth_error']) !== '') {
    $error = trim((string) $_GET['oauth_error']);
}
if (isset($_GET['oauth_success']) && trim((string) $_GET['oauth_success']) !== '') {
    $success = trim((string) $_GET['oauth_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'oauth_unbind') {
        $provider = isset($_POST['provider']) ? (string) $_POST['provider'] : '';
        $result = OAuthService::unbindUser((int) $vsUser['id'], $provider);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('第三方账号已解绑', array('provider' => $provider));
    }

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $avatarUrl = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $newPassword2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';
    $oldPassword = isset($_POST['old_password']) ? $_POST['old_password'] : '';

    if ($newPassword !== '' && $newPassword !== $newPassword2) {
        AjaxResponse::error('两次输入的新密码不一致');
    }

    $result = UserAuth::updateAccount(
        $email,
        $newPassword !== '' ? $newPassword : null,
        $newPassword !== '' ? $oldPassword : null,
        $avatarUrl,
        $username
    );

    if ($result !== true) {
        AjaxResponse::error($result);
    }

    $vsUser = UserAuth::user();
    $vsUserProfile = FrontendUser::current();
    $avatarPreview = is_array($vsUserProfile) ? $vsUserProfile['avatar'] : UserAvatar::resolve($vsUser);
    AjaxResponse::success('账号信息已保存', array(
        'avatar' => $vsUser && isset($vsUser['avatar']) ? trim((string) $vsUser['avatar']) : '',
        'avatar_preview' => $avatarPreview,
    ));
}

vs_user_layout_start('账号设置', 'account');
?>

<div class="vs-panel">
    <?php if ($error): ?>
        <div class="vs-alert vs-alert--error"><?php echo vs_e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="vs-alert vs-alert--success"><?php echo vs_e($success); ?></div>
    <?php endif; ?>

    <div class="vs-account-shell">
        <form method="post" action="" class="vs-form vs-account-form" id="accountForm" data-ajax="1" novalidate>
            <div class="vs-account-form__layout">
                <aside class="vs-account-form__aside">
                    <div class="vs-account-avatar">
                        <img src="<?php echo vs_e($avatarPreview); ?>" alt="" class="vs-account-avatar__img" id="avatarPreview"
                             data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($vsUser ? (int) $vsUser['id'] : 0)); ?>">
                        <label class="vs-label vs-account-avatar__label">头像链接</label>
                        <input type="text" name="avatar" id="avatarUrlInput" class="vs-input"
                               value="<?php echo vs_e($avatarUrl); ?>" placeholder="https://example.com/avatar.jpg" maxlength="500" inputmode="url" autocomplete="off">
                        <?php vs_render_notice('tip', '', '输入图片 URL，留空则使用 QQ 邮箱头像或默认头像', array('field' => true, 'compact' => true)); ?>
                    </div>
                </aside>

                <div class="vs-account-form__main">
                <div class="vs-form-section">
                    <h3 class="vs-form-section__title">基本信息</h3>
                    <?php
                    $roleTip = $roleLabel === '开发者'
                        ? '您当前为开发者，可在用户中心使用「API 管理」发布接口。'
                        : '您当前为普通用户，可生成密钥调用平台接口；如需发布接口请联系管理员调整身份。';
                    vs_render_notice('info', '账号身份：' . $roleLabel, $roleTip, array('compact' => true));
                    ?>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountUsername">用户名</label>
                        <div class="vs-form-row__field">
                            <input type="text" name="username" id="accountUsername" class="vs-input" required minlength="3" maxlength="50"
                                   value="<?php echo vs_e($vsUser ? $vsUser['username'] : ''); ?>" placeholder="至少 3 个字符">
                        </div>
                    </div>
                    <div class="vs-form-row vs-form-row--account">
                        <label class="vs-label" for="accountEmail">邮箱</label>
                        <div class="vs-form-row__field">
                            <input type="email" name="email" id="accountEmail" class="vs-input" required
                                   value="<?php echo vs_e($vsUser ? $vsUser['email'] : ''); ?>" placeholder="user@example.com">
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

    <?php if ($oauthProviders['qq'] || $oauthProviders['gitee']): ?>
    <div class="vs-form-section vs-oauth-bind-section">
        <h3 class="vs-form-section__title">第三方账号</h3>
        <?php vs_render_notice('info', '', '绑定后可在登录页使用第三方快捷登录；解绑后需重新验证账号密码才能再次绑定', array('compact' => true)); ?>
        <div class="vs-oauth-bind-list">
            <?php if ($oauthProviders['qq']): ?>
            <div class="vs-oauth-bind-item">
                <div class="vs-oauth-bind-item__info">
                    <img src="<?php echo vs_e($vsBase); ?>/assets/img/QQ.svg" alt="" class="vs-oauth-bind-item__icon" width="24" height="24">
                    <div>
                        <div class="vs-oauth-bind-item__name">QQ</div>
                        <div class="vs-oauth-bind-item__status"><?php echo $oauthBindings['qq'] ? '已绑定' : '未绑定'; ?></div>
                    </div>
                </div>
                <div class="vs-oauth-bind-item__action">
                    <?php if ($oauthBindings['qq']): ?>
                        <form method="post" action="" class="vs-oauth-unbind-form" data-ajax="1">
                            <input type="hidden" name="action" value="oauth_unbind">
                            <input type="hidden" name="provider" value="qq">
                            <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">
                            <button type="submit" class="vs-btn vs-btn--text vs-btn--oauth-action">解绑</button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo vs_e($vsBase); ?>/user/oauth/start.php?provider=qq&amp;intent=bind" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($oauthProviders['gitee']): ?>
            <div class="vs-oauth-bind-item">
                <div class="vs-oauth-bind-item__info">
                    <img src="<?php echo vs_e($vsBase); ?>/assets/img/gitee.svg" alt="" class="vs-oauth-bind-item__icon" width="24" height="24">
                    <div>
                        <div class="vs-oauth-bind-item__name">Gitee</div>
                        <div class="vs-oauth-bind-item__status"><?php echo $oauthBindings['gitee'] ? '已绑定' : '未绑定'; ?></div>
                    </div>
                </div>
                <div class="vs-oauth-bind-item__action">
                    <?php if ($oauthBindings['gitee']): ?>
                        <form method="post" action="" class="vs-oauth-unbind-form" data-ajax="1">
                            <input type="hidden" name="action" value="oauth_unbind">
                            <input type="hidden" name="provider" value="gitee">
                            <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">
                            <button type="submit" class="vs-btn vs-btn--text vs-btn--oauth-action">解绑</button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo vs_e($vsBase); ?>/user/oauth/start.php?provider=gitee&amp;intent=bind" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<?php vs_user_layout_end(array('account.js')); ?>
