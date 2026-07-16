<?php
/**
 * 文件：media-proxy.php
 * 作用：外链图片代理（跨域 / 防盗链友好展示）
 * 用法：/media-proxy?u={base64(url)} 或 /media-proxy.php?u=
 */

define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';
require_once VS_ROOT . '/core/ExternalMedia.php';

ExternalMedia::handleRequest();
