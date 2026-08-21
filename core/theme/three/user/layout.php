<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
function vs_theme_user_layout_start($title = '') {
    $seo = array();
    if (function_exists('vs_page_seo_pack')) {
        $seo = vs_page_seo_pack($title, array());
    }
    if (function_exists('vs_render_head')) {
        $css = array();
        $href = ThemeManager::activeStylesheetHref();
        if ($href !== '') { $css[] = $href; }
        vs_render_head($title, array(), true, $css, array(), 'vs-body th3-user', $seo, false);
    }
    echo '<div class="th3-stub"><p>主题三用户中心页面建设中。</p>';
}
function vs_theme_user_layout_end(array $extraScripts = array()) {
    echo '</div>';
    $js = array();
    $href = ThemeManager::activeScriptHref();
    if ($href !== '') { $js[] = $href; }
    if (function_exists('vs_render_foot')) {
        vs_render_foot(array(), $js, false);
    }
}
