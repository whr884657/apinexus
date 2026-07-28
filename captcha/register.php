<?php
/**
 * 文件：captcha/register.php
 * 作用：极验 3 代前端初始化（register）接口
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!class_exists('Captcha') || Captcha::version() !== Captcha::VERSION_3 || !Captcha::credentialsReady()) {
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
