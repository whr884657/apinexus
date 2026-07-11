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

vs_frontend_page('sponsor', '赞助');
