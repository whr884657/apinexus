<?php
/**
 * 文件：core/ApiQuickstart.php
 * 作用：默认主题 API 详情「快速上手」——从 aidoc 的 :::qs 短码解析多语言示例
 *
 * 存储格式（AI 生成 / 人工编辑统一）：
 * :::qs lang=curl
 * ...
 * :::
 *
 * 图标：assets/img/lang/（灰版 *.svg + 彩版 *-color.svg；curl 仅一份）
 */

class ApiQuickstart
{
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
        $base = rtrim(vs_base_url(), '/') . '/assets/img/lang/';
        if ($icon === 'curl') {
            return $base . 'curl.svg';
        }
        return $base . $icon . ($color ? '-color.svg' : '.svg');
    }

    /**
     * 从 aidoc 解析各语言代码（不写死示例）
     *
     * @param string $aidoc
     * @return array<int,array{id:string,label:string,icon:string,code:string,icon_gray:string,icon_color:string,single_icon:int}>
     */
    public static function samplesFromAidoc($aidoc)
    {
        $parsed = self::parseQsBlocks((string) $aidoc);
        $out = array();
        foreach (self::langMeta() as $meta) {
            $id = $meta['id'];
            $code = isset($parsed[$id]) ? trim((string) $parsed[$id]) : '';
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
     * 规范化 AI 输出：提取 :::qs 块并按固定顺序重排；兼容 ```lang 围栏
     *
     * @param string $raw
     * @return string
     */
    public static function normalizeAidocBlocks($raw)
    {
        $parsed = self::parseQsBlocks((string) $raw);
        if ($parsed === array()) {
            $parsed = self::parseFenceBlocks((string) $raw);
        }
        if ($parsed === array()) {
            return '';
        }
        $chunks = array();
        foreach (self::langMeta() as $meta) {
            $id = $meta['id'];
            if (empty($parsed[$id])) {
                continue;
            }
            $code = rtrim((string) $parsed[$id]);
            $chunks[] = ':::qs lang=' . $id . "\n" . $code . "\n:::";
        }
        return implode("\n\n", $chunks);
    }

    /**
     * @param string $text
     * @return array<string,string>
     */
    public static function parseQsBlocks($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $map = array();
        if (preg_match_all(
            '/^:::qs\s+(?:lang=)?([a-zA-Z0-9_+-]+)\s*\n([\s\S]*?)^:::\s*$/m',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $id = self::normalizeLangId($m[1]);
                if ($id === '') {
                    continue;
                }
                $map[$id] = trim($m[2]);
            }
        }
        return $map;
    }

    /**
     * @param string $text
     * @return array<string,string>
     */
    public static function parseFenceBlocks($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $map = array();
        if (preg_match_all(
            '/^```([a-zA-Z0-9_+-]*)\s*\n([\s\S]*?)^```\s*$/m',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $id = self::normalizeLangId($m[1]);
                if ($id === '') {
                    continue;
                }
                $map[$id] = trim($m[2]);
            }
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
            'cpp' => 'cpp', 'c++' => 'cpp', 'cplusplus' => 'cpp',
            'rust' => 'rust', 'rs' => 'rust',
        );
        return isset($aliases[$raw]) ? $aliases[$raw] : '';
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
}
