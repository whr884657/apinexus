<?php
/**
 * 文件：user/ip.php
 * 作用：用户 IP 配置（白名单 + 自备出口代理）
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$ready = UserIpAllow::columnReady();
$proxyReady = class_exists('UserIpProxy') && UserIpProxy::tableReady() && UserIpProxy::strategyColumnReady();
$clientIp = AuthSecurity::clientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    // —— 白名单 ——
    if (in_array($action, array('list', 'add', 'remove'), true)) {
        if (!$ready) {
            AjaxResponse::error('IP 配置尚未就绪，请联系管理员完成系统升级');
        }
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
    }

    // —— 出口代理 ——
    if (in_array($action, array('proxy_list', 'proxy_save', 'proxy_delete', 'proxy_test', 'proxy_strategy'), true)) {
        if (!$proxyReady) {
            AjaxResponse::error('出口代理尚未就绪，请联系管理员完成系统升级');
        }
        if ($action === 'proxy_list') {
            $pack = UserIpProxy::listForUser($userId);
            if (empty($pack['ok'])) {
                AjaxResponse::error(isset($pack['msg']) ? $pack['msg'] : '读取失败');
            }
            AjaxResponse::success('ok', array(
                'list'     => isset($pack['list']) ? $pack['list'] : array(),
                'count'    => isset($pack['count']) ? (int) $pack['count'] : 0,
                'max'      => UserIpProxy::MAX_COUNT,
                'strategy' => isset($pack['strategy']) ? (int) $pack['strategy'] : 0,
            ));
        }
        if ($action === 'proxy_save') {
            $input = array(
                'id'       => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                'title'    => isset($_POST['title']) ? (string) $_POST['title'] : '',
                'mode'     => isset($_POST['mode']) ? (int) $_POST['mode'] : 0,
                'proto'    => isset($_POST['proto']) ? (int) $_POST['proto'] : 0,
                'host'     => isset($_POST['host']) ? (string) $_POST['host'] : '',
                'port'     => isset($_POST['port']) ? (int) $_POST['port'] : 0,
                'username' => isset($_POST['username']) ? (string) $_POST['username'] : '',
                'extract'  => isset($_POST['extract']) ? (string) $_POST['extract'] : '',
                'extfmt'   => isset($_POST['extfmt']) ? (int) $_POST['extfmt'] : 0,
                'status'   => isset($_POST['status']) ? (int) $_POST['status'] : 1,
                'sort'     => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            );
            if (array_key_exists('password', $_POST)) {
                $input['password'] = (string) $_POST['password'];
            }
            $result = UserIpProxy::save($userId, $input);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '保存失败');
            }
            AjaxResponse::success($result['msg'], array(
                'row'      => isset($result['row']) ? $result['row'] : null,
                'list'     => isset($result['list']) ? $result['list'] : array(),
                'count'    => isset($result['list']) ? count($result['list']) : 0,
                'max'      => UserIpProxy::MAX_COUNT,
                'strategy' => UserIpProxy::strategyForUser($userId),
            ));
        }
        if ($action === 'proxy_delete') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $result = UserIpProxy::delete($userId, $id);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '删除失败');
            }
            AjaxResponse::success($result['msg'], array(
                'list'  => isset($result['list']) ? $result['list'] : array(),
                'count' => isset($result['list']) ? count($result['list']) : 0,
                'max'   => UserIpProxy::MAX_COUNT,
            ));
        }
        if ($action === 'proxy_test') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $result = UserIpProxy::testConnectivity($userId, $id);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '测试失败');
            }
            AjaxResponse::success($result['msg'], array(
                'detail' => isset($result['detail']) ? $result['detail'] : null,
            ));
        }
        if ($action === 'proxy_strategy') {
            $strategy = isset($_POST['strategy']) ? (int) $_POST['strategy'] : 0;
            $result = UserIpProxy::saveStrategy($userId, $strategy);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '保存失败');
            }
            AjaxResponse::success($result['msg'], array(
                'strategy' => isset($result['strategy']) ? (int) $result['strategy'] : $strategy,
            ));
        }
    }

    AjaxResponse::error('无效操作', 400);
}

$list = $ready ? UserIpAllow::parseList(UserIpAllow::rawForUser($userId)) : array();
$proxyPack = $proxyReady ? UserIpProxy::listForUser($userId) : array('ok' => false, 'list' => array(), 'strategy' => 0);
$proxyList = (!empty($proxyPack['ok']) && isset($proxyPack['list']) && is_array($proxyPack['list']))
    ? $proxyPack['list']
    : array();
$proxyStrategy = $proxyReady ? UserIpProxy::strategyForUser($userId) : 0;

vs_user_render_page(
    'ip',
    'IP 配置',
    'ip',
    array(
        'ready'          => $ready,
        'proxyReady'     => $proxyReady,
        'list'           => $list,
        'clientIp'       => $clientIp,
        'maxCount'       => UserIpAllow::MAX_COUNT,
        'proxyList'      => $proxyList,
        'proxyMax'       => UserIpProxy::MAX_COUNT,
        'proxyStrategy'  => $proxyStrategy,
    ),
    '',
    ($ready || $proxyReady) ? array('user-ip.js') : array()
);
