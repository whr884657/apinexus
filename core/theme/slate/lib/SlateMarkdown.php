<?php
/**
 * 文件：core/theme/slate/lib/SlateMarkdown.php
 * 作用：主题二专属 Markdown 短码预处理 + 共用 Markdown::render 管道
 *
 * 专属语法（在共用 :::card / tip / @[video] 之外）：
 * :::stcallout tone=tip|warn|ok|danger title=可选
 * 内容
 * :::
 * :::ststeps
 * 1. 第一步说明
 * 2. 第二步说明
 * :::
 * @[api](123) 或 @[api](123|自定义标题)
 * ==高亮文案==
 */

class SlateMarkdown
{
    /**
     * @param string $text
     * @return string HTML（含 vs-md-body + st-md）
     */
    public static function render($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (!class_exists('Markdown')) {
            return '<div class="vs-md-body markdown-body st-md"><p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p></div>';
        }

        $slots = array();
        $text = self::extractExclusive($text, $slots);
        $html = Markdown::render($text);

        foreach ($slots as $key => $fragment) {
            $html = str_replace($key, $fragment, $html);
        }

        if (strpos($html, 'class="vs-md-body markdown-body"') !== false) {
            $html = str_replace(
                'class="vs-md-body markdown-body"',
                'class="vs-md-body markdown-body st-md"',
                $html
            );
        } elseif (strpos($html, 'st-md') === false) {
            $html = '<div class="vs-md-body markdown-body st-md">' . $html . '</div>';
        }

        return $html;
    }

    /**
     * 仅做专属短码预处理，供客户端对齐或二次管道使用
     *
     * @param string $text
     * @return string
     */
    public static function preprocess($text)
    {
        $slots = array();
        return self::extractExclusive((string) $text, $slots);
    }

    /**
     * @param string               $text
     * @param array<string,string> $slots
     * @return string
     */
    private static function extractExclusive($text, array &$slots)
    {
        $n = 0;
        $push = function ($html) use (&$slots, &$n) {
            $key = '__STMD' . ($n++) . '__';
            $slots[$key] = $html;
            return "\n\n" . $key . "\n\n";
        };

        $text = preg_replace_callback(
            '/^:::stcallout([^\n]*)\n(.*?)^:::\s*$/ms',
            function ($m) use ($push) {
                $attrs = self::parseAttrs(trim($m[1]));
                $tone = isset($attrs['tone']) ? strtolower((string) $attrs['tone']) : 'tip';
                if (!in_array($tone, array('tip', 'warn', 'ok', 'danger'), true)) {
                    $tone = 'tip';
                }
                $title = isset($attrs['title']) ? trim((string) $attrs['title']) : '';
                $body = trim($m[2]);
                $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
                $titleHtml = $title !== ''
                    ? '<div class="st-md-callout__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
                    : '';
                return $push(
                    '<aside class="st-md-callout st-md-callout--' . $tone . '" role="note">'
                    . $titleHtml
                    . '<div class="st-md-callout__body">' . $bodyHtml . '</div></aside>'
                );
            },
            $text
        );

        $text = preg_replace_callback(
            '/^:::ststeps([^\n]*)\n(.*?)^:::\s*$/ms',
            function ($m) use ($push) {
                $body = trim($m[2]);
                $lines = preg_split('/\n+/', $body);
                $items = array();
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
                    $items[] = '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($items === array()) {
                    return '';
                }
                return $push('<ol class="st-md-steps">' . implode('', $items) . '</ol>');
            },
            $text
        );

        $text = preg_replace_callback(
            '/@\[api\]\((\d+)(?:\|([^)\n]+))?\)/u',
            function ($m) use ($push) {
                $id = (int) $m[1];
                if ($id <= 0) {
                    return $m[0];
                }
                $label = isset($m[2]) ? trim((string) $m[2]) : '';
                if ($label === '') {
                    $label = '接口 #' . $id;
                }
                $url = function_exists('vs_api_detail_url')
                    ? vs_api_detail_url($id)
                    : (rtrim(vs_site_base_path(), '/') . '/detail/' . $id);
                return $push(
                    '<a class="st-md-api" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                    . '<span class="st-md-api__badge">API</span>'
                    . '<span class="st-md-api__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '</a>'
                );
            },
            $text
        );

        $text = preg_replace_callback(
            '/==([^=\n]{1,200})==/u',
            function ($m) use ($push) {
                return $push(
                    '<mark class="st-md-mark">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</mark>'
                );
            },
            $text
        );

        return $text;
    }

    /**
     * @param string $raw
     * @return array<string,string>
     */
    private static function parseAttrs($raw)
    {
        $out = array();
        if ($raw === '') {
            return $out;
        }
        if (preg_match_all('/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/u', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $val = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
                $out[$m[1]] = $val;
            }
        }
        return $out;
    }
}
