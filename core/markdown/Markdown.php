<?php
/**
 * 文件：core/markdown/Markdown.php
 * 作用：Markdown + 扩展短码渲染（公告/文章/API 文档共用）
 *
 * 短码示例（编辑器插入的是简化标记，由本类展开为完整 HTML）：
 * :::card title=标题
 * 内容
 * :::
 * :::tip|warning|success|danger
 * :::collapse title=折叠标题
 * :::button text=按钮 url=https://
 * :::timeline
 * - 2024 | 节点说明
 * :::
 * :::music url=https://... title=歌名
 * 视频：@[video](https://...mp4)
 *
 * 强调色跟随主题 CSS 变量（--accent-primary / --vs-primary / --st-accent），短码 color 仅作可选覆盖。
 */

class Markdown
{
    /**
     * @param string $text
     * @return string HTML（已做基础消毒；短码产出受控 HTML）
     */
    public static function render($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $slots = array();
        $text = self::extractBlocks($text, $slots);
        $text = self::extractInlineMedia($text, $slots);

        $html = self::parseMarkdown($text);
        foreach ($slots as $key => $fragment) {
            $html = str_replace($key, $fragment, $html);
        }

        return '<div class="vs-md-body markdown-body">' . $html . '</div>';
    }

    /**
     * 后台/主题挂载编辑器资源
     *
     * @return array{css:string[],js:string[]} 相对站点根的路径
     */
    public static function assetPaths()
    {
        $base = vs_site_path('/core/markdown/assets');
        return array(
            'css' => array(
                $base . '/css/markdown-editor.css',
                $base . '/css/markdown-render.css',
            ),
            'js'  => array(
                $base . '/vendor/marked.min.js',
                $base . '/vendor/purify.min.js',
                $base . '/js/markdown-render.js',
                $base . '/js/markdown-editor.js',
            ),
        );
    }

    /**
     * 输出编辑器挂载所需 link/script（后台 layout 外或页内）
     *
     * @return string
     */
    public static function renderAssetsHtml()
    {
        $paths = self::assetPaths();
        $html = '';
        foreach ($paths['css'] as $href) {
            $html .= '<link rel="stylesheet" href="' . vs_e($href) . '">' . "\n";
        }
        foreach ($paths['js'] as $src) {
            $html .= '<script src="' . vs_e($src) . '"></script>' . "\n";
        }
        return $html;
    }

    /**
     * @param string               $text
     * @param array<string,string> $slots
     * @return string
     */
    private static function extractBlocks($text, array &$slots)
    {
        $n = 0;
        $pattern = '/^:::(card|tip|warning|success|danger|collapse|button|timeline|music)([^\n]*)\n(.*?)^:::\s*$/ms';
        return preg_replace_callback($pattern, function ($m) use (&$slots, &$n) {
            $type = $m[1];
            $attrs = self::parseAttrs(trim($m[2]));
            $body = trim($m[3]);
            $key = self::slotPlaceholder($n++);
            $slots[$key] = self::renderBlock($type, $attrs, $body);
            return "\n\n" . $key . "\n\n";
        }, $text);
    }

    /**
     * DOM 序列化会丢掉 NUL，短码占位符必须用纯 ASCII（禁止 \x00）。
     *
     * @param int $n
     * @return string
     */
    private static function slotPlaceholder($n)
    {
        return 'VSMDSLOT' . (int) $n . 'VSMDX';
    }

    /**
     * 视频/媒体短码 → 插槽（须在 Parsedown SafeMode 之前抽出，否则 HTML 会被转义成乱码）
     *
     * @param string               $text
     * @param array<string,string> $slots
     * @return string
     */
    private static function extractInlineMedia($text, array &$slots)
    {
        $n = count($slots);
        // 自定义视频：@[video](url) — URL 允许 query/hash
        return preg_replace_callback(
            '/@\[video\]\((https?:\/\/[^\s\)]+)\)/i',
            function ($m) use (&$slots, &$n) {
                $url = vs_e($m[1]);
                $key = self::slotPlaceholder($n++);
                $slots[$key] = '<div class="vs-md-video"><video controls preload="metadata" src="'
                    . $url . '"></video></div>';
                return "\n\n" . $key . "\n\n";
            },
            $text
        );
    }

    /**
     * @deprecated 保留空实现名避免外部误调；请用 extractInlineMedia
     * @param string $text
     * @return string
     */
    private static function replaceInlineMedia($text)
    {
        return $text;
    }

    /**
     * @param string $type
     * @param array  $attrs
     * @param string $body
     * @return string
     */
    private static function renderBlock($type, array $attrs, $body)
    {
        // 修复：强化属性值验证
        $attrs = self::sanitizeAttrs($attrs);

        switch ($type) {
            case 'card':
                $color = vs_safe_css_color(isset($attrs['color']) ? $attrs['color'] : '');
                $title = isset($attrs['title']) ? $attrs['title'] : '';
                $style = $color !== '' ? 'border-color:' . vs_e($color) . ';' : '';
                $titleStyle = $color !== '' ? ' style="color:' . vs_e($color) . ';"' : '';
                $h = $title !== '' ? '<div class="vs-md-card__title"' . $titleStyle . '>'
                    . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>' : '';
                return '<div class="vs-md-card"' . ($style !== '' ? ' style="' . $style . '"' : '') . '>' . $h
                    . '<div class="vs-md-card__body">' . self::parseMarkdown($body) . '</div></div>';

            case 'tip':
            case 'warning':
            case 'success':
            case 'danger':
                return '<div class="vs-md-alert vs-md-alert--' . vs_e($type) . '">'
                    . self::parseMarkdown($body) . '</div>';

            case 'collapse':
                $title = isset($attrs['title']) ? $attrs['title'] : '详情';
                return '<details class="vs-md-collapse"><summary>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                    . '</summary><div class="vs-md-collapse__body">'
                    . self::parseMarkdown($body) . '</div></details>';

            case 'button':
                $color = vs_safe_css_color(isset($attrs['color']) ? $attrs['color'] : '');
                $text = isset($attrs['text']) ? $attrs['text'] : '按钮';
                $url = vs_safe_embed_url(isset($attrs['url']) ? $attrs['url'] : (isset($attrs['text_url']) ? $attrs['text_url'] : '#'));
                $btnStyle = $color !== '' ? ' style="background:' . vs_e($color) . ';"' : '';
                return '<p class="vs-md-btn-wrap"><a class="vs-md-btn" href="' . vs_e($url) . '"'
                    . $btnStyle . ' target="_blank" rel="noopener noreferrer">'
                    . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a></p>';

            case 'timeline':
                $items = preg_split('/\n+/', $body);
                $lis = '';
                foreach ($items as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] !== '-') {
                        continue;
                    }
                    $line = ltrim(substr($line, 1));
                    $parts = array_map('trim', explode('|', $line, 2));
                    $t = isset($parts[0]) ? $parts[0] : '';
                    $d = isset($parts[1]) ? $parts[1] : '';
                    $lis .= '<li><span class="vs-md-timeline__time">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span><span class="vs-md-timeline__desc">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</span></li>';
                }
                return '<ul class="vs-md-timeline">' . $lis . '</ul>';

            case 'music':
                $urlRaw = isset($attrs['url']) ? $attrs['url'] : '';
                $title = isset($attrs['title']) ? $attrs['title'] : '音频';
                if ($urlRaw === '' || !vs_is_safe_embed_url($urlRaw)) {
                    return '';
                }
                $url = $urlRaw;
                return '<div class="vs-md-music"><div class="vs-md-music__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div><audio controls preload="none" src="' . vs_e($url) . '"></audio></div>';

            default:
                return '';
        }
    }

    /**
     * @param string $raw
     * @return array<string,string>
     */
    private static function parseAttrs($raw)
    {
        $attrs = array();
        if ($raw === '') {
            return $attrs;
        }
        if (preg_match_all('/(\w+)=([^\s]+)/u', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $attrs[$row[1]] = trim($row[2], "\"'");
            }
        }
        return $attrs;
    }

    /**
     * 强化属性验证：确保属性值不含危险字符（XSS 防护）
     *
     * v13.26.46 安全修复：添加属性值黑名单检查
     *
     * @param array $attrs
     * @return array
     */
    private static function sanitizeAttrs(array $attrs)
    {
        $result = array();
        foreach ($attrs as $key => $val) {
            $key = preg_replace('/[^a-z0-9_-]/i', '', (string) $key);
            if ($key === '') {
                continue;
            }
            // 按属性名丢弃事件处理器（勿只靠值里删 on\w+ 子串）
            if (strlen($key) >= 3 && strtolower(substr($key, 0, 2)) === 'on') {
                continue;
            }
            $val = (string) $val;
            $val = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', '', $val);
            $urlKeys = array('href' => 1, 'src' => 1, 'action' => 1, 'formaction' => 1);
            if (isset($urlKeys[strtolower($key)])) {
                $decoded = html_entity_decode($val, ENT_QUOTES, 'UTF-8');
                if (preg_match('/^\s*(?:javascript|vbscript|data)\s*:/i', $decoded)) {
                    continue;
                }
            } else {
                // 非 URL 属性：去掉协议型危险串，避免黑名单半删残留
                $val = preg_replace('/(?:javascript|vbscript)\s*:/i', '', $val);
            }
            if ($val !== '') {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function parseMarkdown($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        if (!class_exists('Parsedown', false)) {
            $file = dirname(__FILE__) . '/Parsedown.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (class_exists('Parsedown', false)) {
            $pd = new Parsedown();
            // 与后台 marked + DOMPurify 对齐：允许安全 HTML（如 README 徽章 div/img），再做服务端消毒
            $pd->setSafeMode(false);
            $pd->setBreaksEnabled(true);
            return self::sanitizeRenderedHtml($pd->text($text));
        }
        // 降级：纯文本转义
        return '<p>' . nl2br(vs_e($text)) . '</p>';
    }

    /**
     * 对外：DOM 白名单消毒（站内公告等二次消毒入口）
     *
     * @param string $html
     * @return string
     */
    public static function sanitizeHtml($html)
    {
        return self::sanitizeRenderedHtml($html);
    }

    /**
     * 白名单消毒（DOM）：禁止再靠正则黑名单
     *
     * @param string $html
     * @return string
     */
    private static function sanitizeRenderedHtml($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }
        if (!class_exists('DOMDocument')) {
            return vs_e(strip_tags($html));
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<html><body><div id="vs-md-root">' . $html . '</div></body></html>';
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return vs_e(strip_tags($html));
        }

        $root = $dom->getElementById('vs-md-root');
        if (!$root) {
            return '';
        }
        self::sanitizeDomNode($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return $out;
    }

    /**
     * @param DOMNode $node
     * @return void
     */
    private static function sanitizeDomNode($node)
    {
        // getAttribute / removeAttribute 仅 DOMElement 有；收窄类型供静态分析
        if (!($node instanceof DOMElement)) {
            return;
        }

        $allowTags = array(
            'a' => 1, 'p' => 1, 'br' => 1, 'hr' => 1, 'span' => 1, 'div' => 1,
            'strong' => 1, 'b' => 1, 'em' => 1, 'i' => 1, 'u' => 1, 's' => 1, 'del' => 1, 'ins' => 1,
            'code' => 1, 'pre' => 1, 'blockquote' => 1, 'ul' => 1, 'ol' => 1, 'li' => 1,
            'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1,
            'table' => 1, 'thead' => 1, 'tbody' => 1, 'tr' => 1, 'th' => 1, 'td' => 1,
            'img' => 1, 'sup' => 1, 'sub' => 1, 'kbd' => 1, 'abbr' => 1,
        );
        $urlAttrs = array('href' => 1, 'src' => 1);
        $allowAttrs = array(
            'a' => array('href' => 1, 'title' => 1, 'rel' => 1, 'target' => 1),
            'img' => array('src' => 1, 'alt' => 1, 'title' => 1, 'width' => 1, 'height' => 1),
            'td' => array('colspan' => 1, 'rowspan' => 1),
            'th' => array('colspan' => 1, 'rowspan' => 1),
            'abbr' => array('title' => 1),
            'code' => array('class' => 1),
            'pre' => array('class' => 1),
            'div' => array('class' => 1),
            'span' => array('class' => 1),
        );

        $tag = strtolower($node->nodeName);
        $isRoot = ($tag === 'div' && $node->getAttribute('id') === 'vs-md-root');
        $children = array();
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        if (!$isRoot && !isset($allowTags[$tag])) {
            $parent = $node->parentNode;
            foreach ($children as $child) {
                self::sanitizeDomNode($child);
            }
            if ($parent) {
                foreach ($children as $child) {
                    if ($child->parentNode === $node) {
                        $parent->insertBefore($child, $node);
                    }
                }
                $parent->removeChild($node);
            }
            return;
        }

        if ($node->hasAttributes()) {
            $toRemove = array();
            foreach ($node->attributes as $attr) {
                $an = strtolower($attr->name);
                if ($isRoot && $an === 'id') {
                    continue;
                }
                if (strpos($an, 'on') === 0) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if ($an === 'srcset' || $an === 'dynsrc' || $an === 'lowsrc' || $an === 'poster'
                    || $an === 'background' || $an === 'style' || $an === 'srcdoc' || $an === 'id') {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if (!isset($allowAttrs[$tag][$an])) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if (isset($urlAttrs[$an]) && !self::isSafeMdUrl($attr->value)) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if ($an === 'target' && $attr->value !== '_blank' && $attr->value !== '_self') {
                    $toRemove[] = $attr->name;
                }
            }
            foreach ($toRemove as $rn) {
                $node->removeAttribute($rn);
            }
            // target=_blank 强制 noopener（防反向标签劫持，E478）
            if ($tag === 'a' && strtolower((string) $node->getAttribute('target')) === '_blank') {
                $rel = trim((string) $node->getAttribute('rel'));
                $parts = $rel !== '' ? preg_split('/\s+/', $rel) : array();
                if (!is_array($parts)) {
                    $parts = array();
                }
                $need = array('noopener', 'noreferrer');
                foreach ($need as $token) {
                    $has = false;
                    foreach ($parts as $p) {
                        if (strtolower((string) $p) === $token) {
                            $has = true;
                            break;
                        }
                    }
                    if (!$has) {
                        $parts[] = $token;
                    }
                }
                $node->setAttribute('rel', trim(implode(' ', $parts)));
            }
        }

        foreach ($children as $child) {
            if ($child->parentNode === $node) {
                self::sanitizeDomNode($child);
            }
        }
    }

    /**
     * @param string $url
     * @return bool
     */
    private static function isSafeMdUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '' || $url[0] === '#' || $url[0] === '/') {
            return true;
        }
        if (stripos($url, '//') === 0) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme'])) {
            return strpos($url, ':') === false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        return ($scheme === 'http' || $scheme === 'https' || $scheme === 'mailto');
    }
}

