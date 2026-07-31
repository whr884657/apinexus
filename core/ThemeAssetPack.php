<?php

/**
 * 文件：core/ThemeAssetPack.php
 * 作用：主题 CSS/JS「源文件保持分立、HTTP 一次打包下发」，减少首屏串行请求（不改 UI、不合并源码维护形态）
 *
 * 约定：磁盘上仍是多个 css/js；仅通过本类白名单拼成少数 HTTP 响应。
 */

if (!class_exists('ThemeAssetPack')) {

class ThemeAssetPack
{
    /**
     * @param string $themeId
     * @param string $pack
     * @param string $pageKey
     * @return array{type:string,files:array<int,string>}|null
     */
    public static function resolve($themeId, $pack, $pageKey = '')
    {
        $themeId = self::sanitizeThemeId($themeId);
        $pack = self::sanitizePack($pack);
        $pageKey = self::sanitizePageKey($pageKey);
        if ($themeId === '' || $pack === '') {
            return null;
        }

        $files = array();
        $type = 'css';

        if ($pack === 'front-shell-css') {
            $files = array(
                'assets/shell/common.css',
                'assets/shell/toast.css',
                'assets/shell/modal.css',
                'assets/shell/icons.css',
                'assets/shell/site-footer.css',
            );
        } elseif ($pack === 'user-shell-css') {
            $files = array(
                'assets/shell/common.css',
                'assets/shell/toast.css',
                'assets/shell/modal.css',
                'assets/shell/icons.css',
            );
            if ($themeId === 'default') {
                $files[] = 'assets/shell/theme-picker.css';
            }
            $files[] = 'assets/shell/user-shell.css';
            $files[] = 'assets/user.css';
        } elseif ($pack === 'front-shell-js') {
            $type = 'js';
            $files = array(
                'assets/shell/modal.js',
                'assets/shell/common.js',
            );
        } elseif ($pack === 'user-shell-js') {
            $type = 'js';
            $files = array(
                'assets/shell/modal.js',
                'assets/shell/common.js',
                'assets/shell/vs-pick.js',
            );
            if ($themeId === 'default') {
                $files[] = 'assets/shell/theme-picker.js';
            }
            $files[] = 'assets/user.js';
        } elseif ($pack === 'front-css' && $themeId === 'default') {
            $files = array(
                'assets/css/front-common.css',
            );
            $mdPages = array('home', 'detail', 'articles', 'about');
            if ($pageKey === '' || in_array($pageKey, $mdPages, true)) {
                $files[] = 'assets/css/markdown-content.css';
            }
            $pageCssMap = array(
                'home'         => 'assets/css/pages/index.css',
                'apis'         => 'assets/css/pages/apis.css',
                'detail'       => 'assets/css/pages/detail.css',
                'articles'     => 'assets/css/pages/articles.css',
                'about'        => 'assets/css/pages/about.css',
                'links'        => 'assets/css/pages/links.css',
                'applylink'    => 'assets/css/pages/applylink.css',
                'contributors' => 'assets/css/pages/contributors.css',
                'profile'      => 'assets/css/pages/profile.css',
                'sponsor'      => 'assets/css/pages/donate.css',
            );
            if ($pageKey !== '' && isset($pageCssMap[$pageKey])) {
                $files[] = $pageCssMap[$pageKey];
            }
            $files[] = 'assets/css/theme-tokens.css';
            $files[] = 'assets/css/feer-compat.css';
        } elseif ($pack === 'front-css' && $themeId === 'slate') {
            $files = array('assets/theme.css');
        } elseif ($pack === 'front-js' && $themeId === 'default') {
            $type = 'js';
            $files = array(
                'assets/js/front-theme.js',
                'assets/js/shell.js',
                'assets/js/sidebar-close.js',
                'assets/js/external-link-modal.js',
            );
            $pageJsMap = array(
                'home'         => array(
                    'assets/js/playground-response.js',
                    'assets/js/pages/index-terminal.js',
                    'assets/js/pages/index.js',
                ),
                'apis'         => array('assets/js/pages/apis-page.js'),
                'detail'       => array(
                    'assets/js/playground-response.js',
                    'assets/js/pages/detail.js',
                    'assets/js/pages/detail-quickstart.js',
                ),
                'articles'     => array('assets/js/pages/articles-page.js'),
                'about'        => array('assets/js/pages/about-page.js'),
                'links'        => array('assets/js/pages/links-page.js'),
                'applylink'    => array('assets/js/pages/applylink.js'),
                'contributors' => array('assets/js/pages/contributors-page.js'),
                'profile'      => array(
                    'assets/js/pages/profile.js',
                    'assets/js/pages/profile-search.js',
                ),
                'sponsor'      => array('assets/js/pages/donate.js'),
            );
            if ($pageKey !== '' && isset($pageJsMap[$pageKey])) {
                foreach ($pageJsMap[$pageKey] as $rel) {
                    $files[] = $rel;
                }
            }
        } elseif ($pack === 'front-js' && $themeId === 'slate') {
            $type = 'js';
            $files = array('assets/theme.js');
        } else {
            return null;
        }

        $out = array();
        foreach ($files as $rel) {
            $rel = self::sanitizeRel($rel);
            if ($rel === '') {
                continue;
            }
            $full = self::safeThemeFile($themeId, $rel);
            if ($full === '') {
                continue;
            }
            $out[] = $rel;
        }
        if ($out === array()) {
            return null;
        }

        return array('type' => $type, 'files' => $out);
    }

    /**
     * 打包 URL（轻量入口，不经完整 bootstrap）
     *
     * @param string      $pack
     * @param string|null $themeId
     * @param string      $pageKey
     * @return string
     */
    public static function url($pack, $themeId = null, $pageKey = '')
    {
        if (!defined('VS_ROOT')) {
            return '';
        }
        if ($themeId === null || $themeId === '') {
            $themeId = class_exists('ThemeManager') ? ThemeManager::activeId() : 'default';
        }
        $themeId = self::sanitizeThemeId($themeId);
        $pack = self::sanitizePack($pack);
        if ($themeId === '' || $pack === '') {
            return '';
        }
        $resolved = self::resolve($themeId, $pack, $pageKey);
        if ($resolved === null) {
            return '';
        }

        $base = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
        $q = array(
            't' => $themeId,
            'p' => $pack,
            'v' => defined('VS_VERSION') ? VS_VERSION : '1',
        );
        $pageKey = self::sanitizePageKey($pageKey);
        if ($pageKey !== '' && ($pack === 'front-css' || $pack === 'front-js')) {
            $q['page'] = $pageKey;
        }
        return $base . '/core/theme-asset.php?' . http_build_query($q);
    }

    /**
     * @param string $themeId
     * @param string $pack
     * @param string $pageKey
     * @return void
     */
    public static function serve($themeId, $pack, $pageKey = '')
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            http_response_code(405);
            header('Allow: GET, HEAD');
            header('Cache-Control: no-store');
            echo 'method not allowed';
            return;
        }

        $pageKey = self::sanitizePageKey($pageKey);
        $resolved = self::resolve($themeId, $pack, $pageKey);
        if ($resolved === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store');
            echo 'not found';
            return;
        }

        $themeId = self::sanitizeThemeId($themeId);
        $parts = array();
        $mtimeMax = 0;
        foreach ($resolved['files'] as $rel) {
            $full = self::safeThemeFile($themeId, $rel);
            if ($full === '') {
                continue;
            }
            $raw = @file_get_contents($full);
            if ($raw === false) {
                continue;
            }
            $mt = (int) @filemtime($full);
            if ($mt > $mtimeMax) {
                $mtimeMax = $mt;
            }
            // 注释里的路径已白名单，仅便于调试定位源文件
            $parts[] = "/* >> " . str_replace(array('*/', "\n", "\r"), '', $rel) . " */\n" . rtrim($raw) . "\n";
        }
        if ($parts === array()) {
            http_response_code(404);
            header('Cache-Control: no-store');
            echo 'empty';
            return;
        }

        $body = implode("\n", $parts);
        $etag = '"' . sha1($themeId . '|' . $pack . '|' . $pageKey . '|' . $mtimeMax . '|' . strlen($body)) . '"';

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=604800, immutable');
            return;
        }

        $type = $resolved['type'] === 'js' ? 'application/javascript; charset=UTF-8' : 'text/css; charset=UTF-8';
        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=604800, immutable');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        if ($mtimeMax > 0) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtimeMax) . ' GMT');
        }

        if ($method === 'HEAD') {
            header('Content-Length: ' . (string) strlen($body));
            return;
        }

        // 能压则压，降低无 CDN 时的传输体积
        if (!headers_sent() && extension_loaded('zlib') && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
            && strpos((string) $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false
            && !ini_get('zlib.output_compression')) {
            header('Content-Encoding: gzip');
            echo gzencode($body, 6);
            return;
        }
        echo $body;
    }

    /**
     * @param string $themeId
     * @return string
     */
    private static function sanitizeThemeId($themeId)
    {
        $themeId = strtolower(trim((string) $themeId));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $themeId)) {
            return '';
        }
        if (!is_dir(VS_ROOT . '/core/theme/' . $themeId)) {
            return '';
        }
        return $themeId;
    }

    /**
     * @param string $pack
     * @return string
     */
    private static function sanitizePack($pack)
    {
        $pack = strtolower(trim((string) $pack));
        $allow = array(
            'front-shell-css',
            'front-shell-js',
            'front-css',
            'front-js',
            'user-shell-css',
            'user-shell-js',
        );
        return in_array($pack, $allow, true) ? $pack : '';
    }

    /**
     * @param string $rel
     * @return string
     */
    private static function sanitizeRel($rel)
    {
        $rel = str_replace('\\', '/', trim((string) $rel));
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        if (!preg_match('#^assets/[A-Za-z0-9._/-]+$#', $rel)) {
            return '';
        }
        return $rel;
    }

    /**
     * 仅允许已知前台页键；非法参数一律当作空（出基础包，不拼进危险路径）
     *
     * @param string $pageKey
     * @return string
     */
    private static function sanitizePageKey($pageKey)
    {
        $pageKey = strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string) $pageKey));
        if ($pageKey === '') {
            return '';
        }
        $allow = array(
            'home', 'apis', 'detail', 'articles', 'about', 'links',
            'applylink', 'contributors', 'profile', 'sponsor',
        );
        return in_array($pageKey, $allow, true) ? $pageKey : '';
    }

    /**
     * 解析主题内真实文件路径；必须落在 theme/{id}/ 目录下
     *
     * @param string $themeId
     * @param string $rel
     * @return string 绝对路径或空串
     */
    private static function safeThemeFile($themeId, $rel)
    {
        $themeId = self::sanitizeThemeId($themeId);
        $rel = self::sanitizeRel($rel);
        if ($themeId === '' || $rel === '') {
            return '';
        }
        $themeRoot = VS_ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'theme' . DIRECTORY_SEPARATOR . $themeId;
        $full = $themeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($full)) {
            return '';
        }
        $realFile = realpath($full);
        $realRoot = realpath($themeRoot);
        if ($realFile === false || $realRoot === false) {
            return '';
        }
        $prefix = $realRoot . DIRECTORY_SEPARATOR;
        if (strpos($realFile, $prefix) !== 0 && $realFile !== $realRoot) {
            return '';
        }
        return $realFile;
    }
}

}
