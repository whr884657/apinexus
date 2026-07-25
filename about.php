<?php
/**
 * 文件：about.php
 * 作用：前台 · 关于
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$seo = vs_page_seo_pack('关于', array(
    'description' => vs_seo_truncate('关于 ' . SiteContext::siteName() . ' 平台介绍、版本信息与联系方式。'),
));

vs_frontend_page('about', '关于', array(
    'seo' => $seo,
));
