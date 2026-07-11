<?php
/**
 * 文件：core/ThemeManager.php
 * 作用：前台主题发现、切换与模板渲染
 *
 * 说明：各主题 CSS/JS 独立存放于 core/theme/{id}/assets/，互不共用。
 */

class ThemeManager
{
    const CONFIG_KEY = 'frontend_theme';
    const DEFAULT_THEME = 'default';

    /** @var array|null */
    private static $navCache = null;

    public static function themesRoot()
    {
        return VS_ROOT . '/core/theme';
    }

    public static function activeId()
    {
        $id = trim((string) Config::get(self::CONFIG_KEY, self::DEFAULT_THEME));
        if ($id === '' || !self::isValidTheme($id)) {
            return self::DEFAULT_THEME;
        }
        return $id;
    }

    public static function themeDir($themeId = null)
    {
        if ($themeId === null) {
            $themeId = self::activeId();
        }
        return self::themesRoot() . '/' . $themeId;
    }

    public static function isValidTheme($themeId)
    {
        $themeId = trim((string) $themeId);
        if ($themeId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/i', $themeId)) {
            return false;
        }
        $dir = self::themesRoot() . '/' . $themeId;
        return is_dir($dir) && is_file($dir . '/theme.json');
    }

    public static function listThemes()
    {
        $root = self::themesRoot();
        if (!is_dir($root)) {
            return array();
        }

        $themes = array();
        $dirs = glob($root . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return array();
        }

        foreach ($dirs as $dir) {
            $id = basename($dir);
            if (!self::isValidTheme($id)) {
                continue;
            }
            $meta = self::readMeta($id);
            $themes[] = array(
                'id'          => $id,
                'name'        => isset($meta['name']) ? (string) $meta['name'] : $id,
                'description' => isset($meta['description']) ? (string) $meta['description'] : '',
                'version'     => isset($meta['version']) ? (string) $meta['version'] : '',
                'author'      => isset($meta['author']) ? (string) $meta['author'] : '',
                'preview_url' => self::previewUrl($id),
            );
        }

        usort($themes, function ($a, $b) {
            if ($a['id'] === self::DEFAULT_THEME) {
                return -1;
            }
            if ($b['id'] === self::DEFAULT_THEME) {
                return 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $themes;
    }

    public static function readMeta($themeId)
    {
        $file = self::themeDir($themeId) . '/theme.json';
        if (!is_file($file)) {
            return array();
        }
        $json = json_decode((string) file_get_contents($file), true);
        return is_array($json) ? $json : array();
    }

    public static function previewUrl($themeId)
    {
        $meta = self::readMeta($themeId);
        if (!empty($meta['preview'])) {
            $rel = ltrim(str_replace('\\', '/', (string) $meta['preview']), '/');
            if (is_file(self::themeDir($themeId) . '/' . $rel)) {
                return self::assetUrl($themeId, $rel);
            }
        }
        foreach (array('assets/preview.svg', 'assets/preview.png', 'assets/preview.jpg') as $rel) {
            if (is_file(self::themeDir($themeId) . '/' . $rel)) {
                return self::assetUrl($themeId, $rel);
            }
        }
        return '';
    }

    public static function setActive($themeId)
    {
        $themeId = trim((string) $themeId);
        if (!self::isValidTheme($themeId)) {
            return '无效的主题';
        }
        Config::set(self::CONFIG_KEY, $themeId);
        return true;
    }

    /**
     * 前台导航（统一排序，各主题自行渲染）
     *
     * @return array<int, array<string, string>>
     */
    public static function navItems()
    {
        if (self::$navCache !== null) {
            return self::$navCache;
        }

        $base = vs_base_url();
        self::$navCache = array(
            array('id' => 'home', 'label' => '首页', 'url' => $base . '/'),
            array('id' => 'apis', 'label' => '全部接口', 'url' => $base . '/apis'),
            array('id' => 'articles', 'label' => '文章', 'url' => $base . '/articles'),
            array('id' => 'contributors', 'label' => '贡献者', 'url' => $base . '/contributors'),
            array('id' => 'links', 'label' => '友情链接', 'url' => $base . '/links'),
            array('id' => 'sponsor', 'label' => '赞助', 'url' => $base . '/sponsor'),
            array('id' => 'about', 'label' => '关于', 'url' => $base . '/about'),
        );

        return self::$navCache;
    }

    public static function assetUrl($themeId, $relative)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        return vs_base_url() . '/core/theme/' . rawurlencode($themeId) . '/' . $relative;
    }

    public static function activeStylesheetHref()
    {
        $themeId = self::activeId();
        $css = self::themeDir($themeId) . '/assets/theme.css';
        if (!is_file($css)) {
            return '';
        }
        return self::assetUrl($themeId, 'assets/theme.css') . '?v=' . VS_VERSION;
    }

    public static function activeScriptHref()
    {
        $themeId = self::activeId();
        $js = self::themeDir($themeId) . '/assets/theme.js';
        if (!is_file($js)) {
            return '';
        }
        return self::assetUrl($themeId, 'assets/theme.js') . '?v=' . VS_VERSION;
    }

    public static function renderBody($pageKey, $pageTitle, array $pageData = array())
    {
        if (!defined('VS_THEME_RENDER')) {
            define('VS_THEME_RENDER', true);
        }

        $themeId = self::activeId();
        $ctx = self::buildContext($pageKey, $pageTitle, $pageData);
        extract($ctx, EXTR_SKIP);

        $layoutDir = self::themeDir($themeId) . '/layout';
        $pageFile = self::resolvePageFile($themeId, $pageKey);

        if (!is_file($layoutDir . '/header.php')) {
            echo '<div class="vs-container"><p class="vs-alert vs-alert--error">主题布局缺失：' . vs_e($themeId) . '</p></div>';
            return;
        }

        if ($pageFile === '') {
            echo '<div class="vs-container"><p class="vs-alert vs-alert--error">主题页面缺失：' . vs_e($pageKey) . '</p></div>';
            return;
        }

        include $layoutDir . '/header.php';
        include $pageFile;
        if (is_file($layoutDir . '/footer.php')) {
            include $layoutDir . '/footer.php';
        }
    }

    private static function resolvePageFile($themeId, $pageKey)
    {
        $pageKey = preg_replace('/[^a-z0-9_-]/i', '', (string) $pageKey);
        $file = self::themeDir($themeId) . '/pages/' . $pageKey . '.php';
        return is_file($file) ? $file : '';
    }

    private static function buildContext($pageKey, $pageTitle, array $pageData)
    {
        $base = vs_base_url();
        $loggedIn = UserAuth::check();
        $authUrl = $loggedIn ? ($base . '/user/index') : ($base . '/user/login');

        return array_merge(
            array(
                'vsBase'       => $base,
                'siteName'     => SiteContext::siteName(),
                'siteDesc'     => SiteContext::siteDescription(),
                'pageKey'      => $pageKey,
                'pageTitle'    => $pageTitle,
                'navItems'     => self::navItems(),
                'activeNav'    => $pageKey,
                'userLoggedIn' => $loggedIn,
                'authUrl'      => $authUrl,
                'authLabel'    => $loggedIn ? '进入用户中心' : '登录 / 注册',
                'themeId'      => self::activeId(),
            ),
            $pageData
        );
    }
}
