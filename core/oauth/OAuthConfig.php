<?php
/**
 * 文件：core/oauth/OAuthConfig.php
 * 作用：OAuth 配置读写（存于 config.oauth_config JSON；含 qq / gitee / agg）
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
        foreach (array('qq', 'gitee', 'agg') as $provider) {
            if (!isset($data[$provider]) || !is_array($data[$provider])) {
                $data[$provider] = $defaults[$provider];
                continue;
            }
            if ($provider === 'agg') {
                $data['agg'] = self::normalizeAgg($data['agg'], $defaults['agg']);
            } else {
                $data[$provider] = array_merge($defaults[$provider], $data[$provider]);
            }
        }

        return $data;
    }

    /**
     * @param string $provider qq|gitee|agg
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

        if ($provider === 'agg') {
            $apiurl = trim((string) (isset($cfg['apiurl']) ? $cfg['apiurl'] : ''));
            $appId = trim((string) (isset($cfg['app_id']) ? $cfg['app_id'] : ''));
            $appKey = trim((string) (isset($cfg['app_key']) ? $cfg['app_key'] : ''));
            if ($apiurl === '' || $appId === '' || $appKey === '') {
                return false;
            }
            if (AggOAuth::connectEndpoint($apiurl) === '') {
                return false;
            }
            foreach (self::aggItemsOn($cfg) as $item) {
                return true;
            }
            return false;
        }

        return false;
    }

    /**
     * 某聚合登录方式是否可用（通道启用且该项 on）
     *
     * @param string $itemId
     * @return bool
     */
    public static function isAggItemEnabled($itemId)
    {
        if (!self::isEnabled('agg')) {
            return false;
        }
        $item = self::aggItem($itemId);
        return $item !== null && !empty($item['on']);
    }

    /**
     * @param string $itemId
     * @return array|null
     */
    public static function aggItem($itemId)
    {
        $itemId = trim((string) $itemId);
        if ($itemId === '' || !self::isValidAggItemId($itemId)) {
            return null;
        }
        $cfg = self::getProvider('agg');
        $items = isset($cfg['items']) && is_array($cfg['items']) ? $cfg['items'] : array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['id']) && (string) $item['id'] === $itemId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @param array|null $cfg
     * @return array[]
     */
    public static function aggItemsOn($cfg = null)
    {
        if ($cfg === null) {
            $cfg = self::getProvider('agg');
        }
        $items = isset($cfg['items']) && is_array($cfg['items']) ? $cfg['items'] : array();
        $out = array();
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['on'])) {
                continue;
            }
            if (empty($item['id']) || empty($item['type'])) {
                continue;
            }
            if (!self::isValidAggItemId($item['id']) || !self::isValidAggType($item['type'])) {
                continue;
            }
            $out[] = $item;
        }
        return $out;
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
                'enabled'       => false,
                'client_id'     => '',
                'client_secret' => '',
            ),
            'agg' => array(
                'enabled' => false,
                'apiurl'  => '',
                'app_id'  => '',
                'app_key' => '',
                'items'   => self::builtinAggItems(),
            ),
        );
    }

    /**
     * 内置登录方式（接口 type 取文档高频默认值，可被站点改写）
     *
     * @return array[]
     */
    public static function builtinAggItems()
    {
        $rows = array(
            array('id' => 'qq', 'name' => 'QQ', 'type' => 'qq', 'icon' => 'oauth/qq.svg'),
            array('id' => 'wx', 'name' => '微信', 'type' => 'wx', 'icon' => 'oauth/weixin.svg'),
            array('id' => 'wxmp', 'name' => '公众号', 'type' => 'wxmp', 'icon' => 'oauth/gongzhonghao.svg'),
            array('id' => 'alipay', 'name' => '支付宝', 'type' => 'alipay', 'icon' => 'oauth/zhifubao.svg'),
            array('id' => 'sina', 'name' => '微博', 'type' => 'sina', 'icon' => 'oauth/weibo.svg'),
            array('id' => 'baidu', 'name' => '百度', 'type' => 'baidu', 'icon' => 'oauth/baidu.svg'),
            array('id' => 'douyin', 'name' => '抖音', 'type' => 'douyin', 'icon' => 'oauth/doying.svg'),
            array('id' => 'huawei', 'name' => '华为', 'type' => 'huawei', 'icon' => 'oauth/huawei.svg'),
            array('id' => 'xiaomi', 'name' => '小米', 'type' => 'xiaomi', 'icon' => 'oauth/xiaomi.svg'),
            array('id' => 'aliyun', 'name' => '阿里云', 'type' => 'aliyun', 'icon' => 'oauth/alyun.svg'),
            array('id' => 'wework', 'name' => '企业微信', 'type' => 'wework', 'icon' => 'oauth/qiyeweixin.svg'),
            array('id' => 'dingtalk', 'name' => '钉钉', 'type' => 'dingtalk', 'icon' => 'oauth/dindin.svg'),
            array('id' => 'gitee', 'name' => '码云', 'type' => 'gitee', 'icon' => 'oauth/gitee.svg'),
            array('id' => 'bilibili', 'name' => '哔哩哔哩', 'type' => 'bilibili', 'icon' => 'oauth/bilibil.svg'),
            array('id' => 'kuaishou', 'name' => '快手', 'type' => 'kuaishou', 'icon' => 'oauth/kuaishou.svg'),
            array('id' => 'google', 'name' => '谷歌', 'type' => 'google', 'icon' => 'oauth/google.svg'),
            array('id' => 'microsoft', 'name' => '微软', 'type' => 'microsoft', 'icon' => 'oauth/weiruan.svg'),
            array('id' => 'github', 'name' => 'GitHub', 'type' => 'github', 'icon' => 'oauth/github.svg'),
            array('id' => 'gitlab', 'name' => 'GitLab', 'type' => 'gitlab', 'icon' => 'oauth/gitlab.svg'),
        );

        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'id'   => $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'icon' => $row['icon'],
                'on'   => false,
                'kind' => 'builtin',
            );
        }
        return $out;
    }

    /**
     * @param array $qq
     * @param array $gitee
     * @param array $agg
     * @return void
     * @throws Exception
     */
    public static function save(array $qq, array $gitee, array $agg = array())
    {
        $aggNorm = self::sanitizeAggForSave($agg);

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
            'agg' => $aggNorm,
        );

        if (!empty($aggNorm['enabled'])) {
            if (trim($aggNorm['apiurl']) === '' || AggOAuth::connectEndpoint($aggNorm['apiurl']) === '') {
                throw new Exception('请填写有效的聚合登录网关地址');
            }
            if (trim($aggNorm['app_id']) === '' || trim($aggNorm['app_key']) === '') {
                throw new Exception('请填写聚合登录 AppID 与 AppKey');
            }
            $onCount = 0;
            foreach ($aggNorm['items'] as $item) {
                if (!empty($item['on'])) {
                    $onCount++;
                }
            }
            if ($onCount < 1) {
                throw new Exception('启用聚合登录时请至少开启一种登录方式');
            }
        }

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

    /**
     * 解析图标为可展示 URL（本地 oauth/ 或 https 外链）
     *
     * @param string $icon
     * @return string
     */
    public static function resolveIconUrl($icon)
    {
        $icon = trim((string) $icon);
        if ($icon === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $icon)) {
            return $icon;
        }
        $icon = ltrim(str_replace('\\', '/', $icon), '/');
        if (strpos($icon, '..') !== false) {
            return '';
        }
        return SiteMedia::imgUrl($icon);
    }

    /**
     * @param string $id
     * @return bool
     */
    public static function isValidAggItemId($id)
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,31}$/', (string) $id);
    }

    /**
     * @param string $type
     * @return bool
     */
    public static function isValidAggType($type)
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', (string) $type);
    }

    /**
     * 按网关回调 type 反查内部 item id（优先已启用项；同 type 多条取首个启用）
     *
     * @param string $type
     * @return string 空=未找到
     */
    public static function resolveAggItemIdByType($type)
    {
        $type = trim((string) $type);
        if ($type === '' || !self::isValidAggType($type)) {
            return '';
        }
        $cfg = self::getProvider('agg');
        $items = isset($cfg['items']) && is_array($cfg['items']) ? $cfg['items'] : array();
        $fallback = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $t = isset($item['type']) ? trim((string) $item['type']) : '';
            if (strcasecmp($t, $type) !== 0) {
                continue;
            }
            $id = isset($item['id']) ? (string) $item['id'] : '';
            if ($id === '' || !self::isValidAggItemId($id)) {
                continue;
            }
            if (!empty($item['on'])) {
                return $id;
            }
            if ($fallback === '') {
                $fallback = $id;
            }
        }
        return $fallback;
    }

    /**
     * @param array $agg
     * @param array $defaults
     * @return array
     */
    private static function normalizeAgg(array $agg, array $defaults)
    {
        $out = array(
            'enabled' => !empty($agg['enabled']),
            'apiurl'  => trim(isset($agg['apiurl']) ? $agg['apiurl'] : ''),
            'app_id'  => trim(isset($agg['app_id']) ? $agg['app_id'] : ''),
            'app_key' => trim(isset($agg['app_key']) ? $agg['app_key'] : ''),
            'items'   => array(),
        );

        $byId = array();
        if (isset($agg['items']) && is_array($agg['items'])) {
            foreach ($agg['items'] as $item) {
                if (!is_array($item) || empty($item['id'])) {
                    continue;
                }
                $id = (string) $item['id'];
                if (!self::isValidAggItemId($id)) {
                    continue;
                }
                $byId[$id] = $item;
            }
        }

        foreach ($defaults['items'] as $builtin) {
            $id = $builtin['id'];
            if (isset($byId[$id])) {
                $row = $byId[$id];
                $out['items'][] = array(
                    'id'   => $id,
                    'name' => $builtin['name'],
                    'type' => self::isValidAggType(isset($row['type']) ? $row['type'] : '')
                        ? trim((string) $row['type'])
                        : $builtin['type'],
                    'icon' => $builtin['icon'],
                    'on'   => !empty($row['on']),
                    'kind' => 'builtin',
                );
                unset($byId[$id]);
            } else {
                $out['items'][] = $builtin;
            }
        }

        foreach ($byId as $id => $row) {
            $kind = isset($row['kind']) && $row['kind'] === 'custom' ? 'custom' : 'custom';
            $type = isset($row['type']) ? trim((string) $row['type']) : '';
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $icon = isset($row['icon']) ? trim((string) $row['icon']) : '';
            if ($name === '' || !self::isValidAggType($type)) {
                continue;
            }
            if ($icon !== '' && !preg_match('#^https?://#i', $icon) && strpos($icon, '..') !== false) {
                $icon = '';
            }
            $out['items'][] = array(
                'id'   => $id,
                'name' => mb_substr($name, 0, 32),
                'type' => $type,
                'icon' => $icon,
                'on'   => !empty($row['on']),
                'kind' => $kind,
            );
        }

        return $out;
    }

    /**
     * @param array $agg
     * @return array
     * @throws Exception
     */
    private static function sanitizeAggForSave(array $agg)
    {
        $defaults = self::defaults();
        $merged = self::normalizeAgg(array_merge($defaults['agg'], $agg, array(
            'enabled' => !empty($agg['enabled']),
            'apiurl'  => isset($agg['apiurl']) ? $agg['apiurl'] : '',
            'app_id'  => isset($agg['app_id']) ? $agg['app_id'] : '',
            'app_key' => isset($agg['app_key']) ? $agg['app_key'] : '',
            'items'   => isset($agg['items']) && is_array($agg['items']) ? $agg['items'] : array(),
        )), $defaults['agg']);

        return $merged;
    }
}
