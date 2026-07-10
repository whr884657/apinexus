<?php
/**
 * 文件：core/oauth/qq/QQOAuth.php
 * 作用：QQ 互联 OAuth2.0（网站应用）
 *
 * @see https://wiki.connect.qq.com/oauth2-0%E5%BC%80%E5%8F%91%E6%96%87%E6%A1%A3
 */

class QQOAuth
{
    const PROVIDER = 'qq';

    /**
     * @param array $context
     * @return string
     */
    public static function authorizeUrl(array $context = array())
    {
        $cfg = OAuthConfig::getProvider(self::PROVIDER);
        $params = array(
            'response_type' => 'code',
            'client_id'     => $cfg['app_id'],
            'redirect_uri'  => OAuthConfig::callbackUrl(self::PROVIDER),
            'state'         => OAuthState::create(self::PROVIDER, $context),
            'scope'         => 'get_user_info',
        );

        return 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query($params);
    }

    /**
     * @param string $code
     * @return array{openid: string, nickname: string, avatar: string}|null
     */
    public static function fetchIdentity($code)
    {
        $cfg = OAuthConfig::getProvider(self::PROVIDER);
        $tokenUrl = 'https://graph.qq.com/oauth2.0/token?' . http_build_query(array(
            'grant_type'    => 'authorization_code',
            'client_id'     => $cfg['app_id'],
            'client_secret' => $cfg['app_key'],
            'code'          => $code,
            'redirect_uri'  => OAuthConfig::callbackUrl(self::PROVIDER),
            'fmt'           => 'json',
        ));

        $tokenBody = OAuthHttpClient::get($tokenUrl);
        if ($tokenBody === false) {
            return null;
        }

        $tokenData = json_decode($tokenBody, true);
        if (!is_array($tokenData)) {
            parse_str(trim($tokenBody), $parsed);
            $tokenData = $parsed;
        }

        if (empty($tokenData['access_token'])) {
            return null;
        }

        $accessToken = $tokenData['access_token'];
        $meBody = OAuthHttpClient::get('https://graph.qq.com/oauth2.0/me?access_token=' . rawurlencode($accessToken));
        if ($meBody === false) {
            return null;
        }

        if (preg_match('/\{.*\}/s', $meBody, $m)) {
            $meData = json_decode($m[0], true);
        } else {
            $meData = null;
        }

        if (!is_array($meData) || empty($meData['openid'])) {
            return null;
        }

        $openid = (string) $meData['openid'];
        $nickname = '';
        $avatar = '';

        $infoUrl = 'https://graph.qq.com/user/get_user_info?' . http_build_query(array(
            'access_token'       => $accessToken,
            'oauth_consumer_key' => $cfg['app_id'],
            'openid'             => $openid,
        ));
        $infoBody = OAuthHttpClient::get($infoUrl);
        if ($infoBody !== false) {
            $info = json_decode($infoBody, true);
            if (is_array($info) && isset($info['ret']) && (int) $info['ret'] === 0) {
                $nickname = isset($info['nickname']) ? (string) $info['nickname'] : '';
                $avatar = isset($info['figureurl_qq_2']) ? (string) $info['figureurl_qq_2'] : '';
                if ($avatar === '' && isset($info['figureurl_qq_1'])) {
                    $avatar = (string) $info['figureurl_qq_1'];
                }
            }
        }

        return array(
            'openid'   => $openid,
            'nickname' => $nickname,
            'avatar'   => $avatar,
        );
    }
}
