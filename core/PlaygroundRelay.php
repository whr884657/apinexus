<?php
/**
 * 文件：core/PlaygroundRelay.php
 * 作用：前台在线测试同源中继 —— 服务端代发请求，避免浏览器跨域 Failed to fetch
 */

class PlaygroundRelay
{
    /** 响应体最大字节（约 2MB；过大时预览截断，避免 JSON 编码撑爆） */
    const MAX_BODY = 2097152;

    /** 二进制预览上限（约 768KB；视频等更大则只提示） */
    const MAX_BINARY_PREVIEW = 786432;

    /** 超时秒数 */
    const TIMEOUT = 25;

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
            if (ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : 0) === ApiManager::APITYPE_PROXY) {
                ApiStats::hitProxy($row, false, $code);
            }
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
                ApiStats::hitProxy($row, false, 500);
                return self::fail('上游地址无效', 500, $displayUrl);
            }
            $upstreamParams = $params;
            unset($upstreamParams['key'], $upstreamParams['api_key'], $upstreamParams['apikey']);
            $fetchUrl = self::mergeQuery($target, $upstreamParams, $method);
            $result = self::httpRequest($fetchUrl, $method, $upstreamParams);
            ApiStats::hitProxy($row, !empty($result['ok']), isset($result['http']) ? (int) $result['http'] : 0);
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
     * @param string $url
     * @param array  $params
     * @param string $method
     * @return string
     */
    private static function mergeQuery($url, array $params, $method)
    {
        $method = strtoupper($method);
        if ($method !== 'GET' && $method !== 'HEAD' && $method !== 'OPTIONS') {
            return $url;
        }
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

        $ch = curl_init();
        $headers = array('Accept: */*', 'User-Agent: ApiNexus-Playground/' . VS_VERSION);

        if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
            $url = self::mergeQuery($url, $params, $method);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            if ($method === 'HEAD') {
                curl_setopt($ch, CURLOPT_NOBODY, true);
            } elseif ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json !== false ? $json : '{}');
            $headers[] = 'Content-Type: application/json; charset=utf-8';
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

        $contentType = 'text/plain; charset=utf-8';
        if (preg_match('/^Content-Type:\s*(.+)$/mi', $headerBlob, $m)) {
            $contentType = trim($m[1]);
        }

        $ctLower = strtolower($contentType);
        $isBinary = self::looksBinary($ctLower, $body);

        if ($isBinary) {
            $len = strlen($body);
            if ($len > self::MAX_BINARY_PREVIEW) {
                return array(
                    'ok'          => $http >= 200 && $http < 400,
                    'msg'         => '媒体体积较大，在线预览已跳过，请直接访问接口地址',
                    'http'        => $http,
                    'contentType' => $contentType,
                    'body'        => '',
                    'encoding'    => 'omit',
                    'displayUrl'  => '',
                );
            }
            return array(
                'ok'          => $http >= 200 && $http < 400,
                'msg'         => 'ok',
                'http'        => $http,
                'contentType' => $contentType,
                'body'        => base64_encode($body),
                'encoding'    => 'base64',
                'displayUrl'  => '',
            );
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
                $rawBody = substr((string) substr($raw, $headerSize), 0, self::MAX_BINARY_PREVIEW);
                return array(
                    'ok'          => $http >= 200 && $http < 400,
                    'msg'         => '上游返回非文本内容，已按二进制处理',
                    'http'        => $http,
                    'contentType' => $contentType !== '' ? $contentType : 'application/octet-stream',
                    'body'        => base64_encode($rawBody),
                    'encoding'    => 'base64',
                    'displayUrl'  => '',
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
        // 无 Content-Type 或标成 text 但仍含大量空字节 → 当二进制
        $sample = substr($body, 0, 4096);
        if (strpos($sample, "\0") !== false) {
            return true;
        }
        return false;
    }
}
