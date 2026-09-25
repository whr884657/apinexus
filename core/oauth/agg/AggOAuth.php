<?php
/**
 * 文件：core/oauth/agg/AggOAuth.php
 * 作用：第三方聚合登录对接（彩虹系 connect.php 协议；网关地址由站点配置）
 *
 * @see 开发规范/聚合登录开发规范.md
 */

class AggOAuth
{
    const PROVIDER = 'agg';

    /**
     * @param string $itemId 内部登录方式 id
     * @param array  $context
     * @return string|null 上游授权跳转 URL
     */
    public static function authorizeUrl($itemId, array $context = array())
    {
        $item = OAuthConfig::aggItem($itemId);
        if ($item === null || empty($item['on'])) {
            return null;
        }

        $cfg = OAuthConfig::getProvider(self::PROVIDER);
        $apiurl = self::connectEndpoint(isset($cfg['apiurl']) ? $cfg['apiurl'] : '');
        if ($apiurl === '') {
            return null;
        }

        $type = trim((string) $item['type']);
        if ($type === '' || !OAuthConfig::isValidAggType($type)) {
            return null;
        }

        $context['item_id'] = (string) $item['id'];
        $state = OAuthState::create(self::PROVIDER, $context);
        // 彩虹系文档回调示例常只带 type+code；把短 token 钉在 redirect_uri（ost）与 state 双通道
        $redirect = OAuthConfig::callbackUrl(self::PROVIDER) . '&ost=' . rawurlencode($state);

        $query = http_build_query(array(
            'act'          => 'login',
            'appid'        => isset($cfg['app_id']) ? $cfg['app_id'] : '',
            'appkey'       => isset($cfg['app_key']) ? $cfg['app_key'] : '',
            'type'         => $type,
            'redirect_uri' => $redirect,
            'state'        => $state,
        ));

        $body = OAuthHttpClient::get($apiurl . '?' . $query);
        if ($body === false) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['code']) || (int) $data['code'] !== 0) {
            return null;
        }

        $url = isset($data['url']) ? trim((string) $data['url']) : '';
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }

    /**
     * @param string $code
     * @param string $type 当前配置的接口对应值
     * @param string $itemId
     * @return array{item_id: string, social_uid: string, nickname: string, avatar: string}|null
     */
    public static function fetchIdentity($code, $type, $itemId)
    {
        $cfg = OAuthConfig::getProvider(self::PROVIDER);
        $apiurl = self::connectEndpoint(isset($cfg['apiurl']) ? $cfg['apiurl'] : '');
        $type = trim((string) $type);
        $itemId = trim((string) $itemId);
        $code = trim((string) $code);

        if ($apiurl === '' || $type === '' || $itemId === '' || $code === '') {
            return null;
        }

        $query = http_build_query(array(
            'act'    => 'callback',
            'appid'  => isset($cfg['app_id']) ? $cfg['app_id'] : '',
            'appkey' => isset($cfg['app_key']) ? $cfg['app_key'] : '',
            'type'   => $type,
            'code'   => $code,
        ));

        $body = OAuthHttpClient::get($apiurl . '?' . $query);
        if ($body === false) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['code']) || (int) $data['code'] !== 0) {
            return null;
        }

        $socialUid = isset($data['social_uid']) ? trim((string) $data['social_uid']) : '';
        if ($socialUid === '') {
            return null;
        }

        return array(
            'item_id'    => $itemId,
            'social_uid' => $socialUid,
            'nickname'   => isset($data['nickname']) ? trim((string) $data['nickname']) : '',
            'avatar'     => isset($data['faceimg']) ? trim((string) $data['faceimg']) : '',
        );
    }

    /**
     * @param string $apiurl
     * @return string 完整 connect.php 地址，非法则空串
     */
    public static function connectEndpoint($apiurl)
    {
        $apiurl = trim((string) $apiurl);
        if ($apiurl === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $apiurl)) {
            return '';
        }
        $apiurl = rtrim($apiurl, '/') . '/';
        if (stripos($apiurl, 'connect.php') !== false) {
            return rtrim($apiurl, '/');
        }
        return $apiurl . 'connect.php';
    }
}
