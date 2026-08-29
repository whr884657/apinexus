<?php
/**
 * 文件：user/ip.php
 * 作用：用户 IP 配置（白名单；代理占位）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$ready = UserIpAllow::columnReady();
$clientIp = AuthSecurity::clientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    if (!$ready) {
        AjaxResponse::error('IP 配置尚未就绪，请联系管理员完成系统升级');
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'list') {
        $list = UserIpAllow::parseList(UserIpAllow::rawForUser($userId));
        AjaxResponse::success('ok', array(
            'list'      => $list,
            'count'     => count($list),
            'max'       => UserIpAllow::MAX_COUNT,
            'client_ip' => $clientIp,
        ));
    }

    if ($action === 'add') {
        $ip = isset($_POST['ip']) ? (string) $_POST['ip'] : '';
        $result = UserIpAllow::addIp($userId, $ip);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '添加失败');
        }
        AjaxResponse::success($result['msg'], array(
            'list'  => isset($result['list']) ? $result['list'] : array(),
            'count' => isset($result['list']) ? count($result['list']) : 0,
            'max'   => UserIpAllow::MAX_COUNT,
        ));
    }

    if ($action === 'remove') {
        $ip = isset($_POST['ip']) ? (string) $_POST['ip'] : '';
        $result = UserIpAllow::removeIp($userId, $ip);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '移除失败');
        }
        AjaxResponse::success($result['msg'], array(
            'list'  => isset($result['list']) ? $result['list'] : array(),
            'count' => isset($result['list']) ? count($result['list']) : 0,
            'max'   => UserIpAllow::MAX_COUNT,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$list = $ready ? UserIpAllow::parseList(UserIpAllow::rawForUser($userId)) : array();

vs_user_render_page(
    'ip',
    'IP 配置',
    'ip',
    array(
        'ready'    => $ready,
        'list'     => $list,
        'clientIp' => $clientIp,
        'maxCount' => UserIpAllow::MAX_COUNT,
    ),
    '',
    $ready ? array('user-ip.js') : array()
);
