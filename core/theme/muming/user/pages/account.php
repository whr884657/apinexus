<?php
/**
 * 木明主题 · 用户账号设置（顶卡 + Tab）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$error = isset($error) ? (string) $error : '';
$success = isset($success) ? (string) $success : '';
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$vsUser = (isset($vsUser) && is_array($vsUser)) ? $vsUser : null;
$accountUserId = ($vsUser && isset($vsUser['id'])) ? (int) $vsUser['id'] : 0;
$accountUsername = ($vsUser && isset($vsUser['username'])) ? (string) $vsUser['username'] : '';
$accountEmail = ($vsUser && isset($vsUser['email'])) ? (string) $vsUser['email'] : '';
$avatarUrl = isset($avatarUrl) ? (string) $avatarUrl : '';
$bio = isset($bio) ? (string) $bio : '';
$blog = isset($blog) ? (string) $blog : '';
$wallpaper = isset($wallpaper) ? (string) $wallpaper : '';
$avatarPreview = isset($avatarPreview) ? (string) $avatarPreview : '';
$roleLabel = isset($roleLabel) ? (string) $roleLabel : '普通用户';
$isPlainUser = !empty($isPlainUser);
$oauthProviders = isset($oauthProviders) && is_array($oauthProviders) ? $oauthProviders : array('qq' => false, 'gitee' => false);
$oauthBindings = isset($oauthBindings) && is_array($oauthBindings) ? $oauthBindings : array('qq' => false, 'gitee' => false);
$oauthButtons = isset($oauthButtons) && is_array($oauthButtons) ? $oauthButtons : array();
$devApply = (isset($devApply) && is_array($devApply)) ? $devApply : null;
$devApplyPending = (isset($devApplyPending) && is_array($devApplyPending)) ? $devApplyPending : null;
if (empty($oauthButtons)) {
    if (!empty($oauthProviders['qq'])) {
        $oauthButtons[] = array(
            'provider' => 'qq',
            'item_id'  => '',
            'label'    => 'QQ',
            'icon'     => SiteMedia::imgUrl('oauth/qq.svg'),
            'bound'    => !empty($oauthBindings['qq']),
            'bind_url' => $vsBase . '/user/oauth/start?provider=qq&intent=bind',
        );
    }
    if (!empty($oauthProviders['gitee'])) {
        $oauthButtons[] = array(
            'provider' => 'gitee',
            'item_id'  => '',
            'label'    => 'Gitee',
            'icon'     => SiteMedia::imgUrl('oauth/gitee.svg'),
            'bound'    => !empty($oauthBindings['gitee']),
            'bind_url' => $vsBase . '/user/oauth/start?provider=gitee&intent=bind',
        );
    }
}
$roleTip = $roleLabel === '开发者'
    ? '您当前为开发者，可在用户中心「API 管理」发布自己的接口。'
    : '您当前为普通用户；可申请成为开发者以发布接口。';
$defaultTab = 'oauth';
if (isset($_GET['tab'])) {
    $t = (string) $_GET['tab'];
    if (in_array($t, array('oauth', 'profile', 'password', 'dev'), true)) {
        $defaultTab = $t;
    }
}
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
             data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($accountUserId)); ?>">
        <div class="vs-account-hero__meta">
            <div class="vs-account-hero__name" id="accountHeroName"><?php echo vs_e($accountUsername); ?></div>
            <div class="vs-account-hero__email" id="accountHeroEmail"><?php echo vs_e($accountEmail); ?></div>
            <span class="vs-account-hero__badge" title="<?php echo vs_e($roleTip); ?>"><?php echo vs_e($roleLabel); ?></span>
        </div>
    </div>

    <div class="vs-tabs vs-account-tabs vs-recharge-tabs" id="accountTabs" role="tablist" aria-label="账号设置">
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'oauth' ? ' is-active' : ''; ?>"
                data-account-tab="oauth" role="tab" aria-selected="<?php echo $defaultTab === 'oauth' ? 'true' : 'false'; ?>">第三方账号</button>
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'profile' ? ' is-active' : ''; ?>"
                data-account-tab="profile" role="tab" aria-selected="<?php echo $defaultTab === 'profile' ? 'true' : 'false'; ?>">基本信息</button>
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'password' ? ' is-active' : ''; ?>"
                data-account-tab="password" role="tab" aria-selected="<?php echo $defaultTab === 'password' ? 'true' : 'false'; ?>">修改密码</button>
        <?php if ($isPlainUser): ?>
        <button type="button" class="vs-tabs__btn vs-account-tab<?php echo $defaultTab === 'dev' ? ' is-active' : ''; ?>"
                data-account-tab="dev" role="tab" aria-selected="<?php echo $defaultTab === 'dev' ? 'true' : 'false'; ?>">开发者申请</button>
        <?php endif; ?>
    </div>

    <div class="vs-account-pane" data-account-pane="oauth"<?php echo $defaultTab === 'oauth' ? '' : ' hidden'; ?>>
        <?php if (!empty($oauthButtons)): ?>
            <?php vs_render_notice('info', '', '绑定后可在登录页使用第三方快捷登录；解绑后可在本页重新绑定。', array('compact' => true)); ?>
            <div class="vs-oauth-bind-list">
                <?php foreach ($oauthButtons as $btn): ?>
                    <?php
                    $btnProvider = isset($btn['provider']) ? (string) $btn['provider'] : '';
                    $btnItemId = isset($btn['item_id']) ? (string) $btn['item_id'] : '';
                    $btnLabel = isset($btn['label']) ? (string) $btn['label'] : '';
                    $btnIcon = isset($btn['icon']) ? (string) $btn['icon'] : '';
                    $btnBound = !empty($btn['bound']);
                    $btnBindUrl = isset($btn['bind_url']) ? (string) $btn['bind_url'] : '';
                    if ($btnProvider === '' || $btnLabel === '') {
                        continue;
                    }
                    ?>
                <div class="vs-oauth-bind-item" data-oauth-bound="<?php echo $btnBound ? '1' : '0'; ?>">
                    <div class="vs-oauth-bind-item__info">
                        <img src="<?php echo vs_e($btnIcon); ?>" alt="<?php echo vs_e($btnLabel); ?>" class="vs-oauth-bind-item__icon" width="24" height="24">
                        <div>
                            <div class="vs-oauth-bind-item__name"><?php echo vs_e($btnLabel); ?></div>
                            <div class="vs-oauth-bind-item__status"><?php echo $btnBound ? '已绑定' : '未绑定'; ?></div>
                        </div>
                    </div>
                    <div class="vs-oauth-bind-item__action">
                        <?php if ($btnBound): ?>
                            <form method="post" action="" class="vs-oauth-unbind-form" data-ajax="1">
                                <input type="hidden" name="action" value="oauth_unbind">
                                <input type="hidden" name="provider" value="<?php echo vs_e($btnProvider); ?>">
                                <input type="hidden" name="item_id" value="<?php echo vs_e($btnItemId); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">
                                <button type="submit" class="vs-btn vs-btn--text vs-btn--oauth-action">解绑</button>
                            </form>
                        <?php else: ?>
                            <a href="<?php echo vs_e($btnBindUrl); ?>" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php vs_render_notice('info', '', '管理员尚未开启第三方登录，或当前无可绑定的方式。', array('compact' => true)); ?>
        <?php endif; ?>
    </div>

    <div class="vs-account-pane" data-account-pane="profile"<?php echo $defaultTab === 'profile' ? '' : ' hidden'; ?>>
        <form method="post" action="" class="vs-form vs-account-form" id="accountProfileForm" data-ajax="1" novalidate>
            <input type="hidden" name="action" value="save_profile">
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="avatarUrlInput">头像链接</label>
                <div class="vs-form-row__field">
                    <input type="text" name="avatar" id="avatarUrlInput" class="vs-input"
                           value="<?php echo vs_e($avatarUrl); ?>" placeholder="https://example.com/avatar.jpg" maxlength="500" inputmode="url" autocomplete="off">
                    <?php vs_render_notice('tip', '', '输入图片 URL，留空则使用 QQ 邮箱头像或默认头像', array('field' => true, 'compact' => true)); ?>
                    <img src="<?php echo vs_e($avatarPreview); ?>" alt="" class="vs-account-avatar__img vs-account-avatar__img--inline" id="avatarPreview"
                         data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($accountUserId)); ?>" hidden>
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountUsername">用户名</label>
                <div class="vs-form-row__field">
                    <input type="text" name="username" id="accountUsername" class="vs-input" required minlength="3" maxlength="50"
                           value="<?php echo vs_e($accountUsername); ?>" placeholder="至少 3 个字符">
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountEmail">邮箱</label>
                <div class="vs-form-row__field">
                    <input type="email" name="email" id="accountEmail" class="vs-input" required
                           value="<?php echo vs_e($accountEmail); ?>" placeholder="user@example.com">
                    <?php vs_render_notice('tip', '', '用于找回密码；QQ 邮箱可自动匹配 QQ 头像', array('field' => true, 'compact' => true)); ?>
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountBio">个人简介</label>
                <div class="vs-form-row__field">
                    <textarea name="bio" id="accountBio" class="vs-textarea" rows="3" maxlength="200"
                              placeholder="介绍一下自己（公开主页展示）"><?php echo vs_e($bio); ?></textarea>
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountBlog">博客链接</label>
                <div class="vs-form-row__field">
                    <input type="url" name="blog" id="accountBlog" class="vs-input"
                           value="<?php echo vs_e($blog); ?>" placeholder="https://example.com" maxlength="500">
                </div>
            </div>
            <div class="vs-form-row vs-form-row--account">
                <label class="vs-label" for="accountWallpaper">主页背景</label>
                <div class="vs-form-row__field">
                    <input type="url" name="wallpaper" id="accountWallpaper" class="vs-input"
                           value="<?php echo vs_e($wallpaper); ?>" placeholder="https://example.com/bg.jpg" maxlength="500">
                    <?php vs_render_notice('tip', '', '留空则使用站点统一默认背景', array('field' => true, 'compact' => true)); ?>
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

    <?php if ($isPlainUser): ?>
    <div class="vs-account-pane" data-account-pane="dev"<?php echo $defaultTab === 'dev' ? '' : ' hidden'; ?>>
        <?php if ($devApplyPending): ?>
            <?php vs_render_notice('info', '审核中', '您已提交开发者申请，请等待管理员审核。理由：' . (isset($devApplyPending['description']) ? (string) $devApplyPending['description'] : ''), array('compact' => true)); ?>
        <?php else: ?>
            <?php
            if ($devApply && (int) $devApply['status'] === ApiFeedbackManager::STATUS_REJECTED) {
                vs_render_notice('warning', '上次申请未通过', '可修改理由后重新提交。', array('compact' => true));
            }
            ?>
            <form method="post" action="" class="vs-form vs-account-form" id="accountDevForm" data-ajax="1" novalidate>
                <input type="hidden" name="action" value="apply_developer">
                <div class="vs-form-row vs-form-row--account">
                    <label class="vs-label" for="devApplyReason">申请理由</label>
                    <div class="vs-form-row__field">
                        <textarea name="reason" id="devApplyReason" class="vs-textarea" rows="4" maxlength="200" required
                                  placeholder="请说明申请成为开发者的用途（不超过 200 字）"></textarea>
                        <?php vs_render_notice('tip', '', '通过后可在「API 管理」发布接口；管理员可在后台「开发审核」中审核。', array('field' => true, 'compact' => true)); ?>
                    </div>
                </div>
                <div class="vs-form-actions">
                    <button type="submit" class="vs-btn vs-btn--primary">提交申请</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
