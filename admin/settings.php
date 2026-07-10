<?php
/**
 * 文件：admin/settings.php
 * 作用：misc-api 后台系统设置（站点信息、用户注册、OAuth、邮箱发信）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_site') {
        try {
            Config::setMany(array(
                'site_name'        => trim(isset($_POST['site_name']) ? $_POST['site_name'] : ''),
                'site_description' => trim(isset($_POST['site_description']) ? $_POST['site_description'] : ''),
                'site_keywords'    => trim(isset($_POST['site_keywords']) ? $_POST['site_keywords'] : ''),
                'site_favicon'     => trim(isset($_POST['site_favicon']) ? $_POST['site_favicon'] : ''),
                'site_logo'        => trim(isset($_POST['site_logo']) ? $_POST['site_logo'] : ''),
                'site_icp'         => trim(isset($_POST['site_icp']) ? $_POST['site_icp'] : ''),
                'site_gongan'      => trim(isset($_POST['site_gongan']) ? $_POST['site_gongan'] : ''),
            ));
            SiteContext::clearCache();
            AjaxResponse::success('站点设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error($e->getMessage());
        }
    }

    if ($action === 'save_register') {
        try {
            $input = isset($_POST['register_email_suffixes']) ? $_POST['register_email_suffixes'] : '';
            $suffixes = RegisterPolicy::parseSuffixInput($input);
            RegisterPolicy::saveEmailSuffixes($suffixes);
            AjaxResponse::success('注册设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败：' . $e->getMessage());
        }
    }

    if ($action === 'save_oauth') {
        try {
            OAuthConfig::save(
                array(
                    'enabled' => isset($_POST['qq_enabled']) ? '1' : '',
                    'app_id'  => isset($_POST['qq_app_id']) ? $_POST['qq_app_id'] : '',
                    'app_key' => isset($_POST['qq_app_key']) ? $_POST['qq_app_key'] : '',
                ),
                array(
                    'enabled'       => isset($_POST['gitee_enabled']) ? '1' : '',
                    'client_id'     => isset($_POST['gitee_client_id']) ? $_POST['gitee_client_id'] : '',
                    'client_secret' => isset($_POST['gitee_client_secret']) ? $_POST['gitee_client_secret'] : '',
                )
            );
            AjaxResponse::success('OAuth 设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败：' . $e->getMessage());
        }
    }

    if ($action === 'save_mail') {
        try {
            Config::setMany(array(
                'mail_enabled'     => isset($_POST['mail_enabled']) ? '1' : '0',
                'mail_smtp_host'   => trim(isset($_POST['mail_smtp_host']) ? $_POST['mail_smtp_host'] : ''),
                'mail_smtp_port'   => trim(isset($_POST['mail_smtp_port']) ? $_POST['mail_smtp_port'] : '465'),
                'mail_smtp_user'   => trim(isset($_POST['mail_smtp_user']) ? $_POST['mail_smtp_user'] : ''),
                'mail_smtp_pass'   => trim(isset($_POST['mail_smtp_pass']) ? $_POST['mail_smtp_pass'] : ''),
                'mail_smtp_secure' => trim(isset($_POST['mail_smtp_secure']) ? $_POST['mail_smtp_secure'] : 'ssl'),
                'mail_from_email'  => trim(isset($_POST['mail_from_email']) ? $_POST['mail_from_email'] : ''),
                'mail_from_name'   => trim(isset($_POST['mail_from_name']) ? $_POST['mail_from_name'] : SiteContext::siteName()),
            ));

            AjaxResponse::success('邮箱设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败：' . $e->getMessage());
        }
    }

    if ($action === 'test_mail') {
        $testEmail = trim(isset($_POST['test_email']) ? $_POST['test_email'] : '');
        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            AjaxResponse::error('请输入有效的测试邮箱地址');
        }
        try {
            Mailer::send(
                $testEmail,
                SiteContext::siteName() . ' 邮箱测试',
                '<p>这是一封来自 ' . htmlspecialchars(SiteContext::siteName()) . ' 的测试邮件。</p>'
            );
            AjaxResponse::success('测试邮件已发送，请查收');
        } catch (Exception $e) {
            AjaxResponse::error('发送失败：' . $e->getMessage());
        }
    }

    AjaxResponse::error('未知操作', 400);
}

Config::clearCache();
$vsCfg = Config::all();
$registerSuffixes = RegisterPolicy::formatSuffixInput(RegisterPolicy::getPolicy()['email_suffixes']);
$oauthCfg = OAuthConfig::getAll();
$oauthQqCallback = OAuthConfig::callbackUrl('qq');
$oauthGiteeCallback = OAuthConfig::callbackUrl('gitee');

vs_admin_layout_start('系统设置', 'settings');
?>

<div id="settingsFlash" class="vs-settings-flash" role="alert" hidden></div>

<?php
vs_admin_accordion_start(
    'settings-site',
    '站点信息',
    '配置系统名称、图标、描述与备案信息'
);
?>
    <form method="post" action="" class="vs-form" id="siteForm" data-ajax="1">
        <input type="hidden" name="action" value="save_site">
        <div class="vs-form-row">
            <label class="vs-label">系统名称</label>
            <input type="text" name="site_name" class="vs-input" required maxlength="50"
                   value="<?php echo vs_e(Config::get('site_name', '')); ?>">
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">站点图标（Favicon）</label>
                <input type="text" name="site_favicon" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_favicon', '')); ?>"
                       placeholder="/assets/img/favicon.ico 或 https://...">
                <?php vs_render_notice('tip', '', '浏览器标签页小图标，支持相对路径或完整 URL', array('field' => true, 'compact' => true)); ?>
            </div>
            <div class="vs-form-row">
                <label class="vs-label">站点 Logo</label>
                <input type="text" name="site_logo" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_logo', '')); ?>"
                       placeholder="/assets/img/logo.png 或 https://...">
                <?php vs_render_notice('tip', '', '用于前台页眉与后台侧栏展示', array('field' => true, 'compact' => true)); ?>
            </div>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">系统描述</label>
            <textarea name="site_description" class="vs-textarea" rows="3"><?php echo vs_e(Config::get('site_description', '')); ?></textarea>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">关键词</label>
            <input type="text" name="site_keywords" class="vs-input"
                   value="<?php echo vs_e(Config::get('site_keywords', '')); ?>">
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">ICP 备案号</label>
                <input type="text" name="site_icp" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_icp', '')); ?>"
                       placeholder="例如 京ICP备12345678号">
            </div>
            <div class="vs-form-row">
                <label class="vs-label">公安备案号</label>
                <input type="text" name="site_gongan" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_gongan', '')); ?>"
                       placeholder="例如 京公网安备11010802012345号">
            </div>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存站点设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-register',
    '用户注册',
    '限制可注册邮箱后缀，减少临时邮箱滥用'
);
?>
    <form method="post" action="" class="vs-form" id="registerForm" data-ajax="1">
        <input type="hidden" name="action" value="save_register">
        <?php
        vs_render_notice(
            'info',
            '邮箱后缀白名单',
            '<p>每行填写一个邮箱后缀（如 <code>qq.com</code> 或 <code>@163.com</code>）。</p><p>留空表示不限制，所有邮箱均可注册。</p>',
            array('allow_html' => true, 'compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label">允许的邮箱后缀</label>
            <textarea name="register_email_suffixes" class="vs-textarea" rows="5"
                      placeholder="qq.com&#10;163.com&#10;gmail.com"><?php echo vs_e($registerSuffixes); ?></textarea>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存注册设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-oauth',
    '第三方登录',
    '配置 QQ / Gitee OAuth，仅用于用户登录页聚合登录'
);
?>
    <form method="post" action="" class="vs-form" id="oauthForm" data-ajax="1">
        <input type="hidden" name="action" value="save_oauth">
        <?php
        vs_render_notice(
            'info',
            '使用说明',
            '<p>用户须先完成邮箱注册，首次使用第三方登录时需验证已有账号密码完成绑定。</p>'
            . '<p>QQ 回调：<code>' . vs_e($oauthQqCallback) . '</code></p>'
            . '<p>Gitee 回调：<code>' . vs_e($oauthGiteeCallback) . '</code></p>',
            array('allow_html' => true, 'compact' => true)
        );
        ?>
        <h4 class="vs-form-subtitle">QQ 互联</h4>
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="qq_enabled" value="1" <?php echo !empty($oauthCfg['qq']['enabled']) ? 'checked' : ''; ?>>
                <span>启用 QQ 登录</span>
            </label>
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">App ID</label>
                <input type="text" name="qq_app_id" class="vs-input" value="<?php echo vs_e($oauthCfg['qq']['app_id']); ?>">
            </div>
            <div class="vs-form-row">
                <label class="vs-label">App Key</label>
                <input type="text" name="qq_app_key" class="vs-input" value="<?php echo vs_e($oauthCfg['qq']['app_key']); ?>">
            </div>
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">Gitee OAuth</h4>
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="gitee_enabled" value="1" <?php echo !empty($oauthCfg['gitee']['enabled']) ? 'checked' : ''; ?>>
                <span>启用 Gitee 登录</span>
            </label>
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">Client ID</label>
                <input type="text" name="gitee_client_id" class="vs-input" value="<?php echo vs_e($oauthCfg['gitee']['client_id']); ?>">
            </div>
            <div class="vs-form-row">
                <label class="vs-label">Client Secret</label>
                <input type="text" name="gitee_client_secret" class="vs-input" value="<?php echo vs_e($oauthCfg['gitee']['client_secret']); ?>">
            </div>
        </div>

        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存 OAuth 设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-mail',
    '邮箱发信',
    '配置 SMTP 发信参数，并发送测试邮件验证'
);
?>
    <form method="post" action="" class="vs-form" id="mailForm" data-ajax="1">
        <input type="hidden" name="action" value="save_mail">
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="mail_enabled" value="1" <?php echo (isset($vsCfg['mail_enabled']) && $vsCfg['mail_enabled'] === '1') ? 'checked' : ''; ?>>
                <span>启用邮箱发信</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">SMTP 服务器</label>
            <input type="text" name="mail_smtp_host" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_smtp_host']) ? $vsCfg['mail_smtp_host'] : ''); ?>">
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label">SMTP 端口</label>
                <input type="text" name="mail_smtp_port" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_smtp_port']) ? $vsCfg['mail_smtp_port'] : '465'); ?>">
            </div>
            <div class="vs-form-col">
                <label class="vs-label">加密方式</label>
                <select name="mail_smtp_secure" class="vs-input">
                    <option value="ssl" <?php echo (isset($vsCfg['mail_smtp_secure']) && $vsCfg['mail_smtp_secure'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo (isset($vsCfg['mail_smtp_secure']) && $vsCfg['mail_smtp_secure'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                    <option value="none" <?php echo (isset($vsCfg['mail_smtp_secure']) && $vsCfg['mail_smtp_secure'] === 'none') ? 'selected' : ''; ?>>无</option>
                </select>
            </div>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">SMTP 用户名</label>
            <input type="text" name="mail_smtp_user" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_smtp_user']) ? $vsCfg['mail_smtp_user'] : ''); ?>">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">SMTP 密码</label>
            <input type="text" name="mail_smtp_pass" class="vs-input"
                   value="<?php echo vs_e(isset($vsCfg['mail_smtp_pass']) ? $vsCfg['mail_smtp_pass'] : ''); ?>">
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label">发件人邮箱</label>
                <input type="email" name="mail_from_email" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_from_email']) ? $vsCfg['mail_from_email'] : ''); ?>">
            </div>
            <div class="vs-form-col">
                <label class="vs-label">发件人名称</label>
                <input type="text" name="mail_from_name" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_from_name']) ? $vsCfg['mail_from_name'] : ''); ?>">
            </div>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存邮箱设置</button>
        </div>
    </form>

    <hr class="vs-divider">

    <form method="post" action="" class="vs-form vs-form--test-mail" id="testMailForm" data-ajax="1">
        <input type="hidden" name="action" value="test_mail">
        <h4 class="vs-form-subtitle">发送测试邮件</h4>
        <div class="vs-form-row vs-form-row--test-mail">
            <input type="email" name="test_email" class="vs-input" placeholder="测试邮箱地址" required>
            <button type="submit" class="vs-btn vs-btn--default">发送测试</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<script>window.VS_SETTINGS_BASE = <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>;</script>

<?php vs_admin_layout_end(array('settings.js')); ?>
