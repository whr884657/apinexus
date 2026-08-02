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

    $result = FrontendComment::submit(
        isset($_POST['contentid']) ? (int) $_POST['contentid'] : 0,
        isset($_POST['email']) ? (string) $_POST['email'] : '',
        isset($_POST['body']) ? (string) $_POST['body'] : '',
        isset($_POST['nickname']) ? (string) $_POST['nickname'] : '',
        isset($_POST['website']) ? (string) $_POST['website'] : '',
        isset($_POST['parentid']) ? (int) $_POST['parentid'] : 0
    );

    if (!is_array($result)) {
        AjaxResponse::error($result);
    }

    AjaxResponse::success('评论已发布', array(
        'comment' => $result,
    ));
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
