<?php
/**
 * 文件：core/RedisCache.php
 * 作用：misc-api 业务数据 Redis 缓存（读写分离 MySQL，降低高频查询与限流写入压力）
 */

class RedisCache
{
    const KEY_FRONTEND_API = 'cache:frontend:api_list';
    const KEY_FRONTEND_CATEGORY = 'cache:frontend:category_tags';
    const KEY_API_PUBLIC = 'cache:api:public_list';
    const KEY_STAT_HITS = 'stats:cache_hits';
    const KEY_STAT_MISSES = 'stats:cache_misses';

    const TTL_FRONTEND_API = 120;
    const TTL_FRONTEND_CATEGORY = 300;
    const TTL_API_PUBLIC = 120;

    /**
     * @return bool
     */
    public static function enabled()
    {
        return RedisService::extensionLoaded() && RedisService::ping();
    }

    /**
     * 读缓存；未命中则执行回调并写入
     *
     * @param string   $logicalKey
     * @param int      $ttl
     * @param callable $factory
     * @return mixed
     */
    public static function remember($logicalKey, $ttl, $factory)
    {
        if (!self::enabled()) {
            return call_user_func($factory);
        }

        try {
            return RedisService::withClient(function (Redis $redis) use ($logicalKey, $ttl, $factory) {
                $fullKey = RedisService::buildKey($logicalKey);
                $raw = $redis->get($fullKey);
                if ($raw !== false && $raw !== '') {
                    self::incrStat($redis, self::KEY_STAT_HITS);
                    $value = @unserialize($raw);
                    if ($value !== false || $raw === 'b:0;') {
                        return $value;
                    }
                }

                self::incrStat($redis, self::KEY_STAT_MISSES);
                $value = call_user_func($factory);
                $redis->setex($fullKey, max(1, (int) $ttl), serialize($value));
                return $value;
            });
        } catch (Exception $e) {
            return call_user_func($factory);
        }
    }

    /**
     * @param string $logicalKey
     * @return void
     */
    public static function forget($logicalKey)
    {
        if (!self::enabled()) {
            return;
        }

        try {
            RedisService::withClient(function (Redis $redis) use ($logicalKey) {
                $redis->del(RedisService::buildKey($logicalKey));
            });
        } catch (Exception $e) {
            // 忽略
        }
    }

    /**
     * 前台/API 相关缓存一并失效（分类、接口列表变更时调用）
     *
     * @return void
     */
    public static function invalidateFrontend()
    {
        self::forget(self::KEY_FRONTEND_API);
        self::forget(self::KEY_FRONTEND_CATEGORY);
        self::forget(self::KEY_API_PUBLIC);
    }

    /**
     * @return array{hits:int,misses:int,hit_rate_percent:float|null}
     */
    public static function appStats()
    {
        $empty = array('hits' => 0, 'misses' => 0, 'hit_rate_percent' => null);
        if (!self::enabled()) {
            return $empty;
        }

        try {
            return RedisService::withClient(function (Redis $redis) {
                $hits = (int) $redis->get(RedisService::buildKey(self::KEY_STAT_HITS));
                $misses = (int) $redis->get(RedisService::buildKey(self::KEY_STAT_MISSES));
                $total = $hits + $misses;
                return array(
                    'hits' => $hits,
                    'misses' => $misses,
                    'hit_rate_percent' => $total > 0 ? round(($hits / $total) * 100, 2) : null,
                );
            });
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 后台监控：各业务缓存项状态
     *
     * @return array<int, array<string, mixed>>
     */
    public static function inspectEntries()
    {
        $defs = array(
            array(
                'id' => 'api_public',
                'label' => '公开接口列表（MySQL 查询缓存）',
                'key' => self::KEY_API_PUBLIC,
                'ttl_hint' => self::TTL_API_PUBLIC . ' 秒',
            ),
            array(
                'id' => 'frontend_api',
                'label' => '前台接口展示数据',
                'key' => self::KEY_FRONTEND_API,
                'ttl_hint' => self::TTL_FRONTEND_API . ' 秒',
            ),
            array(
                'id' => 'frontend_category',
                'label' => '前台分类标签',
                'key' => self::KEY_FRONTEND_CATEGORY,
                'ttl_hint' => self::TTL_FRONTEND_CATEGORY . ' 秒',
            ),
        );

        $rows = array();
        foreach ($defs as $def) {
            $rows[] = array_merge($def, self::inspectKey($def['key']));
        }
        return $rows;
    }

    /**
     * @param string $logicalKey
     * @return array
     */
    private static function inspectKey($logicalKey)
    {
        $result = array(
            'cached' => false,
            'ttl_seconds' => null,
            'size_bytes' => 0,
            'size_human' => '—',
        );

        if (!self::enabled()) {
            return $result;
        }

        try {
            return RedisService::withClient(function (Redis $redis) use ($logicalKey, $result) {
                $fullKey = RedisService::buildKey($logicalKey);
                $exists = $redis->exists($fullKey);
                if (!$exists) {
                    return $result;
                }

                $raw = $redis->get($fullKey);
                $ttl = (int) $redis->ttl($fullKey);
                $size = is_string($raw) ? strlen($raw) : 0;

                return array(
                    'cached' => true,
                    'ttl_seconds' => $ttl >= 0 ? $ttl : null,
                    'size_bytes' => $size,
                    'size_human' => RedisService::formatBytes($size),
                );
            });
        } catch (Exception $e) {
            return $result;
        }
    }

    /**
     * @param Redis  $redis
     * @param string $statKey
     * @return void
     */
    private static function incrStat(Redis $redis, $statKey)
    {
        $redis->incr(RedisService::buildKey($statKey));
    }
}
