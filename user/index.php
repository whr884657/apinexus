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

// 默认主题：按时段问候（仅 default）
$helloLine = '欢迎回来，' . $displayName;
$helloHint = '';
$themeId = class_exists('ThemeManager') ? ThemeManager::activeId() : 'default';
if ($themeId === 'default') {
    $hour = (int) date('G');
    if ($hour >= 0 && $hour < 5) {
        $helloLine = '夜深了，' . $displayName;
        $helloHint = '这么晚还在忙，注意休息，明天会更高效。';
    } elseif ($hour < 9) {
        $helloLine = '早上好，' . $displayName;
        $helloHint = '新的一天开始了，先处理最重要的事吧。';
    } elseif ($hour < 12) {
        $helloLine = '上午好，' . $displayName;
        $helloHint = '状态不错的话，趁上午把关键任务推进一下。';
    } elseif ($hour < 14) {
        $helloLine = '中午好，' . $displayName;
        $helloHint = '午饭后稍微缓一缓，下午再冲刺。';
    } elseif ($hour < 18) {
        $helloLine = '下午好，' . $displayName;
        $helloHint = '下午时光正好，欢迎回来继续打理接口。';
    } elseif ($hour < 22) {
        $helloLine = '晚上好，' . $displayName;
        $helloHint = '晚上好，今天辛苦了，慢慢来也很好。';
    } else {
        $helloLine = '夜深了，' . $displayName;
        $helloHint = '这么晚还在线，别忘了早点休息。';
    }
}

$scripts = array();
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
    ),
    '',
    $scripts
);
