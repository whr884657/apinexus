<?php
/**
 * 文件：core/RedisService.php
 * 作用：Redis 连接、状态检测与监控指标采集（后台 Redis 管理页）
 */

class RedisService
{
    const CONFIG_HOST = 'redis_host';
    const CONFIG_PORT = 'redis_port';
    const CONFIG_PASSWORD = 'redis_password';
    const CONFIG_DATABASE = 'redis_database';
    const CONFIG_PREFIX = 'redis_prefix';

    /**
     * @return bool
     */
    public static function extensionLoaded()
    {
        return class_exists('Redis');
    }

    /**
     * 连接配置（不含密码明文输出给前端）
     *
     * @return array
     */
    public static function connectionConfig()
    {
        $port = (int) Config::get(self::CONFIG_PORT, '6379');
        if ($port <= 0 || $port > 65535) {
            $port = 6379;
        }

        $database = (int) Config::get(self::CONFIG_DATABASE, '0');
        if ($database < 0) {
            $database = 0;
        }

        $prefix = trim((string) Config::get(self::CONFIG_PREFIX, 'misc_api:'));
        if ($prefix === '') {
            $prefix = 'misc_api:';
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
     * 关于页等使用的 Redis 版本摘要
     *
     * @return string
     */
    public static function versionLabel()
    {
        if (!self::extensionLoaded()) {
            return 'PHP Redis 扩展未安装';
        }

        $snapshot = self::collectMonitorSnapshot();
        if (!$snapshot['connected']) {
            return $snapshot['error'] !== '' ? $snapshot['error'] : '未连接';
        }

        $version = isset($snapshot['server']['redis_version']) ? $snapshot['server']['redis_version'] : '';
        if ($version === '') {
            return '已连接';
        }

        return 'Redis ' . $version;
    }

    /**
     * 采集监控快照（供后台页与 AJAX 刷新）
     *
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
            'server' => array(),
            'memory' => array(),
            'stats' => array(),
            'keys' => array(
                'total' => 0,
                'prefixed' => 0,
            ),
            'collected_at' => date('Y-m-d H:i:s'),
        );

        if (!$snapshot['extension_loaded']) {
            $snapshot['error'] = 'PHP Redis 扩展未安装';
            return $snapshot;
        }

        $redis = null;
        try {
            $redis = self::connectClient();
            $snapshot['connected'] = true;
            $snapshot['ok'] = true;

            $info = $redis->info();
            if (!is_array($info)) {
                $info = array();
            }

            $snapshot['server'] = array(
                'redis_version' => self::infoValue($info, 'redis_version'),
                'redis_mode' => self::infoValue($info, 'redis_mode'),
                'os' => self::infoValue($info, 'os'),
                'arch_bits' => self::infoValue($info, 'arch_bits'),
                'uptime_seconds' => (int) self::infoValue($info, 'uptime_in_seconds', '0'),
                'uptime_human' => self::formatUptime((int) self::infoValue($info, 'uptime_in_seconds', '0')),
                'role' => self::infoValue($info, 'role'),
                'tcp_port' => self::infoValue($info, 'tcp_port'),
            );

            $maxMemory = (int) self::infoValue($info, 'maxmemory', '0');
            $usedMemory = (int) self::infoValue($info, 'used_memory', '0');

            $snapshot['memory'] = array(
                'used_memory_human' => self::infoValue($info, 'used_memory_human'),
                'used_memory_peak_human' => self::infoValue($info, 'used_memory_peak_human'),
                'used_memory_rss_human' => self::infoValue($info, 'used_memory_rss_human'),
                'maxmemory_human' => $maxMemory > 0
                    ? self::infoValue($info, 'maxmemory_human')
                    : '未限制',
                'mem_fragmentation_ratio' => self::infoValue($info, 'mem_fragmentation_ratio'),
                'usage_percent' => $maxMemory > 0
                    ? round(($usedMemory / $maxMemory) * 100, 2)
                    : null,
            );

            $hits = (int) self::infoValue($info, 'keyspace_hits', '0');
            $misses = (int) self::infoValue($info, 'keyspace_misses', '0');
            $lookupTotal = $hits + $misses;

            $snapshot['stats'] = array(
                'connected_clients' => (int) self::infoValue($info, 'connected_clients', '0'),
                'blocked_clients' => (int) self::infoValue($info, 'blocked_clients', '0'),
                'instantaneous_ops_per_sec' => (int) self::infoValue($info, 'instantaneous_ops_per_sec', '0'),
                'total_commands_processed' => (int) self::infoValue($info, 'total_commands_processed', '0'),
                'keyspace_hits' => $hits,
                'keyspace_misses' => $misses,
                'hit_rate_percent' => $lookupTotal > 0 ? round(($hits / $lookupTotal) * 100, 2) : null,
                'expired_keys' => (int) self::infoValue($info, 'expired_keys', '0'),
                'evicted_keys' => (int) self::infoValue($info, 'evicted_keys', '0'),
            );

            $snapshot['keys']['total'] = (int) $redis->dbSize();
            $snapshot['keys']['prefixed'] = self::countPrefixedKeys($redis, $config['prefix']);
        } catch (Exception $e) {
            $snapshot['error'] = $e->getMessage();
        } finally {
            if ($redis instanceof Redis) {
                try {
                    $redis->close();
                } catch (Exception $e) {
                    // ignore
                }
            }
        }

        return $snapshot;
    }

    /**
     * @return Redis
     * @throws Exception
     */
    private static function connectClient()
    {
        if (!self::extensionLoaded()) {
            throw new Exception('PHP Redis 扩展未安装');
        }

        $config = self::connectionConfig();
        $host = $config['host'] !== '' ? $config['host'] : '127.0.0.1';

        $redis = new Redis();
        $connected = @$redis->connect($host, $config['port'], 2.0);
        if (!$connected) {
            throw new Exception('无法连接 Redis（' . $host . ':' . $config['port'] . '）');
        }

        $password = trim((string) Config::get(self::CONFIG_PASSWORD, ''));
        if ($password !== '') {
            if (!$redis->auth($password)) {
                throw new Exception('Redis 认证失败');
            }
        }

        if (!$redis->select($config['database'])) {
            throw new Exception('无法选择 Redis 数据库 db' . $config['database']);
        }

        $pong = $redis->ping();
        if ($pong !== '+PONG' && $pong !== 'PONG' && $pong !== true) {
            throw new Exception('Redis PING 未响应');
        }

        return $redis;
    }

    /**
     * @param Redis  $redis
     * @param string $prefix
     * @return int
     */
    private static function countPrefixedKeys(Redis $redis, $prefix)
    {
        $prefix = (string) $prefix;
        if ($prefix === '') {
            return 0;
        }

        $count = 0;
        $iterator = null;
        $pattern = $prefix . '*';
        $maxKeys = 50000;

        do {
            $keys = $redis->scan($iterator, $pattern, 200);
            if ($keys === false) {
                break;
            }
            if (!is_array($keys)) {
                continue;
            }
            $count += count($keys);
            if ($count >= $maxKeys) {
                return $maxKeys;
            }
        } while ($iterator !== 0 && $iterator !== null);

        return $count;
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
    private static function formatUptime($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return $days . ' 天 ' . $hours . ' 小时';
        }
        if ($hours > 0) {
            return $hours . ' 小时 ' . $minutes . ' 分钟';
        }
        return $minutes . ' 分钟';
    }
}
