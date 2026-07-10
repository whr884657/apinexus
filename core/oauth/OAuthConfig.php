<?php
/**
 * 文件：core/oauth/OAuthConfig.php
 * 作用：OAuth 配置读写（存于 config.oauth_config JSON）
 */

class OAuthConfig
{
    const CONFIG_KEY = 'oauth_config';

    /**
     * @return array
     */
    public static function getAll()
    {
        $raw = (string) Config::get(self::CONFIG_KEY, '');
        if ($raw === '') {
            return self::defaults();
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return self::defaults();
        }

        $defaults = self::defaults();
        foreach (array('qq', 'gitee') as $provider) {
            if (!isset($data[$provider]) || !is_array($data[$provider])) {
                $data[$provider] = $defaults[$provider];
                continue;
            }
            $data[$provider] = array_merge($defaults[$provider], $data[$provider]);
        }

        return $data;
    }

    /**
     * @param string $provider qq|gitee
     * @return array
     */
    public static function getProvider($provider)
    {
        $all = self::getAll();
        return isset($all[$provider]) ? $all[$provider] : array();
    }

    /**
     * @param string $provider
     * @return bool
     */
    public static function isEnabled($provider)
    {
        $cfg = self::getProvider($provider);
        if (empty($cfg['enabled'])) {
            return false;
        }

        if ($provider === 'qq') {
            return trim((string) $cfg['app_id']) !== '' && trim((string) $cfg['app_key']) !== '';
        }

        if ($provider === 'gitee') {
            return trim((string) $cfg['client_id']) !== '' && trim((string) $cfg['client_secret']) !== '';
        }

        return false;
    }

    /**
     * @return array
     */
    public static function defaults()
    {
        return array(
            'qq' => array(
                'enabled' => false,
                'app_id'  => '',
                'app_key' => '',
            ),
            'gitee' => array(
                'enabled'    => false,
                'client_id'     => '',
                'client_secret' => '',
            ),
        );
    }

    /**
     * @param array $qq
     * @param array $gitee
     * @return void
     * @throws Exception
     */
    public static function save(array $qq, array $gitee)
    {
        $payload = array(
            'qq' => array(
                'enabled' => !empty($qq['enabled']),
                'app_id'  => trim(isset($qq['app_id']) ? $qq['app_id'] : ''),
                'app_key' => trim(isset($qq['app_key']) ? $qq['app_key'] : ''),
            ),
            'gitee' => array(
                'enabled'       => !empty($gitee['enabled']),
                'client_id'     => trim(isset($gitee['client_id']) ? $gitee['client_id'] : ''),
                'client_secret' => trim(isset($gitee['client_secret']) ? $gitee['client_secret'] : ''),
            ),
        );

        Config::set(self::CONFIG_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param string $provider
     * @return string
     */
    public static function callbackUrl($provider)
    {
        return vs_base_url() . '/user/oauth/callback.php?provider=' . rawurlencode($provider);
    }
}
