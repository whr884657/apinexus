<?php
/**
 * 文件：core/captcha/image.php
 * 作用：本地图形验证码 PNG（含频率限制）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

if (!class_exists('Captcha') || Captcha::mode() !== Captcha::MODE_LOCAL) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$ip = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
$sid = (session_status() === PHP_SESSION_ACTIVE) ? session_id() : 'nosess';
if (class_exists('AuthSecurity')) {
    // 每分钟：同 IP 30 次；同会话 20 次
    if (!AuthSecurity::rateLimitAllow('captcha_img_ip:' . $ip, 60, 30, true)
        || !AuthSecurity::rateLimitAllow('captcha_img_sid:' . $sid, 60, 20, true)
    ) {
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: 60');
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Too many requests';
        exit;
    }
}

$scene = isset($_GET['scene']) ? (string) $_GET['scene'] : '';
$scene = preg_replace('/[^a-z0-9_]/', '', strtolower($scene));
if ($scene === '' || !in_array($scene, Captcha::scenes(), true)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid scene';
    exit;
}

CaptchaLocal::outputPng($scene);
