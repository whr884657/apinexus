<?php
/**
 * 文件：applylink.php
 * 作用：前台 · 申请友情链接（短名无横线；GET 展示 / POST 提交）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : 'apply';
    if ($action !== 'apply') {
        AjaxResponse::error('无效操作', 400);
    }

    $captchaErr = Captcha::requireValid(Captcha::SCENE_APPLYLINK, $_POST);
    // requireValid 成功返回 true，失败返回文案字符串（对齐 login.php；禁止用 !== null）
    if ($captchaErr !== true) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => $captchaErr,
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK),
        ));
    }

    $ticket = isset($_POST['submit_ticket']) ? (string) $_POST['submit_ticket'] : '';
    if (!AuthSecurity::validateAndConsumeSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK, $ticket)) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => '提交凭证已失效，请刷新页面后重试',
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK),
        ));
    }

    $coolErr = AuthSecurity::checkApplyLinkCoolDown();
    if ($coolErr !== null) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => $coolErr,
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK),
        ), 429);
    }

    $clientIp = AuthSecurity::clientIp();
    if (!AuthSecurity::rateLimitAllow('front_applylink_ip:' . $clientIp, 3600, 20, false)) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => '提交过于频繁，请稍后再试',
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK),
        ), 429);
    }

    $result = LinkManager::apply(array(
        'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
        'siteurl'     => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
        'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
        'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
        'contact'     => isset($_POST['contact']) ? (string) $_POST['contact'] : '',
    ));

    if (!is_array($result)) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => is_string($result) ? $result : '申请失败',
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK),
        ));
    }

    AuthSecurity::recordApplyLinkCoolDown();
    AuthSecurity::rateLimitAllow('front_applylink_ip:' . $clientIp, 3600, 20, true);

    if (class_exists('LinkNotify')) {
        LinkNotify::notifyAdminsPending($result);
    }

    AjaxResponse::success(
        '申请已提交，请等待站长审核',
        AuthSecurity::withSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_APPLYLINK, array())
    );
}

vs_frontend_page('applylink', '申请友链', array(
    'activeNav' => 'links',
    'siteCard'  => FrontendLink::siteCard(),
    'seo'       => vs_page_seo_pack('申请友链', array(
        'description' => vs_seo_truncate('提交友情链接申请，填写站点信息与联系方式。'),
        'robots'      => 'noindex,follow',
    )),
));
