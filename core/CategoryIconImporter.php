<?php
/**
 * 文件：core/CategoryIconImporter.php
 * 作用：从 svg图标.txt 解析并写入 assets/img/category-icons/
 */

class CategoryIconImporter
{
    /**
     * 中文名称 → 文件名（不含扩展名）；重复名称用 -alt
     *
     * @return array<string, string>
     */
    public static function slugMap()
    {
        return array(
            '网易云'   => 'netease',
            'QQ音乐'  => 'qq-music',
            '王者荣耀' => 'honor-of-kings',
            '营业执照' => 'business-license',
            '身份证'   => 'id-card',
            '备案'     => 'icp',
            '车商备案' => 'car-dealer',
            '地图'     => 'map',
            '微博'     => 'weibo',
            '快递'     => 'express',
        );
    }

    /**
     * @return string
     */
    public static function storageDir()
    {
        return dirname(__DIR__) . '/assets/img/category-icons';
    }

    /**
     * 从参考 txt 导入 SVG（格式：名称:<svg...> 或 名称;<svg...>，每行一条）
     *
     * @param string $txtPath
     * @return array{written: array<int, string>, errors: array<int, string>}
     */
    public static function importFromTxt($txtPath)
    {
        $written = array();
        $errors = array();

        if (!is_file($txtPath) || !is_readable($txtPath)) {
            $errors[] = '无法读取文件：' . $txtPath;
            return array('written' => $written, 'errors' => $errors);
        }

        $dir = self::storageDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $errors[] = '无法创建目录：' . $dir;
            return array('written' => $written, 'errors' => $errors);
        }

        $content = file_get_contents($txtPath);
        if ($content === false || trim($content) === '') {
            $errors[] = '文件为空';
            return array('written' => $written, 'errors' => $errors);
        }

        $slugMap = self::slugMap();
        $slugUsed = array();

        foreach (preg_split('/\r?\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^([^:;]+)[:;](<svg\b.+)$/uis', $line, $m)) {
                $errors[] = '无法解析行：' . mb_substr($line, 0, 40, 'UTF-8');
                continue;
            }

            $name = trim($m[1]);
            $svg = self::normalizeSvg(trim($m[2]));

            if (!self::isValidSvg($svg)) {
                $errors[] = '无效 SVG：' . $name;
                continue;
            }

            $baseSlug = isset($slugMap[$name]) ? $slugMap[$name] : self::fallbackSlug($name);
            $slug = $baseSlug;
            if (isset($slugUsed[$baseSlug])) {
                $slug = $baseSlug . '-alt';
            }
            $slugUsed[$baseSlug] = true;

            $file = $dir . '/' . $slug . '.svg';
            if (file_put_contents($file, $svg) === false) {
                $errors[] = '写入失败：' . $slug . '.svg';
                continue;
            }
            $written[] = '/assets/img/category-icons/' . $slug . '.svg';
        }

        return array('written' => $written, 'errors' => $errors);
    }

    /**
     * @param string $svg
     * @return string
     */
    private static function normalizeSvg($svg)
    {
        $svg = preg_replace('/\s(width|height)="200"/i', '', $svg);
        return trim($svg);
    }

    /**
     * @param string $svg
     * @return bool
     */
    private static function isValidSvg($svg)
    {
        return strpos($svg, '<svg') === 0 && strpos($svg, '</svg>') !== false;
    }

    /**
     * @param string $name
     * @return string
     */
    private static function fallbackSlug($name)
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'icon';
    }
}
