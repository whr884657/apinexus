<?php
/**
 * 文件：core/markdown/Markdown.php
 * 作用：Markdown + 扩展短码渲染（公告/文章/API 文档共用）
 *
 * 短码示例（编辑器插入的是简化标记，由本类展开为完整 HTML）：
 * :::card color=#059669 title=标题
 * 内容
 * :::
 * :::tip|warning|success|danger
 * :::collapse title=折叠标题
 * :::button color=#059669 text=按钮 text_url=https://
 * :::timeline
 * - 2024 | 节点说明
 * :::
 * :::music url=https://... title=歌名
 * :::indent 首行缩进段落
 * 视频：@[video](https://...mp4)
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
        $text = self::replaceInlineMedia($text);

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
        $base = rtrim(vs_base_url(), '/') . '/core/markdown/assets';
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
        $pattern = '/^:::(card|tip|warning|success|danger|collapse|button|timeline|music|indent)([^\n]*)\n(.*?)^:::\s*$/ms';
        return preg_replace_callback($pattern, function ($m) use (&$slots, &$n) {
            $type = $m[1];
            $attrs = self::parseAttrs(trim($m[2]));
            $body = trim($m[3]);
            $key = "\x00MDSLOT" . ($n++) . "\x00";
            $slots[$key] = self::renderBlock($type, $attrs, $body);
            return "\n\n" . $key . "\n\n";
        }, $text);
    }

    /**
     * @param string $text
     * @return string
     */
    private static function replaceInlineMedia($text)
    {
        // 自定义视频：@[video](url)
        $text = preg_replace_callback(
            '/@\[video\]\((https?:\/\/[^\s)]+)\)/i',
            function ($m) {
                $url = vs_e($m[1]);
                return "\n\n<div class=\"vs-md-video\"><video controls preload=\"metadata\" src=\""
                    . $url . "\"></video></div>\n\n";
            },
            $text
        );
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
        switch ($type) {
            case 'card':
                $color = isset($attrs['color']) ? $attrs['color'] : '#059669';
                $title = isset($attrs['title']) ? $attrs['title'] : '';
                $style = 'border-color:' . vs_e($color) . ';';
                $h = $title !== '' ? '<div class="vs-md-card__title" style="color:' . vs_e($color) . ';">'
                    . vs_e($title) . '</div>' : '';
                return '<div class="vs-md-card" style="' . $style . '">' . $h
                    . '<div class="vs-md-card__body">' . self::parseMarkdown($body) . '</div></div>';

            case 'tip':
            case 'warning':
            case 'success':
            case 'danger':
                return '<div class="vs-md-alert vs-md-alert--' . vs_e($type) . '">'
                    . self::parseMarkdown($body) . '</div>';

            case 'collapse':
                $title = isset($attrs['title']) ? $attrs['title'] : '详情';
                return '<details class="vs-md-collapse"><summary>' . vs_e($title)
                    . '</summary><div class="vs-md-collapse__body">'
                    . self::parseMarkdown($body) . '</div></details>';

            case 'button':
                $color = isset($attrs['color']) ? $attrs['color'] : '#059669';
                $text = isset($attrs['text']) ? $attrs['text'] : '按钮';
                $url = isset($attrs['url']) ? $attrs['url'] : (isset($attrs['text_url']) ? $attrs['text_url'] : '#');
                return '<p class="vs-md-btn-wrap"><a class="vs-md-btn" href="' . vs_e($url)
                    . '" style="background:' . vs_e($color) . ';" target="_blank" rel="noopener noreferrer">'
                    . vs_e($text) . '</a></p>';

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
                    $lis .= '<li><span class="vs-md-timeline__time">' . vs_e($t)
                        . '</span><span class="vs-md-timeline__desc">' . vs_e($d) . '</span></li>';
                }
                return '<ul class="vs-md-timeline">' . $lis . '</ul>';

            case 'music':
                $url = isset($attrs['url']) ? $attrs['url'] : '';
                $title = isset($attrs['title']) ? $attrs['title'] : '音频';
                if ($url === '') {
                    return '';
                }
                return '<div class="vs-md-music"><div class="vs-md-music__title">' . vs_e($title)
                    . '</div><audio controls preload="none" src="' . vs_e($url) . '"></audio></div>';

            case 'indent':
                return '<p class="vs-md-indent">' . nl2br(vs_e($body)) . '</p>';

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
            $pd->setSafeMode(true);
            $pd->setBreaksEnabled(true);
            return $pd->text($text);
        }
        // 降级：纯文本转义
        return '<p>' . nl2br(vs_e($text)) . '</p>';
    }
}
