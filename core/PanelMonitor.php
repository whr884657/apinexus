<?php
/**
 * 文件：core/PanelMonitor.php
 * 作用：对接宝塔 / 1Panel 面板接口，汇总控制台「服务器」卡片数据
 *
 * 原则：失败不得拖垮控制台；短超时；轻量接口；缓存正负结果。
 */

class PanelMonitor
{
    const PROVIDER_NONE = '';
    const PROVIDER_BAOTA = 'baota';
    const PROVIDER_ONEPANEL = 'onepanel';

    /** 成功缓存秒数 */
    const CACHE_TTL = 10;
    /** 失败缓存秒数（防雪崩） */
    const CACHE_FAIL_TTL = 6;
    /** 单次 HTTP 超时（秒） */
    const HTTP_TIMEOUT = 3;

    /**
     * @return array
     */
    public static function emptySnapshot()
    {
        return array(
            'enabled'       => false,
            'configured'    => false,
            'ok'            => false,
            'error'         => '',
            'provider'      => '',
            'providerlabel' => '',
            'panelversion'  => '',
            'system'        => '',
            'uptime'        => '',
            'cpu'           => null,
            'cpucores'      => null,
            'load1'         => null,
            'load5'         => null,
            'load15'        => null,
            'memtotal'      => null,
            'memused'       => null,
            'mempercent'    => null,
            'netup'         => null,
            'netdown'       => null,
            'fetchedat'     => '',
        );
    }

    /**
     * 配置开关是否启用（容忍空白/大小写）
     *
     * @return bool
     */
    public static function isEnabled()
    {
        $v = strtolower(trim((string) Config::get('panelmonitor_enabled', '0')));
        return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }

    /**
     * 清除面板监控缓存（保存/测试成功后调用）
     *
     * @return void
     */
    public static function clearCache()
    {
        if (!class_exists('RedisCache')) {
            return;
        }
        $provider = self::normalizeProvider(Config::get('panelmonitor_provider', ''));
        $url = trim((string) Config::get('panelmonitor_baseurl', ''));
        if ($provider !== self::PROVIDER_NONE && $url !== '') {
            RedisCache::forget(self::cacheKey($provider, $url));
            // 兼容历史未规范化 URL 的缓存键
            RedisCache::forget(self::cacheKey($provider, rtrim($url, '/')));
        }
        $prev = RedisCache::get('panel_monitor_last_key');
        if (is_string($prev) && $prev !== '') {
            RedisCache::forget($prev);
            RedisCache::forget('panel_monitor_last_key');
        }
    }

    /**
     * @param string $provider
     * @param string $url
     * @return string
     */
    private static function cacheKey($provider, $url)
    {
        return 'panel_monitor_snap_' . md5($provider . '|' . $url);
    }

    /**
     * 用当前 Config 覆盖快照中的启用/类型/已配置标记（禁止 Redis 旧值误导控制台）
     *
     * @param array  $snap
     * @param bool   $enabled
     * @param string $provider
     * @param string $url
     * @param string $key
     * @return array
     */
    private static function applyConfigFlags(array $snap, $enabled, $provider, $url, $key)
    {
        $snap['enabled'] = (bool) $enabled;
        $snap['provider'] = $provider;
        $snap['providerlabel'] = self::providerLabel($provider);
        $snap['configured'] = ($url !== '' && $key !== '' && $provider !== self::PROVIDER_NONE);
        return $snap;
    }

    /**
     * 持久化面板监控配置（保存 / 测试成功后写入）
     *
     * @param string      $provider
     * @param string      $baseUrl
     * @param string      $apiKey      空串表示保留原密钥
     * @param bool        $enabled
     * @param int|null    $liveInterval 1～5；null 不改刷新间隔
     * @return true|string 成功返回 true，失败返回业务错误文案
     */
    public static function persistConfig($provider, $baseUrl, $apiKey, $enabled, $liveInterval = null)
    {
        $rawProvider = trim((string) $provider);
        $provider = self::normalizeProvider($rawProvider);
        // 空或无法识别时保留原面板类型，禁止用空串/脏值覆盖（E190 / E192）
        if ($provider === self::PROVIDER_NONE) {
            $prev = self::normalizeProvider(Config::get('panelmonitor_provider', ''));
            if ($prev !== self::PROVIDER_NONE) {
                $provider = $prev;
            }
        }
        $baseUrl = trim((string) $baseUrl);
        if ($baseUrl === '') {
            $baseUrl = trim((string) Config::get('panelmonitor_baseurl', ''));
        }
        if ($baseUrl !== '') {
            $urlOk = self::assertSafePanelUrl($baseUrl);
            if ($urlOk !== true) {
                return $urlOk;
            }
            $baseUrl = rtrim($baseUrl, '/');
            if (!preg_match('#^https?://#i', $baseUrl)) {
                $baseUrl = 'https://' . $baseUrl;
            }
        }
        $apiKey = trim((string) $apiKey);
        if ($apiKey === '') {
            $apiKey = (string) Config::get('panelmonitor_apikey', '');
        }
        $enabled = (bool) $enabled;
        if ($enabled && $provider === self::PROVIDER_NONE) {
            return '请选择面板类型';
        }
        if ($enabled && ($baseUrl === '' || $apiKey === '')) {
            return '请填写面板地址与接口密钥';
        }

        $items = array(
            'panelmonitor_enabled'  => $enabled ? '1' : '0',
            'panelmonitor_provider' => $provider,
            'panelmonitor_baseurl'  => $baseUrl,
            'panelmonitor_apikey'   => $apiKey,
        );
        if ($liveInterval !== null) {
            $interval = (int) $liveInterval;
            if ($interval < 1) {
                $interval = 1;
            }
            if ($interval > 5) {
                $interval = 5;
            }
            $items['dashboard_live_interval'] = (string) $interval;
        }
        self::clearCache();
        Config::setMany($items);
        Config::clearCache();
        // 写后回读，避免静默写失败导致控制台长期「未启用」
        if (self::isEnabled() !== $enabled) {
            Config::set('panelmonitor_enabled', $enabled ? '1' : '0');
            Config::clearCache();
        }
        if ($enabled && self::normalizeProvider(Config::get('panelmonitor_provider', '')) === self::PROVIDER_NONE) {
            return '面板类型未能保存，请重新选择后保存';
        }
        if ($enabled && trim((string) Config::get('panelmonitor_baseurl', '')) === '') {
            return '面板地址未能保存，请重新填写后保存';
        }
        if ($enabled && trim((string) Config::get('panelmonitor_apikey', '')) === '') {
            return '接口密钥未能保存，请重新填写后保存';
        }
        self::clearCache();
        return true;
    }

    /**
     * 控制台用快照（短缓存；失败不抛出）
     *
     * @param bool $refresh
     * @return array
     */
    public static function snapshot($refresh = false)
    {
        $base = self::emptySnapshot();
        try {
            $enabled = self::isEnabled();
            $provider = self::normalizeProvider(Config::get('panelmonitor_provider', ''));
            $url = trim((string) Config::get('panelmonitor_baseurl', ''));
            $key = trim((string) Config::get('panelmonitor_apikey', ''));
            $base = self::applyConfigFlags($base, $enabled, $provider, $url, $key);

            if (!$enabled) {
                $base['error'] = '未启用服务器监控';
                return $base;
            }
            if ($provider === self::PROVIDER_NONE) {
                $base['error'] = '请选择面板类型';
                return $base;
            }
            if ($url === '' || $key === '') {
                $base['configured'] = false;
                $base['error'] = '请先在系统设置中填写面板地址与接口密钥，并点击保存';
                return $base;
            }

            $cacheKey = self::cacheKey($provider, $url);
            if (!$refresh && class_exists('RedisCache')) {
                $hit = RedisCache::get($cacheKey);
                if (is_array($hit) && isset($hit['ok'])) {
                    // E191：缓存命中也必须叠加当前 Config，禁止旧 enabled=false 误显示「未启用」
                    $hit = self::sanitizeSnapshot($hit);
                    return self::applyConfigFlags($hit, true, $provider, $url, $key);
                }
            }

            if ($provider === self::PROVIDER_BAOTA) {
                $snap = self::fetchBaota($url, $key);
            } else {
                $snap = self::fetchOnePanel($url, $key);
            }
            $snap = self::applyConfigFlags($snap, true, $provider, $url, $key);
            $snap['fetchedat'] = date('Y-m-d H:i:s');
            $snap = self::sanitizeSnapshot($snap);
            if (class_exists('RedisCache')) {
                RedisCache::set($cacheKey, $snap, self::CACHE_TTL);
                RedisCache::set('panel_monitor_last_key', $cacheKey, self::CACHE_TTL + 30);
            }
            return $snap;
        } catch (Exception $e) {
            return self::failSnapshot($base, '面板连接失败，请检查地址、密钥与白名单');
        } catch (Throwable $e) {
            return self::failSnapshot($base, '面板暂时不可用');
        }
    }

    /**
     * @param array  $base
     * @param string $msg
     * @return array
     */
    private static function failSnapshot(array $base, $msg)
    {
        $base['ok'] = false;
        $base['error'] = $msg;
        $base['fetchedat'] = date('Y-m-d H:i:s');
        try {
            $provider = self::normalizeProvider(Config::get('panelmonitor_provider', ''));
            $url = trim((string) Config::get('panelmonitor_baseurl', ''));
            $key = trim((string) Config::get('panelmonitor_apikey', ''));
            $enabled = self::isEnabled();
            $base = self::applyConfigFlags($base, $enabled, $provider, $url, $key);
            $base['ok'] = false;
            $base['error'] = $msg;
            if ($url !== '' && $provider !== self::PROVIDER_NONE && class_exists('RedisCache')) {
                $cacheKey = self::cacheKey($provider, $url);
                RedisCache::set($cacheKey, $base, self::CACHE_FAIL_TTL);
                RedisCache::set('panel_monitor_last_key', $cacheKey, self::CACHE_FAIL_TTL + 30);
            }
        } catch (Exception $e) {
            // 回落路径禁止再抛
        } catch (Throwable $e) {
            // 回落路径禁止再抛
        }
        return $base;
    }

    /**
     * 设置页「测试连接」
     *
     * @param string $provider
     * @param string $baseUrl
     * @param string $apiKey
     * @return array{ok:bool,msg:string,snapshot?:array}
     */
    public static function testConnection($provider, $baseUrl, $apiKey)
    {
        $provider = self::normalizeProvider($provider);
        $baseUrl = trim((string) $baseUrl);
        $apiKey = trim((string) $apiKey);
        if ($provider === self::PROVIDER_NONE) {
            return array('ok' => false, 'msg' => '请选择面板类型');
        }
        if ($baseUrl === '' || $apiKey === '') {
            return array('ok' => false, 'msg' => '请填写面板地址与接口密钥');
        }
        try {
            if ($provider === self::PROVIDER_BAOTA) {
                $snap = self::fetchBaota($baseUrl, $apiKey);
            } else {
                $snap = self::fetchOnePanel($baseUrl, $apiKey);
            }
            $snap = self::sanitizeSnapshot($snap);
            $snap['provider'] = $provider;
            $snap['providerlabel'] = self::providerLabel($provider);
            $label = $snap['providerlabel'];
            $ver = isset($snap['panelversion']) ? (string) $snap['panelversion'] : '';
            $msg = '连接成功：' . $label;
            if ($ver !== '') {
                $msg .= ' ' . $ver;
            }
            return array('ok' => true, 'msg' => $msg, 'snapshot' => $snap);
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '连接失败，请检查地址、密钥、IP 白名单与面板 API 是否已开启');
        } catch (Throwable $e) {
            return array('ok' => false, 'msg' => '连接失败，请稍后重试');
        }
    }

    /**
     * 仅根据 Config 组装快照（异常回落用；不发起面板 HTTP）
     *
     * @param string $errorMsg
     * @return array
     */
    public static function configOnlySnapshot($errorMsg = '')
    {
        $enabled = self::isEnabled();
        $provider = self::normalizeProvider(Config::get('panelmonitor_provider', ''));
        $url = trim((string) Config::get('panelmonitor_baseurl', ''));
        $key = trim((string) Config::get('panelmonitor_apikey', ''));
        $snap = self::applyConfigFlags(self::emptySnapshot(), $enabled, $provider, $url, $key);
        $snap['ok'] = false;
        if ($errorMsg !== '') {
            $snap['error'] = (string) $errorMsg;
        } elseif (!$enabled) {
            $snap['error'] = '未启用服务器监控';
        } elseif ($provider === self::PROVIDER_NONE) {
            $snap['error'] = '请选择面板类型';
        } elseif ($url === '' || $key === '') {
            $snap['error'] = '请先在系统设置中填写面板地址与接口密钥，并点击保存';
        }
        $snap['fetchedat'] = date('Y-m-d H:i:s');
        return $snap;
    }

    /**
     * @param string $raw
     * @return string
     */
    public static function normalizeProvider($raw)
    {
        $raw = (string) $raw;
        // 去掉 BOM / 零宽字符，避免库内看似 onepanel 却匹配失败（E192）
        $raw = preg_replace('/^\xEF\xBB\xBF/u', '', $raw);
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw);
        $raw = strtolower(trim($raw));
        $raw = preg_replace('/\s+/', '', $raw);
        if ($raw === 'baota' || $raw === 'bt') {
            return self::PROVIDER_BAOTA;
        }
        if ($raw === 'onepanel' || $raw === '1panel' || $raw === 'one' || $raw === 'ep'
            || $raw === '1p' || $raw === 'ipanel') {
            return self::PROVIDER_ONEPANEL;
        }
        return self::PROVIDER_NONE;
    }

    /**
     * @param string $provider
     * @return string
     */
    public static function providerLabel($provider)
    {
        if ($provider === self::PROVIDER_BAOTA) {
            return '宝塔面板';
        }
        if ($provider === self::PROVIDER_ONEPANEL) {
            return '1Panel';
        }
        return '';
    }

    /**
     * 宝塔：优先轻量 GetSystemTotal + GetNetWorkApi（避免 GetAllInfo 过重拖垮控制台）
     * @see https://docs.bt.cn/api/system/GetSystemTotal
     * @see https://docs.bt.cn/api/system/GetNetWork
     *
     * @param string $baseUrl
     * @param string $apiKey
     * @return array
     */
    private static function fetchBaota($baseUrl, $apiKey)
    {
        $snap = self::emptySnapshot();
        $snap['ok'] = true;

        $total = self::baotaPost($baseUrl, $apiKey, 'GetSystemTotal');
        if (!is_array($total)) {
            throw new Exception('invalid response');
        }
        if (isset($total['status']) && $total['status'] === false) {
            throw new Exception('api rejected');
        }

        if (isset($total['version'])) {
            $snap['panelversion'] = self::safeStr($total['version']);
        }
        if (isset($total['system'])) {
            $snap['system'] = self::safeStr($total['system']);
        }
        if (isset($total['time'])) {
            $snap['uptime'] = self::safeStr($total['time']);
        }
        if (isset($total['cpuRealUsed'])) {
            $snap['cpu'] = self::safeFloat($total['cpuRealUsed'], 1);
        }
        if (isset($total['cpuNum'])) {
            $snap['cpucores'] = (int) $total['cpuNum'];
        }
        if (isset($total['memTotal'])) {
            $snap['memtotal'] = (int) $total['memTotal'];
        }
        if (isset($total['memRealUsed'])) {
            $snap['memused'] = (int) $total['memRealUsed'];
        }
        if ($snap['memtotal'] !== null && $snap['memtotal'] > 0 && $snap['memused'] !== null) {
            $snap['mempercent'] = self::safeFloat($snap['memused'] * 100 / $snap['memtotal'], 1);
        }

        try {
            $net = self::baotaPost($baseUrl, $apiKey, 'GetNetWorkApi');
            if (!is_array($net)) {
                $net = self::baotaPost($baseUrl, $apiKey, 'GetNetWork');
            }
            if (is_array($net)) {
                self::mergeBaotaRuntime($snap, $net);
            }
        } catch (Exception $e) {
            // 系统总量已够；网络/负载可选
        }

        return $snap;
    }

    /**
     * @param array $snap
     * @param array $data
     * @return void
     */
    private static function mergeBaotaRuntime(array &$snap, array $data)
    {
        if ($snap['cpu'] === null && isset($data['cpu']) && is_array($data['cpu']) && isset($data['cpu'][0])) {
            $snap['cpu'] = self::safeFloat($data['cpu'][0], 1);
        }
        if ($snap['cpucores'] === null && isset($data['cpu']) && is_array($data['cpu']) && isset($data['cpu'][1])) {
            $snap['cpucores'] = (int) $data['cpu'][1];
        }

        $load = null;
        if (isset($data['load']) && is_array($data['load'])) {
            $load = $data['load'];
        } elseif (isset($data['load_average']) && is_array($data['load_average'])) {
            $load = $data['load_average'];
        }
        if (is_array($load)) {
            if (isset($load['one'])) {
                $snap['load1'] = self::safeFloat($load['one'], 2);
            }
            if (isset($load['five'])) {
                $snap['load5'] = self::safeFloat($load['five'], 2);
            }
            if (isset($load['fifteen'])) {
                $snap['load15'] = self::safeFloat($load['fifteen'], 2);
            }
        }

        if (isset($data['up'])) {
            $snap['netup'] = self::safeFloat($data['up'], 1);
        }
        if (isset($data['down'])) {
            $snap['netdown'] = self::safeFloat($data['down'], 1);
        }
        if (($snap['netup'] === null || $snap['netdown'] === null)
            && isset($data['network']) && is_array($data['network'])
        ) {
            // network 可能是汇总对象，也可能是按网卡字典
            $net = $data['network'];
            if (isset($net['up']) || isset($net['down'])) {
                if ($snap['netup'] === null && isset($net['up'])) {
                    $snap['netup'] = self::safeFloat($net['up'], 1);
                }
                if ($snap['netdown'] === null && isset($net['down'])) {
                    $snap['netdown'] = self::safeFloat($net['down'], 1);
                }
            } else {
                $upSum = 0.0;
                $downSum = 0.0;
                $has = false;
                foreach ($net as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if (isset($row['up'])) {
                        $upSum += (float) $row['up'];
                        $has = true;
                    }
                    if (isset($row['down'])) {
                        $downSum += (float) $row['down'];
                        $has = true;
                    }
                }
                if ($has) {
                    if ($snap['netup'] === null) {
                        $snap['netup'] = self::safeFloat($upSum, 1);
                    }
                    if ($snap['netdown'] === null) {
                        $snap['netdown'] = self::safeFloat($downSum, 1);
                    }
                }
            }
        }

        if ($snap['system'] === '' && isset($data['system'])) {
            $snap['system'] = self::safeStr($data['system']);
        }
        if ($snap['uptime'] === '' && isset($data['time'])) {
            $snap['uptime'] = self::safeStr($data['time']);
        }
    }

    /**
     * @param string $baseUrl
     * @param string $apiKey
     * @param string $action
     * @return array
     */
    private static function baotaPost($baseUrl, $apiKey, $action)
    {
        $endpoint = rtrim(self::normalizeBaseUrl($baseUrl), '/') . '/system';
        $requestTime = (string) time();
        $requestToken = md5($requestTime . '' . md5($apiKey));
        $body = http_build_query(array(
            'request_time'  => $requestTime,
            'request_token' => $requestToken,
            'action'        => $action,
        ));
        $raw = self::httpRequest('POST', $endpoint, $body, array(
            'Content-Type: application/x-www-form-urlencoded',
        ));
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new Exception('bad json');
        }
        return $json;
    }

    /**
     * 1Panel：MD5 / HMAC 双鉴权；多路径回退
     * @see https://1panel.cn/docs/v2/dev_manual/api_manual/
     *
     * @param string $baseUrl
     * @param string $apiKey
     * @return array
     */
    private static function fetchOnePanel($baseUrl, $apiKey)
    {
        $pathsCurrent = array(
            '/api/v2/dashboard/current/all/all',
            '/api/v2/dashboard/current/all/all/',
        );
        $pathsBase = array(
            '/api/v2/dashboard/base/all/all',
            '/api/v2/dashboard/base/os',
            '/api/v2/toolbox/device/base',
        );

        $current = null;
        $lastErr = null;
        foreach ($pathsCurrent as $path) {
            try {
                $current = self::onePanelRequest($baseUrl, $apiKey, 'GET', $path);
                break;
            } catch (Exception $e) {
                $lastErr = $e;
                try {
                    $current = self::onePanelRequest($baseUrl, $apiKey, 'POST', $path);
                    break;
                } catch (Exception $e2) {
                    $lastErr = $e2;
                }
            }
        }
        if (!is_array($current)) {
            throw ($lastErr instanceof Exception) ? $lastErr : new Exception('onepanel current failed');
        }

        $baseInfo = array();
        foreach ($pathsBase as $path) {
            try {
                $baseInfo = self::onePanelRequest($baseUrl, $apiKey, 'GET', $path);
                break;
            } catch (Exception $e) {
                try {
                    $baseInfo = self::onePanelRequest($baseUrl, $apiKey, 'POST', $path);
                    break;
                } catch (Exception $e2) {
                    $baseInfo = array();
                }
            }
        }

        $cur = self::unwrapOnePanelData($current);
        $base = self::unwrapOnePanelData($baseInfo);
        if (!is_array($cur) || $cur === array()) {
            throw new Exception('empty current');
        }

        $snap = self::emptySnapshot();
        $snap['ok'] = true;

        foreach (array('version', 'panelVersion', 'panel_version', 'appVersion') as $vk) {
            if ($snap['panelversion'] === '' && isset($base[$vk]) && (string) $base[$vk] !== '') {
                $snap['panelversion'] = self::safeStr($base[$vk]);
            }
            if ($snap['panelversion'] === '' && isset($cur[$vk]) && (string) $cur[$vk] !== '') {
                $snap['panelversion'] = self::safeStr($cur[$vk]);
            }
        }

        $platform = '';
        foreach (array('platform', 'os', 'platformName', 'distro') as $pk) {
            if ($platform === '' && isset($base[$pk])) {
                $platform = self::safeStr($base[$pk]);
            }
        }
        $kernel = '';
        foreach (array('kernel', 'kernelVersion', 'platformVersion') as $kk) {
            if ($kernel === '' && isset($base[$kk])) {
                $kernel = self::safeStr($base[$kk]);
            }
        }
        $arch = isset($base['kernelArch']) ? self::safeStr($base['kernelArch']) : (isset($base['arch']) ? self::safeStr($base['arch']) : '');
        $sysParts = array_filter(array($platform, $kernel, $arch));
        $snap['system'] = implode(' ', $sysParts);

        if (isset($base['timeSinceUptime'])) {
            $snap['uptime'] = self::safeStr($base['timeSinceUptime']);
        } elseif (isset($base['uptime'])) {
            $snap['uptime'] = self::formatUptime($base['uptime']);
        }

        if (isset($cur['cpuUsedPercent'])) {
            $snap['cpu'] = self::safeFloat($cur['cpuUsedPercent'], 1);
        } elseif (isset($cur['cpu']) && is_array($cur['cpu']) && isset($cur['cpu']['usedPercent'])) {
            $snap['cpu'] = self::safeFloat($cur['cpu']['usedPercent'], 1);
        } elseif (isset($cur['cpuTotal']) && is_array($cur['cpuTotal']) && isset($cur['cpuTotal'][0])) {
            $snap['cpu'] = self::safeFloat($cur['cpuTotal'][0], 1);
        }
        if (isset($cur['cpuCount'])) {
            $snap['cpucores'] = (int) $cur['cpuCount'];
        } elseif (isset($base['cpuCores'])) {
            $snap['cpucores'] = (int) $base['cpuCores'];
        } elseif (isset($cur['cpu']) && is_array($cur['cpu']) && isset($cur['cpu']['cores'])) {
            $snap['cpucores'] = (int) $cur['cpu']['cores'];
        }

        $load = null;
        if (isset($cur['loadUsage']) && is_array($cur['loadUsage'])) {
            $load = $cur['loadUsage'];
        } elseif (isset($cur['load']) && is_array($cur['load'])) {
            $load = $cur['load'];
        }
        if (is_array($load)) {
            foreach (array(
                array('load1', array('Load1', 'load1', 'one')),
                array('load5', array('Load5', 'load5', 'five')),
                array('load15', array('Load15', 'load15', 'fifteen')),
            ) as $pair) {
                $field = $pair[0];
                foreach ($pair[1] as $k) {
                    if (isset($load[$k])) {
                        $snap[$field] = self::safeFloat($load[$k], 2);
                        break;
                    }
                }
            }
        }

        $memTotal = null;
        $memUsed = null;
        if (isset($cur['memoryTotal'])) {
            $memTotal = (float) $cur['memoryTotal'];
        } elseif (isset($cur['memTotal'])) {
            $memTotal = (float) $cur['memTotal'];
        } elseif (isset($cur['memory']) && is_array($cur['memory']) && isset($cur['memory']['total'])) {
            $memTotal = (float) $cur['memory']['total'];
        }
        if (isset($cur['memoryUsed'])) {
            $memUsed = (float) $cur['memoryUsed'];
        } elseif (isset($cur['memUsed'])) {
            $memUsed = (float) $cur['memUsed'];
        } elseif (isset($cur['memory']) && is_array($cur['memory']) && isset($cur['memory']['used'])) {
            $memUsed = (float) $cur['memory']['used'];
        }
        if ($memTotal !== null) {
            if ($memTotal > 100000) {
                $snap['memtotal'] = (int) round($memTotal / 1024 / 1024);
                $snap['memused'] = $memUsed !== null ? (int) round($memUsed / 1024 / 1024) : null;
            } else {
                $snap['memtotal'] = (int) round($memTotal);
                $snap['memused'] = $memUsed !== null ? (int) round($memUsed) : null;
            }
        }
        if (isset($cur['memoryUsedPercent'])) {
            $snap['mempercent'] = self::safeFloat($cur['memoryUsedPercent'], 1);
        } elseif ($snap['memtotal'] !== null && $snap['memtotal'] > 0 && $snap['memused'] !== null) {
            $snap['mempercent'] = self::safeFloat($snap['memused'] * 100 / $snap['memtotal'], 1);
        }

        $up = null;
        $down = null;
        if (isset($cur['netBytesSent'])) {
            $up = (float) $cur['netBytesSent'];
        } elseif (isset($cur['shotNet']) && is_array($cur['shotNet']) && isset($cur['shotNet']['up'])) {
            $up = (float) $cur['shotNet']['up'];
        } elseif (isset($cur['net']) && is_array($cur['net'])) {
            if (isset($cur['net']['up'])) {
                $up = (float) $cur['net']['up'];
            } elseif (isset($cur['net']['bytesSent'])) {
                $up = (float) $cur['net']['bytesSent'];
            }
        }
        if (isset($cur['netBytesRecv'])) {
            $down = (float) $cur['netBytesRecv'];
        } elseif (isset($cur['shotNet']) && is_array($cur['shotNet']) && isset($cur['shotNet']['down'])) {
            $down = (float) $cur['shotNet']['down'];
        } elseif (isset($cur['net']) && is_array($cur['net'])) {
            if (isset($cur['net']['down'])) {
                $down = (float) $cur['net']['down'];
            } elseif (isset($cur['net']['bytesRecv'])) {
                $down = (float) $cur['net']['bytesRecv'];
            }
        }
        if ($up !== null) {
            $snap['netup'] = self::safeFloat(self::toKbps($up), 1);
        }
        if ($down !== null) {
            $snap['netdown'] = self::safeFloat(self::toKbps($down), 1);
        }

        return $snap;
    }

    /**
     * @param mixed $bytesOrKb
     * @return float
     */
    private static function toKbps($bytesOrKb)
    {
        $n = (float) $bytesOrKb;
        if ($n > 1024 * 50) {
            return $n / 1024.0;
        }
        return $n;
    }

    /**
     * @param mixed $seconds
     * @return string
     */
    private static function formatUptime($seconds)
    {
        $s = (int) $seconds;
        if ($s < 60) {
            return $s . '秒';
        }
        if ($s < 3600) {
            return (int) floor($s / 60) . '分钟';
        }
        if ($s < 86400) {
            return (int) floor($s / 3600) . '小时';
        }
        return (int) floor($s / 86400) . '天';
    }

    /**
     * @param array $resp
     * @return array
     */
    private static function unwrapOnePanelData($resp)
    {
        if (!is_array($resp)) {
            return array();
        }
        if (isset($resp['data']) && is_array($resp['data'])) {
            return $resp['data'];
        }
        return $resp;
    }

    /**
     * 先 MD5（兼容面广），再 HMAC-SHA256
     *
     * @param string $baseUrl
     * @param string $apiKey
     * @param string $method
     * @param string $path
     * @return array
     */
    private static function onePanelRequest($baseUrl, $apiKey, $method, $path)
    {
        $endpoint = rtrim(self::normalizeBaseUrl($baseUrl), '/') . $path;
        $ts = (string) time();
        $tokens = array(
            md5('1panel' . $apiKey . $ts),
        );
        if (function_exists('hash_hmac')) {
            $tokens[] = hash_hmac('sha256', '1panel:' . $ts, $apiKey);
        }

        $last = null;
        foreach ($tokens as $token) {
            try {
                $raw = self::httpRequest($method, $endpoint, '', array(
                    '1Panel-Token: ' . $token,
                    '1Panel-Timestamp: ' . $ts,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ));
                $json = json_decode($raw, true);
                if (!is_array($json)) {
                    throw new Exception('bad json');
                }
                if (isset($json['code'])) {
                    $code = (int) $json['code'];
                    // 成功常见 200；部分封装用 0/1
                    if ($code !== 200 && $code !== 0 && $code !== 1) {
                        if (!isset($json['data']) || !is_array($json['data'])) {
                            throw new Exception('api code ' . $code);
                        }
                    }
                }
                return $json;
            } catch (Exception $e) {
                $last = $e;
            }
        }
        throw ($last instanceof Exception) ? $last : new Exception('auth failed');
    }

    /**
     * @param string $url
     * @return true|string
     */
    public static function assertSafePanelUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '请填写面板地址';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '面板地址格式不正确';
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return '面板地址仅支持 http 或 https';
        }
        $host = strtolower((string) $parts['host']);
        if ($host === '' || strpos($host, '/') !== false) {
            return '面板地址主机名无效';
        }
        // 允许本机/内网面板（E186）；禁止云元数据与链路本地地址（E193）
        if (self::isBlockedPanelHost($host)) {
            return '面板地址不可用，请更换为面板入口地址';
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::isBlockedPanelIp($host)) {
                return '面板地址不可用，请更换为面板入口地址';
            }
            return true;
        }
        // 解析域名，拦截解析到链路本地/元数据网段的情况
        $ips = self::resolveHostIps($host);
        if ($ips === array()) {
            // 解析失败留给后续连接报错，避免误伤离线配置保存
            return true;
        }
        foreach ($ips as $ip) {
            if (self::isBlockedPanelIp($ip)) {
                return '面板地址不可用，请更换为面板入口地址';
            }
        }
        return true;
    }

    /**
     * 禁止的面板主机名（云元数据等）
     *
     * @param string $host
     * @return bool
     */
    private static function isBlockedPanelHost($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return true;
        }
        $blocked = array(
            'metadata.google.internal',
            'metadata.goog',
            'metadata',
            'instance-data',
            'kubernetes.default',
            'kubernetes.default.svc',
        );
        if (in_array($host, $blocked, true)) {
            return true;
        }
        if (strpos($host, 'metadata.') === 0) {
            return true;
        }
        return false;
    }

    /**
     * 禁止的目标 IP：链路本地 / 云元数据段（仍允许 127/8、RFC1918）
     *
     * @param string $ip
     * @return bool
     */
    private static function isBlockedPanelIp($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 169.254.0.0/16 link-local / 云元数据
            if (strpos($ip, '169.254.') === 0) {
                return true;
            }
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = @inet_pton($ip);
            if ($bin === false || strlen($bin) < 2) {
                return true;
            }
            $b0 = ord($bin[0]);
            $b1 = ord($bin[1]);
            // fe80::/10 link-local
            if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) {
                return true;
            }
            // fc00::/7 unique local — 允许（内网面板可能用 ULA）
            return false;
        }
        return true;
    }

    /**
     * @param string $host
     * @return string[]
     */
    private static function resolveHostIps($host)
    {
        $host = trim((string) $host);
        $out = array();
        if ($host === '') {
            return $out;
        }
        if (function_exists('dns_get_record')) {
            $a = @dns_get_record($host, DNS_A);
            if (is_array($a)) {
                foreach ($a as $row) {
                    if (!empty($row['ip'])) {
                        $out[] = (string) $row['ip'];
                    }
                }
            }
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $row) {
                    if (!empty($row['ipv6'])) {
                        $out[] = (string) $row['ipv6'];
                    }
                }
            }
        }
        if ($out === array() && function_exists('gethostbynamel')) {
            $list = @gethostbynamel($host);
            if (is_array($list)) {
                foreach ($list as $ip) {
                    $out[] = (string) $ip;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param string $url
     * @return string
     */
    private static function normalizeBaseUrl($url)
    {
        $url = trim((string) $url);
        $url = rtrim($url, '/');
        if ($url === '') {
            throw new Exception('empty url');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $ok = self::assertSafePanelUrl($url);
        if ($ok !== true) {
            throw new Exception($ok);
        }
        return $url;
    }

    /**
     * @param string $method
     * @param string $url
     * @param string $body
     * @param array  $headers
     * @return string
     */
    private static function httpRequest($method, $url, $body, array $headers)
    {
        $timeout = self::HTTP_TIMEOUT;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
            if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ApiNexus-PanelMonitor/' . (defined('VS_VERSION') ? VS_VERSION : '1'));
            $method = strtoupper($method);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } elseif ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($body !== '') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false || $code >= 400 || $code === 0) {
                throw new Exception($err !== '' ? $err : 'http ' . $code);
            }
            return (string) $raw;
        }

        $hdr = '';
        foreach ($headers as $h) {
            $hdr .= $h . "\r\n";
        }
        $opts = array(
            'http' => array(
                'method'        => strtoupper($method),
                'header'        => $hdr,
                'content'       => $body,
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ),
        );
        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new Exception('request failed');
        }
        return (string) $raw;
    }

    /**
     * @param mixed $v
     * @return string
     */
    private static function safeStr($v)
    {
        $s = trim((string) $v);
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
            $s = @mb_convert_encoding($s, 'UTF-8', 'UTF-8, GBK, GB2312, ISO-8859-1');
        }
        if (!is_string($s)) {
            return '';
        }
        // 去掉控制字符，避免破坏 JSON
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return is_string($s) ? $s : '';
    }

    /**
     * @param mixed $v
     * @param int   $digits
     * @return float|null
     */
    private static function safeFloat($v, $digits = 1)
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (float) $v;
        if (!is_finite($n)) {
            return null;
        }
        return round($n, (int) $digits);
    }

    /**
     * @param array $snap
     * @return array
     */
    private static function sanitizeSnapshot(array $snap)
    {
        foreach (array('error', 'provider', 'providerlabel', 'panelversion', 'system', 'uptime', 'fetchedat') as $k) {
            if (isset($snap[$k])) {
                $snap[$k] = self::safeStr($snap[$k]);
            }
        }
        foreach (array('cpu', 'load1', 'load5', 'load15', 'mempercent', 'netup', 'netdown') as $k) {
            if (array_key_exists($k, $snap) && $snap[$k] !== null) {
                $snap[$k] = self::safeFloat($snap[$k], $k === 'load1' || $k === 'load5' || $k === 'load15' ? 2 : 1);
            }
        }
        return $snap;
    }
}
