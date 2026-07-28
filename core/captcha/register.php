<?php
/**
 * 文件：core/captcha/register.php
 * 作用：极验 3 代初始化（官方 first_register）
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!class_exists('Captcha') || Captcha::mode() !== Captcha::MODE_GT3 || !Captcha::credentialsReady()) {
    echo json_encode(array(
        'success'     => 0,
        'gt'          => '',
        'challenge'   => '',
        'new_captcha' => true,
    ));
    exit;
}

echo Captcha::registerGt3();
exit;
