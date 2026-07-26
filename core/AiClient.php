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
     * @return string|array 成功返回助手文本；失败返回错误文案字符串（以「错误：」开头）由上层识别
     */
    public static function chat($system, $user)
    {
        if (!AiConfig::isReady()) {
            return '错误：请先在系统设置中启用并配置 AI';
        }
        $cfg = AiConfig::get();

        $adminId = class_exists('Auth') ? (int) Auth::id() : 0;
        $bucket = 'ai:chat:' . ($adminId > 0 ? $adminId : '0');
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, 60, 8, true)) {
            return '错误：请求过于频繁，请稍后再试';
        }

        $url = rtrim($cfg['baseurl'], '/') . '/chat/completions';
        // LongCat OpenAI 兼容根须含 /v1
        if (stripos($cfg['baseurl'], 'longcat.chat/openai') !== false
            && !preg_match('#/v\d+$#i', rtrim($cfg['baseurl'], '/'))) {
            $url = rtrim($cfg['baseurl'], '/') . '/v1/chat/completions';
        }
        $payload = array(
            'model'       => $cfg['model'],
            'temperature' => 0.3,
            'messages'    => array(
                array('role' => 'system', 'content' => (string) $system),
                array('role' => 'user', 'content' => (string) $user),
            ),
        );

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return '错误：请求编码失败';
        }

        $raw = self::httpPostJson($url, $body, $cfg['apikey'], $cfg['timeout']);
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

        // 去掉常见 markdown 外层代码围栏
        if (preg_match('/^```(?:markdown|md)?\s*\n([\s\S]*?)\n```\s*$/u', $content, $m)) {
            $content = trim($m[1]);
        }
        return $content;
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
