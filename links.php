<?php
/**
 * 文件：links.php
 * 作用：前台 · 友情链接
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$seo = vs_page_seo_pack('友情链接', array(
    'description' => vs_seo_truncate('本站友情链接列表，欢迎互换优质站点链接。'),
));

vs_frontend_page('links', '友情链接', array(
    'activeNav' => 'links',
    'seo'       => $seo,
));
