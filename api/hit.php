<?php
/**
 * 本地接口统计 · 兼容入口（已不推荐）
 *
 * 请改用「bootstrap + ApiStats::hit(接口ID)」两行写法，见 api/统计代码使用说明.md。
 * 本文件无法得知接口 ID，调用后不会记账。
 */
$d = dirname(__DIR__);
require_once $d . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap.php';
// 故意不调用 ApiStats::hit()：无接口 ID 无法认人
