<?php
/**
 * 演示包：一言代理接口（目录 api/demo，勿与业务接口重名）
 * 完全兼容 hitokoto.cn 官方API，纯转发不做任何修改
 * 官方文档: https://developer.hitokoto.cn/sentence/
 *
 * 注意：本文件仅为演示/联调。自写本地接口的安全要求见：
 *   api/接口开发安全须知.md
 * （含 JSONP callback 校验、禁止泄露后台路径与凭证等）
 */

// —— 调用统计（须填本接口在后台的数字 ID；详见 api/统计代码使用说明.md）——
require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
$demoApiId = 0;
if (class_exists('Config')) {
    $demoApiId = (int) Config::get('demo_aword_api_id', '0');
}
ApiStats::hit($demoApiId > 0 ? $demoApiId : 0);


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
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    vs_curl_ssl_verify($ch);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $outHeaders = ApiStats::outboundHeaders();
    $outHeaders[] = 'Accept-Language: zh-CN,zh;q=0.9';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $outHeaders);
    curl_setopt($ch, CURLOPT_USERAGENT, ApiStats::outboundUa());
    // 调用方传 vsproxy（短码/a/b/c/1）且用户中心已配置出口代理时注入（平台不提供免费节点）
    ApiStats::applyOutboundProxy($ch);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    vs_curl_close($ch);
    
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
    echo json_encode(array(
        'error' => 'All upstream APIs failed',
        'code' => $httpCode,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$encode = isset($_GET['encode']) ? strtolower((string) $_GET['encode']) : 'json';
header('X-Content-Type-Options: nosniff');

if ($encode === 'js') {
    $jsonData = json_decode((string) $response, true);
    if (!is_array($jsonData) && preg_match('/^[A-Za-z_$][A-Za-z0-9_$]{0,63}\((.+)\);?$/s', (string) $response, $m)) {
        $jsonData = json_decode($m[1], true);
    }
    $prettyJson = is_array($jsonData)
        ? json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        : (string) $response;
    $rawCb = isset($_GET['callback']) ? $_GET['callback'] : '';
    $wrapped = JsonpGuard::wrapJsonIfSafe($prettyJson, $rawCb);
    header('Content-Type: ' . $wrapped['contentType']);
    echo $wrapped['body'];
    exit;
}

if ($encode === 'json' || $encode === '') {
    $jsonData = json_decode((string) $response, true);
    if (is_array($jsonData)) {
        $response = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
    exit;
}

$ct = is_string($contentType) ? strtolower(trim(explode(';', $contentType, 2)[0])) : '';
$allowCt = array('application/json', 'application/javascript', 'text/plain');
if (!in_array($ct, $allowCt, true)) {
    $ct = 'text/plain';
}
header('Content-Type: ' . $ct . '; charset=utf-8');
echo $response;