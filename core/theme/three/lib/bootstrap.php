<?php
/**
 * 主题 three · 页面公共引导（Markdown）
 */
if (!defined('VS_THEME_RENDER')) {
    return;
}

if (!function_exists('th3_md_render')) {
    /**
     * @param string $text
     * @return string
     */
    function th3_md_render($text)
    {
        if (class_exists('Markdown')) {
            return Markdown::render((string) $text);
        }
        return '';
    }
}
