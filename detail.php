<?php
/**
 * 文件：detail.php
 * 作用：接口详情页入口（PATH_INFO：/detail.php/{id}，不依赖伪静态）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$apiId = vs_resolve_path_id();
$api = $apiId > 0 ? FrontendApi::findForThemeById($apiId) : null;
$base = rtrim(vs_base_url(), '/');

$playground = array(
    'loggedIn'      => UserAuth::check(),
    'apiKey'        => '',
    'apiKeyCount'   => 0,
    'userCenterUrl' => $base . '/user/index',
    'loginUrl'      => $base . '/user/login',
);

if ($playground['loggedIn'] && class_exists('ApiKeyManager') && ApiKeyManager::tableReady()) {
    $user = UserAuth::user();
    $uid = is_array($user) && isset($user['id']) ? (int) $user['id'] : 0;
    if ($uid > 0) {
        foreach (ApiKeyManager::listByUser($uid) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $enabled = isset($row['status'])
                ? ((int) $row['status'] === ApiKeyManager::STATUS_ENABLED)
                : true;
            if (!$enabled) {
                continue;
            }
            $playground['apiKeyCount']++;
            if ($playground['apiKey'] === '' && !empty($row['secret'])) {
                $playground['apiKey'] = (string) $row['secret'];
            }
        }
    }
}

if ($api === null) {
    http_response_code(404);
    vs_frontend_page('detail', '接口不存在', array(
        'api'         => null,
        'apiId'       => $apiId,
        'notFound'    => true,
        'playground'  => $playground,
    ));
    exit;
}

$pageTitle = isset($api['name']) ? ((string) $api['name'] . ' · 接口详情') : '接口详情';
vs_frontend_page('detail', $pageTitle, array(
    'api'        => $api,
    'apiId'      => $apiId,
    'notFound'   => false,
    'playground' => $playground,
));
