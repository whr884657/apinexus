<?php
/**
 * 文件：user/points.php
 * 作用：用户积分变动
 */

require_once __DIR__ . '/init.php';

$userId = (int) UserAuth::id();
$ready = OrderManager::tableReady() && PointsManager::hasPointsColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    if (!$ready) {
        AjaxResponse::error('积分系统未就绪');
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'list') {
        AjaxResponse::error('无效操作', 400);
    }
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $pagesize = isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20;
    $beforeId = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
    $data = OrderManager::listPaged(array(
        'userid'    => $userId,
        'page'      => $page,
        'pagesize'  => $pagesize,
        'scope'     => 'ledger',
        'before_id' => $beforeId,
    ));
    $data['balance'] = PayConfig::fmtPoints(PointsManager::balance($userId));
    AjaxResponse::success('ok', $data);
}

$balance = $ready ? PointsManager::balance($userId) : 0;

vs_user_render_page(
    'points',
    '积分变动',
    'points',
    array(
        'ready'   => $ready,
        'balance' => $balance,
    ),
    '',
    $ready ? array('user-points.js') : array()
);
