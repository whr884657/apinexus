<?php
/**
 * 文件：user/account.php
 * 作用：用户账号设置（用户名、邮箱、头像、密码）
 */

require_once __DIR__ . '/init.php';

$error = '';
$success = '';
$avatarUrl = $vsUser && isset($vsUser['avatar']) ? trim((string) $vsUser['avatar']) : '';
$bio = $vsUser && isset($vsUser['bio']) ? trim((string) $vsUser['bio']) : '';
$blog = $vsUser && isset($vsUser['blog']) ? trim((string) $vsUser['blog']) : '';
$wallpaper = $vsUser && isset($vsUser['wallpaper']) ? trim((string) $vsUser['wallpaper']) : '';
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
    $bio = trim(isset($_POST['bio']) ? $_POST['bio'] : '');
    $blog = trim(isset($_POST['blog']) ? $_POST['blog'] : '');
    $wallpaper = trim(isset($_POST['wallpaper']) ? $_POST['wallpaper'] : '');
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
        $username,
        $bio,
        $blog,
        $wallpaper
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

vs_user_render_page(
    'account',
    '账号设置',
    'account',
    array(
        'error'          => $error,
        'success'        => $success,
        'avatarUrl'      => $avatarUrl,
        'bio'            => $bio,
        'blog'           => $blog,
        'wallpaper'      => $wallpaper,
        'avatarPreview'  => $avatarPreview,
        'roleLabel'      => $roleLabel,
        'oauthProviders' => $oauthProviders,
        'oauthBindings'  => $oauthBindings,
    ),
    '',
    array('account.js')
);
