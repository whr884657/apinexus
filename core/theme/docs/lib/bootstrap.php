<?php
/**
 * 主题 five · 页面公共引导（Markdown）
 */
if (!defined('VS_THEME_RENDER')) {
    return;
}

if (!function_exists('TH5_md_render')) {
    /**
     * @param string $text
     * @return string
     */
    function TH5_md_render($text)
    {
        if (class_exists('Markdown')) {
            return Markdown::render((string) $text);
        }
        return '';
    }
}

if (!function_exists('TH5_resolve_media_url')) {
    /**
     * @param string $url
     * @return string
     */
    function TH5_resolve_media_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (function_exists('vs_favicon_href')) {
            $resolved = vs_favicon_href($url);
            if ($resolved !== '') {
                return $resolved;
            }
        }
        return $url;
    }
}

if (!function_exists('TH5_social_icon_url')) {
    /**
     * @param string $type
     * @return string
     */
    function TH5_social_icon_url($type)
    {
        $map = array(
            'wechat'  => 'wechat.svg',
            'qq'      => 'qq.svg',
            'email'   => 'email.svg',
            'github'  => 'github.svg',
            'gitee'   => 'gitee.svg',
            'twitter' => 'twitter.svg',
        );
        $type = strtolower(trim((string) $type));
        if (!isset($map[$type])) {
            return '';
        }
        return ThemeManager::assetUrl('docs', 'assets/img/' . $map[$type]);
    }
}

if (!function_exists('TH5_footer_social_items')) {
    /**
     * 页脚社交图标（最多 3 个，由 theme.json 配置）
     *
     * @return array<int, array<string, string>>
     */
    function TH5_footer_social_items()
    {
        $allowed = array('wechat', 'qq', 'email', 'github', 'gitee', 'twitter');
        $items = array();

        for ($i = 1; $i <= 3; $i++) {
            $type = strtolower(trim(ThemeManager::themeSettingStr('footer_social_' . $i . '_type', '')));
            $value = trim(ThemeManager::themeSettingStr('footer_social_' . $i . '_value', ''));
            if ($type === '' || $value === '' || !in_array($type, $allowed, true)) {
                continue;
            }

            $icon = TH5_social_icon_url($type);
            if ($icon === '') {
                continue;
            }

            $qqMode = ThemeManager::themeSettingStr('footer_social_' . $i . '_qq_mode', 'link');
            $qqMode = ($qqMode === 'qrcode') ? 'qrcode' : 'link';

            $item = array(
                'type'  => $type,
                'icon'  => $icon,
                'label' => TH5_social_label($type),
            );

            if ($type === 'wechat' || ($type === 'qq' && $qqMode === 'qrcode')) {
                $qr = TH5_resolve_media_url($value);
                if ($qr === '') {
                    continue;
                }
                $item['mode'] = 'qrcode';
                $item['qr_src'] = $qr;
            } elseif ($type === 'email') {
                $email = preg_replace('/\s+/', '', $value);
                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }
                $item['mode'] = 'email';
                $item['href'] = 'mailto:' . $email;
            } else {
                $href = $value;
                if (!preg_match('#^https?://#i', $href) && !preg_match('#^mailto:#i', $href)) {
                    $href = 'https://' . ltrim($href, '/');
                }
                $item['mode'] = 'link';
                $item['href'] = $href;
            }

            $items[] = $item;
        }

        return $items;
    }
}

if (!function_exists('TH5_social_label')) {
    /**
     * @param string $type
     * @return string
     */
    function TH5_social_label($type)
    {
        $labels = array(
            'wechat'  => '微信',
            'qq'      => 'QQ',
            'email'   => '邮箱',
            'github'  => 'GitHub',
            'gitee'   => 'Gitee',
            'twitter' => '推特',
        );
        $type = strtolower(trim((string) $type));
        return isset($labels[$type]) ? $labels[$type] : '社交';
    }
}

if (!function_exists('TH5_render_footer_social')) {
    /**
     * @return void
     */
    function TH5_render_footer_social()
    {
        $items = TH5_footer_social_items();
        if (count($items) === 0) {
            return;
        }

        echo '<div class="th5-footer-social" role="list">';
        foreach ($items as $item) {
            $label = isset($item['label']) ? (string) $item['label'] : '社交';
            $icon = isset($item['icon']) ? (string) $item['icon'] : '';
            $mode = isset($item['mode']) ? (string) $item['mode'] : 'link';

            if ($mode === 'qrcode') {
                $qr = isset($item['qr_src']) ? (string) $item['qr_src'] : '';
                if ($qr === '') {
                    continue;
                }
                echo '<div class="th5-social-item th5-social-item--qr" role="listitem">';
                echo '<button type="button" class="th5-social-btn" aria-label="' . vs_e($label) . '">';
                echo '<img src="' . vs_e($icon) . '" alt="" width="18" height="18" loading="lazy" decoding="async">';
                echo '</button>';
                echo '<span class="th5-social-qr-pop" role="tooltip">';
                echo '<img src="' . vs_e($qr) . '" alt="' . vs_e($label) . '二维码" width="104" height="104" loading="lazy" decoding="async" referrerpolicy="no-referrer">';
                echo '</span></div>';
                continue;
            }

            $href = isset($item['href']) ? (string) $item['href'] : '';
            if ($href === '') {
                continue;
            }

            $target = ($mode === 'email') ? '' : ' target="_blank" rel="noopener noreferrer"';
            echo '<a class="th5-social-item th5-social-btn" role="listitem" href="' . vs_e($href) . '"' . $target . ' aria-label="' . vs_e($label) . '">';
            echo '<img src="' . vs_e($icon) . '" alt="" width="18" height="18" loading="lazy" decoding="async">';
            echo '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('TH5_emit_console_brand_script')) {
    /**
     * 主题四专用：控制台品牌脚本走站内根路径（符合《前端页面渲染与源码规范》）
     *
     * @return void
     */
    function TH5_emit_console_brand_script()
    {
        if (!empty($GLOBALS['vs_console_brand_emitted'])) {
            return;
        }
        $GLOBALS['vs_console_brand_emitted'] = true;

        $ver = defined('VS_VERSION') ? (string) VS_VERSION : '';
        $src = vs_site_path('/assets/js/console-brand.js');
        if ($ver !== '') {
            $src .= '?v=' . rawurlencode($ver);
        }

        echo '<script>window.VS_VERSION=window.VS_VERSION||' . json_encode($ver, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
        echo '<script src="' . vs_e($src) . '"></script>' . "\n";
    }
}
