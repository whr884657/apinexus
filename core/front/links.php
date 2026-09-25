<?php
/**
 * 文件：core/front/links.php
 * 作用：前台友情链接公开列表（POST + CSRF）；页脚/友链页首屏不灌大包（对齐 catalog.php / E252）
 *
 * 公网地址：{站点根}/core/front/links.php（带 .php 直访，不依赖伪静态）
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
if (!AuthSecurity::rateLimitAllow('front_links_ip:' . $ip, 60, 60, true)) {
    AjaxResponse::error('请求过于频繁，请稍后再试', 429);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
if ($action !== 'footer' && $action !== 'page') {
    AjaxResponse::error('无效操作', 400);
}

if (!class_exists('FrontendLink')) {
    AjaxResponse::error('友链模块未就绪', 500);
}

if ($action === 'footer') {
    $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 8;
    if ($limit < 0) {
        $limit = 0;
    }
    if ($limit > 10) {
        $limit = 10;
    }
    $pack = FrontendLink::pickForFooter($limit);
    AjaxResponse::json(array(
        'code'     => 1,
        'msg'      => 'ok',
        'items'    => isset($pack['items']) ? $pack['items'] : array(),
        'has_more' => !empty($pack['has_more']),
        'total'    => isset($pack['total']) ? (int) $pack['total'] : 0,
        'limit'    => isset($pack['limit']) ? (int) $pack['limit'] : $limit,
    ));
}

$pack = FrontendLink::listForThemePage();
AjaxResponse::json(array(
    'code'      => 1,
    'msg'       => 'ok',
    'items'     => isset($pack['items']) ? $pack['items'] : array(),
    'total'     => isset($pack['total']) ? (int) $pack['total'] : 0,
    'truncated' => !empty($pack['truncated']),
    'limit'     => isset($pack['limit']) ? (int) $pack['limit'] : FrontendLink::PAGE_HARD_LIMIT,
));
