<?php
/**
 * 一次性维护脚本：从 svg图标.txt 同步内置分类图标
 * 用法：php install/sync-category-icons.php
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

$txtPath = VS_ROOT . '/svg图标.txt';
$result = CategoryIconImporter::importFromTxt($txtPath);

echo '写入 ' . count($result['written']) . " 个图标\n";
foreach ($result['written'] as $path) {
    echo '  ' . $path . "\n";
}
if (!empty($result['errors'])) {
    echo "错误：\n";
    foreach ($result['errors'] as $err) {
        echo '  ' . $err . "\n";
    }
    exit(1);
}
exit(0);
