<?php
/**
 * 文件：core/ApiQuickstart.php
 * 作用：默认主题 API 详情「快速上手」——从 aidoc 的 :::qs 短码解析多语言示例
 *
 * 存储格式（AI 生成 / 人工编辑统一）：
 * :::qs lang=curl auth=query
 * ...
 * :::
 *
 * auth 可选，缺省 query。图标：assets/img/lang/（灰版 *.svg + 彩版 *-color.svg；curl 仅一份）
 */

class ApiQuickstart
{
    /** 缺省鉴权方式 */
    const AUTH_DEFAULT = 'query';

    /**
     * @return array<int,array{id:string,label:string,icon:string}>
     */
    public static function langMeta()
    {
        return array(
            array('id' => 'curl', 'label' => 'cURL', 'icon' => 'curl'),
            array('id' => 'typescript', 'label' => 'TypeScript', 'icon' => 'typescript'),
            array('id' => 'browser', 'label' => 'Browser', 'icon' => 'browser'),
            array('id' => 'python', 'label' => 'Python', 'icon' => 'python'),
            array('id' => 'go', 'label' => 'Go', 'icon' => 'go'),
            array('id' => 'java', 'label' => 'Java', 'icon' => 'java'),
            array('id' => 'php', 'label' => 'PHP', 'icon' => 'php'),
            array('id' => 'cpp', 'label' => 'C++', 'icon' => 'cpp'),
            array('id' => 'rust', 'label' => 'Rust', 'icon' => 'rust'),
        );
    }

    /**
     * @return array<string,string> id => label
     */
    public static function langMap()
    {
        $map = array();
        foreach (self::langMeta() as $row) {
            $map[$row['id']] = $row['label'];
        }
        return $map;
    }

    /**
     * @return array<string,string>
     */
    public static function authLabels()
    {
        return array(
            'query'  => 'Query 参数',
            'header' => 'Header(X-API-Key)',
            'bearer' => 'Bearer Token',
        );
    }

    /**
     * @param string $auth
     * @return string
     */
    public static function authLabel($auth)
    {
        $auth = self::normalizeAuthId($auth);
        $map = self::authLabels();
        return isset($map[$auth]) ? $map[$auth] : $map[self::AUTH_DEFAULT];
    }

    /**
     * @param string $icon
     * @param bool   $color
     * @return string
     */
    public static function iconUrl($icon, $color = false)
    {
        $icon = preg_replace('/[^a-z0-9_-]/i', '', (string) $icon);
        if ($icon === '') {
            return '';
        }
        $file = ($icon === 'curl') ? 'lang/curl.svg' : ('lang/' . $icon . ($color ? '-color.svg' : '.svg'));
        if (class_exists('SiteMedia')) {
            $url = SiteMedia::imgUrl($file);
            if ($url !== '') {
                return $url;
            }
        }
        $base = rtrim(vs_base_url(), '/') . '/assets/img/lang/';
        if ($icon === 'curl') {
            return $base . 'curl.svg';
        }
        return $base . $icon . ($color ? '-color.svg' : '.svg');
    }

    /**
     * 语言 → 图标 URL（供详情页 JS 兜底，切换鉴权时不得依赖样本字段是否带齐图标）
     *
     * @return array<string,array{label:string,icon_gray:string,icon_color:string,single_icon:int,syn:string}>
     */
    public static function langIconMap()
    {
        $map = array();
        foreach (self::langMeta() as $meta) {
            $id = $meta['id'];
            $icon = $meta['icon'];
            $map[$id] = array(
                'label'       => $meta['label'],
                'icon_gray'   => self::iconUrl($icon, false),
                'icon_color'  => self::iconUrl($icon, true),
                'single_icon' => ($icon === 'curl') ? 1 : 0,
                'syn'         => self::syntaxLang($id),
            );
        }
        return $map;
    }

    /**
     * 从 aidoc 解析各语言代码（不写死示例）
     *
     * @param string     $aidoc
     * @param array|null $keyways 接口配置的鉴权方式；null 则按 aidoc 内 auth 推断
     * @return array<int,array{id:string,label:string,icon:string,code:string,icon_gray:string,icon_color:string,single_icon:int,syn:string}>
     */
    public static function samplesFromAidoc($aidoc, $keyways = null)
    {
        $bundle = self::qsBundleFromAidoc($aidoc, $keyways);
        $auths = isset($bundle['auths']) && is_array($bundle['auths']) ? $bundle['auths'] : array();
        if (empty($auths)) {
            return array();
        }
        $auth = $auths[0];
        $byAuth = isset($bundle['byAuth']) && is_array($bundle['byAuth']) ? $bundle['byAuth'] : array();
        return isset($byAuth[$auth]) && is_array($byAuth[$auth]) ? $byAuth[$auth] : array();
    }

    /**
     * 多鉴权 bundle（detail 页 window.detailQsBundle）
     *
     * @param string     $aidoc
     * @param array|null $keyways
     * @return array{auths:array<int,string>,authLabels:array<string,string>,byAuth:array<string,array>}
     */
    public static function qsBundleFromAidoc($aidoc, $keyways = null)
    {
        $parsed = self::parseQsBlocks((string) $aidoc);
        if ($parsed === array()) {
            $parsed = self::parseFenceBlocksAsQs((string) $aidoc);
        }

        $authOrder = self::resolveAuthOrder($parsed, $keyways);
        $authLabels = self::authLabels();
        $byAuth = array();

        foreach ($authOrder as $auth) {
            $samples = self::buildSamplesForAuth($parsed, $auth);
            if ($samples !== array()) {
                $byAuth[$auth] = $samples;
            }
        }

        if ($byAuth === array() && $parsed !== array()) {
            $samples = self::buildSamplesForAuth($parsed, self::AUTH_DEFAULT);
            if ($samples !== array()) {
                $byAuth[self::AUTH_DEFAULT] = $samples;
                $authOrder = array(self::AUTH_DEFAULT);
            }
        }

        $auths = array();
        foreach ($authOrder as $auth) {
            if (isset($byAuth[$auth])) {
                $auths[] = $auth;
            }
        }

        return array(
            'auths'      => $auths,
            'authLabels' => $authLabels,
            'byAuth'     => $byAuth,
        );
    }

    /**
     * 规范化 AI 输出：提取 :::qs 块并按 auth×lang 重排；剥离 HTML / 高亮泄漏
     *
     * @param string $raw
     * @return string
     */
    public static function normalizeAidocBlocks($raw)
    {
        $parsed = self::parseQsBlocks((string) $raw);
        if ($parsed === array()) {
            $parsed = self::parseFenceBlocksAsQs((string) $raw);
        }
        if ($parsed === array()) {
            return '';
        }

        $authOrder = self::resolveAuthOrder($parsed, null);
        $chunks = array();
        foreach ($authOrder as $auth) {
            foreach (self::langMeta() as $meta) {
                $id = $meta['id'];
                $code = self::codeForAuthLang($parsed, $auth, $id);
                if ($code === '') {
                    continue;
                }
                $line = ':::qs lang=' . $id;
                if ($auth !== self::AUTH_DEFAULT) {
                    $line .= ' auth=' . $auth;
                }
                $chunks[] = $line . "\n" . $code . "\n:::";
            }
        }
        return implode("\n\n", $chunks);
    }

    /**
     * @param string $text
     * @return array<string,array<string,string>> auth => lang => code
     */
    public static function parseQsBlocks($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        // 去掉包住短码的 markdown 围栏，避免 AI 输出 ```:::qs ...:::``` 解析失败
        $text = preg_replace('/^```(?:text|markdown|md|qs)?\s*\n(?=:::)/im', '', $text);
        $text = preg_replace('/(^:::[\s\S]*?^:::)\s*\n```\s*$/m', '$1', $text);
        $map = array();
        if (!preg_match_all(
            '/^:::\s*qs\s+([^\n]+)\n([\s\S]*?)^:::\s*$/im',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            return $map;
        }
        foreach ($matches as $m) {
            $attrs = self::parseQsAttrs($m[1]);
            $lang = isset($attrs['lang']) ? $attrs['lang'] : '';
            if ($lang === '') {
                continue;
            }
            $auth = isset($attrs['auth']) ? $attrs['auth'] : self::AUTH_DEFAULT;
            if (!isset($map[$auth])) {
                $map[$auth] = array();
            }
            $map[$auth][$lang] = self::cleanCodeBlock($m[2]);
        }
        return $map;
    }

    /**
     * 兼容旧格式 lang= 单行
     *
     * @param string $header
     * @return array{lang:string,auth:string}
     */
    public static function parseQsAttrs($header)
    {
        $out = array('lang' => '', 'auth' => self::AUTH_DEFAULT);
        $header = trim((string) $header);
        if ($header === '') {
            return $out;
        }
        if (preg_match('/^([a-zA-Z0-9_+-]+)$/', $header)) {
            $out['lang'] = self::normalizeLangId($header);
            return $out;
        }
        if (preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*([^\s]+)/', $header, $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $key = strtolower($pair[1]);
                $val = trim($pair[2], '"\'');
                if ($key === 'lang') {
                    $out['lang'] = self::normalizeLangId($val);
                } elseif ($key === 'auth') {
                    $out['auth'] = self::normalizeAuthId($val);
                }
            }
        }
        return $out;
    }

    /**
     * @param string $text
     * @return array<string,array<string,string>>
     */
    public static function parseFenceBlocksAsQs($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $map = array();
        if (!preg_match_all(
            '/^```([a-zA-Z0-9_+-]*)\s*\n([\s\S]*?)^```\s*$/m',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            return $map;
        }
        foreach ($matches as $m) {
            $id = self::normalizeLangId($m[1]);
            if ($id === '') {
                continue;
            }
            $auth = self::AUTH_DEFAULT;
            if (!isset($map[$auth])) {
                $map[$auth] = array();
            }
            $map[$auth][$id] = self::cleanCodeBlock($m[2]);
        }
        return $map;
    }

    /**
     * @param string $raw
     * @return string
     */
    public static function normalizeLangId($raw)
    {
        $raw = strtolower(trim((string) $raw));
        $aliases = array(
            'curl' => 'curl', 'bash' => 'curl', 'shell' => 'curl', 'sh' => 'curl',
            'typescript' => 'typescript', 'ts' => 'typescript',
            'browser' => 'browser', 'javascript' => 'browser', 'js' => 'browser', 'fetch' => 'browser',
            'python' => 'python', 'py' => 'python',
            'go' => 'go', 'golang' => 'go',
            'java' => 'java',
            'php' => 'php',
            'cpp' => 'cpp', 'c++' => 'cpp', 'cplusplus' => 'cpp', 'c' => 'cpp',
            'rust' => 'rust', 'rs' => 'rust',
        );
        return isset($aliases[$raw]) ? $aliases[$raw] : '';
    }

    /**
     * @param string $raw
     * @return string
     */
    public static function normalizeAuthId($raw)
    {
        $raw = strtolower(trim((string) $raw));
        if ($raw === 'header' || $raw === 'bearer') {
            return $raw;
        }
        return self::AUTH_DEFAULT;
    }

    /**
     * VsSyntax 语言标记
     *
     * @param string $id
     * @return string
     */
    public static function syntaxLang($id)
    {
        $id = self::normalizeLangId($id) !== '' ? self::normalizeLangId($id) : strtolower($id);
        $map = array(
            'curl' => 'bash',
            'typescript' => 'typescript',
            'browser' => 'javascript',
            'python' => 'python',
            'go' => 'go',
            'java' => 'java',
            'php' => 'php',
            'cpp' => 'cpp',
            'rust' => 'rust',
        );
        return isset($map[$id]) ? $map[$id] : 'javascript';
    }

    /**
     * @param array<string,array<string,string>> $parsed
     * @param array|null                         $keyways
     * @return array<int,string>
     */
    private static function resolveAuthOrder(array $parsed, $keyways)
    {
        $fromDoc = array();
        foreach (array_keys($parsed) as $auth) {
            $auth = self::normalizeAuthId($auth);
            $fromDoc[$auth] = $auth;
        }
        $ordered = array();
        if (is_array($keyways) && $keyways !== array()) {
            foreach (ApiManager::normalizeKeyways($keyways) as $way) {
                $ordered[] = $way;
            }
            return $ordered;
        }
        if ($ordered === array()) {
            foreach (array('query', 'header', 'bearer') as $way) {
                if (isset($fromDoc[$way])) {
                    $ordered[] = $way;
                }
            }
        }
        if ($ordered === array()) {
            $ordered[] = self::AUTH_DEFAULT;
        }
        return $ordered;
    }

    /**
     * @param array<string,array<string,string>> $parsed
     * @param string                             $auth
     * @param string                             $lang
     * @return string
     */
    private static function codeForAuthLang(array $parsed, $auth, $lang)
    {
        $auth = self::normalizeAuthId($auth);
        // 禁止用 query 示例填充 header/bearer Tab，避免鉴权方式错位
        if (isset($parsed[$auth][$lang])) {
            return trim((string) $parsed[$auth][$lang]);
        }
        return '';
    }

    /**
     * @param array<string,array<string,string>> $parsed
     * @param string                             $auth
     * @return array
     */
    private static function buildSamplesForAuth(array $parsed, $auth)
    {
        $out = array();
        foreach (self::langMeta() as $meta) {
            $id = $meta['id'];
            $code = self::codeForAuthLang($parsed, $auth, $id);
            if ($code === '') {
                continue;
            }
            $icon = $meta['icon'];
            $out[] = array(
                'id'          => $id,
                'label'       => $meta['label'],
                'icon'        => $icon,
                'code'        => $code,
                'icon_gray'   => self::iconUrl($icon, false),
                'icon_color'  => self::iconUrl($icon, true),
                'single_icon' => ($icon === 'curl') ? 1 : 0,
                'syn'         => self::syntaxLang($id),
            );
        }
        return $out;
    }

    /**
     * 剥离 HTML / vs-syn 高亮泄漏（展示、入库、AI 输出共用）
     *
     * @param string $code
     * @return string
     */
    public static function scrubHighlightLeak($code)
    {
        $code = (string) $code;
        if ($code === '') {
            return '';
        }
        // 完整 span 解包（可嵌套残缺）
        for ($n = 0; $n < 6; $n++) {
            $next = preg_replace(
                '/<span[^>]*class\s*=\s*["\'][^"\']*vs-syn[^"\']*["\'][^>]*>(.*?)<\/span>/is',
                '$1',
                $code
            );
            if ($next === null || $next === $code) {
                break;
            }
            $code = $next;
        }
        if (strpos($code, '<') !== false) {
            $code = strip_tags($code);
        }
        // 高亮泄漏碎片：-syn vs-syn--keyword"> / vs-syn--attr">
        $code = preg_replace('/-?syn\s+vs-syn--[\w-]*"\s*>?/i', '', $code);
        $code = preg_replace('/vs-syn--[\w-]*"\s*>?/i', '', $code);
        $code = preg_replace('/\bvs-syn--[\w-]+/i', '', $code);
        $code = preg_replace('/\sclass\s*=\s*["\'][^"\']*["\']/i', '', $code);
        $code = preg_replace('/\sdata-vs-syn(?:-done)?\s*=\s*["\'][^"\']*["\']/i', '', $code);
        $code = self::stripEmoji($code);
        return trim($code);
    }

    /**
     * @param string $code
     * @return string
     */
    private static function cleanCodeBlock($code)
    {
        return self::scrubHighlightLeak($code);
    }

    /**
     * 去掉 emoji 与装饰符号（示例代码仅供参考，禁止花哨符号）
     *
     * @param string $text
     * @return string
     */
    public static function stripEmoji($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);
        $text = preg_replace('/[\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{2300}-\x{23FF}]/u', '', $text);
        return $text;
    }
}
