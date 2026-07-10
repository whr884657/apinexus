<?php
/**
 * 文件：core/oauth/OAuthState.php
 * 作用：OAuth state 防 CSRF（含 intent / user_id）
 */

class OAuthState
{
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

        $state = bin2hex(random_bytes(16));
        $_SESSION['vs_oauth_state'] = array(
            'provider' => $provider,
            'state'    => $state,
            'intent'   => $intent,
            'user_id'  => $userId,
            'expires'  => time() + 600,
        );

        return $state;
    }

    /**
     * @param string $provider
     * @param string $state
     * @return array|false
     */
    public static function consume($provider, $state)
    {
        if (!isset($_SESSION['vs_oauth_state']) || !is_array($_SESSION['vs_oauth_state'])) {
            return false;
        }

        $saved = $_SESSION['vs_oauth_state'];
        unset($_SESSION['vs_oauth_state']);

        if (empty($saved['expires']) || (int) $saved['expires'] < time()) {
            return false;
        }

        if (
            !isset($saved['provider'], $saved['state'])
            || $saved['provider'] !== $provider
            || !hash_equals((string) $saved['state'], (string) $state)
        ) {
            return false;
        }

        $saved['intent'] = isset($saved['intent']) && $saved['intent'] === 'bind' ? 'bind' : 'login';
        $saved['user_id'] = isset($saved['user_id']) ? (int) $saved['user_id'] : 0;

        return $saved;
    }
}
