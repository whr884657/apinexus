<?php
/**
 * 文件：core/ConsoleBrand.php
 * 作用：全站浏览器控制台品牌信息（系统级，不属于任何主题）
 *
 * 铁律：
 * - 固定文案不可由后台/主题改写；仅版本取 VS_VERSION。
 * - 不展示技术栈、开源协议、「仓库」标题；不打印裸 URL。
 * - 卡片须有明确配色（非纯白灰糊）。
 * - 预览：杂七杂八/13.26.18/浏览器控制台品牌信息-单文件预览.html
 */

class ConsoleBrand
{
    const PROJECT = 'ApiNexus';
    const DEVELOPER = '尋鯨錄';

    const REPO_GITEE = 'https://gitee.com/xunjinlu/apinexus';
    const REPO_GITCODE = 'https://gitcode.com/xunjinlu/apinexus';
    const REPO_GITHUB = 'https://github.com/whr884657/apinexus';

    /**
     * @return string
     */
    public static function tagline()
    {
        return '可自部署的开放 API 接口平台';
    }

    /**
     * @param string $file
     * @return string
     */
    public static function imgUrl($file)
    {
        $file = basename(str_replace('\\', '/', (string) $file));
        if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(svg|png|jpe?g|webp)$/', $file)) {
            return '';
        }
        $base = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
        return $base . '/assets/img/' . $file;
    }

    /**
     * @return array
     */
    public static function payload()
    {
        $version = defined('VS_VERSION') ? (string) VS_VERSION : '';
        return array(
            'project'   => self::PROJECT,
            'tagline'   => self::tagline(),
            'developer' => self::DEVELOPER,
            'version'   => $version,
            'repos'     => array(
                array('name' => 'Gitee', 'url' => self::REPO_GITEE, 'icon' => self::imgUrl('gitee.svg'), 'bg' => '#e5484d', 'fg' => '#ffffff'),
                array('name' => 'GitCode', 'url' => self::REPO_GITCODE, 'icon' => self::imgUrl('gitcode.svg'), 'bg' => '#f0762b', 'fg' => '#ffffff'),
                array('name' => 'GitHub', 'url' => self::REPO_GITHUB, 'icon' => self::imgUrl('github.svg'), 'bg' => '#3f3f46', 'fg' => '#fafafa'),
            ),
            'tease'     => '别看了，这里什么都没有。',
            'epilogue'  => '—— 世间总有些遗憾，是后来的自以为是，弄丢了当初的人。若你认识一位姓秦的姑娘，愿她一切安好。',
        );
    }

    /**
     * @return void
     */
    public static function emit()
    {
        if (!empty($GLOBALS['vs_console_brand_emitted'])) {
            return;
        }
        $GLOBALS['vs_console_brand_emitted'] = true;

        $json = json_encode(
            self::payload(),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if ($json === false) {
            return;
        }

        echo "<script>(function(){\n";
        echo self::clientJsBody($json);
        echo "})();</script>\n";
    }

    /**
     * @param string $jsonPayload
     * @return string
     */
    public static function clientJsBody($jsonPayload)
    {
        $js = "if(window.__VS_CONSOLE_BRAND__)return;window.__VS_CONSOLE_BRAND__=1;\n";
        $js .= 'var d=' . $jsonPayload . ";\n";
        $js .= "try{\n";
        $js .= "console.log('%c ApiNexus ','background:#1e293b;color:#f8fafc;font-weight:800;font-size:15px;padding:10px 18px;border-radius:10px;letter-spacing:0.04em');\n";
        $js .= "console.log('%c '+d.tagline+' ','background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;padding:8px 14px;border-radius:8px;border:1px solid #e2e8f0');\n";
        $js .= "console.log('%c开发者%c '+d.developer+' %c版本%c v'+d.version+' ',\n";
        $js .= "'background:#334155;color:#f1f5f9;font-size:11px;font-weight:600;padding:6px 10px;border-radius:8px 0 0 8px',\n";
        $js .= "'background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;padding:6px 12px;margin-right:8px;border-radius:0 8px 8px 0;border:1px solid #e2e8f0',\n";
        $js .= "'background:#334155;color:#f1f5f9;font-size:11px;font-weight:600;padding:6px 10px;border-radius:8px 0 0 8px',\n";
        $js .= "'background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;padding:6px 12px;border-radius:0 8px 8px 0;border:1px solid #e2e8f0');\n";
        $js .= "if(d.repos&&d.repos.length){var fmt='',css=[],i,r;for(i=0;i<d.repos.length;i++){r=d.repos[i];fmt+='%c '+r.name+' ';css.push('background:'+(r.bg||'#3f3f46')+';color:'+(r.fg||'#fff')+';font-weight:700;font-size:12px;padding:7px 14px;margin:0 5px 0 0;border-radius:8px');}css.unshift(fmt);console.log.apply(console,css);}\n";
        $js .= "console.log('%c '+d.tease+' ','background:#fafafa;color:#737373;font-size:12px;font-weight:500;padding:8px 12px;border-radius:8px;border:1px solid #e5e5e5');\n";
        $js .= "console.log('%c '+d.epilogue+' ','background:#fffbeb;color:#92400e;font-size:11px;padding:10px 14px;border-radius:8px;border:1px solid #fcd34d;line-height:1.6');\n";
        $js .= "}catch(e){}\n";
        return $js;
    }
}
