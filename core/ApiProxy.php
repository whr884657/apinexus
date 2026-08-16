<?php
/**
 * 文件：core/ApiProxy.php
 * 作用：代理外链网关 —— 公开地址转发上游
 *
 * 出站（美观）：
 *   /apis/{proxyslug}?foo=1
 *
 * 解析规则（强制 · v13.25.0 起写死）：
 *   - 公开入口**只认短码 proxyslug**，查询条件仅为 `proxyslug` + 代理类型
 *   - **禁止**按数据库主键 `api.id` 解析；路径里即使是纯数字，也只当短码字符串匹配
 *   - 不存在「把短码换成接口 ID 即可调用」的能力；ID 仅用于后台/本地 ApiStats::hit
 *
 * 转发策略（v13.4.0+）：
 *   - 一律先由本站 curl 请求上游（无论上游是否需要密钥），不对「上游接口 URL」本身做网关 302
 *   - 上游 HTTP 方法由 api.upmethod 决定（0=GET / 1=POST，v13.22.5）；可与调用方方法不同
 *   - 调用方 method 字段声明允许的 GET/POST；不在声明内则 errcode 11018
 *   - 上游响应为 JSON/TXT/二进制等：透传状态码、Content-Type 与正文
 *   - 若配置了 jsonrewrite：仅对合法 JSON 正文做字段级 set/del（见 ProxyJsonRewrite；TXT/二进制不改）
 *   - v13.25.0：剥离调用方 callback/jsonp 参数，禁止 JSONP 透传上游（JsonpGuard）
 *   - v13.25.0：出站 JSON 擦除 /admin 等敏感路径（ApiOutboundSanitize）
 *   - v13.25.2：业务错误体（errcode 11001～11018）禁止改写，并强制只保留 code/msg/errcode
 *   - 上游响应为 3xx + Location（如随机视频跳转）：校验公网后原样把跳转还给调用方（v13.4.1）
 *     禁止服务端跟随跳转去拉最终大文件（视频等）
 *   - 可配置上游认证、出站 User-Agent / Referer（见 ProxyClientProfile）
 *
 * 入站短码来源（按序）：
 *   1) $_GET['_vs_slug'] —— Nginx/Apache 伪静态内部参数
 *   2) PATH_INFO —— /apis.php/{短码}
 *   3) REQUEST_URI 形如 /apis/{短码} 且当前脚本为 apis.php
 */

class ApiProxy
{
    /** 伪静态内部短码参数名（仅 rewrite 注入，不出现在公开出站 URL） */
    const REWRITE_SLUG_PARAM = '_vs_slug';

    /** 对外公开路径前缀（去 .php，美观） */
    const PUBLIC_PREFIX = '/apis';

    /** 中继响应体上限（约 16MB） */
    const RELAY_MAX_BODY = 16777216;

    /** 中继超时秒数 */
    const RELAY_TIMEOUT = 45;

    /**
     * 按短码查代理接口行（不过滤状态/审核，供网关返回明确错误文案）
     *
     * 强制：仅 `proxyslug` 等值匹配；**永不** `WHERE id = ?`。
     * 纯数字短码（如 016）若存在，是短码碰巧为数字，不是按主键 ID 访问。
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug($slug)
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '' || !ApiManager::tableReady() || !ApiManager::hasProxyColumns()) {
            return null;
        }

        try {
            $pdo = Database::connect();
            // 禁止改成按 id 查询；公开网关只认短码
            $sql = 'SELECT * FROM `' . ApiManager::table() . '`
                    WHERE `proxyslug` = ? AND `apitype` = ?
                    LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($slug, ApiManager::APITYPE_PROXY));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 根据短码查已通过且状态正常的代理接口（不含禁用/未审）
     *
     * @param string $slug
     * @return array|null
     */
    public static function findCallableBySlug($slug)
    {
        $row = self::findBySlug($slug);
        if (!$row) {
            return null;
        }

        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : 0);
        if ($status === ApiManager::STATUS_DISABLED) {
            return null;
        }
        if ($status === ApiManager::STATUS_MAINTENANCE) {
            return null;
        }
        if (ApiManager::hasAuditColumn()) {
            $audit = ApiManager::normalizeAuditStatus(isset($row['audit']) ? $row['audit'] : 1);
            if ($audit !== ApiManager::AUDIT_APPROVED) {
                return null;
            }
        }
        return $row;
    }

    /**
     * @return string
     */
    public static function requestPathInfo()
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            return (string) $_SERVER['PATH_INFO'];
        }

        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
        if ($script === '') {
            return '';
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }

        $scriptBase = basename($script);
        $pos = strrpos($path, '/' . $scriptBase);
        if ($pos === false) {
            return '';
        }
        $after = substr($path, $pos + strlen('/' . $scriptBase));
        if ($after === '' || $after[0] !== '/') {
            return '';
        }
        return $after;
    }

    /**
     * @return string
     */
    public static function resolveSlugFromRequest()
    {
        if (isset($_GET[self::REWRITE_SLUG_PARAM])) {
            $slug = self::normalizeSlug((string) $_GET[self::REWRITE_SLUG_PARAM]);
            if ($slug !== '') {
                return $slug;
            }
        }

        $info = self::requestPathInfo();
        if ($info !== '' && $info !== '/') {
            $parts = explode('/', trim($info, '/'));
            if (isset($parts[0]) && $parts[0] !== '') {
                $slug = self::normalizeSlug($parts[0]);
                if ($slug !== '') {
                    return $slug;
                }
            }
        }

        $script = isset($_SERVER['SCRIPT_NAME'])
            ? basename(str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']))
            : '';
        if (strcasecmp($script, 'apis.php') !== 0) {
            return '';
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        if (preg_match('#/apis/([A-Za-z0-9]{3,64})/?$#', $path, $m)) {
            return self::normalizeSlug($m[1]);
        }

        return '';
    }

    /**
     * @return bool
     */
    public static function isGatewayRequest()
    {
        return self::resolveSlugFromRequest() !== '';
    }

    /**
     * 处理 HTTP 请求：一律服务端中继（v13.4.0 起不再对调用方 302）
     *
     * @param string|null $slug
     * @return void
     */
    public static function handleRequest($slug = null)
    {
        if ($slug === null) {
            $slug = self::resolveSlugFromRequest();
        } else {
            $slug = self::normalizeSlug($slug);
        }

        $row = self::findBySlug($slug);
        if (!$row) {
            // 短码不存在：不尝试用数字当 api.id 二次查找
            vs_api_error_exit(ApiError::NOT_FOUND, '接口不存在');
        }

        $gate = ApiStats::guardAccess($row);
        if ($gate !== true) {
            $errcode = isset($gate['errcode']) ? (int) $gate['errcode'] : ApiError::UNAVAILABLE;
            $msg = isset($gate['msg']) ? (string) $gate['msg'] : '接口不可用';
            ApiStats::hitProxy($row, false, $errcode);
            vs_api_error_exit($errcode, $msg);
        }

        $target = trim((string) (isset($row['targeturl']) ? $row['targeturl'] : ''));
        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            ApiStats::hitProxy($row, false, ApiError::UPSTREAM_BAD);
            vs_api_error_exit(ApiError::UPSTREAM_BAD, '上游地址无效');
        }
        if (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($target)) {
            ApiStats::hitProxy($row, false, ApiError::UPSTREAM_BLOCKED);
            vs_api_error_exit(ApiError::UPSTREAM_BLOCKED, '上游地址不允许指向内网或非公网主机');
        }

        $clientMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ($clientMethod === '') {
            $clientMethod = 'GET';
        }
        if ($clientMethod !== 'OPTIONS') {
            $checkMethod = ($clientMethod === 'HEAD') ? 'GET' : $clientMethod;
            $allowed = ApiManager::normalizeMethods(isset($row['method']) ? $row['method'] : ApiManager::METHOD_GET);
            if (!in_array($checkMethod, $allowed, true)) {
                ApiStats::hitProxy($row, false, ApiError::BAD_METHOD);
                vs_api_error_exit(ApiError::BAD_METHOD, '请求方式不允许，请使用本接口支持的方式调用');
            }
        }

        $collected = self::collectClientPayload();

        // 付费代理：先扣积分再中继（失败由 hitProxy 退回）
        $prepaid = ApiStats::chargeProxyUpfront($row);
        if ($prepaid !== true) {
            $errcode = isset($prepaid['errcode']) ? (int) $prepaid['errcode'] : ApiError::NO_POINTS;
            $msg = isset($prepaid['msg']) ? (string) $prepaid['msg'] : '积分余额不足';
            ApiStats::hitProxy($row, false, $errcode);
            vs_api_error_exit($errcode, $msg);
        }

        self::relayToUpstream($row, $target, $collected);
    }

    /**
     * 收集调用方 Query / 表单 / JSON 参数（已剥离平台密钥字段）
     *
     * @return array{params:array<string,mixed>,rawBody:string,contentType:string}
     */
    private static function collectClientPayload()
    {
        $params = array();
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if (is_array($v)) {
                    continue;
                }
                $key = (string) $k;
                $keyLower = strtolower($key);
                if ($key === '' || $key === self::REWRITE_SLUG_PARAM
                    || $keyLower === 'key' || $keyLower === 'api_key' || $keyLower === 'apikey') {
                    continue;
                }
                // 禁止 JSONP 回调参数转发给上游（防反射型 XSS）
                if (class_exists('JsonpGuard') && JsonpGuard::isJsonpParamName($key)) {
                    continue;
                }
                $params[$key] = $v;
            }
        }
        if (!empty($_POST) && is_array($_POST)) {
            $post = self::stripPlatformKeyFieldsFromArray($_POST);
            foreach ($post as $k => $v) {
                if (is_array($v)) {
                    continue;
                }
                if (class_exists('JsonpGuard') && JsonpGuard::isJsonpParamName((string) $k)) {
                    continue;
                }
                $params[(string) $k] = $v;
            }
        }

        $contentType = isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : '';
        $rawIn = file_get_contents('php://input');
        $rawBody = is_string($rawIn) ? $rawIn : '';
        if ($rawBody !== '') {
            $rawBody = self::stripPlatformKeyFieldsFromBody($rawBody, $contentType);
        }

        // application/json：把一层对象键值并入 params（供 GET 上游或表单化 POST）
        if ($rawBody !== '' && stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (is_array($v) || is_object($v)) {
                        continue;
                    }
                    $key = (string) $k;
                    $keyLower = strtolower($key);
                    if ($key === '' || $keyLower === 'key' || $keyLower === 'api_key' || $keyLower === 'apikey') {
                        continue;
                    }
                    if (class_exists('JsonpGuard') && JsonpGuard::isJsonpParamName($key)) {
                        continue;
                    }
                    if (!array_key_exists($key, $params)) {
                        $params[$key] = $v;
                    }
                }
            }
        }

        return array(
            'params'      => $params,
            'rawBody'     => $rawBody,
            'contentType' => $contentType,
        );
    }

    /**
     * 组装上游请求（URL + 头 + 正文；按 upmethod）
     *
     * @param string $targetUrl
     * @param array  $row
     * @param array  $clientParams 扁平参数
     * @param array  $payload      collectClientPayload 结果（可选）
     * @return array{url:string,headers:array,method:string,body:string,contentType:string}|string
     */
    public static function buildUpstreamRequest($targetUrl, array $row, array $clientParams, array $payload = array())
    {
        $targetUrl = trim((string) $targetUrl);
        if ($targetUrl === '' || !preg_match('#^https?://#i', $targetUrl)) {
            return '上游地址无效';
        }

        $upmethod = ApiManager::hasUpmethodColumn()
            ? ApiManager::normalizeUpmethod(isset($row['upmethod']) ? $row['upmethod'] : ApiManager::UPMETHOD_GET)
            : ApiManager::UPMETHOD_GET;
        $httpMethod = ApiManager::upmethodHttp($upmethod);

        $params = array();
        foreach ($clientParams as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $key = (string) $k;
            $keyLower = strtolower($key);
            if ($key === '' || $keyLower === 'key' || $keyLower === 'api_key' || $keyLower === 'apikey'
                || $key === self::REWRITE_SLUG_PARAM) {
                continue;
            }
            $params[$key] = $v;
        }

        $headers = array();
        $queryExtra = array();
        $upauth = ApiManager::hasUpstreamAuthColumns()
            ? ApiManager::normalizeUpauth(isset($row['upauth']) ? $row['upauth'] : 0)
            : ApiManager::UPAUTH_NONE;
        $upkey = isset($row['upkey']) ? trim((string) $row['upkey']) : '';
        $upkeyQueryName = '';

        if ($upauth === ApiManager::UPAUTH_APIKEY) {
            if ($upkey === '') {
                return '上游密钥未配置';
            }
            $via = ApiManager::normalizeUpkeyvia(isset($row['upkeyvia']) ? $row['upkeyvia'] : 0);
            $name = ApiManager::normalizeUpkeyname(isset($row['upkeyname']) ? $row['upkeyname'] : '');
            if ($name === '') {
                $name = ($via === ApiManager::UPKEYVIA_HEADER) ? 'X-API-Key' : 'api_key';
            }
            if ($via === ApiManager::UPKEYVIA_HEADER) {
                $headers[] = $name . ': ' . $upkey;
            } else {
                $queryExtra[$name] = $upkey;
                $upkeyQueryName = $name;
            }
        } elseif ($upauth === ApiManager::UPAUTH_BEARER) {
            if ($upkey === '') {
                return '上游密钥未配置';
            }
            $headers[] = 'Authorization: Bearer ' . $upkey;
        }

        $rawBody = isset($payload['rawBody']) ? (string) $payload['rawBody'] : '';
        $clientCt = isset($payload['contentType']) ? (string) $payload['contentType'] : '';
        $body = '';
        $contentType = '';

        if ($upmethod === ApiManager::UPMETHOD_GET) {
            $url = self::mergeQuery($targetUrl, array_merge($params, $queryExtra));
            return array(
                'url'         => $url,
                'headers'     => $headers,
                'method'      => 'GET',
                'body'        => '',
                'contentType' => '',
            );
        }

        // POST 上游：业务参数进正文；Query 仅保留上游 Key（若配置为 Query）
        $url = self::mergeQuery($targetUrl, $queryExtra);
        $isJson = ($rawBody !== '' && stripos($clientCt, 'application/json') !== false);
        if ($isJson) {
            $body = $rawBody;
            $contentType = 'application/json';
            // JSON 已转发时，仍把未进 JSON 的扁平参数并入 Query（不含 upkey 重复）
            $urlParams = $params;
            if ($upkeyQueryName !== '' && array_key_exists($upkeyQueryName, $urlParams)) {
                unset($urlParams[$upkeyQueryName]);
            }
            if ($urlParams !== array()) {
                $url = self::mergeQuery($url, $urlParams);
            }
        } else {
            $bodyParams = $params;
            if ($upkeyQueryName !== '' && array_key_exists($upkeyQueryName, $bodyParams)) {
                unset($bodyParams[$upkeyQueryName]);
            }
            $body = http_build_query($bodyParams);
            $contentType = 'application/x-www-form-urlencoded';
        }

        return array(
            'url'         => $url,
            'headers'     => $headers,
            'method'      => $httpMethod,
            'body'        => $body,
            'contentType' => $contentType,
        );
    }

    /**
     * @param array  $row
     * @param string $target
     * @param array  $collected collectClientPayload()
     * @return void
     */
    private static function relayToUpstream(array $row, $target, array $collected)
    {
        if (!function_exists('curl_init')) {
            ApiStats::hitProxy($row, false, ApiError::SERVER);
            vs_api_error_exit(ApiError::SERVER, '服务器未启用 curl，无法完成代理');
        }

        $params = isset($collected['params']) && is_array($collected['params']) ? $collected['params'] : array();
        $built = self::buildUpstreamRequest($target, $row, $params, $collected);
        if (!is_array($built)) {
            ApiStats::hitProxy($row, false, ApiError::UPSTREAM_BAD);
            vs_api_error_exit(ApiError::UPSTREAM_BAD, (string) $built);
        }

        $method = isset($built['method']) ? strtoupper((string) $built['method']) : 'GET';
        if ($method !== 'POST') {
            $method = 'GET';
        }
        $body = isset($built['body']) ? (string) $built['body'] : '';
        $contentType = isset($built['contentType']) ? (string) $built['contentType'] : '';

        $clientHeaders = class_exists('ProxyClientProfile')
            ? ProxyClientProfile::buildClientHeaders($row)
            : array('Accept: */*', 'User-Agent: ApiNexus-Proxy/' . (defined('VS_VERSION') ? VS_VERSION : '1'));
        $headers = array_merge($clientHeaders, $built['headers']);
        if ($contentType !== '' && $method === 'POST') {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $url = $built['url'];
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => self::RELAY_TIMEOUT,
            // 兼容上游过期/自签/链不完整证书；仍限 HTTP(S)，公网 URL 已由上游地址校验把关
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADER         => true,
            CURLOPT_ENCODING       => '',
        ));
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $errno) {
            ApiStats::hitProxy($row, false, ApiError::UPSTREAM_FAIL);
            vs_api_error_exit(ApiError::UPSTREAM_FAIL, '上游请求失败');
        }

        // 上游若返回跳转（如随机视频 302），透传 Location；禁止跟随拉取最终大文件
        if ($http === 301 || $http === 302 || $http === 303 || $http === 307 || $http === 308) {
            $headerBlob = substr($raw, 0, $headerSize);
            $loc = '';
            if (preg_match('/^Location:\s*(.+)$/mi', $headerBlob, $lm)) {
                $loc = trim($lm[1]);
            }
            if ($loc !== '') {
                $next = self::resolveRedirectUrl($url, $loc);
                if ($next === '' || (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($next))) {
                    ApiStats::hitProxy($row, false, ApiError::UPSTREAM_BLOCKED);
                    vs_api_error_exit(ApiError::UPSTREAM_BLOCKED, '上游重定向目标不允许');
                }
                ApiStats::hitProxy($row, true, $http);
                header('Cache-Control: no-store');
                http_response_code($http);
                header('Location: ' . $next, true, $http);
                exit;
            }
        }

        $respHeaders = substr($raw, 0, $headerSize);
        $respBody = substr($raw, $headerSize);
        if (strlen($respBody) > self::RELAY_MAX_BODY) {
            $respBody = substr($respBody, 0, self::RELAY_MAX_BODY);
        }

        // 提取上游 Content-Type，供 JSON 改写判定
        $upstreamCt = '';
        if (preg_match('/^Content-Type:\s*(.+)$/mi', (string) $respHeaders, $ctm)) {
            $upstreamCt = trim($ctm[1]);
        }

        // 代理 JSON 字段改写（仅 JSON；失败则原样透传）
        $jsonRewritten = false;
        if (class_exists('ProxyJsonRewrite') && ProxyJsonRewrite::hasColumn()) {
            $rewriteCfg = isset($row['jsonrewrite']) ? (string) $row['jsonrewrite'] : '';
            if ($rewriteCfg !== '') {
                $rewritten = ProxyJsonRewrite::apply($respBody, $upstreamCt, $rewriteCfg);
                if (!empty($rewritten['changed']) && isset($rewritten['body'])) {
                    $respBody = (string) $rewritten['body'];
                    if (isset($rewritten['contentType']) && (string) $rewritten['contentType'] !== '') {
                        $upstreamCt = (string) $rewritten['contentType'];
                    }
                    $jsonRewritten = true;
                }
            }
        }

        // 出站消毒：擦除 /admin 等敏感路径（含改写写入的后台 URL）
        if (class_exists('ApiOutboundSanitize')) {
            $scrub = ApiOutboundSanitize::scrubJsonBody($respBody, $upstreamCt);
            if (!empty($scrub['changed']) && isset($scrub['body'])) {
                $respBody = (string) $scrub['body'];
                $jsonRewritten = true;
            }
            // 业务错误体收窄：去掉 api_info 等附加字段（含改写灌入的 developer）
            $narrow = ApiOutboundSanitize::narrowBusinessErrorBody($respBody, $upstreamCt);
            if (!empty($narrow['changed']) && isset($narrow['body'])) {
                $respBody = (string) $narrow['body'];
                $jsonRewritten = true;
                $upstreamCt = 'application/json; charset=utf-8';
            }
        }

        $ok = ($http >= 200 && $http < 400);
        ApiStats::hitProxy($row, $ok, $http > 0 ? $http : 502);

        if ($http > 0) {
            http_response_code($http);
        }
        header('Cache-Control: no-store');

        $skip = array(
            'transfer-encoding', 'connection', 'keep-alive', 'proxy-authenticate',
            'proxy-authorization', 'te', 'trailer', 'upgrade', 'content-length',
            'content-encoding', 'location', 'set-cookie', 'set-cookie2',
        );
        // 改写后强制覆盖 Content-Type，避免上游旧类型残留
        if ($jsonRewritten) {
            $skip[] = 'content-type';
        }
        $lines = preg_split('/\r\n|\n|\r/', (string) $respHeaders);
        $sentCt = false;
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if ($line === '' || strpos($line, ':') === false) {
                    continue;
                }
                if (stripos($line, 'HTTP/') === 0) {
                    continue;
                }
                list($hk, $hv) = explode(':', $line, 2);
                $hkTrim = strtolower(trim($hk));
                if (in_array($hkTrim, $skip, true)) {
                    continue;
                }
                if ($hkTrim === 'content-type') {
                    $sentCt = true;
                }
                header(trim($hk) . ': ' . trim($hv), false);
            }
        }
        if ($jsonRewritten) {
            header('Content-Type: ' . ($upstreamCt !== '' ? $upstreamCt : 'application/json; charset=utf-8'));
            $sentCt = true;
        }
        if (!$sentCt) {
            header('Content-Type: application/octet-stream');
        }
        echo $respBody;
        exit;
    }

    /**
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
     * @param string $slug
     * @return string
     */
    public static function publicPath($slug)
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return '';
        }
        return self::PUBLIC_PREFIX . '/' . $slug;
    }

    /**
     * @param string $slug
     * @return string
     */
    public static function publicUrl($slug)
    {
        $path = self::publicPath($slug);
        if ($path === '') {
            return '';
        }
        return vs_site_path($path);
    }

    /**
     * @param string $slug
     * @return string
     */
    public static function normalizeSlug($slug)
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return '';
        }
        if (!preg_match('/^[a-z0-9]{3,64}$/', $slug)) {
            return '';
        }
        return $slug;
    }

    /**
     * @param int $len
     * @return string
     */
    public static function generateUniqueSlug($len = 6)
    {
        $len = max(4, min(16, (int) $len));
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $slug = '';
            for ($i = 0; $i < $len; $i++) {
                $slug .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            if (self::slugExists($slug)) {
                continue;
            }
            return $slug;
        }
        return substr(md5(uniqid((string) mt_rand(), true)), 0, $len);
    }

    /**
     * @param string   $slug
     * @param int|null $excludeId
     * @return bool
     */
    public static function slugExists($slug, $excludeId = null)
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '' || !ApiManager::hasProxyColumns()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            if ($excludeId !== null && (int) $excludeId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . ApiManager::table() . '`
                     WHERE `proxyslug` = ? AND `id` <> ? LIMIT 1'
                );
                $stmt->execute(array($slug, (int) $excludeId));
            } else {
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . ApiManager::table() . '` WHERE `proxyslug` = ? LIMIT 1'
                );
                $stmt->execute(array($slug));
            }
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 平台密钥字段名（转发上游前须剥离，避免本站 sk 泄露给第三方）
     *
     * @param string $name
     * @return bool
     */
    public static function isPlatformKeyFieldName($name)
    {
        $n = strtolower(trim((string) $name));
        return ($n === 'key' || $n === 'api_key' || $n === 'apikey');
    }

    /**
     * @param array $data
     * @return array
     */
    public static function stripPlatformKeyFieldsFromArray(array $data)
    {
        $out = array();
        foreach ($data as $k => $v) {
            if (self::isPlatformKeyFieldName((string) $k)) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * 从请求体剥离平台密钥字段（JSON / x-www-form-urlencoded / multipart 字段）
     *
     * @param string $body
     * @param string $contentType
     * @return string
     */
    public static function stripPlatformKeyFieldsFromBody($body, $contentType = '')
    {
        $body = (string) $body;
        if ($body === '') {
            return '';
        }
        $ct = strtolower(trim(explode(';', (string) $contentType, 2)[0]));

        // JSON 对象
        $trim0 = isset($body[0]) ? $body[0] : '';
        if ($ct === 'application/json' || $ct === 'text/json' || $trim0 === '{' || $trim0 === '[') {
            $data = json_decode($body, true);
            if (is_array($data)) {
                // 仅处理对象（关联数组）；列表原样
                $isList = array_keys($data) === range(0, count($data) - 1);
                if (!$isList) {
                    $data = self::stripPlatformKeyFieldsFromArray($data);
                    $enc = json_encode($data, JSON_UNESCAPED_UNICODE);
                    return is_string($enc) ? $enc : $body;
                }
            }
            return $body;
        }

        // 表单
        if ($ct === 'application/x-www-form-urlencoded' || $ct === '') {
            $parsed = array();
            parse_str($body, $parsed);
            if (is_array($parsed) && $parsed !== array()) {
                return http_build_query(self::stripPlatformKeyFieldsFromArray($parsed));
            }
            return $body;
        }

        // multipart：去掉 name="key|api_key|apikey" 的 part
        if (strpos($ct, 'multipart/') === 0) {
            if (!preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/i', (string) $contentType, $bm)) {
                return $body;
            }
            $boundary = isset($bm[1]) && $bm[1] !== '' ? $bm[1] : $bm[2];
            $boundary = trim($boundary);
            if ($boundary === '') {
                return $body;
            }
            $delim = '--' . $boundary;
            $parts = explode($delim, $body);
            $kept = array();
            foreach ($parts as $part) {
                if ($part === '' || $part === '--' || $part === "--\r\n" || $part === "--\n") {
                    $kept[] = $part;
                    continue;
                }
                if (preg_match('/Content-Disposition:\s*[^\r\n]*\bname=(["\']?)(key|api_key|apikey)\1/i', $part)) {
                    continue;
                }
                $kept[] = $part;
            }
            return implode($delim, $kept);
        }

        return $body;
    }

    /**
     * @param string $url
     * @param array  $params
     * @return string
     */
    public static function mergeQuery($url, array $params)
    {
        if (count($params) === 0) {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }
        $existing = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $existing);
        }
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $existing[$k] = $v;
        }
        $query = http_build_query($existing);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $scheme . $auth . $host . $port . $path . ($query !== '' ? '?' . $query : '') . $frag;
    }
}
