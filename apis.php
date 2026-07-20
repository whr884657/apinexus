<?php
/**
 * 文件：apis.php
 * 作用：
 *   1) /apis 或 /apis.php（无短码）→ 全部接口列表
 *   2) /apis/{短码}（伪静态进本脚本）或 /apis.php/{短码} → 代理网关
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

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

vs_frontend_page('apis', '全部接口', array(
    'seo' => array(
        'description' => vs_seo_truncate('浏览全部已上线的公开 API 接口，支持分类筛选与详情查看。'),
    ),
));
