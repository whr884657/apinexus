<?php
/**
 * 文件：sponsor.php
 * 作用：前台 · 赞助
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$seo = vs_page_seo_pack('赞助', array(
    'description' => vs_seo_truncate('支持 ' . SiteContext::siteName() . ' 持续发展，了解赞助方式。'),
));

vs_frontend_page('sponsor', '赞助', array(
    'seo' => $seo,
));
