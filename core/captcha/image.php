<?php
/**
 * 文件：core/captcha/image.php
 * 作用：本地图形验证码 PNG
 */

define('VS_ROOT', dirname(dirname(__DIR__)));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireInstalled();

if (!class_exists('Captcha') || Captcha::mode() !== Captcha::MODE_LOCAL) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

CaptchaLocal::outputPng();
