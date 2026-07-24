<?php
/**
 * 文件：detail.php
 * 作用：接口详情页入口（对外 /detail/{id}，伪静态落到本脚本 ?id=）；支持 POST 提交接口反馈
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'submit_feedback') {
        AjaxResponse::error('无效操作', 400);
    }

    if (!class_exists('UserAuth') || !UserAuth::check()) {
        AjaxResponse::json(array(
            'code'     => 0,
            'msg'      => '请登录后提交反馈问题',
            'need_login' => 1,
            'csrf'     => AuthSecurity::csrfToken(),
        ), 401);
    }

    $apiIdPost = isset($_POST['apiid']) ? (int) $_POST['apiid'] : 0;
    $content = isset($_POST['content']) ? (string) $_POST['content'] : '';
    $result = FrontendFeedback::submit($apiIdPost, $content);
    if (!is_array($result)) {
        AjaxResponse::error($result);
    }

    if (class_exists('FeedbackNotify')) {
        FeedbackNotify::notifyAdminsPending($result);
    }

    AjaxResponse::success('反馈已提交，我们会尽快处理', array(
        'feedback' => array(
            'id'     => isset($result['id']) ? (int) $result['id'] : 0,
            'status' => isset($result['status_label']) ? (string) $result['status_label'] : '待处理',
        ),
    ));
}

$apiId = vs_resolve_path_id();
$api = $apiId > 0 ? FrontendApi::findForThemeById($apiId) : null;
$playground = vs_playground_session_context();
$detailPath = $apiId > 0 ? ('/detail/' . $apiId) : '/detail';
$loginWithRedirect = rtrim(vs_base_url(), '/') . '/user/login?redirect=' . rawurlencode($detailPath);
if (is_array($playground)) {
    $playground['loginUrl'] = $loginWithRedirect;
    $playground['feedbackReady'] = FrontendFeedback::tableReady();
}

if ($api === null) {
    http_response_code(404);
    vs_frontend_page('detail', '接口不存在', array(
        'api'         => null,
        'apiId'       => $apiId,
        'notFound'    => true,
        'playground'  => $playground,
        'seo' => array(
            'description' => '该接口不存在、未通过审核或已下架。',
            'robots'      => 'noindex,follow',
        ),
    ));
    exit;
}

$pageTitle = isset($api['name']) ? ((string) $api['name'] . ' · 接口详情') : '接口详情';
$apiDesc = isset($api['desc']) ? trim((string) $api['desc']) : '';
vs_frontend_page('detail', $pageTitle, array(
    'api'        => $api,
    'apiId'      => $apiId,
    'notFound'   => false,
    'playground' => $playground,
    'seo' => array(
        'description' => vs_seo_truncate($apiDesc !== '' ? $apiDesc : ($api['name'] . ' - 接口详情与在线测试')),
        'type'        => 'article',
    ),
));
