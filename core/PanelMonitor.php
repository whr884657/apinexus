<?php
/**
 * 文件：core/PanelMonitor.php
 * 作用：对接宝塔 / 1Panel 面板接口，汇总控制台「服务器」卡片数据
 */

class PanelMonitor
{
    const PROVIDER_NONE = '';
    const PROVIDER_BAOTA = 'baota';
    const PROVIDER_ONEPANEL = 'onepanel';

    const CACHE_TTL = 8;

    /**
     * @return array
     */
    public static function emptySnapshot()
    {
        return array(
            'enabled'      => false,
            'configured'   => false,
            'ok'           => false,
            'error'        => '',
            'provider'     => '',
            'providerlabel'=> '',
            'panelversion' => '',
            'system'       => '',
            'uptime'       => '',
            'cpu'          => null,
            'cpucores'     => null,
            'load1'        => null,
            'load5'        => null,
            'load15'       => null,
            'memtotal'     => null,
            'memused'      => null,
            'mempercent'   => null,
            'netup'        => null,
            'netdown'      => null,
            'fetchedat'    => '',
        );
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
        $enabled = Config::get('panelmonitor_enabled', '0') === '1';
        $provider = self::normalizeProvider(Config::get('panelmonitor_provider', ''));
        $base['enabled'] = $enabled;
        $base['provider'] = $provider;
        $base['providerlabel'] = self::providerLabel($provider);

        if (!$enabled || $provider === self::PROVIDER_NONE) {
            $base['error'] = '未启用服务器监控';
            return $base;
        }

        $url = trim((string) Config::get('panelmonitor_baseurl', ''));
        $key = trim((string) Config::get('panelmonitor_apikey', ''));
        $base['configured'] = ($url !== '' && $key !== '');
        if (!$base['configured']) {
            $base['error'] = '请先在系统设置中填写面板地址与接口密钥';
            return $base;
        }

        $cacheKey = 'panel_monitor_snap_' . md5($provider . '|' . $url);
        if (!$refresh && class_exists('RedisCache')) {
            $hit = RedisCache::get($cacheKey);
            if (is_array($hit) && isset($hit['ok'])) {
                return $hit;
            }
        }

        try {
            if ($provider === self::PROVIDER_BAOTA) {
                $snap = self::fetchBaota($url, $key);
            } else {
                $snap = self::fetchOnePanel($url, $key);
            }
            $snap['enabled'] = true;
            $snap['configured'] = true;
            $snap['provider'] = $provider;
            $snap['providerlabel'] = self::providerLabel($provider);
            $snap['fetchedat'] = date('Y-m-d H:i:s');
            if (class_exists('RedisCache')) {
                RedisCache::set($cacheKey, $snap, self::CACHE_TTL);
            }
            return $snap;
        } catch (Exception $e) {
            $base['error'] = '面板连接失败，请检查地址、密钥与白名单';
            return $base;
        }
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
            return array('ok' => false, 'msg' => '连接失败，请检查地址、密钥、IP 白名单与 HTTPS 证书');
        }
    }

    /**
     * @param string $raw
     * @return string
     */
    public static function normalizeProvider($raw)
    {
        $raw = strtolower(trim((string) $raw));
        if ($raw === 'baota' || $raw === 'bt') {
            return self::PROVIDER_BAOTA;
        }
        if ($raw === 'onepanel' || $raw === '1panel' || $raw === 'one') {
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
     * @param string $baseUrl
     * @param string $apiKey
     * @return array
     */
    private static function fetchBaota($baseUrl, $apiKey)
    {
        // GetAllInfo 聚合 CPU / 负载 / 内存 / 网络 / 系统 / 面板版本
        // @see https://docs.bt.cn/api/system/GetAllInfo
        $data = self::baotaPost($baseUrl, $apiKey, 'GetAllInfo');
        if (!is_array($data)) {
            throw new Exception('invalid response');
        }
        // 部分版本失败时返回 status/msg
        if (isset($data['status']) && $data['status'] === false) {
            throw new Exception('api rejected');
        }

        $snap = self::emptySnapshot();
        $snap['ok'] = true;

        if (isset($data['version'])) {
            $snap['panelversion'] = (string) $data['version'];
        }
        if (isset($data['system'])) {
            $snap['system'] = (string) $data['system'];
        }
        if (isset($data['time'])) {
            $snap['uptime'] = (string) $data['time'];
        }

        $cpu = isset($data['cpu']) ? $data['cpu'] : null;
        if (is_array($cpu)) {
            if (isset($cpu[0])) {
                $snap['cpu'] = round((float) $cpu[0], 1);
            }
            if (isset($cpu[1])) {
                $snap['cpucores'] = (int) $cpu[1];
            }
        }

        $load = null;
        if (isset($data['load_average']) && is_array($data['load_average'])) {
            $load = $data['load_average'];
        } elseif (isset($data['load']) && is_array($data['load'])) {
            $load = $data['load'];
        }
        if (is_array($load)) {
            if (isset($load['one'])) {
                $snap['load1'] = round((float) $load['one'], 2);
            }
            if (isset($load['five'])) {
                $snap['load5'] = round((float) $load['five'], 2);
            }
            if (isset($load['fifteen'])) {
                $snap['load15'] = round((float) $load['fifteen'], 2);
            }
        }

        $mem = isset($data['mem']) && is_array($data['mem']) ? $data['mem'] : $data;
        if (isset($mem['memTotal'])) {
            $snap['memtotal'] = (int) $mem['memTotal'];
        }
        if (isset($mem['memRealUsed'])) {
            $snap['memused'] = (int) $mem['memRealUsed'];
        } elseif (isset($mem['memUsed'])) {
            $snap['memused'] = (int) $mem['memUsed'];
        }
        if ($snap['memtotal'] > 0 && $snap['memused'] !== null) {
            $snap['mempercent'] = round($snap['memused'] * 100 / $snap['memtotal'], 1);
        }

        if (isset($data['up'])) {
            $snap['netup'] = round((float) $data['up'], 1);
        }
        if (isset($data['down'])) {
            $snap['netdown'] = round((float) $data['down'], 1);
        }
        if (($snap['netup'] === null || $snap['netdown'] === null)
            && isset($data['network']) && is_array($data['network'])
        ) {
            // GetAllInfo 的 network 有时是汇总对象
            $net = $data['network'];
            if (isset($net['up']) && $snap['netup'] === null) {
                $snap['netup'] = round((float) $net['up'], 1);
            }
            if (isset($net['down']) && $snap['netdown'] === null) {
                $snap['netdown'] = round((float) $net['down'], 1);
            }
        }

        // 若缺版本/系统，补调 GetSystemTotal
        if ($snap['panelversion'] === '' || $snap['system'] === '') {
            try {
                $total = self::baotaPost($baseUrl, $apiKey, 'GetSystemTotal');
                if (is_array($total)) {
                    if ($snap['panelversion'] === '' && isset($total['version'])) {
                        $snap['panelversion'] = (string) $total['version'];
                    }
                    if ($snap['system'] === '' && isset($total['system'])) {
                        $snap['system'] = (string) $total['system'];
                    }
                    if ($snap['uptime'] === '' && isset($total['time'])) {
                        $snap['uptime'] = (string) $total['time'];
                    }
                    if ($snap['cpu'] === null && isset($total['cpuRealUsed'])) {
                        $snap['cpu'] = round((float) $total['cpuRealUsed'], 1);
                    }
                    if ($snap['cpucores'] === null && isset($total['cpuNum'])) {
                        $snap['cpucores'] = (int) $total['cpuNum'];
                    }
                    if ($snap['memtotal'] === null && isset($total['memTotal'])) {
                        $snap['memtotal'] = (int) $total['memTotal'];
                    }
                    if ($snap['memused'] === null && isset($total['memRealUsed'])) {
                        $snap['memused'] = (int) $total['memRealUsed'];
                    }
                    if ($snap['memtotal'] > 0 && $snap['memused'] !== null && $snap['mempercent'] === null) {
                        $snap['mempercent'] = round($snap['memused'] * 100 / $snap['memtotal'], 1);
                    }
                }
            } catch (Exception $e) {
                // 主数据已够用则忽略补调失败
            }
        }

        return $snap;
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
     * @param string $baseUrl
     * @param string $apiKey
     * @return array
     */
    private static function fetchOnePanel($baseUrl, $apiKey)
    {
        // @see https://1panel.cn/docs/v2/dev_manual/api_manual/
        // 当前指标：/api/v2/dashboard/current/{io}/{net}
        // 基础信息：/api/v2/dashboard/base/{io}/{net}
        $current = self::onePanelGet($baseUrl, $apiKey, '/api/v2/dashboard/current/all/all');
        $baseInfo = array();
        try {
            $baseInfo = self::onePanelGet($baseUrl, $apiKey, '/api/v2/dashboard/base/all/all');
        } catch (Exception $e) {
            try {
                $baseInfo = self::onePanelGet($baseUrl, $apiKey, '/api/v2/dashboard/base/os');
            } catch (Exception $e2) {
                $baseInfo = array();
            }
        }

        $cur = self::unwrapOnePanelData($current);
        $base = self::unwrapOnePanelData($baseInfo);

        $snap = self::emptySnapshot();
        $snap['ok'] = true;

        // 面板版本 / 系统
        foreach (array('version', 'panelVersion', 'panel_version', 'appVersion') as $vk) {
            if ($snap['panelversion'] === '' && isset($base[$vk]) && (string) $base[$vk] !== '') {
                $snap['panelversion'] = (string) $base[$vk];
            }
            if ($snap['panelversion'] === '' && isset($cur[$vk]) && (string) $cur[$vk] !== '') {
                $snap['panelversion'] = (string) $cur[$vk];
            }
        }

        $platform = '';
        foreach (array('platform', 'os', 'platformName', 'distro') as $pk) {
            if ($platform === '' && isset($base[$pk])) {
                $platform = (string) $base[$pk];
            }
        }
        $kernel = '';
        foreach (array('kernel', 'kernelVersion', 'platformVersion') as $kk) {
            if ($kernel === '' && isset($base[$kk])) {
                $kernel = (string) $base[$kk];
            }
        }
        $arch = isset($base['kernelArch']) ? (string) $base['kernelArch'] : (isset($base['arch']) ? (string) $base['arch'] : '');
        $sysParts = array_filter(array($platform, $kernel, $arch));
        $snap['system'] = implode(' ', $sysParts);

        if (isset($base['timeSinceUptime'])) {
            $snap['uptime'] = (string) $base['timeSinceUptime'];
        } elseif (isset($base['uptime'])) {
            $snap['uptime'] = self::formatUptime($base['uptime']);
        }

        // CPU
        if (isset($cur['cpuUsedPercent'])) {
            $snap['cpu'] = round((float) $cur['cpuUsedPercent'], 1);
        } elseif (isset($cur['cpu']) && is_array($cur['cpu']) && isset($cur['cpu']['usedPercent'])) {
            $snap['cpu'] = round((float) $cur['cpu']['usedPercent'], 1);
        } elseif (isset($cur['cpuTotal']) && is_array($cur['cpuTotal']) && isset($cur['cpuTotal'][0])) {
            $snap['cpu'] = round((float) $cur['cpuTotal'][0], 1);
        }
        if (isset($cur['cpuCount'])) {
            $snap['cpucores'] = (int) $cur['cpuCount'];
        } elseif (isset($base['cpuCores'])) {
            $snap['cpucores'] = (int) $base['cpuCores'];
        } elseif (isset($cur['cpu']) && is_array($cur['cpu']) && isset($cur['cpu']['cores'])) {
            $snap['cpucores'] = (int) $cur['cpu']['cores'];
        }

        // Load
        $load = null;
        if (isset($cur['load']) && is_array($cur['load'])) {
            $load = $cur['load'];
        } elseif (isset($cur['loadUsage']) && is_array($cur['loadUsage'])) {
            $load = $cur['loadUsage'];
        }
        if (is_array($load)) {
            if (isset($load['Load1'])) {
                $snap['load1'] = round((float) $load['Load1'], 2);
            } elseif (isset($load['load1'])) {
                $snap['load1'] = round((float) $load['load1'], 2);
            } elseif (isset($load['one'])) {
                $snap['load1'] = round((float) $load['one'], 2);
            }
            if (isset($load['Load5'])) {
                $snap['load5'] = round((float) $load['Load5'], 2);
            } elseif (isset($load['load5'])) {
                $snap['load5'] = round((float) $load['load5'], 2);
            } elseif (isset($load['five'])) {
                $snap['load5'] = round((float) $load['five'], 2);
            }
            if (isset($load['Load15'])) {
                $snap['load15'] = round((float) $load['Load15'], 2);
            } elseif (isset($load['load15'])) {
                $snap['load15'] = round((float) $load['load15'], 2);
            } elseif (isset($load['fifteen'])) {
                $snap['load15'] = round((float) $load['fifteen'], 2);
            }
        }

        // Memory（1Panel 常见字节）
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
            // > 100000 视为字节
            if ($memTotal > 100000) {
                $snap['memtotal'] = (int) round($memTotal / 1024 / 1024);
                $snap['memused'] = $memUsed !== null ? (int) round($memUsed / 1024 / 1024) : null;
            } else {
                $snap['memtotal'] = (int) round($memTotal);
                $snap['memused'] = $memUsed !== null ? (int) round($memUsed) : null;
            }
        }
        if (isset($cur['memoryUsedPercent'])) {
            $snap['mempercent'] = round((float) $cur['memoryUsedPercent'], 1);
        } elseif ($snap['memtotal'] > 0 && $snap['memused'] !== null) {
            $snap['mempercent'] = round($snap['memused'] * 100 / $snap['memtotal'], 1);
        }

        // Network：字节/秒 → KB/s
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
            $snap['netup'] = round(self::toKbps($up), 1);
        }
        if ($down !== null) {
            $snap['netdown'] = round(self::toKbps($down), 1);
        }

        return $snap;
    }

    /**
     * @param mixed $bytesOrKb
     * @return float KB/s
     */
    private static function toKbps($bytesOrKb)
    {
        $n = (float) $bytesOrKb;
        // 若数值很大，按字节/秒换算
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
     * @param string $baseUrl
     * @param string $apiKey
     * @param string $path
     * @return array
     */
    private static function onePanelGet($baseUrl, $apiKey, $path)
    {
        $endpoint = rtrim(self::normalizeBaseUrl($baseUrl), '/') . $path;
        $ts = (string) time();
        // 优先 HMAC-SHA256；兼容旧 MD5
        if (function_exists('hash_hmac')) {
            $token = hash_hmac('sha256', '1panel:' . $ts, $apiKey);
        } else {
            $token = md5('1panel' . $apiKey . $ts);
        }
        $raw = self::httpRequest('GET', $endpoint, '', array(
            '1Panel-Token: ' . $token,
            '1Panel-Timestamp: ' . $ts,
            'Accept: application/json',
        ));
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new Exception('bad json');
        }
        // 业务失败码
        if (isset($json['code']) && (int) $json['code'] !== 200 && (int) $json['code'] !== 0 && (int) $json['code'] !== 1) {
            // 1Panel 成功常见 code=200
            if (!isset($json['data'])) {
                throw new Exception('api error');
            }
        }
        return $json;
    }

    /**
     * 设置页校验：仅允许 http(s)；面板常在本机/内网，不拦截私网地址
     *
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
        return true;
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
        $verifySsl = false;
        $timeout = 8;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            // 面板多为本机/自签证书：固定不校验对端证书（不再提供配置开关）
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ApiNexus-PanelMonitor/' . (defined('VS_VERSION') ? VS_VERSION : '1'));
            if (strtoupper($method) === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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
                'method'  => strtoupper($method),
                'header'  => $hdr,
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer'      => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ),
        );
        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new Exception('request failed');
        }
        return (string) $raw;
    }
}
