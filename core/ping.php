<?php
/**
 * 文件：core/ping.php
 * 作用：检测指定主机 TCP 连通耗时（供前台接口卡片 / 个人主页延迟展示）
 *
 * 访问：/core/ping.php?host=example.com
 * 返回 JSON：{ "ok":1, "avg":42 } 或 { "ok":0, "msg":"..." }
 *
 * 出站必须连「校验时解析出的公网 IP」，禁止 fsockopen 域名二次解析（DNS 重绑定 TOCTOU）。
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pingIp = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
if (class_exists('AuthSecurity') && !AuthSecurity::rateLimitAllow('front_ping_ip:' . $pingIp, 30, 60, true)) {
    echo json_encode(array('ok' => 0, 'msg' => '请求过于频繁'), JSON_UNESCAPED_UNICODE);
    exit;
}

$host = isset($_GET['host']) ? trim((string) $_GET['host']) : '';
$host = strtolower($host);
$host = preg_replace('/:\d+$/', '', $host);
$host = preg_replace('/[^a-z0-9.\-]/', '', $host);

if ($host === '' || strlen($host) > 253 || strpos($host, '.') === false) {
    echo json_encode(array('ok' => 0, 'msg' => '主机无效'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!class_exists('LinkSiteMeta')) {
    echo json_encode(array('ok' => 0, 'msg' => '主机无效'), JSON_UNESCAPED_UNICODE);
    exit;
}

$pin = LinkSiteMeta::pinPublicFetchTarget('https://' . $host . '/');
if ($pin === null || empty($pin['ip']) || empty($pin['host']) || $pin['host'] !== $host) {
    echo json_encode(array('ok' => 0, 'msg' => '主机无效'), JSON_UNESCAPED_UNICODE);
    exit;
}

$ip = (string) $pin['ip'];
$connectHost = (strpos($ip, ':') !== false) ? ('[' . $ip . ']') : $ip;

$ports = array(443, 80);
$samples = array();
foreach ($ports as $port) {
    $start = microtime(true);
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($connectHost, $port, $errno, $errstr, 2.0);
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
