<?php
/**
 * 文件：core/api/apiquery.php
 * 作用：系统级接口查询 API（详情 / 搜索 / 随机；脱敏前台可见信息）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 失败码：12xxx（SystemApiError）
 *
 * 鉴权：系统密钥（Bearer / X-Api-Key / POST·JSON / Query key|apikey（三态））
 * 开关：sysapion.apiquery（闸关与鉴权失败统一 UNAUTH）
 *
 * action：
 *   get     必填其一：id / name / slug（代理短码）→ 详情
 *   search  必填 q；可选 limit（1～50）→ 摘要列表
 *   random  无业务参数 → 随机一条详情
 * 不传 action：有 id/name/slug → get；有 q → search；否则 → random
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!InstallChecker::isInstalled()) {
    SystemApiKey::fail(SystemApiError::NOT_INSTALLED, '系统未安装');
}

SystemApiKey::requireAuthorizedGate('apiquery');

$input = SystemApiKey::mergeInput();

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
$id = isset($input['id']) ? (int) $input['id'] : 0;
$name = isset($input['name']) ? trim((string) $input['name']) : '';
$slug = isset($input['slug']) ? trim((string) $input['slug']) : '';
if ($slug === '' && isset($input['proxyslug'])) {
    $slug = trim((string) $input['proxyslug']);
}
$q = isset($input['q']) ? trim((string) $input['q']) : '';

if ($action === '') {
    if ($id > 0 || $name !== '' || $slug !== '') {
        $action = 'get';
    } elseif ($q !== '') {
        $action = 'search';
    } else {
        $action = 'random';
    }
}

if ($action === 'get') {
    $result = SystemApiPublic::queryOne($input);
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '查询失败';
        if (strpos($msg, '未就绪') !== false) {
            SystemApiKey::fail(SystemApiError::NOT_READY, $msg);
        }
        if (strpos($msg, '不存在') !== false) {
            SystemApiKey::fail(SystemApiError::NOT_FOUND, $msg);
        }
        SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg);
    }
    SystemApiKey::ok('ok', array(
        'api' => isset($result['api']) ? $result['api'] : array(),
    ));
}

if ($action === 'search') {
    $limit = isset($input['limit']) ? (int) $input['limit'] : 20;
    $result = SystemApiPublic::search($q, $limit);
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '搜索失败';
        if (strpos($msg, '未就绪') !== false) {
            SystemApiKey::fail(SystemApiError::NOT_READY, $msg);
        }
        SystemApiKey::fail(SystemApiError::BAD_PARAM, $msg);
    }
    SystemApiKey::ok('ok', array(
        'total' => isset($result['total']) ? (int) $result['total'] : 0,
        'list'  => isset($result['list']) ? $result['list'] : array(),
    ));
}

if ($action === 'random') {
    $result = SystemApiPublic::randomOne();
    if (empty($result['ok'])) {
        $msg = isset($result['msg']) ? (string) $result['msg'] : '随机查询失败';
        if (strpos($msg, '未就绪') !== false) {
            SystemApiKey::fail(SystemApiError::NOT_READY, $msg);
        }
        if (strpos($msg, '暂无') !== false) {
            SystemApiKey::fail(SystemApiError::NOT_FOUND, $msg);
        }
        SystemApiKey::fail(SystemApiError::BUSINESS, $msg);
    }
    SystemApiKey::ok('ok', array(
        'api' => isset($result['api']) ? $result['api'] : array(),
    ));
}

SystemApiKey::fail(SystemApiError::BAD_PARAM, '不支持的 action（get / search / random）');
