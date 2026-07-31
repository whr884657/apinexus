<?php
/**
 * 文件：user/index.php
 * 作用：用户中心控制台路由（POST/取数）；视图在各主题 user/pages/dashboard.php
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'checkin') {
        $result = FrontendUser::doCheckin();
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '签到失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : '签到成功',
            array(
                'amount' => isset($result['amount']) ? $result['amount'] : 0,
                'points' => isset($result['points']) ? $result['points'] : '',
                'banner' => FrontendUser::checkinBanner(),
                'stats'  => FrontendUser::dashboardStats(),
            )
        );
    }
    AjaxResponse::error('无效操作', 400);
}

$avatarPreview = UserAvatar::resolve($vsUser);
$checkinBanner = class_exists('FrontendUser')
    ? FrontendUser::checkinBanner()
    : array('show_banner' => false);
$dash = FrontendUser::dashboardStats();
$displayName = $vsUser ? (string) $vsUser['username'] : '用户';
$isDeveloper = !empty($dash['can_publish_api']);

// 双主题：2 小时时段 + 文案池随机（core/UserDashHello）
$hello = class_exists('UserDashHello')
    ? UserDashHello::pick($displayName)
    : array('hello' => '欢迎回来，' . $displayName, 'hint' => '', 'slot' => '', 'hour' => (int) date('G'));
$helloLine = isset($hello['hello']) ? (string) $hello['hello'] : ('欢迎回来，' . $displayName);
$helloHint = isset($hello['hint']) ? (string) $hello['hint'] : '';
$helloSlot = isset($hello['slot']) ? (string) $hello['slot'] : '';

$scripts = array('user-dash-hello.js');
if (!empty($checkinBanner['show_banner'])) {
    $scripts[] = 'user-checkin.js';
}

vs_user_render_page(
    'dashboard',
    '控制台',
    'dashboard',
    array(
        'dash'           => $dash,
        'checkinBanner'  => $checkinBanner,
        'avatarPreview'  => $avatarPreview,
        'displayName'    => $displayName,
        'isDeveloper'    => $isDeveloper,
        'helloLine'      => $helloLine,
        'helloHint'      => $helloHint,
        'helloSlot'      => $helloSlot,
    ),
    '',
    $scripts
);
