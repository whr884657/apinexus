<?php
/**
 * 青绿平台 · 用户中心布局（复用 default 结构，样式由 assets/user.css 覆盖）
 */
$file = ThemeManager::resolveThemeFile('user/layout.php', 'default');
if ($file !== '') {
    require_once $file;
}
