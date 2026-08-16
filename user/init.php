<?php
/**
 * 文件：user/init.php
 * 作用：用户中心页面统一引导
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

InstallChecker::requireInstalled();
UserAuth::requireLogin();
AuthSecurity::sendSecurityHeaders();

$vsBase     = vs_site_base_path();
$vsUser     = UserAuth::user();
$vsUserProfile = FrontendUser::current();
$vsSiteName   = SiteContext::siteName();
$vsSystemName = SiteContext::systemName();
