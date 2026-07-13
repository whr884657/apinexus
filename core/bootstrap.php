<?php
/**
 * misc-api 系统引导入口，按序加载 core 下全部核心类
 */

defined('VS_ROOT') or define('VS_ROOT', dirname(__DIR__));

require_once VS_ROOT . '/core/version.php';
require_once VS_ROOT . '/core/helpers.php';
require_once VS_ROOT . '/core/InstallChecker.php';
require_once VS_ROOT . '/core/Database.php';
require_once VS_ROOT . '/core/DatabaseInstaller.php';
require_once VS_ROOT . '/core/DatabaseMigrator.php';
require_once VS_ROOT . '/core/Domain.php';
require_once VS_ROOT . '/core/SiteContext.php';
require_once VS_ROOT . '/core/RegisterPolicy.php';
require_once VS_ROOT . '/core/Config.php';
require_once VS_ROOT . '/core/Mailer.php';
require_once VS_ROOT . '/core/Auth.php';
require_once VS_ROOT . '/core/UserAuth.php';
require_once VS_ROOT . '/core/RateLimitStore.php';
require_once VS_ROOT . '/core/AuthSecurity.php';
require_once VS_ROOT . '/core/AjaxResponse.php';
require_once VS_ROOT . '/core/SystemInfo.php';
require_once VS_ROOT . '/core/Updater.php';
require_once VS_ROOT . '/core/UpdateLog.php';
require_once VS_ROOT . '/core/UserAvatar.php';
require_once VS_ROOT . '/core/UserManager.php';
require_once VS_ROOT . '/core/AdminUserBinding.php';
require_once VS_ROOT . '/core/ApiManager.php';
require_once VS_ROOT . '/core/ApiCategoryManager.php';
require_once VS_ROOT . '/core/CategoryIconImporter.php';
require_once VS_ROOT . '/core/ThemeManager.php';
require_once VS_ROOT . '/core/oauth/HttpClient.php';
require_once VS_ROOT . '/core/oauth/OAuthConfig.php';
require_once VS_ROOT . '/core/oauth/OAuthState.php';
require_once VS_ROOT . '/core/oauth/OAuthService.php';
require_once VS_ROOT . '/core/oauth/qq/QQOAuth.php';
require_once VS_ROOT . '/core/oauth/gitee/GiteeOAuth.php';

if (session_status() === PHP_SESSION_NONE) {
    AuthSecurity::configureSessionCookies();
    session_start();
    AuthSecurity::ensureCsrfToken();
}
