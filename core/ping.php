<?php
/**
 * 文件：core/ping.php
 * 作用：检测指定主机 TCP 连通耗时（供前台接口卡片延迟展示）
 *
 * 访问：/core/ping.php?host=example.com
 * 返回 JSON：{ "ok":1, "avg":42 } 或 { "ok":0, "msg":"..." }
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$host = isset($_GET['host']) ? trim((string) $_GET['host']) : '';
$host = strtolower($host);
$host = preg_replace('/:\d+$/', '', $host);
$host = preg_replace('/[^a-z0-9.\-]/', '', $host);

if ($host === '' || strlen($host) > 253 || strpos($host, '.') === false) {
    echo json_encode(array('ok' => 0, 'msg' => '主机无效'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|0\.|::1)/', $host)) {
    echo json_encode(array('ok' => 0, 'msg' => '主机无效'), JSON_UNESCAPED_UNICODE);
    exit;
}

$ip = gethostbyname($host);
if ($ip === $host || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    // 解析失败或解析到保留地址
    if ($ip === $host || $ip === '') {
        echo json_encode(array('ok' => 0, 'msg' => '无法解析'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        echo json_encode(array('ok' => 0, 'msg' => '主机无效'), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$ports = array(443, 80);
$samples = array();
foreach ($ports as $port) {
    $start = microtime(true);
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 2.0);
    $ms = (microtime(true) - $start) * 1000;
    if (is_resource($fp)) {
        fclose($fp);
        $samples[] = $ms;
        break;
    }
}

if (count($samples) === 0) {
    echo json_encode(array('ok' => 0, 'msg' => '连接失败'), JSON_UNESCAPED_UNICODE);
    exit;
}

$avg = (int) round(array_sum($samples) / count($samples));
if ($avg < 1) {
    $avg = 1;
}

echo json_encode(array('ok' => 1, 'avg' => $avg), JSON_UNESCAPED_UNICODE);
