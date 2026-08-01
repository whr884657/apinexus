<?php
/**
 * 文件：core/ProxyClientProfile.php
 * 作用：出站身份（User-Agent / Referer）内置预设与解析
 *       代理网关中继与本地脚本（ApiStats::outboundHeaders）共用
 *
 * 字段（api 表，无下划线）：
 *   upuamode     0系统默认 1内置预设 2自定义 3轮询内置
 *   upuapreset   预设键
 *   upua         自定义 UA
 *   upreferermode 0不发送 1自定义 2转发客户端
 *   upreferer    自定义 Referer
 */

class ProxyClientProfile
{
    const UAMODE_DEFAULT = 0;
    const UAMODE_PRESET = 1;
    const UAMODE_CUSTOM = 2;
    const UAMODE_ROTATE = 3;

    const REFMODE_NONE = 0;
    const REFMODE_CUSTOM = 1;
    const REFMODE_CLIENT = 2;

    /**
     * 内置设备 / 浏览器 UA（约 20 条；界面只展示 label）
     *
     * @return array<string,array{label:string,ua:string}>
     */
    public static function presets()
    {
        return array(
            'android_phone' => array(
                'label' => '安卓手机（Chrome）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
            ),
            'android_huawei' => array(
                'label' => '安卓手机（华为）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 12; ANA-AN00) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.196 Mobile Safari/537.36',
            ),
            'android_xiaomi' => array(
                'label' => '安卓手机（小米）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 13; 2201123C) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36',
            ),
            'iphone' => array(
                'label' => '苹果手机（Safari）',
                'ua'    => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            ),
            'iphone_chrome' => array(
                'label' => '苹果手机（Chrome）',
                'ua'    => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1',
            ),
            'ipad' => array(
                'label' => '苹果平板（iPad）',
                'ua'    => 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            ),
            'android_tablet' => array(
                'label' => '安卓平板',
                'ua'    => 'Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.105 Safari/537.36',
            ),
            'win_chrome' => array(
                'label' => 'Windows 电脑（Chrome）',
                'ua'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            ),
            'win_edge' => array(
                'label' => 'Windows 电脑（Edge）',
                'ua'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
            ),
            'mac_safari' => array(
                'label' => 'Mac 电脑（Safari）',
                'ua'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
            ),
            'mac_chrome' => array(
                'label' => 'Mac 电脑（Chrome）',
                'ua'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            ),
            'linux_chrome' => array(
                'label' => 'Linux 电脑（Chrome）',
                'ua'    => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            ),
            'win_laptop' => array(
                'label' => 'Windows 笔记本（Chrome）',
                'ua'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.207 Safari/537.36',
            ),
            'via_android' => array(
                'label' => 'VIA 浏览器（安卓）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 13; V2046A Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Mobile Safari/537.36 Via/5.6.0',
            ),
            'xbrowser_android' => array(
                'label' => 'X 浏览器（安卓）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 13; M2012K11AC) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Mobile Safari/537.36 XBrowser/4.6.0',
            ),
            'ig_android' => array(
                'label' => 'IG 浏览器（安卓）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 12; PEL-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/110.0.5481.154 Mobile Safari/537.36 Ig/1.0',
            ),
            'uc_android' => array(
                'label' => 'UC 浏览器（安卓）',
                'ua'    => 'Mozilla/5.0 (Linux; U; Android 13; zh-CN; 22041216C Build/TP1A.220624.014) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/123.0.6312.80 UWS/3.22.2.48 Mobile Safari/537.36 UCBrowser/15.5.0.1340',
            ),
            'qq_android' => array(
                'label' => 'QQ 浏览器（安卓）',
                'ua'    => 'Mozilla/5.0 (Linux; Android 13; V2148A) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 MQQBrowser/14.0 Mobile Safari/537.36',
            ),
            'firefox_android' => array(
                'label' => 'Firefox（安卓）',
                'ua'    => 'Mozilla/5.0 (Android 14; Mobile; rv:127.0) Gecko/127.0 Firefox/127.0',
            ),
            'safari_ios' => array(
                'label' => 'Safari（iOS 轻量）',
                'ua'    => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
            ),
        );
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public static function presetOptions()
    {
        $out = array();
        foreach (self::presets() as $key => $row) {
            $out[] = array(
                'value' => $key,
                'label' => isset($row['label']) ? (string) $row['label'] : $key,
            );
        }
        return $out;
    }

    /**
     * @param mixed $v
     * @return int
     */
    public static function normalizeUaMode($v)
    {
        $n = (int) $v;
        if ($n === self::UAMODE_PRESET || $n === self::UAMODE_CUSTOM || $n === self::UAMODE_ROTATE) {
            return $n;
        }
        return self::UAMODE_DEFAULT;
    }

    /**
     * @param mixed $v
     * @return int
     */
    public static function normalizeRefererMode($v)
    {
        $n = (int) $v;
        if ($n === self::REFMODE_CUSTOM || $n === self::REFMODE_CLIENT) {
            return $n;
        }
        return self::REFMODE_NONE;
    }

    /**
     * @param mixed $v
     * @return string
     */
    public static function normalizePresetKey($v)
    {
        $key = trim((string) $v);
        if ($key === '') {
            return '';
        }
        if (!preg_match('/^[a-z0-9_]{2,32}$/', $key)) {
            return '';
        }
        $all = self::presets();
        return isset($all[$key]) ? $key : '';
    }

    /**
     * @param mixed $v
     * @return string
     */
    public static function normalizeUa($v)
    {
        $ua = trim((string) $v);
        if ($ua === '') {
            return '';
        }
        // 禁止换行，避免头注入
        $ua = preg_replace('/[\r\n]+/', ' ', $ua);
        if (!is_string($ua)) {
            return '';
        }
        if (mb_strlen($ua, 'UTF-8') > 512) {
            $ua = mb_substr($ua, 0, 512, 'UTF-8');
        }
        return $ua;
    }

    /**
     * @param mixed $v
     * @return string
     */
    public static function normalizeReferer($v)
    {
        $ref = trim((string) $v);
        if ($ref === '') {
            return '';
        }
        $ref = preg_replace('/[\r\n]+/', '', $ref);
        if (!is_string($ref)) {
            return '';
        }
        if (mb_strlen($ref, 'UTF-8') > 500) {
            $ref = mb_substr($ref, 0, 500, 'UTF-8');
        }
        if ($ref !== '' && !preg_match('#^https?://#i', $ref)) {
            return '';
        }
        return $ref;
    }

    /**
     * @param array $row
     * @return string
     */
    public static function resolveUa(array $row)
    {
        $mode = self::normalizeUaMode(isset($row['upuamode']) ? $row['upuamode'] : 0);
        $presets = self::presets();
        $keys = array_keys($presets);

        if ($mode === self::UAMODE_CUSTOM) {
            $custom = self::normalizeUa(isset($row['upua']) ? $row['upua'] : '');
            if ($custom !== '') {
                return $custom;
            }
        }

        if ($mode === self::UAMODE_PRESET) {
            $key = self::normalizePresetKey(isset($row['upuapreset']) ? $row['upuapreset'] : '');
            if ($key !== '' && isset($presets[$key]['ua'])) {
                return (string) $presets[$key]['ua'];
            }
        }

        if ($mode === self::UAMODE_ROTATE && !empty($keys)) {
            $apiId = isset($row['id']) ? (int) $row['id'] : 0;
            // 按分钟轮询，同分钟内同一接口稳定，跨分钟换 UA
            $idx = abs(($apiId * 31 + (int) floor(time() / 60)) % count($keys));
            $pick = $keys[$idx];
            return (string) $presets[$pick]['ua'];
        }

        $ver = defined('VS_VERSION') ? VS_VERSION : '1';
        $apitype = isset($row['apitype']) ? (int) $row['apitype'] : 0;
        $prefix = ($apitype === 1) ? 'ApiNexus-Proxy/' : 'ApiNexus/';
        return $prefix . $ver;
    }

    /**
     * @param array $row
     * @return string 空串表示不发送 Referer
     */
    public static function resolveReferer(array $row)
    {
        $mode = self::normalizeRefererMode(isset($row['upreferermode']) ? $row['upreferermode'] : 0);
        if ($mode === self::REFMODE_CUSTOM) {
            return self::normalizeReferer(isset($row['upreferer']) ? $row['upreferer'] : '');
        }
        if ($mode === self::REFMODE_CLIENT) {
            $ref = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : '';
            return self::normalizeReferer($ref);
        }
        return '';
    }

    /**
     * 组装除认证外的出站头（含 UA；可选 Referer）
     *
     * @param array $row
     * @return array
     */
    public static function buildClientHeaders(array $row)
    {
        $headers = array(
            'Accept: */*',
            'User-Agent: ' . self::resolveUa($row),
        );
        $ref = self::resolveReferer($row);
        if ($ref !== '') {
            $headers[] = 'Referer: ' . $ref;
        }
        return $headers;
    }
}
