<?php
/**
 * 文件：articles.php
 * 作用：前台 · 文章列表 / 详情；POST 提交文章评论
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'submit_comment') {
        AjaxResponse::error('无效操作', 400);
    }

    if (!FrontendComment::tableReady()) {
        AjaxResponse::error('评论功能尚未就绪');
    }

    $captchaErr = Captcha::requireValid(Captcha::SCENE_COMMENT, $_POST);
    // requireValid 成功返回 true，失败返回文案字符串（对齐 login.php；禁止用 !== null）
    if ($captchaErr !== true) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => $captchaErr,
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_COMMENT),
        ));
    }

    $ticket = isset($_POST['submit_ticket']) ? (string) $_POST['submit_ticket'] : '';
    if (!AuthSecurity::validateAndConsumeSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_COMMENT, $ticket)) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => '提交凭证已失效，请刷新页面后重试',
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_COMMENT),
        ));
    }

    $result = FrontendComment::submit(
        isset($_POST['contentid']) ? (int) $_POST['contentid'] : 0,
        isset($_POST['email']) ? (string) $_POST['email'] : '',
        isset($_POST['body']) ? (string) $_POST['body'] : '',
        isset($_POST['nickname']) ? (string) $_POST['nickname'] : '',
        isset($_POST['website']) ? (string) $_POST['website'] : '',
        isset($_POST['parentid']) ? (int) $_POST['parentid'] : 0
    );

    if (!is_array($result)) {
        AjaxResponse::json(array(
            'code'          => 0,
            'msg'           => is_string($result) ? $result : '评论失败',
            'submit_ticket' => AuthSecurity::issueSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_COMMENT),
        ));
    }

    AjaxResponse::success(
        '评论已发布',
        AuthSecurity::withSubmitTicket(AuthSecurity::SUBMIT_PURPOSE_COMMENT, array(
            'comment' => $result,
        ))
    );
}

$articleId = vs_resolve_path_id('id');
$pageTitle = '文章';
$seo = vs_page_seo_pack('文章', array(
    'description' => vs_seo_truncate(SiteContext::siteName() . ' 技术文章与平台动态。'),
));

if ($articleId > 0) {
    $article = FrontendArticle::findById($articleId, true);
    if (is_array($article) && !empty($article['title'])) {
        $pageTitle = (string) $article['title'];
        $sum = isset($article['summary']) ? trim((string) $article['summary']) : '';
        $seo = vs_page_seo_pack($pageTitle, array(
            'description' => vs_seo_truncate($sum !== '' ? $sum : ($pageTitle . ' · ' . SiteContext::siteName())),
            'type'        => 'article',
        ));
    }
}

vs_frontend_page('articles', $pageTitle, array(
    'seo' => $seo,
));
