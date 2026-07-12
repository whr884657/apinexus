<?php
/**
 * 文件：api-proxy.php
 * 作用：首页在线调试 · 简易同源代理（GET/POST）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!InstallChecker::isInstalled()) {
    http_response_code(503);
    echo json_encode(array('error' => '系统未安装'));
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$url = isset($payload['url']) ? trim((string) $payload['url']) : '';
$method = isset($payload['method']) ? strtoupper(trim((string) $payload['method'])) : 'GET';

if ($url === '' || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    echo json_encode(array('error' => '无效的请求 URL'));
    exit;
}

$allowed = array('GET', 'POST', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');
if (!in_array($method, $allowed, true)) {
    $method = 'GET';
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_HEADER, true);

$headers = array('Accept: */*');
if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    $body = isset($payload['body']) ? $payload['body'] : '';
    if (is_array($body)) {
        $body = json_encode($body);
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($errno) {
    http_response_code(502);
    echo json_encode(array('error' => $error !== '' ? $error : '请求失败'));
    exit;
}

$respHeaders = substr((string) $response, 0, $headerSize);
$respBody = substr((string) $response, $headerSize);

http_response_code($status > 0 ? $status : 200);
echo json_encode(array(
    'status'  => $status,
    'headers' => $respHeaders,
    'body'    => $respBody,
), JSON_UNESCAPED_UNICODE);
