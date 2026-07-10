<?php
/**
 * 文件：core/oauth/gitee/GiteeOAuth.php
 * 作用：Gitee OAuth2.0
 *
 * @see https://gitee.com/api/v5/oauth_doc
 */

class GiteeOAuth
{
    const PROVIDER = 'gitee';

    /**
     * @param array $context
     * @return string
     */
    public static function authorizeUrl(array $context = array())
    {
        $cfg = OAuthConfig::getProvider(self::PROVIDER);
        $params = array(
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => OAuthConfig::callbackUrl(self::PROVIDER),
            'response_type' => 'code',
            'state'         => OAuthState::create(self::PROVIDER, $context),
        );

        return 'https://gitee.com/oauth/authorize?' . http_build_query($params);
    }

    /**
     * @param string $code
     * @return array{id: string, login: string, name: string, avatar: string}|null
     */
    public static function fetchIdentity($code)
    {
        $cfg = OAuthConfig::getProvider(self::PROVIDER);
        $tokenBody = OAuthHttpClient::postForm('https://gitee.com/oauth/token', array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => OAuthConfig::callbackUrl(self::PROVIDER),
            'client_secret' => $cfg['client_secret'],
        ));

        if ($tokenBody === false) {
            return null;
        }

        $tokenData = json_decode($tokenBody, true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            return null;
        }

        $userBody = OAuthHttpClient::get(
            'https://gitee.com/api/v5/user?access_token=' . rawurlencode($tokenData['access_token'])
        );
        if ($userBody === false) {
            return null;
        }

        $user = json_decode($userBody, true);
        if (!is_array($user) || empty($user['id'])) {
            return null;
        }

        return array(
            'id'     => (string) $user['id'],
            'login'  => isset($user['login']) ? (string) $user['login'] : '',
            'name'   => isset($user['name']) ? (string) $user['name'] : '',
            'avatar' => isset($user['avatar_url']) ? (string) $user['avatar_url'] : '',
        );
    }
}
