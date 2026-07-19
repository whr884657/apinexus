<?php
/**
 * 文件：core/oauth/OAuthState.php
 * 作用：OAuth state 防 CSRF（HMAC 签名，不依赖 Session 存取）
 *
 * 说明：OAuth 回调为跨站跳转，SameSite=Strict 时 Session 可能丢失；
 *       state 使用服务端密钥签名，回调时可直接校验。
 */

class OAuthState
{
    const TTL = 600;
    const SESSION_USED_KEY = 'vs_oauth_state_used';

    /**
     * @param string $provider
     * @param array  $context intent: login|bind, user_id: int
     * @return string
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

        $payload = array(
            'p' => (string) $provider,
            'i' => $intent,
            'u' => $userId,
            'e' => time() + self::TTL,
            'n' => bin2hex(random_bytes(8)),
        );

        return self::encode($payload);
    }

    /**
     * 仅解析 state（不消费、不校验 nonce），用于错误页跳转判断
     *
     * @param string $provider
     * @param string $state
     * @return array{intent: string, user_id: int}|false
     */
    public static function peek($provider, $state)
    {
        $payload = self::decode($state);
        if ($payload === false || $payload['p'] !== $provider) {
            return false;
        }

        return array(
            'intent'  => $payload['i'],
            'user_id' => $payload['u'],
        );
    }

    /**
     * @param string $provider
     * @param string $state
     * @return array|false
     */
    public static function consume($provider, $state)
    {
        $payload = self::decode($state);
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
        );
    }

    /**
     * @param array $payload
     * @return string
     */
    private static function encode(array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', $json, self::signingKey());

        return self::base64UrlEncode($json) . '.' . $sig;
    }

    /**
     * @param string $state
     * @return array{p: string, i: string, u: int, e: int, n: string}|false
     */
    private static function decode($state)
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
        if (!is_array($data) || empty($data['p']) || empty($data['n']) || empty($data['e'])) {
            return false;
        }

        $intent = isset($data['i']) && $data['i'] === 'bind' ? 'bind' : 'login';

        return array(
            'p' => (string) $data['p'],
            'i' => $intent,
            'u' => isset($data['u']) ? (int) $data['u'] : 0,
            'e' => (int) $data['e'],
            'n' => (string) $data['n'],
        );
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
