<?php
/**
 * 文件：apis.php
 * 作用：前台 · 全部接口
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

vs_frontend_page('apis', '全部接口');
