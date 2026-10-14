<?php
/**
 * 文件：admin/account.php
 * 作用：管理后台账号设置（顶卡 + Tab：发布身份 / 基本信息 / 修改密码）
 */

require_once __DIR__ . '/init.php';

$error = '';
$success = '';
$avatarUrl = $vsAdmin && isset($vsAdmin['avatar']) ? trim((string) $vsAdmin['avatar']) : '';
$avatarPreview = UserAvatar::resolve($vsAdmin);
$boundUser = AdminUserBinding::getBoundUser((int) Auth::id());
$adminUsername = $vsAdmin && isset($vsAdmin['username']) ? (string) $vsAdmin['username'] : '';
$adminEmail = $vsAdmin && isset($vsAdmin['email']) ? (string) $vsAdmin['email'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'bind_user') {
        $account = isset($_POST['user_account']) ? (string) $_POST['user_account'] : '';
        $result = AdminUserBinding::bind((int) Auth::id(), $account);
        if (is_string($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('用户账号已绑定', array('bound_user' => $result));
    }

    if ($action === 'unbind_user') {
        $result = AdminUserBinding::unbind((int) Auth::id());
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('已解除用户账号绑定');
    }

    if ($action === 'save_profile') {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $avatarUrl = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');
        $result = Auth::updateAccount($email, null, null, $avatarUrl, $username);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $vsAdmin = Auth::user();
        $avatarPreview = UserAvatar::resolve($vsAdmin);
        AjaxResponse::success('基本信息已保存', array(
            'avatar'         => $vsAdmin && isset($vsAdmin['avatar']) ? trim((string) $vsAdmin['avatar']) : '',
            'avatar_preview' => $avatarPreview,
            'username'       => $vsAdmin && isset($vsAdmin['username']) ? (string) $vsAdmin['username'] : '',
            'email'          => $vsAdmin && isset($vsAdmin['email']) ? (string) $vsAdmin['email'] : '',
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
        $result = Auth::updateAccount(
            isset($vsAdmin['email']) ? (string) $vsAdmin['email'] : '',
            $newPassword,
            $oldPassword,
            isset($vsAdmin['avatar']) ? (string) $vsAdmin['avatar'] : '',
            isset($vsAdmin['username']) ? (string) $vsAdmin['username'] : ''
        );
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('密码已修改');
    }

    // 未知 action 直接拒绝，避免落到旧「一并保存」误改资料
    if ($action !== '') {
        AjaxResponse::error('无效操作', 400);
    }

    // 兼容旧整页表单
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $avatarUrl = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $newPassword2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';
    $oldPassword = isset($_POST['old_password']) ? $_POST['old_password'] : '';

    if ($newPassword !== '' && $newPassword !== $newPassword2) {
        AjaxResponse::error('两次输入的新密码不一致');
    }

    $result = Auth::updateAccount(
        $email,
        $newPassword !== '' ? $newPassword : null,
        $newPassword !== '' ? $oldPassword : null,
        $avatarUrl,
        $username
    );

    if ($result !== true) {
        AjaxResponse::error($result);
    }

    $vsAdmin = Auth::user();
    $avatarPreview = UserAvatar::resolve($vsAdmin);
    AjaxResponse::success('账号信息已保存', array(
        'avatar' => $vsAdmin && isset($vsAdmin['avatar']) ? trim((string) $vsAdmin['avatar']) : '',
        'avatar_preview' => $avatarPreview,
    ));
}

$defaultTab = 'bind';
if (isset($_GET['tab'])) {
    $t = (string) $_GET['tab'];
    if (in_array($t, array('bind', 'profile', 'password'), true)) {
        $defaultTab = $t;
    }
}

vs_admin_layout_start('账号设置', 'account');
?>

<div class="vs-account-page" id="accountPage" data-active-tab="<?php echo vs_e($defaultTab); ?>">
    <?php if ($error): ?>
        <div class="vs-alert vs-alert--error"><?php echo vs_e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="vs-alert vs-alert--success"><?php echo vs_e($success); ?></div>
    <?php endif; ?>

    <div class="vs-account-hero">
        <img src="<?php echo vs_e($avatarPreview); ?>" alt="" class="vs-account-hero__avatar" id="accountHeroAvatar"
             data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($vsAdmin ? (int) $vsAdmin['id'] : 0)); ?>">
        <div class="vs-account-hero__meta">
            <div class="vs-account-hero__name" id="accountHeroName"><?php echo vs_e($adminUsername); ?></div>
            <div class="vs-account-hero__email" id="accountHeroEmail"><?php echo vs_e($adminEmail); ?></div>
            <span class="vs-account-hero__badge" title="您当前为站点管理员，可管理后台全部功能。">管理员</span>
        </div>
    </div>

    <div class="vs-tabs vs-account-tabs vs-api-review-tabs" id="accountTabs" role="tablist" aria-label="账号设置">
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'bind' ? ' is-active' : ''; ?>"
                data-account-tab="bind" role="tab" aria-selected="<?php echo $defaultTab === 'bind' ? 'true' : 'false'; ?>">发布身份</button>
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'profile' ? ' is-active' : ''; ?>"
                data-account-tab="profile" role="tab" aria-selected="<?php echo $defaultTab === 'profile' ? 'true' : 'false'; ?>">基本信息</button>
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'password' ? ' is-active' : ''; ?>"
                data-account-tab="password" role="tab" aria-selected="<?php echo $defaultTab === 'password' ? 'true' : 'false'; ?>">修改密码</button>
    </div>

    <div class="vs-account-pane" data-account-pane="bind"<?php echo $defaultTab === 'bind' ? '' : ' hidden'; ?>>
        <?php vs_render_notice('info', '', '管理员在后台发布 API/文章等内容时，须使用已绑定的用户账号身份，以区分后台发布与用户自主发布。绑定后该用户账号对管理员发布的内容拥有同等增删改查权限。', array('compact' => true)); ?>
        <div class="vs-admin-bind-status" id="adminBindStatus">
            <?php if ($boundUser): ?>
                <div class="vs-admin-bind-card">
                    <div class="vs-admin-bind-card__info">
                        <span class="vs-admin-bind-card__label">已绑定用户</span>
                        <span class="vs-admin-bind-card__value" id="adminBindUserText">
                            ID <?php echo (int) $boundUser['id']; ?> · <?php echo vs_e($boundUser['username']); ?> · <?php echo vs_e($boundUser['email']); ?>
                        </span>
                    </div>
                    <form method="post" action="" class="vs-admin-unbind-form" id="unbindUserForm" data-ajax="1">
                        <input type="hidden" name="action" value="unbind_user">
                        <button type="submit" class="vs-btn vs-btn--text vs-btn--oauth-action">解除绑定</button>
                    </form>
                </div>
            <?php else: ?>
                <p class="vs-admin-bind-empty" id="adminBindEmpty">当前未绑定用户账号，后台发布内容前请先完成绑定。</p>
            <?php endif; ?>
        </div>
        <form method="post" action="" class="vs-form vs-admin-bind-form" id="bindUserForm" data-ajax="1"<?php echo $boundUser ? ' hidden' : ''; ?>>
            <input type="hidden" name="action" value="bind_user">
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="bindUserAccount">用户账号</label>
                <div class="vs-form-row__field">
                    <input type="text" name="user_account" id="bindUserAccount" class="vs-input" required
                           placeholder="输入已注册用户的用户名或邮箱">
                    <?php vs_render_notice('tip', '', '用户须已通过注册流程创建；一个用户只能绑定一个管理员', array('field' => true, 'compact' => true)); ?>
                </div>
            </div>
            <div class="vs-form-actions">
                <button type="submit" class="vs-btn vs-btn--primary">绑定用户</button>
            </div>
        </form>
    </div>

    <div class="vs-account-pane" data-account-pane="profile"<?php echo $defaultTab === 'profile' ? '' : ' hidden'; ?>>
        <form method="post" action="" class="vs-form vs-account-form" id="accountProfileForm" data-ajax="1" novalidate>
            <input type="hidden" name="action" value="save_profile">
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="avatarUrlInput">头像链接</label>
                <div class="vs-form-row__field">
                    <input type="text" name="avatar" id="avatarUrlInput" class="vs-input"
                           value="<?php echo vs_e($avatarUrl); ?>" placeholder="https://example.com/avatar.jpg" maxlength="500" inputmode="url" autocomplete="off">
                    <?php vs_render_notice('tip', '', '输入图片 URL，留空则使用默认头像', array('field' => true, 'compact' => true)); ?>
                    <img src="<?php echo vs_e($avatarPreview); ?>" alt="" class="vs-account-avatar__img vs-account-avatar__img--inline" id="avatarPreview"
                         data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($vsAdmin ? (int) $vsAdmin['id'] : 0)); ?>" hidden>
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountUsername">用户名</label>
                <div class="vs-form-row__field">
                    <input type="text" name="username" id="accountUsername" class="vs-input" required minlength="3" maxlength="50"
                           value="<?php echo vs_e($adminUsername); ?>" placeholder="至少 3 个字符">
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountEmail">邮箱</label>
                <div class="vs-form-row__field">
                    <input type="email" name="email" id="accountEmail" class="vs-input" required
                           value="<?php echo vs_e($adminEmail); ?>" placeholder="admin@example.com">
                    <?php vs_render_notice('tip', '', '用于找回密码；QQ 邮箱可自动匹配 QQ 头像', array('field' => true, 'compact' => true)); ?>
                </div>
            </div>
            <div class="vs-form-actions">
                <button type="submit" class="vs-btn vs-btn--primary">保存基本信息</button>
            </div>
        </form>
    </div>

    <div class="vs-account-pane" data-account-pane="password"<?php echo $defaultTab === 'password' ? '' : ' hidden'; ?>>
        <form method="post" action="" class="vs-form vs-account-form" id="accountPasswordForm" data-ajax="1" novalidate>
            <input type="hidden" name="action" value="save_password">
            <?php vs_render_notice('info', '', '修改密码需验证当前密码；新密码至少 6 个字符。', array('compact' => true)); ?>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountOldPassword">当前密码</label>
                <div class="vs-form-row__field">
                    <input type="password" name="old_password" id="accountOldPassword" class="vs-input" required placeholder="当前密码" autocomplete="current-password">
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountNewPassword">新密码</label>
                <div class="vs-form-row__field">
                    <input type="password" name="new_password" id="accountNewPassword" class="vs-input" required placeholder="至少 6 个字符" minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountNewPassword2">确认新密码</label>
                <div class="vs-form-row__field">
                    <input type="password" name="new_password2" id="accountNewPassword2" class="vs-input" required placeholder="再次输入新密码" minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="vs-form-actions">
                <button type="submit" class="vs-btn vs-btn--primary">保存新密码</button>
            </div>
        </form>
    </div>
</div>

<?php
vs_admin_layout_end(array('account.js'));
