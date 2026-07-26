<?php
/**
 * 文件：core/AiClient.php
 * 作用：OpenAI 兼容 Chat Completions 客户端（DeepSeek / 智谱 / LongCat / 自定义）
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
     * 用指定配置探测连通性（可不要求已启用；用于设置页「测试连接」）
     *
     * @param array $cfg 须含 baseurl / apikey / model；timeout 可选
     * @return string 成功返回「连接成功」类文案；失败「错误：…」
     */
    public static function testConnection(array $cfg)
    {
        $base = rtrim(trim((string) (isset($cfg['baseurl']) ? $cfg['baseurl'] : '')), '/');
        $key = trim((string) (isset($cfg['apikey']) ? $cfg['apikey'] : ''));
        $model = trim((string) (isset($cfg['model']) ? $cfg['model'] : ''));
        if ($base === '' || $key === '' || $model === '') {
            return '错误：请填写接口根地址、API Key 与模型名';
        }
        if (!preg_match('#^https?://#i', $base)) {
            return '错误：接口根地址须以 http:// 或 https:// 开头';
        }
        $timeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 30;
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 60) {
            $timeout = 60;
        }
        $probe = array(
            'baseurl' => $base,
            'apikey'  => $key,
            'model'   => $model,
            'timeout' => $timeout,
        );
        $out = self::chatWithConfig(
            $probe,
            'You are a connectivity probe. Reply with exactly: ok',
            'ping',
            array('max_tokens' => 32, 'temperature' => 0)
        );
        if (strpos($out, '错误：') === 0) {
            return $out;
        }
        return '连接成功';
    }

    /**
     * @param array  $cfg
     * @param string $system
     * @param string $user
     * @param array  $opts max_tokens / temperature 可选
     * @return string
     */
    public static function chatWithConfig(array $cfg, $system, $user, array $opts = array())
    {
        $base = rtrim(trim((string) (isset($cfg['baseurl']) ? $cfg['baseurl'] : '')), '/');
        $key = trim((string) (isset($cfg['apikey']) ? $cfg['apikey'] : ''));
        $model = trim((string) (isset($cfg['model']) ? $cfg['model'] : ''));
        $timeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 60;
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 180) {
            $timeout = 180;
        }
        if ($base === '' || $key === '' || $model === '') {
            return '错误：AI 配置不完整';
        }

        $adminId = class_exists('Auth') ? (int) Auth::id() : 0;
        $bucket = 'ai:chat:' . ($adminId > 0 ? $adminId : '0');
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, 60, 10, true)) {
            return '错误：请求过于频繁，请稍后再试';
        }

        $url = self::completionsUrl($base);
        $temperature = isset($opts['temperature']) ? (float) $opts['temperature'] : 0.3;
        $payload = array(
            'model'       => $model,
            'temperature' => $temperature,
            'messages'    => array(
                array('role' => 'system', 'content' => (string) $system),
                array('role' => 'user', 'content' => (string) $user),
            ),
        );
        if (isset($opts['max_tokens']) && (int) $opts['max_tokens'] > 0) {
            $payload['max_tokens'] = (int) $opts['max_tokens'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return '错误：请求编码失败';
        }

        $raw = self::httpPostJson($url, $body, $key, $timeout);
        if (!is_array($raw)) {
            return '错误：' . (string) $raw;
        }

        $content = '';
        if (isset($raw['choices'][0]['message']['content'])) {
            $content = (string) $raw['choices'][0]['message']['content'];
        } elseif (isset($raw['choices'][0]['text'])) {
            $content = (string) $raw['choices'][0]['text'];
        }
        $content = trim($content);
        if ($content === '') {
            $msg = isset($raw['error']['message']) ? (string) $raw['error']['message'] : '';
            if ($msg === '' && isset($raw['msg'])) {
                $msg = (string) $raw['msg'];
            }
            return '错误：' . ($msg !== '' ? $msg : '模型未返回内容');
        }

        if (preg_match('/^```(?:markdown|md)?\s*\n([\s\S]*?)\n```\s*$/u', $content, $m)) {
            $content = trim($m[1]);
        }
        return $content;
    }

    /**
     * @param string $baseurl
     * @return string
     */
    public static function completionsUrl($baseurl)
    {
        $base = rtrim((string) $baseurl, '/');
        // 用户误填到完整 completions 路径时不再追加
        if (preg_match('#/chat/completions$#i', $base)) {
            return $base;
        }
        // LongCat OpenAI 兼容根须含 /v1
        if (stripos($base, 'longcat.chat/openai') !== false
            && !preg_match('#/v\d+$#i', $base)) {
            return $base . '/v1/chat/completions';
        }
        return $base . '/chat/completions';
    }

    /**
     * @param string $url
     * @param string $jsonBody
     * @param string $apiKey
     * @param int    $timeout
     * @return array|string
     */
    private static function httpPostJson($url, $jsonBody, $apiKey, $timeout)
    {
        $timeout = (int) $timeout;
        if (!function_exists('curl_init')) {
            return '服务器未启用 curl 扩展';
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return '无法发起请求';
        }
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        );
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            return '网络错误：' . ($err !== '' ? $err : ('#' . $errno));
        }
        $decoded = json_decode((string) $resp, true);
        if (!is_array($decoded)) {
            return '响应不是合法 JSON（HTTP ' . $http . '）';
        }
        if ($http >= 400) {
            $msg = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : '';
            if ($msg === '' && isset($decoded['message'])) {
                $msg = (string) $decoded['message'];
            }
            if ($msg === '' && isset($decoded['msg'])) {
                $msg = (string) $decoded['msg'];
            }
            return '上游 HTTP ' . $http . ($msg !== '' ? ('：' . $msg) : '');
        }
        return $decoded;
    }
}
