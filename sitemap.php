<?php
/**
 * 文件：sitemap.php
 * 作用：对外 /sitemap.xml（伪静态）与 /sitemap.php 站点地图入口
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Site not installed';
    exit;
}

Sitemap::emit();
