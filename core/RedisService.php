<?php
/**
 * 文件：core/RedisService.php
 * 作用：Redis 连接、业务缓存监控与 misc-api 专用键空间
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
     * @return bool
     */
    public static function ping()
    {
        if (!self::extensionLoaded()) {
            return false;
        }

        try {
            return self::withClient(function (Redis $redis) {
                $pong = $redis->ping();
                return ($pong === true || $pong === '+PONG' || $pong === 'PONG');
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param callable $callback function(Redis $redis)
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
     * @param string $suffix
     * @return string
     */
    public static function buildKey($suffix)
    {
        $prefix = self::connectionConfig()['prefix'];
        return $prefix . ltrim((string) $suffix, ':');
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
            $version = self::withClient(function (Redis $redis) {
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
                'uptime_human' => '',
                'used_memory_human' => '',
            ),
            'collected_at' => date('Y-m-d H:i:s'),
        );

        if (!$snapshot['extension_loaded']) {
            $snapshot['error'] = 'PHP Redis 扩展未安装';
            return $snapshot;
        }

        try {
            self::withClient(function (Redis $redis) use (&$snapshot, $config) {
                $snapshot['connected'] = true;
                $snapshot['ok'] = true;

                $info = $redis->info();
                if (!is_array($info)) {
                    $info = array();
                }

                $snapshot['server'] = array(
                    'redis_version' => self::infoValue($info, 'redis_version'),
                    'uptime_human' => self::formatUptime((int) self::infoValue($info, 'uptime_in_seconds', '0')),
                    'used_memory_human' => self::infoValue($info, 'used_memory_human'),
                );

                $snapshot['business']['cache_enabled'] = true;
                $appStats = RedisCache::appStats();
                $snapshot['business']['app_hits'] = $appStats['hits'];
                $snapshot['business']['app_misses'] = $appStats['misses'];
                $snapshot['business']['app_hit_rate_percent'] = $appStats['hit_rate_percent'];
                $snapshot['business']['entries'] = RedisCache::inspectEntries();

                $prefix = $config['prefix'];
                $cacheScan = self::scanKeyStats($redis, $prefix . 'cache:*');
                $rateScan = self::scanKeyStats($redis, $prefix . 'rl:*');

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
     * @param Redis  $redis
     * @param string $pattern
     * @return array{count:int,bytes:int}
     */
    private static function scanKeyStats(Redis $redis, $pattern)
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

        return $redis;
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
