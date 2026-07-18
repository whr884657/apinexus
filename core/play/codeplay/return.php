<?php
/**
 * 文件：core/play/codeplay/return.php
 * 作用：码支付浏览器回跳（履约以 notify 为准）
 */

if (!defined('VS_ROOT')) {
    define('VS_ROOT', dirname(dirname(dirname(__DIR__))));
}
require_once VS_ROOT . '/core/bootstrap.php';

vs_redirect(vs_base_url() . '/user/recharge');
