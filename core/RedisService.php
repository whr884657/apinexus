<?php
/**
 * 文件：core/RedisService.php
 * 作用：Redis 连接、业务缓存监控与 ApiNexus 专用键空间
 */

class RedisService
{
    const CONFIG_HOST = 'redis_host';
    const CONFIG_PORT = 'redis_port';
    const CONFIG_PASSWORD = 'redis_password';
    const CONFIG_DATABASE = 'redis_database';
    const CONFIG_PREFIX = 'redis_prefix';

    /** 单站默认键前缀（同机多站须改） */
    const DEFAULT_PREFIX = 'apinexus:';

    /** 前缀最大长度（含末尾冒号） */
    const PREFIX_MAX_LEN = 48;

    /** Redis 逻辑库号上限（含）：0～15 共 16 个 */
    const DATABASE_MAX = 15;

    /**
     * 规范化 Redis 库号：仅允许 0～15 的整数；空串视为 0
     *
     * @param mixed $raw
     * @return int|false
     */
    public static function normalizeDatabase($raw)
    {
        if ($raw === null) {
            return 0;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return 0;
        }
        if (!preg_match('/^\d+$/', $s)) {
            return false;
        }
        $n = (int) $s;
        if ($n < 0 || $n > self::DATABASE_MAX) {
            return false;
        }
        return $n;
    }

    /**
     * 规范化 Redis 主机：主机名或 IP；拒绝 URL/空白/控制字符
     *
     * @param mixed $raw
     * @return string|false
     */
    public static function normalizeHost($raw)
    {
        $host = trim((string) $raw);
        if ($host === '') {
            return '127.0.0.1';
        }
        if (strlen($host) > 253) {
            return false;
        }
        // 禁止写成 redis:// 或带路径；仅主机名/IP
        if (preg_match('#[:/\\\\\\s\\x00-\\x1f\\x7f]#', $host)) {
            return false;
        }
        return $host;
    }

    /**
     * 用草稿参数测连 Redis（不写 Config；供安装向导 / 设置页）
     *
     * @param array $draft host? port? password? database?
     * @return array{ok:bool,msg:string,database?:int}
     */
    public static function testDraftConnection(array $draft)
    {
        if (!self::extensionLoaded()) {
            return array('ok' => false, 'msg' => 'PHP Redis 扩展未安装');
        }

        $host = self::normalizeHost(isset($draft['host']) ? $draft['host'] : '127.0.0.1');
        if ($host === false) {
            return array('ok' => false, 'msg' => 'Redis 主机无效');
        }
        $port = (int) (isset($draft['port']) ? $draft['port'] : 6379);
        if ($port <= 0 || $port > 65535) {
            return array('ok' => false, 'msg' => 'Redis 端口无效');
        }
        $database = self::normalizeDatabase(isset($draft['database']) ? $draft['database'] : 0);
        if ($database === false) {
            return array('ok' => false, 'msg' => 'Redis 库号须为 0～15 的整数');
        }
        $password = isset($draft['password']) ? (string) $draft['password'] : '';

        try {
            $redis = self::openClient($host, $port, $password, $database);
            $pong = $redis->ping();
            try {
                $redis->close();
            } catch (Exception $eClose) {
                // ignore
            }
            $ok = ($pong === true || $pong === '+PONG' || $pong === 'PONG');
            if (!$ok) {
                return array('ok' => false, 'msg' => 'Redis 已连接但 PING 异常');
            }
            return array(
                'ok' => true,
                'msg' => 'Redis 连接成功（db' . $database . '）',
                'database' => $database,
            );
        } catch (Exception $e) {
            $raw = $e->getMessage();
            // 勿把密码拼进对外文案
            if (stripos($raw, 'auth') !== false || stripos($raw, 'NOAUTH') !== false || stripos($raw, 'invalid password') !== false) {
                return array('ok' => false, 'msg' => 'Redis 认证失败，请检查密码');
            }
            return array('ok' => false, 'msg' => 'Redis 连接失败：' . $raw);
        }
    }

    /**
     * 保存 Redis 连接相关配置（前缀仍走 savePrefixConfig）
     *
     * @param array $input host, port, database, password?, clear_password?
     * @return array{ok:bool,msg:string}
     */
    public static function saveConnectionSettings(array $input)
    {
        $host = self::normalizeHost(isset($input['host']) ? $input['host'] : '');
        if ($host === false) {
            return array('ok' => false, 'msg' => 'Redis 主机无效');
        }
        $port = (int) (isset($input['port']) ? $input['port'] : 6379);
        if ($port <= 0 || $port > 65535) {
            return array('ok' => false, 'msg' => 'Redis 端口无效');
        }
        $database = self::normalizeDatabase(isset($input['database']) ? $input['database'] : 0);
        if ($database === false) {
            return array('ok' => false, 'msg' => 'Redis 库号须为 0～15 的整数');
        }

        $clearPassword = !empty($input['clear_password']);
        $passwordIn = isset($input['password']) ? (string) $input['password'] : '';
        if ($clearPassword) {
            Config::set(self::CONFIG_PASSWORD, '');
        } elseif ($passwordIn !== '') {
            Config::set(self::CONFIG_PASSWORD, $passwordIn);
        }
        // 密码留空且未勾选清除 → 保持原值

        Config::set(self::CONFIG_HOST, $host);
        Config::set(self::CONFIG_PORT, (string) $port);
        Config::set(self::CONFIG_DATABASE, (string) $database);

        return array('ok' => true, 'msg' => 'Redis 连接参数已保存');
    }

    /**
     * @return bool
     */
    public static function extensionLoaded()
    {
        return class_exists('Redis');
    }

    /**
     * 规范化缓存键前缀：去空白、非法字符剔除、空则默认、末尾补冒号
     *
     * @param string $raw
     * @param bool   $emptyAsDefault 空串是否回落到 DEFAULT_PREFIX
     * @return string|false 非法时 false
     */
    public static function normalizePrefix($raw, $emptyAsDefault = true)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return $emptyAsDefault ? self::DEFAULT_PREFIX : '';
        }

        // 仅允许字母数字、下划线、连字符、冒号；统一小写更稳
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_:\-]/', '', $raw));
        if ($clean === '' || $clean === ':') {
            return false;
        }
        if (substr($clean, -1) !== ':') {
            $clean .= ':';
        }
        if (strlen($clean) > self::PREFIX_MAX_LEN) {
            return false;
        }
        // 禁止仅冒号堆叠或开头为冒号
        if ($clean[0] === ':' || preg_match('/:{2,}/', $clean)) {
            return false;
        }

        return $clean;
    }

    /**
     * 统计某前缀下已有键数量（用于同机多站冲突提示）
     *
     * @param string $prefix 已规范化前缀
     * @param int    $limit  扫描上限
     * @return array{ok:bool,count:int,error:string}
     */
    public static function countKeysUnderPrefix($prefix, $limit = 50)
    {
        $prefix = self::normalizePrefix($prefix, false);
        if ($prefix === false || $prefix === '') {
            return array('ok' => false, 'count' => 0, 'error' => '前缀无效');
        }
        if (!self::extensionLoaded()) {
            return array('ok' => false, 'count' => 0, 'error' => 'Redis 扩展未安装');
        }

        $limit = max(1, min(500, (int) $limit));
        try {
            $count = (int) self::withClient(function ($redis) use ($prefix, $limit) {
                $n = 0;
                $it = null;
                $pattern = $prefix . '*';
                do {
                    $keys = $redis->scan($it, $pattern, 80);
                    if ($keys === false || !is_array($keys)) {
                        break;
                    }
                    foreach ($keys as $key) {
                        // 仅统计本前缀（避免更短前缀误伤，如 a: 命中 ab:）
                        if (strpos((string) $key, $prefix) !== 0) {
                            continue;
                        }
                        $n++;
                        if ($n >= $limit) {
                            return $n;
                        }
                    }
                } while ($it !== 0 && $it !== null);

                return $n;
            });

            return array('ok' => true, 'count' => $count, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'count' => 0, 'error' => $e->getMessage());
        }
    }

    /**
     * 检测目标前缀是否可能与其它站点冲突（目标前缀下已有键，且不同于当前站前缀）
     *
     * @param string $candidateRaw
     * @return array{conflict:bool,prefix:string,count:int,message:string}
     */
    public static function detectPrefixConflict($candidateRaw)
    {
        $normalized = self::normalizePrefix($candidateRaw, true);
        if ($normalized === false) {
            return array(
                'conflict' => true,
                'prefix' => '',
                'count' => 0,
                'message' => '前缀格式无效：仅允许字母、数字、下划线、连字符，并以冒号结尾',
            );
        }

        $current = self::connectionConfig()['prefix'];
        if ($normalized === $current) {
            return array(
                'conflict' => false,
                'prefix' => $normalized,
                'count' => 0,
                'message' => '',
            );
        }

        $scan = self::countKeysUnderPrefix($normalized, 20);
        if (!$scan['ok']) {
            // 连不上 Redis 时不挡保存，仅提示
            return array(
                'conflict' => false,
                'prefix' => $normalized,
                'count' => 0,
                'message' => $scan['error'] !== '' ? ('无法检测冲突：' . $scan['error']) : '',
            );
        }

        if ($scan['count'] > 0) {
            return array(
                'conflict' => true,
                'prefix' => $normalized,
                'count' => $scan['count'],
                'message' => '该前缀下已有缓存数据（约 ' . $scan['count'] . ' 项起），可能与同机其它站点共用。继续使用会导致数据串读，请更换前缀。',
            );
        }

        return array(
            'conflict' => false,
            'prefix' => $normalized,
            'count' => 0,
            'message' => '',
        );
    }

    /**
     * 删除本站键空间下全部键（redis_prefix + 站点盐；不 flushdb，不误清它站）
     *
     * @param string|null $prefixRaw null=当前配置前缀
     * @return array{ok:bool,deleted:int,error:string}
     */
    public static function flushKeyspace($prefixRaw = null)
    {
        $space = self::keyspacePrefix($prefixRaw);
        if ($space === false || $space === '') {
            return array('ok' => false, 'deleted' => 0, 'error' => '前缀无效');
        }

        if (!self::extensionLoaded()) {
            return array('ok' => false, 'deleted' => 0, 'error' => 'Redis 扩展未安装');
        }

        try {
            $deleted = (int) self::withClient(function ($redis) use ($space) {
                $n = 0;
                $it = null;
                $pattern = $space . '*';
                do {
                    $keys = $redis->scan($it, $pattern, 100);
                    if ($keys === false || !is_array($keys)) {
                        break;
                    }
                    $batch = array();
                    foreach ($keys as $key) {
                        if (strpos((string) $key, $space) === 0) {
                            $batch[] = $key;
                        }
                    }
                    if (!empty($batch)) {
                        $n += (int) $redis->del($batch);
                    }
                } while ($it !== 0 && $it !== null);

                return $n;
            });

            return array('ok' => true, 'deleted' => $deleted, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'deleted' => 0, 'error' => $e->getMessage());
        }
    }

    /**
     * 保存键前缀：先清空旧前缀键空间，再写入配置；目标前缀冲突且未强制时返回需确认
     *
     * @param string $rawPrefix
     * @param bool   $forceConflict 冲突时仍保存
     * @return array{ok:bool,need_confirm:bool,msg:string,prefix:string,deleted:int}
     */
    public static function savePrefixConfig($rawPrefix, $forceConflict = false)
    {
        $normalized = self::normalizePrefix($rawPrefix, true);
        if ($normalized === false) {
            return array(
                'ok' => false,
                'need_confirm' => false,
                'msg' => '前缀格式无效：仅允许字母、数字、下划线、连字符，长度不超过 '
                    . self::PREFIX_MAX_LEN . '，建议以冒号结尾',
                'prefix' => '',
                'deleted' => 0,
            );
        }

        $conflict = self::detectPrefixConflict($normalized);
        if (!empty($conflict['conflict']) && !$forceConflict) {
            return array(
                'ok' => false,
                'need_confirm' => true,
                'msg' => $conflict['message'],
                'prefix' => $normalized,
                'deleted' => 0,
            );
        }

        $oldPrefix = self::connectionConfig()['prefix'];
        $flush = self::flushKeyspace($oldPrefix);
        // 前缀变更时也清掉目标前缀下残留（全新缓存）
        if ($normalized !== $oldPrefix) {
            $flushNew = self::flushKeyspace($normalized);
            if ($flushNew['ok']) {
                $flush['deleted'] = (int) $flush['deleted'] + (int) $flushNew['deleted'];
            }
        }

        Config::set(self::CONFIG_PREFIX, $normalized);

        $msg = '缓存键前缀已保存';
        if ($flush['ok']) {
            $msg .= '，已清空旧缓存 ' . (int) $flush['deleted'] . ' 项，将按新前缀重新写入';
        } elseif ($flush['error'] !== '') {
            $msg .= '（清空缓存时：' . $flush['error'] . '）';
        }

        return array(
            'ok' => true,
            'need_confirm' => false,
            'msg' => $msg,
            'prefix' => $normalized,
            'deleted' => (int) $flush['deleted'],
        );
    }

    /**
     * @return bool
     */
    public static function ping()
    {
        if (!self::extensionLoaded()) {
            return false;
        }

        try {
            return self::withClient(function ($redis) {
                $pong = $redis->ping();
                return ($pong === true || $pong === '+PONG' || $pong === 'PONG');
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param callable $callback function(object $redis)
     * @return mixed
     */
    public static function withClient($callback)
    {
        $redis = self::connectClient();
        try {
            return call_user_func($callback, $redis);
        } finally {
            try {
                $redis->close();
            } catch (Exception $e) {
                // ignore
            }
        }
    }

    /**
     * 站点命名空间（防同 redis_prefix 多站互串）
     * 由库名 + 配置路径派生，与 redis_prefix 叠加进最终键
     *
     * @return string 形如「a1b2c3d4e5:」
     */
    public static function siteKeySalt()
    {
        static $salt = null;
        if ($salt !== null) {
            return $salt;
        }
        $dbname = '';
        $host = '';
        $root = defined('VS_ROOT') ? (string) VS_ROOT : '';
        if (class_exists('Database')) {
            $cfg = Database::loadConfig();
            if (is_array($cfg)) {
                $dbname = isset($cfg['dbname']) ? (string) $cfg['dbname'] : '';
                $host = isset($cfg['host']) ? (string) $cfg['host'] : '';
            }
        }
        $raw = strtolower($host . '|' . $dbname . '|' . str_replace('\\', '/', $root));
        $salt = substr(hash('sha256', $raw), 0, 10) . ':';
        return $salt;
    }

    /**
     * 当前站完整键空间前缀 = redis_prefix + siteSalt
     *
     * @param string|null $prefixRaw null=当前配置
     * @return string|false
     */
    public static function keyspacePrefix($prefixRaw = null)
    {
        if ($prefixRaw === null) {
            $prefix = self::connectionConfig()['prefix'];
        } else {
            $prefix = self::normalizePrefix($prefixRaw, true);
            if ($prefix === false) {
                return false;
            }
        }
        return $prefix . self::siteKeySalt();
    }

    /**
     * @param string $suffix
     * @return string
     */
    public static function buildKey($suffix)
    {
        $space = self::keyspacePrefix(null);
        if ($space === false || $space === '') {
            $space = self::DEFAULT_PREFIX . self::siteKeySalt();
        }
        return $space . ltrim((string) $suffix, ':');
    }

    /**
     * @param int $bytes
     * @return string
     */
    public static function formatBytes($bytes)
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 2) . ' MB';
    }

    /**
     * @return array
     */
    public static function connectionConfig()
    {
        $port = (int) Config::get(self::CONFIG_PORT, '6379');
        if ($port <= 0 || $port > 65535) {
            $port = 6379;
        }

        $database = self::normalizeDatabase(Config::get(self::CONFIG_DATABASE, '0'));
        if ($database === false) {
            $database = 0;
        }

        $prefixRaw = (string) Config::get(self::CONFIG_PREFIX, self::DEFAULT_PREFIX);
        $prefix = self::normalizePrefix($prefixRaw, true);
        if ($prefix === false || $prefix === '') {
            $prefix = self::DEFAULT_PREFIX;
        }

        return array(
            'host' => trim((string) Config::get(self::CONFIG_HOST, '127.0.0.1')),
            'port' => $port,
            'database' => $database,
            'prefix' => $prefix,
            'has_password' => trim((string) Config::get(self::CONFIG_PASSWORD, '')) !== '',
        );
    }

    /**
     * @return string
     */
    public static function versionLabel()
    {
        if (!self::extensionLoaded()) {
            return 'PHP Redis 扩展未安装';
        }
        if (!self::ping()) {
            return '未连接';
        }

        try {
            $version = self::withClient(function ($redis) {
                $info = $redis->info();
                return is_array($info) && isset($info['redis_version']) ? (string) $info['redis_version'] : '';
            });
            return $version !== '' ? 'Redis ' . $version : '已连接';
        } catch (Exception $e) {
            return '未连接';
        }
    }

    /**
     * @return array
     */
    public static function collectMonitorSnapshot()
    {
        $config = self::connectionConfig();
        $snapshot = array(
            'ok' => false,
            'error' => '',
            'extension_loaded' => self::extensionLoaded(),
            'connected' => false,
            'config' => $config,
            'business' => array(
                'cache_enabled' => false,
                'app_hits' => 0,
                'app_misses' => 0,
                'app_hit_rate_percent' => null,
                'entries' => array(),
                'cache_keys' => 0,
                'rate_limit_keys' => 0,
                'cache_memory_bytes' => 0,
                'cache_memory_human' => '—',
            ),
            'server' => array(
                'redis_version' => '',
                'uptime_seconds' => 0,
                'uptime_human' => '',
                'used_memory_human' => '',
                'used_memory_peak_human' => '',
                'connected_clients' => 0,
            ),
            'collected_at' => date('Y-m-d H:i:s'),
        );

        if (!$snapshot['extension_loaded']) {
            $snapshot['error'] = 'PHP Redis 扩展未安装';
            return $snapshot;
        }

        RedisCache::maintainKeyspace();

        try {
            self::withClient(function ($redis) use (&$snapshot, $config) {
                $snapshot['connected'] = true;
                $snapshot['ok'] = true;

                $info = $redis->info();
                if (!is_array($info)) {
                    $info = array();
                }

                $uptimeSec = (int) self::infoValue($info, 'uptime_in_seconds', '0');
                $snapshot['server'] = array(
                    'redis_version' => self::infoValue($info, 'redis_version'),
                    'uptime_seconds' => $uptimeSec,
                    'uptime_human' => self::formatUptime($uptimeSec),
                    'used_memory_human' => self::infoValue($info, 'used_memory_human'),
                    'used_memory_peak_human' => self::infoValue($info, 'used_memory_peak_human'),
                    'connected_clients' => (int) self::infoValue($info, 'connected_clients', '0'),
                );

                $snapshot['business']['cache_enabled'] = true;
                $appStats = RedisCache::appStats();
                $snapshot['business']['app_hits'] = $appStats['hits'];
                $snapshot['business']['app_misses'] = $appStats['misses'];
                $snapshot['business']['app_hit_rate_percent'] = $appStats['hit_rate_percent'];
                $snapshot['business']['entries'] = RedisCache::inspectEntries();

                $space = self::keyspacePrefix($config['prefix']);
                if ($space === false) {
                    $space = $config['prefix'] . self::siteKeySalt();
                }
                $cacheScan = self::scanKeyStats($redis, $space . 'cache:*');
                $rateScan = self::scanKeyStats($redis, $space . 'rl:*');

                $snapshot['business']['cache_keys'] = $cacheScan['count'];
                $snapshot['business']['rate_limit_keys'] = $rateScan['count'];
                $snapshot['business']['cache_memory_bytes'] = $cacheScan['bytes'] + $rateScan['bytes'];
                $snapshot['business']['cache_memory_human'] = self::formatBytes(
                    $snapshot['business']['cache_memory_bytes']
                );
            });
        } catch (Exception $e) {
            $snapshot['error'] = $e->getMessage();
        }

        return $snapshot;
    }

    /**
     * @param object $redis
     * @param string $pattern
     * @return array{count:int,bytes:int}
     */
    private static function scanKeyStats($redis, $pattern)
    {
        $count = 0;
        $bytes = 0;
        $iterator = null;
        $maxKeys = 10000;

        do {
            $keys = $redis->scan($iterator, $pattern, 100);
            if ($keys === false || !is_array($keys)) {
                break;
            }
            foreach ($keys as $key) {
                $count++;
                $len = $redis->strlen($key);
                if ($len !== false) {
                    $bytes += (int) $len;
                }
                if ($count >= $maxKeys) {
                    return array('count' => $maxKeys, 'bytes' => $bytes);
                }
            }
        } while ($iterator !== 0 && $iterator !== null);

        return array('count' => $count, 'bytes' => $bytes);
    }

    /**
     * 清理过期限流键并在超出上限时淘汰最旧键
     *
     * @param object $redis
     * @param int    $maxKeys
     * @return int 删除数量
     */
    public static function pruneRateLimitKeys($redis, $maxKeys)
    {
        $maxKeys = max(100, (int) $maxKeys);
        $space = self::keyspacePrefix(null);
        if ($space === false) {
            $space = self::DEFAULT_PREFIX . self::siteKeySalt();
        }
        $pattern = $space . 'rl:*';
        $keys = array();
        $iterator = null;

        do {
            $batch = $redis->scan($iterator, $pattern, 200);
            if ($batch === false || !is_array($batch)) {
                break;
            }
            foreach ($batch as $key) {
                $keys[] = $key;
            }
        } while ($iterator !== 0 && $iterator !== null);

        $pruned = 0;
        $alive = array();

        foreach ($keys as $key) {
            $ttl = (int) $redis->ttl($key);
            if ($ttl === -2) {
                continue;
            }
            if ($ttl === 0) {
                $redis->del($key);
                $pruned++;
                continue;
            }
            if ($ttl === -1 && strpos($key, $space . 'rl:last:') !== 0) {
                $redis->expire($key, 3600);
            }
            $alive[] = $key;
        }

        if (count($alive) <= $maxKeys) {
            return $pruned;
        }

        $candidates = array();
        foreach ($alive as $key) {
            if (strpos($key, $space . 'rl:last:') === 0) {
                continue;
            }
            $candidates[] = array(
                'key' => $key,
                'score' => (int) $redis->ttl($key),
            );
        }

        usort($candidates, function ($a, $b) {
            return $a['score'] - $b['score'];
        });

        $overflow = count($alive) - $maxKeys;
        for ($i = 0; $i < $overflow && $i < count($candidates); $i++) {
            if ($redis->del($candidates[$i]['key'])) {
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * @param string $host
     * @param int    $port
     * @param string $password
     * @param int    $database
     * @return object phpredis 客户端（勿在签名写死 Redis，见 E226）
     * @throws Exception
     */
    private static function openClient($host, $port, $password, $database)
    {
        if (!self::extensionLoaded()) {
            throw new Exception('PHP Redis 扩展未安装');
        }

        $host = self::normalizeHost($host);
        if ($host === false) {
            throw new Exception('Redis 主机无效');
        }
        $port = (int) $port;
        if ($port <= 0 || $port > 65535) {
            $port = 6379;
        }
        $database = self::normalizeDatabase($database);
        if ($database === false) {
            throw new Exception('Redis 库号须为 0～15 的整数');
        }

        // 动态类名：避免 IDE 假 stub / 硬类型 Redis 触发 class_exists 误判（E226）
        $redisClass = 'Redis';
        $redis = new $redisClass();
        $connected = @$redis->connect($host, $port, 2.0);
        if (!$connected) {
            throw new Exception('无法连接 Redis（' . $host . ':' . $port . '）');
        }

        $password = (string) $password;
        if ($password !== '') {
            if (!$redis->auth($password)) {
                throw new Exception('Redis 认证失败');
            }
        }

        if (!$redis->select($database)) {
            throw new Exception('无法选择 Redis 数据库 db' . $database);
        }

        return $redis;
    }

    /**
     * @return object
     * @throws Exception
     */
    private static function connectClient()
    {
        $config = self::connectionConfig();
        $host = $config['host'] !== '' ? $config['host'] : '127.0.0.1';
        $password = trim((string) Config::get(self::CONFIG_PASSWORD, ''));
        return self::openClient($host, $config['port'], $password, $config['database']);
    }

    /**
     * @param array  $info
     * @param string $key
     * @param string $default
     * @return string
     */
    private static function infoValue(array $info, $key, $default = '')
    {
        return isset($info[$key]) ? (string) $info[$key] : $default;
    }

    /**
     * @param int $seconds
     * @return string
     */
    public static function formatUptime($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = (int) ($seconds % 60);

        $parts = array();
        if ($days > 0) {
            $parts[] = $days . ' 天';
        }
        if ($days > 0 || $hours > 0) {
            $parts[] = $hours . ' 小时';
        }
        if ($days > 0 || $hours > 0 || $minutes > 0) {
            $parts[] = $minutes . ' 分';
        }
        $parts[] = $secs . ' 秒';
        return implode(' ', $parts);
    }
}
