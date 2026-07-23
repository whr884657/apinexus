<?php
/**
 * ApiNexus 前台首页
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

// SEO 描述：只认系统设置「站点描述」，禁止主题 Hero 标签/文案渗入
$seoDesc = vs_seo_site_description();

vs_frontend_page('home', '', array(
    'seo' => array(
        'description' => $seoDesc,
        'type'        => 'website',
    ),
));
