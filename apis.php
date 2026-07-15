<?php
/**
 * 文件：apis.php
 * 作用：
 *   1) 路径样式网关：/apis/{短码} 或 /apis.php/{短码} → 代理跳转上游
 *   2) 无短码时：前台 · 全部接口列表页
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (ApiProxy::isGatewayRequest()) {
    if (!InstallChecker::isInstalled()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo '系统未安装';
        exit;
    }
    ApiProxy::handleRequest();
}

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

vs_frontend_page('apis', '全部接口');
