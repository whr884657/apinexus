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
    /** v13.26.46 起后缀 query4：列表字段含转发头、彻底不含耗时，旧缓存作废 */
    const KEY_APILOG_PAGE_PREFIX = 'cache:apilog:query4:';
    /**
     * 今日调用次数逻辑键前缀（完整键 = 前缀 + Y-m-d；见 apilogTodayKey）
     * 兼容监控展示：旧无日期键 cache:apilog:today_count 升级后不再写入
     */
    const KEY_APILOG_TODAY = 'cache:apilog:today_count';
    /** 时间窗内无筛选时的总数缓存前缀 */
    const KEY_APILOG_RANGE_TOTAL_PREFIX = 'cache:apilog:range_total:';
    /** 控制台 / 大屏统计（按 epoch 分代，前缀匹配） */
    const KEY_DASHBOARD_PREFIX = 'cache:dashboard:';
    /** 订单/积分流水时间窗总数 */
    const KEY_ORDERS_RANGE_TOTAL_PREFIX = 'cache:orders:range_total:';
    const KEY_STAT_HITS = 'stats:cache_hits';
    const KEY_STAT_MISSES = 'stats:cache_misses';
    const KEY_USER_STATUS_PREFIX = 'cache:user_status:';
    const KEY_ADMIN_STATUS_PREFIX = 'cache:admin_status:';
    /** 已订阅接口状态的用户列表（变更订阅或封禁后失效） */
    const KEY_SUB_APISTATUS_USERS = 'cache:sub:apistatus:users';
    const KEY_SUB_APISTATUS_USERS_VERSION = 'cache:sub:apistatus:users:version';
    /** 待发的接口状态变更（Redis 列表，不落库） */
    const KEY_SUB_APISTATUS_QUEUE = 'cache:sub:apistatus:queue';

    /** 前台列表短缓存：约 10 秒，保存时仍 invalidateFrontend / forget（E485） */
    const TTL_FRONTEND_API = 10;
    const TTL_FRONTEND_CATEGORY = 10;
    const TTL_FRONTEND_LINK = 10;
    const TTL_FRONTEND_PARTNER = 10;
    /** 赞助商列表与伙伴同级缓存时长 */
    const TTL_FRONTEND_SPONSOR = 10;
    const TTL_FRONTEND_ARTICLE = 10;
    const TTL_FRONTEND_ANNOUNCE = 10;
    const TTL_FRONTEND_MISC = 10;
    const TTL_API_PUBLIC = 10;
    /** 日志查询/列表短 TTL（筛选组合多，禁止 24h） */
    const TTL_APILOG_PAGE = 90;
    /** 全站今日调用次数缓存（控制台 / 大屏 / 前台首页共用；E485） */
    const TTL_APILOG_TODAY = 10;
    /** 今日调用无日期键时的兜底 TTL */
    const TTL_APILOG_STATS = 3600;
    /** 时间窗总数稍长，避免每次进页都 COUNT */
    const TTL_APILOG_RANGE_TOTAL = 180;
    const TTL_ORDERS_RANGE_TOTAL = 180;
    const TTL_USER_STATUS = 30;
    const TTL_ADMIN_STATUS = 30;
    /** 订阅用户名单；改订阅、封禁、改邮箱时 invalidateApiStatusSubscribers */
    const TTL_SUB_APISTATUS_USERS = 600;
    /** 待发队列整体保留 7 天，过期由键 TTL 与消费时按 at 丢弃 */
    const TTL_SUB_APISTATUS_QUEUE = 604800;
    const TTL_SUB_APISTATUS_CLAIM = 900;
    const MAX_SUB_APISTATUS_QUEUE = 300;

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
     * 读缓存；未命中则执行回调，仅当代际号仍为查询前的值时写回。
     * 封禁等失效必须先推进代际再删缓存，避免旧查询把旧状态写回去。
     *
     * @param string   $logicalKey
     * @param string   $versionKey
     * @param int      $ttl
     * @param callable $factory
     * @return mixed
     */
    public static function rememberGuarded($logicalKey, $versionKey, $ttl, $factory)
    {
        if (!self::enabled()) {
            return call_user_func($factory);
        }

        try {
            return RedisService::withClient(function ($redis) use ($logicalKey, $versionKey, $ttl, $factory) {
                $fullKey = RedisService::buildKey($logicalKey);
                $verKey = RedisService::buildKey($versionKey);
                $generation = (int) $redis->get($verKey);
                $raw = $redis->get($fullKey);
                if ($raw !== false && $raw !== '') {
                    $cached = self::safeUnserialize($raw);
                    if (is_array($cached)
                        && isset($cached['g'], $cached['v'])
                        && (int) $cached['g'] === $generation
                    ) {
                        self::incrStat($redis, self::KEY_STAT_HITS);
                        return $cached['v'];
                    }
                }

                self::incrStat($redis, self::KEY_STAT_MISSES);
                $value = call_user_func($factory);
                $payload = serialize(array('g' => $generation, 'v' => $value));
                $script = "local v=redis.call('GET',KEYS[1]); if not v then v='0' end; "
                    . "if v~=ARGV[1] then return 0 end; "
                    . "redis.call('SETEX',KEYS[2],ARGV[3],ARGV[2]); return 1";
                $redis->eval(
                    $script,
                    array($verKey, $fullKey, (string) $generation, $payload, max(1, (int) $ttl)),
                    2
                );
                self::maybeMaintain($redis);
                return $value;
            });
        } catch (Exception $e) {
            return call_user_func($factory);
        }
    }

    /**
     * 推进代际并删除对应缓存。进行中的 rememberGuarded 写回会因代际不符而放弃。
     *
     * @param string $logicalKey
     * @param string $versionKey
     * @return void
     */
    public static function invalidateGuarded($logicalKey, $versionKey)
    {
        if (!self::enabled()) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($logicalKey, $versionKey) {
                $redis->incr(RedisService::buildKey($versionKey));
                $redis->del(RedisService::buildKey($logicalKey));
            });
        } catch (Exception $e) {
            // 缓存有 TTL，数据库仍是权威数据
        }
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
     * 接口状态变更写入待发队列。队列满时拒绝新事件并返回 false，不裁掉旧事件。
     *
     * @param int $apiId
     * @param int $toStatus
     * @return bool
     */
    public static function pushApiStatusNotice($apiId, $toStatus)
    {
        $apiId = (int) $apiId;
        if ($apiId <= 0 || !self::enabled()) {
            return false;
        }
        try {
            $id = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            return false;
        }
        $payload = json_encode(array(
            'id'    => $id,
            'apiid' => $apiId,
            'to'    => (int) $toStatus,
            'at'    => time(),
        ));
        if (!is_string($payload)) {
            return false;
        }
        try {
            return (bool) RedisService::withClient(function ($redis) use ($payload) {
                $key = RedisService::buildKey(self::KEY_SUB_APISTATUS_QUEUE);
                $script = "local n=redis.call('LLEN',KEYS[1]); "
                    . "if n>=tonumber(ARGV[2]) then return 0 end; "
                    . "redis.call('RPUSH',KEYS[1],ARGV[1]); "
                    . "redis.call('EXPIRE',KEYS[1],tonumber(ARGV[3])); return 1";
                return $redis->eval(
                    $script,
                    array($key, $payload, self::MAX_SUB_APISTATUS_QUEUE, self::TTL_SUB_APISTATUS_QUEUE),
                    1
                );
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 原子领取一批待发记录；每条事件以稳定 ID 独立加锁。
     *
     * @param int $limit
     * @return array{ok:bool,events:array<int,array>}
     */
    public static function claimApiStatusNotices($limit)
    {
        $limit = (int) $limit;
        if ($limit <= 0 || !self::enabled()) {
            return array('ok' => false, 'events' => array());
        }
        try {
            return RedisService::withClient(function ($redis) use ($limit) {
                $key = RedisService::buildKey(self::KEY_SUB_APISTATUS_QUEUE);
                $rawRows = $redis->lRange($key, 0, $limit - 1);
                if (!is_array($rawRows)) {
                    return array('ok' => false, 'events' => array());
                }
                $events = array();
                foreach ($rawRows as $raw) {
                    $raw = (string) $raw;
                    $decoded = json_decode($raw, true);
                    $id = is_array($decoded) && !empty($decoded['id'])
                        ? (string) $decoded['id']
                        : 'invalid-' . hash('sha256', $raw);
                    $claimToken = bin2hex(random_bytes(16));
                    $claimKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_QUEUE . ':claim:' . $id);
                    $claimed = $redis->set(
                        $claimKey,
                        $claimToken,
                        array('nx', 'ex' => self::TTL_SUB_APISTATUS_CLAIM)
                    );
                    if (!$claimed) {
                        continue;
                    }
                    $events[] = array(
                        'id' => $id,
                        'apiid' => is_array($decoded) && isset($decoded['apiid']) ? (int) $decoded['apiid'] : 0,
                        'tostatus' => is_array($decoded) && isset($decoded['to']) ? (int) $decoded['to'] : -1,
                        'at' => is_array($decoded) && isset($decoded['at']) ? (int) $decoded['at'] : 0,
                        '_raw' => $raw,
                        '_claim' => $claimToken,
                    );
                }
                return array('ok' => true, 'events' => $events);
            });
        } catch (Exception $e) {
            return array('ok' => false, 'events' => array());
        }
    }

    /**
     * 精确 ack 已成功处理的事件，或释放失败事件的领取锁。
     *
     * @param array<int,array> $events
     * @param bool $ack
     * @return void
     */
    public static function finishApiStatusNotices(array $events, $ack)
    {
        if ($events === array() || !self::enabled()) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($events, $ack) {
                $queueKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_QUEUE);
                foreach ($events as $event) {
                    if (empty($event['_claim']) || empty($event['_raw']) || empty($event['id'])) {
                        continue;
                    }
                    $claimKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_QUEUE . ':claim:' . $event['id']);
                    if ($ack) {
                        $script = "if redis.call('GET',KEYS[2])==ARGV[1] then "
                            . "redis.call('LREM',KEYS[1],1,ARGV[2]); "
                            . "redis.call('DEL',KEYS[2]); return 1 end; return 0";
                        $redis->eval($script, array($queueKey, $claimKey, $event['_claim'], $event['_raw']), 2);
                    } else {
                        $script = "if redis.call('GET',KEYS[1])==ARGV[1] then "
                            . "return redis.call('DEL',KEYS[1]) end; return 0";
                        $redis->eval($script, array($claimKey, $event['_claim']), 1);
                    }
                }
            });
        } catch (Exception $e) {
            // 领取锁过期后可重试；精确 ack 不删除后续事件
        }
    }

    /**
     * 读取订阅名单缓存及其代际号。
     *
     * @return array{available:bool,generation:int,users:array|null}
     */
    public static function apiStatusSubscribersCacheSnapshot()
    {
        $miss = array('available' => false, 'generation' => 0, 'users' => null);
        if (!self::enabled()) {
            return $miss;
        }
        try {
            return RedisService::withClient(function ($redis) {
                $versionKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_USERS_VERSION);
                $generation = (int) $redis->get($versionKey);
                $raw = $redis->get(RedisService::buildKey(self::KEY_SUB_APISTATUS_USERS));
                if ($raw === false || $raw === '') {
                    return array('available' => true, 'generation' => $generation, 'users' => null);
                }
                $cached = self::safeUnserialize($raw);
                if (!is_array($cached)
                    || !isset($cached['generation'], $cached['users'])
                    || (int) $cached['generation'] !== $generation
                    || !is_array($cached['users'])
                ) {
                    return array('available' => true, 'generation' => $generation, 'users' => null);
                }
                return array('available' => true, 'generation' => $generation, 'users' => $cached['users']);
            });
        } catch (Exception $e) {
            return $miss;
        }
    }

    /**
     * 仅在名单缓存仍处于查询开始时的代际下写入。
     *
     * @param int $generation
     * @param array $users
     * @return void
     */
    public static function putApiStatusSubscribersIfCurrent($generation, array $users)
    {
        if (!self::enabled()) {
            return;
        }
        $payload = serialize(array('generation' => (int) $generation, 'users' => $users));
        try {
            RedisService::withClient(function ($redis) use ($generation, $payload) {
                $versionKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_USERS_VERSION);
                $cacheKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_USERS);
                $script = "local v=redis.call('GET',KEYS[1]); if not v then v='0' end; "
                    . "if v~=ARGV[1] then return 0 end; "
                    . "redis.call('SETEX',KEYS[2],ARGV[3],ARGV[2]); return 1";
                $redis->eval(
                    $script,
                    array($versionKey, $cacheKey, (string) (int) $generation, $payload, self::TTL_SUB_APISTATUS_USERS),
                    2
                );
            });
        } catch (Exception $e) {
            // 缓存失败不影响名单查询结果
        }
    }

    /** 使订阅名单缓存失效并推进代际。 */
    public static function invalidateApiStatusSubscribers()
    {
        if (!self::enabled()) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) {
                $versionKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_USERS_VERSION);
                $cacheKey = RedisService::buildKey(self::KEY_SUB_APISTATUS_USERS);
                $redis->incr($versionKey);
                $redis->del($cacheKey);
            });
        } catch (Exception $e) {
            // 缓存有 TTL，数据库仍是权威数据
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
            'q_field'    => isset($opts['q_field']) ? (string) $opts['q_field'] : 'id',
            'ok'         => array_key_exists('ok', $opts) ? $opts['ok'] : null,
            'apiid'      => (int) (isset($opts['apiid']) ? $opts['apiid'] : 0),
            'userid'     => (int) (isset($opts['userid']) ? $opts['userid'] : 0),
            'before_id'  => (int) (isset($opts['before_id']) ? $opts['before_id'] : 0),
            'skip_total' => !empty($opts['skip_total']) ? 1 : 0,
            'date_from'  => isset($opts['date_from']) ? (string) $opts['date_from'] : '',
            'date_to'    => isset($opts['date_to']) ? (string) $opts['date_to'] : '',
            'inc_cold'   => !empty($opts['inc_cold']) ? 1 : 0,
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
            'q'         => isset($opts['q']) ? (string) $opts['q'] : '',
            'q_field'   => isset($opts['q_field']) ? (string) $opts['q_field'] : 'id',
            'ok'        => array_key_exists('ok', $opts) ? $opts['ok'] : null,
            'apiid'     => (int) (isset($opts['apiid']) ? $opts['apiid'] : 0),
            'userid'    => (int) (isset($opts['userid']) ? $opts['userid'] : 0),
            'date_from' => isset($opts['date_from']) ? (string) $opts['date_from'] : '',
            'date_to'   => isset($opts['date_to']) ? (string) $opts['date_to'] : '',
        );
        return self::KEY_APILOG_RANGE_TOTAL_PREFIX . 'f:' . md5(json_encode($norm));
    }

    /**
     * 今日调用次数逻辑键（按自然日；过 0 点自动换键）
     *
     * @param string $day Y-m-d，空则今天
     * @return string
     */
    public static function apilogTodayKey($day = '')
    {
        $day = trim((string) $day);
        if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = date('Y-m-d');
        }
        return self::KEY_APILOG_TODAY . ':' . $day;
    }

    /**
     * 距今日结束的秒数（作今日计数键 TTL；至少 60）
     *
     * @return int
     */
    public static function ttlUntilEndOfDay()
    {
        $end = strtotime('tomorrow');
        if ($end === false) {
            return (int) self::TTL_APILOG_STATS;
        }
        return max(60, min(86400, $end - time()));
    }

    /**
     * 今日调用 +N（写入路径实时更新；键不存在则从 0 起增并设到期）
     *
     * @param int $by
     * @return void
     */
    public static function bumpApiLogToday($by = 1)
    {
        $by = (int) $by;
        if ($by === 0 || !self::enabled()) {
            return;
        }
        $logicalKey = self::apilogTodayKey();
        $ttl = (int) self::TTL_APILOG_TODAY;
        try {
            RedisService::withClient(function ($redis) use ($logicalKey, $by, $ttl) {
                $fullKey = RedisService::buildKey($logicalKey);
                // 键不存在时禁止从 0 自增（会盖住真实总数）；留给 remember 回源
                if (!$redis->exists($fullKey)) {
                    return;
                }
                if ($by > 0) {
                    $redis->incrBy($fullKey, $by);
                } else {
                    $redis->decrBy($fullKey, abs($by));
                }
                $redis->expire($fullKey, $ttl);
            });
        } catch (Exception $e) {
            // 忽略：读路径可回源库
        }
    }

    /**
     * 写入今日调用绝对数（冷启动 / 回填后重建）
     *
     * @param int    $count
     * @param string $day
     * @return void
     */
    public static function putApiLogToday($count, $day = '')
    {
        $count = max(0, (int) $count);
        self::put(self::apilogTodayKey($day), $count, (int) self::TTL_APILOG_TODAY);
    }

    /**
     * 清除今日调用计数键（含旧无日期键 + 当日键；回填后用）
     *
     * @return void
     */
    public static function forgetApiLogToday()
    {
        self::forget(self::KEY_APILOG_TODAY);
        self::forget(self::apilogTodayKey());
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

        self::forgetApiLogToday();

        try {
            return (int) RedisService::withClient(function ($redis) {
                $deleted = 0;
                foreach (array(
                    self::KEY_APILOG_PAGE_PREFIX,
                    self::KEY_APILOG_RANGE_TOTAL_PREFIX,
                    self::KEY_APILOG_TODAY . ':',
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
                'id' => 'sub_apistatus_users',
                'label' => '接口状态订阅用户',
                'desc' => '已打开订阅的用户名单；改订阅、封禁、改邮箱后推进代际并清除',
                'key' => self::KEY_SUB_APISTATUS_USERS,
                'ttl_hint' => self::TTL_SUB_APISTATUS_USERS . ' 秒',
                'chart_color' => '#a855f7',
            ),
            array(
                'id' => 'sub_apistatus_queue',
                'label' => '接口状态待发邮件',
                'desc' => '接口上架、下架、维护待发队列，计划任务消费',
                'key' => self::KEY_SUB_APISTATUS_QUEUE,
                'ttl_hint' => '7 天',
                'chart_color' => '#7c3aed',
            ),
            array(
                'id' => 'user_status',
                'label' => '用户封禁状态',
                'desc' => '登录检查与带密钥接口热路径共用；封禁/解禁/删号推进代际并立即失效',
                'key' => self::KEY_USER_STATUS_PREFIX,
                'ttl_hint' => self::TTL_USER_STATUS . ' 秒',
                'pattern' => true,
                'chart_color' => '#f43f5e',
            ),
            array(
                'id' => 'admin_status',
                'label' => '管理员状态',
                'desc' => '后台登录检查；与用户状态键隔离；禁用时推进代际',
                'key' => self::KEY_ADMIN_STATUS_PREFIX,
                'ttl_hint' => self::TTL_ADMIN_STATUS . ' 秒',
                'pattern' => true,
                'chart_color' => '#fb7185',
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
                'desc' => '键 cache:apilog:today_count:Y-m-d；写入 bump；冷启 remember 回源 statday/COUNT',
                'key' => self::KEY_APILOG_TODAY . ':',
                'ttl_hint' => self::TTL_APILOG_TODAY . ' 秒',
                'pattern' => true,
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
                'ttl_hint' => '分层约 20～1800 秒（live≈20；今日≈180；24h≈600；7日≈1800；geo≈180）',
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
            array(
                'id' => 'notify_points_insufficient',
                'label' => '积分不足邮件去重',
                'desc' => '余额不足以调用接口时的邮件提醒标记（每用户）',
                'key' => 'notify:points_insufficient:',
                'ttl_hint' => '86400 秒（24 小时）',
                'pattern' => true,
                'chart_color' => '#ef4444',
            ),
            array(
                'id' => 'ops_site_promo',
                'label' => '全站优惠镜像',
                'desc' => '用户激励全站折扣/免费（权威在 vs_config；本键为镜像，TTL=剩余有效秒数）',
                'key' => 'ops:incentive:site_promo',
                'ttl_hint' => '随 expire_at 剩余时间（最长约 31 天封顶）',
                'pattern' => false,
                'chart_color' => '#10b981',
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
