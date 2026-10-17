<?php
/**
 * 文件：core/SystemApiKey.php
 * 作用：系统级密钥 + 开关闸（sysapion）+ 可配 QPM/QPS（sysapirq）；Bearer / Header / POST·JSON / Query 三态鉴权（E428）
 */

class SystemApiKey
{
    const CONFIG_KEY = 'system_api_key';
    /** @deprecated 仅迁移读取，业务禁止再写 */
    const LEGACY_CONFIG_KEY = 'apilog_cron_key';

    /** 系统级 API 开关总闸（单键 JSON；字段=接口短名，值 0/1） */
    const GATES_KEY = 'sysapion';

    /**
     * 系统级 API 限流（单键 JSON；按接口短名配 qpm/qps；0=不限）
     * 结构：{"apis":{"cardkey":{"qpm":0,"qps":0},...}}
     */
    const RATE_KEY = 'sysapirq';

    /** QPM 上限（管理员可填最大值） */
    const QPM_MAX = 1000000;
    /** QPS 上限 */
    const QPS_MAX = 10000;

    /** 系统级 API 请求体上限（字节）；防未鉴权大包打爆内存（E388） */
    const MAX_BODY_BYTES = 65536;

    /** JSON 最大嵌套深度 */
    const JSON_MAX_DEPTH = 16;

    /** @var array|null 本请求内 gates 缓存 */
    private static $gatesCache = null;

    /** @var array|null 本请求内 sysapirq 缓存 */
    private static $ratesCache = null;

    /** @var string|null 缓存 php://input，避免鉴权与业务各读一次导致第二次为空 */
    private static $rawBody = null;
    /** @var bool */
    private static $rawBodyLoaded = false;

    /**
     * 读取并缓存请求体（JSON 鉴权与业务共用；有长度上限）
     *
     * @return string
     */
    public static function requestBody()
    {
        if (!self::$rawBodyLoaded) {
            self::$rawBodyLoaded = true;
            self::$rawBody = '';
            $declared = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : -1;
            if ($declared > self::MAX_BODY_BYTES) {
                self::fail(SystemApiError::BODY_TOO_LARGE, '请求体过大');
            }
            $fh = @fopen('php://input', 'rb');
            if ($fh === false) {
                return self::$rawBody;
            }
            $raw = stream_get_contents($fh, self::MAX_BODY_BYTES + 1);
            fclose($fh);
            if (!is_string($raw)) {
                $raw = '';
            }
            if (strlen($raw) > self::MAX_BODY_BYTES) {
                self::fail(SystemApiError::BODY_TOO_LARGE, '请求体过大');
            }
            self::$rawBody = $raw;
        }
        return self::$rawBody;
    }

    /**
     * 合并 GET / POST / JSON 业务参数；拒绝 JSONP；同名参数冲突即拒（防 HPP / 检测面绕过，E397）
     *
     * @return array
     */
    public static function mergeInput()
    {
        $input = array_merge($_GET, $_POST);
        self::rejectJsonpParams($input);
        $raw = self::requestBody();
        if ($raw === '') {
            return $input;
        }
        $json = json_decode($raw, true, self::JSON_MAX_DEPTH);
        if (!is_array($json)) {
            return $input;
        }
        self::rejectJsonpParams($json);
        foreach ($json as $k => $v) {
            if (!array_key_exists($k, $input)) {
                continue;
            }
            // 同名且语义冲突：禁止 JSON 静默覆盖 Query/POST（审计 M-1）
            if (!self::paramValuesEqual($input[$k], $v)) {
                $safe = preg_replace('/[^A-Za-z0-9_]/', '', (string) $k);
                if ($safe === '') {
                    $safe = 'param';
                }
                self::fail(SystemApiError::PARAM_CONFLICT, '参数重复且冲突：' . $safe);
            }
        }
        return array_merge($input, $json);
    }

    /**
     * 比较业务参数是否等价（标量转字符串；数组按 JSON 规范化）
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private static function paramValuesEqual($a, $b)
    {
        if (is_array($a) || is_array($b)) {
            if (!is_array($a) || !is_array($b)) {
                return false;
            }
            return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                === json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) $a === (string) $b;
    }

    /**
     * 请求级复位 php://input 缓存（常驻进程 / 多请求复用时防串读，E397）
     *
     * @return void
     */
    public static function resetRequestBodyCache()
    {
        self::$rawBody = null;
        self::$rawBodyLoaded = false;
    }

    /**
     * @param array $params
     * @return void
     */
    private static function rejectJsonpParams(array $params)
    {
        if (!class_exists('JsonpGuard')) {
            return;
        }
        foreach ($params as $k => $v) {
            if (!JsonpGuard::isJsonpParamName((string) $k)) {
                continue;
            }
            if ($v === null || $v === '' || $v === array()) {
                continue;
            }
            self::fail(SystemApiError::JSONP_FORBIDDEN, '不支持 JSONP 参数');
        }
    }

    /**
     * 一次性：旧 apilog_cron_key → system_api_key，并删除旧键
     *
     * @return void
     */
    public static function migrateFromLegacy()
    {
        try {
            $all = Config::all();
            $cur = isset($all[self::CONFIG_KEY]) ? trim((string) $all[self::CONFIG_KEY]) : '';
            $legacy = isset($all[self::LEGACY_CONFIG_KEY]) ? trim((string) $all[self::LEGACY_CONFIG_KEY]) : '';
            if ($cur === '' && $legacy !== '') {
                Config::set(self::CONFIG_KEY, $legacy);
                $cur = $legacy;
            }
            if (!array_key_exists(self::CONFIG_KEY, $all) && $cur === '') {
                Config::set(self::CONFIG_KEY, '');
            }
            if (array_key_exists(self::LEGACY_CONFIG_KEY, $all)) {
                $pdo = Database::connect();
                $table = Database::table('config');
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                $stmt->execute(array(self::LEGACY_CONFIG_KEY));
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * @return string
     */
    public static function get()
    {
        try {
            return trim((string) Config::get(self::CONFIG_KEY, ''));
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @return string 64 位 hex
     */
    public static function generate()
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                return bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // 再试
            }
        }
        throw new Exception('无法生成系统密钥');
    }

    /**
     * @param string $key
     * @return void
     */
    public static function set($key)
    {
        Config::set(self::CONFIG_KEY, trim((string) $key));
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function validate($key)
    {
        $expected = self::get();
        $key = trim((string) $key);
        if ($expected === '' || $key === '') {
            return false;
        }
        return hash_equals($expected, $key);
    }

    /**
     * 从请求提取密钥（优先级：Bearer → Header Api-Key → POST → JSON body → Query）
     * 三种对外传钥方式：Bearer / Header / Query（POST·JSON 为 body 通道，E428）
     * Query 易进 access_log / Referer：推荐 Header/Bearer；计划任务无 Header 时可用 Query
     *
     * @return string
     */
    public static function extractFromRequest()
    {
        $auth = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if ($auth !== '' && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
            return trim($m[1]);
        }

        $headerCandidates = array(
            'HTTP_X_API_KEY',
            'HTTP_API_KEY',
            'HTTP_APIKEY',
        );
        foreach ($headerCandidates as $h) {
            if (isset($_SERVER[$h]) && trim((string) $_SERVER[$h]) !== '') {
                return trim((string) $_SERVER[$h]);
            }
        }

        if (isset($_POST['key']) && trim((string) $_POST['key']) !== '') {
            return trim((string) $_POST['key']);
        }
        if (isset($_POST['apikey']) && trim((string) $_POST['apikey']) !== '') {
            return trim((string) $_POST['apikey']);
        }

        $raw = self::requestBody();
        if ($raw !== '') {
            $json = json_decode($raw, true, self::JSON_MAX_DEPTH);
            if (is_array($json)) {
                if (isset($json['key']) && trim((string) $json['key']) !== '') {
                    return trim((string) $json['key']);
                }
                if (isset($json['apikey']) && trim((string) $json['apikey']) !== '') {
                    return trim((string) $json['apikey']);
                }
            }
        }

        if (isset($_GET['key']) && trim((string) $_GET['key']) !== '') {
            return trim((string) $_GET['key']);
        }
        if (isset($_GET['apikey']) && trim((string) $_GET['apikey']) !== '') {
            return trim((string) $_GET['apikey']);
        }

        return '';
    }

    /**
     * 鉴权失败直接输出 JSON 并结束
     * 失败限流仅作用于错误密钥；正确密钥永不因失败桶被 429（E397）
     *
     * @return void
     */
    public static function requireAuthorized()
    {
        // 每请求入口复位 body 缓存（PHP-FPM 无害；常驻进程防串读）
        self::resetRequestBodyCache();

        $ip = AuthSecurity::clientIp();
        $failBucket = 'syskey:fail:' . $ip;

        $key = self::extractFromRequest();
        if (self::validate($key)) {
            return;
        }

        // 仅失败路径：先判是否已刷满，再记一次
        $failCount = RateLimitStore::countHits($failBucket, 60);
        if ($failCount >= 30) {
            self::fail(SystemApiError::RATE_LIMIT, '请求过于频繁');
        }
        RateLimitStore::recordHit($failBucket, 60);
        self::fail(SystemApiError::UNAUTH, '无权访问');
    }

    /**
     * 开关闸 + 鉴权：闸关与鉴权失败统一 UNAUTH（防密钥有效性探测，E412）
     * 入口须用本方法，禁止「先鉴权再 API_OFF」暴露侧信道
     *
     * @param string $gateId sysapion 短名
     * @return void
     */
    public static function requireAuthorizedGate($gateId)
    {
        $gateId = strtolower(trim((string) $gateId));
        if ($gateId === '' || !self::isGateOn($gateId)) {
            self::fail(SystemApiError::UNAUTH, '无权访问');
        }
        self::requireAuthorized();
        // 鉴权通过后执行管理员配置的单接口 QPS/QPM（0=不限；E419）
        self::enforceConfiguredLimit($gateId);
    }

    /**
     * @deprecated 业务入口禁止再手写分桶；改用 sysapirq + enforceConfiguredLimit（经 requireAuthorizedGate）
     *
     * @param string $bucketSuffix
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @return void
     */
    public static function requireRateLimit($bucketSuffix, $windowSeconds, $maxAttempts)
    {
        $ip = AuthSecurity::clientIp();
        $bucket = 'syskey:' . $bucketSuffix . ':' . $ip;
        if (!RateLimitStore::allow($bucket, $windowSeconds, $maxAttempts, true)) {
            self::fail(SystemApiError::RATE_LIMIT, '请求过于频繁');
        }
    }

    /**
     * 默认限流 JSON（各接口 qpm/qps 均为 0=不限）
     *
     * @return string
     */
    public static function defaultRatesJson()
    {
        $apis = array();
        foreach (self::knownGateIds() as $id) {
            $apis[$id] = array('qpm' => 0, 'qps' => 0);
        }
        return json_encode(array('apis' => $apis), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 钳制非负整数限额
     *
     * @param mixed $v
     * @param int   $max
     * @return int
     */
    public static function clampRateValue($v, $max)
    {
        $n = (int) $v;
        if ($n < 0) {
            $n = 0;
        }
        $max = (int) $max;
        if ($max > 0 && $n > $max) {
            $n = $max;
        }
        return $n;
    }

    /**
     * 解析 sysapirq（缺字段补 0；非法 JSON → 全 0）
     *
     * @param string $raw
     * @return array{apis:array<string,array{qpm:int,qps:int}>}
     */
    public static function decodeRates($raw)
    {
        $apis = array();
        foreach (self::knownGateIds() as $id) {
            $apis[$id] = array('qpm' => 0, 'qps' => 0);
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array('apis' => $apis);
        }
        $obj = json_decode($raw, true, self::JSON_MAX_DEPTH);
        if (!is_array($obj)) {
            return array('apis' => $apis);
        }
        $src = isset($obj['apis']) && is_array($obj['apis']) ? $obj['apis'] : $obj;
        foreach (self::knownGateIds() as $id) {
            if (!isset($src[$id]) || !is_array($src[$id])) {
                continue;
            }
            $row = $src[$id];
            $apis[$id] = array(
                'qpm' => self::clampRateValue(isset($row['qpm']) ? $row['qpm'] : 0, self::QPM_MAX),
                'qps' => self::clampRateValue(isset($row['qps']) ? $row['qps'] : 0, self::QPS_MAX),
            );
        }
        return array('apis' => $apis);
    }

    /**
     * @return array{apis:array<string,array{qpm:int,qps:int}>}
     */
    public static function rates()
    {
        if (is_array(self::$ratesCache)) {
            return self::$ratesCache;
        }
        $raw = '';
        try {
            $raw = trim((string) Config::get(self::RATE_KEY, ''));
        } catch (Exception $e) {
            $raw = '';
        }
        self::$ratesCache = self::decodeRates($raw);
        return self::$ratesCache;
    }

    /**
     * @param string $gateId
     * @return array{qpm:int,qps:int}
     */
    public static function rateForGate($gateId)
    {
        $gateId = strtolower(trim((string) $gateId));
        $all = self::rates();
        if ($gateId !== '' && isset($all['apis'][$gateId])) {
            return $all['apis'][$gateId];
        }
        return array('qpm' => 0, 'qps' => 0);
    }

    /**
     * 写入单个接口的 QPM/QPS（只动该短名）
     *
     * @param string $gateId
     * @param mixed  $qpm
     * @param mixed  $qps
     * @return void
     */
    public static function setGateRate($gateId, $qpm, $qps)
    {
        $gateId = strtolower(trim((string) $gateId));
        if ($gateId === '' || !in_array($gateId, self::knownGateIds(), true)) {
            return;
        }
        $map = self::rates();
        $map['apis'][$gateId] = array(
            'qpm' => self::clampRateValue($qpm, self::QPM_MAX),
            'qps' => self::clampRateValue($qps, self::QPS_MAX),
        );
        ksort($map['apis']);
        Config::set(self::RATE_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));
        self::$ratesCache = $map;
    }

    /**
     * 鉴权通过后：按 sysapirq 执行单接口 QPS（窗 1s）再 QPM（窗 60s）
     * 计数维度：密钥指纹 + IP + gateId；0=跳过该维
     *
     * @param string $gateId
     * @return void
     */
    public static function enforceConfiguredLimit($gateId)
    {
        $gateId = strtolower(trim((string) $gateId));
        if ($gateId === '' || !in_array($gateId, self::knownGateIds(), true)) {
            return;
        }
        $r = self::rateForGate($gateId);
        $qps = (int) $r['qps'];
        $qpm = (int) $r['qpm'];
        if ($qps <= 0 && $qpm <= 0) {
            return;
        }
        $ip = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
        $key = '';
        try {
            $key = (string) self::get();
        } catch (Exception $e) {
            $key = '';
        }
        $fp = substr(hash('sha256', $key !== '' ? $key : 'nokey'), 0, 16);
        $base = 'sysrq:' . $gateId . ':' . $fp . ':' . $ip;
        if ($qps > 0) {
            if (!RateLimitStore::allow($base . ':qps', 1, $qps, true)) {
                self::fail(SystemApiError::RATE_LIMIT, '请求过于频繁');
            }
        }
        if ($qpm > 0) {
            if (!RateLimitStore::allow($base . ':qpm', 60, $qpm, true)) {
                self::fail(SystemApiError::RATE_LIMIT, '请求过于频繁');
            }
        }
    }

    /**
     * 业务失败退出（HTTP 200 + code=0 + errcode 12xxx）
     *
     * @param int    $errcode SystemApiError::*
     * @param string $msg
     * @param array  $extra
     * @return never
     */
    public static function fail($errcode, $msg, array $extra = array())
    {
        $errcode = (int) $errcode;
        if ($errcode < 12001 || $errcode > 12015) {
            $errcode = SystemApiError::BUSINESS;
        }
        $extra['errcode'] = $errcode;
        self::jsonExit(200, 0, $msg, $extra);
    }

    /**
     * 成功退出（HTTP 200 + code=1；不含 errcode）
     *
     * @param string $msg
     * @param array  $extra
     * @return never
     */
    public static function ok($msg, array $extra = array())
    {
        if (isset($extra['errcode'])) {
            unset($extra['errcode']);
        }
        self::jsonExit(200, 1, $msg, $extra);
    }

    /**
     * 已知开关字段（与 core/api 短名 / Catalog id 一致；新增接口只加这里）
     *
     * @return string[]
     */
    public static function knownGateIds()
    {
        return array(
            'apilogarchive',
            'apiquery',
            'apirank',
            'cardkey',
            'checkin',
            'cron',
            'sitestats',
            'userinfo',
        );
    }

    /**
     * 默认全关 JSON 字符串
     *
     * @return string
     */
    public static function defaultGatesJson()
    {
        $map = array();
        foreach (self::knownGateIds() as $id) {
            $map[$id] = 0;
        }
        return json_encode($map, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取全部开关（缺字段补 0；非法 JSON 视为全关）
     * 首次读取前幂等合并旧 *on（避免升级后未进后台时闸状态丢失）
     *
     * @return array<string,int> id => 0|1
     */
    public static function gates()
    {
        if (is_array(self::$gatesCache)) {
            return self::$gatesCache;
        }
        static $migrated = false;
        if (!$migrated) {
            $migrated = true;
            self::migrateGatesFromLegacy();
        }
        if (is_array(self::$gatesCache)) {
            return self::$gatesCache;
        }
        $raw = '';
        try {
            $raw = trim((string) Config::get(self::GATES_KEY, ''));
        } catch (Exception $e) {
            $raw = '';
        }
        $map = self::decodeGates($raw);
        self::$gatesCache = $map;
        return $map;
    }

    /**
     * 某接口开关是否开启
     *
     * @param string $gateId 如 cardkey / sitestats
     * @return bool
     */
    public static function isGateOn($gateId)
    {
        $gateId = strtolower(trim((string) $gateId));
        if ($gateId === '') {
            return false;
        }
        $gates = self::gates();
        return isset($gates[$gateId]) && (int) $gates[$gateId] === 1;
    }

    /**
     * 合并写入若干开关（只动传入字段；未知 id 忽略）
     *
     * @param array $partial id => bool|int|string
     * @return void
     */
    public static function setGates(array $partial)
    {
        $map = self::gates();
        foreach ($partial as $id => $on) {
            $id = strtolower(trim((string) $id));
            if ($id === '' || !in_array($id, self::knownGateIds(), true)) {
                continue;
            }
            $map[$id] = self::truthyGate($on) ? 1 : 0;
        }
        ksort($map);
        Config::set(self::GATES_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));
        self::$gatesCache = $map;
    }

    /**
     * 单字段写入
     *
     * @param string $gateId
     * @param mixed  $on
     * @return void
     */
    public static function setGate($gateId, $on)
    {
        self::setGates(array($gateId => $on));
    }

    /**
     * 一次性：旧 *on / *_api_enabled 合并进 sysapion 后删除旧键（幂等；业务禁止再读旧键）
     *
     * @return void
     */
    public static function migrateGatesFromLegacy()
    {
        try {
            $all = Config::all();
        } catch (Exception $e) {
            return;
        }

        $legacyToGate = array(
            'apilogon'             => 'apilogarchive',
            'apilog_api_enabled'   => 'apilogarchive',
            'cardkeyon'            => 'cardkey',
            'cardkey_api_enabled'  => 'cardkey',
            'checkinon'            => 'checkin',
            'apiqueryon'           => 'apiquery',
            'apiquery_api_enabled' => 'apiquery',
            'apirankon'            => 'apirank',
            'apirank_api_enabled'  => 'apirank',
            'userinfoon'           => 'userinfo',
            'sitestatson'          => 'sitestats',
        );

        $hadLegacy = false;
        foreach ($legacyToGate as $oldKey => $_gate) {
            if (array_key_exists($oldKey, $all)) {
                $hadLegacy = true;
                break;
            }
        }

        $rawNow = isset($all[self::GATES_KEY]) ? trim((string) $all[self::GATES_KEY]) : '';
        $hasSys = $rawNow !== '';
        $map = self::decodeGates($rawNow);

        if ($hadLegacy) {
            foreach ($legacyToGate as $oldKey => $gateId) {
                if (!array_key_exists($oldKey, $all)) {
                    continue;
                }
                $map[$gateId] = self::truthyGate($all[$oldKey]) ? 1 : 0;
            }
        } elseif (!$hasSys) {
            // 仅在尚无 sysapion、也无 *on 短键时，用旧归档/删除双开关抬开一次日志清理闸
            $archOn = isset($all['apilog_archive_enabled']) && trim((string) $all['apilog_archive_enabled']) === '1';
            $purgeOn = isset($all['apilog_purge_enabled']) && trim((string) $all['apilog_purge_enabled']) === '1';
            if ($archOn || $purgeOn) {
                $map['apilogarchive'] = 1;
            }
        }

        ksort($map);
        $encoded = json_encode($map, JSON_UNESCAPED_UNICODE);
        if (!$hasSys || $hadLegacy || $rawNow !== $encoded) {
            Config::set(self::GATES_KEY, $encoded);
            self::$gatesCache = $map;
        }

        if (!$hadLegacy) {
            return;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('config');
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
            foreach (array_keys($legacyToGate) as $oldKey) {
                if (array_key_exists($oldKey, $all)) {
                    $stmt->execute(array($oldKey));
                }
            }
            Config::clearCache();
            self::$gatesCache = null;
        } catch (Exception $e) {
            // 下次启动再删
        }
    }

    /**
     * @param string $raw
     * @return array<string,int>
     */
    private static function decodeGates($raw)
    {
        $map = array();
        foreach (self::knownGateIds() as $id) {
            $map[$id] = 0;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return $map;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $map;
        }
        foreach (self::knownGateIds() as $id) {
            if (array_key_exists($id, $decoded)) {
                $map[$id] = self::truthyGate($decoded[$id]) ? 1 : 0;
            }
        }
        return $map;
    }

    /**
     * @param mixed $v
     * @return bool
     */
    private static function truthyGate($v)
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v === 1;
        }
        $s = strtolower(trim((string) $v));
        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
    }

    /**
     * @param int    $http  传输层状态（业务失败请用 fail()，固定 200）
     * @param int    $code  1 成功 / 0 失败
     * @param string $msg
     * @param array  $extra 失败时若未带 errcode 且 code=0，自动补 SystemApiError::BUSINESS
     * @return never
     */
    public static function jsonExit($http, $code, $msg, array $extra = array())
    {
        $code = (int) $code;
        if ($code === 0) {
            if (!isset($extra['errcode']) || (int) $extra['errcode'] < 12001) {
                $extra['errcode'] = SystemApiError::BUSINESS;
            } else {
                $extra['errcode'] = (int) $extra['errcode'];
            }
            // 与公开 API 对齐：业务失败传输层固定 200，避免与站点 404/403 页冲突
            $http = 200;
        } else {
            if (isset($extra['errcode'])) {
                unset($extra['errcode']);
            }
        }
        if (!headers_sent()) {
            http_response_code((int) $http);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        $payload = array_merge(array(
            'code' => $code,
            'msg'  => (string) $msg,
        ), $extra);
        // HEX 转义降低被误当 HTML/脚本解析的风险；永不 JSONP 包装
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
}
