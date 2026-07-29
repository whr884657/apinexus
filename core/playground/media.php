<?php
/**
 * 文件：core/playground/media.php
 * 作用：在线测试媒体预览（短时落盘文件，同源播放 video/img/audio）
 */

if (!defined('VS_ROOT')) {
    define('VS_ROOT', dirname(dirname(__DIR__)));
}
require_once VS_ROOT . '/core/bootstrap.php';

$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/i', '', (string) $_GET['t']) : '';
if ($token === '' || strlen($token) < 16) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'not found';
    exit;
}

$dir = VS_ROOT . '/data/playground';
$binPath = $dir . '/' . $token . '.bin';
$metaPath = $dir . '/' . $token . '.json';
if (!is_file($binPath) || !is_file($metaPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'expired';
    exit;
}

$metaRaw = @file_get_contents($metaPath);
$meta = is_string($metaRaw) ? json_decode($metaRaw, true) : null;
$expires = (is_array($meta) && isset($meta['expires'])) ? (int) $meta['expires'] : 0;
if ($expires > 0 && $expires < time()) {
    @unlink($binPath);
    @unlink($metaPath);
    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'expired';
    exit;
}

$ct = (is_array($meta) && !empty($meta['ct'])) ? (string) $meta['ct'] : 'application/octet-stream';
$ctLower = strtolower(trim(explode(';', $ct, 2)[0]));
$allowCt = array(
    'image/jpeg' => true,
    'image/png'  => true,
    'image/gif'  => true,
    'image/webp' => true,
    'audio/mpeg' => true,
    'audio/mp3'  => true,
    'audio/wav'  => true,
    'audio/ogg'  => true,
    'audio/mp4'  => true,
    'audio/webm' => true,
    'video/mp4'  => true,
    'video/webm' => true,
    'video/mpeg' => true,
);
if (!isset($allowCt[$ctLower])) {
    $ct = 'application/octet-stream';
} else {
    $ct = $ctLower;
}
$size = filesize($binPath);
header('Content-Type: ' . $ct);
header('Content-Length: ' . (string) (int) $size);
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
header('Content-Security-Policy: default-src \'none\'; sandbox');
readfile($binPath);
exit;
