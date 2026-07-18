<?php
/**
 * 文件：codepay.php
 * 作用：码支付公网入口（PATH_INFO：/notify | /return）
 *
 * 回调逻辑位于 core/play/codeplay/，根目录仅保留薄入口，禁止再建 pay/ 目录。
 */

define('VS_ROOT', __DIR__);

$act = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $act = trim((string) $_SERVER['PATH_INFO'], '/');
} elseif (isset($_GET['_vs_act'])) {
    $act = trim((string) $_GET['_vs_act'], '/');
}

if ($act === 'notify') {
    require VS_ROOT . '/core/play/codeplay/notify.php';
    exit;
}

if ($act === 'return') {
    require VS_ROOT . '/core/play/codeplay/return.php';
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'fail';
