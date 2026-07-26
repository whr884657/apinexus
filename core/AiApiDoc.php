<?php
/**
 * 文件：core/AiApiDoc.php
 * 作用：根据接口资料生成详细文档（Markdown）与快速上手代码示例（:::qs 短码）
 *
 * 代码示例（v12.0.0）：前端按「鉴权×语言」分片调用 generateCodeSamplePiece；
 * 调度模式（单线程/并行）见 AiConfig::codeMode / codeConcurrency。
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
     * 生成单片代码示例（供前端分片 / 并行调度；一次只写一个鉴权×一种语言）
     *
     * @param array  $api
     * @param string $authWay query|header|bearer
     * @param string $lang
     * @return string|array 成功 array{piece:string,auth:string,lang:string}；失败错误文案
     */
    public static function generateCodeSamplePiece(array $api, $authWay, $lang)
    {
        $safe = self::safeContext($api);
        $needKey = (int) (isset($safe['needkey']) ? $safe['needkey'] : 0);
        $keyways = isset($safe['keyways']) && is_array($safe['keyways']) ? $safe['keyways'] : array('query');
        if ($needKey === 0) {
            $keyways = array('query');
        } elseif ($keyways === array()) {
            $keyways = array('query');
        }

        $authWay = strtolower(trim((string) $authWay));
        $lang = strtolower(trim((string) $lang));
        $langs = array('curl', 'typescript', 'browser', 'python', 'go', 'java', 'php', 'cpp', 'rust');
        if (!in_array($lang, $langs, true)) {
            return '错误：不支持的语言';
        }
        if ($needKey === 0) {
            $authWay = 'query';
        } elseif (!in_array($authWay, array('query', 'header', 'bearer'), true)) {
            return '错误：不支持的鉴权方式';
        } elseif (!in_array($authWay, $keyways, true)) {
            return '错误：本接口未启用该鉴权方式';
        }

        $part = self::generateOneCodeSample($safe, $authWay, $lang, $needKey !== 0);
        if (is_string($part) && strpos($part, '错误：') === 0) {
            return $part;
        }
        if (!is_string($part) || $part === '') {
            return '错误：未能解析出有效代码块';
        }
        return array(
            'piece' => $part,
            'auth'  => $authWay,
            'lang'  => $lang,
        );
    }

    /**
     * @deprecated v12.0.0 起由前端分片调用 generateCodeSamplePiece；保留仅兼容旧客户端
     *
     * @param array $api
     * @return string|array
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
        $langs = array('curl', 'typescript', 'browser', 'python', 'go', 'java', 'php', 'cpp', 'rust');
        $chunks = array();
        $lastErr = '';
        $failed = array();
        foreach ($keyways as $way) {
            foreach ($langs as $lang) {
                $r = self::generateCodeSamplePiece($api, $way, $lang);
                if (is_string($r) && strpos($r, '错误：') === 0) {
                    $lastErr = $r;
                    $failed[] = $way . '/' . $lang;
                    continue;
                }
                if (is_array($r) && !empty($r['piece'])) {
                    $chunks[] = $r['piece'];
                } else {
                    $failed[] = $way . '/' . $lang;
                }
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
        if ($failed !== array()) {
            $result['warning'] = '部分示例未生成成功：' . implode('、', array_slice($failed, 0, 12));
        }
        return $result;
    }

    /**
     * 生成单一鉴权 + 单一语言的一个 :::qs 块
     *
     * @param array  $safe
     * @param string $authWay query|header|bearer
     * @param string $lang
     * @param bool   $requireAuthAttr
     * @return string 规范化后的 :::qs 文本，或以「错误：」开头
     */
    private static function generateOneCodeSample(array $safe, $authWay, $lang, $requireAuthAttr)
    {
        $authWay = strtolower((string) $authWay);
        if (!in_array($authWay, array('query', 'header', 'bearer'), true)) {
            $authWay = 'query';
        }
        $lang = strtolower((string) $lang);
        $allowedLangs = array('curl', 'typescript', 'browser', 'python', 'go', 'java', 'php', 'cpp', 'rust');
        if (!in_array($lang, $allowedLangs, true)) {
            return '错误：不支持的语言 ' . $lang;
        }

        if ($authWay === 'query') {
            $authHow = 'Query 参数 key=YOUR_API_KEY';
        } elseif ($authWay === 'header') {
            $authHow = '请求头 X-API-Key: YOUR_API_KEY';
        } else {
            $authHow = 'Authorization: Bearer YOUR_API_KEY';
        }

        $authLine = $requireAuthAttr
            ? ('只输出恰好一个块：:::qs lang=' . $lang . ' auth=' . $authWay . "\n代码\n:::"
                . '鉴权方式必须是 ' . $authWay . '（' . $authHow . '）。禁止输出其它语言或其它 auth。')
            : ('本接口无需密钥。只输出恰好一个块：:::qs lang=' . $lang . "\n代码\n:::（可不写 auth）");

        $langHint = '';
        if ($lang === 'browser') {
            $langHint = '使用浏览器 fetch。';
        } elseif ($lang === 'typescript') {
            $langHint = '使用 async/await fetch。';
        } elseif ($lang === 'php') {
            $langHint = '禁止输出 <?php 与 ?>；从变量赋值起写即可。lang 必须写 php（不要写其它别名）。';
        } elseif ($lang === 'cpp') {
            $langHint = '使用 libcurl 或等价示意；lang 必须写 cpp（不要写 c++）。';
        } elseif ($lang === 'python' || $lang === 'go' || $lang === 'java' || $lang === 'rust') {
            $langHint = '含 try/except 或等价错误处理；检查 code/errcode。';
        }

        $system = '你是 API 调用示例生成器。只输出一个 :::qs 短码块，不要解释、不要 Markdown 标题、不要用 ``` 包裹、不要输出其它语言。'
            . $authLine
            . '代码必须可运行示意：使用对外调用地址与给定参数名；密钥用 YOUR_API_KEY 占位。'
            . '须含基本错误处理：检查响应 JSON 的 code/errcode，或非成功响应。'
            . '关键步骤必须有简洁中文注释（// 或 # 或语言等价注释）。'
            . '严禁 emoji、颜文字、图标符号、装饰性特殊字符；代码里只能是普通文本。'
            . $langHint
            . '严禁出现 HTML/CSS class/高亮标记、上游真实地址、代理、密钥明文、内部实现、「全部支持」。'
            . 'GET 用查询参数；POST 可用 form 或 JSON。';

        $user = "请为下列接口生成「鉴权=" . $authWay . "，语言=" . $lang . "」的单个快速上手代码块：\n\n"
            . self::contextMarkdown($safe)
            . "\n\n输出格式必须严格为（不要前后多余文字）：\n"
            . ($requireAuthAttr
                ? (":::qs lang=" . $lang . " auth=" . $authWay . "\n// 中文注释…\n代码\n:::")
                : (":::qs lang=" . $lang . "\n// 中文注释…\n代码\n:::"));

        $cfg = AiConfig::get();
        $cfg['timeout'] = max((int) $cfg['timeout'], 60);
        if ($cfg['timeout'] > 300) {
            $cfg['timeout'] = 300;
        }
        // 单块请求：每片重置 PHP 执行时限，避免长串排队被杀
        @set_time_limit((int) $cfg['timeout'] + 30);

        $out = AiClient::chatWithConfig($cfg, $system, $user, array(
            'temperature' => 0.2,
            'max_tokens'  => 2200,
        ));
        if (strpos($out, '错误：') === 0) {
            return $out;
        }
        $one = self::extractRequestedQsBlock($out, $authWay, $lang, $requireAuthAttr);
        // 解析失败再试一次（更严格式提醒），降低 php/cpp 等偶发失败
        if ($one === '') {
            @set_time_limit((int) $cfg['timeout'] + 30);
            $retryUser = $user . "\n\n上次输出无法解析。请再次只输出一个合法短码块，第一行必须是 "
                . ($requireAuthAttr
                    ? (':::qs lang=' . $lang . ' auth=' . $authWay)
                    : (':::qs lang=' . $lang))
                . " ，最后一行必须是 ::: ，中间是带中文注释的纯代码，禁止 emoji 与 ```。";
            $out2 = AiClient::chatWithConfig($cfg, $system, $retryUser, array(
                'temperature' => 0.1,
                'max_tokens'  => 2200,
            ));
            if (strpos($out2, '错误：') !== 0) {
                $one = self::extractRequestedQsBlock($out2, $authWay, $lang, $requireAuthAttr);
            }
        }
        if ($one === '') {
            return '错误：鉴权 ' . $authWay . ' / 语言 ' . $lang . ' 未能解析出有效代码块';
        }
        return $one;
    }

    /**
     * 从模型输出中只取「当前鉴权 + 当前语言」一块，避免模型仍一次吐多语言被误合并
     *
     * @param string $raw
     * @param string $authWay
     * @param string $lang
     * @param bool   $requireAuthAttr
     * @return string 单个 :::qs 块或空串
     */
    private static function extractRequestedQsBlock($raw, $authWay, $lang, $requireAuthAttr)
    {
        $raw = self::sanitizeOutput((string) $raw);
        $raw = ApiQuickstart::stripEmoji($raw);
        $parsed = ApiQuickstart::parseQsBlocks($raw);
        if ($parsed === array()) {
            $normalized = ApiQuickstart::normalizeAidocBlocks($raw);
            $parsed = ApiQuickstart::parseQsBlocks($normalized);
        }
        if ($parsed === array()) {
            $parsed = ApiQuickstart::parseFenceBlocksAsQs($raw);
        }

        $authWay = strtolower((string) $authWay);
        $lang = strtolower((string) $lang);
        $code = '';
        if (isset($parsed[$authWay][$lang])) {
            $code = trim((string) $parsed[$authWay][$lang]);
        }
        // 模型常漏写 auth= 或写成其它 auth：只要语言匹配就收回并改写成目标 auth
        if ($code === '') {
            foreach ($parsed as $authKey => $langs) {
                if (is_array($langs) && isset($langs[$lang]) && trim((string) $langs[$lang]) !== '') {
                    $code = trim((string) $langs[$lang]);
                    break;
                }
            }
        }
        if ($code === '' && !$requireAuthAttr && isset($parsed[ApiQuickstart::AUTH_DEFAULT][$lang])) {
            $code = trim((string) $parsed[ApiQuickstart::AUTH_DEFAULT][$lang]);
        }
        if ($code === '') {
            return '';
        }
        $code = ApiQuickstart::stripEmoji($code);
        if ($code === '') {
            return '';
        }

        $line = ':::qs lang=' . $lang;
        if ($requireAuthAttr) {
            $line .= ' auth=' . $authWay;
        }
        return $line . "\n" . $code . "\n:::";
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
        if (class_exists('ApiQuickstart')) {
            $text = ApiQuickstart::stripEmoji($text);
        }
        return trim($text);
    }
}
