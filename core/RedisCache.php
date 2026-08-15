<?php
/**
 * 文件：core/RedisCache.php
 * 作用：ApiNexus 业务数据 Redis 缓存（读写分离 MySQL，降低高频查询与限流写入压力）
 */

class RedisCache
{
    /** @var object|null safeUnserialize 失败哨兵（区分合法 false） */
    private static $unserializeMiss = null;

    const KEY_FRONTEND_API = 'cache:frontend:api_list:v2';
    const KEY_FRONTEND_CATEGORY = 'cache:frontend:category_tags';
    const KEY_FRONTEND_LINK = 'cache:frontend:link_list';
    const KEY_FRONTEND_PARTNER = 'cache:frontend:partner_list';
    const KEY_FRONTEND_SPONSOR = 'cache:frontend:sponsor_list';
    const KEY_FRONTEND_ARTICLE = 'cache:frontend:article_list';
    const KEY_FRONTEND_ANNOUNCE = 'cache:frontend:announce_list';
    const KEY_FRONTEND_MISC_PREFIX = 'cache:frontend:misc:';
    const KEY_IPLOC_PREFIX = 'cache:iploc:';
    const KEY_API_PUBLIC = 'cache:api:public_list';
    /** 日志查询结果缓存键前缀（后台列表 / 后续图表等凡读 apilog 均可复用） */
    const KEY_APILOG_PAGE_PREFIX = 'cache:apilog:query:';
    /** 今日调用次数等汇总统计 */
    const KEY_APILOG_TODAY = 'cache:apilog:today_count';
    /** 时间窗内无筛选时的总数缓存前缀 */
    const KEY_APILOG_RANGE_TOTAL_PREFIX = 'cache:apilog:range_total:';
    /** 控制台 / 大屏统计（按 epoch 分代，前缀匹配） */
    const KEY_DASHBOARD_PREFIX = 'cache:dashboard:';
    /** 订单/积分流水时间窗总数 */
    const KEY_ORDERS_RANGE_TOTAL_PREFIX = 'cache:orders:range_total:';
    const KEY_STAT_HITS = 'stats:cache_hits';
    const KEY_STAT_MISSES = 'stats:cache_misses';

    const TTL_FRONTEND_API = 120;
    const TTL_FRONTEND_CATEGORY = 300;
    const TTL_FRONTEND_LINK = 300;
    const TTL_FRONTEND_PARTNER = 300;
    /** 赞助商列表与伙伴同级缓存时长 */
    const TTL_FRONTEND_SPONSOR = 300;
    const TTL_FRONTEND_ARTICLE = 120;
    const TTL_FRONTEND_ANNOUNCE = 60;
    const TTL_FRONTEND_MISC = 120;
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
            return RedisService::withClient(function ($redis) use ($logicalKey, $ttl, $factory) {
                $fullKey = RedisService::buildKey($logicalKey);
                $raw = $redis->get($fullKey);
                if ($raw !== false && $raw !== '') {
                    self::incrStat($redis, self::KEY_STAT_HITS);
                    $value = self::safeUnserialize($raw);
                    if ($value !== self::$unserializeMiss) {
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
     * 读取已序列化缓存；未命中返回 null
     *
     * @param string $logicalKey
     * @return mixed|null
     */
    public static function get($logicalKey)
    {
        if (!self::enabled()) {
            return null;
        }
        try {
            return RedisService::withClient(function ($redis) use ($logicalKey) {
                $raw = $redis->get(RedisService::buildKey($logicalKey));
                if ($raw === false || $raw === '') {
                    return null;
                }
                $value = self::safeUnserialize($raw);
                if ($value === self::$unserializeMiss) {
                    return null;
                }
                return $value;
            });
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 写入序列化缓存
     *
     * @param string $logicalKey
     * @param mixed  $value
     * @param int    $ttl
     * @return void
     */
    public static function put($logicalKey, $value, $ttl)
    {
        if (!self::enabled()) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($logicalKey, $value, $ttl) {
                $redis->setex(
                    RedisService::buildKey($logicalKey),
                    max(1, (int) $ttl),
                    serialize($value)
                );
            });
        } catch (Exception $e) {
            // 忽略
        }
    }

    /**
     * 写入已序列化缓存（与 put 同义；保留 set 别名避免业务误调）
     *
     * @param string $logicalKey
     * @param mixed  $value
     * @param int    $ttl
     * @return void
     */
    public static function set($logicalKey, $value, $ttl)
    {
        self::put($logicalKey, $value, $ttl);
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
            RedisService::withClient(function ($redis) use ($logicalKey) {
                $redis->del(RedisService::buildKey($logicalKey));
            });
        } catch (Exception $e) {
            // 忽略
        }
    }

    /**
     * 安全反序列化：禁止还原对象（防 POP 链 RCE）
     *
     * @param string $raw
     * @return mixed 失败返回 self::$unserializeMiss
     */
    private static function safeUnserialize($raw)
    {
        if (self::$unserializeMiss === null) {
            self::$unserializeMiss = new stdClass();
        }
        if (!is_string($raw) || $raw === '') {
            return self::$unserializeMiss;
        }
        if ($raw === 'b:0;') {
            return false;
        }
        $opts = array('allowed_classes' => false);
        $value = @unserialize($raw, $opts);
        if ($value === false && $raw !== 'b:0;') {
            return self::$unserializeMiss;
        }
        // 拒绝意外还原出的对象（旧 PHP / 异常载荷）
        if (is_object($value)) {
            return self::$unserializeMiss;
        }
        return $value;
    }

    /**
     * 前台/API 相关缓存一并失效（分类、接口列表变更时调用）
     *
     * @return void
     */
    public static function invalidateFrontend()
    {
        self::forget(self::KEY_FRONTEND_API);
        self::forget('cache:frontend:api_list'); // 旧键（绝对域名烤死）
        self::forget(self::KEY_FRONTEND_CATEGORY);
        self::forget(self::KEY_FRONTEND_LINK);
        self::forget(self::KEY_FRONTEND_PARTNER);
        self::forget(self::KEY_FRONTEND_SPONSOR);
        self::forget(self::KEY_FRONTEND_ARTICLE);
        self::forget(self::KEY_FRONTEND_ANNOUNCE);
        self::forget(self::KEY_API_PUBLIC);
        // 杂项前台缓存（公告弹窗、关于页摘要等）
        if (self::enabled()) {
            try {
                RedisService::withClient(function ($redis) {
                    $pattern = RedisService::buildKey(self::KEY_FRONTEND_MISC_PREFIX) . '*';
                    $it = null;
                    do {
                        $keys = $redis->scan($it, $pattern, 80);
                        if ($keys === false) {
                            break;
                        }
                        if (!empty($keys)) {
                            $redis->del($keys);
                        }
                    } while ($it !== 0 && $it !== null);
                });
            } catch (Exception $e) {
                // 忽略
            }
        }
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
            'page'       => (int) (isset($opts['page']) ? $opts['page'] : 1),
            'pagesize'   => (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20),
            'q'          => isset($opts['q']) ? (string) $opts['q'] : '',
            'ok'         => array_key_exists('ok', $opts) ? $opts['ok'] : null,
            'apiid'      => (int) (isset($opts['apiid']) ? $opts['apiid'] : 0),
            'userid'     => (int) (isset($opts['userid']) ? $opts['userid'] : 0),
            'before_id'  => (int) (isset($opts['before_id']) ? $opts['before_id'] : 0),
            'skip_total' => !empty($opts['skip_total']) ? 1 : 0,
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
     * 订单/积分流水筛选总数缓存键（不含 before_id / 天数窗）
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
            'q'      => isset($opts['q']) ? (string) $opts['q'] : '',
        );
        return self::KEY_ORDERS_RANGE_TOTAL_PREFIX . md5(json_encode($norm));
    }

    /**
     * 日志筛选总数缓存键（不含 before_id）
     *
     * @param array $opts
     * @return string
     */
    public static function apilogFilterTotalKey(array $opts)
    {
        $norm = array(
            'q'      => isset($opts['q']) ? (string) $opts['q'] : '',
            'ok'     => array_key_exists('ok', $opts) ? $opts['ok'] : null,
            'apiid'  => (int) (isset($opts['apiid']) ? $opts['apiid'] : 0),
            'userid' => (int) (isset($opts['userid']) ? $opts['userid'] : 0),
        );
        return self::KEY_APILOG_RANGE_TOTAL_PREFIX . 'f:' . md5(json_encode($norm));
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
            return (int) RedisService::withClient(function ($redis) {
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
            return (int) RedisService::withClient(function ($redis) {
                $deleted = 0;
                foreach (array(
                    self::KEY_APILOG_PAGE_PREFIX,
                    self::KEY_APILOG_RANGE_TOTAL_PREFIX,
                    'cache:userapilog:',
                ) as $prefix) {
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
            return RedisService::withClient(function ($redis) {
                return self::runMaintain($redis);
            });
        } catch (Exception $e) {
            return array('pruned' => 0, 'capped' => false);
        }
    }

    /**
     * @param object $redis phpredis 客户端
     * @return void
     */
    private static function maybeMaintain($redis)
    {
        if (random_int(1, 12) > 1) {
            return;
        }
        self::runMaintain($redis);
    }

    /**
     * @param object $redis phpredis 客户端
     * @return array{pruned:int,capped:bool}
     */
    private static function runMaintain($redis)
    {
        $pruned = RedisService::pruneRateLimitKeys($redis, self::MAX_RATE_LIMIT_KEYS);
        $capped = self::capStatKeys($redis);
        return array('pruned' => $pruned, 'capped' => $capped);
    }

    /**
     * @param object $redis phpredis 客户端
     * @return bool
     */
    private static function capStatKeys($redis)
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
            return RedisService::withClient(function ($redis) {
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
     * 后台监控：各业务缓存项状态（中文名称；搜索可匹配逻辑键）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function inspectEntries()
    {
        $defs = array(
            array(
                'id' => 'frontend_api',
                'label' => '前台接口列表',
                'desc' => '主题首页 / 全部接口等；缓存 call_path，域名按访问入口重绑',
                'key' => self::KEY_FRONTEND_API,
                'ttl_hint' => self::TTL_FRONTEND_API . ' 秒',
                'chart_color' => '#3b82f6',
            ),
            array(
                'id' => 'frontend_category',
                'label' => '前台分类标签',
                'desc' => '接口分类筛选标签',
                'key' => self::KEY_FRONTEND_CATEGORY,
                'ttl_hint' => self::TTL_FRONTEND_CATEGORY . ' 秒',
                'chart_color' => '#06b6d4',
            ),
            array(
                'id' => 'frontend_link',
                'label' => '友情链接',
                'desc' => '前台友链展示',
                'key' => self::KEY_FRONTEND_LINK,
                'ttl_hint' => self::TTL_FRONTEND_LINK . ' 秒',
                'chart_color' => '#14b8a6',
            ),
            array(
                'id' => 'frontend_partner',
                'label' => '合作伙伴',
                'desc' => '前台合作伙伴展示',
                'key' => self::KEY_FRONTEND_PARTNER,
                'ttl_hint' => self::TTL_FRONTEND_PARTNER . ' 秒',
                'chart_color' => '#10b981',
            ),
            array(
                'id' => 'frontend_sponsor',
                'label' => '赞助名单',
                'desc' => '前台赞助展示',
                'key' => self::KEY_FRONTEND_SPONSOR,
                'ttl_hint' => self::TTL_FRONTEND_SPONSOR . ' 秒',
                'chart_color' => '#84cc16',
            ),
            array(
                'id' => 'frontend_article',
                'label' => '文章列表',
                'desc' => '前台文章列表短时缓存',
                'key' => self::KEY_FRONTEND_ARTICLE,
                'ttl_hint' => self::TTL_FRONTEND_ARTICLE . ' 秒',
                'chart_color' => '#0ea5e9',
            ),
            array(
                'id' => 'frontend_announce',
                'label' => '公告列表',
                'desc' => '前台公告与弹窗数据',
                'key' => self::KEY_FRONTEND_ANNOUNCE,
                'ttl_hint' => self::TTL_FRONTEND_ANNOUNCE . ' 秒',
                'chart_color' => '#f97316',
            ),
            array(
                'id' => 'frontend_misc',
                'label' => '其他前台数据',
                'desc' => '杂项短时缓存（关于页、贡献者等不便单列的项）',
                'key' => self::KEY_FRONTEND_MISC_PREFIX,
                'ttl_hint' => self::TTL_FRONTEND_MISC . ' 秒',
                'pattern' => true,
                'chart_color' => '#64748b',
            ),
            array(
                'id' => 'iploc_cache',
                'label' => 'IP 归属地',
                'desc' => '按 IP 缓存的外网解析结果',
                'key' => self::KEY_IPLOC_PREFIX,
                'ttl_hint' => '约 1 天',
                'pattern' => true,
                'chart_color' => '#e11d48',
            ),
            array(
                'id' => 'api_public',
                'label' => '公开接口缓存',
                'desc' => '已通过审核的公开接口集合',
                'key' => self::KEY_API_PUBLIC,
                'ttl_hint' => self::TTL_API_PUBLIC . ' 秒',
                'chart_color' => '#22c55e',
            ),
            array(
                'id' => 'apilog_query',
                'label' => '调用日志查询',
                'desc' => '后台日志列表短时结果',
                'key' => self::KEY_APILOG_PAGE_PREFIX,
                'ttl_hint' => self::TTL_APILOG_PAGE . ' 秒',
                'pattern' => true,
                'chart_color' => '#8b5cf6',
            ),
            array(
                'id' => 'apilog_range_total',
                'label' => '日志条数汇总',
                'desc' => '无筛选时的日志总数缓存',
                'key' => self::KEY_APILOG_RANGE_TOTAL_PREFIX,
                'ttl_hint' => self::TTL_APILOG_RANGE_TOTAL . ' 秒',
                'pattern' => true,
                'chart_color' => '#a78bfa',
            ),
            array(
                'id' => 'apilog_today',
                'label' => '今日调用次数',
                'desc' => '优先读 statday 当日行；无表时回退 apilog COUNT',
                'key' => self::KEY_APILOG_TODAY,
                'ttl_hint' => self::TTL_APILOG_STATS . ' 秒',
                'chart_color' => '#ec4899',
            ),
            array(
                'id' => 'statday_topmap',
                'label' => '日聚合 TOP 计数',
                'desc' => 'statday 当日各接口调用 Hash（刷入 topjson）',
                'key' => 'cache:statday:topmap:',
                'ttl_hint' => '约 2 天',
                'pattern' => true,
                'chart_color' => '#14b8a6',
            ),
            array(
                'id' => 'dashboard_stats',
                'label' => '控制台/大屏统计',
                'desc' => '控制台 KPI/趋势；大屏 screen_full、geo_dist_today/live、iploc 城市计数等',
                'key' => self::KEY_DASHBOARD_PREFIX,
                'ttl_hint' => '分层 8～300 秒（screen_full≈60；geo_live≈轮询间隔）',
                'pattern' => true,
                'chart_color' => '#6366f1',
            ),
            array(
                'id' => 'orders_range_total',
                'label' => '订单/积分总数',
                'desc' => '财务列表搜索条件下的总数缓存',
                'key' => self::KEY_ORDERS_RANGE_TOTAL_PREFIX,
                'ttl_hint' => self::TTL_ORDERS_RANGE_TOTAL . ' 秒',
                'pattern' => true,
                'chart_color' => '#f59e0b',
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
            return RedisService::withClient(function ($redis) use ($logicalPrefix, $result) {
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
            return RedisService::withClient(function ($redis) use ($logicalKey, $result) {
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
     * @param object $redis phpredis 客户端
     * @param string $statKey
     * @return void
     */
    private static function incrStat($redis, $statKey)
    {
        $redis->incr(RedisService::buildKey($statKey));
    }
}
