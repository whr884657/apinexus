<?php
/**
 * 文件：core/OutboundUa.php
 * 作用：系统出站 User-Agent 统一拼装（品牌名可配置，模块后缀按调用点区分）
 *
 * 形态：{Brand}/{Version} 或 {Brand}-{Component}/{Version}
 * 例：MySite-Proxy/13.26.46 · MySite-IpLocator/13.26.46
 */

class OutboundUa
{
    const CONFIG_KEY = 'uabrand';
    const DEFAULT_BRAND = 'ApiNexus';
    const MAX_BRAND_LEN = 32;

    /** @var string|null 请求内缓存 */
    private static $brandMemo = null;

    /**
     * @return void
     */
    public static function clearMemo()
    {
        self::$brandMemo = null;
    }

    /**
     * 规范化品牌（仅字母数字与 - _；禁空格/控制字符/斜杠）
     *
     * @param string $raw
     * @return string 空串表示非法
     */
    public static function normalizeBrand($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, self::MAX_BRAND_LEN, 'UTF-8');
        } elseif (strlen($s) > self::MAX_BRAND_LEN) {
            $s = substr($s, 0, self::MAX_BRAND_LEN);
        }
        // 禁止注入头：CRLF、斜杠、空白
        if (preg_match('/[\r\n\/\\\\\s]/', $s)) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,31}$/', $s)) {
            return '';
        }
        return $s;
    }

    /**
     * 当前品牌（配置非法/空则回退默认）
     *
     * @return string
     */
    public static function brand()
    {
        if (self::$brandMemo !== null) {
            return self::$brandMemo;
        }
        $raw = '';
        try {
            if (class_exists('Config')) {
                $raw = (string) Config::get(self::CONFIG_KEY, self::DEFAULT_BRAND);
            }
        } catch (Exception $e) {
            $raw = self::DEFAULT_BRAND;
        }
        $norm = self::normalizeBrand($raw);
        self::$brandMemo = ($norm !== '') ? $norm : self::DEFAULT_BRAND;
        return self::$brandMemo;
    }

    /**
     * @return string
     */
    public static function version()
    {
        return defined('VS_VERSION') ? (string) VS_VERSION : '1';
    }

    /**
     * 规范化模块后缀（可选；如 Proxy、IpLocator）
     *
     * @param string $component
     * @return string
     */
    public static function normalizeComponent($component)
    {
        $c = trim((string) $component);
        if ($c === '') {
            return '';
        }
        if (preg_match('/[\r\n\/\\\\\s]/', $c)) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,31}$/', $c)) {
            return '';
        }
        return $c;
    }

    /**
     * 拼装完整 UA 字符串
     *
     * @param string $component 空=仅品牌/版本；非空=品牌-模块/版本
     * @return string
     */
    public static function build($component = '')
    {
        return self::buildWithVersion($component, self::version());
    }

    /**
     * 指定版本号拼装（如 Updater 用 localVersion，可与 VS_VERSION 短暂不一致）
     *
     * @param string $component
     * @param string $version
     * @return string
     */
    public static function buildWithVersion($component, $version)
    {
        $brand = self::brand();
        $ver = trim((string) $version);
        if ($ver === '' || preg_match('/[\r\n\/\\\\\s]/', $ver)) {
            $ver = self::version();
        }
        $comp = self::normalizeComponent($component);
        if ($comp === '') {
            return $brand . '/' . $ver;
        }
        return $brand . '-' . $comp . '/' . $ver;
    }

    /**
     * HTTP 头行（不含末尾 CRLF）
     *
     * @param string $component
     * @return string
     */
    public static function headerLine($component = '')
    {
        return 'User-Agent: ' . self::build($component);
    }

    /**
     * 预览样例（设置页展示）
     *
     * @param string|null $brandOverride
     * @return array{plain:string,proxy:string,iplocator:string,oauth:string}
     */
    public static function previewSamples($brandOverride = null)
    {
        $prev = self::$brandMemo;
        if ($brandOverride !== null) {
            $n = self::normalizeBrand($brandOverride);
            self::$brandMemo = ($n !== '') ? $n : self::DEFAULT_BRAND;
        }
        $out = array(
            'plain'     => self::build(''),
            'proxy'     => self::build('Proxy'),
            'iplocator' => self::build('IpLocator'),
            'oauth'     => self::build('OAuth'),
        );
        self::$brandMemo = $prev;
        return $out;
    }
}
