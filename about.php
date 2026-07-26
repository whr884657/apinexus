<?php
/**
 * 文件：about.php
 * 作用：前台 · 关于（内容由绑定文章驱动）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$aboutArticle = class_exists('FrontendAbout') ? FrontendAbout::getBoundArticle() : null;
$seoDesc = '关于 ' . SiteContext::siteName() . ' 平台介绍、版本信息与联系方式。';
if (is_array($aboutArticle) && !empty($aboutArticle['summary'])) {
    $seoDesc = (string) $aboutArticle['summary'];
} elseif (is_array($aboutArticle) && !empty($aboutArticle['title'])) {
    $seoDesc = (string) $aboutArticle['title'];
}

$seo = vs_page_seo_pack('关于', array(
    'description' => vs_seo_truncate($seoDesc),
));

vs_frontend_page('about', '关于', array(
    'seo'           => $seo,
    'aboutArticle'  => $aboutArticle,
));
