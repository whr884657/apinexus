<?php
/**
 * 文件：core/helpers.php
 * 作用：misc-api 通用辅助函数
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

/**
 * HTML 转义
 *
 * @param mixed $value
 * @return string
 */
function vs_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * 渲染统一界面提示块（info / warning / tip / success / danger）
 *
 * @param string $type
 * @param string $title
 * @param string $body
 * @param array  $options allow_html, compact, field
 * @return void
 */
function vs_render_notice($type, $title, $body, array $options = array())
{
    $allowed = array('info', 'warning', 'tip', 'success', 'danger');
    $type = in_array($type, $allowed, true) ? $type : 'info';

    $classes = array('vs-notice', 'vs-notice--' . $type);
    if (!empty($options['compact'])) {
        $classes[] = 'vs-notice--compact';
    }
    if (!empty($options['field'])) {
        $classes[] = 'vs-notice--field';
    }

    $bodyHtml = !empty($options['allow_html']) ? $body : vs_e($body);

    echo '<div class="' . vs_e(implode(' ', $classes)) . '" role="note">' . "\n";
    if (trim($title) !== '') {
        echo '<p class="vs-notice__title">' . vs_e($title) . '</p>' . "\n";
    }
    if (trim(strip_tags($bodyHtml)) !== '') {
        echo '<div class="vs-notice__text">' . $bodyHtml . '</div>' . "\n";
    }
    echo '</div>' . "\n";
}

/**
 * 渲染系统版本展示（有新版本时显示箭头与可点击的新版本号）
 *
 * @param array|null $updateCheck Updater::checkForUpdate() 结果
 * @return string
 */
function vs_render_version_display($updateCheck = null)
{
    $local = 'v' . VS_VERSION;
    $upgradeUrl = vs_base_url() . '/admin/upgrade.php';

    if (
        is_array($updateCheck)
        && !empty($updateCheck['update_available'])
        && !empty($updateCheck['remote_version'])
    ) {
        $remote = $updateCheck['remote_version'];
        $html = '<span class="vs-version-display">';
        $html .= '<span class="vs-version-display__current">' . vs_e($local) . '</span>';
        $html .= '<span class="vs-version-display__arrow" aria-hidden="true">→</span>';
        $html .= '<a href="' . vs_e($upgradeUrl) . '" class="vs-version-display__new" title="前往系统升级">';
        $html .= '<span class="vs-version-display__badge">新</span>';
        $html .= 'v' . vs_e($remote);
        $html .= '</a></span>';
        return $html;
    }

    return vs_e($local);
}

/**
 * 主题背景色预加载（与 theme-picker.js 共用 login_page_bg）
 *
 * @return void
 */
function vs_theme_bg_preload_script()
{
    echo '<script>';
    echo '(function(){try{var c=localStorage.getItem(\'login_page_bg\');if(!c)return;var h=c.replace(\'#\',\'\').trim();if(h.length===3)h=h[0]+h[0]+h[1]+h[1]+h[2]+h[2];if(h.length===8)h=h.slice(0,6);if(h.length!==6)return;var color=\'#\'+h.toLowerCase();document.documentElement.style.setProperty(\'--page-bg\',color);document.documentElement.style.backgroundColor=color;}catch(e){}})();';
    echo '</script>' . "\n";
}

/**
 *
 * @param string $password
 * @return string
 */
function vs_password_hash($password)
{
    return md5(md5($password));
}

/**
 * 获取站点根 URL
 *
 * @return string
 */
function vs_base_url()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    if (defined('VS_ROOT') && isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
        $projectRoot = rtrim(str_replace('\\', '/', realpath(VS_ROOT)), '/');
        if ($docRoot && $projectRoot && strpos($projectRoot, $docRoot) === 0) {
            $path = substr($projectRoot, strlen($docRoot));
            $cached = rtrim($scheme . '://' . $host . $path, '/');
            return $cached;
        }
    }

    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = preg_replace('#/(admin|install)(/.*)?$#', '', $dir);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $dir = '';
    }
    $cached = rtrim($scheme . '://' . $host . $dir, '/');
    return $cached;
}

/**
 * 获取项目根路径
 *
 * @return string
 */
function vs_root_path()
{
    return VS_ROOT;
}

/**
 * 重定向
 *
 * @param string $url
 * @return void
 */
function vs_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * 构建浏览器标题（避免页面名与站点名重复）
 *
 * @param string $pageTitle
 * @param string|null $siteName
 * @return string
 */
function vs_page_title($pageTitle, $siteName = null)
{
    if ($siteName === null) {
        if (class_exists('SiteContext') && InstallChecker::isInstalled()) {
            $siteName = SiteContext::siteName();
        } else {
            $siteName = 'misc-api';
        }
    }

    $pageTitle = trim((string) $pageTitle);
    $siteName = trim((string) $siteName);

    if ($siteName === '') {
        $siteName = 'misc-api';
    }

    if ($pageTitle === '' || $pageTitle === $siteName) {
        return $siteName;
    }

    $suffix = ' - ' . $siteName;
    if (strlen($pageTitle) >= strlen($suffix) && substr($pageTitle, -strlen($suffix)) === $suffix) {
        return $pageTitle;
    }

    return $pageTitle . $suffix;
}

/**
 * 渲染页面头部
 *
 * @param string $title
 * @param array  $cssFiles
 * @return void
 */
function vs_render_head($title, array $cssFiles = array(), $useSiteConfig = true)
{
    $base = vs_base_url();
    $siteName = 'misc-api';
    $favicon = '';
    $keywords = '';
    $description = '';

    if ($useSiteConfig && class_exists('InstallChecker') && InstallChecker::isInstalled()) {
        $siteName = SiteContext::siteName();
        $favicon = SiteContext::siteFavicon();
        $keywords = SiteContext::siteKeywords();
        $description = SiteContext::siteDescription();
    }

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    if ($description !== '') {
        echo '<meta name="description" content="' . vs_e($description) . '">' . "\n";
    }
    if ($keywords !== '') {
        echo '<meta name="keywords" content="' . vs_e($keywords) . '">' . "\n";
    }
    echo '<title>' . vs_e(vs_page_title($title, $siteName)) . '</title>' . "\n";
    if ($favicon !== '') {
        echo '<link rel="icon" href="' . vs_e(vs_favicon_href($favicon)) . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/common.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/modal.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/icons.css?v=' . VS_VERSION . '">' . "\n";
    foreach ($cssFiles as $css) {
        echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/' . vs_e($css) . '?v=' . VS_VERSION . '">' . "\n";
    }
    echo '</head>' . "\n";
    echo '<body class="vs-body">' . "\n";
}

/**
 * 渲染页面底部
 *
 * @param array $jsFiles
 * @return void
 */
function vs_render_foot(array $jsFiles = array())
{
    $base = vs_base_url();
    vs_render_modal_shell();
    echo '<script>window.VS_BASE_URL = ' . json_encode($base) . ';</script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/modal.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    foreach ($jsFiles as $js) {
        echo '<script src="' . vs_e($base) . '/assets/js/' . vs_e($js) . '?v=' . VS_VERSION . '"></script>' . "\n";
    }
    echo '</body></html>';
}

/**
 * 解析 Favicon 地址（支持完整 URL 或站点相对路径）
 *
 * @param string $path
 * @return string
 */
function vs_favicon_href($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = vs_base_url();
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return $base . $path;
}

/**
 * 渲染站点 Logo 图片（未配置时不输出）
 *
 * @param string $class CSS 类名
 * @return void
 */
function vs_render_site_logo($class = 'vs-logo-icon')
{
    if (!class_exists('SiteContext')) {
        return;
    }

    $logo = trim(SiteContext::siteLogo());
    if ($logo === '') {
        return;
    }

    $href = vs_favicon_href($logo);
    if ($href === '') {
        return;
    }

    $classAttr = trim($class . ' vs-site-logo-img');
    echo '<img class="' . vs_e($classAttr) . '" src="' . vs_e($href) . '" alt="' . vs_e(SiteContext::siteName()) . '">';
}

/**
 * 渲染页脚（版权 + ICP + 公安备案）
 *
 * @param string|null $siteName
 * @return void
 */
function vs_render_site_footer($siteName = null)
{
    if (!InstallChecker::isInstalled()) {
        return;
    }

    $siteName = $siteName !== null ? trim($siteName) : SiteContext::siteName();
    $beian = SiteContext::beianInfo();
    $base = vs_base_url();
    $year = date('Y');

    echo '<footer class="vs-site-footer">' . "\n";
    echo '<div class="vs-container vs-site-footer__inner">' . "\n";

    echo '<div class="vs-site-footer__item vs-site-footer__copyright">';
    echo vs_e($siteName) . ' &copy; ' . vs_e($year);
    echo '</div>' . "\n";

    if ($beian['icp_number'] !== '') {
        echo '<div class="vs-site-footer__item vs-site-footer__icp">';
        echo '<a href="' . vs_e($beian['icp_link']) . '" target="_blank" rel="noopener noreferrer">' . vs_e($beian['icp_number']) . '</a>';
        echo '</div>' . "\n";
    }

    if ($beian['gongan_number'] !== '') {
        echo '<div class="vs-site-footer__item vs-site-footer__gongan">';
        echo '<a href="' . vs_e($beian['gongan_link']) . '" target="_blank" rel="noopener noreferrer" class="vs-site-footer__gongan-link">';
        echo '<img src="' . vs_e($base) . '/assets/img/gov.png" alt="" class="vs-gongan-icon" width="16" height="16">';
        echo '<span>' . vs_e($beian['gongan_number']) . '</span>';
        echo '</a></div>' . "\n";
    }

    echo '</div></footer>' . "\n";
}

/**
 * 渲染统一弹窗骨架（全站共用）
 *
 * @return void
 */
function vs_render_modal_shell()
{
    echo '<div class="vs-modal-root" id="vsModalRoot" hidden aria-hidden="true">' . "\n";
    echo '<div class="vs-modal-overlay" id="vsModalOverlay"></div>' . "\n";
    echo '<div class="vs-modal" role="dialog" aria-modal="true" aria-labelledby="vsModalTitle">' . "\n";
    echo '<div class="vs-modal__head"><h3 class="vs-modal__title" id="vsModalTitle"></h3></div>' . "\n";
    echo '<div class="vs-modal__body" id="vsModalBody"></div>' . "\n";
    echo '<div class="vs-modal__foot" id="vsModalFoot"></div>' . "\n";
    echo '</div></div>' . "\n";
}
