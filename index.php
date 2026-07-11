<?php
/**
 * misc-api 前台首页
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$heroDesc = SiteContext::siteDescription();
if ($heroDesc === '') {
    $heroDesc = '基于 PHP + MySQL 的轻量级 Web 管理系统，全面适配电脑端与手机端。';
}

vs_frontend_page('home', '首页', array('heroDesc' => $heroDesc));
