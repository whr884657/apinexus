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
    /** 全站主题设置总表（JSON：{ "default": {...}, "slate": {...} }） */
    const SETTINGS_CONFIG_KEY = 'themesettings';
    const DEFAULT_THEME = 'default';

    /** @var array|null */
    private static $navCache = null;

    /** @var array|null 当前主题 settings 请求内缓存 */
    private static $themeSettingCache = null;

    /** @var array|null */
    private static $themeSettingSchemaDefaults = null;

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

        // 扫描主题包后，确保 MySQL themesettings 中每个主题都有独立配置段
        self::syncThemesettingsEntries($themes);

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
                return self::assetUrl($themeId, $rel) . '?v=' . VS_VERSION;
            }
        }
        foreach (array('preview.png', 'preview.jpg', 'preview.webp', 'assets/preview.png', 'assets/preview.svg') as $rel) {
            if (is_file(self::themeDir($themeId) . '/' . $rel)) {
                return self::assetUrl($themeId, $rel) . '?v=' . VS_VERSION;
            }
        }
        return '';
    }

    public static function setActive($themeId)
    {
        $themeId = trim((string) $themeId);
        if ($themeId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/i', $themeId)) {
            return '无效的主题';
        }
        if (!self::isValidTheme($themeId)) {
            return '无效的主题';
        }
        Config::set(self::CONFIG_KEY, $themeId);
        self::clearThemeSettingCache();
        return true;
    }

    /**
     * 主题是否已启用（当前正在使用）
     *
     * @param string $themeId
     * @return bool
     */
    public static function isThemeEnabled($themeId)
    {
        return self::isValidTheme($themeId) && $themeId === self::activeId();
    }

    /**
     * 历史磁盘路径（仅一次性迁移读取，v5.3.0 起不再写入）
     *
     * @param string $themeId
     * @param string $filename
     * @return string
     */
    public static function themeDataFile($themeId, $filename = 'settings.json')
    {
        if (!self::isValidTheme($themeId)) {
            return '';
        }
        $filename = basename((string) $filename);
        if (!preg_match('/^[a-z0-9_-]+\.json$/i', $filename)) {
            return '';
        }
        return self::themeDir($themeId) . '/data/' . $filename;
    }

    /**
     * 读取 themesettings 总表（Config）
     *
     * @return array<string, array>
     */
    public static function readAllThemesettings()
    {
        $raw = Config::get(self::SETTINGS_CONFIG_KEY, '{}');
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }
        $out = array();
        foreach ($decoded as $themeId => $payload) {
            $themeId = trim((string) $themeId);
            if ($themeId === '' || !is_array($payload)) {
                continue;
            }
            $out[$themeId] = $payload;
        }
        return $out;
    }

    /**
     * 写回 themesettings 总表
     *
     * @param array<string, array> $all
     * @return true|string
     */
    public static function writeAllThemesettings(array $all)
    {
        $clean = array();
        foreach ($all as $themeId => $payload) {
            $themeId = trim((string) $themeId);
            if ($themeId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/i', $themeId)) {
                continue;
            }
            $clean[$themeId] = is_array($payload) ? $payload : array();
        }
        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return '主题设置编码失败';
        }
        try {
            Config::set(self::SETTINGS_CONFIG_KEY, $encoded);
        } catch (Exception $e) {
            return '写入主题设置失败';
        }
        self::clearThemeSettingCache();
        return true;
    }

    /**
     * 扫描主题包后，为缺失主题自动补齐空配置段；并一次性迁入旧 settings.json
     *
     * @param array|null $themes listThemes() 结果；null 时自行扫描
     * @return void
     */
    public static function syncThemesettingsEntries($themes = null)
    {
        if (!class_exists('Config') || !class_exists('InstallChecker') || !InstallChecker::isInstalled()) {
            return;
        }

        if ($themes === null) {
            // 避免 listThemes ↔ sync 递归：此处只扫目录
            $themes = array();
            $root = self::themesRoot();
            if (is_dir($root)) {
                $dirs = glob($root . '/*', GLOB_ONLYDIR);
                if (is_array($dirs)) {
                    foreach ($dirs as $dir) {
                        $id = basename($dir);
                        if (self::isValidTheme($id)) {
                            $themes[] = array('id' => $id);
                        }
                    }
                }
            }
        }

        $all = self::readAllThemesettings();
        $changed = false;

        foreach ($themes as $theme) {
            $id = isset($theme['id']) ? trim((string) $theme['id']) : '';
            if ($id === '' || !self::isValidTheme($id)) {
                continue;
            }
            if (!isset($all[$id]) || !is_array($all[$id])) {
                $migrated = self::readLegacyThemeDataFile($id);
                $all[$id] = is_array($migrated) ? $migrated : array();
                $changed = true;
            } elseif ($all[$id] === array()) {
                // 库中为空对象时，尝试迁入磁盘遗留配置（仅一次有数据才写）
                $migrated = self::readLegacyThemeDataFile($id);
                if (!empty($migrated)) {
                    $all[$id] = $migrated;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            self::writeAllThemesettings($all);
        }
    }

    /**
     * 读取旧版磁盘 settings.json（迁移用）
     *
     * @param string $themeId
     * @return array
     */
    private static function readLegacyThemeDataFile($themeId)
    {
        $file = self::themeDataFile($themeId, 'settings.json');
        if ($file === '' || !is_file($file)) {
            return array();
        }
        $json = json_decode((string) file_get_contents($file), true);
        return is_array($json) ? $json : array();
    }

    /**
     * 读取指定主题的设置（来自 MySQL config.themesettings）
     *
     * @param string $themeId
     * @param string $filename 保留参数兼容旧调用，仅 settings.json 有效
     * @return array<string, mixed>
     */
    public static function readThemeData($themeId, $filename = 'settings.json')
    {
        $themeId = trim((string) $themeId);
        if ($themeId === '' || !self::isValidTheme($themeId)) {
            return array();
        }
        $filename = basename((string) $filename);
        if ($filename !== '' && strtolower($filename) !== 'settings.json') {
            return array();
        }

        $all = self::readAllThemesettings();
        if (!isset($all[$themeId]) || !is_array($all[$themeId])) {
            $migrated = self::readLegacyThemeDataFile($themeId);
            $all[$themeId] = $migrated;
            self::writeAllThemesettings($all);
            return $migrated;
        }
        return $all[$themeId];
    }

    /**
     * 写入指定主题的设置到 MySQL（themesettings 中以主题 ID 为键）
     *
     * @param string $themeId
     * @param array<string, mixed> $data
     * @param string $filename 保留参数兼容旧调用
     * @return true|string
     */
    public static function writeThemeData($themeId, array $data, $filename = 'settings.json')
    {
        $themeId = trim((string) $themeId);
        if ($themeId === '' || !self::isValidTheme($themeId)) {
            return '无效的主题';
        }
        $filename = basename((string) $filename);
        if ($filename !== '' && strtolower($filename) !== 'settings.json') {
            return '不支持的配置文件';
        }

        $all = self::readAllThemesettings();
        $all[$themeId] = $data;
        return self::writeAllThemesettings($all);
    }

    /**
     * 清除 themeSetting() 请求内缓存
     *
     * @return void
     */
    public static function clearThemeSettingCache()
    {
        self::$themeSettingCache = null;
        self::$themeSettingSchemaDefaults = null;
    }

    /**
     * 读取当前启用主题的配置项
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function themeSetting($key, $default = '')
    {
        if (self::$themeSettingCache === null) {
            $themeId = self::activeId();
            self::$themeSettingCache = self::readThemeData($themeId);
            self::$themeSettingSchemaDefaults = array();
            foreach (self::getSettingsSchema($themeId) as $field) {
                if (!empty($field['key']) && array_key_exists('default', $field)) {
                    self::$themeSettingSchemaDefaults[$field['key']] = $field['default'];
                }
            }
        }

        $key = (string) $key;
        if (array_key_exists($key, self::$themeSettingCache)) {
            return self::$themeSettingCache[$key];
        }
        if (is_array(self::$themeSettingSchemaDefaults) && array_key_exists($key, self::$themeSettingSchemaDefaults)) {
            return self::$themeSettingSchemaDefaults[$key];
        }
        return $default;
    }

    /**
     * 读取主题配置字符串（trim 后）
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function themeSettingStr($key, $default = '')
    {
        $val = self::themeSetting($key, $default);
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        $str = trim((string) $val);
        return $str !== '' ? $str : trim((string) $default);
    }

    /**
     * 读取主题配置布尔值
     *
     * @param string $key
     * @param bool   $default
     * @return bool
     */
    public static function themeSettingBool($key, $default = false)
    {
        $val = self::themeSetting($key, $default);
        if (is_bool($val)) {
            return $val;
        }
        if ($val === '1' || $val === 1 || $val === 'true' || $val === 'on') {
            return true;
        }
        if ($val === '0' || $val === 0 || $val === 'false' || $val === 'off') {
            return false;
        }
        if ($val === '' || $val === null) {
            return (bool) $default;
        }
        return (bool) $val;
    }

    /**
     * theme.json 中声明的可配置项
     *
     * @param string $themeId
     * @return array<int, array<string, mixed>>
     */
    public static function getSettingsSchema($themeId)
    {
        $meta = self::readMeta($themeId);
        if (empty($meta['settings']) || !is_array($meta['settings'])) {
            return array();
        }

        $allowedTypes = array('text', 'textarea', 'number', 'checkbox', 'select');
        $schema = array();
        foreach ($meta['settings'] as $item) {
            if (!is_array($item) || empty($item['key'])) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_]/i', '', (string) $item['key']);
            if ($key === '') {
                continue;
            }
            $type = isset($item['type']) ? (string) $item['type'] : 'text';
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }
            $schema[] = array(
                'key'         => $key,
                'label'       => isset($item['label']) ? (string) $item['label'] : $key,
                'type'        => $type,
                'placeholder' => isset($item['placeholder']) ? (string) $item['placeholder'] : '',
                'default'     => isset($item['default']) ? $item['default'] : '',
                'options'     => ($type === 'select' && !empty($item['options']) && is_array($item['options'])) ? $item['options'] : array(),
            );
        }

        return $schema;
    }

    /**
     * 根据 schema 清洗提交的设置值
     *
     * @param string $themeId
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitizeThemeSettingsInput($themeId, array $input)
    {
        $schema = self::getSettingsSchema($themeId);
        $out = array();
        foreach ($schema as $field) {
            $key = $field['key'];
            if ($field['type'] === 'checkbox') {
                $out[$key] = !empty($input[$key]) && $input[$key] !== '0' && $input[$key] !== 'false';
                continue;
            }
            if ($field['type'] === 'select') {
                $value = isset($input[$key]) ? trim((string) $input[$key]) : '';
                $allowed = array();
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $opt) {
                        if (is_array($opt) && isset($opt['value'])) {
                            $allowed[] = (string) $opt['value'];
                        }
                    }
                }
                if ($value === '' && isset($field['default'])) {
                    $value = (string) $field['default'];
                }
                if (!empty($allowed) && !in_array($value, $allowed, true)) {
                    $value = isset($field['default']) ? (string) $field['default'] : '';
                }
                $out[$key] = $value;
                continue;
            }
            $value = isset($input[$key]) ? trim((string) $input[$key]) : '';
            if ($field['type'] === 'number' && $value !== '' && !is_numeric($value)) {
                $value = '';
            }
            $out[$key] = $value;
        }
        return $out;
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

    /**
     * 用户中心菜单（各主题共用路由，布局由主题包渲染）
     *
     * @return array<int, array<string, string>>
     */
    public static function userMenuGroups()
    {
        $base = vs_base_url();
        $groups = array(
            array('id' => 'dashboard', 'title' => '控制台', 'icon' => 'dashboard', 'url' => $base . '/user/index'),
            array('id' => 'api-manage', 'title' => 'API 管理', 'icon' => 'cloud', 'url' => $base . '/user/api-manage', 'require_developer' => true),
            array('id' => 'keys', 'title' => '令牌管理', 'icon' => 'share', 'url' => $base . '/user/keys'),
            array('id' => 'recharge', 'title' => '充值中心', 'icon' => 'archive', 'url' => $base . '/user/recharge'),
            array('id' => 'points', 'title' => '积分变动', 'icon' => 'archive', 'url' => $base . '/user/points'),
            array('id' => 'api-list', 'title' => '接口列表', 'icon' => 'folder', 'url' => $base . '/user/apis'),
            array('id' => 'account', 'title' => '账号设置', 'icon' => 'user', 'url' => $base . '/user/account'),
        );

        if (!UserRole::currentCanPublishApi()) {
            $filtered = array();
            foreach ($groups as $group) {
                if (!empty($group['require_developer'])) {
                    continue;
                }
                $filtered[] = $group;
            }
            return $filtered;
        }

        return $groups;
    }

    /**
     * 解析当前激活主题内文件（严格模式：不回退其他主题）
     *
     * @param string $relative
     * @return string
     */
    public static function resolveActiveThemeFile($relative)
    {
        $themeId = self::activeId();
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== false) {
            return '';
        }

        $root = realpath(self::themeDir($themeId));
        $file = realpath(self::themeDir($themeId) . '/' . $relative);
        if ($root === false || $file === false || strpos($file, $root) !== 0 || !is_file($file)) {
            return '';
        }

        return $file;
    }

    /**
     * @deprecated 仅兼容旧调用，内部转严格解析
     */
    public static function resolveThemeFile($relative, $themeId = null)
    {
        if ($themeId !== null && $themeId !== self::activeId()) {
            return '';
        }
        return self::resolveActiveThemeFile($relative);
    }

    /**
     * @return array<int, string>
     */
    public static function userStylesheetHrefs()
    {
        $themeId = self::activeId();
        $userCss = self::themeDir($themeId) . '/assets/user.css';
        if (!is_file($userCss)) {
            return array();
        }
        return array(self::assetUrl($themeId, 'assets/user.css') . '?v=' . VS_VERSION);
    }

    /**
     * @return array<int, string>
     */
    public static function authStylesheetHrefs()
    {
        $themeId = self::activeId();
        $authCss = self::themeDir($themeId) . '/assets/auth.css';
        if (!is_file($authCss)) {
            return array();
        }
        return array(self::assetUrl($themeId, 'assets/auth.css') . '?v=' . VS_VERSION);
    }

    /**
     * @return string
     */
    public static function authScriptHref()
    {
        $themeId = self::activeId();
        $js = self::themeDir($themeId) . '/assets/auth.js';
        if (!is_file($js)) {
            return '';
        }
        return self::assetUrl($themeId, 'assets/auth.js') . '?v=' . VS_VERSION;
    }

    /**
     * @return string
     */
    public static function userScriptHref()
    {
        $themeId = self::activeId();
        $js = self::themeDir($themeId) . '/assets/user.js';
        if (!is_file($js)) {
            return '';
        }
        return self::assetUrl($themeId, 'assets/user.js') . '?v=' . VS_VERSION;
    }

    /**
     * 加载当前主题认证布局函数
     *
     * @return void
     */
    public static function ensureAuthLayoutLoaded()
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $file = self::resolveActiveThemeFile('user/auth/layout.php');
        if ($file !== '') {
            require_once $file;
            $loaded = true;
        }
    }

    /**
     * 主题认证页头部（仅加载当前主题包资源）
     *
     * @param string $pageTitle
     * @return void
     */
    public static function renderThemeAuthHead($pageTitle)
    {
        AuthSecurity::sendSecurityHeaders();
        self::ensureAuthLayoutLoaded();
        if (!function_exists('vs_theme_auth_head')) {
            echo '<div class="vs-alert vs-alert--error">认证页主题布局缺失</div>';
            return;
        }
        vs_theme_auth_head($pageTitle);
    }

    /**
     * 主题认证页底部
     *
     * @param string $inlineJs
     * @return void
     */
    public static function renderThemeAuthFoot($inlineJs = '')
    {
        self::ensureAuthLayoutLoaded();
        if (!function_exists('vs_theme_auth_foot')) {
            echo '</body></html>';
            return;
        }
        vs_theme_auth_foot($inlineJs);
    }

    /**
     * 用户中心布局开始
     *
     * @param string $pageTitle
     * @param string $activeMenu
     * @param string $headerActions 标题行右侧操作区 HTML（可选）
     * @return void
     */
    public static function renderUserLayoutStart($pageTitle, $activeMenu = '', $headerActions = '')
    {
        $file = self::resolveActiveThemeFile('user/layout.php');
        if ($file === '') {
            echo '<div class="vs-alert vs-alert--error">用户中心主题布局缺失</div>';
            return;
        }
        require_once $file;
        if (function_exists('vs_theme_user_layout_start')) {
            vs_theme_user_layout_start($pageTitle, $activeMenu, $headerActions);
        }
    }

    /**
     * 用户中心布局结束
     *
     * @param array $extraScripts
     * @return void
     */
    public static function renderUserLayoutEnd(array $extraScripts = array())
    {
        $file = self::resolveActiveThemeFile('user/layout.php');
        if ($file === '' || !function_exists('vs_theme_user_layout_end')) {
            echo '</body></html>';
            return;
        }
        vs_theme_user_layout_end($extraScripts);
    }

    /**
     * 认证页（登录/注册/忘记密码/绑定）视图
     *
     * @param string $pageKey
     * @param string $pageTitle
     * @param array  $pageData
     * @return void
     */
    public static function renderAuthPage($pageKey, $pageTitle, array $pageData = array())
    {
        if (!defined('VS_THEME_RENDER')) {
            define('VS_THEME_RENDER', true);
        }

        $pageKey = preg_replace('/[^a-z0-9_-]/i', '', (string) $pageKey);
        $viewFile = self::resolveActiveThemeFile('user/auth/' . $pageKey . '.php');
        if ($viewFile === '') {
            echo '<div class="vs-alert vs-alert--error">认证页主题模板缺失：' . vs_e($pageKey) . '</div>';
            return;
        }

        $ctx = array_merge(
            array(
                'vsBase'    => vs_base_url(),
                'siteName'  => SiteContext::siteName(),
                'pageTitle' => $pageTitle,
                'pageKey'   => $pageKey,
                'themeId'   => self::activeId(),
            ),
            $pageData
        );
        extract($ctx, EXTR_SKIP);

        require $viewFile;
    }

    public static function assetUrl($themeId, $relative)
    {
        $themeId = trim((string) $themeId);
        if (!self::isValidTheme($themeId)) {
            return '';
        }
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== false) {
            return '';
        }
        return vs_base_url() . '/core/theme/' . rawurlencode($themeId) . '/' . $relative;
    }

    /**
     * 默认主题 · 参考 UI 资源清单（多 CSS/JS）
     *
     * @param string $pageKey
     * @return array{css: array, js: array, head_scripts: array, body_class: string, skip_legacy: bool}
     */
    public static function defaultFrontendAssets($pageKey)
    {
        $pageKey = preg_replace('/[^a-z0-9_-]/i', '', (string) $pageKey);
        $v = VS_VERSION;
        $asset = function ($rel) use ($v) {
            return self::assetUrl('default', $rel) . '?v=' . $v;
        };

        $css = array(
            'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@400;500;700;900&display=swap',
            $asset('assets/css/front-common.css'),
            $asset('assets/css/markdown-content.css'),
        );

        $pageCssMap = array(
            'home'         => 'assets/css/pages/index.css',
            'apis'         => 'assets/css/pages/apis.css',
            'detail'       => 'assets/css/pages/detail.css',
            'articles'     => 'assets/css/pages/articles.css',
            'about'        => 'assets/css/pages/about.css',
            'links'        => 'assets/css/pages/links.css',
            'applylink'    => 'assets/css/pages/applylink.css',
            'contributors' => 'assets/css/pages/contributors.css',
            'sponsor'      => 'assets/css/pages/donate.css',
        );
        if (isset($pageCssMap[$pageKey])) {
            $css[] = $asset($pageCssMap[$pageKey]);
        }
        $css[] = $asset('assets/css/theme-tokens.css');
        $css[] = $asset('assets/css/feer-compat.css');

        $js = array(
            $asset('assets/js/front-theme.js'),
            $asset('assets/js/shell.js'),
            $asset('assets/js/sidebar-close.js'),
            $asset('assets/js/external-link-modal.js'),
        );

        $pageJsMap = array(
            'home'         => array(
                'assets/js/playground-response.js',
                'assets/js/pages/index-terminal.js',
                'assets/js/pages/index.js',
            ),
            'apis'         => array('assets/js/pages/apis-page.js'),
            'detail'       => array(
                'https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js',
                'assets/js/playground-response.js',
                'assets/js/pages/detail.js',
            ),
            'articles'     => array('assets/js/pages/articles-page.js'),
            'about'        => array('assets/js/pages/about-page.js'),
            'links'        => array('assets/js/pages/links-page.js'),
            'applylink'    => array('assets/js/pages/applylink.js'),
            'contributors' => array('assets/js/pages/contributors-page.js'),
            'sponsor'      => array('assets/js/pages/donate.js'),
        );
        if (isset($pageJsMap[$pageKey])) {
            foreach ($pageJsMap[$pageKey] as $rel) {
                $rel = (string) $rel;
                if (preg_match('#^https?://#i', $rel)) {
                    $js[] = $rel;
                } else {
                    $js[] = $asset($rel);
                }
            }
        }

        $fallback = htmlspecialchars($asset('assets/vendor/tailwind.min.js'), ENT_QUOTES, 'UTF-8');
        return array(
            'css'           => $css,
            'js'            => $js,
            'head_scripts'  => array(
                '<script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src=\'' . $fallback . '\';"></script>',
            ),
            'body_class'    => 'vs-body feer-front',
            'skip_legacy'   => true,
        );
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
        $authAvatarUrl = '';
        if ($loggedIn) {
            $authUser = UserAuth::user();
            if (is_array($authUser) && class_exists('UserAvatar')) {
                $authAvatarUrl = UserAvatar::resolve($authUser);
            }
        }

        return array_merge(
            array(
                'vsBase'         => $base,
                'siteName'       => SiteContext::siteName(),
                'siteDesc'       => SiteContext::siteDescription(),
                'pageKey'        => $pageKey,
                'pageTitle'      => $pageTitle,
                'navItems'       => self::navItems(),
                'activeNav'      => $pageKey,
                'userLoggedIn'   => $loggedIn,
                'authUrl'        => $authUrl,
                'authLabel'      => $loggedIn ? '用户中心' : '登录 / 注册',
                'authAvatarUrl'  => $authAvatarUrl,
                'themeId'        => self::activeId(),
            ),
            $pageData
        );
    }
}
