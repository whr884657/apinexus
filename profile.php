<?php
/**
 * 文件：profile.php
 * 作用：开发者公开个人主页（对外 /profile/{id}）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

if (!InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/');
}

$userId = vs_resolve_path_id();
$profile = $userId > 0 ? FrontendContributor::findProfile($userId) : null;

if ($profile === null) {
    vs_render_404_page('用户不存在', '该用户不存在或暂无公开主页。');
}

$wallpaper = FrontendContributor::wallpaperUrl($profile);
$pageTitle = $profile['username'] . ' · 个人主页';
$desc = $profile['bio'] !== ''
    ? $profile['bio']
    : ($profile['username'] . ' 发布了 ' . (int) $profile['apicount'] . ' 个接口，累计调用 ' . $profile['calls_label'] . ' 次。');

vs_frontend_page('profile', $pageTitle, array(
    'profile'   => $profile,
    'wallpaper' => $wallpaper,
    'notFound'  => false,
    'pingUrl'   => rtrim(vs_base_url(), '/') . '/core/ping.php',
    'seo'       => vs_page_seo_pack($pageTitle, array(
        'description' => vs_seo_truncate($desc),
        'type'        => 'profile',
    )),
));
