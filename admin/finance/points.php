<?php
/**
 * 文件：admin/finance/points.php
 * 作用：积分变动入口（与订单管理同一数据源）
 */

require_once dirname(__DIR__) . '/init.php';

vs_redirect(vs_base_url() . '/admin/finance/orders');
