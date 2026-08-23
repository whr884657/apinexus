<?php
/**
 * 默认主题 · 用户账号设置页视图
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
$oauthProviders = isset($oauthProviders) && is_array($oauthProviders) ? $oauthProviders : array('qq' => false, 'gitee' => false);
$oauthBindings = isset($oauthBindings) && is_array($oauthBindings) ? $oauthBindings : array('qq' => false, 'gitee' => false);
$oauthBindQq = $vsBase . '/user/oauth/start?provider=qq&intent=bind';
$oauthBindGitee = $vsBase . '/user/oauth/start?provider=gitee&intent=bind';
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
                        <img src="<?php echo vs_e($avatarPreview); ?>" alt="用户头像" class="vs-account-avatar__img" id="avatarPreview"
                             data-fallback="<?php echo vs_e(UserAvatar::localRandomAvatar($accountUserId)); ?>">
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
                            <?php vs_render_notice('tip', '', '留空则使用站点统一默认背景；填写后仅你的公开主页使用此图', array('field' => true, 'compact' => true)); ?>
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
                    <img src="<?php echo vs_e(SiteMedia::imgUrl('QQ.svg')); ?>" alt="QQ" class="vs-oauth-bind-item__icon" width="24" height="24">
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
                        <a href="<?php echo vs_e($oauthBindQq); ?>" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($oauthProviders['gitee']): ?>
            <div class="vs-oauth-bind-item">
                <div class="vs-oauth-bind-item__info">
                    <img src="<?php echo vs_e(SiteMedia::imgUrl('gitee.svg')); ?>" alt="Gitee" class="vs-oauth-bind-item__icon" width="24" height="24">
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
                        <a href="<?php echo vs_e($oauthBindGitee); ?>" class="vs-btn vs-btn--default vs-btn--oauth-action">绑定</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>
