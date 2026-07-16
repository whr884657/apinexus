<?php
/**
 * 一言API代理接口
 * 完全兼容 hitokoto.cn 官方API，纯转发不做任何修改
 * 官方文档: https://developer.hitokoto.cn/sentence/
 */

// —— 调用统计（3 行；任意深度；详见 api/统计代码使用说明.md）——
for ($d = __DIR__; !is_file($d . '/core/bootstrap.php') && $d !== dirname($d); $d = dirname($d)) {}
require_once $d . '/core/bootstrap.php';
ApiStats::hit();

// 禁用错误显示
error_reporting(0);
ini_set('display_errors', '0');

// ==================== 跨域机制优化 ====================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// 预检请求直接返回
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ==================== 上游API列表（优先级从高到低） ====================
$apiList = [
    'https://international.v1.hitokoto.cn',
    'https://v1.hitokoto.cn',
];

// 手动构建查询字符串，兼容各种参数传递方式
$params = [];

// 处理c参数（支持数组或&分隔的字符串）
$cValues = [];
if (isset($_GET['c'])) {
    if (is_array($_GET['c'])) {
        $cValues = $_GET['c'];
    } else {
        $cStr = $_GET['c'];
        if (strpos($cStr, '&c=') !== false) {
            $parts = explode('&c=', $cStr);
            foreach ($parts as $part) {
                $val = str_replace('&', '', $part);
                if ($val !== '') $cValues[] = $val;
            }
        } elseif (strpos($cStr, '&') !== false) {
            $parts = explode('&', $cStr);
            foreach ($parts as $part) {
                $val = str_replace('c=', '', $part);
                if ($val !== '') $cValues[] = $val;
            }
        } else {
            $cValues[] = $cStr;
        }
    }
}

$cValues = array_unique(array_filter($cValues));
foreach ($cValues as $val) {
    $params[] = 'c=' . urlencode($val);
}

// 处理其他参数
$otherParams = ['encode', 'charset', 'callback', 'select', 'min_length', 'max_length'];
foreach ($otherParams as $param) {
    if (isset($_GET[$param]) && $_GET[$param] !== '') {
        $params[] = $param . '=' . urlencode($_GET[$param]);
    }
}

// 构建查询字符串
$queryString = !empty($params) ? '?' . implode('&', $params) : '';

// ==================== 请求上游API（自动切换） ====================
$response = false;
$httpCode = 0;
$error = null;
$contentType = null;
$usedApi = null;

foreach ($apiList as $api) {
    $targetUrl = $api . $queryString;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Language: zh-CN,zh;q=0.9'
    ]);
    
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Hitokoto-Proxy/1.0)');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 成功则跳出循环
    if ($httpCode == 200 && $response !== false) {
        $usedApi = $api;
        break;
    }
    
    // 失败则继续尝试下一个
    $response = false;
}

// ==================== 返回机制优化 ====================
// 所有上游都失败
if ($response === false || $httpCode != 200) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'All upstream APIs failed',
        'code' => $httpCode,
        'message' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 获取encode参数判断返回类型
$encode = isset($_GET['encode']) ? strtolower($_GET['encode']) : 'json';

// 输出处理
if ($encode === 'js') {
    $callback = isset($_GET['callback']) ? $_GET['callback'] : null;
    
    if ($callback) {
        if (preg_match('/^' . preg_quote($callback, '/') . '\((.+)\);?$/s', $response, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if ($jsonData) {
                $prettyJson = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $response = $callback . '(' . $prettyJson . ');';
            }
        }
    }
    header('Content-Type: application/javascript; charset=utf-8');
} elseif ($encode === 'json' || empty($encode)) {
    $jsonData = json_decode($response, true);
    if ($jsonData) {
        $response = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    header('Content-Type: application/json; charset=utf-8');
} else {
    // 其他格式直接转发
    if ($contentType) {
        header('Content-Type: ' . $contentType);
    }
}

echo $response;