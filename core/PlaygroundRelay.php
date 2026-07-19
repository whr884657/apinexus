<?php
/**
 * 文件：core/PlaygroundRelay.php
 * 作用：可选同源中继（兼容旧主题）。默认主题 v4.8.0+ 用浏览器直连公开 endpoint，勿在此写 apilog。
 */

class PlaygroundRelay
{
    /** 上游响应体最大读取（约 16MB，供媒体预览落盘） */
    const MAX_BODY = 16777216;

    /** 小体积二进制仍可走 base64（约 384KB） */
    const MAX_BINARY_INLINE = 393216;

    /** 超时秒数 */
    const TIMEOUT = 45;

    /**
     * @param int    $apiId
     * @param string $method
     * @param array  $params  name => value（不含文件）
     * @return array{ok:bool,msg:string,http:int,contentType:string,body:string,encoding:string,displayUrl:string}
     */
    public static function execute($apiId, $method, array $params)
    {
        $apiId = (int) $apiId;
        $method = strtoupper(trim((string) $method));
        if ($method === '') {
            $method = 'GET';
        }
        if ($apiId <= 0) {
            return self::fail('请选择接口');
        }
        if (!ApiManager::tableReady()) {
            return self::fail('接口数据未就绪');
        }

        $row = ApiManager::findById($apiId);
        if (!$row) {
            return self::fail('接口不存在');
        }

        $theme = FrontendApi::formatForTheme($row);
        if ($theme === null) {
            return self::fail('该接口不可用');
        }

        $displayUrl = isset($theme['endpoint']) ? (string) $theme['endpoint'] : '';
        if (!empty($theme['maintenance'])) {
            return array(
                'ok'          => false,
                'msg'         => '该接口维护中',
                'http'        => 503,
                'contentType' => 'text/plain; charset=utf-8',
                'body'        => '维护中',
                'encoding'    => 'text',
                'displayUrl'  => $displayUrl,
            );
        }

        // 将参数注入超全局，供 guardAccess 读取密钥
        $savedGet = $_GET;
        $savedPost = $_POST;
        $savedMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        foreach ($params as $k => $v) {
            $key = (string) $k;
            if ($key === '') {
                continue;
            }
            $_GET[$key] = $v;
            $_POST[$key] = $v;
        }
        $_SERVER['REQUEST_METHOD'] = $method;

        $guard = ApiStats::guardAccess($row);
        $_GET = $savedGet;
        $_POST = $savedPost;
        $_SERVER['REQUEST_METHOD'] = $savedMethod;

        if ($guard !== true) {
            $msg = (is_array($guard) && isset($guard['msg'])) ? (string) $guard['msg'] : '无法调用';
            $code = (is_array($guard) && isset($guard['http'])) ? (int) $guard['http'] : 403;
            // 中继禁止写 apilog：REQUEST_URI 是 relay.php，会污染 path（见 E57）
            return array(
                'ok'          => false,
                'msg'         => $msg,
                'http'        => $code,
                'contentType' => 'application/json; charset=utf-8',
                'body'        => json_encode(array('code' => 0, 'msg' => $msg), JSON_UNESCAPED_UNICODE),
                'encoding'    => 'text',
                'displayUrl'  => $displayUrl,
            );
        }

        $apitype = ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : 0);
        if ($apitype === ApiManager::APITYPE_PROXY) {
            $target = trim((string) (isset($row['targeturl']) ? $row['targeturl'] : ''));
            if ($target === '' || !preg_match('#^https?://#i', $target)) {
                return self::fail('上游地址无效', 500, $displayUrl);
            }
            $upstreamParams = $params;
            unset($upstreamParams['key'], $upstreamParams['api_key'], $upstreamParams['apikey']);
            $fetchUrl = self::mergeQuery($target, $upstreamParams);
            $result = self::httpRequest($fetchUrl, $method, $upstreamParams);
            // 不在此 hitProxy：默认主题已改浏览器直连 /apis/{短码}，由 ApiProxy 记账
            $result['displayUrl'] = $displayUrl;
            return $result;
        }

        $fetchUrl = ApiManager::resolveCallUrl($row);
        if ($fetchUrl === '') {
            return self::fail('未配置调用地址', 400, $displayUrl);
        }
        // 本站密钥需带给本地接口
        $result = self::httpRequest($fetchUrl, $method, $params);
        $result['displayUrl'] = $displayUrl;
        return $result;
    }

    /**
     * @param string $msg
     * @param int    $http
     * @param string $displayUrl
     * @return array
     */
    private static function fail($msg, $http = 400, $displayUrl = '')
    {
        return array(
            'ok'          => false,
            'msg'         => (string) $msg,
            'http'        => (int) $http,
            'contentType' => 'text/plain; charset=utf-8',
            'body'        => (string) $msg,
            'encoding'    => 'text',
            'displayUrl'  => (string) $displayUrl,
        );
    }

    /**
     * 合并查询参数（所有 Method 均拼到 URL，确保 KEY 等对本地/上游可读）
     *
     * @param string $url
     * @param array  $params
     * @return string
     */
    private static function mergeQuery($url, array $params)
    {
        if ($params === array()) {
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        foreach ($params as $k => $v) {
            $query[(string) $k] = $v;
        }
        $base = '';
        if (!empty($parts['scheme'])) {
            $base .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $base .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        $base .= isset($parts['path']) ? $parts['path'] : '';
        $qs = http_build_query($query);
        return $qs !== '' ? ($base . '?' . $qs) : $base;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array  $params
     * @return array
     */
    private static function httpRequest($url, $method, array $params)
    {
        $method = strtoupper($method);
        if (!function_exists('curl_init')) {
            return self::fail('服务器未启用 curl，无法完成测试');
        }

        // 一律把参数拼进 Query（含 POST），避免上游/本地脚本只读 $_GET['key'] 时报未填密钥
        $url = self::mergeQuery($url, $params);

        $ch = curl_init();
        $headers = array('Accept: */*', 'User-Agent: ApiNexus-Playground/' . VS_VERSION);

        if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            if ($method === 'HEAD') {
                curl_setopt($ch, CURLOPT_NOBODY, true);
            } elseif ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
        } else {
            // form-urlencoded + 明确 Content-Length，避免部分上游报 No Content Length
            $bodyStr = http_build_query($params);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: ' . (string) strlen($bodyStr);
        }

        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADER         => true,
        ));

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $errno) {
            return self::fail($err !== '' ? ('请求失败：' . $err) : '请求失败');
        }

        $headerBlob = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        if (strlen($body) > self::MAX_BODY) {
            $body = substr($body, 0, self::MAX_BODY);
        }

        // 跟随重定向时 header 含多段，必须取最后一跳的 Content-Type
        $headerBlob = self::lastResponseHeaders($headerBlob);
        $contentType = 'application/octet-stream';
        if (preg_match('/^Content-Type:\s*(.+)$/mi', $headerBlob, $m)) {
            $contentType = trim($m[1]);
        }
        // 以文件魔数纠偏（上游常标错或标 octet-stream）
        $sniffed = self::sniffMediaType($body);
        if ($sniffed !== '') {
            $contentType = $sniffed;
        }

        $ctLower = strtolower($contentType);
        $isBinary = self::looksBinary($ctLower, $body);

        if ($isBinary) {
            return self::packBinaryResult($http, $contentType, $body);
        }

        // 文本须为合法 UTF-8，否则 json_encode 会失败导致前端 Unexpected end of JSON input
        $isUtf8 = function_exists('mb_check_encoding')
            ? mb_check_encoding($body, 'UTF-8')
            : (bool) preg_match('//u', $body);
        if (!$isUtf8) {
            $converted = null;
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($body, 'UTF-8', 'UTF-8, GBK, GB2312, BIG5, ISO-8859-1');
            }
            $body = is_string($converted) ? $converted : preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $body);
            $okUtf8 = is_string($body) && (
                function_exists('mb_check_encoding')
                    ? mb_check_encoding($body, 'UTF-8')
                    : (bool) preg_match('//u', $body)
            );
            if (!$okUtf8) {
                return self::packBinaryResult(
                    $http,
                    $contentType !== '' ? $contentType : 'application/octet-stream',
                    substr((string) substr($raw, $headerSize), 0, self::MAX_BODY)
                );
            }
        }

        return array(
            'ok'          => $http >= 200 && $http < 400,
            'msg'         => 'ok',
            'http'        => $http,
            'contentType' => $contentType,
            'body'        => $body,
            'encoding'    => 'text',
            'displayUrl'  => '',
        );
    }

    /**
     * 二进制：小体积 base64；大体积落盘返回同源预览 URL（供 video/img/audio 播放）
     *
     * @param int    $http
     * @param string $contentType
     * @param string $body
     * @return array
     */
    private static function packBinaryResult($http, $contentType, $body)
    {
        $ok = $http >= 200 && $http < 400;
        $sniffed = self::sniffMediaType($body);
        if ($sniffed !== '') {
            $contentType = $sniffed;
        }
        $kind = self::mediaKindFromCt($contentType);
        $len = strlen($body);
        if ($len <= self::MAX_BINARY_INLINE) {
            return array(
                'ok'          => $ok,
                'msg'         => 'ok',
                'http'        => $http,
                'contentType' => $contentType,
                'mediaKind'   => $kind,
                'body'        => base64_encode($body),
                'encoding'    => 'base64',
                'displayUrl'  => '',
            );
        }

        $mediaUrl = self::storeMediaPreview($body, $contentType);
        if ($mediaUrl === '') {
            return array(
                'ok'          => $ok,
                'msg'         => '媒体已获取但无法生成预览，请直接访问接口地址',
                'http'        => $http,
                'contentType' => $contentType,
                'mediaKind'   => $kind,
                'body'        => '',
                'encoding'    => 'omit',
                'displayUrl'  => '',
            );
        }

        return array(
            'ok'          => $ok,
            'msg'         => 'ok',
            'http'        => $http,
            'contentType' => $contentType,
            'mediaKind'   => $kind,
            'body'        => $mediaUrl,
            'encoding'    => 'url',
            'displayUrl'  => '',
        );
    }

    /**
     * 取最后一跳响应头（curl FOLLOWLOCATION 时多段头拼在一起）
     *
     * @param string $headerBlob
     * @return string
     */
    private static function lastResponseHeaders($headerBlob)
    {
        $headerBlob = (string) $headerBlob;
        if ($headerBlob === '') {
            return '';
        }
        if (!preg_match_all('/^HTTP\/\d(?:\.\d)?\s+\d+/mi', $headerBlob, $m, PREG_OFFSET_CAPTURE)) {
            return $headerBlob;
        }
        $matches = $m[0];
        $last = $matches[count($matches) - 1];
        $offset = isset($last[1]) ? (int) $last[1] : 0;
        return substr($headerBlob, $offset);
    }

    /**
     * @param string $binary
     * @return string 如 image/jpeg、video/mp4；无法识别返回空
     */
    private static function sniffMediaType($binary)
    {
        if (!is_string($binary) || strlen($binary) < 12) {
            return '';
        }
        $b = $binary;
        $h2 = substr($b, 0, 2);
        $h3 = substr($b, 0, 3);
        $h4 = substr($b, 0, 4);
        $h6 = substr($b, 0, 6);
        $h8 = substr($b, 0, 8);

        if ($h3 === "\xFF\xD8\xFF") {
            return 'image/jpeg';
        }
        if ($h8 === "\x89PNG\r\n\x1A\n") {
            return 'image/png';
        }
        if ($h6 === 'GIF87a' || $h6 === 'GIF89a') {
            return 'image/gif';
        }
        if ($h4 === 'RIFF' && substr($b, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if ($h4 === 'RIFF' && substr($b, 8, 4) === 'WAVE') {
            return 'audio/wav';
        }
        if ($h4 === 'OggS') {
            return 'audio/ogg';
        }
        if ($h3 === 'ID3' || ($h2 === "\xFF\xFB") || ($h2 === "\xFF\xF3") || ($h2 === "\xFF\xF2")) {
            return 'audio/mpeg';
        }
        // ISO BMFF: ....ftyp....
        if (strlen($b) >= 12 && substr($b, 4, 4) === 'ftyp') {
            $brand = strtolower(substr($b, 8, 4));
            if (strpos($brand, 'm4a') !== false || $brand === 'M4A ' || $brand === 'mp4a') {
                return 'audio/mp4';
            }
            return 'video/mp4';
        }
        // EBML / WebM / Matroska
        if ($h4 === "\x1A\x45\xDF\xA3") {
            return 'video/webm';
        }
        if (substr($b, 0, 4) === "\x00\x00\x01\xBA" || substr($b, 0, 4) === "\x00\x00\x01\xB3") {
            return 'video/mpeg';
        }
        return '';
    }

    /**
     * @param string $contentType
     * @return string image|audio|video|''
     */
    private static function mediaKindFromCt($contentType)
    {
        $t = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        if (strpos($t, 'image/') === 0) {
            return 'image';
        }
        if (strpos($t, 'audio/') === 0) {
            return 'audio';
        }
        if (strpos($t, 'video/') === 0) {
            return 'video';
        }
        return '';
    }

    /**
     * @param string $binary
     * @param string $contentType
     * @return string 同源预览 URL，失败返回空串
     */
    private static function storeMediaPreview($binary, $contentType)
    {
        $root = defined('VS_ROOT') ? VS_ROOT : dirname(__DIR__);
        $dir = $root . '/data/playground';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return '';
        }

        self::cleanupMediaPreview($dir);

        $token = bin2hex(random_bytes(16));
        $binPath = $dir . '/' . $token . '.bin';
        $metaPath = $dir . '/' . $token . '.json';
        if (@file_put_contents($binPath, $binary) === false) {
            return '';
        }
        $meta = array(
            'ct'      => (string) $contentType,
            'expires' => time() + 3600,
            'bytes'   => strlen($binary),
        );
        @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE));

        return rtrim(vs_base_url(), '/') . '/core/playground/media.php?t=' . rawurlencode($token);
    }

    /**
     * @param string $dir
     * @return void
     */
    private static function cleanupMediaPreview($dir)
    {
        $now = time();
        $files = @scandir($dir);
        if (!is_array($files)) {
            return;
        }
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            if (substr($f, -5) !== '.json') {
                continue;
            }
            $metaPath = $dir . '/' . $f;
            $raw = @file_get_contents($metaPath);
            $meta = is_string($raw) ? json_decode($raw, true) : null;
            $exp = (is_array($meta) && isset($meta['expires'])) ? (int) $meta['expires'] : 0;
            if ($exp > 0 && $exp > $now) {
                continue;
            }
            $token = substr($f, 0, -5);
            @unlink($metaPath);
            @unlink($dir . '/' . $token . '.bin');
        }
    }

    /**
     * @param string $ctLower
     * @param string $body
     * @return bool
     */
    private static function looksBinary($ctLower, $body)
    {
        if (preg_match('#^(image|audio|video)/#', $ctLower)) {
            return true;
        }
        if (preg_match('#octet-stream|application/pdf|application/zip|application/x-|font/#', $ctLower)) {
            return true;
        }
        if ($body === '') {
            return false;
        }
        $sample = substr($body, 0, 4096);
        if (strpos($sample, "\0") !== false) {
            return true;
        }
        return false;
    }
}
