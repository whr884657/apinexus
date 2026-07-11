<?php
/**
 * 文件：core/ThemeManager.php
 * 作用：前台主题发现、切换与模板渲染
 *
 * 说明：主题目录位于 core/theme/{id}/，页面 PHP 仅负责路由，内容由主题模板输出。
 */

class ThemeManager
{
    const CONFIG_KEY = 'frontend_theme';
    const DEFAULT_THEME = 'default';

    /** @var array|null */
    private static $navCache = null;

    /**
     * 主题根目录
     *
     * @return string
     */
    public static function themesRoot()
    {
        return VS_ROOT . '/core/theme';
    }

    /**
     * 当前启用的主题 ID
     *
     * @return string
     */
    public static function activeId()
    {
        $id = trim((string) Config::get(self::CONFIG_KEY, self::DEFAULT_THEME));
        if ($id === '' || !self::isValidTheme($id)) {
            return self::DEFAULT_THEME;
        }
        return $id;
    }

    /**
     * 主题目录绝对路径
     *
     * @param string|null $themeId
     * @return string
     */
    public static function themeDir($themeId = null)
    {
        if ($themeId === null) {
            $themeId = self::activeId();
        }
        return self::themesRoot() . '/' . $themeId;
    }

    /**
     * @param string $themeId
     * @return bool
     */
    public static function isValidTheme($themeId)
    {
        $themeId = trim((string) $themeId);
        if ($themeId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/i', $themeId)) {
            return false;
        }
        $dir = self::themesRoot() . '/' . $themeId;
        return is_dir($dir) && is_file($dir . '/theme.json');
    }

    /**
     * 扫描可用主题
     *
     * @return array<int, array<string, string>>
     */
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

    /**
     * @param string $themeId
     * @return array
     */
    public static function readMeta($themeId)
    {
        $file = self::themeDir($themeId) . '/theme.json';
        if (!is_file($file)) {
            return array();
        }
        $json = json_decode((string) file_get_contents($file), true);
        return is_array($json) ? $json : array();
    }

    /**
     * 保存当前主题
     *
     * @param string $themeId
     * @return true|string
     */
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
     * 前台导航项
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
            array('id' => 'apis', 'label' => '全部接口', 'url' => $base . '/apis.php'),
            array('id' => 'articles', 'label' => '文章', 'url' => $base . '/articles.php'),
            array('id' => 'links', 'label' => '友情链接', 'url' => $base . '/links.php'),
            array('id' => 'sponsor', 'label' => '赞助', 'url' => $base . '/sponsor.php'),
            array('id' => 'about', 'label' => '关于', 'url' => $base . '/about.php'),
        );

        return self::$navCache;
    }

    /**
     * 主题附加 CSS（相对 assets/css/ 或绝对 URL）
     *
     * @return array<int, string>
     */
    public static function themeCssFiles()
    {
        $files = array();
        $themeId = self::activeId();
        $css = self::themeDir($themeId) . '/assets/theme.css';
        if (is_file($css)) {
            $files[] = 'theme/' . $themeId . '/theme.css';
        }
        return $files;
    }

    /**
     * 主题静态资源 URL
     *
     * @param string $themeId
     * @param string $relative
     * @return string
     */
    public static function assetUrl($themeId, $relative)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        return vs_base_url() . '/core/theme/' . rawurlencode($themeId) . '/' . $relative;
    }

    /**
     * 渲染完整前台页面（head 与 foot 由调用方负责）
     *
     * @param string $pageKey
     * @param string $pageTitle
     * @param array  $pageData
     * @return void
     */
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

        if (!is_file($layoutDir . '/header.php') || !is_file($pageFile)) {
            echo '<div class="vs-container"><p class="vs-alert vs-alert--error">主题模板缺失，请检查 core/theme/' . vs_e($themeId) . '</p></div>';
            return;
        }

        include $layoutDir . '/header.php';
        echo '<main class="vs-main vs-frontend-main">' . "\n";
        echo '<div class="vs-container">' . "\n";
        include $pageFile;
        echo '</div></main>' . "\n";
        if (is_file($layoutDir . '/footer.php')) {
            include $layoutDir . '/footer.php';
        }
    }

    /**
     * @param string $themeId
     * @param string $pageKey
     * @return string
     */
    private static function resolvePageFile($themeId, $pageKey)
    {
        $pageKey = preg_replace('/[^a-z0-9_-]/i', '', (string) $pageKey);
        $file = self::themeDir($themeId) . '/pages/' . $pageKey . '.php';
        if (is_file($file)) {
            return $file;
        }
        return self::themeDir(self::DEFAULT_THEME) . '/pages/' . $pageKey . '.php';
    }

    /**
     * @param string $pageKey
     * @param string $pageTitle
     * @param array  $pageData
     * @return array
     */
    private static function buildContext($pageKey, $pageTitle, array $pageData)
    {
        $base = vs_base_url();
        $loggedIn = UserAuth::check();
        $authUrl = $loggedIn ? ($base . '/user/index.php') : ($base . '/user/login.php');
        $registerUrl = $base . '/user/register.php';

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
                'registerUrl'  => $registerUrl,
                'authLabel'    => $loggedIn ? '进入用户中心' : '登录 / 注册',
                'themeId'      => self::activeId(),
            ),
            $pageData
        );
    }
}
