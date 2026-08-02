<?php
/**
 * 文件：404.php
 * 作用：全站 404 错误页入口（Nginx/Apache ErrorDocument 与业务层共用 vs_render_404_page）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

vs_render_404_page();
