<?php
/**
 * 文件：core/helpers.php
 * 作用：ApiNexus 通用辅助函数
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
    if (trim((string) $title) !== '') {
        echo '<p class="vs-notice__title">' . vs_e($title) . '</p>' . "\n";
    }
    if (trim(strip_tags((string) $bodyHtml)) !== '') {
        echo '<div class="vs-notice__text">' . $bodyHtml . '</div>' . "\n";
    }
    echo '</div>' . "\n";
}

/**
 * 数据列表加载态（动效 + 文案，禁止纯文字「加载中」）
 *
 * @param string $label 可选短文案，默认「正在加载」
 * @param array  $options compact=true 时更紧凑
 * @return void
 */
function vs_render_loading($label = '正在加载', array $options = array())
{
    $label = trim((string) $label);
    if ($label === '' || $label === '加载中' || $label === '加载中…' || $label === '加载中...') {
        $label = '正在加载';
    }
    $classes = 'vs-loading';
    if (!empty($options['compact'])) {
        $classes .= ' vs-loading--compact';
    }
    echo '<div class="' . vs_e($classes) . '" role="status" aria-live="polite" aria-busy="true">' . "\n";
    echo '<div class="vs-loading__orbit" aria-hidden="true">'
        . '<span class="vs-loading__ring"></span>'
        . '<span class="vs-loading__dot"></span>'
        . '</div>' . "\n";
    echo '<p class="vs-loading__text">' . vs_e($label) . '</p>' . "\n";
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
 * 邮箱规范化（去空格、小写），用于查找与会话比对
 *
 * @param string $email
 * @return string
 */
function vs_normalize_email($email)
{
    return strtolower(trim((string) $email));
}

/**
 * Unicode 字符长度（用户名等前台 maxlength 按「字」计）
 *
 * @param string $value
 * @return int
 */
function vs_unicode_len($value)
{
    $value = (string) $value;
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

/**
 * 头像链接是否可保存（http/https，或站点内以 / 开头的相对路径）
 *
 * @param string $url
 * @return bool
 */
function vs_is_allowed_avatar_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return true;
    }

    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    // 站点相对路径，如 /assets/img/avatar/xx.png
    if (isset($url[0]) && $url[0] === '/' && strpos($url, '//') !== 0 && strpos($url, '\\') === false) {
        return strlen($url) <= 500;
    }

    return false;
}

/**
 * 路径式资源公开地址：/{脚本名}/{数字ID}（无 .php；依赖通用伪静态）
 *
 * @param string $script 根入口脚本名，如 detail / article（可带 .php，会去掉）
 * @param int    $id
 * @return string
 */
function vs_path_resource_url($script, $id)
{
    $script = strtolower(basename(str_replace('\\', '/', (string) $script)));
    if (substr($script, -4) === '.php') {
        $script = substr($script, 0, -4);
    }
    $script = preg_replace('/[^a-z0-9_-]/', '', $script);
    $id = (int) $id;
    if ($script === '' || $id <= 0) {
        return rtrim(vs_base_url(), '/');
    }
    return rtrim(vs_base_url(), '/') . '/' . $script . '/' . $id;
}

/**
 * 接口详情公开地址（无 .php：/detail/{id}，依赖通用伪静态）
 *
 * @param int $apiId
 * @return string
 */
function vs_api_detail_url($apiId)
{
    $apiId = (int) $apiId;
    if ($apiId <= 0) {
        return rtrim(vs_base_url(), '/') . '/apis';
    }
    return vs_path_resource_url('detail', $apiId);
}

/**
 * 开发者公开主页地址（无 .php：/profile/{id}）
 *
 * @param int $userId
 * @return string
 */
function vs_profile_url($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return rtrim(vs_base_url(), '/') . '/contributors';
    }
    return vs_path_resource_url('profile', $userId);
}

/**
 * 通用 http(s) 外链校验（头像 / 壁纸 / 博客）
 *
 * @param string $url
 * @return bool
 */
function vs_is_allowed_http_url($url)
{
    return vs_is_allowed_avatar_url($url);
}

/**
 * 从当前请求解析资源数字 ID
 * 优先 $_GET['id']（伪静态 /detail/{id} → detail.php?id=）；其次 PATH_INFO（兼容旧 /detail.php/{id}）
 *
 * @param string $queryKey 查询参数名，默认 id
 * @return int
 */
function vs_resolve_path_id($queryKey = 'id')
{
    $queryKey = is_string($queryKey) && $queryKey !== '' ? $queryKey : 'id';
    if (isset($_GET[$queryKey])) {
        $fromGet = (string) $_GET[$queryKey];
        if ($fromGet !== '' && ctype_digit($fromGet)) {
            return (int) $fromGet;
        }
    }

    $info = '';
    if (!empty($_SERVER['PATH_INFO'])) {
        $info = (string) $_SERVER['PATH_INFO'];
    } elseif (!empty($_SERVER['ORIG_PATH_INFO'])) {
        $info = (string) $_SERVER['ORIG_PATH_INFO'];
    } else {
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        if (is_string($path) && $script !== '') {
            $scriptBase = basename($script);
            $pos = strrpos($path, '/' . $scriptBase);
            if ($pos !== false) {
                $after = substr($path, $pos + strlen('/' . $scriptBase));
                if ($after !== '' && isset($after[0]) && $after[0] === '/') {
                    $info = $after;
                }
            }
        }
    }

    if ($info !== '' && $info !== '/') {
        $parts = explode('/', trim($info, '/'));
        if (isset($parts[0]) && ctype_digit($parts[0])) {
            return (int) $parts[0];
        }
    }

    return 0;
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

    // 与 AuthSecurity::isHttps 一致：兼容反向代理 X-Forwarded-Proto（SEO/OG 绝对 URL 必须准）
    $https = class_exists('AuthSecurity')
        ? AuthSecurity::isHttps()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443));
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
 * 校验 POST 请求（同源 + CSRF），失败时返回 JSON 错误
 *
 * @return void
 */
function vs_require_secure_post()
{
    AuthSecurity::sendSecurityHeaders();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        AjaxResponse::error('无效请求', 405);
    }

    if (!AuthSecurity::validateSameOrigin()) {
        AjaxResponse::json(array(
            'code' => 0,
            'msg'  => '请求来源无效，请从本站页面操作',
            'csrf' => AuthSecurity::rotateCsrfToken(),
        ), 403);
    }

    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!AuthSecurity::validateCsrf($token)) {
        AjaxResponse::json(array(
            'code' => 0,
            'msg'  => '登录凭证已失效，请刷新页面后重试',
            'csrf' => AuthSecurity::rotateCsrfToken(),
        ), 403);
    }
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
            $siteName = 'ApiNexus';
        }
    }

    $pageTitle = trim((string) $pageTitle);
    $siteName = trim((string) $siteName);

    if ($siteName === '') {
        $siteName = 'ApiNexus';
    }

    if ($pageTitle === '' || $pageTitle === $siteName || $pageTitle === '首页') {
        return $siteName;
    }

    $suffix = ' - ' . $siteName;
    if (strlen($pageTitle) >= strlen($suffix) && substr($pageTitle, -strlen($suffix)) === $suffix) {
        return $pageTitle;
    }

    return $pageTitle . $suffix;
}

/**
 * 站点运行时间起点（YYYY-MM-DD HH:MM:SS）
 *
 * @return string
 */
function vs_site_runtime_start()
{
    if (!class_exists('SiteContext') || !InstallChecker::isInstalled()) {
        return '';
    }
    return SiteContext::siteRuntimeStart();
}

/**
 * 是否已配置网站运行时间
 *
 * @return bool
 */
function vs_site_has_runtime()
{
    $start = vs_site_runtime_start();
    if ($start === '') {
        return false;
    }
    $ts = strtotime($start);
    return $ts !== false;
}

/**
 * 已启用且 URL 非空的页脚二维码列表
 *
 * @return array<int, array{name: string, url: string}>
 */
function vs_footer_enabled_qrs()
{
    if (!class_exists('SiteContext') || !InstallChecker::isInstalled()) {
        return array();
    }

    $items = array(
        array(
            'enabled' => SiteContext::footerQr1Enabled(),
            'name'    => SiteContext::footerQr1Name(),
            'url'     => SiteContext::footerQr1Url(),
        ),
        array(
            'enabled' => SiteContext::footerQr2Enabled(),
            'name'    => SiteContext::footerQr2Name(),
            'url'     => SiteContext::footerQr2Url(),
        ),
    );

    $out = array();
    foreach ($items as $item) {
        if ($item['enabled'] !== '1') {
            continue;
        }
        $url = trim((string) $item['url']);
        if ($url === '') {
            continue;
        }
        $name = trim((string) $item['name']);
        $out[] = array(
            'name' => $name !== '' ? $name : '二维码',
            'url'  => $url,
        );
    }

    return $out;
}

/**
 * 渲染页脚自定义三栏（管理员可信 HTML，原样输出）
 *
 * @return void
 */
function vs_render_footer_custom_bar()
{
    if (!class_exists('SiteContext') || !InstallChecker::isInstalled()) {
        return;
    }

    $left = SiteContext::footerHtmlLeft();
    $center = SiteContext::footerHtmlCenter();
    $right = SiteContext::footerHtmlRight();
    if (trim($left . $center . $right) === '') {
        return;
    }

    echo '<div class="vs-foot-custom">' . "\n";
    echo '<div class="vs-foot-custom__slot vs-foot-custom__slot--left">' . $left . '</div>' . "\n";
    echo '<div class="vs-foot-custom__slot vs-foot-custom__slot--center">' . $center . '</div>' . "\n";
    echo '<div class="vs-foot-custom__slot vs-foot-custom__slot--right">' . $right . '</div>' . "\n";
    echo '</div>' . "\n";
}

/**
 * 渲染页脚二维码区
 *
 * @param string $modifier 额外 CSS 类名
 * @return void
 */
function vs_render_footer_qrs($modifier = '')
{
    if (!class_exists('ThemeManager') || !ThemeManager::themeSettingBool('show_footer_qr', true)) {
        return;
    }

    $qrs = vs_footer_enabled_qrs();
    if ($qrs === array()) {
        return;
    }

    $classes = array('vs-foot-qr');
    $modifier = trim((string) $modifier);
    if ($modifier !== '') {
        $classes[] = $modifier;
    }

    echo '<div class="' . vs_e(implode(' ', $classes)) . '">' . "\n";
    foreach ($qrs as $qr) {
        $href = vs_favicon_href($qr['url']);
        if ($href === '') {
            continue;
        }
        echo '<figure class="vs-foot-qr__item">' . "\n";
        echo '<img class="vs-foot-qr__img" src="' . vs_e($href) . '" alt="' . vs_e($qr['name']) . '" loading="lazy" referrerpolicy="no-referrer">' . "\n";
        if ($qr['name'] !== '') {
            echo '<figcaption class="vs-foot-qr__label">' . vs_e($qr['name']) . '</figcaption>' . "\n";
        }
        echo '</figure>' . "\n";
    }
    echo '</div>' . "\n";
}

/**
 * 当前页面 canonical 绝对 URL
 *
 * @return string
 */
function vs_seo_canonical_url()
{
    $base = rtrim(vs_base_url(), '/');
    if (!empty($_SERVER['REQUEST_URI'])) {
        $path = strtok((string) $_SERVER['REQUEST_URI'], '?');
        if ($path !== false && $path !== '') {
            return $base . $path;
        }
    }
    return $base . '/';
}

/**
 * 转为绝对 URL（OG / 分享图须为绝对地址，QQ/微信抓取才稳定）
 *
 * @param string $path
 * @return string
 */
function vs_seo_abs_url($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return vs_seo_prefer_https($path);
    }
    $base = rtrim(vs_base_url(), '/');
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return vs_seo_prefer_https($base . $path);
}

/**
 * 当前请求为 HTTPS 时，把 http:// 升级为 https://（OG secure_url / 社交抓取）
 *
 * @param string $url
 * @return string
 */
function vs_seo_prefer_https($url)
{
    $url = trim((string) $url);
    if ($url === '' || stripos($url, 'http://') !== 0) {
        return $url;
    }
    $forceHttps = class_exists('AuthSecurity') ? AuthSecurity::isHttps() : false;
    if (!$forceHttps) {
        return $url;
    }
    return 'https://' . substr($url, 7);
}

/**
 * 推断图片 MIME（供 og:image:type / link type）
 *
 * @param string $url
 * @return string
 */
function vs_seo_image_mime($url)
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        return 'image/png';
    }
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return 'image/jpeg';
    }
    if ($ext === 'webp') {
        return 'image/webp';
    }
    if ($ext === 'gif') {
        return 'image/gif';
    }
    if ($ext === 'svg') {
        return 'image/svg+xml';
    }
    if ($ext === 'ico') {
        return 'image/x-icon';
    }
    return '';
}

/**
 * 社交分享图：优先 Logo（通常更大），其次 Favicon；必须绝对 URL
 * 主题 Hero 图文禁止进入 SEO 图片链。
 *
 * @return string
 */
function vs_seo_share_image()
{
    if (!class_exists('SiteContext') || !InstallChecker::isInstalled()) {
        return '';
    }
    $logo = trim((string) SiteContext::siteLogo());
    if ($logo !== '') {
        return vs_seo_abs_url(vs_favicon_href($logo));
    }
    $favicon = trim((string) SiteContext::siteFavicon());
    if ($favicon !== '') {
        return vs_seo_abs_url(vs_favicon_href($favicon));
    }
    return '';
}

/**
 * 系统级站点描述（SEO 唯一主源）；禁止用主题 Hero 标签/标题顶替
 *
 * @param string $fallback 系统描述为空时的兜底（一般为站点名）
 * @return string
 */
function vs_seo_site_description($fallback = '')
{
    $desc = '';
    if (class_exists('SiteContext') && InstallChecker::isInstalled()) {
        $desc = trim((string) SiteContext::siteDescription());
    }
    if ($desc !== '') {
        return vs_seo_truncate($desc);
    }
    $fallback = trim((string) $fallback);
    if ($fallback === '' && class_exists('SiteContext') && InstallChecker::isInstalled()) {
        $fallback = SiteContext::siteName();
    }
    return vs_seo_truncate($fallback !== '' ? $fallback : 'ApiNexus');
}

/**
 * 截断 meta description（搜索引擎 / 社交平台建议 ≤160 字）
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function vs_seo_truncate($text, $max = 160)
{
    // preg_replace 失败时可能返回 null，PHP 8.1+ trim(null) 会触发 Deprecated
    $cleaned = preg_replace('/\s+/u', ' ', strip_tags((string) ($text === null ? '' : $text)));
    $text = trim((string) ($cleaned === null ? '' : $cleaned));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $max) {
        return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…';
    }
    if (strlen($text) > $max) {
        return rtrim(substr($text, 0, $max - 1)) . '…';
    }
    return $text;
}

/**
 * 构建页面 SEO 默认项（可被页面级覆盖）
 *
 * @param array $overrides
 * @return array
 */
function vs_seo_defaults(array $overrides = array())
{
    $siteName = 'ApiNexus';
    $keywords = '';
    if (class_exists('SiteContext') && InstallChecker::isInstalled()) {
        $siteName = SiteContext::siteName();
        $keywords = SiteContext::siteKeywords();
    }

    $canonical = vs_seo_canonical_url();
    $image = vs_seo_share_image();
    $desc = vs_seo_site_description($siteName);

    $defaults = array(
        'title'       => $siteName,
        'description' => $desc,
        'keywords'    => $keywords,
        'image'       => $image,
        'url'         => $canonical,
        'canonical'   => $canonical,
        'robots'      => 'index,follow',
        'type'        => 'website',
        'site_name'   => $siteName,
    );

    return array_merge($defaults, $overrides);
}

/**
 * 渲染 SEO / Open Graph / Twitter / Schema 元标签
 *
 * @param array $opts title, description, keywords, image, url, robots, canonical, theme_color, type, locale
 * @return void
 */
function vs_render_seo_meta(array $opts = array())
{
    $title = trim((string) (isset($opts['title']) ? $opts['title'] : ''));
    $description = trim((string) (isset($opts['description']) ? $opts['description'] : ''));
    $keywords = trim((string) (isset($opts['keywords']) ? $opts['keywords'] : ''));
    $image = trim((string) (isset($opts['image']) ? $opts['image'] : ''));
    $url = trim((string) (isset($opts['url']) ? $opts['url'] : ''));
    $robots = trim((string) (isset($opts['robots']) ? $opts['robots'] : ''));
    $canonical = trim((string) (isset($opts['canonical']) ? $opts['canonical'] : ''));
    $themeColor = trim((string) (isset($opts['theme_color']) ? $opts['theme_color'] : ''));
    $type = trim((string) (isset($opts['type']) ? $opts['type'] : 'website'));
    if ($type === '') {
        $type = 'website';
    }
    $siteName = trim((string) (isset($opts['site_name']) ? $opts['site_name'] : ''));
    $locale = trim((string) (isset($opts['locale']) ? $opts['locale'] : 'zh_CN'));
    if ($locale === '') {
        $locale = 'zh_CN';
    }

    if ($siteName === '' && class_exists('SiteContext') && InstallChecker::isInstalled()) {
        $siteName = SiteContext::siteName();
    }

    if ($image !== '') {
        $image = vs_seo_abs_url($image);
    }
    if ($url !== '') {
        $url = vs_seo_abs_url($url);
    }
    if ($canonical !== '') {
        $canonical = vs_seo_abs_url($canonical);
    }
    if ($description !== '') {
        $description = vs_seo_truncate($description);
    }

    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . "\n";
    echo '<meta name="renderer" content="webkit">' . "\n";
    echo '<meta name="format-detection" content="telephone=no">' . "\n";

    if ($description !== '') {
        echo '<meta name="description" content="' . vs_e($description) . '">' . "\n";
    }
    if ($keywords !== '') {
        echo '<meta name="keywords" content="' . vs_e($keywords) . '">' . "\n";
    }
    if ($robots !== '') {
        echo '<meta name="robots" content="' . vs_e($robots) . '">' . "\n";
    }
    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . vs_e($canonical) . '">' . "\n";
    }
    if ($themeColor !== '') {
        echo '<meta name="theme-color" content="' . vs_e($themeColor) . '">' . "\n";
    }
    if ($siteName !== '') {
        echo '<meta name="application-name" content="' . vs_e($siteName) . '">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . vs_e($siteName) . '">' . "\n";
    }
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
    echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";

    if ($title !== '') {
        echo '<meta itemprop="name" content="' . vs_e($title) . '">' . "\n";
        echo '<meta property="og:title" content="' . vs_e($title) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . vs_e($title) . '">' . "\n";
    }
    if ($description !== '') {
        echo '<meta itemprop="description" content="' . vs_e($description) . '">' . "\n";
        echo '<meta property="og:description" content="' . vs_e($description) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . vs_e($description) . '">' . "\n";
    }
    if ($image !== '') {
        $imageMime = vs_seo_image_mime($image);
        $imageSecure = vs_seo_prefer_https($image);
        if (stripos($imageSecure, 'http://') === 0) {
            $imageSecure = 'https://' . substr($imageSecure, 7);
        }
        echo '<meta itemprop="image" content="' . vs_e($image) . '">' . "\n";
        echo '<meta property="og:image" content="' . vs_e($image) . '">' . "\n";
        echo '<meta property="og:image:url" content="' . vs_e($image) . '">' . "\n";
        echo '<meta property="og:image:secure_url" content="' . vs_e($imageSecure) . '">' . "\n";
        if ($imageMime !== '') {
            echo '<meta property="og:image:type" content="' . vs_e($imageMime) . '">' . "\n";
        }
        // 社交平台建议 ≥300；未知真实尺寸时给保守占位，避免因缺尺寸被拒
        echo '<meta property="og:image:width" content="512">' . "\n";
        echo '<meta property="og:image:height" content="512">' . "\n";
        if ($siteName !== '') {
            echo '<meta property="og:image:alt" content="' . vs_e($siteName) . '">' . "\n";
        }
        echo '<meta name="twitter:image" content="' . vs_e($image) . '">' . "\n";
    }
    if ($url !== '') {
        echo '<meta property="og:url" content="' . vs_e($url) . '">' . "\n";
    }
    if ($type !== '') {
        echo '<meta property="og:type" content="' . vs_e($type) . '">' . "\n";
    }
    if ($siteName !== '') {
        echo '<meta property="og:site_name" content="' . vs_e($siteName) . '">' . "\n";
    }
    if ($locale !== '') {
        echo '<meta property="og:locale" content="' . vs_e($locale) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="' . vs_e($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
}

/**
 * 渲染页面头部
 *
 * @param string $title
 * @param array  $cssFiles
 * @param bool   $useSiteConfig
 * @param array  $extraCssHrefs 完整 URL（如主题 assets）
 * @param array  $headScripts   head 内联脚本或外链（完整 URL）
 * @param string $bodyClass     body 额外 class
 * @param array  $seoOpts       页面级 SEO 覆盖（description / image / robots 等）
 * @return void
 */
function vs_render_head($title, array $cssFiles = array(), $useSiteConfig = true, array $extraCssHrefs = array(), array $headScripts = array(), $bodyClass = 'vs-body', array $seoOpts = array())
{
    $base = vs_base_url();
    $siteName = 'ApiNexus';
    $favicon = '';
    $keywords = '';
    $canonical = vs_seo_canonical_url();

    if ($useSiteConfig && class_exists('InstallChecker') && InstallChecker::isInstalled()) {
        $siteName = SiteContext::siteName();
        $favicon = SiteContext::siteFavicon();
        $keywords = SiteContext::siteKeywords();
    }

    $pageTitle = vs_page_title($title, $siteName);
    $ogImage = vs_seo_share_image();
    $description = vs_seo_site_description($siteName);

    $seo = vs_seo_defaults(array(
        'title'       => $pageTitle,
        'description' => $description,
        'keywords'    => $keywords,
        'image'       => $ogImage,
        'url'         => $canonical,
        'canonical'   => $canonical,
        'robots'      => 'index,follow',
        'site_name'   => $siteName,
    ));
    if ($seoOpts !== array()) {
        $seo = array_merge($seo, $seoOpts);
    }
    // 首页等未显式传 description 时，强制系统站点描述（禁止主题 Hero 渗入）
    if (!isset($seoOpts['description']) || trim((string) $seoOpts['description']) === '') {
        $seo['description'] = $description;
    } else {
        $seo['description'] = vs_seo_truncate((string) $seoOpts['description']);
    }
    if (!isset($seo['image']) || trim((string) $seo['image']) === '') {
        $seo['image'] = $ogImage;
    }
    if (!isset($seo['title']) || trim((string) $seo['title']) === '') {
        $seo['title'] = $pageTitle;
    }

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    vs_render_seo_meta($seo);
    echo '<title>' . vs_e($pageTitle) . '</title>' . "\n";
    vs_render_site_icons($favicon, $ogImage);
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/common.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/modal.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/icons.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/site-footer.css?v=' . VS_VERSION . '">' . "\n";
    foreach ($cssFiles as $css) {
        echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/' . vs_e($css) . '?v=' . VS_VERSION . '">' . "\n";
    }
    foreach ($extraCssHrefs as $href) {
        $href = trim((string) $href);
        if ($href !== '') {
            echo '<link rel="stylesheet" href="' . vs_e($href) . '">' . "\n";
        }
    }
    foreach ($headScripts as $script) {
        $script = trim((string) $script);
        if ($script === '') {
            continue;
        }
        if (strpos($script, '<') === 0) {
            echo $script . "\n";
            continue;
        }
        echo '<script src="' . vs_e($script) . '"></script>' . "\n";
    }
    echo '<script>(function(){try{var t=localStorage.getItem("theme");if(t!=="dark"&&t!=="light"){t="light"}document.documentElement.setAttribute("data-theme",t);}catch(e){document.documentElement.setAttribute("data-theme","light");}})();</script>' . "\n";
    echo '</head>' . "\n";
    echo '<body class="' . vs_e(trim((string) $bodyClass)) . '">' . "\n";
}

/**
 * 渲染页面底部
 *
 * @param array  $jsFiles
 * @param array  $extraJsHrefs 完整 URL（如主题 theme.js）
 * @return void
 */
function vs_render_foot(array $jsFiles = array(), array $extraJsHrefs = array())
{
    $base = vs_base_url();
    vs_render_modal_shell();
    echo '<script>window.VS_BASE_URL = ' . json_encode($base) . ';</script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/modal.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    foreach ($jsFiles as $js) {
        echo '<script src="' . vs_e($base) . '/assets/js/' . vs_e($js) . '?v=' . VS_VERSION . '"></script>' . "\n";
    }
    foreach ($extraJsHrefs as $href) {
        $href = trim((string) $href);
        if ($href !== '') {
            echo '<script src="' . vs_e($href) . '"></script>' . "\n";
        }
    }
    echo '</body></html>';
}

/**
 * 渲染前台页面（主题驱动）
 *
 * @param string $pageKey   主题 pages 下的页面键名
 * @param string $pageTitle 浏览器标题
 * @param array  $pageData  传给主题模板的额外变量
 * @return void
 */
function vs_frontend_page($pageKey, $pageTitle, array $pageData = array())
{
    $seoOpts = array();
    if (isset($pageData['seo']) && is_array($pageData['seo'])) {
        $seoOpts = $pageData['seo'];
        unset($pageData['seo']);
    }

    $extraCss = array();
    $extraJs = array();
    $headScripts = array();
    $bodyClass = 'vs-body';
    $themeId = ThemeManager::activeId();

    if ($themeId === 'default') {
        $bundle = ThemeManager::defaultFrontendAssets($pageKey);
        $extraCss = $bundle['css'];
        $extraJs = $bundle['js'];
        $headScripts = $bundle['head_scripts'];
        $bodyClass = $bundle['body_class'];
    } else {
        $cssHref = ThemeManager::activeStylesheetHref();
        if ($cssHref !== '') {
            $extraCss[] = $cssHref;
        }
        $jsHref = ThemeManager::activeScriptHref();
        if ($jsHref !== '') {
            $extraJs[] = $jsHref;
        }
    }

    vs_render_head($pageTitle, array(), true, $extraCss, $headScripts, $bodyClass, $seoOpts);

    ThemeManager::renderBody($pageKey, $pageTitle, $pageData);

    vs_render_foot(array(), $extraJs);
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
        return vs_seo_prefer_https($path);
    }
    $base = vs_base_url();
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return vs_seo_prefer_https($base . $path);
}

/**
 * 输出站点图标 link（浏览器 tab + Apple + 快捷方式）
 * 须绝对 URL；QQ/微信/百度抓图标依赖这些标签 + og:image。
 *
 * @param string $favicon 原始 favicon 配置
 * @param string $shareImage 已绝对化的分享图（可与 logo 相同）
 * @return void
 */
function vs_render_site_icons($favicon, $shareImage = '')
{
    $iconHref = '';
    $favicon = trim((string) $favicon);
    if ($favicon !== '') {
        $iconHref = vs_seo_abs_url(vs_favicon_href($favicon));
    }
    $touchHref = trim((string) $shareImage);
    if ($touchHref === '') {
        $touchHref = $iconHref;
    } else {
        $touchHref = vs_seo_abs_url($touchHref);
    }

    if ($iconHref !== '') {
        $mime = vs_seo_image_mime($iconHref);
        $typeAttr = $mime !== '' ? ' type="' . vs_e($mime) . '"' : '';
        echo '<link rel="icon" href="' . vs_e($iconHref) . '"' . $typeAttr . '>' . "\n";
        echo '<link rel="shortcut icon" href="' . vs_e($iconHref) . '"' . $typeAttr . '>' . "\n";
    }
    if ($touchHref !== '') {
        echo '<link rel="apple-touch-icon" href="' . vs_e($touchHref) . '">' . "\n";
        echo '<link rel="apple-touch-icon-precomposed" href="' . vs_e($touchHref) . '">' . "\n";
    }
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
 * 前台主题品牌图标：优先站点 Logo，未配置时使用主题内默认占位
 *
 * @param string $imgClass
 * @param string $fallbackClass
 * @return void
 */
function vs_theme_site_logo($imgClass = '', $fallbackClass = '')
{
    if (!class_exists('SiteContext')) {
        return;
    }

    $logo = trim(SiteContext::siteLogo());
    if ($logo !== '') {
        vs_render_site_logo($imgClass);
        return;
    }

    $cls = trim($imgClass . ' ' . $fallbackClass);
    if ($cls === '') {
        $cls = 'vs-theme-logo-fallback';
    }
    echo '<span class="' . vs_e($cls) . '" aria-hidden="true"></span>';
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

/**
 * 输出 404 页面并终止（含网络安全法律提示）
 *
 * @return void
 */
function vs_render_404_page()
{
    if (!headers_sent()) {
        http_response_code(404);
        AuthSecurity::sendSecurityHeaders();
    }

    $base = vs_base_url();
    $siteName = 'ApiNexus';
    if (class_exists('InstallChecker') && InstallChecker::isInstalled() && class_exists('SiteContext')) {
        $siteName = SiteContext::siteName();
    }

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh-CN"><head><meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    echo '<title>' . vs_e(vs_page_title('页面不存在', $siteName)) . '</title>' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/common.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/error.css?v=' . VS_VERSION . '">' . "\n";
    echo '</head><body class="vs-body vs-error-body">' . "\n";
    echo '<main class="vs-error-page">' . "\n";
    echo '<div class="vs-error-page__code">404</div>' . "\n";
    echo '<h1 class="vs-error-page__title">页面不存在</h1>' . "\n";
    echo '<p class="vs-error-page__lead">您访问的地址不存在，或请求方式不符合站点安全策略。</p>' . "\n";
    echo '<div class="vs-error-page__legal">' . "\n";
    echo '<h2 class="vs-error-page__legal-title">安全与法律提示</h2>' . "\n";
    echo '<ul class="vs-error-page__legal-list">' . "\n";
    echo '<li>请通过本站提供的正常入口访问功能，勿尝试扫描、爆破或篡改未公开接口。</li>' . "\n";
    echo '<li>根据《中华人民共和国网络安全法》，任何危害网络安全、非法侵入他人网络或干扰网络正常功能的行为，将依法承担法律责任。</li>' . "\n";
    echo '<li>根据《中华人民共和国刑法》第二百八十五条等规定，非法侵入计算机信息系统、非法获取数据或提供侵入工具，构成犯罪的，依法追究刑事责任。</li>' . "\n";
    echo '<li>异常抓包、伪造或重放请求、绕过 CSRF/令牌校验等行为，可能被记录并作为安全审计依据。</li>' . "\n";
    echo '</ul></div>' . "\n";
    echo '<div class="vs-error-page__actions">' . "\n";
    echo '<a href="' . vs_e($base) . '/" class="vs-btn vs-btn--primary">返回首页</a>' . "\n";
    echo '</div></main></body></html>';
    exit;
}

/**
 * 前台在线测试：当前登录用户的 KEY 上下文
 *
 * @return array{loggedIn:bool,apiKey:string,apiKeyCount:int,userCenterUrl:string,loginUrl:string,csrf:string,playUrl:string}
 */
function vs_playground_session_context()
{
    $base = rtrim(vs_base_url(), '/');
    $out = array(
        'loggedIn'      => false,
        'apiKey'        => '',
        'apiKeyCount'   => 0,
        'userCenterUrl' => $base . '/user/index',
        'loginUrl'      => $base . '/user/login',
        'csrf'          => class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '',
        'playUrl'       => $base . '/core/playground/relay.php',
    );
    if (!class_exists('UserAuth') || !UserAuth::check()) {
        return $out;
    }
    $out['loggedIn'] = true;
    if (!class_exists('ApiKeyManager') || !ApiKeyManager::tableReady()) {
        return $out;
    }
    $user = UserAuth::user();
    $uid = is_array($user) && isset($user['id']) ? (int) $user['id'] : 0;
    if ($uid <= 0) {
        return $out;
    }
    foreach (ApiKeyManager::listByUser($uid) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $enabled = isset($row['status'])
            ? ((int) $row['status'] === ApiKeyManager::STATUS_ENABLED)
            : true;
        if (!$enabled) {
            continue;
        }
        $out['apiKeyCount']++;
        if ($out['apiKey'] === '' && !empty($row['secret'])) {
            $out['apiKey'] = (string) $row['secret'];
        }
    }
    return $out;
}
