<?php
/**
 * 文件：core/AiApiDoc.php
 * 作用：根据接口资料生成详细文档（Markdown）与快速上手代码示例（:::qs 短码）
 *
 * 安全：禁止在提示与输出中暴露代理上游 URL、上游密钥、内部实现细节。
 */

class AiApiDoc
{
    /**
     * @param array $api 表单字段（不含 upkey / targeturl 等敏感上游信息）
     * @return string|array 成功 array{doc:string}；失败错误文案
     */
    public static function generateDetailDoc(array $api)
    {
        $safe = self::safeContext($api);
        $cfg = AiConfig::get();
        $maxLen = (int) $cfg['doc_maxlen'];

        $system = '你是 API 文档撰写助手。只输出 Markdown 正文，不要寒暄，不要用 ```markdown 包裹全文。'
            . '必须使用 Markdown：标题、列表、表格、代码块。'
            . '篇幅控制在约 ' . $maxLen . ' 字以内，结构清晰、面向调用方。'
            . '严禁输出任何 HTML 标签、CSS class、语法高亮标记（如 vs-syn、span class）。'
            . '严禁提及：代理、上游、中继、源站地址、上游密钥、Authorization 上游头、内部表名、枚举数字含义、后台路径。'
            . '只能描述本站对外提供的调用地址、参数与行为。'
            . '必须包含：接口说明、调用地址、请求方式、请求参数表、参数说明、'
            . '成功响应示例、错误响应示例、响应字段说明，以及本平台业务错误码（errcode，不是 HTTP 状态码）：'
            . '11001 未提供密钥、11002 密钥错误、11003 密钥已禁用、11004 积分不足、'
            . '11005 请求过于频繁、11006 维护中、11007 接口已禁用、11012 鉴权方式错误。'
            . '错误响应 JSON 示例必须为 {"code":0,"msg":"…","errcode":11001} 形态，禁止写 "http":401 或 "全部鉴权方式"。'
            . '鉴权方式只写本接口实际支持的那几种（Query / Header / Bearer），禁止写「全部支持」「支持全部鉴权方式」。'
            . '若接口有多种参数组合，用表格说明典型取值。'
            . 'PHP 示例禁止输出 <?php 与 ?> 标签；用注释标明语言即可。';

        $user = "请根据下列接口资料撰写详细文档（Markdown）：\n\n" . self::contextMarkdown($safe);
        $cfg['timeout'] = max((int) $cfg['timeout'], 120);
        if ($cfg['timeout'] > 300) {
            $cfg['timeout'] = 300;
        }
        $out = AiClient::chatWithConfig($cfg, $system, $user, array(
            'temperature' => 0.3,
            'max_tokens'  => 8000,
        ));
        if (strpos($out, '错误：') === 0) {
            return $out;
        }
        $out = self::sanitizeOutput($out);
        if (function_exists('mb_strlen') && mb_strlen($out, 'UTF-8') > $maxLen + 500) {
            $out = function_exists('mb_substr')
                ? mb_substr($out, 0, $maxLen, 'UTF-8')
                : substr($out, 0, $maxLen);
            $out .= "\n\n…（已按长度限制截断）";
        }
        return array('doc' => $out);
    }

    /**
     * 按鉴权方式分片请求模型，避免一次生成 9×3 块导致超时无数据
     *
     * @param array $api
     * @return string|array 成功 array{aidoc:string}；失败错误文案
     */
    public static function generateCodeSamples(array $api)
    {
        $safe = self::safeContext($api);
        $needKey = (int) (isset($safe['needkey']) ? $safe['needkey'] : 0);
        $keyways = isset($safe['keyways']) && is_array($safe['keyways']) ? $safe['keyways'] : array('query');
        if ($needKey === 0) {
            $keyways = array('query');
        } elseif ($keyways === array()) {
            $keyways = array('query');
        }

        $chunks = array();
        $lastErr = '';
        $failedWays = array();
        foreach ($keyways as $way) {
            $way = strtolower(trim((string) $way));
            if ($way === '') {
                continue;
            }
            $part = self::generateCodeSamplesForAuth($safe, $way, $needKey !== 0);
            if (is_string($part) && strpos($part, '错误：') === 0) {
                $lastErr = $part;
                $failedWays[] = $way;
                continue;
            }
            if (is_string($part) && $part !== '') {
                $chunks[] = $part;
            } else {
                $failedWays[] = $way;
            }
        }

        if ($chunks === array()) {
            return $lastErr !== '' ? $lastErr : '错误：未能解析出有效的语言代码块，请重试或加大 AI 超时秒数';
        }

        $merged = ApiQuickstart::normalizeAidocBlocks(implode("\n\n", $chunks));
        if ($merged === '') {
            return '错误：未能解析出有效的语言代码块，请重试';
        }
        $result = array('aidoc' => $merged);
        if ($failedWays !== array()) {
            $result['warning'] = '部分鉴权方式未生成成功：' . implode('、', $failedWays) . '。请加大超时后重试，或单独再生成。';
        }
        return $result;
    }

    /**
     * 为单一鉴权方式生成 9 种语言示例
     *
     * @param array  $safe
     * @param string $authWay query|header|bearer
     * @param bool   $requireAuthAttr
     * @return string 规范化后的 :::qs 文本，或以「错误：」开头
     */
    private static function generateCodeSamplesForAuth(array $safe, $authWay, $requireAuthAttr)
    {
        $langs = array('curl', 'typescript', 'browser', 'python', 'go', 'java', 'php', 'cpp', 'rust');
        $langList = implode('、', $langs);
        $authWay = strtolower((string) $authWay);
        if (!in_array($authWay, array('query', 'header', 'bearer'), true)) {
            $authWay = 'query';
        }

        if ($authWay === 'query') {
            $authHow = 'Query 参数 key=YOUR_API_KEY';
        } elseif ($authWay === 'header') {
            $authHow = '请求头 X-API-Key: YOUR_API_KEY';
        } else {
            $authHow = 'Authorization: Bearer YOUR_API_KEY';
        }

        $authLine = $requireAuthAttr
            ? ('本批只生成鉴权方式 auth=' . $authWay . '（' . $authHow . '）。'
                . '每个块必须带 auth=' . $authWay . '，格式：:::qs lang=curl auth=' . $authWay . "\n代码\n:::")
            : ('本接口无需密钥。格式：:::qs lang=curl' . "\n代码\n:::（可不写 auth）");

        $system = '你是 API 多语言调用示例生成器。只输出指定短码，不要解释、不要 Markdown 标题。'
            . '必须为下列每一种语言各输出恰好一个块（缺一不可）：' . $langList . '。'
            . $authLine
            . '代码必须可运行示意：使用对外调用地址与给定参数名；密钥用 YOUR_API_KEY 占位。'
            . '每种示例须含基本错误处理：检查响应 JSON 的 code/errcode，或非成功响应；'
            . 'Python/Go/Java 等用 try/except 或 if resp.status_code；TypeScript/Browser 用 if (!response.ok)。'
            . '严禁出现 HTML/CSS class/高亮标记、上游真实地址、代理、密钥明文、内部实现、「全部支持」。'
            . 'GET 用查询参数；POST 可用 form 或 JSON。'
            . 'browser 使用浏览器 fetch；typescript 使用 async/await fetch。'
            . 'PHP 块禁止输出 <?php 与 ?>；从变量赋值起写即可。';

        $user = "请为下列接口生成「仅 " . $authWay . " 鉴权」的快速上手代码：\n\n" . self::contextMarkdown($safe);
        $cfg = AiConfig::get();
        $cfg['timeout'] = max((int) $cfg['timeout'], 120);
        if ($cfg['timeout'] > 300) {
            $cfg['timeout'] = 300;
        }
        $out = AiClient::chatWithConfig($cfg, $system, $user, array(
            'temperature' => 0.2,
            'max_tokens'  => 8000,
        ));
        if (strpos($out, '错误：') === 0) {
            return $out;
        }
        $out = self::sanitizeOutput($out);
        $normalized = ApiQuickstart::normalizeAidocBlocks($out);
        if ($normalized === '') {
            return '错误：鉴权 ' . $authWay . ' 未能解析出有效代码块';
        }
        return $normalized;
    }

    /**
     * 去掉敏感字段，只保留可对 AI / 用户文档暴露的资料
     *
     * @param array $api
     * @return array
     */
    public static function safeContext(array $api)
    {
        $apitype = isset($api['apitype']) ? (int) $api['apitype'] : 0;
        $endpoint = trim((string) (isset($api['endpoint']) ? $api['endpoint'] : ''));
        // 代理接口对外地址用本站解析后的调用地址；绝不传 targeturl
        if ($apitype === 1 && class_exists('ApiManager') && !empty($api['id'])) {
            $row = array(
                'id'        => (int) $api['id'],
                'apitype'   => 1,
                'endpoint'  => $endpoint,
                'proxyslug' => isset($api['proxyslug']) ? $api['proxyslug'] : '',
            );
            $resolved = ApiManager::resolveCallUrl($row);
            if ($resolved !== '') {
                $endpoint = $resolved;
            }
        } elseif ($apitype === 1 && $endpoint === '' && !empty($api['callurl'])) {
            $endpoint = trim((string) $api['callurl']);
        }

        $keyways = ApiManager::normalizeKeyways(
            isset($api['keyways']) ? $api['keyways'] : ApiManager::KEYWAY_QUERY
        );

        return array(
            'name'        => trim((string) (isset($api['name']) ? $api['name'] : '')),
            'description' => trim((string) (isset($api['description']) ? $api['description'] : '')),
            'endpoint'    => $endpoint,
            'method'      => isset($api['method']) ? $api['method'] : 'GET',
            'params'      => isset($api['params']) ? (string) $api['params'] : '',
            'response'    => isset($api['response']) ? (string) $api['response'] : '',
            'needkey'     => isset($api['needkey']) ? (int) $api['needkey'] : 0,
            'keyways'     => $keyways,
            'charge'      => isset($api['charge']) ? (int) $api['charge'] : 0,
            'price'       => isset($api['price']) ? $api['price'] : 0,
            'qpm'         => isset($api['qpm']) ? (int) $api['qpm'] : 0,
            'category'    => isset($api['category']) ? (string) $api['category'] : '',
        );
    }

    /**
     * @param array $safe
     * @return string
     */
    private static function contextMarkdown(array $safe)
    {
        $need = (int) $safe['needkey'];
        $needLabel = '无需密钥';
        if ($need === 1) {
            $needLabel = '必须密钥';
        } elseif ($need === 2) {
            $needLabel = '可选密钥';
        }
        $charge = (int) $safe['charge'] === 1 ? '收费' : '免费';
        $keywaysLabel = ApiManager::keywaysLabel(isset($safe['keyways']) ? $safe['keyways'] : 'query');
        $lines = array(
            '- 名称：' . $safe['name'],
            '- 描述：' . $safe['description'],
            '- 调用地址：' . $safe['endpoint'],
            '- 请求方式：' . (is_array($safe['method']) ? implode(',', $safe['method']) : (string) $safe['method']),
            '- 密钥要求：' . $needLabel,
            '- 鉴权方式：' . ($need === 0 ? '无需密钥' : $keywaysLabel),
            '- 计费：' . $charge,
            '- 分类：' . $safe['category'],
            '- 请求参数 JSON：' . ($safe['params'] !== '' ? $safe['params'] : '[]'),
            '- 返回示例：' . ($safe['response'] !== '' ? $safe['response'] : '(无)'),
        );
        if ((int) $safe['qpm'] > 0) {
            $lines[] = '- 频率限制：每分钟约 ' . (int) $safe['qpm'] . ' 次';
        }
        return implode("\n", $lines);
    }

    /**
     * @param string $text
     * @return string
     */
    private static function sanitizeOutput($text)
    {
        $text = (string) $text;
        // 先清高亮泄漏碎片（可不含完整 HTML 标签）
        $text = preg_replace('/-?syn\s+vs-syn--[\w-]*"\s*>?/i', '', $text);
        $text = preg_replace('/\bvs-syn--[\w-]+/i', '', $text);
        if (strpos($text, '<') !== false) {
            $text = preg_replace('/<span[^>]*class\s*=\s*["\'][^"\']*vs-syn[^"\']*["\'][^>]*>(.*?)<\/span>/is', '$1', $text);
            // 仅去掉明显 HTML 标签，保留 Markdown 内可能出现的比较符语境由模型避免
            $text = preg_replace('/<\/?[a-zA-Z][^>]*>/', '', $text);
        }
        $text = preg_replace('/\sclass\s*=\s*["\'][^"\']*vs-syn[^"\']*["\']/i', '', $text);
        $text = preg_replace('/\sdata-vs-syn(?:-done)?\s*=\s*["\'][^"\']*["\']/i', '', $text);
        // 粗过滤：常见泄密词
        $banned = array(
            'targeturl', 'upkey', 'upauth', '上游', '源站', '中继转发', '代理外链真实地址',
        );
        foreach ($banned as $w) {
            if ($w !== '' && stripos($text, $w) !== false) {
                $text = str_ireplace($w, '***', $text);
            }
        }
        return trim($text);
    }
}
