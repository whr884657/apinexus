<?php
/**
 * ApiNexus 前台首页
 *
 * SEO 双层：本入口用 vs_page_seo_pack 打包（图标/描述/关键词/OG）；
 * 主题 layout 再渲染 pageSeo 微数据（见 SEO 优化规范）。
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$seo = vs_page_seo_pack('', array(
    'description' => vs_seo_site_description(),
    'type'        => 'website',
));

vs_frontend_page('home', '', array(
    'seo' => $seo,
));
