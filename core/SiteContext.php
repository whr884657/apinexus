<?php
/**
 * 文件：core/SiteContext.php
 * 作用：站点展示信息（读取系统配置；备案号按访问 Host 匹配，最多两槽）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class SiteContext
{
    /** @var array|null */
    private static $cache = null;

    /**
     * 清除解析缓存
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$cache = null;
    }

    /**
     * 规范化 Host（去端口、转小写）
     *
     * @param string $host
     * @return string
     */
    public static function normalizeHost($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return '';
        }

        if (strpos($host, ':') !== false) {
            $host = preg_replace('/:\d+$/', '', $host);
        }

        return $host;
    }

    /**
     * 后台保存用：规范化绑定域名（去 scheme/路径/端口，小写）
     *
     * @param string $input
     * @return string
     */
    public static function normalizeDomainInput($input)
    {
        $host = trim((string) $input);
        if ($host === '') {
            return '';
        }
        $host = preg_replace('#^https?://#i', '', $host);
        $host = preg_replace('~[/?#].*$~', '', $host);
        return self::normalizeHost($host);
    }

    /**
     * 当前访问 Host
     *
     * @return string
     */
    public static function currentHost()
    {
        return isset($_SERVER['HTTP_HOST']) ? self::normalizeHost($_SERVER['HTTP_HOST']) : '';
    }

    /**
     * 解析当前站点上下文
     *
     * @return array
     */
    public static function resolve()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $siteName = trim((string) Config::get('site_name', 'ApiNexus'));
        $systemName = trim((string) Config::get('system_name', ''));
        if ($systemName === '') {
            $systemName = $siteName;
        }
        $navName = trim((string) Config::get('nav_name', ''));
        if ($navName === '') {
            $navName = $siteName;
        }
        $copyrightName = trim((string) Config::get('copyright_name', ''));
        if ($copyrightName === '') {
            $copyrightName = $systemName !== '' ? $systemName : $siteName;
        }
        $copyrightUrl = trim((string) Config::get('copyright_url', ''));

        self::$cache = array(
            'host'               => self::currentHost(),
            'site_name'          => $siteName,
            'system_name'        => $systemName,
            'nav_name'           => $navName,
            'copyright_name'     => $copyrightName,
            'copyright_url'      => $copyrightUrl,
            'site_description'   => trim((string) Config::get('site_description', '')),
            'site_keywords'      => trim((string) Config::get('site_keywords', '')),
            'site_favicon'       => trim((string) Config::get('site_favicon', '')),
            'site_logo'          => trim((string) Config::get('site_logo', '')),
            'icp_number'         => trim((string) Config::get('site_icp', '')),
            'gongan_number'      => trim((string) Config::get('site_gongan', '')),
            'site_runtime_start' => trim((string) Config::get('site_runtime_start', '')),
            'footer_html_left'   => (string) Config::get('footer_html_left', ''),
            'footer_html_center' => (string) Config::get('footer_html_center', ''),
            'footer_html_right'  => (string) Config::get('footer_html_right', ''),
            'footer_qr1_enabled' => trim((string) Config::get('footer_qr1_enabled', '')),
            'footer_qr1_name'    => trim((string) Config::get('footer_qr1_name', '')),
            'footer_qr1_url'     => trim((string) Config::get('footer_qr1_url', '')),
            'footer_qr2_enabled' => trim((string) Config::get('footer_qr2_enabled', '')),
            'footer_qr2_name'    => trim((string) Config::get('footer_qr2_name', '')),
            'footer_qr2_url'     => trim((string) Config::get('footer_qr2_url', '')),
        );

        return self::$cache;
    }

    /**
     * 浏览器标题 / SEO 用名称（配置键 site_name）
     *
     * @return string
     */
    public static function siteName()
    {
        $ctx = self::resolve();
        $name = trim($ctx['site_name']);
        return $name !== '' ? $name : 'ApiNexus';
    }

    /**
     * 系统/产品名称（后台侧栏/顶栏、关于页、管理员登录等；缺省回落浏览器标题名）
     *
     * @return string
     */
    public static function systemName()
    {
        $ctx = self::resolve();
        $name = trim($ctx['system_name']);
        if ($name !== '') {
            return $name;
        }
        $fallback = trim($ctx['site_name']);
        return $fallback !== '' ? $fallback : 'ApiNexus';
    }

    /**
     * 前台顶栏品牌短名（缺省回落浏览器标题名）
     *
     * @return string
     */
    public static function navName()
    {
        $ctx = self::resolve();
        $name = trim(isset($ctx['nav_name']) ? $ctx['nav_name'] : '');
        if ($name !== '') {
            return $name;
        }
        return self::siteName();
    }

    /**
     * 页脚版权名称（缺省回落系统名）
     *
     * @return string
     */
    public static function copyrightName()
    {
        $ctx = self::resolve();
        $name = trim(isset($ctx['copyright_name']) ? $ctx['copyright_name'] : '');
        if ($name !== '') {
            return $name;
        }
        return self::systemName();
    }

    /**
     * 页脚版权跳转地址（可空）
     *
     * @return string
     */
    public static function copyrightUrl()
    {
        $ctx = self::resolve();
        return trim(isset($ctx['copyright_url']) ? $ctx['copyright_url'] : '');
    }

    /**
     * @return string
     */
    public static function siteDescription()
    {
        return self::resolve()['site_description'];
    }

    /**
     * @return string
     */
    public static function siteKeywords()
    {
        return self::resolve()['site_keywords'];
    }

    /**
     * @return string
     */
    public static function siteFavicon()
    {
        return self::resolve()['site_favicon'];
    }

    /**
     * @return string
     */
    public static function siteLogo()
    {
        return self::resolve()['site_logo'];
    }

    /**
     * @return string
     */
    public static function siteRuntimeStart()
    {
        return self::resolve()['site_runtime_start'];
    }

    /**
     * @return string
     */
    public static function footerHtmlLeft()
    {
        return self::resolve()['footer_html_left'];
    }

    /**
     * @return string
     */
    public static function footerHtmlCenter()
    {
        return self::resolve()['footer_html_center'];
    }

    /**
     * @return string
     */
    public static function footerHtmlRight()
    {
        return self::resolve()['footer_html_right'];
    }

    /**
     * @return string
     */
    public static function footerQr1Enabled()
    {
        return self::resolve()['footer_qr1_enabled'];
    }

    /**
     * @return string
     */
    public static function footerQr1Name()
    {
        return self::resolve()['footer_qr1_name'];
    }

    /**
     * @return string
     */
    public static function footerQr1Url()
    {
        return self::resolve()['footer_qr1_url'];
    }

    /**
     * @return string
     */
    public static function footerQr2Enabled()
    {
        return self::resolve()['footer_qr2_enabled'];
    }

    /**
     * @return string
     */
    public static function footerQr2Name()
    {
        return self::resolve()['footer_qr2_name'];
    }

    /**
     * @return string
     */
    public static function footerQr2Url()
    {
        return self::resolve()['footer_qr2_url'];
    }

    /**
     * ICP 备案官方查询链接
     *
     * @return string
     */
    public static function icpLink()
    {
        return 'https://beian.miit.gov.cn/';
    }

    /**
     * 公安备案官方查询链接
     *
     * @param string $number
     * @return string
     */
    public static function gonganLink($number)
    {
        $code = preg_replace('/\D/', '', $number);
        if ($code === '') {
            return 'https://beian.mps.gov.cn/';
        }
        return 'https://beian.mps.gov.cn/#/query/webSearch?code=' . rawurlencode($code);
    }

    /**
     * 获取当前访问 Host 对应的备案信息（最多两槽；未绑定域名不展示）
     *
     * @return array{icp_number:string,icp_link:string,gongan_number:string,gongan_link:string}
     */
    public static function beianInfo()
    {
        $host = self::currentHost();
        $domain = self::normalizeDomainInput(Config::get('site_domain', ''));
        $domain1 = self::normalizeDomainInput(Config::get('site_domain1', ''));
        $icp = trim((string) Config::get('site_icp', ''));
        $gongan = trim((string) Config::get('site_gongan', ''));
        $icp1 = trim((string) Config::get('site_icp1', ''));
        $gongan1 = trim((string) Config::get('site_gongan1', ''));

        $hasDomainBinding = ($domain !== '' || $domain1 !== '');
        $matchIcp = '';
        $matchGongan = '';

        if ($host !== '' && $domain !== '' && $host === $domain) {
            $matchIcp = $icp;
            $matchGongan = $gongan;
        } elseif ($host !== '' && $domain1 !== '' && $host === $domain1) {
            $matchIcp = $icp1;
            $matchGongan = $gongan1;
        } elseif (!$hasDomainBinding && ($icp !== '' || $gongan !== '')) {
            // 升级兼容：未配置绑定域名时，仍全站展示原 site_icp / site_gongan
            $matchIcp = $icp;
            $matchGongan = $gongan;
        }

        return array(
            'icp_number'    => $matchIcp,
            'icp_link'      => self::icpLink(),
            'gongan_number' => $matchGongan,
            'gongan_link'   => self::gonganLink($matchGongan),
        );
    }
}
