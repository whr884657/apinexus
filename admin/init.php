<?php
/**
 * 文件：admin/init.php
 * 作用：后台页面统一引导（认证 + 布局依赖）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

InstallChecker::requireInstalled();
Auth::requireLogin();
AuthSecurity::sendSecurityHeaders();

$vsBase     = vs_base_url();
$vsAdmin    = Auth::user();
$vsSiteName   = SiteContext::siteName();
$vsSystemName = SiteContext::systemName();
$vsCfg      = Config::all();
