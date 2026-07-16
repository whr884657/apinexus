<?php
/**
 * 文件：api-detail.php
 * 作用：接口详情页入口
 *   美观：/api-detail/{id}（伪静态 → ?_vs_id=）
 *   PATH_INFO：/api-detail.php/{id}
 *   兼容旧链：?id=
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$apiId = vs_resolve_path_id('_vs_id');
$api = $apiId > 0 ? FrontendApi::findForThemeById($apiId) : null;

if ($api === null) {
    http_response_code(404);
    vs_frontend_page('api-detail', '接口不存在', array(
        'api'       => null,
        'apiId'     => $apiId,
        'notFound'  => true,
    ));
    exit;
}

$pageTitle = isset($api['name']) ? ((string) $api['name'] . ' · 接口详情') : '接口详情';
vs_frontend_page('api-detail', $pageTitle, array(
    'api'      => $api,
    'apiId'    => $apiId,
    'notFound' => false,
));
