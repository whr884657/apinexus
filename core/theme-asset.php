<?php

/**
 * 文件：core/theme-asset.php
 * 作用：主题资源打包下发入口（轻量，不连库），供前台/用户中心减少 CSS/JS 串行请求
 *
 * 访问：/core/theme-asset.php?t=default&p=front-shell-css&v=…
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/version.php';
require_once VS_ROOT . '/core/ThemeAssetPack.php';

// 只接受查询串白名单参数，忽略其余噪音
$themeId = isset($_GET['t']) ? (string) $_GET['t'] : '';
$pack = isset($_GET['p']) ? (string) $_GET['p'] : '';
$pageKey = isset($_GET['page']) ? (string) $_GET['page'] : '';

ThemeAssetPack::serve($themeId, $pack, $pageKey);
