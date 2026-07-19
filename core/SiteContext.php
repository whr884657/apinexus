<?php
/**
 * 文件：core/SiteContext.php
 * 作用：站点展示信息（单域名，读取系统配置）
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

        self::$cache = array(
            'host'               => self::currentHost(),
            'site_name'          => trim((string) Config::get('site_name', 'ApiNexus')),
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
     * @return string
     */
    public static function siteName()
    {
        $ctx = self::resolve();
        $name = trim($ctx['site_name']);
        return $name !== '' ? $name : 'ApiNexus';
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
     * 获取当前域名备案信息
     *
     * @return array
     */
    public static function beianInfo()
    {
        $ctx = self::resolve();
        return array(
            'icp_number'    => trim((string) $ctx['icp_number']),
            'icp_link'      => self::icpLink(),
            'gongan_number' => trim((string) $ctx['gongan_number']),
            'gongan_link'   => self::gonganLink($ctx['gongan_number']),
        );
    }
}
