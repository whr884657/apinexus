<?php
/**
 * 文件：payreturn.php
 * 作用：码支付浏览器回跳公网入口（带 .php，不依赖伪静态）
 *
 * 履约以异步 notify 为准；本页仅跳转充值中心。
 */

define('VS_ROOT', __DIR__);
require VS_ROOT . '/core/play/codeplay/return.php';
