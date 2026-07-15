<?php
/**
 * 文件：apis.php
 * 作用：
 *   1) 无 PATH_INFO：前台 · 全部接口列表（/apis 或 /apis.php）
 *   2) 有 PATH_INFO：代理网关 /apis.php/{短码} → 跳转上游
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

// 脚本后带路径段 = 代理调用；否则为接口列表
$proxySlug = ApiProxy::resolveSlugFromRequest();
if ($proxySlug !== '') {
    if (!InstallChecker::isInstalled()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo '系统未安装';
        exit;
    }
    ApiProxy::handleRequest($proxySlug);
}

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

vs_frontend_page('apis', '全部接口');
