<?php
/**
 * 文件：contributors.php
 * 作用：前台 · 贡献者
 */
define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';
if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}
vs_frontend_page('contributors', '贡献者', array(
    'seo' => array(
        'description' => vs_seo_truncate(SiteContext::siteName() . ' 项目贡献者与社区参与者。'),
    ),
));
