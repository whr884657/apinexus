<?php
/**
 * 文件：core/OpenApiBuilder.php
 * 作用：由 api.params（自定义参数行数组）+ 接口元数据派生 OpenAPI 3.1.1 文档
 *
 * 真相源仅为 params；本类只读生成，不入库。禁止主题/前端手写拼装 OpenAPI。
 * 禁止直接 HTTP 访问本文件（须经 bootstrap + 已鉴权入口调用）。
 */

if (!defined('VS_ROOT')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

class OpenApiBuilder
{
    /** OpenAPI Specification 版本（文档根字段 openapi） */
    const SPEC_VERSION = '3.1.1';

    /**
     * @param array $row 接口库行或预览拼装行（须含 params / method / name 等）
     * @return array
     */
    public static function documentForApiRow(array $row)
    {
        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($name === '') {
            $name = 'API';
        }
        $desc = trim((string) (isset($row['description']) ? $row['description'] : ''));
        $unavailable = !empty($row['openapi_unavailable']);

        $methods = class_exists('ApiManager')
            ? ApiManager::normalizeMethods(isset($row['method']) ? $row['method'] : 'GET')
            : array('GET');
        if ($methods === array()) {
            $methods = array('GET');
        }

        $pathInfo = self::resolvePathAndServers($row, $unavailable);
        $path = $pathInfo['path'];
        $servers = $pathInfo['servers'];

        $paramsRaw = isset($row['params']) ? (string) $row['params'] : '';
        $paramsList = class_exists('FrontendApi')
            ? FrontendApi::parseParamsList($paramsRaw)
            : self::parseParamsFallback($paramsRaw);

        $keyways = class_exists('ApiManager')
            ? ApiManager::normalizeKeyways(isset($row['keyways']) ? $row['keyways'] : 'query')
            : array('query');

        $parameters = array();
        foreach ($paramsList as $item) {
            if (!is_array($item)) {
                continue;
            }
            $param = self::paramToOpenApi($item, $keyways);
            if ($param !== null) {
                $parameters[] = $param;
            }
        }

        $infoDesc = $desc !== '' ? $desc : $name;
        if ($unavailable) {
            $infoDesc = trim($infoDesc . "\n\n（当前接口不可用或已禁用，文档仅作参数说明，paths 为占位不可调用。）");
        }

        $pathItem = array();
        if (!$unavailable) {
            foreach ($methods as $method) {
                $method = strtolower(trim((string) $method));
                if ($method === '') {
                    continue;
                }
                $op = array(
                    'summary'     => $name,
                    'description' => $desc,
                    'parameters'   => $parameters,
                    'responses'   => self::minimalOkResponse(),
                );
                if ($desc === '') {
                    unset($op['description']);
                }
                $pathItem[$method] = $op;
            }
            if ($pathItem === array()) {
                $pathItem['get'] = array(
                    'summary'    => $name,
                    'parameters'  => $parameters,
                    'responses'  => self::minimalOkResponse(),
                );
            }
        } else {
            // 禁用：保留参数结构在 components 式说明不可用；paths 仅占位 GET
            $pathItem['get'] = array(
                'summary'     => $name . '（不可用）',
                'description' => '接口已禁用或调用地址不可用，请勿请求本路径。',
                'parameters'   => $parameters,
                'responses'   => array(
                    '503' => array(
                        'description' => '不可用',
                    ),
                ),
                'deprecated'  => true,
            );
        }

        $doc = array(
            'openapi' => self::SPEC_VERSION,
            'info'    => array(
                'title'       => $name,
                'description' => $infoDesc,
                'version'     => '1.0.0',
            ),
            'paths'   => array(
                $path => $pathItem,
            ),
        );
        if ($servers !== array()) {
            $doc['servers'] = $servers;
        }
        return $doc;
    }

    /**
     * @param array $row
     * @param bool  $pretty true=缩进（编辑预览）；false=紧凑（详情 HTML 嵌入）
     * @return string
     */
    public static function jsonForApiRow(array $row, $pretty = true)
    {
        $doc = self::documentForApiRow($row);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return (string) json_encode($doc, $flags);
    }

    /**
     * 预览请求：以已有库行（可选）为底，用 POST 字段覆盖后再生成
     * 白名单字段：禁止经本入口注入 targeturl / upkey 等敏感列
     *
     * @param array      $post
     * @param array|null $baseRow
     * @return array
     */
    public static function documentFromPreviewRequest(array $post, $baseRow = null)
    {
        $row = is_array($baseRow) ? $baseRow : array();
        // 预览不得带回上游密钥/目标等字段进文档输入面
        unset(
            $row['upkey'],
            $row['targeturl'],
            $row['upua'],
            $row['upreferer'],
            $row['jsonrewrite']
        );
        $keys = array(
            'name', 'description', 'endpoint', 'method', 'params',
            'keyways', 'proxyslug', 'apitype', 'needkey',
        );
        foreach ($keys as $k) {
            if (!array_key_exists($k, $post)) {
                continue;
            }
            $val = $post[$k];
            if ($k === 'keyways' && is_array($val)) {
                $val = implode(',', $val);
            }
            if ($k === 'method' && is_array($val)) {
                $val = implode(',', $val);
            }
            $row[$k] = $val;
        }
        if (!isset($row['params'])) {
            $row['params'] = '';
        }
        return self::documentForApiRow($row);
    }

    /**
     * @param array      $post
     * @param array|null $baseRow
     * @param bool       $pretty
     * @return string
     */
    public static function jsonFromPreviewRequest(array $post, $baseRow = null, $pretty = true)
    {
        $doc = self::documentFromPreviewRequest($post, $baseRow);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return (string) json_encode($doc, $flags);
    }

    /**
     * preview AJAX 共用频控（登录态仍防持会话狂刷占 CPU）
     *
     * @return true|string true=通过；string=错误文案
     */
    public static function assertPreviewRateLimit()
    {
        if (!class_exists('AuthSecurity')) {
            return true;
        }
        $ip = AuthSecurity::clientIp();
        if ($ip === '') {
            $ip = 'unknown';
        }
        // 每 IP：60 秒内最多 30 次预览
        if (!AuthSecurity::rateLimitAllow('openapi_preview_ip:' . $ip, 60, 30, true)) {
            return '操作过于频繁，请稍后再试';
        }
        return true;
    }

    /**
     * @return array
     */
    private static function minimalOkResponse()
    {
        return array(
            '200' => array(
                'description' => '成功',
                'content'     => array(
                    'application/json' => array(
                        'schema' => array('type' => 'object'),
                    ),
                ),
            ),
        );
    }

    /**
     * @param array $row
     * @param bool  $unavailable
     * @return array{path:string,servers:array<int,array<string,string>>}
     */
    private static function resolvePathAndServers(array $row, $unavailable = false)
    {
        if ($unavailable) {
            return array(
                'path'    => '/_unavailable',
                'servers' => self::siteServers(),
            );
        }

        $call = '';
        if (class_exists('ApiManager')) {
            $call = trim((string) ApiManager::resolveCallPath($row));
        }
        if ($call === '') {
            $call = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));
        }
        if ($call === '') {
            return array(
                'path'    => '/_unavailable',
                'servers' => self::siteServers(),
            );
        }

        if (preg_match('#^https?://#i', $call)) {
            $parts = parse_url($call);
            $scheme = isset($parts['scheme']) ? (string) $parts['scheme'] : 'https';
            $host = isset($parts['host']) ? (string) $parts['host'] : '';
            $port = isset($parts['port']) ? (int) $parts['port'] : 0;
            $path = isset($parts['path']) ? (string) $parts['path'] : '/';
            if ($path === '') {
                $path = '/';
            }
            if ($host === '') {
                return array(
                    'path'    => $path,
                    'servers' => self::siteServers(),
                );
            }
            $serverUrl = $scheme . '://' . $host;
            if ($port > 0 && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
                $serverUrl .= ':' . $port;
            }
            return array(
                'path'    => $path,
                'servers' => array(array('url' => $serverUrl, 'description' => '调用地址')),
            );
        }

        if ($call[0] !== '/') {
            $call = '/' . $call;
        }
        return array(
            'path'    => $call,
            'servers' => self::siteServers(),
        );
    }

    /**
     * 动态站点入口 servers（OpenAPI 根字段）
     *
     * 只填「用户当前这次访问」的协议+Host+子目录（vs_base_url）。
     * 不追加 site_domain / site_domain1 等多条绑定域名。
     * 协议动态：https 访问 → https；http 访问 → http（非写死）。
     *
     * @return array<int,array{url:string,description?:string}>
     */
    private static function siteServers()
    {
        $servers = array();
        $seen = array();

        $scheme = self::requestScheme();
        $primary = '';
        if (function_exists('vs_base_url')) {
            $primary = rtrim((string) vs_base_url(), '/');
        }
        if ($primary !== '') {
            if (preg_match('#^(https?)://#i', $primary, $m)) {
                $scheme = strtolower($m[1]);
            }
            self::pushServer($servers, $seen, $primary, '当前访问入口（' . $scheme . '）');
            return $servers;
        }

        self::pushServer($servers, $seen, $scheme . '://localhost', '回退入口（' . $scheme . '）');
        return $servers;
    }

    /**
     * 当前请求协议：https 或 http（动态，非写死）
     *
     * @return string
     */
    private static function requestScheme()
    {
        if (class_exists('AuthSecurity')) {
            return AuthSecurity::isHttps() ? 'https' : 'http';
        }
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return 'https';
        }
        return 'http';
    }

    /**
     * @param array<int,array{url:string,description?:string}> $servers
     * @param array<string,bool> $seen
     * @param string $url
     * @param string $description
     * @return void
     */
    private static function pushServer(array &$servers, array &$seen, $url, $description)
    {
        $url = rtrim((string) $url, '/');
        if ($url === '') {
            return;
        }
        $lk = strtolower($url);
        if (isset($seen[$lk])) {
            return;
        }
        $servers[] = array(
            'url'         => $url,
            'description' => (string) $description,
        );
        $seen[$lk] = true;
    }

    /**
     * @param array{name:string,type:string,required:bool,description:string,example:string} $item
     * @param array<int,string> $keyways
     * @return array|null
     */
    private static function paramToOpenApi(array $item, array $keyways)
    {
        $name = trim((string) (isset($item['name']) ? $item['name'] : ''));
        if ($name === '') {
            return null;
        }
        $in = 'query';
        if (self::isKeyLikeName($name) && self::keywaysPreferHeader($keyways)) {
            $in = 'header';
        }

        $schemaType = self::mapSchemaType(isset($item['type']) ? (string) $item['type'] : 'string');
        $schema = array('type' => $schemaType);
        if ($schemaType === 'array') {
            $schema['items'] = array('type' => 'string');
        }
        $example = isset($item['example']) ? trim((string) $item['example']) : '';
        if ($example !== '') {
            $schema['example'] = self::castExample($example, $schemaType);
        }

        $param = array(
            'name'     => $name,
            'in'       => $in,
            'required' => !empty($item['required']),
            'schema'   => $schema,
        );
        $desc = isset($item['description']) ? trim((string) $item['description']) : '';
        if ($desc !== '') {
            $param['description'] = $desc;
        }
        return $param;
    }

    /**
     * @param string $name
     * @return bool
     */
    private static function isKeyLikeName($name)
    {
        $n = strtolower(str_replace(array('-', ' '), '_', trim((string) $name)));
        $n = preg_replace('/_+/', '_', $n);
        return in_array($n, array('key', 'api_key', 'apikey', 'token', 'access_token', 'x_api_key'), true);
    }

    /**
     * @param array<int,string> $keyways
     * @return bool
     */
    private static function keywaysPreferHeader(array $keyways)
    {
        $hasQuery = in_array('query', $keyways, true);
        if ($hasQuery) {
            return false;
        }
        return in_array('header', $keyways, true) || in_array('bearer', $keyways, true);
    }

    /**
     * @param string $type
     * @return string
     */
    private static function mapSchemaType($type)
    {
        $t = strtolower(trim((string) $type));
        if ($t === '') {
            return 'string';
        }
        $map = array(
            'string'    => 'string',
            'text'      => 'string',
            'char'      => 'string',
            'email'     => 'string',
            'url'       => 'string',
            'phone'     => 'string',
            'password'  => 'string',
            'uuid'      => 'string',
            'enum'      => 'string',
            'datetime'  => 'string',
            'ip'        => 'string',
            'file'      => 'string',
            'blob'      => 'string',
            'integer'   => 'integer',
            'int'       => 'integer',
            'long'      => 'integer',
            'short'     => 'integer',
            'byte'      => 'integer',
            'timestamp' => 'integer',
            'number'    => 'number',
            'float'     => 'number',
            'double'    => 'number',
            'boolean'   => 'boolean',
            'bool'      => 'boolean',
            'array'     => 'array',
            'list'      => 'array',
            'boolean[]' => 'array',
            'object'    => 'object',
            'json'      => 'object',
            'obj'       => 'object',
        );
        return isset($map[$t]) ? $map[$t] : 'string';
    }

    /**
     * @param string $example
     * @param string $schemaType
     * @return mixed
     */
    private static function castExample($example, $schemaType)
    {
        if ($schemaType === 'integer') {
            if (is_numeric($example)) {
                return (int) $example;
            }
            return $example;
        }
        if ($schemaType === 'number') {
            if (is_numeric($example)) {
                return (float) $example;
            }
            return $example;
        }
        if ($schemaType === 'boolean') {
            $l = strtolower($example);
            if ($l === 'true' || $l === '1' || $l === 'yes') {
                return true;
            }
            if ($l === 'false' || $l === '0' || $l === 'no') {
                return false;
            }
            return $example;
        }
        return $example;
    }

    /**
     * @param string $raw
     * @return array<int,array<string,mixed>>
     */
    private static function parseParamsFallback($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }
        $out = array();
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = '';
            if (isset($item['name'])) {
                $name = trim((string) $item['name']);
            } elseif (isset($item['key'])) {
                $name = trim((string) $item['key']);
            }
            if ($name === '') {
                continue;
            }
            $out[] = array(
                'name'        => $name,
                'type'        => isset($item['type']) ? trim((string) $item['type']) : 'string',
                'required'    => !empty($item['required']),
                'description' => isset($item['description'])
                    ? trim((string) $item['description'])
                    : (isset($item['desc']) ? trim((string) $item['desc']) : ''),
                'example'     => isset($item['example']) ? trim((string) $item['example']) : '',
            );
        }
        return $out;
    }
}
