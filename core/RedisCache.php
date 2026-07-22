<?php
/**
 * 文件：core/RedisCache.php
 * 作用：ApiNexus 业务数据 Redis 缓存（读写分离 MySQL，降低高频查询与限流写入压力）
 */

class RedisCache
{
    const KEY_FRONTEND_API = 'cache:frontend:api_list';
    const KEY_FRONTEND_CATEGORY = 'cache:frontend:category_tags';
    const KEY_FRONTEND_LINK = 'cache:frontend:link_list';
    const KEY_FRONTEND_PARTNER = 'cache:frontend:partner_list';
    const KEY_FRONTEND_SPONSOR = 'cache:frontend:sponsor_list';
    const KEY_API_PUBLIC = 'cache:api:public_list';
    /** 日志查询结果缓存键前缀（后台列表 / 后续图表等凡读 apilog 均可复用） */
    const KEY_APILOG_PAGE_PREFIX = 'cache:apilog:query:';
    /** 今日调用次数等汇总统计 */
    const KEY_APILOG_TODAY = 'cache:apilog:today_count';
    /** 时间窗内无筛选时的总数缓存前缀 */
    const KEY_APILOG_RANGE_TOTAL_PREFIX = 'cache:apilog:range_total:';
    /** 订单/积分流水时间窗总数 */
    const KEY_ORDERS_RANGE_TOTAL_PREFIX = 'cache:orders:range_total:';
    const KEY_STAT_HITS = 'stats:cache_hits';
    const KEY_STAT_MISSES = 'stats:cache_misses';

    const TTL_FRONTEND_API = 120;
    const TTL_FRONTEND_CATEGORY = 300;
    const TTL_FRONTEND_LINK = 300;
    const TTL_FRONTEND_PARTNER = 300;
    const TTL_API_PUBLIC = 120;
    /** 日志查询/列表短 TTL，降低大表反复扫库 */
    const TTL_APILOG_PAGE = 45;
    const TTL_APILOG_STATS = 30;
    /** 时间窗总数稍长，避免每次进页都 COUNT */
    const TTL_APILOG_RANGE_TOTAL = 90;
    const TTL_ORDERS_RANGE_TOTAL = 90;

    const MAX_RATE_LIMIT_KEYS = 2000;
    const STAT_MAX_VALUE = 100000000;
    const STAT_TTL_SECONDS = 2592000;

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
                self::maybeMaintain($redis);
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
        self::forget(self::KEY_FRONTEND_LINK);
        self::forget(self::KEY_FRONTEND_PARTNER);
        self::forget(self::KEY_API_PUBLIC);
    }

    /**
     * 日志分页缓存键（按筛选条件摘要）
     *
     * @param array $opts
     * @return string
     */
    public static function apilogPageKey(array $opts)
    {
        $norm = array(
            'page'      => (int) (isset($opts['page']) ? $opts['page'] : 1),
            'pagesize'  => (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20),
            'q'         => isset($opts['q']) ? (string) $opts['q'] : '',
            'ok'        => array_key_exists('ok', $opts) ? $opts['ok'] : null,
            'apiid'     => (int) (isset($opts['apiid']) ? $opts['apiid'] : 0),
            'days'      => (int) (isset($opts['days']) ? $opts['days'] : 7),
            'before_id' => (int) (isset($opts['before_id']) ? $opts['before_id'] : 0),
        );
        return self::KEY_APILOG_PAGE_PREFIX . md5(json_encode($norm));
    }

    /**
     * 时间窗内无筛选总数缓存键
     *
     * @param int $days
     * @return string
     */
    public static function apilogRangeTotalKey($days)
    {
        return self::KEY_APILOG_RANGE_TOTAL_PREFIX . max(1, (int) $days);
    }

    /**
     * 订单/积分流水时间窗总数缓存键
     *
     * @param array $opts
     * @return string
     */
    public static function ordersRangeTotalKey(array $opts)
    {
        $norm = array(
            'scope'  => isset($opts['scope']) ? (string) $opts['scope'] : '',
            'userid' => (int) (isset($opts['userid']) ? $opts['userid'] : 0),
            'status' => array_key_exists('status', $opts) ? $opts['status'] : null,
            'days'   => (int) (isset($opts['days']) ? $opts['days'] : 30),
        );
        return self::KEY_ORDERS_RANGE_TOTAL_PREFIX . md5(json_encode($norm));
    }

    /**
     * 清理订单/积分流水列表相关缓存
     *
     * @return int
     */
    public static function invalidateOrders()
    {
        if (!self::enabled()) {
            return 0;
        }
        try {
            return (int) RedisService::withClient(function (Redis $redis) {
                $deleted = 0;
                $pattern = RedisService::buildKey(self::KEY_ORDERS_RANGE_TOTAL_PREFIX) . '*';
                $it = null;
                do {
                    $keys = $redis->scan($it, $pattern, 80);
                    if ($keys === false) {
                        break;
                    }
                    if (!empty($keys)) {
                        $deleted += (int) $redis->del($keys);
                    }
                } while ($it !== 0 && $it !== null);
                return $deleted;
            });
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 清理日志列表缓存（写入新日志后调用；无匹配键时静默）
     *
     * @return int 删除键数
     */
    public static function invalidateApiLog()
    {
        if (!self::enabled()) {
            return 0;
        }

        self::forget(self::KEY_APILOG_TODAY);

        try {
            return (int) RedisService::withClient(function (Redis $redis) {
                $deleted = 0;
                foreach (array(self::KEY_APILOG_PAGE_PREFIX, self::KEY_APILOG_RANGE_TOTAL_PREFIX) as $prefix) {
                    $pattern = RedisService::buildKey($prefix) . '*';
                    $it = null;
                    do {
                        $keys = $redis->scan($it, $pattern, 80);
                        if ($keys === false) {
                            break;
                        }
                        if (!empty($keys)) {
                            $deleted += (int) $redis->del($keys);
                        }
                    } while ($it !== 0 && $it !== null);
                }
                return $deleted;
            });
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 键空间维护：清理过期限流键、限制键数量、防止统计无限增长
     *
     * @return array{pruned:int,capped:bool}
     */
    public static function maintainKeyspace()
    {
        if (!self::enabled()) {
            return array('pruned' => 0, 'capped' => false);
        }

        if (random_int(1, 8) > 2) {
            return array('pruned' => 0, 'capped' => false);
        }

        try {
            return RedisService::withClient(function (Redis $redis) {
                return self::runMaintain($redis);
            });
        } catch (Exception $e) {
            return array('pruned' => 0, 'capped' => false);
        }
    }

    /**
     * @param Redis $redis
     * @return void
     */
    private static function maybeMaintain(Redis $redis)
    {
        if (random_int(1, 12) > 1) {
            return;
        }
        self::runMaintain($redis);
    }

    /**
     * @param Redis $redis
     * @return array{pruned:int,capped:bool}
     */
    private static function runMaintain(Redis $redis)
    {
        $pruned = RedisService::pruneRateLimitKeys($redis, self::MAX_RATE_LIMIT_KEYS);
        $capped = self::capStatKeys($redis);
        return array('pruned' => $pruned, 'capped' => $capped);
    }

    /**
     * @param Redis $redis
     * @return bool
     */
    private static function capStatKeys(Redis $redis)
    {
        $capped = false;
        foreach (array(self::KEY_STAT_HITS, self::KEY_STAT_MISSES) as $statKey) {
            $fullKey = RedisService::buildKey($statKey);
            if (!$redis->exists($fullKey)) {
                continue;
            }
            $ttl = (int) $redis->ttl($fullKey);
            if ($ttl < 0) {
                $redis->expire($fullKey, self::STAT_TTL_SECONDS);
            }
            $value = (int) $redis->get($fullKey);
            if ($value > self::STAT_MAX_VALUE) {
                $redis->setex($fullKey, self::STAT_TTL_SECONDS, '0');
                $capped = true;
            }
        }
        return $capped;
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
                'id' => 'apilog_query',
                'label' => 'API 调用日志',
                'desc' => '日志查询页、今日调用统计等读库结果（短时缓存）',
                'key' => self::KEY_APILOG_PAGE_PREFIX,
                'ttl_hint' => self::TTL_APILOG_PAGE . ' 秒',
                'pattern' => true,
                'chart_color' => '#8b5cf6',
            ),
            array(
                'id' => 'apilog_range_total',
                'label' => '日志时间窗总数',
                'desc' => '无筛选时近期日志条数（避免每次进页全表 COUNT）',
                'key' => self::KEY_APILOG_RANGE_TOTAL_PREFIX,
                'ttl_hint' => self::TTL_APILOG_RANGE_TOTAL . ' 秒',
                'pattern' => true,
                'chart_color' => '#a78bfa',
            ),
            array(
                'id' => 'apilog_today',
                'label' => '今日调用次数',
                'desc' => '首页等展示的「今日调用」汇总数字',
                'key' => self::KEY_APILOG_TODAY,
                'ttl_hint' => self::TTL_APILOG_STATS . ' 秒',
                'chart_color' => '#ec4899',
            ),
        );

        $rows = array();
        foreach ($defs as $def) {
            if (!empty($def['pattern'])) {
                $rows[] = array_merge($def, self::inspectKeyPattern($def['key']));
            } else {
                $rows[] = array_merge($def, self::inspectKey($def['key']));
            }
        }
        return $rows;
    }

    /**
     * 前缀键族占用概览（日志分页等多键缓存）
     *
     * @param string $logicalPrefix
     * @return array
     */
    private static function inspectKeyPattern($logicalPrefix)
    {
        $result = array(
            'cached' => false,
            'ttl_seconds' => null,
            'size_bytes' => 0,
            'size_human' => '—',
            'key_count' => 0,
        );

        if (!self::enabled()) {
            return $result;
        }

        try {
            return RedisService::withClient(function (Redis $redis) use ($logicalPrefix, $result) {
                $pattern = RedisService::buildKey($logicalPrefix) . '*';
                $count = 0;
                $size = 0;
                $minTtl = null;
                $it = null;
                do {
                    $keys = $redis->scan($it, $pattern, 80);
                    if ($keys === false) {
                        break;
                    }
                    foreach ($keys as $fullKey) {
                        $count++;
                        $raw = $redis->get($fullKey);
                        if (is_string($raw)) {
                            $size += strlen($raw);
                        }
                        $ttl = (int) $redis->ttl($fullKey);
                        if ($ttl >= 0 && ($minTtl === null || $ttl < $minTtl)) {
                            $minTtl = $ttl;
                        }
                    }
                } while ($it !== 0 && $it !== null);

                if ($count <= 0) {
                    return $result;
                }

                return array(
                    'cached' => true,
                    'ttl_seconds' => $minTtl,
                    'size_bytes' => $size,
                    'size_human' => RedisService::formatBytes($size),
                    'key_count' => $count,
                );
            });
        } catch (Exception $e) {
            return $result;
        }
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
