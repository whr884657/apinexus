<?php
/**
 * 文件：articles.php
 * 作用：前台 · 文章列表
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

vs_frontend_page('articles', '文章', array(
    'seo' => array(
        'description' => vs_seo_truncate(SiteContext::siteName() . ' 技术文章与平台动态。'),
    ),
));
