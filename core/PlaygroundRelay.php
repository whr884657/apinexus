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
     * @param string $authWay query|header|bearer；空则取接口 keyways 第一种
     * @return array{ok:bool,msg:string,http:int,contentType:string,body:string,encoding:string,displayUrl:string}
     */
    public static function execute($apiId, $method, array $params, $authWay = '')
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
            $errcode = ApiError::MAINTENANCE;
            return array(
                'ok'          => false,
                'msg'         => '该接口维护中',
                'http'        => 200,
                'errcode'     => $errcode,
                'contentType' => 'application/json; charset=utf-8',
                'body'        => json_encode(array('code' => 0, 'msg' => '该接口维护中', 'errcode' => $errcode), JSON_UNESCAPED_UNICODE),
                'encoding'    => 'text',
                'displayUrl'  => $displayUrl,
            );
        }

        // 将参数注入超全局，供 guardAccess 读取密钥（仅注入所选通道）
        $authWay = self::resolveAuthWay($row, $authWay);
        $savedGet = $_GET;
        $savedPost = $_POST;
        $savedMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $savedAuth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
        $savedRedirAuth = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : null;
        $savedXAuth = isset($_SERVER['HTTP_X_AUTHORIZATION']) ? $_SERVER['HTTP_X_AUTHORIZATION'] : null;
        $savedXApiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;
        foreach ($params as $k => $v) {
            $key = (string) $k;
            if ($key === '') {
                continue;
            }
            $_GET[$key] = $v;
            $_POST[$key] = $v;
        }
        $_SERVER['REQUEST_METHOD'] = $method;
        self::injectKeywaysForGuard($row, $params, $authWay);

        $guard = ApiStats::guardAccess($row);
        $_GET = $savedGet;
        $_POST = $savedPost;
        $_SERVER['REQUEST_METHOD'] = $savedMethod;
        if ($savedAuth === null) {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        } else {
            $_SERVER['HTTP_AUTHORIZATION'] = $savedAuth;
        }
        if ($savedRedirAuth === null) {
            unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } else {
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $savedRedirAuth;
        }
        if ($savedXAuth === null) {
            unset($_SERVER['HTTP_X_AUTHORIZATION']);
        } else {
            $_SERVER['HTTP_X_AUTHORIZATION'] = $savedXAuth;
        }
        if ($savedXApiKey === null) {
            unset($_SERVER['HTTP_X_API_KEY']);
        } else {
            $_SERVER['HTTP_X_API_KEY'] = $savedXApiKey;
        }

        if ($guard !== true) {
            $msg = (is_array($guard) && isset($guard['msg'])) ? (string) $guard['msg'] : '无法调用';
            $code = (is_array($guard) && isset($guard['errcode']))
                ? (int) $guard['errcode']
                : ApiError::UNAVAILABLE;
            // 中继禁止写 apilog：REQUEST_URI 是 relay.php，会污染 path（见 E57）
            // http=传输层语义固定 200；业务看 errcode
            return array(
                'ok'          => false,
                'msg'         => $msg,
                'http'        => 200,
                'errcode'     => $code,
                'contentType' => 'application/json; charset=utf-8',
                'body'        => json_encode(array('code' => 0, 'msg' => $msg, 'errcode' => $code), JSON_UNESCAPED_UNICODE),
                'encoding'    => 'text',
                'displayUrl'  => $displayUrl,
            );
        }

        // 收费接口须走公开 endpoint（ApiProxy/本地脚本）扣费记账；中继不履约扣费
        if (ApiManager::hasChargeColumns()) {
            $charge = ApiManager::normalizeCharge(isset($row['charge']) ? $row['charge'] : 0);
            $price = ApiManager::normalizePrice(isset($row['price']) ? $row['price'] : 0);
            if ($charge === ApiManager::CHARGE_PAID && $price > 0) {
                return self::fail('收费接口请通过公开调用地址访问，中继不支持扣费', ApiError::CHARGE_NEED_KEY, $displayUrl);
            }
        }

        $apitype = ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : 0);
        if ($apitype === ApiManager::APITYPE_PROXY) {
            $target = trim((string) (isset($row['targeturl']) ? $row['targeturl'] : ''));
            if ($target === '' || !preg_match('#^https?://#i', $target)) {
                return self::fail('上游地址无效', ApiError::UPSTREAM_BAD, $displayUrl);
            }
            if (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($target)) {
                return self::fail('上游地址不允许指向内网或非公网主机', ApiError::UPSTREAM_BLOCKED, $displayUrl);
            }
            $fwd = self::buildClientForward($params, $authWay);
            $upstreamParams = $fwd['params'];
            $built = ApiProxy::buildUpstreamRequest($target, $row, $upstreamParams);
            if (!is_array($built)) {
                return self::fail((string) $built, ApiError::UPSTREAM_BAD, $displayUrl);
            }
            // 上游只带 ApiProxy 拼装的头；本站调用方密钥已在 guard 阶段校验，勿转给上游
            $result = self::httpRequest($built['url'], $method, $upstreamParams, $built['headers']);
            // 与正式网关一致：对代理 JSON 应用字段改写（无论上游业务成功与否）
            if (is_array($result)
                && class_exists('ProxyJsonRewrite') && ProxyJsonRewrite::hasColumn()) {
                $rewriteCfg = isset($row['jsonrewrite']) ? (string) $row['jsonrewrite'] : '';
                if ($rewriteCfg !== '' && isset($result['body'], $result['contentType'])) {
                    $rewritten = ProxyJsonRewrite::apply(
                        (string) $result['body'],
                        (string) $result['contentType'],
                        $rewriteCfg
                    );
                    if (!empty($rewritten['changed'])) {
                        $result['body'] = (string) $rewritten['body'];
                        $result['contentType'] = (string) $rewritten['contentType'];
                        $result['encoding'] = 'text';
                    }
                }
            }
            $result['displayUrl'] = $displayUrl;
            return $result;
        }

        $fetchUrl = ApiManager::resolveCallUrl($row);
        if ($fetchUrl === '') {
            return self::fail('未配置调用地址', ApiError::UPSTREAM_BAD, $displayUrl);
        }
        // 绝对 URL（含历史「本地直连」）必须过 SSRF 白名单；本站相对路径拼出的同源地址放行
        if (preg_match('#^https?://#i', $fetchUrl)) {
            $base = rtrim(vs_base_url(), '/');
            $isSameOrigin = (stripos($fetchUrl, $base . '/') === 0 || strcasecmp(rtrim($fetchUrl, '/'), $base) === 0);
            if (!$isSameOrigin && class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($fetchUrl)) {
                return self::fail('调用地址不允许指向内网或非公网主机', ApiError::UPSTREAM_BLOCKED, $displayUrl);
            }
        }
        // 本站密钥按所选通道带给本地接口（禁止 header/bearer 仍塞进 Query）
        $fwd = self::buildClientForward($params, $authWay);
        $result = self::httpRequest($fetchUrl, $method, $fwd['params'], $fwd['headers']);
        $result['displayUrl'] = $displayUrl;
        return $result;
    }

    /**
     * @param array  $row
     * @param string $authWay
     * @return string
     */
    private static function resolveAuthWay(array $row, $authWay)
    {
        $allowed = ApiManager::normalizeKeyways(isset($row['keyways']) ? $row['keyways'] : 'query');
        $way = strtolower(trim((string) $authWay));
        if ($way !== '' && in_array($way, $allowed, true)) {
            return $way;
        }
        return isset($allowed[0]) ? $allowed[0] : 'query';
    }

    /**
     * @param array  $params
     * @param string $authWay
     * @return array{params:array,headers:array}
     */
    private static function buildClientForward(array $params, $authWay)
    {
        $next = array();
        $secret = '';
        foreach ($params as $k => $v) {
            $n = strtolower((string) $k);
            if ($n === 'key' || $n === 'api_key' || $n === 'apikey') {
                $val = trim((string) $v);
                if ($val !== '' && $secret === '') {
                    $secret = $val;
                }
                continue;
            }
            $next[(string) $k] = $v;
        }
        $way = strtolower(trim((string) $authWay));
        $headers = array();
        if ($secret !== '') {
            if ($way === 'header') {
                $headers[] = 'X-API-Key: ' . $secret;
            } elseif ($way === 'bearer') {
                $headers[] = 'Authorization: Bearer ' . $secret;
                $headers[] = 'X-Authorization: Bearer ' . $secret;
            } else {
                $next['key'] = $secret;
            }
        }
        return array('params' => $next, 'headers' => $headers);
    }

    /**
     * @param string $msg
     * @param int    $errcode 业务错误码（ApiError::*）
     * @param string $displayUrl
     * @return array
     */
    private static function fail($msg, $errcode = 0, $displayUrl = '')
    {
        $errcode = (int) $errcode;
        if ($errcode > 0 && $errcode < 1000) {
            // 与 vs_api_error_exit 对齐；上游拦截请显式传 ApiError::UPSTREAM_BLOCKED
            $legacy = array(
                401 => ApiError::NO_KEY,
                402 => ApiError::NO_POINTS,
                403 => ApiError::DISABLED,
                429 => ApiError::QPM,
                500 => ApiError::SERVER,
                502 => ApiError::UPSTREAM_FAIL,
                503 => ApiError::MAINTENANCE,
            );
            $errcode = isset($legacy[$errcode]) ? $legacy[$errcode] : ApiError::UNAVAILABLE;
        }
        if ($errcode <= 0) {
            $errcode = ApiError::UNAVAILABLE;
        }
        return array(
            'ok'          => false,
            'msg'         => (string) $msg,
            'http'        => 200,
            'errcode'     => $errcode,
            'contentType' => 'application/json; charset=utf-8',
            'body'        => json_encode(array(
                'code'    => 0,
                'msg'     => (string) $msg,
                'errcode' => $errcode,
            ), JSON_UNESCAPED_UNICODE),
            'encoding'    => 'text',
            'displayUrl'  => (string) $displayUrl,
        );
    }

    /**
     * 中继守卫：仅注入所选鉴权通道，避免 header/bearer 接口被 query 误伤
     *
     * @param array  $row
     * @param array  $params
     * @param string $authWay
     * @return void
     */
    private static function injectKeywaysForGuard(array $row, array $params, $authWay = '')
    {
        $secret = '';
        foreach ($params as $k => $v) {
            $n = strtolower((string) $k);
            if ($n === 'key' || $n === 'api_key' || $n === 'apikey') {
                $val = trim((string) $v);
                if ($val !== '') {
                    $secret = $val;
                    break;
                }
            }
        }
        foreach (array('key', 'api_key', 'apikey', 'API_KEY', 'ApiKey') as $nk) {
            unset($_GET[$nk], $_POST[$nk]);
        }
        unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_AUTHORIZATION']);
        if ($secret === '') {
            return;
        }
        $way = self::resolveAuthWay($row, $authWay);
        if ($way === 'header') {
            $_SERVER['HTTP_X_API_KEY'] = $secret;
        } elseif ($way === 'bearer') {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $secret;
            $_SERVER['HTTP_X_AUTHORIZATION'] = 'Bearer ' . $secret;
        } else {
            $_GET['key'] = $secret;
            $_POST['key'] = $secret;
        }
    }

    /**
     * 解析 Location 相对/绝对重定向为目标绝对 URL
     *
     * @param string $base
     * @param string $location
     * @return string
     */
    private static function resolveRedirectUrl($base, $location)
    {
        $location = trim((string) $location);
        if ($location === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        if (isset($location[0]) && $location[0] === '/') {
            return $origin . $location;
        }
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        if ($dir === null || $dir === '') {
            $dir = '/';
        }
        return $origin . $dir . $location;
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
     * @param array  $extraHeaders 额外请求头（如上游 Authorization）
     * @return array
     */
    private static function httpRequest($url, $method, array $params, array $extraHeaders = array())
    {
        $method = strtoupper($method);
        if (!function_exists('curl_init')) {
            return self::fail('服务器未启用 curl，无法完成测试');
        }

        // 一律把「业务参数」拼进 Query；密钥是否进 Query 由上层 buildClientForward 决定
        if ($params !== array()) {
            $url = self::mergeQuery($url, $params);
        }

        $ch = curl_init();
        $headers = array('Accept: */*', 'User-Agent: ApiNexus-Playground/' . VS_VERSION);
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $h) {
                $h = trim((string) $h);
                if ($h !== '') {
                    $headers[] = $h;
                }
            }
        }

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
            // 禁止自动跟随：重定向须二次校验主机，防 SSRF
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            // 与 ApiProxy 一致：兼容上游坏证书；目标 URL 已做公网校验
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
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

        // 手动跟随有限次重定向，每跳校验公网 URL
        $redirLeft = 5;
        while ($redirLeft > 0 && ($http === 301 || $http === 302 || $http === 303 || $http === 307 || $http === 308)) {
            $headerBlob = substr($raw, 0, $headerSize);
            $loc = '';
            if (preg_match('/^Location:\s*(.+)$/mi', $headerBlob, $lm)) {
                $loc = trim($lm[1]);
            }
            if ($loc === '') {
                break;
            }
            $next = self::resolveRedirectUrl($url, $loc);
            if ($next === '' || (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($next))) {
                return self::fail('上游重定向目标不允许', ApiError::UPSTREAM_BLOCKED);
            }
            $url = $next;
            $redirLeft--;
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_HEADER         => true,
                CURLOPT_HTTPGET        => true,
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
        // SVG 可含脚本，禁止作为媒体预览
        if ($t === 'image/svg+xml' || $t === 'image/svg') {
            return '';
        }
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
     * 媒体预览 Content-Type 白名单
     *
     * @param string $contentType
     * @return string
     */
    private static function sanitizeMediaContentType($contentType)
    {
        $t = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        $allow = array(
            'image/jpeg' => true,
            'image/jpg'  => true,
            'image/png'  => true,
            'image/gif'  => true,
            'image/webp' => true,
            'audio/mpeg' => true,
            'audio/mp3'  => true,
            'audio/wav'  => true,
            'audio/ogg'  => true,
            'audio/mp4'  => true,
            'audio/webm' => true,
            'video/mp4'  => true,
            'video/webm' => true,
            'video/mpeg' => true,
        );
        if (isset($allow[$t])) {
            return $t === 'image/jpg' ? 'image/jpeg' : $t;
        }
        return 'application/octet-stream';
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
        $denyHt = $dir . '/.htaccess';
        if (!is_file($denyHt)) {
            @file_put_contents($denyHt, "Require all denied\nDeny from all\n");
        }
        $dataDeny = $root . '/data/.htaccess';
        if (!is_file($dataDeny)) {
            @file_put_contents($dataDeny, "Require all denied\nDeny from all\n");
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
        // 仅允许安全媒体类型，避免 SVG 等在站点源执行脚本
        $ct = self::sanitizeMediaContentType($contentType);
        $meta = array(
            'ct'      => $ct,
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
