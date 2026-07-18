<?php
/**
 * 文件：pay/return.php
 * 作用：码支付浏览器回跳（履约以 notify 为准）
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

vs_redirect(vs_base_url() . '/user/recharge');
