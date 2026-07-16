<?php
/**
 * 本地接口统计 · 一行引入入口（放在 api/ 目录下）
 *
 * 用法（脚本位于 api/子目录/xxx.php 时）：
 *   require_once dirname(__DIR__) . '/hit.php';
 *
 * 更深目录请把 dirname 层数加大，或改用「向上查找」三行写法（见 统计代码使用说明.md）。
 */
$d = dirname(__DIR__);
require_once $d . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (class_exists('ApiStats', false)) {
    ApiStats::hit();
}
