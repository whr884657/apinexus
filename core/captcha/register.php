<?php
/**
 * 文件：core/captcha/register.php
 * 作用：极验 3 代初始化（官方 first_register + 频率限制）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!class_exists('Captcha') || !Captcha::sideUsesMode(Captcha::MODE_GT3) || !Captcha::credentialsReadyForMode(Captcha::MODE_GT3)) {
    echo json_encode(array(
        'success'     => 0,
        'gt'          => '',
        'challenge'   => '',
        'new_captcha' => true,
    ));
    exit;
}

$ip = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
if (class_exists('AuthSecurity')
    && !AuthSecurity::rateLimitAllow('captcha_gt3_reg_ip:' . $ip, 60, 20, true)
) {
    header('HTTP/1.1 429 Too Many Requests');
    echo json_encode(array(
        'success'     => 0,
        'gt'          => '',
        'challenge'   => '',
        'new_captcha' => true,
        'msg'         => '请求过于频繁',
    ));
    exit;
}

echo Captcha::registerGt3();
exit;
