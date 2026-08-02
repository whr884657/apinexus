<?php
/**
 * 文件：404.php
 * 作用：全站 404 错误页（含网络安全法律提示）
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';

vs_render_404_page();
