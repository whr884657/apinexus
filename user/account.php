<?php
/**
 * 文件：user/account.php
 * 作用：用户账号设置（顶卡 + Tab：第三方 / 基本信息 / 密码 / 开发者申请）
 */

require_once __DIR__ . '/init.php';

$error = '';
$success = '';
$avatarUrl = $vsUser && isset($vsUser['avatar']) ? trim((string) $vsUser['avatar']) : '';
$bio = $vsUser && isset($vsUser['bio']) ? trim((string) $vsUser['bio']) : '';
$blog = $vsUser && isset($vsUser['blog']) ? trim((string) $vsUser['blog']) : '';
$wallpaper = $vsUser && isset($vsUser['wallpaper']) ? trim((string) $vsUser['wallpaper']) : '';
$avatarPreview = is_array($vsUserProfile) ? $vsUserProfile['avatar'] : UserAvatar::resolve($vsUser);
$roleRaw = $vsUser && isset($vsUser['role']) ? $vsUser['role'] : UserRole::ROLE_USER;
$roleLabel = is_array($vsUserProfile) ? $vsUserProfile['role_label'] : UserRole::label($roleRaw);
$isPlainUser = UserRole::normalize($roleRaw) === UserRole::ROLE_USER;
$oauthProviders = OAuthService::enabledProviders();
$oauthBindings = OAuthService::bindingsForUser((int) $vsUser['id']);
$oauthButtons = OAuthService::accountButtons((int) $vsUser['id']);
$devApply = ($isPlainUser && class_exists('ApiFeedbackManager'))
    ? ApiFeedbackManager::latestDevApplyForUser((int) $vsUser['id'])
    : null;
$devApplyPending = ($isPlainUser && class_exists('ApiFeedbackManager'))
    ? ApiFeedbackManager::pendingDevApplyForUser((int) $vsUser['id'])
    : null;

$flash = vs_flash_take();
if (is_array($flash)) {
    if ($flash['type'] === 'success') {
        $success = $flash['msg'];
    } else {
        $error = $flash['msg'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'oauth_unbind') {
        $provider = isset($_POST['provider']) ? (string) $_POST['provider'] : '';
        $itemId = isset($_POST['item_id']) ? (string) $_POST['item_id'] : '';
        $result = OAuthService::unbindUser((int) $vsUser['id'], $provider, $itemId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('第三方账号已解绑', array(
            'provider' => $provider,
            'item_id'  => $itemId,
        ));
    }

    if ($action === 'save_profile') {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $avatarUrl = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');
        $bio = trim(isset($_POST['bio']) ? $_POST['bio'] : '');
        $blog = trim(isset($_POST['blog']) ? $_POST['blog'] : '');
        $wallpaper = trim(isset($_POST['wallpaper']) ? $_POST['wallpaper'] : '');
        $result = UserAuth::updateAccount(
            $email,
            null,
            null,
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
        AjaxResponse::success('基本信息已保存', array(
            'avatar'         => $vsUser && isset($vsUser['avatar']) ? trim((string) $vsUser['avatar']) : '',
            'avatar_preview' => $avatarPreview,
            'username'       => $vsUser && isset($vsUser['username']) ? (string) $vsUser['username'] : '',
            'email'          => $vsUser && isset($vsUser['email']) ? (string) $vsUser['email'] : '',
        ));
    }

    if ($action === 'save_password') {
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $newPassword2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';
        $oldPassword = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        if ($newPassword === '') {
            AjaxResponse::error('请填写新密码');
        }
        if ($newPassword !== $newPassword2) {
            AjaxResponse::error('两次输入的新密码不一致');
        }
        // updateAccount 会写入 avatar/bio 等：须回传当前值，避免被空串覆盖
        $result = UserAuth::updateAccount(
            isset($vsUser['email']) ? (string) $vsUser['email'] : '',
            $newPassword,
            $oldPassword,
            isset($vsUser['avatar']) ? (string) $vsUser['avatar'] : '',
            isset($vsUser['username']) ? (string) $vsUser['username'] : '',
            isset($vsUser['bio']) ? (string) $vsUser['bio'] : '',
            isset($vsUser['blog']) ? (string) $vsUser['blog'] : '',
            isset($vsUser['wallpaper']) ? (string) $vsUser['wallpaper'] : ''
        );
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('密码已修改');
    }

    if ($action === 'apply_developer') {
        if (!$isPlainUser) {
            AjaxResponse::error('仅普通用户可申请开发者');
        }
        $reason = isset($_POST['reason']) ? (string) $_POST['reason'] : '';
        $result = ApiFeedbackManager::applyDeveloper((int) $vsUser['id'], $reason);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('申请已提交，请等待管理员审核', array(
            'apply' => $result,
        ));
    }

    // 未知 action 直接拒绝，避免落到旧「一并保存」误改资料
    if ($action !== '') {
        AjaxResponse::error('无效操作', 400);
    }

    // 兼容旧整页表单：无 action 时按原「一并保存」处理
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
        'error'            => $error,
        'success'          => $success,
        'avatarUrl'        => $avatarUrl,
        'bio'              => $bio,
        'blog'             => $blog,
        'wallpaper'        => $wallpaper,
        'avatarPreview'    => $avatarPreview,
        'roleLabel'        => $roleLabel,
        'isPlainUser'      => $isPlainUser,
        'oauthProviders'   => $oauthProviders,
        'oauthBindings'    => $oauthBindings,
        'oauthButtons'     => $oauthButtons,
        'devApply'         => $devApply,
        'devApplyPending'  => $devApplyPending,
    ),
    '',
    array('account.js')
);
