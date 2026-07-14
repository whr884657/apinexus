<?php
/**
 * 文件：proxy.php
 * 作用：代理外链接口入口（纯 PHP 302，无需改 Nginx 伪静态）
 *
 * 示例：/proxy.php?s=sjspks&foo=1  →  302 到上游并带上 foo=1
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo '系统未安装';
    exit;
}

ApiProxy::handleRequest();
