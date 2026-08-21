<?php
/**
 * 文件：core/theme/slate/lib/bootstrap.php
 * 作用：主题二页面公共引导（专属 Markdown 等）
 */
if (!defined('VS_THEME_RENDER')) {
    return;
}

$slateMdFile = __DIR__ . '/SlateMarkdown.php';
if (is_file($slateMdFile) && !class_exists('SlateMarkdown', false)) {
    require_once $slateMdFile;
}

if (!function_exists('slate_md_render')) {
    /**
     * @param string $text
     * @return string
     */
    function slate_md_render($text)
    {
        if (class_exists('SlateMarkdown')) {
            return SlateMarkdown::render($text);
        }
        if (class_exists('Markdown')) {
            return Markdown::render($text);
        }
        return '';
    }
}
