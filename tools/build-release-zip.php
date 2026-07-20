<?php
/**
 * 用 PHP ZipArchive 打包发行 ZIP（供 pack-release.ps1 调用）
 * 避免 Compress-Archive 产物在 Linux/PHP 解压时个别文件无法读取。
 *
 * 用法：php tools/build-release-zip.php <源目录> <输出zip> <排除目录名逗号分隔>
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/build-release-zip.php <srcDir> <outZip> [excludeCsv]\n");
    exit(1);
}

$srcDir = realpath($argv[1]);
$outZip = $argv[2];
$excludeCsv = isset($argv[3]) ? $argv[3] : '';

if ($srcDir === false || !is_dir($srcDir)) {
    fwrite(STDERR, "Invalid srcDir\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension required\n");
    exit(1);
}

$exclude = array();
foreach (explode(',', $excludeCsv) as $name) {
    $name = trim($name);
    if ($name !== '') {
        $exclude[$name] = true;
    }
}

// 根目录名含「参考」一律排除
$cankao = "\xE5\x8F\x82\xE8\x80\x83"; // UTF-8 参考
foreach (scandir($srcDir) as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    if (is_dir($srcDir . DIRECTORY_SEPARATOR . $entry) && strpos($entry, $cankao) !== false) {
        $exclude[$entry] = true;
    }
}

if (is_file($outZip)) {
    @unlink($outZip);
}
$outDir = dirname($outZip);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$zip = new ZipArchive();
if ($zip->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create zip\n");
    exit(1);
}

$srcDir = str_replace('\\', '/', $srcDir);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ($iterator as $item) {
    $full = str_replace('\\', '/', $item->getPathname());
    $rel = ltrim(substr($full, strlen($srcDir)), '/');
    if ($rel === '') {
        continue;
    }

    $top = $rel;
    $slash = strpos($rel, '/');
    if ($slash !== false) {
        $top = substr($rel, 0, $slash);
    }
    if (isset($exclude[$top])) {
        continue;
    }
    if (substr($rel, -4) === '.zip') {
        continue;
    }

    if ($item->isDir()) {
        $zip->addEmptyDir($rel);
        continue;
    }

    if (!$item->isFile() || !is_readable($full)) {
        fwrite(STDERR, "Unreadable: {$rel}\n");
        $zip->close();
        @unlink($outZip);
        exit(1);
    }

    // 读入后写入 ZIP（避免 Windows addFile 延迟写入导致个别条目损坏）
    $raw = @file_get_contents($full);
    if ($raw === false) {
        fwrite(STDERR, "read failed: {$rel}\n");
        $zip->close();
        @unlink($outZip);
        exit(1);
    }
    if (!$zip->addFromString($rel, $raw)) {
        fwrite(STDERR, "addFromString failed: {$rel}\n");
        $zip->close();
        @unlink($outZip);
        exit(1);
    }
    $count++;
}

$zip->close();

// 自检：关键路径必须可读
$check = new ZipArchive();
if ($check->open($outZip) !== true) {
    fwrite(STDERR, "Reopen zip failed\n");
    exit(1);
}
$must = array(
    'core/version.php',
    'core/Updater.php',
    'core/theme/slate/user/auth/register.php',
    'core/theme/default/user/auth/register.php',
    'update.json',
);
foreach ($must as $path) {
    $stat = $check->statName($path);
    if ($stat === false || (int) $stat['size'] <= 0) {
        fwrite(STDERR, "Missing or empty in zip: {$path}\n");
        $check->close();
        exit(1);
    }
    $data = $check->getFromName($path);
    if ($data === false || $data === '') {
        fwrite(STDERR, "Cannot read from zip: {$path}\n");
        $check->close();
        exit(1);
    }
}
// 禁止参考目录
for ($i = 0; $i < $check->numFiles; $i++) {
    $name = $check->getNameIndex($i);
    if ($name === false) {
        continue;
    }
    if (strpos($name, $cankao) !== false) {
        fwrite(STDERR, "Forbidden path in zip: {$name}\n");
        $check->close();
        exit(1);
    }
}
$check->close();

echo 'OK files=' . $count . ' size=' . filesize($outZip) . PHP_EOL;
exit(0);
