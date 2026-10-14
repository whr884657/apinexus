<?php
/**
 * 文件：core/SystemApiCatalog.php
 * 作用：系统级外部 API 文档目录（后台「系统 API」页只读展示；非用户网关接口）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 * 目录数据：assets/js/sysapi-catalog/{id}.json + index.json（PHP 文件系统加载；禁止 HTTP 直链）
 * 请求示例：sample_queries 列出全部变体；响应示例：response_samples 列出成败变体（规范 8.1）
 * 失败码：公共 errcode.json（12xxx），各接口「失败码」板块共用（E400）
 */

if (!defined('VS_ROOT')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

class SystemApiCatalog
{
    /**
     * Catalog JSON 目录（仅 PHP 可读；Web 须 deny）
     *
     * @return string
     */
    public static function catalogDir()
    {
        return VS_ROOT . '/assets/js/sysapi-catalog';
    }

    /**
     * 公共失败码（errcode.json · 12xxx）；各接口文档页共用
     *
     * @return array{intro:string,codes:array<int,array{code:int,msg:string}>}
     */
    public static function sharedErrcodes()
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }
        $path = rtrim(self::catalogDir(), '/\\') . DIRECTORY_SEPARATOR . 'errcode.json';
        $intro = '系统级外部 API 失败时一般为 HTTP 200，JSON 含 code=0、msg、errcode（12xxx）。公开接口用 11xxx，两套不要混用。';
        $codes = array();
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                if (isset($decoded['intro']) && trim((string) $decoded['intro']) !== '') {
                    $intro = trim((string) $decoded['intro']);
                }
                if (isset($decoded['codes']) && is_array($decoded['codes'])) {
                    foreach ($decoded['codes'] as $row) {
                        if (!is_array($row) || !isset($row['code'])) {
                            continue;
                        }
                        $code = (int) $row['code'];
                        if ($code < 12001 || $code > 12999) {
                            continue;
                        }
                        $msg = isset($row['msg']) ? trim((string) $row['msg']) : '';
                        if ($msg === '' && class_exists('SystemApiError')) {
                            $msg = SystemApiError::label($code);
                        }
                        if ($msg === '') {
                            continue;
                        }
                        $codes[] = array('code' => $code, 'msg' => $msg);
                    }
                }
            }
        }
        if ($codes === array() && class_exists('SystemApiError')) {
            foreach (SystemApiError::businessLabelMap() as $code => $msg) {
                $codes[] = array('code' => (int) $code, 'msg' => (string) $msg);
            }
        }
        $cached = array('intro' => $intro, 'codes' => $codes);
        return $cached;
    }

    /**
     * 文档说明里禁止再堆失败码段（改走独立「失败码」板块）
     *
     * @param string $md
     * @return string
     */
    private static function stripDocErrcodeSection($md)
    {
        $md = str_replace(array("\r\n", "\r"), "\n", (string) $md);
        $md = preg_replace('/\n##\s*失败码[^\n]*\n[\s\S]*\z/u', "\n", $md);
        return rtrim((string) $md) . "\n";
    }

    /**
     * @return array<int,array>
     */
    public static function catalog()
    {
        $dir = self::catalogDir();
        $ids = self::loadIndexIds($dir);
        $out = array();
        foreach ($ids as $id) {
            $item = self::loadItemFile($dir, $id);
            if ($item !== null) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * 供文档页渲染的上下文列表
     *
     * @return array<int,array>
     */
    public static function docsContexts()
    {
        $out = array();
        foreach (self::catalog() as $item) {
            if (!is_array($item)) {
                continue;
            }
            $ctx = self::buildContext($item);
            if ($ctx !== null) {
                $out[] = $ctx;
            }
        }
        return $out;
    }

    /**
     * @param string $id
     * @return string
     */
    public static function endpointUrl($id)
    {
        $id = strtolower(trim((string) $id));
        $base = rtrim(function_exists('vs_base_url') ? vs_base_url() : '', '/');
        // 对外正式地址去 .php（伪静态 try_files）；物理文件仍为 core/api/{短名}.php
        $slug = preg_replace('/[^a-z0-9]/', '', $id);
        if ($slug === '') {
            return $base . '/core/api';
        }
        return $base . '/core/api/' . $slug;
    }

    /**
     * @param string $dir
     * @return array<int,string>
     */
    private static function loadIndexIds($dir)
    {
        $indexPath = rtrim((string) $dir, '/\\') . DIRECTORY_SEPARATOR . 'index.json';
        if (is_file($indexPath)) {
            $raw = @file_get_contents($indexPath);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $ids = array();
                foreach ($decoded as $id) {
                    $slug = preg_replace('/[^a-z0-9]/', '', strtolower((string) $id));
                    if ($slug !== '') {
                        $ids[] = $slug;
                    }
                }
                if ($ids !== array()) {
                    return $ids;
                }
            }
        }
        // 无 index 时扫描目录
        $ids = array();
        if (is_dir($dir)) {
            foreach (scandir($dir) as $name) {
                if (!is_string($name) || substr($name, -5) !== '.json'
                    || $name === 'index.json' || $name === 'errcode.json') {
                    continue;
                }
                $slug = preg_replace('/[^a-z0-9]/', '', strtolower(substr($name, 0, -5)));
                if ($slug !== '') {
                    $ids[] = $slug;
                }
            }
            sort($ids);
        }
        return $ids;
    }

    /**
     * @param string $dir
     * @param string $id
     * @return array|null
     */
    private static function loadItemFile($dir, $id)
    {
        $id = preg_replace('/[^a-z0-9]/', '', strtolower((string) $id));
        if ($id === '') {
            return null;
        }
        $path = rtrim((string) $dir, '/\\') . DIRECTORY_SEPARATOR . $id . '.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $item = json_decode($raw, true);
        if (!is_array($item)) {
            return null;
        }
        $item['id'] = $id;
        if (!isset($item['name']) || trim((string) $item['name']) === '') {
            return null;
        }

        // 缺 key 参数时补系统密钥行
        $params = isset($item['params']) && is_array($item['params']) ? $item['params'] : array();
        $hasKey = false;
        foreach ($params as $p) {
            if (is_array($p) && isset($p['name']) && strtolower((string) $p['name']) === 'key') {
                $hasKey = true;
                break;
            }
        }
        if (!$hasKey) {
            array_unshift($params, self::paramSystemKey());
        }
        $item['params'] = $params;

        // response_samples 缺省时用 response 占位
        if (empty($item['response_samples']) || !is_array($item['response_samples'])) {
            if (isset($item['response'])) {
                $item['response_samples'] = array(
                    array(
                        'title' => '成功',
                        'http'  => 200,
                        'body'  => $item['response'],
                    ),
                );
            } else {
                $item['response_samples'] = array();
            }
        }

        return $item;
    }

    /**
     * 系统密钥参数（一律置顶、标必填；三态：Bearer / Header / Query；亦可用 POST/JSON）
     *
     * @return array
     */
    private static function paramSystemKey()
    {
        return array(
            'name'        => 'key',
            'type'        => 'string',
            'required'    => true,
            'description' => '系统密钥。三态传钥：① Authorization: Bearer；② Header X-Api-Key / Api-Key / Apikey；③ Query ?key= / ?apikey=（GET 友好；易进日志，生产优先 Header/Bearer）。亦可用 POST/JSON 的 key / apikey。在「系统设置 → 系统密钥」生成。',
            'example'     => '你的系统密钥',
        );
    }

    /**
     * 规范化 sample_queries：每项须含 title + query（query 可为空串，表示无业务参数）
     *
     * @param array $rows
     * @return array<int,array{title:string,query:string}>
     */
    private static function normalizeSampleQueries(array $rows)
    {
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '') {
                continue;
            }
            $query = isset($row['query']) ? ltrim((string) $row['query'], '?&') : '';
            $out[] = array(
                'title' => $title,
                'query' => $query,
            );
        }
        return $out;
    }

    /**
     * 规范化 response_samples：title + http + body（或 raw 字符串）
     *
     * @param array $rows
     * @return array<int,array{title:string,http:int,pretty:string}>
     */
    private static function normalizeResponseSamples(array $rows)
    {
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '') {
                $title = '响应';
            }
            $http = isset($row['http']) ? (int) $row['http'] : 200;
            if (isset($row['body'])) {
                $body = $row['body'];
                if (is_array($body)) {
                    $pretty = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                } else {
                    $pretty = self::prettyJson((string) $body);
                }
            } elseif (isset($row['pretty'])) {
                $pretty = self::prettyJson((string) $row['pretty']);
            } else {
                continue;
            }
            if ($pretty === '') {
                continue;
            }
            $out[] = array(
                'title'  => $title,
                'http'   => $http,
                'pretty' => $pretty,
            );
        }
        return $out;
    }

    /**
     * @param array $item
     * @return array|null
     */
    private static function buildContext(array $item)
    {
        $id = isset($item['id']) ? (string) $item['id'] : '';
        $name = isset($item['name']) ? trim((string) $item['name']) : '';
        if ($id === '' || $name === '') {
            return null;
        }

        $endpoint = self::endpointUrl($id);
        $paramsList = isset($item['params']) && is_array($item['params']) ? $item['params'] : array();
        $paramsJson = (string) json_encode($paramsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $responseSamples = array();
        if (isset($item['response_samples']) && is_array($item['response_samples'])) {
            $responseSamples = self::normalizeResponseSamples($item['response_samples']);
        }
        if ($responseSamples === array() && isset($item['response'])) {
            $responseSamples = self::normalizeResponseSamples(array(
                array('title' => '成功', 'http' => 200, 'body' => $item['response']),
            ));
        }

        $responsePretty = '';
        if ($responseSamples !== array()) {
            $responsePretty = (string) $responseSamples[0]['pretty'];
        } elseif (isset($item['response'])) {
            $responseRaw = $item['response'];
            if (is_array($responseRaw)) {
                $responsePretty = (string) json_encode($responseRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } else {
                $responsePretty = self::prettyJson((string) $responseRaw);
            }
        }

        $methods = isset($item['methods']) && is_array($item['methods']) ? $item['methods'] : array('GET', 'POST');

        $samples = array();
        if (isset($item['sample_queries']) && is_array($item['sample_queries'])) {
            $samples = self::normalizeSampleQueries($item['sample_queries']);
        } elseif (isset($item['sample_query']) && (string) $item['sample_query'] !== '') {
            $samples = self::normalizeSampleQueries(array(
                array('title' => '请求示例', 'query' => (string) $item['sample_query']),
            ));
        }
        if ($samples === array()) {
            $samples = self::normalizeSampleQueries(array(
                array('title' => '请求示例', 'query' => ''),
            ));
        }

        $qsBundle = self::buildAuthExamples($endpoint, $samples);
        $sampleQuery = isset($samples[0]['query']) ? (string) $samples[0]['query'] : '';

        $openapiRow = array(
            'name'        => $name,
            'description' => isset($item['desc']) ? (string) $item['desc'] : '',
            'endpoint'    => $endpoint,
            'method'      => implode(',', $methods),
            'params'      => $paramsJson,
            'keyways'     => 'bearer,header,query',
        );
        $openapiJson = '';
        if (class_exists('OpenApiBuilder')) {
            $openapiJson = OpenApiBuilder::jsonForApiRow($openapiRow, true);
        }

        $cat = isset($item['category']) ? trim((string) $item['category']) : '系统级';
        if ($cat === '') {
            $cat = '系统级';
        }
        $desc = isset($item['desc']) ? trim((string) $item['desc']) : '';
        $docRaw = self::stripDocErrcodeSection(isset($item['doc']) ? (string) $item['doc'] : '');
        $docRaw = trim($docRaw);
        $docHtml = '';
        if ($docRaw !== '') {
            if (class_exists('Markdown')) {
                $docHtml = Markdown::render($docRaw);
            } else {
                $docHtml = '<pre class="doc-md-fallback">' . htmlspecialchars($docRaw, ENT_QUOTES, 'UTF-8') . '</pre>';
            }
        }
        $errPack = self::sharedErrcodes();
        $search = mb_strtolower($name . ' ' . $desc . ' ' . $cat . ' ' . $endpoint . ' ' . $id . ' ' . $docRaw, 'UTF-8');

        return array(
            'id'               => $id,
            'name'             => $name,
            'desc'             => $desc,
            'category'         => $cat,
            'methods'          => $methods,
            'endpoint'         => $endpoint,
            'params_list'      => $paramsList,
            'params_json'      => $paramsJson,
            'openapi_json'     => $openapiJson,
            'response_pretty'  => $responsePretty,
            'response_samples' => $responseSamples,
            'doc_html'         => $docHtml,
            'doc_md'           => $docRaw,
            'errcode_intro'    => isset($errPack['intro']) ? (string) $errPack['intro'] : '',
            'errcode_list'     => isset($errPack['codes']) && is_array($errPack['codes']) ? $errPack['codes'] : array(),
            'qs_bundle'        => $qsBundle,
            'sample_query'     => $sampleQuery,
            'sample_queries'   => $samples,
            'search'           => $search,
        );
    }

    /**
     * 业务 Query 按键值做 RFC3986 编码。
     * 示例 query 须用 ASCII 占位（如 EXAMPLE_CODE），避免中文百分号编码在文档里像乱码（E400）。
     *
     * @param string $bizQ
     * @return string
     */
    private static function encodeSampleQuery($bizQ)
    {
        $bizQ = ltrim((string) $bizQ, '?&');
        if ($bizQ === '') {
            return '';
        }
        $parts = array();
        foreach (explode('&', $bizQ) as $pair) {
            if ($pair === '') {
                continue;
            }
            $kv = explode('=', $pair, 2);
            $k = rawurlencode((string) $kv[0]);
            $v = isset($kv[1]) ? rawurlencode((string) $kv[1]) : '';
            $parts[] = $k . '=' . $v;
        }
        return implode('&', $parts);
    }

    /**
     * 鉴权示例（Bearer / Header / Query）× 全部业务变体（E428；与前台 keyways 三态对齐）
     *
     * @param string $endpoint
     * @param array<int,array{title:string,query:string}> $samples
     * @return array{auths:array,authLabels:array,byAuth:array}
     */
    private static function buildAuthExamples($endpoint, array $samples)
    {
        $endpoint = rtrim((string) $endpoint, '?&');
        $labels = array(
            'bearer' => 'Bearer Token',
            'header' => 'Header(X-API-Key)',
            'query'  => 'Query 参数',
        );

        $byAuth = array(
            'bearer' => array(),
            'header' => array(),
            'query'  => array(),
        );

        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $title = isset($sample['title']) ? trim((string) $sample['title']) : '请求';
            if ($title === '') {
                $title = '请求';
            }
            $bizQ = isset($sample['query']) ? ltrim((string) $sample['query'], '?&') : '';
            $bizQEnc = self::encodeSampleQuery($bizQ);
            $plainUrl = ($bizQEnc !== '') ? ($endpoint . '?' . $bizQEnc) : $endpoint;
            $queryUrl = ($bizQEnc !== '')
                ? ($endpoint . '?key=你的系统密钥&' . $bizQEnc)
                : ($endpoint . '?key=你的系统密钥');

            $byAuth['bearer'][] = array(
                'label' => $title . ' · cURL',
                'syn'   => 'bash',
                'code'  => 'curl -fsS -H "Authorization: Bearer 你的系统密钥" "' . $plainUrl . '"',
            );
            $byAuth['bearer'][] = array(
                'label' => $title . ' · JavaScript',
                'syn'   => 'javascript',
                'code'  => "const res = await fetch('" . addslashes($plainUrl) . "', {\n"
                    . "  headers: { 'Authorization': 'Bearer 你的系统密钥' }\n"
                    . "});\n"
                    . "const data = await res.json();\n"
                    . 'console.log(data);',
            );

            $byAuth['header'][] = array(
                'label' => $title . ' · cURL',
                'syn'   => 'bash',
                'code'  => 'curl -fsS -H "X-Api-Key: 你的系统密钥" "' . $plainUrl . '"',
            );
            $byAuth['header'][] = array(
                'label' => $title . ' · JavaScript',
                'syn'   => 'javascript',
                'code'  => "const res = await fetch('" . addslashes($plainUrl) . "', {\n"
                    . "  headers: { 'X-Api-Key': '你的系统密钥' }\n"
                    . "});\n"
                    . "const data = await res.json();\n"
                    . 'console.log(data);',
            );

            $byAuth['query'][] = array(
                'label' => $title . ' · cURL',
                'syn'   => 'bash',
                'code'  => 'curl -fsS "' . $queryUrl . '"',
            );
            $byAuth['query'][] = array(
                'label' => $title . ' · JavaScript',
                'syn'   => 'javascript',
                'code'  => "const res = await fetch('" . addslashes($queryUrl) . "');\n"
                    . "const data = await res.json();\n"
                    . 'console.log(data);',
            );
        }

        return array(
            'auths'      => array('bearer', 'header', 'query'),
            'authLabels' => $labels,
            'byAuth'     => $byAuth,
        );
    }

    /**
     * @param string $raw
     * @return string
     */
    private static function prettyJson($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        return $raw;
    }
}
