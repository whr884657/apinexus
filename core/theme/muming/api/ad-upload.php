<?php
/**
 * 文件：core/theme/muming/api/ad-upload.php
 * 作用：主题6 · 首页广告图片上传（仅管理员；POST + CSRF + 文件校验）
 *
 * 公网地址：{站点根}/core/theme/muming/api/ad-upload.php
 * 约定：上传结果写入主题目录 assets/img/ad/，返回站内相对 URL；
 *       管理员在「后台 → 主题设置 → 首页广告位」点击「上传图片」调用。
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
/*
// 仅管理员可上传（后台专用操作）
if (!class_exists('Auth') || !Auth::check()) {
    AjaxResponse::error('无权限操作', 403);
}
*/
// 频控：防持 CSRF 会话批量上传
$adIp = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
if (!AuthSecurity::rateLimitAllow('theme_ad_upload_ip:' . $adIp, 20, 60, true)) {
    AjaxResponse::error('请求过于频繁，请稍后再试', 429);
}

if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
    AjaxResponse::error('未选择文件');
}

$file = $_FILES['file'];
if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
    $errMap = array(
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器上传限制',
        UPLOAD_ERR_FORM_SIZE  => '文件超过大小限制',
        UPLOAD_ERR_PARTIAL    => '文件上传不完整',
        UPLOAD_ERR_NO_FILE    => '未选择文件',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '服务器无法写入文件',
    );
    $errCode = (int) $file['error'];
    AjaxResponse::error(isset($errMap[$errCode]) ? $errMap[$errCode] : '上传失败');
}

if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_file($file['tmp_name'])) {
    AjaxResponse::error('上传文件无效');
}

$maxBytes = 2 * 1024 * 1024; // 2MB
$size = (int) $file['size'];
if ($size <= 0 || $size > $maxBytes) {
    AjaxResponse::error('图片大小需在 2MB 以内');
}

$info = @getimagesize($file['tmp_name']);
if ($info === false || empty($info['mime'])) {
    AjaxResponse::error('仅支持 jpg / png / gif / webp 图片');
}

$allowedExt = array(
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
);
$mime = strtolower((string) $info['mime']);
$ext = array_search($mime, $allowedExt, true);
if ($ext === false) {
    AjaxResponse::error('仅支持 jpg / png / gif / webp 图片');
}

// 随机文件名：防路径穿越、防覆盖
$name = 'ad_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $ext;

$targetDir = dirname(__DIR__) . '/assets/img/ad';
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}
if (!is_dir($targetDir) || !is_writable($targetDir)) {
    AjaxResponse::error('主题图片目录不可写，请检查目录权限');
}

$targetFile = $targetDir . '/' . $name;
if (!@move_uploaded_file($file['tmp_name'], $targetFile)) {
    AjaxResponse::error('保存文件失败，请重试');
}

$url = function_exists('vs_site_path')
    ? vs_site_path('core/theme/muming/assets/img/ad/' . $name)
    : '/core/theme/muming/assets/img/ad/' . $name;

AjaxResponse::success('上传成功', array('url' => $url));
