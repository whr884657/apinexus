<?php
/**
 * 个人调用 / 积分只读查询（本地接口）
 *
 * 路径：/api/index.php
 * 后台挂载本地接口后，把下方 $vsUserStatsApiId 改成真实接口 ID；须密钥=必须。
 * 未改 ID 时接口直接返回「接口未配置」，不会跳过平台守卫。
 *
 * 请求参数尽量少：key（必填）+ 可选 q（字母 a～i 与数字 1～9 等价；all / 0 = 全部）
 *   只带 key / q=0 / q=all → 全部
 *   q=a 或 q=1     → 累计调用
 *   q=ac 或 q=13   → 累计调用+积分余额
 *   q=hi 或 q=89   → 今日排行+近7日排行
 *   对照：a/1调用 b/2消耗 c/3余额 d/4今日调用 e/5今日消耗 f/6近7日调用 g/7近7日消耗 h/8今日排行 i/9近7日排行
 *
 * 成功：{"code":1,"msg":"ok","data":{…}}
 * 失败：{"code":0,"msg":"…","errcode":11001}
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

/**
 * @param int        $code
 * @param string     $msg
 * @param array|null $data
 * @param int|null   $errcode
 * @return void
 */
function vs_user_stats_exit($code, $msg, $data = null, $errcode = null)
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    $out = array(
        'code' => (int) $code,
        'msg'  => (string) $msg,
    );
    if ($errcode !== null) {
        $out['errcode'] = (int) $errcode;
    }
    if ($data !== null) {
        $out['data'] = $data;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// —— 调用统计（须填本接口在后台的数字 ID；详见 api/统计代码使用说明.md）——
// 敏感只读接口：未改真实 ID 时直接拒绝，避免 hit(0) 跳过禁用/QPM/密钥通道
$vsUserStatsApiId = 0; // ← 改成后台「接口管理」里本接口的真实 ID
if ($vsUserStatsApiId <= 0) {
    vs_user_stats_exit(0, '接口未配置', null, ApiError::UNAVAILABLE);
}
// 填了正数但后台无此接口时，hit 会静默跳过守卫；此处先校验存在再放行
if (!class_exists('ApiManager') || !ApiManager::tableReady() || !ApiManager::findById($vsUserStatsApiId)) {
    vs_user_stats_exit(0, '接口未配置', null, ApiError::UNAVAILABLE);
}
ApiStats::hit($vsUserStatsApiId);

// 含积分余额：勿对任意站点放行；浏览器跨域请自建同源代理或改白名单
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-API-Key, Authorization, X-Authorization, X-Api-Bearer');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');

if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$userId = 0;
$ctx = ApiStats::keyContext();
if (!empty($ctx['valid']) && (int) $ctx['userid'] > 0) {
    $userId = (int) $ctx['userid'];
} else {
    // hit 已要求密钥时通常不会走到这里；仅作兜底认人（仍禁止 Cookie）
    $resolved = UserCallStats::resolveUserFromRequest();
    if (empty($resolved['ok'])) {
        $err = isset($resolved['errcode']) ? (int) $resolved['errcode'] : ApiError::NO_KEY;
        $msg = isset($resolved['msg']) ? (string) $resolved['msg'] : '请提供调用密钥';
        if (function_exists('vs_api_error_exit')) {
            vs_api_error_exit($err, $msg);
        }
        vs_user_stats_exit(0, $msg, null, $err);
    }
    $userId = (int) $resolved['userid'];
}

$parsed = UserCallStats::parseFromRequest();
if (empty($parsed['ok'])) {
    vs_user_stats_exit(0, '不支持的查询字段');
}

$fields = isset($parsed['fields']) ? $parsed['fields'] : UserCallStats::allFieldKeys();
$data = UserCallStats::query($userId, $fields);
vs_user_stats_exit(1, 'ok', $data);
