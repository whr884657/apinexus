<?php
/**
 * 文件：core/oauth/OAuthState.php
 * 作用：OAuth state 防 CSRF
 */

class OAuthState
{
    /**
     * @param string $provider
     * @return string
     */
    public static function create($provider)
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['vs_oauth_state'] = array(
            'provider' => $provider,
            'state'    => $state,
            'expires'  => time() + 600,
        );
        return $state;
    }

    /**
     * @param string $provider
     * @param string $state
     * @return bool
     */
    public static function verify($provider, $state)
    {
        if (!isset($_SESSION['vs_oauth_state']) || !is_array($_SESSION['vs_oauth_state'])) {
            return false;
        }

        $saved = $_SESSION['vs_oauth_state'];
        unset($_SESSION['vs_oauth_state']);

        if (empty($saved['expires']) || (int) $saved['expires'] < time()) {
            return false;
        }

        return isset($saved['provider'], $saved['state'])
            && $saved['provider'] === $provider
            && hash_equals((string) $saved['state'], (string) $state);
    }
}
