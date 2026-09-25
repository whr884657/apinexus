<?php
/**
 * 文件：core/oauth/OAuthState.php
 * 作用：OAuth state 防 CSRF
 *
 * 策略：
 * - 对外只传短 token（32 hex），完整 payload 存服务端（Redis 优先，文件兜底）
 * - 聚合网关常不回传 / 截断超长 state（官方文档回调示例仅 type+code）；短 token + redirect_uri 上的 ost= 双通道
 * - 仍兼容旧版「base64.hmac」内联 state（升级瞬间在途请求）
 */

class OAuthState
{
    const TTL = 600;
    const SESSION_USED_KEY = 'vs_oauth_state_used';
    const REDIS_PREFIX = 'cache:oauth:state:';

    /**
     * @param string $provider
     * @param array  $context intent: login|bind, user_id: int, item_id: string
     * @return string 短 token（写入上游 state / 本站 ost）
     */
    public static function create($provider, array $context = array())
    {
        $intent = isset($context['intent']) ? (string) $context['intent'] : 'login';
        if ($intent !== 'bind') {
            $intent = 'login';
        }

        $userId = isset($context['user_id']) ? (int) $context['user_id'] : 0;
        if ($intent === 'bind' && $userId <= 0) {
            $intent = 'login';
            $userId = 0;
        }

        $itemId = isset($context['item_id']) ? trim((string) $context['item_id']) : '';
        if ($itemId !== '' && !preg_match('/^[a-z][a-z0-9_]{0,31}$/', $itemId)) {
            $itemId = '';
        }

        $payload = array(
            'p' => (string) $provider,
            'i' => $intent,
            'u' => $userId,
            'm' => $itemId,
            'e' => time() + self::TTL,
            'n' => bin2hex(random_bytes(8)),
        );

        $token = bin2hex(random_bytes(16));
        if (!self::storePayload($token, $payload)) {
            // 极端：存储失败时退回签名内联（可能被聚合网关截断，仅兜底）
            return self::encodeInline($payload);
        }

        return $token;
    }

    /**
     * 仅解析 state（不消费），用于错误页跳转判断
     *
     * @param string $provider
     * @param string $state
     * @return array{intent: string, user_id: int, item_id: string}|false
     */
    public static function peek($provider, $state)
    {
        $payload = self::loadPayload($state, false);
        if ($payload === false || $payload['p'] !== $provider) {
            return false;
        }

        return array(
            'intent'  => $payload['i'],
            'user_id' => $payload['u'],
            'item_id' => isset($payload['m']) ? (string) $payload['m'] : '',
        );
    }

    /**
     * @param string $provider
     * @param string $state
     * @return array|false
     */
    public static function consume($provider, $state)
    {
        $payload = self::loadPayload($state, true);
        if ($payload === false) {
            return false;
        }

        if ($payload['p'] !== $provider) {
            return false;
        }

        if ($payload['e'] < time()) {
            return false;
        }

        if (self::isNonceUsed($payload['n'])) {
            return false;
        }
        self::markNonceUsed($payload['n']);

        return array(
            'provider' => $payload['p'],
            'intent'   => $payload['i'],
            'user_id'  => $payload['u'],
            'item_id'  => isset($payload['m']) ? (string) $payload['m'] : '',
        );
    }

    /**
     * @param string $token
     * @param array  $payload
     * @return bool
     */
    private static function storePayload($token, array $payload)
    {
        $token = preg_replace('/[^a-f0-9]/i', '', (string) $token);
        if (strlen($token) < 16) {
            return false;
        }

        $ttl = self::TTL;
        if (class_exists('RedisCache') && method_exists('RedisCache', 'enabled') && RedisCache::enabled()) {
            try {
                RedisCache::put(self::REDIS_PREFIX . $token, $payload, $ttl);
                return true;
            } catch (Exception $e) {
                // fall through
            }
        }

        $dir = self::fileDir();
        if ($dir === '' || !is_dir($dir)) {
            return false;
        }
        $path = $dir . '/' . $token . '.json';
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($raw === false) {
            return false;
        }
        $ok = @file_put_contents($path, $raw, LOCK_EX);
        if ($ok === false) {
            return false;
        }
        @chmod($path, 0640);
        return true;
    }

    /**
     * @param string $state
     * @param bool   $consume 短 token 读后删除
     * @return array{p: string, i: string, u: int, m: string, e: int, n: string}|false
     */
    private static function loadPayload($state, $consume)
    {
        $state = trim((string) $state);
        if ($state === '') {
            return false;
        }

        // 短 token：仅十六进制
        if (preg_match('/^[a-f0-9]{32}$/i', $state)) {
            $token = strtolower($state);
            $payload = null;

            if (class_exists('RedisCache') && method_exists('RedisCache', 'enabled') && RedisCache::enabled()) {
                try {
                    $payload = RedisCache::get(self::REDIS_PREFIX . $token);
                    if ($consume && is_array($payload)) {
                        RedisCache::forget(self::REDIS_PREFIX . $token);
                    }
                } catch (Exception $e) {
                    $payload = null;
                }
            }

            if (!is_array($payload)) {
                $path = self::fileDir() . '/' . $token . '.json';
                if (is_file($path)) {
                    $json = @file_get_contents($path);
                    $payload = is_string($json) ? json_decode($json, true) : null;
                    if ($consume) {
                        @unlink($path);
                    }
                }
            }

            return self::normalizePayload($payload);
        }

        // 旧版内联 HMAC
        return self::decodeInline($state);
    }

    /**
     * @param mixed $payload
     * @return array{p: string, i: string, u: int, m: string, e: int, n: string}|false
     */
    private static function normalizePayload($payload)
    {
        if (!is_array($payload) || empty($payload['p']) || empty($payload['n']) || empty($payload['e'])) {
            return false;
        }

        $intent = isset($payload['i']) && $payload['i'] === 'bind' ? 'bind' : 'login';
        $itemId = isset($payload['m']) ? trim((string) $payload['m']) : '';
        if ($itemId !== '' && !preg_match('/^[a-z][a-z0-9_]{0,31}$/', $itemId)) {
            $itemId = '';
        }

        return array(
            'p' => (string) $payload['p'],
            'i' => $intent,
            'u' => isset($payload['u']) ? (int) $payload['u'] : 0,
            'm' => $itemId,
            'e' => (int) $payload['e'],
            'n' => (string) $payload['n'],
        );
    }

    /**
     * @return string
     */
    private static function fileDir()
    {
        $dir = VS_ROOT . '/data/cache/oauth_state';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
            if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
                @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            }
            if (is_dir($dir) && !is_file($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '');
            }
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return '';
        }
        // 偶发清理过期文件（最多扫 40 个）
        static $cleaned = false;
        if (!$cleaned) {
            $cleaned = true;
            $files = @scandir($dir);
            if (is_array($files)) {
                $n = 0;
                foreach ($files as $f) {
                    if ($n >= 40) {
                        break;
                    }
                    if (!preg_match('/^[a-f0-9]{32}\.json$/i', $f)) {
                        continue;
                    }
                    $path = $dir . '/' . $f;
                    $mtime = @filemtime($path);
                    if ($mtime !== false && $mtime < time() - self::TTL - 60) {
                        @unlink($path);
                        $n++;
                    }
                }
            }
        }
        return $dir;
    }

    /**
     * @param array $payload
     * @return string
     */
    private static function encodeInline(array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', $json, self::signingKey());

        return self::base64UrlEncode($json) . '.' . $sig;
    }

    /**
     * @param string $state
     * @return array{p: string, i: string, u: int, m: string, e: int, n: string}|false
     */
    private static function decodeInline($state)
    {
        $state = trim((string) $state);
        if ($state === '' || strpos($state, '.') === false) {
            return false;
        }

        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $json = self::base64UrlDecode($parts[0]);
        if ($json === false || $json === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $json, self::signingKey());
        if (!hash_equals($expected, $parts[1])) {
            return false;
        }

        $data = json_decode($json, true);
        return self::normalizePayload($data);
    }

    /**
     * @return string
     */
    private static function signingKey()
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }

        $parts = array('apinexus-oauth-state-v1');
        $lockFile = VS_ROOT . '/config/install.lock';
        if (is_file($lockFile)) {
            $parts[] = trim((string) @file_get_contents($lockFile));
        }

        try {
            if (InstallChecker::isInstalled()) {
                $oauth = OAuthConfig::getAll();
                $parts[] = isset($oauth['gitee']['client_secret']) ? (string) $oauth['gitee']['client_secret'] : '';
                $parts[] = isset($oauth['qq']['app_key']) ? (string) $oauth['qq']['app_key'] : '';
                $parts[] = isset($oauth['agg']['app_key']) ? (string) $oauth['agg']['app_key'] : '';
            }
        } catch (Exception $e) {
            // ignore
        }

        $parts[] = vs_base_url();
        $key = hash('sha256', implode('|', $parts));

        return $key;
    }

    /**
     * @param string $nonce
     * @return bool
     */
    private static function isNonceUsed($nonce)
    {
        if (!isset($_SESSION[self::SESSION_USED_KEY]) || !is_array($_SESSION[self::SESSION_USED_KEY])) {
            return false;
        }

        return in_array($nonce, $_SESSION[self::SESSION_USED_KEY], true);
    }

    /**
     * @param string $nonce
     * @return void
     */
    private static function markNonceUsed($nonce)
    {
        if (!isset($_SESSION[self::SESSION_USED_KEY]) || !is_array($_SESSION[self::SESSION_USED_KEY])) {
            $_SESSION[self::SESSION_USED_KEY] = array();
        }

        $_SESSION[self::SESSION_USED_KEY][] = $nonce;
        if (count($_SESSION[self::SESSION_USED_KEY]) > 50) {
            $_SESSION[self::SESSION_USED_KEY] = array_slice($_SESSION[self::SESSION_USED_KEY], -50);
        }
    }

    /**
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param string $data
     * @return string|false
     */
    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? false : $decoded;
    }
}
