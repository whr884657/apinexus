<?php
/**
 * 文件：core/theme/default/api/sitemeta.php
 * 作用：默认主题 · 一键获取站点 TDK（POST + CSRF；逻辑在 core/LinkSiteMeta）
 *
 * 公网地址：{站点根}/core/theme/default/api/sitemeta.php
 */

$root = dirname(__DIR__, 4);
if (!is_file($root . '/core/bootstrap.php')) {
    $root = __DIR__;
    for ($i = 0; $i < 8 && !is_file($root . '/core/bootstrap.php'); $i++) {
        $root = dirname($root);
    }
}
require_once $root . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    AjaxResponse::error('系统未安装', 503);
}

vs_require_secure_post();

$url = isset($_POST['url']) ? (string) $_POST['url'] : (isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '');
$result = LinkSiteMeta::fetch($url);
if (empty($result['ok'])) {
    AjaxResponse::error(isset($result['msg']) ? (string) $result['msg'] : '获取失败');
}

AjaxResponse::success(isset($result['msg']) ? (string) $result['msg'] : '获取成功', array(
    'name'        => isset($result['name']) ? (string) $result['name'] : '',
    'description' => isset($result['description']) ? (string) $result['description'] : '',
    'icon'        => isset($result['icon']) ? (string) $result['icon'] : '',
    'siteurl'     => isset($result['siteurl']) ? (string) $result['siteurl'] : $url,
));
