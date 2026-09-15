<?php
/**
 * 文件：core/front/catalog.php
 * 作用：前台公开接口目录（POST + CSRF）；首页/apis 首屏不灌大包，一次拉取后本地筛选
 *
 * 公网地址：{站点根}/core/front/catalog.php（带 .php 直访，不依赖伪静态）
 */

if (!defined('VS_ROOT')) {
    define('VS_ROOT', dirname(dirname(__DIR__)));
}
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    AjaxResponse::error('系统未安装', 503);
}

vs_require_secure_post();

$ip = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
if (!AuthSecurity::rateLimitAllow('front_catalog_ip:' . $ip, 60, 60, true)) {
    AjaxResponse::error('请求过于频繁，请稍后再试', 429);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
if ($action !== 'list') {
    AjaxResponse::error('无效操作', 400);
}

// 展示顺序由 config.apiorder 决定（0 随机临时打乱 / 1 按分类权重）；忽略客户端 shuffle
$apiData = FrontendApi::listForCatalog();
$orderMode = FrontendCategory::orderMode();

$out = array(
    'code'          => 1,
    'msg'           => 'ok',
    'apiData'       => $apiData,
    'categoryNames' => FrontendCategory::nameMap(),
    'apiCount'      => is_array($apiData) ? count($apiData) : 0,
    'apiorder'      => $orderMode,
);

if (!empty($_POST['partners']) && class_exists('FrontendPartner')) {
    $out['partners'] = FrontendPartner::listForTheme();
}

AjaxResponse::json($out);
