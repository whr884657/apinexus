<?php
/**
 * 文件：core/LinkSiteMeta.php
 * 作用：抓取外站 HTML，解析 title / description / favicon（友链一键填充）
 */

class LinkSiteMeta
{
    const TIMEOUT = 8;
    const MAX_BYTES = 524288;
    const MAX_REDIRECTS = 3;

    /**
     * @param string $url
     * @return array{ok:bool,msg:string,name?:string,description?:string,icon?:string,siteurl?:string}
     */
    public static function fetch($url)
    {
        $siteurl = LinkManager::normalizeUrl($url);
        if ($siteurl === '') {
            return array('ok' => false, 'msg' => '请填写有效的网站链接');
        }
        if (!self::isPublicHttpUrl($siteurl)) {
            return array('ok' => false, 'msg' => '仅支持公网 http(s) 地址');
        }

        $html = self::httpGet($siteurl);
        if ($html === null) {
            return array('ok' => false, 'msg' => '无法访问该网站，请手动填写信息');
        }

        $meta = self::parseHtml($html, $siteurl);
        $meta['ok'] = true;
        $meta['msg'] = '获取成功';
        $meta['siteurl'] = $siteurl;
        return $meta;
    }

    /**
     * 是否允许服务端主动抓取（公网 http/https；防 SSRF）
     *
     * @param string $url
     * @return bool
     */
    public static function isAllowedFetchUrl($url)
    {
        return self::isPublicHttpUrl($url);
    }

    /**
     * @param string $url
     * @return bool
     */
    private static function isPublicHttpUrl($url)
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local' || substr($host, -5) === '.test') {
            return false;
        }
        // 拒绝十进制/十六进制/短格式等非规范 IP（curl 可能仍解析到回环）
        if (self::looksLikeIpLiteral($host)) {
            if (!filter_var($host, FILTER_VALIDATE_IP)) {
                return false;
            }
            return self::isPublicIp($host);
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }
        // 主机名形态校验
        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i', $host)
            && !preg_match('/^[a-z]([a-z0-9\-]*[a-z0-9])?$/i', $host)) {
            return false;
        }
        $ips = @gethostbynamel($host);
        // DNS 失败：fail-closed
        if (!is_array($ips) || count($ips) === 0) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 是否看起来像 IP 字面量（含非规范十进制/hex/短写）
     *
     * @param string $host
     * @return bool
     */
    private static function looksLikeIpLiteral($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return false;
        }
        if (strpos($host, ':') !== false) {
            return true;
        }
        if (preg_match('/^[\d.]+$/', $host)) {
            return true;
        }
        if (preg_match('/0x[0-9a-f]+/i', $host)) {
            return true;
        }
        if (preg_match('/^0[0-7]+(\.|$)/', $host)) {
            return true;
        }
        return false;
    }

    /**
     * @param string $ip
     * @return bool
     */
    private static function isPublicIp($ip)
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * @param string $url
     * @return string|null
     */
    private static function httpGet($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $current = $url;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (!self::isPublicHttpUrl($current)) {
                return null;
            }
            $ch = curl_init($current);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'ApiNexus-LinkMeta/1.0',
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_ENCODING       => '',
                CURLOPT_HEADER         => true,
            ));
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $headerBlob = substr($raw, 0, $headerSize);
            $body = substr($raw, $headerSize);

            if ($code >= 300 && $code < 400) {
                $next = self::locationFromHeaders($headerBlob, $current);
                if ($next === '') {
                    return null;
                }
                $current = $next;
                continue;
            }
            if ($code < 200 || $code >= 400 || !is_string($body) || $body === '') {
                return null;
            }
            if (strlen($body) > self::MAX_BYTES) {
                $body = substr($body, 0, self::MAX_BYTES);
            }
            return $body;
        }
        return null;
    }

    /**
     * @param string $headerBlob
     * @param string $baseUrl
     * @return string
     */
    private static function locationFromHeaders($headerBlob, $baseUrl)
    {
        if (!preg_match('/^Location:\s*(.+)$/im', (string) $headerBlob, $m)) {
            return '';
        }
        $loc = trim($m[1]);
        $loc = preg_replace('/[\r\n].*$/s', '', $loc);
        if ($loc === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $loc)) {
            return $loc;
        }
        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return '';
        }
        $origin = $base['scheme'] . '://' . $base['host'];
        if (!empty($base['port'])) {
            $origin .= ':' . $base['port'];
        }
        if (isset($loc[0]) && $loc[0] === '/') {
            return $origin . $loc;
        }
        $path = isset($base['path']) ? (string) $base['path'] : '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        return $origin . $dir . $loc;
    }

    /**
     * @param string $html
     * @param string $baseUrl
     * @return array{name:string,description:string,icon:string}
     */
    private static function parseHtml($html, $baseUrl)
    {
        $name = '';
        $description = '';
        $icon = '';

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $name = self::cleanText($m[1]);
        }
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/i', $html, $m)
            || preg_match('/<meta[^>]+property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)) {
            $description = self::cleanText($m[1]);
        }
        if ($name === '' && preg_match('/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)) {
            $name = self::cleanText($m[1]);
        }

        if (preg_match('/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\'](?:shortcut )?icon["\']/i', $html, $m)
            || preg_match('/<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
            $icon = self::absolutizeUrl(self::cleanText($m[1]), $baseUrl);
        }
        if ($icon === '') {
            $parts = parse_url($baseUrl);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                $icon = $parts['scheme'] . '://' . $parts['host'] . '/favicon.ico';
            }
        }

        if (mb_strlen($name, 'UTF-8') > 50) {
            $name = mb_substr($name, 0, 50, 'UTF-8');
        }
        if (mb_strlen($description, 'UTF-8') > 200) {
            $description = mb_substr($description, 0, 200, 'UTF-8');
        }

        return array(
            'name'        => $name,
            'description' => $description,
            'icon'        => $icon,
        );
    }

    /**
     * @param string $text
     * @return string
     */
    private static function cleanText($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    /**
     * @param string $href
     * @param string $baseUrl
     * @return string
     */
    private static function absolutizeUrl($href, $baseUrl)
    {
        $href = trim((string) $href);
        if ($href === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (strpos($href, '//') === 0) {
            $parts = parse_url($baseUrl);
            $scheme = (is_array($parts) && !empty($parts['scheme'])) ? $parts['scheme'] : 'https';
            return $scheme . ':' . $href;
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? (':' . $parts['port']) : '');
        if (isset($href[0]) && $href[0] === '/') {
            return $origin . $href;
        }
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        if ($dir === null || $dir === '') {
            $dir = '/';
        }
        return $origin . $dir . $href;
    }
}
