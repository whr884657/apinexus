<?php
/**
 * 文件：core/playground/relay.php
 * 作用：前台在线测试同源中继入口（POST + CSRF）
 *
 * 说明：
 * - 供各主题共用，勿放根目录、勿放单一主题包
 * - 公网地址：{站点根}/core/playground/relay.php（带 .php 直访，不依赖伪静态）
 */

if (!defined('VS_ROOT')) {
    define('VS_ROOT', dirname(dirname(__DIR__)));
}
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    AjaxResponse::error('系统未安装', 503);
}

AuthSecurity::sendSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('无效请求', 405);
}

if (!AuthSecurity::validateSameOrigin()) {
    AjaxResponse::error('请求来源无效，请从本站页面操作', 403);
}

$raw = file_get_contents('php://input');
$input = array();
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if ($input === array() && !empty($_POST)) {
    $input = $_POST;
}

$token = '';
if (isset($input['csrf_token'])) {
    $token = (string) $input['csrf_token'];
} elseif (isset($_POST['csrf_token'])) {
    $token = (string) $_POST['csrf_token'];
} elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
}

if (!AuthSecurity::validateCsrf($token)) {
    AjaxResponse::error('登录凭证已失效，请刷新页面后重试', 403);
}

$apiId = isset($input['api_id']) ? (int) $input['api_id'] : 0;
$method = isset($input['method']) ? (string) $input['method'] : 'GET';
$params = array();
if (isset($input['params']) && is_array($input['params'])) {
    foreach ($input['params'] as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $params[(string) $k] = (string) $v;
        }
    }
}

$result = PlaygroundRelay::execute($apiId, $method, $params);

AjaxResponse::json(array(
    'code'        => !empty($result['ok']) ? 1 : 0,
    'msg'         => isset($result['msg']) ? (string) $result['msg'] : '',
    'http'        => isset($result['http']) ? (int) $result['http'] : 0,
    'contentType' => isset($result['contentType']) ? (string) $result['contentType'] : '',
    'body'        => isset($result['body']) ? (string) $result['body'] : '',
    'encoding'    => isset($result['encoding']) ? (string) $result['encoding'] : 'text',
    'displayUrl'  => isset($result['displayUrl']) ? (string) $result['displayUrl'] : '',
));
