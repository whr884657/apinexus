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

    $clientIp = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
    if (class_exists('AuthSecurity') && !AuthSecurity::rateLimitAllow('front_applylink_ip:' . $clientIp, 8, 600, true)) {
        AjaxResponse::error('提交过于频繁，请稍后再试', 429);
    }

    $result = LinkManager::apply(array(
        'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
        'siteurl'     => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
        'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
        'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
        'contact'     => isset($_POST['contact']) ? (string) $_POST['contact'] : '',
    ));

    if (!is_array($result)) {
        AjaxResponse::error($result);
    }

    if (class_exists('LinkNotify')) {
        LinkNotify::notifyAdminsPending($result);
    }

    // 公开响应仅回业务提示，不回传联系方式等完整记录（防整页误提交时信息暴露）
    AjaxResponse::success('申请已提交，请等待站长审核');
}

vs_frontend_page('applylink', '申请友链', array(
    'activeNav' => 'links',
    'siteCard'  => FrontendLink::siteCard(),
    'seo'       => vs_page_seo_pack('申请友链', array(
        'description' => vs_seo_truncate('提交友情链接申请，填写站点信息与联系方式。'),
        'robots'      => 'noindex,follow',
    )),
));
