<?php
/**
 * 文件：core/AiClient.php
 * 作用：OpenAI 兼容客户端（Chat Completions + Responses API；模型列表；连通测试）
 *
 * 兼容常见网关：OpenAI / DeepSeek / 智谱 / LongCat / Gemini OpenAI 兼容层 / 各类 Claude·OpenAI 代理等。
 */

class AiClient
{
    /**
     * @param string $system
     * @param string $user
     * @return string 成功返回助手文本；失败返回以「错误：」开头的文案
     */
    public static function chat($system, $user)
    {
        if (!AiConfig::isReady()) {
            return '错误：请先在系统设置中启用并配置 AI';
        }
        return self::chatWithConfig(AiConfig::get(), $system, $user, array('temperature' => 0.3));
    }

    /**
     * 连通性测试：只要上游 HTTP 成功且 JSON 可解析即判定成功（不因正文为空而失败）
     *
     * @param array $cfg baseurl / apikey / model? / timeout? / api_mode?
     * @return array{ok:bool,msg:string,via?:string,reply?:string,http?:int}
     */
    public static function testConnection(array $cfg)
    {
        $base = self::normalizeBaseUrl(isset($cfg['baseurl']) ? $cfg['baseurl'] : '');
        $key = trim((string) (isset($cfg['apikey']) ? $cfg['apikey'] : ''));
        $model = trim((string) (isset($cfg['model']) ? $cfg['model'] : ''));
        $mode = self::normalizeApiMode(isset($cfg['api_mode']) ? $cfg['api_mode'] : 'auto');
        $timeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 30;
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 90) {
            $timeout = 90;
        }

        if ($base === '' || $key === '') {
            return array('ok' => false, 'msg' => '请填写接口根地址与 API Key');
        }
        if (!preg_match('#^https?://#i', $base)) {
            return array('ok' => false, 'msg' => '接口根地址须以 http:// 或 https:// 开头');
        }
        $ssrf = self::assertSafeBaseUrl($base);
        if ($ssrf !== true) {
            return array('ok' => false, 'msg' => $ssrf);
        }

        $adminId = class_exists('Auth') ? (int) Auth::id() : 0;
        $bucket = 'ai:test:' . ($adminId > 0 ? $adminId : '0');
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, 60, 12, true)) {
            return array('ok' => false, 'msg' => '请求过于频繁，请稍后再试');
        }

        // 1) 先试 GET /models（不依赖模型名）
        $modelsHit = self::httpRequest('GET', self::modelsUrl($base), null, $key, $timeout);
        $modelsOk = is_array($modelsHit) && empty($modelsHit['_error']);

        // 2) 有模型名则发一条极简对话（Chat → Responses 回退）
        if ($model !== '') {
            $probeCfg = array(
                'baseurl'  => $base,
                'apikey'   => $key,
                'model'    => $model,
                'timeout'  => $timeout,
                'api_mode' => $mode,
            );
            $probe = self::requestAssistant(
                $probeCfg,
                'Reply with exactly: ok',
                'ping',
                array('temperature' => 0, 'probe' => true)
            );
            if (!empty($probe['ok'])) {
                $reply = isset($probe['text']) ? trim((string) $probe['text']) : '';
                $via = isset($probe['via']) ? (string) $probe['via'] : 'chat';
                $msg = '连接成功（' . $via . '）';
                if ($reply !== '') {
                    $short = function_exists('mb_substr') ? mb_substr($reply, 0, 80, 'UTF-8') : substr($reply, 0, 80);
                    $msg .= '，模型回复：' . $short;
                } else {
                    $msg .= '，上游已正常响应（HTTP ' . (int) (isset($probe['http']) ? $probe['http'] : 200) . '）';
                }
                return array(
                    'ok'    => true,
                    'msg'   => $msg,
                    'via'   => $via,
                    'reply' => $reply,
                    'http'  => isset($probe['http']) ? (int) $probe['http'] : 200,
                );
            }
            // 对话失败但 /models 成功：仍算连通，提示检查模型名
            if ($modelsOk) {
                $err = isset($probe['error']) ? (string) $probe['error'] : '对话接口未通过';
                return array(
                    'ok'  => true,
                    'msg' => '密钥与根地址可用（已拉取到模型列表），但当前模型对话未通过：' . $err . '。请换模型或检查 api_mode。',
                    'via' => 'models',
                );
            }
            return array(
                'ok'  => false,
                'msg' => isset($probe['error']) ? (string) $probe['error'] : '连接失败',
            );
        }

        if ($modelsOk) {
            $count = 0;
            if (isset($modelsHit['data']) && is_array($modelsHit['data'])) {
                $count = count($modelsHit['data']);
            }
            return array(
                'ok'  => true,
                'msg' => '连接成功（已访问 /models' . ($count > 0 ? ('，约 ' . $count . ' 个模型') : '') . '）。请选择或填写模型名后再测一次对话。',
                'via' => 'models',
            );
        }

        $err = is_array($modelsHit) && isset($modelsHit['_error'])
            ? (string) $modelsHit['_error']
            : '无法访问 /models，且未填写模型名做对话探测';
        return array('ok' => false, 'msg' => $err);
    }

    /**
     * 拉取可用模型 ID 列表
     *
     * @param array $cfg
     * @return array{ok:bool,msg:string,models?:array<int,string>}
     */
    public static function listModels(array $cfg)
    {
        $base = self::normalizeBaseUrl(isset($cfg['baseurl']) ? $cfg['baseurl'] : '');
        $key = trim((string) (isset($cfg['apikey']) ? $cfg['apikey'] : ''));
        $timeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 30;
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 90) {
            $timeout = 90;
        }
        if ($base === '' || $key === '') {
            return array('ok' => false, 'msg' => '请填写接口根地址与 API Key');
        }
        $ssrf = self::assertSafeBaseUrl($base);
        if ($ssrf !== true) {
            return array('ok' => false, 'msg' => $ssrf);
        }

        $adminId = class_exists('Auth') ? (int) Auth::id() : 0;
        $bucket = 'ai:models:' . ($adminId > 0 ? $adminId : '0');
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, 60, 8, true)) {
            return array('ok' => false, 'msg' => '请求过于频繁，请稍后再试');
        }

        $raw = self::httpRequest('GET', self::modelsUrl($base), null, $key, $timeout);
        if (!is_array($raw) || !empty($raw['_error'])) {
            return array(
                'ok'  => false,
                'msg' => is_array($raw) && isset($raw['_error']) ? (string) $raw['_error'] : '拉取模型失败',
            );
        }

        $ids = array();
        $rows = array();
        if (isset($raw['data']) && is_array($raw['data'])) {
            $rows = $raw['data'];
        } elseif (isset($raw['models']) && is_array($raw['models'])) {
            $rows = $raw['models'];
        } elseif (self::isListArray($raw)) {
            $rows = $raw;
        }
        foreach ($rows as $row) {
            if (is_string($row) && $row !== '') {
                $ids[] = $row;
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $id = '';
            if (isset($row['id'])) {
                $id = (string) $row['id'];
            } elseif (isset($row['name'])) {
                $id = (string) $row['name'];
            } elseif (isset($row['model'])) {
                $id = (string) $row['model'];
            }
            $id = trim($id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);
        if ($ids === array()) {
            return array('ok' => false, 'msg' => '上游未返回可用模型列表（接口可能不支持 GET /models）');
        }
        return array(
            'ok'     => true,
            'msg'    => '已拉取 ' . count($ids) . ' 个模型',
            'models' => $ids,
        );
    }

    /**
     * @param array  $cfg
     * @param string $system
     * @param string $user
     * @param array  $opts messages=多轮；stream 请用 chatStreamWithConfig
     * @return string
     */
    public static function chatWithConfig(array $cfg, $system, $user, array $opts = array())
    {
        $adminId = class_exists('Auth') ? (int) Auth::id() : 0;
        $userId = class_exists('UserAuth') ? (int) UserAuth::id() : 0;
        // 代码示例最多 27 片/批，10 次/分会误杀；放宽到 45 次/分
        $bucket = 'ai:chat:' . ($adminId > 0 ? ('a' . $adminId) : ('u' . $userId));
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, 60, 45, true)) {
            return '错误：请求过于频繁，请稍后再试';
        }

        $result = self::requestAssistant($cfg, $system, $user, $opts);
        if (empty($result['ok'])) {
            return '错误：' . (isset($result['error']) ? (string) $result['error'] : '请求失败');
        }
        $text = isset($result['text']) ? trim((string) $result['text']) : '';
        if ($text === '') {
            // 生成场景仍需要正文；测试走 testConnection，不走这里
            return '错误：模型未返回可用正文（请换模型或将接口模式改为 Chat / Responses）';
        }
        if (preg_match('/^```(?:markdown|md)?\s*\n([\s\S]*?)\n```\s*$/u', $text, $m)) {
            $text = trim($m[1]);
        }
        return $text;
    }

    /**
     * Chat Completions 流式；onDelta(string $chunk)。失败时回退整包并一次回调全文。
     *
     * @param array         $cfg
     * @param array         $messages [{role,content},…]
     * @param array         $opts
     * @param callable|null $onDelta
     * @return array{ok:bool,text?:string,error?:string,via?:string}
     */
    public static function chatStreamWithConfig(array $cfg, array $messages, array $opts = array(), $onDelta = null)
    {
        $adminId = class_exists('Auth') ? (int) Auth::id() : 0;
        $userId = class_exists('UserAuth') ? (int) UserAuth::id() : 0;
        $bucket = 'ai:chat:' . ($adminId > 0 ? ('a' . $adminId) : ('u' . $userId));
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, 60, 45, true)) {
            return array('ok' => false, 'error' => '请求过于频繁，请稍后再试');
        }

        $base = self::normalizeBaseUrl(isset($cfg['baseurl']) ? $cfg['baseurl'] : '');
        $key = trim((string) (isset($cfg['apikey']) ? $cfg['apikey'] : ''));
        $model = trim((string) (isset($cfg['model']) ? $cfg['model'] : ''));
        $timeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 120;
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 600) {
            $timeout = 600;
        }
        if ($base === '' || $key === '' || $model === '') {
            return array('ok' => false, 'error' => 'AI 配置不完整（根地址 / Key / 模型）');
        }
        $ssrf = self::assertSafeBaseUrl($base);
        if ($ssrf !== true) {
            return array('ok' => false, 'error' => $ssrf);
        }
        if ($messages === array()) {
            return array('ok' => false, 'error' => '对话消息为空');
        }

        $hit = self::callChatCompletionsStream($base, $key, $model, $messages, $timeout, $opts, $onDelta);
        if (!empty($hit['ok'])) {
            $hit['via'] = 'chat_stream';
            return $hit;
        }

        // 流式失败：整包 Chat，再一次性推给前端
        $opts['messages'] = $messages;
        $system = '';
        $user = '';
        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'])) {
                continue;
            }
            $role = strtolower((string) $m['role']);
            $content = isset($m['content']) ? (string) $m['content'] : '';
            if ($role === 'system' && $system === '') {
                $system = $content;
            } elseif ($role === 'user') {
                $user = $content;
            }
        }
        $fallback = self::callChatCompletions($base, $key, $model, $system, $user, $timeout, $opts);
        if (!empty($fallback['ok'])) {
            $text = isset($fallback['text']) ? (string) $fallback['text'] : '';
            if ($text !== '' && is_callable($onDelta)) {
                call_user_func($onDelta, $text);
            }
            return array(
                'ok'   => true,
                'text' => $text,
                'via'  => 'chat_fallback',
                'http' => isset($fallback['http']) ? (int) $fallback['http'] : 200,
            );
        }
        return array(
            'ok'    => false,
            'error' => isset($hit['error']) ? (string) $hit['error'] : (
                isset($fallback['error']) ? (string) $fallback['error'] : '流式与整包均失败'
            ),
        );
    }

    /**
     * @param array  $cfg
     * @param string $system
     * @param string $user
     * @param array  $opts probe=true 时允许空正文仍算成功
     * @return array{ok:bool,text?:string,via?:string,http?:int,error?:string,raw?:array}
     */
    private static function requestAssistant(array $cfg, $system, $user, array $opts = array())
    {
        $base = self::normalizeBaseUrl(isset($cfg['baseurl']) ? $cfg['baseurl'] : '');
        $key = trim((string) (isset($cfg['apikey']) ? $cfg['apikey'] : ''));
        $model = trim((string) (isset($cfg['model']) ? $cfg['model'] : ''));
        $timeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 120;
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 600) {
            $timeout = 600;
        }
        $mode = self::normalizeApiMode(isset($cfg['api_mode']) ? $cfg['api_mode'] : AiConfig::apiMode());
        $probe = !empty($opts['probe']);

        if ($base === '' || $key === '' || $model === '') {
            return array('ok' => false, 'error' => 'AI 配置不完整（根地址 / Key / 模型）');
        }
        $ssrf = self::assertSafeBaseUrl($base);
        if ($ssrf !== true) {
            return array('ok' => false, 'error' => $ssrf);
        }

        $order = array();
        if ($mode === 'responses') {
            $order = array('responses', 'chat');
        } elseif ($mode === 'chat') {
            $order = array('chat', 'responses');
        } else {
            $order = array('chat', 'responses');
        }

        $lastError = '未知错误';
        foreach ($order as $via) {
            if ($via === 'chat') {
                $hit = self::callChatCompletions($base, $key, $model, $system, $user, $timeout, $opts);
            } else {
                $hit = self::callResponses($base, $key, $model, $system, $user, $timeout, $opts);
            }
            if (!empty($hit['ok'])) {
                $text = isset($hit['text']) ? (string) $hit['text'] : '';
                if ($text !== '' || $probe) {
                    $hit['via'] = $via;
                    return $hit;
                }
                $lastError = '上游已响应但正文为空（' . $via . '）';
                continue;
            }
            $lastError = isset($hit['error']) ? (string) $hit['error'] : ('调用失败：' . $via);
            // 404/不支持时尝试下一种协议
            if (isset($hit['http']) && ((int) $hit['http'] === 404 || (int) $hit['http'] === 405)) {
                continue;
            }
            // 明确鉴权失败则不再试
            if (isset($hit['http']) && in_array((int) $hit['http'], array(401, 403), true)) {
                break;
            }
        }

        return array('ok' => false, 'error' => $lastError);
    }

    /**
     * @param string $base
     * @param string $key
     * @param string $model
     * @param string $system
     * @param string $user
     * @param int    $timeout
     * @param array  $opts
     * @return array
     */
    private static function callChatCompletions($base, $key, $model, $system, $user, $timeout, array $opts)
    {
        $url = self::chatCompletionsUrl($base);
        $temperature = isset($opts['temperature']) ? (float) $opts['temperature'] : 0.3;
        if (isset($opts['messages']) && is_array($opts['messages']) && $opts['messages'] !== array()) {
            $messages = $opts['messages'];
        } else {
            $messages = array(
                array('role' => 'system', 'content' => (string) $system),
                array('role' => 'user', 'content' => (string) $user),
            );
        }
        $payload = array(
            'model'    => $model,
            'messages' => $messages,
        );
        // 探测时不加 max_tokens，避免部分模型（o 系列 / reasoner）空 content
        if (empty($opts['probe'])) {
            $payload['temperature'] = $temperature;
            if (isset($opts['max_tokens']) && (int) $opts['max_tokens'] > 0) {
                $payload['max_tokens'] = (int) $opts['max_tokens'];
            }
        } else {
            $payload['temperature'] = 0;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return array('ok' => false, 'error' => '请求编码失败');
        }
        $raw = self::httpRequest('POST', $url, $body, $key, $timeout);
        if (!is_array($raw) || !empty($raw['_error'])) {
            return array(
                'ok'    => false,
                'error' => is_array($raw) && isset($raw['_error']) ? (string) $raw['_error'] : '网络错误',
                'http'  => is_array($raw) && isset($raw['_http']) ? (int) $raw['_http'] : 0,
            );
        }
        $http = isset($raw['_http']) ? (int) $raw['_http'] : 200;
        unset($raw['_http']);
        $text = self::extractAssistantText($raw);
        return array(
            'ok'   => true,
            'text' => $text,
            'http' => $http,
            'raw'  => $raw,
        );
    }

    /**
     * @param string        $base
     * @param string        $key
     * @param string        $model
     * @param array         $messages
     * @param int           $timeout
     * @param array         $opts
     * @param callable|null $onDelta
     * @return array{ok:bool,text?:string,error?:string,http?:int}
     */
    private static function callChatCompletionsStream($base, $key, $model, array $messages, $timeout, array $opts, $onDelta)
    {
        $url = self::chatCompletionsUrl($base);
        $temperature = isset($opts['temperature']) ? (float) $opts['temperature'] : 0.3;
        $payload = array(
            'model'    => $model,
            'messages' => $messages,
            'stream'   => true,
        );
        $payload['temperature'] = $temperature;
        if (isset($opts['max_tokens']) && (int) $opts['max_tokens'] > 0) {
            $payload['max_tokens'] = (int) $opts['max_tokens'];
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return array('ok' => false, 'error' => '请求编码失败');
        }

        $buf = '';
        $full = '';
        $http = 0;
        $hadDelta = false;
        $parseError = '';

        $onLine = function ($line) use (&$full, &$hadDelta, &$parseError, $onDelta) {
            $line = trim((string) $line);
            if ($line === '' || $line === 'data: [DONE]' || $line === '[DONE]') {
                return;
            }
            if (strpos($line, 'data:') === 0) {
                $line = trim(substr($line, 5));
            }
            if ($line === '' || $line === '[DONE]') {
                return;
            }
            $json = json_decode($line, true);
            if (!is_array($json)) {
                return;
            }
            if (isset($json['error'])) {
                if (is_array($json['error']) && isset($json['error']['message'])) {
                    $parseError = (string) $json['error']['message'];
                } elseif (is_string($json['error'])) {
                    $parseError = $json['error'];
                }
                return;
            }
            $piece = '';
            if (isset($json['choices'][0]['delta']['content'])) {
                $piece = self::stringifyContent($json['choices'][0]['delta']['content']);
                // stringifyContent 会 trim，流式中间空格不能丢：改用手写
                $rawC = $json['choices'][0]['delta']['content'];
                if (is_string($rawC)) {
                    $piece = $rawC;
                }
            } elseif (isset($json['choices'][0]['text'])) {
                $rawC = $json['choices'][0]['text'];
                $piece = is_string($rawC) ? $rawC : self::stringifyContent($rawC);
            }
            if ($piece === '') {
                return;
            }
            $hadDelta = true;
            $full .= $piece;
            if (is_callable($onDelta)) {
                call_user_func($onDelta, $piece);
            }
        };

        $raw = self::httpRequestStream('POST', $url, $body, $key, $timeout, function ($chunk) use (&$buf, $onLine) {
            $buf .= (string) $chunk;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = substr($buf, 0, $pos);
                $buf = substr($buf, $pos + 1);
                $onLine($line);
            }
        }, $http);

        if ($buf !== '') {
            $onLine($buf);
        }

        if (!empty($raw['_error'])) {
            return array(
                'ok'    => false,
                'error' => (string) $raw['_error'],
                'http'  => $http,
            );
        }
        if ($parseError !== '') {
            return array('ok' => false, 'error' => $parseError, 'http' => $http);
        }
        if ($http >= 400 && !$hadDelta) {
            return array(
                'ok'    => false,
                'error' => '上游 HTTP ' . $http,
                'http'  => $http,
            );
        }
        if (!$hadDelta && trim($full) === '') {
            return array('ok' => false, 'error' => '流式未返回正文', 'http' => $http);
        }
        return array('ok' => true, 'text' => $full, 'http' => $http);
    }

    /**
     * OpenAI Responses API：POST /responses
     *
     * @param string $base
     * @param string $key
     * @param string $model
     * @param string $system
     * @param string $user
     * @param int    $timeout
     * @param array  $opts
     * @return array
     */
    private static function callResponses($base, $key, $model, $system, $user, $timeout, array $opts)
    {
        $url = self::responsesUrl($base);
        $variants = array();
        // 形态 A：input 为消息数组
        $variants[] = array(
            'model' => $model,
            'input' => array(
                array('role' => 'system', 'content' => (string) $system),
                array('role' => 'user', 'content' => (string) $user),
            ),
        );
        // 形态 B：instructions + 字符串 input（官方常见）
        $variants[] = array(
            'model'        => $model,
            'instructions' => (string) $system,
            'input'        => (string) $user,
        );

        $last = array('ok' => false, 'error' => 'Responses 调用失败', 'http' => 0);
        foreach ($variants as $payload) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                return array('ok' => false, 'error' => '请求编码失败');
            }
            $raw = self::httpRequest('POST', $url, $body, $key, $timeout);
            if (!is_array($raw) || !empty($raw['_error'])) {
                $last = array(
                    'ok'    => false,
                    'error' => is_array($raw) && isset($raw['_error']) ? (string) $raw['_error'] : '网络错误',
                    'http'  => is_array($raw) && isset($raw['_http']) ? (int) $raw['_http'] : 0,
                );
                continue;
            }
            $http = isset($raw['_http']) ? (int) $raw['_http'] : 200;
            unset($raw['_http']);
            return array(
                'ok'   => true,
                'text' => self::extractAssistantText($raw),
                'http' => $http,
                'raw'  => $raw,
            );
        }
        return $last;
    }

    /**
     * 从 Chat / Responses / 多厂商变体中抽出助手文本
     *
     * @param array $raw
     * @return string
     */
    public static function extractAssistantText(array $raw)
    {
        // Chat Completions
        if (isset($raw['choices'][0])) {
            $c0 = $raw['choices'][0];
            if (is_array($c0)) {
                if (isset($c0['message']) && is_array($c0['message'])) {
                    $t = self::stringifyContent($c0['message']['content']);
                    if ($t === '' && isset($c0['message']['reasoning_content'])) {
                        $t = self::stringifyContent($c0['message']['reasoning_content']);
                    }
                    if ($t !== '') {
                        return $t;
                    }
                }
                if (isset($c0['text'])) {
                    $t = self::stringifyContent($c0['text']);
                    if ($t !== '') {
                        return $t;
                    }
                }
                if (isset($c0['delta'])) {
                    $t = self::stringifyContent(isset($c0['delta']['content']) ? $c0['delta']['content'] : $c0['delta']);
                    if ($t !== '') {
                        return $t;
                    }
                }
            }
        }

        // Responses API
        if (isset($raw['output_text']) && is_string($raw['output_text']) && trim($raw['output_text']) !== '') {
            return trim($raw['output_text']);
        }
        if (isset($raw['output']) && is_array($raw['output'])) {
            $buf = '';
            foreach ($raw['output'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $part) {
                        if (!is_array($part)) {
                            continue;
                        }
                        if (isset($part['text'])) {
                            $buf .= self::stringifyContent($part['text']);
                        } elseif (isset($part['output_text'])) {
                            $buf .= self::stringifyContent($part['output_text']);
                        }
                    }
                }
                if (isset($item['text'])) {
                    $buf .= self::stringifyContent($item['text']);
                }
            }
            $buf = trim($buf);
            if ($buf !== '') {
                return $buf;
            }
        }

        // Anthropic Messages 兼容代理偶发字段
        if (isset($raw['content'])) {
            $t = self::stringifyContent($raw['content']);
            if ($t !== '') {
                return $t;
            }
        }
        if (isset($raw['result'])) {
            $t = self::stringifyContent($raw['result']);
            if ($t !== '') {
                return $t;
            }
        }
        if (isset($raw['data']['content'])) {
            $t = self::stringifyContent($raw['data']['content']);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * @param mixed $content
     * @return string
     */
    private static function stringifyContent($content)
    {
        if ($content === null) {
            return '';
        }
        if (is_string($content)) {
            return trim($content);
        }
        if (is_numeric($content)) {
            return trim((string) $content);
        }
        if (!is_array($content)) {
            return '';
        }
        // OpenAI content parts: [{type:text,text:"..."}]
        $buf = '';
        if (self::isListArray($content)) {
            foreach ($content as $part) {
                if (is_string($part)) {
                    $buf .= $part;
                    continue;
                }
                if (!is_array($part)) {
                    continue;
                }
                if (isset($part['text']) && is_string($part['text'])) {
                    $buf .= $part['text'];
                } elseif (isset($part['content']) && is_string($part['content'])) {
                    $buf .= $part['content'];
                } elseif (isset($part['value']) && is_string($part['value'])) {
                    $buf .= $part['value'];
                }
            }
            return trim($buf);
        }
        if (isset($content['text'])) {
            return self::stringifyContent($content['text']);
        }
        return '';
    }

    /**
     * @param string $baseurl
     * @return string
     */
    public static function normalizeBaseUrl($baseurl)
    {
        $base = trim((string) $baseurl);
        $base = rtrim($base, '/');
        // 用户误填完整路径时剥到根
        $base = preg_replace('#/chat/completions$#i', '', $base);
        $base = preg_replace('#/responses$#i', '', $base);
        $base = preg_replace('#/models$#i', '', $base);
        $base = rtrim((string) $base, '/');
        // LongCat 缺 /v1
        if (stripos($base, 'longcat.chat/openai') !== false && !preg_match('#/v\d+$#i', $base)) {
            $base .= '/v1';
        }
        return $base;
    }

    /**
     * AI 根地址 SSRF 守卫：禁止内网 / 回环 / 非公网主机
     *
     * @param string $baseurl
     * @return true|string
     */
    public static function assertSafeBaseUrl($baseurl)
    {
        $base = self::normalizeBaseUrl($baseurl);
        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            return '接口根地址须以 http:// 或 https:// 开头';
        }
        if (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($base)) {
            return '接口根地址不允许指向内网、本机或非公网主机';
        }
        return true;
    }

    /**
     * @param string $mode
     * @return string auto|chat|responses
     */
    public static function normalizeApiMode($mode)
    {
        $mode = strtolower(trim((string) $mode));
        if ($mode === 'chat' || $mode === 'completions') {
            return 'chat';
        }
        if ($mode === 'responses' || $mode === 'response') {
            return 'responses';
        }
        return 'auto';
    }

    /**
     * @param string $base
     * @return string
     */
    public static function chatCompletionsUrl($base)
    {
        $base = self::normalizeBaseUrl($base);
        return $base . '/chat/completions';
    }

    /**
     * @param string $base
     * @return string
     */
    public static function responsesUrl($base)
    {
        $base = self::normalizeBaseUrl($base);
        return $base . '/responses';
    }

    /**
     * @param string $base
     * @return string
     */
    public static function modelsUrl($base)
    {
        $base = self::normalizeBaseUrl($base);
        return $base . '/models';
    }

    /**
     * 流式 HTTP：onChunk 收到原始字节；成功返回空数组；失败含 _error
     *
     * @param string        $method
     * @param string        $url
     * @param string|null   $jsonBody
     * @param string        $apiKey
     * @param int           $timeout
     * @param callable      $onChunk
     * @param int           $httpOut
     * @return array
     */
    private static function httpRequestStream($method, $url, $jsonBody, $apiKey, $timeout, $onChunk, &$httpOut)
    {
        $timeout = (int) $timeout;
        $httpOut = 0;
        if (!function_exists('curl_init')) {
            return array('_error' => '服务器未启用 curl 扩展');
        }
        if (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($url)) {
            return array('_error' => '接口地址不允许指向内网或非公网主机');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return array('_error' => '无法发起请求');
        }
        $headers = array(
            'Accept: text/event-stream, application/json',
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        );
        $errHold = array('');
        // 首包前上游也可能「思考」很久：靠进度回调给浏览器/CDN 打 SSE 心跳，避免空闲掐断
        $lastKeepAt = array(microtime(true));
        $opts = array(
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_POSTFIELDS     => $jsonBody !== null ? $jsonBody : '{}',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => function () use (&$lastKeepAt) {
                if (!class_exists('AiSse') || !AiSse::isActive()) {
                    return 0;
                }
                $now = microtime(true);
                if (($now - $lastKeepAt[0]) >= 5.0) {
                    $lastKeepAt[0] = $now;
                    AiSse::maybePing(true);
                }
                return 0;
            },
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use ($onChunk, &$httpOut, $errHold) {
                if ($httpOut <= 0) {
                    $httpOut = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                }
                // 非 2xx 仍收集正文以便报错
                if (is_callable($onChunk)) {
                    call_user_func($onChunk, $data);
                } else {
                    $errHold[0] .= $data;
                }
                return strlen($data);
            },
        );
        curl_setopt_array($ch, $opts);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        if ($httpOut <= 0) {
            $httpOut = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
        if ($errno) {
            return array('_error' => '网络错误：' . ($err !== '' ? $err : ('#' . $errno)), '_http' => $httpOut);
        }
        if ($ok === false) {
            return array('_error' => '流式请求失败', '_http' => $httpOut);
        }
        return array('_http' => $httpOut);
    }

    /**
     * @param string      $method GET|POST
     * @param string      $url
     * @param string|null $jsonBody
     * @param string      $apiKey
     * @param int         $timeout
     * @return array 成功为解码数组（含 _http）；失败含 _error / _http
     */
    private static function httpRequest($method, $url, $jsonBody, $apiKey, $timeout)
    {
        $timeout = (int) $timeout;
        if (!function_exists('curl_init')) {
            return array('_error' => '服务器未启用 curl 扩展', '_http' => 0);
        }
        // 出站前再拦一层（含 models/chat 最终 URL）
        if (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($url)) {
            return array('_error' => '接口地址不允许指向内网或非公网主机', '_http' => 0);
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return array('_error' => '无法发起请求', '_http' => 0);
        }
        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        );
        $method = strtoupper($method);
        $opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST  => $method,
        );
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = $jsonBody !== null ? $jsonBody : '{}';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            return array('_error' => '网络错误：' . ($err !== '' ? $err : ('#' . $errno)), '_http' => $http);
        }
        $decoded = json_decode((string) $resp, true);
        if (!is_array($decoded)) {
            $snippet = trim(preg_replace('/\s+/', ' ', (string) $resp));
            if (function_exists('mb_substr')) {
                $snippet = mb_substr($snippet, 0, 120, 'UTF-8');
            } else {
                $snippet = substr($snippet, 0, 120);
            }
            return array(
                '_error' => '响应不是合法 JSON（HTTP ' . $http . '）' . ($snippet !== '' ? ('：' . $snippet) : ''),
                '_http'  => $http,
            );
        }
        if ($http >= 400) {
            $msg = '';
            if (isset($decoded['error']['message'])) {
                $msg = (string) $decoded['error']['message'];
            } elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                $msg = (string) $decoded['error'];
            } elseif (isset($decoded['message'])) {
                $msg = (string) $decoded['message'];
            } elseif (isset($decoded['msg'])) {
                $msg = (string) $decoded['msg'];
            }
            return array(
                '_error' => '上游 HTTP ' . $http . ($msg !== '' ? ('：' . $msg) : ''),
                '_http'  => $http,
            );
        }
        $decoded['_http'] = $http;
        return $decoded;
    }

    /**
     * @param array $arr
     * @return bool
     */
    private static function isListArray(array $arr)
    {
        if ($arr === array()) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
