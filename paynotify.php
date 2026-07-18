<?php
/**
 * 文件：paynotify.php
 * 作用：码支付异步通知公网入口（带 .php，不依赖伪静态）
 *
 * 下单时作为 notify_url 传给码支付；逻辑在 core/play/codeplay/notify.php。
 */

define('VS_ROOT', __DIR__);
require VS_ROOT . '/core/play/codeplay/notify.php';
