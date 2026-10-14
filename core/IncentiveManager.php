<?php
/**
 * 文件：core/IncentiveManager.php
 * 作用：运营中心·用户激励
 * ① 积分赠送：筛选分片+进度 → Redis 暂存用户 ID → 分批赠送
 * ② 全站优惠：vs_config 单键权威 + Redis 镜像；调用时按活动免扣/折扣扣费（绝不改 api 表价）
 *
 * 入账仅写 orders（KIND_ADMIN_ADD + 自定义 remark）。
 * 禁止扫 apilog；禁止再拆 Filter/Job/Notify；禁止新建激励任务/明细表。
 */

class IncentiveManager
{
    /** 单次活动最多入账人数 */
    const SYNC_MAX = 2000;
    /** 赠送：每拍入账人数（短请求 + 进度条） */
    const TICK_SIZE = 40;
    /** 筛选：每拍扫描行数（按 id 游标；进度=已扫/候选） */
    const FILTER_TICK_SIZE = 250;
    const AMOUNT_MAX = 10000;
    const REMARK_MAX = 120;
    const TAG_MAX = 32;
    const SAMPLE_MAX = 20;
    /**
     * 按 stat7 过滤前，SQL 候选人数上限；过大则要求先收窄条件，避免全表读 mediumtext 卡死
     */
    const STAT7_CANDIDATE_MAX = 15000;

    /** Redis：赠送互斥锁 */
    const REDIS_LOCK = 'ops:incentive:gift:lock';
    /** Redis：待赠用户 ID（LIST） */
    const REDIS_IDS = 'ops:incentive:gift:ids';
    /** Redis：赠送进度 meta */
    const REDIS_META = 'ops:incentive:gift:meta';
    /** Redis：筛选命中 ID（LIST，完成后交给赠送） */
    const REDIS_FILTER_IDS = 'ops:incentive:filter:ids';
    /** Redis：筛选游标/进度 meta */
    const REDIS_FILTER_META = 'ops:incentive:filter:meta';
    /** 邮件群发筛选（与激励隔离） */
    const REDIS_MAIL_FILTER_IDS = 'ops:mail:filter:ids';
    const REDIS_MAIL_FILTER_META = 'ops:mail:filter:meta';
    const FILTER_SCOPE_INCENTIVE = 'incentive';
    const FILTER_SCOPE_MAIL = 'mail';
    const REDIS_TTL = 900;
    const MYSQL_LOCK = 'vs_incentive_gift';

    /** 全站优惠：vs_config 单键（JSON） */
    const PROMO_CONFIG_KEY = 'ops_site_promo';
    /** 全站优惠：Redis 镜像逻辑键 */
    const REDIS_SITE_PROMO = 'ops:incentive:site_promo';
    const PROMO_LABEL_MAX = 16;
    /** 镜像 TTL 上限（秒）；权威过期仍看 expire_at */
    const PROMO_REDIS_TTL_CAP = 2678400;
    const PROMO_MIN_SECONDS = 300;
    const PROMO_MAX_SECONDS = 31622400;

    /** @var array|null 请求内全站优惠解析缓存 */
    private static $promoResolveCache = null;
    /** @var string 当前筛选 Redis 作用域 incentive|mail */
    private static $activeFilterScope = 'incentive';
    /** @var int 当前筛选所属管理员（键分桶，防互踩） */
    private static $activeFilterAdminId = 0;

    /**
     * 写操作：必须后台管理员已登录，且 caller 传入的 adminId 与会话一致
     *
     * @param int $adminId
     * @return array{ok:bool,msg:string}|null  null=已通过
     */
    private static function requireAdminCaller($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0
            || !class_exists('Auth')
            || !Auth::check()
            || Auth::id() !== $adminId
        ) {
            return array('ok' => false, 'msg' => '需要管理员登录后才能操作');
        }
        return null;
    }

    /**
     * 从 POST/数组归一筛选条件
     *
     * @param array $raw
     * @return array{ok:bool,msg?:string,filter?:array,warnings?:string[]}
     */
    public static function normalizeFilter(array $raw)
    {
        $warnings = array();
        $tags = self::parseFilterTags(isset($raw['tags']) ? $raw['tags'] : (isset($raw['filter_tags']) ? $raw['filter_tags'] : ''));
        $tagSet = array();
        foreach ($tags as $t) {
            $tagSet[$t] = true;
        }

        $hasAll = isset($tagSet['all']);
        $hasDev = isset($tagSet['developer']);
        $hasUser = isset($tagSet['user']);
        $audienceCount = ($hasAll ? 1 : 0) + ($hasDev ? 1 : 0) + ($hasUser ? 1 : 0);
        if ($audienceCount > 1) {
            return array(
                'ok'  => false,
                'msg' => '筛选条件冲突：「全部用户 / 仅开发者 / 仅普通用户」只能选其一',
            );
        }
        $audience = $hasDev ? 'developer' : ($hasUser ? 'user' : 'all');

        $hasLogin7 = isset($tagSet['login_7']);
        $hasLogin30 = isset($tagSet['login_30']);
        if ($hasLogin7 && $hasLogin30) {
            return array(
                'ok'  => false,
                'msg' => '筛选条件冲突：「近 7 日登录」与「近 30 日登录」不能同时选择',
            );
        }

        $hasCalls = isset($tagSet['calls_7']);
        $hasCallsActive = isset($tagSet['calls_7_active']);
        if ($hasCalls && $hasCallsActive) {
            return array(
                'ok'  => false,
                'msg' => '筛选条件冲突：「近 7 日有调用」与「近 7 日活跃调用」不能同时选择',
            );
        }

        $statusScope = isset($tagSet['include_banned']) ? 'include_banned' : 'active_only';
        $hasCustom = isset($tagSet['custom']);
        $hasSpentTag = isset($tagSet['spent']);

        $loginWithin = $hasLogin7 ? 7 : ($hasLogin30 ? 30 : null);
        $callsWithin = null;
        $callsMin = null;
        if ($hasCalls) {
            $callsWithin = 7;
            $callsMin = 1;
        } elseif ($hasCallsActive) {
            $callsWithin = 7;
            $callsMin = 10;
        }

        $keycallsMin = null;
        $pointsspentMin = null;
        $userIds = null;

        if ($hasCustom || $hasSpentTag) {
            if ($hasCustom) {
                $cLogin = self::nullablePositiveInt(isset($raw['login_within_days']) ? $raw['login_within_days'] : null, 1, 365);
                if ($cLogin !== null) {
                    if ($loginWithin !== null && (int) $loginWithin !== (int) $cLogin) {
                        return array(
                            'ok'  => false,
                            'msg' => '筛选条件冲突：已选登录标签与自定义登录天数不一致',
                        );
                    }
                    $loginWithin = $cLogin;
                }
                $cCallsWithin = self::nullablePositiveInt(isset($raw['calls_within_days']) ? $raw['calls_within_days'] : null, 1, 7);
                $cCallsMin = self::nullablePositiveInt(isset($raw['calls_min']) ? $raw['calls_min'] : null, 1, 100000000);
                if (isset($raw['calls_within_days']) && self::rawFieldFilled($raw['calls_within_days'])
                    && (int) $raw['calls_within_days'] > 7) {
                    return array(
                        'ok'  => false,
                        'msg' => '筛选条件冲突：近 N 日调用仅支持 1～7 日（对齐 stat7）',
                    );
                }
                if ($cCallsWithin !== null || $cCallsMin !== null) {
                    if ($callsWithin !== null) {
                        return array(
                            'ok'  => false,
                            'msg' => '筛选条件冲突：已选调用标签时不要再填自定义调用条件，请取消标签或改自定义',
                        );
                    }
                    $callsWithin = $cCallsWithin !== null ? $cCallsWithin : 7;
                    $callsMin = $cCallsMin !== null ? $cCallsMin : 1;
                }
                $keycallsMin = self::nullablePositiveInt(isset($raw['keycalls_min']) ? $raw['keycalls_min'] : null, 1, 100000000);
                $userIds = self::parseUserIds(isset($raw['user_ids']) ? $raw['user_ids'] : null);
                if ($userIds === false) {
                    return array('ok' => false, 'msg' => '指定用户 ID 无效或超过 500 个上限');
                }
            }
            $pointsspentMin = self::nullableFloat(isset($raw['pointsspent_min']) ? $raw['pointsspent_min'] : null, 0.0001, 1.0e12);
            if ($hasSpentTag && $pointsspentMin === null) {
                return array('ok' => false, 'msg' => '已选「累计消耗达标」，请在自定义中填写消耗下限');
            }
        }

        if ($callsWithin !== null && $callsMin === null) {
            $callsMin = 1;
        }
        if ($callsMin !== null && $callsWithin === null) {
            $callsWithin = 7;
        }

        if ($callsWithin !== null || $callsMin !== null) {
            if (!self::userHasColumn('stat7')) {
                return array('ok' => false, 'msg' => '近 7 日调用统计列未就绪，无法按调用量筛选；请先完成系统升级，或改用其他条件');
            }
            $warnings[] = '近 N 日调用仅支持 1～7 日（对齐 stat7）';
        }
        if ($keycallsMin !== null && !self::userHasColumn('keycalls')) {
            return array('ok' => false, 'msg' => '累计调用列未就绪，无法按 keycalls 筛选；请先完成系统升级');
        }
        if ($pointsspentMin !== null && !self::userHasColumn('pointsspent')) {
            return array('ok' => false, 'msg' => '累计积分消耗列未就绪，无法按消耗筛选；请先完成系统升级');
        }

        if ($userIds !== null && $userIds !== array() && $audience !== 'all') {
            $warnings[] = '已指定用户 ID，仍会按「'
                . ($audience === 'developer' ? '仅开发者' : '仅普通用户')
                . '」过滤，不在该体系内的 ID 不会命中';
        }

        return array(
            'ok'       => true,
            'filter'   => array(
                'audience'          => $audience,
                'status_scope'      => $statusScope,
                'preset'            => 'tags',
                'tags'              => $tags,
                'login_within_days' => $loginWithin,
                'calls_within_days' => $callsWithin,
                'calls_min'         => $callsMin,
                'keycalls_min'      => $keycallsMin,
                'pointsspent_min'   => $pointsspentMin,
                'user_ids'          => $userIds,
            ),
            'warnings' => $warnings,
        );
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private static function parseFilterTags($raw)
    {
        $allowed = array(
            'all' => true,
            'developer' => true,
            'user' => true,
            'login_7' => true,
            'login_30' => true,
            'calls_7' => true,
            'calls_7_active' => true,
            'spent' => true,
            'include_banned' => true,
            'custom' => true,
        );
        $list = array();
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw !== '') {
                $list = preg_split('/[\s,;]+/', $raw);
            }
        }
        $out = array();
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $t) {
            $t = trim((string) $t);
            if ($t !== '' && isset($allowed[$t])) {
                $out[$t] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * @param mixed $v
     * @return bool
     */
    private static function rawFieldFilled($v)
    {
        if ($v === null) {
            return false;
        }
        if (is_string($v) && trim($v) === '') {
            return false;
        }
        return true;
    }

    /**
     * @param mixed $scope
     * @return string incentive|mail
     */
    public static function normalizeFilterScope($scope)
    {
        return ((string) $scope === self::FILTER_SCOPE_MAIL)
            ? self::FILTER_SCOPE_MAIL
            : self::FILTER_SCOPE_INCENTIVE;
    }

    /**
     * @param mixed $scope
     * @param int   $adminId
     * @return void
     */
    private static function beginFilterScope($scope, $adminId = 0)
    {
        self::$activeFilterScope = self::normalizeFilterScope($scope);
        self::$activeFilterAdminId = max(0, (int) $adminId);
    }

    /**
     * @return string
     */
    private static function filterMetaRedisKey()
    {
        $base = self::$activeFilterScope === self::FILTER_SCOPE_MAIL
            ? self::REDIS_MAIL_FILTER_META
            : self::REDIS_FILTER_META;
        return $base . ':' . self::$activeFilterAdminId;
    }

    /**
     * @return string
     */
    private static function filterIdsRedisKey()
    {
        $base = self::$activeFilterScope === self::FILTER_SCOPE_MAIL
            ? self::REDIS_MAIL_FILTER_IDS
            : self::REDIS_FILTER_IDS;
        return $base . ':' . self::$activeFilterAdminId;
    }

    /**
     * 启动筛选：COUNT 候选人数后写入 Redis 游标；前端再 gift_filter_tick 分片扫描
     *
     * @param array  $filter
     * @param int    $adminId
     * @param array  $warnings
     * @param string $scope incentive|mail
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function startFilter(array $filter, $adminId, array $warnings = array(), $scope = 'incentive')
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $adminId = (int) $adminId;
        self::beginFilterScope($scope, $adminId);
        // 群发筛选不依赖积分流水表；激励赠送仍须积分就绪
        if (self::$activeFilterScope !== self::FILTER_SCOPE_MAIL) {
            if (!class_exists('PointsManager') || !PointsManager::hasPointsColumn() || !OrderManager::tableReady()) {
                return array('ok' => false, 'msg' => '积分系统未就绪，请先完成系统升级');
            }
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'msg' => '筛选进度需要 Redis（按批扫描用户并刷新进度条）。请先启用 Redis');
        }

        $needStat7 = isset($filter['calls_within_days']) && $filter['calls_within_days'] !== null;
        try {
            $pdo = Database::connect();
            $built = self::buildSqlWhere($filter);
            $table = Database::table('user');
            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $built['sql']);
            $cntStmt->execute($built['bind']);
            $candidate = (int) $cntStmt->fetchColumn();
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '筛选用户失败');
        }

        if ($needStat7 && $candidate > self::STAT7_CANDIDATE_MAX) {
            return array(
                'ok'  => false,
                'msg' => '当前条件候选约 ' . $candidate . ' 人，超过按调用量筛选上限 '
                    . self::STAT7_CANDIDATE_MAX
                    . '。请先叠加「仅开发者/登录天数」等条件缩小范围后再筛',
            );
        }

        self::clearFilterRedis();

        if ($candidate < 1) {
            return array(
                'ok'   => true,
                'msg'  => 'ok',
                'data' => self::filterProgressPayload(
                    array(
                        'token'     => '',
                        'candidate' => 0,
                        'scanned'   => 0,
                        'matched'   => 0,
                        'sample'    => array(),
                        'warnings'  => $warnings,
                    ),
                    true
                ),
            );
        }

        $token = bin2hex(function_exists('random_bytes') ? random_bytes(8) : openssl_random_pseudo_bytes(8));
        $meta = array(
            'token'      => $token,
            'admin_id'   => $adminId,
            'filter'     => $filter,
            'need_stat7' => $needStat7 ? 1 : 0,
            'candidate'  => $candidate,
            'scanned'    => 0,
            'matched'    => 0,
            'cursor'     => 0,
            'over'       => 0,
            'finished'   => 0,
            'sample'     => array(),
            'warnings'   => array_values($warnings),
            'started'    => time(),
        );
        if (!self::redisPutFilterMeta($meta)) {
            self::clearFilterRedis();
            return array('ok' => false, 'msg' => '写入 Redis 筛选进度失败，请重试');
        }

        return array(
            'ok'   => true,
            'msg'  => '开始筛选',
            'data' => self::filterProgressPayload($meta, false),
        );
    }

    /**
     * 筛选推进：按 id 游标扫描一批，命中 ID 写入 Redis；进度=已扫描/候选
     *
     * @param int    $adminId
     * @param string $token
     * @param string $scope incentive|mail
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function tickFilter($adminId, $token, $scope = 'incentive')
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $adminId = (int) $adminId;
        self::beginFilterScope($scope, $adminId);
        $token = trim((string) $token);
        if ($token === '') {
            return array('ok' => false, 'msg' => '参数无效');
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'msg' => 'Redis 不可用');
        }

        $meta = self::redisGetFilterMeta();
        if (!is_array($meta) || !isset($meta['token']) || $meta['token'] !== $token) {
            return array('ok' => false, 'msg' => '筛选任务不存在或已过期，请重新筛选');
        }
        if ((int) (isset($meta['admin_id']) ? $meta['admin_id'] : 0) !== $adminId) {
            return array('ok' => false, 'msg' => '无权继续该筛选');
        }
        if (!empty($meta['finished'])) {
            return array(
                'ok'   => true,
                'msg'  => 'ok',
                'data' => self::filterProgressPayload($meta, true),
            );
        }
        if (!isset($meta['sample']) || !is_array($meta['sample'])) {
            $meta['sample'] = array();
        }

        $filter = isset($meta['filter']) && is_array($meta['filter']) ? $meta['filter'] : array();
        $needStat7 = !empty($meta['need_stat7']);
        $cursor = (int) (isset($meta['cursor']) ? $meta['cursor'] : 0);
        $callsWithin = $needStat7 ? (int) $filter['calls_within_days'] : 0;
        $callsMin = $needStat7 ? (int) $filter['calls_min'] : 0;

        try {
            $pdo = Database::connect();
            $built = self::buildSqlWhere($filter);
            $table = Database::table('user');
            $cols = '`id`, `username`, `role`';
            if ($needStat7) {
                $cols .= ', `stat7`';
            }
            $sql = 'SELECT ' . $cols . ' FROM `' . $table . '` WHERE (' . $built['sql']
                . ') AND `id` > ? ORDER BY `id` ASC LIMIT ' . (int) self::FILTER_TICK_SIZE;
            $bind = $built['bind'];
            $bind[] = $cursor;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);

            $batchIds = array();
            $rows = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows++;
                $uid = (int) $row['id'];
                $cursor = $uid;
                $meta['scanned'] = (int) $meta['scanned'] + 1;
                if ($needStat7) {
                    $sum = self::sumStat7Calls(isset($row['stat7']) ? $row['stat7'] : '', $callsWithin);
                    if ($sum < $callsMin) {
                        continue;
                    }
                }
                $meta['matched'] = (int) $meta['matched'] + 1;
                if ((int) $meta['matched'] <= self::SYNC_MAX + 1) {
                    $batchIds[] = $uid;
                }
                if (count($meta['sample']) < self::SAMPLE_MAX) {
                    $meta['sample'][] = self::sampleUser($row);
                }
                if ((int) $meta['matched'] > self::SYNC_MAX) {
                    $meta['over'] = 1;
                }
            }
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '筛选扫描失败');
        }

        if (!empty($batchIds) && !self::redisPushFilterIds($batchIds, false)) {
            return array('ok' => false, 'msg' => '写入筛选结果失败，请重试');
        }

        $meta['cursor'] = $cursor;
        $meta['updated'] = time();
        $finished = ($rows < 1) || !empty($meta['over']);
        // over 时已确认超过上限，提前结束扫描以保流畅（总数按 SYNC_MAX+1 展示）
        if (!empty($meta['over'])) {
            $meta['matched'] = self::SYNC_MAX + 1;
        }

        if ($finished) {
            $meta['finished'] = 1;
            self::redisPutFilterMeta($meta);
            return array(
                'ok'   => true,
                'msg'  => '筛选完成',
                'data' => self::filterProgressPayload($meta, true),
            );
        }

        self::redisPutFilterMeta($meta);
        return array(
            'ok'   => true,
            'msg'  => '筛选中',
            'data' => self::filterProgressPayload($meta, false),
        );
    }

    /**
     * @param array $meta
     * @param bool  $finished
     * @return array
     */
    private static function filterProgressPayload(array $meta, $finished)
    {
        $candidate = max(0, (int) (isset($meta['candidate']) ? $meta['candidate'] : 0));
        $scanned = max(0, (int) (isset($meta['scanned']) ? $meta['scanned'] : 0));
        if ($scanned > $candidate) {
            $scanned = $candidate;
        }
        $matched = max(0, (int) (isset($meta['matched']) ? $meta['matched'] : 0));
        $percent = $candidate > 0 ? (int) floor(($scanned * 100) / $candidate) : 100;
        if ($finished) {
            $percent = 100;
            $scanned = $candidate;
        }
        return array(
            'phase'          => 'filter',
            'token'          => (string) (isset($meta['token']) ? $meta['token'] : ''),
            'candidate'      => $candidate,
            'scanned'        => $scanned,
            'done'           => $scanned,
            'total'          => $candidate,
            'matched_count'  => $matched,
            'percent'        => $percent,
            'finished'       => $finished ? 1 : 0,
            'sample_users'   => isset($meta['sample']) && is_array($meta['sample']) ? $meta['sample'] : array(),
            'warnings'       => isset($meta['warnings']) && is_array($meta['warnings']) ? $meta['warnings'] : array(),
            'sync_max'       => self::SYNC_MAX,
            'can_execute'    => $finished && $matched > 0 && $matched <= self::SYNC_MAX,
        );
    }

    /**
     * 启动赠送：复用筛选阶段 Redis 中的用户 ID；不重扫全表
     *
     * @param array  $filter
     * @param float  $amount
     * @param string $remark
     * @param int    $adminId
     * @param string $confirmText
     * @param int    $filterCount
     * @param string $filterToken
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function startGift(
        array $filter,
        $amount,
        $remark,
        $adminId,
        $confirmText = '',
        $filterCount = 0,
        $filterToken = ''
    ) {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        if (!class_exists('PointsManager') || !PointsManager::hasPointsColumn() || !OrderManager::tableReady()) {
            return array('ok' => false, 'msg' => '积分系统未就绪，请先完成系统升级');
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'msg' => '积分赠送需要 Redis：用于暂存待赠用户 ID 与进度。请先启用 Redis 后重试');
        }

        $amountCheck = self::validateAmount($amount);
        if (!$amountCheck['ok']) {
            return $amountCheck;
        }
        $amount = $amountCheck['amount'];

        $remarkBuilt = self::buildRemark($remark);
        if (!$remarkBuilt['ok']) {
            return $remarkBuilt;
        }
        $remarkFinal = $remarkBuilt['remark'];

        $adminId = (int) $adminId;

        $filterToken = trim((string) $filterToken);
        // 强制走筛选 Redis 队列；禁止无 token 的全表长请求旁路（防绕过进度切片与卡死）
        if ($filterToken === '') {
            return array('ok' => false, 'msg' => '请先点击「筛选」完成用户匹配后再赠送');
        }

        self::beginFilterScope(self::FILTER_SCOPE_INCENTIVE, $adminId);
        $fmeta = self::redisGetFilterMeta();
        if (!is_array($fmeta) || empty($fmeta['finished'])
            || !isset($fmeta['token']) || $fmeta['token'] !== $filterToken) {
            return array('ok' => false, 'msg' => '请先完成筛选（筛选结果已过期请重筛）');
        }
        if ((int) (isset($fmeta['admin_id']) ? $fmeta['admin_id'] : 0) !== $adminId) {
            return array('ok' => false, 'msg' => '无权使用该筛选结果');
        }
        $count = (int) (isset($fmeta['matched']) ? $fmeta['matched'] : 0);
        if ($count < 1) {
            return array('ok' => false, 'msg' => '没有命中任何用户');
        }
        if ($count > self::SYNC_MAX) {
            return array(
                'ok'  => false,
                'msg' => '命中超过一次上限 ' . self::SYNC_MAX . '，请收窄条件后重试',
            );
        }
        $idsLen = self::redisFilterIdsLen();
        if ($idsLen !== $count) {
            return array('ok' => false, 'msg' => '筛选结果不完整，请重新筛选');
        }

        $filterCount = (int) $filterCount;
        if ($filterCount > 0) {
            $drift = abs($filterCount - $count);
            $tol = max(1, (int) ceil($filterCount * 0.05));
            if ($drift > $tol) {
                return array(
                    'ok'  => false,
                    'msg' => '用户集已变化（筛选 ' . $filterCount . '，当前 ' . $count . '），请重新筛选后再执行',
                );
            }
        }

        // 高危判定必须用筛选阶段写入 Redis 的条件，禁止用本次 POST 的 filter 伪造降级以跳过确认
        $riskFilter = (isset($fmeta['filter']) && is_array($fmeta['filter']))
            ? $fmeta['filter']
            : $filter;
        $risk = self::riskLevel($count, $amount, $riskFilter);
        if ($risk === 'high') {
            $confirmText = trim((string) $confirmText);
            if ($confirmText !== '确认赠送') {
                return array(
                    'ok'  => false,
                    'msg' => '高危操作：请在确认框中确认后再赠送',
                );
            }
        }

        if (!self::acquireRunLock($adminId, $count, $amount)) {
            return array('ok' => false, 'msg' => '已有赠送进行中，请稍后再试');
        }

        $token = bin2hex(function_exists('random_bytes') ? random_bytes(8) : openssl_random_pseudo_bytes(8));
        $meta = array(
            'token'     => $token,
            'admin_id'  => $adminId,
            'amount'    => $amount,
            'remark'    => $remarkFinal,
            'total'     => $count,
            'done'      => 0,
            'success'   => 0,
            'fail'      => 0,
            'started'   => time(),
        );
        $meta['sig'] = self::signGiftMeta($meta);

        try {
            if (!self::redisPromoteFilterIdsToGift($token) || !self::redisPutMeta($meta)) {
                self::clearGiftRedis();
                self::releaseRunLock();
                return array('ok' => false, 'msg' => '准备赠送队列失败，请重试');
            }
            self::redisForgetFilterMetaOnly();
        } catch (Exception $e) {
            self::clearGiftRedis();
            self::releaseRunLock();
            return array('ok' => false, 'msg' => '写入 Redis 失败，请重试');
        }

        return array(
            'ok'   => true,
            'msg'  => '已准备 ' . $count . ' 人，开始赠送',
            'data' => self::progressPayload($meta, false),
        );
    }

    /**
     * 推进一批：从 Redis LIST 弹出最多 TICK_SIZE 个用户 ID 并入账
     *
     * @param int    $adminId
     * @param string $token
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function tickGift($adminId, $token)
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $adminId = (int) $adminId;
        $token = trim((string) $token);
        if ($token === '') {
            return array('ok' => false, 'msg' => '参数无效');
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return array('ok' => false, 'msg' => 'Redis 不可用');
        }

        $meta = self::redisGetMeta();
        if (!is_array($meta) || !isset($meta['token']) || $meta['token'] !== $token) {
            return array('ok' => false, 'msg' => '赠送任务不存在或已结束，请重新筛选');
        }
        if ((int) (isset($meta['admin_id']) ? $meta['admin_id'] : 0) !== $adminId) {
            return array('ok' => false, 'msg' => '无权继续该任务');
        }
        if (!self::verifyGiftMeta($meta)) {
            self::clearGiftRedis();
            self::releaseRunLock();
            return array('ok' => false, 'msg' => '赠送任务校验失败（队列可能被篡改），已终止（已入账不回滚，请重筛）');
        }

        $amountCheck = self::validateAmount(isset($meta['amount']) ? $meta['amount'] : 0);
        if (empty($amountCheck['ok'])) {
            // 金额被污染：清队释锁，避免卡死 900s；已入账部分不回滚（须财务核对）
            self::clearGiftRedis();
            self::releaseRunLock();
            return array('ok' => false, 'msg' => '赠送金额异常，已终止本任务（已入账不回滚，请重筛）');
        }
        $amount = $amountCheck['amount'];
        $remarkFinal = (string) $meta['remark'];
        $totalPlan = max(0, (int) (isset($meta['total']) ? $meta['total'] : 0));
        $doneNow = max(0, (int) (isset($meta['done']) ? $meta['done'] : 0));
        if ($totalPlan < 1) {
            self::clearGiftRedis();
            self::releaseRunLock();
            return array('ok' => false, 'msg' => '赠送任务无效，已终止');
        }
        if ($doneNow >= $totalPlan) {
            $remainCheck = self::redisIdsLen();
            // done 被抬高但队列仍有人：视为篡改，禁止静默清队丢赠
            if ($remainCheck > 0) {
                self::clearGiftRedis();
                self::releaseRunLock();
                return array(
                    'ok'  => false,
                    'msg' => '赠送进度异常（可能被篡改），已终止（已入账不回滚，请重筛）',
                );
            }
            self::clearGiftRedis();
            self::releaseRunLock();
            return array(
                'ok'   => true,
                'msg'  => '赠送完成：成功 ' . (int) (isset($meta['success']) ? $meta['success'] : 0),
                'data' => self::progressPayload($meta, true),
            );
        }
        // 批内封顶：最多再处理 total-done，禁止队列注水后单 tick 超发
        $batchLimit = min(self::TICK_SIZE, $totalPlan - $doneNow);
        $batch = self::redisPopIds($batchLimit);
        // null=Redis 故障：禁止当成「弹空」去清队，否则剩余用户永久丢赠
        if ($batch === null) {
            return array('ok' => false, 'msg' => 'Redis 暂不可用，赠送队列未清除，请稍后继续推进');
        }

        if (!empty($batch)) {
            $tampered = false;
            PointsManager::beginBulkOrders();
            try {
                foreach ($batch as $entry) {
                    if ((int) $meta['done'] >= $totalPlan) {
                        break;
                    }
                    $uid = self::parseSignedGiftId($entry, $token);
                    // 签名失败=队列被注水/篡改：立即终止，禁止占 done 额度把合法用户挤掉
                    if ($uid <= 0) {
                        $tampered = true;
                        break;
                    }
                    $meta['done'] = (int) $meta['done'] + 1;
                    $r = PointsManager::opsGift($uid, $amount, $remarkFinal);
                    if (!empty($r['ok'])) {
                        $meta['success'] = (int) $meta['success'] + 1;
                    } else {
                        $meta['fail'] = (int) $meta['fail'] + 1;
                    }
                }
            } finally {
                PointsManager::endBulkOrders();
            }
            if ($tampered) {
                self::clearGiftRedis();
                self::releaseRunLock();
                return array(
                    'ok'   => false,
                    'msg'  => '赠送队列校验失败（可能被篡改），已终止（已入账不回滚，请重筛）',
                    'data' => self::progressPayload($meta, true),
                );
            }
        }

        $remain = self::redisIdsLen();
        if ($remain < 0) {
            // 长度不可读：已入账本批保留 meta，不结束、不清队
            $meta['updated'] = time();
            self::redisPutMeta($meta);
            return array(
                'ok'   => true,
                'msg'  => '进行中（队列长度暂不可读，请继续推进）',
                'data' => self::progressPayload($meta, false),
            );
        }
        // 结束条件：队列真正空；done>=total 但队列仍有人 → 进度被篡改，不得静默清队
        $meta['updated'] = time();
        if ($remain >= 1 && (int) $meta['done'] >= (int) $meta['total']) {
            self::clearGiftRedis();
            self::releaseRunLock();
            return array(
                'ok'  => false,
                'msg' => '赠送进度异常（可能被篡改），已终止（已入账不回滚，请重筛）',
            );
        }
        $finished = ($remain < 1);

        if ($finished) {
            self::clearGiftRedis();
            self::releaseRunLock();
            $msg = '赠送完成：成功 ' . (int) $meta['success'];
            if ((int) $meta['fail'] > 0) {
                $msg .= '，未入账 ' . (int) $meta['fail'];
            }
            return array(
                'ok'   => true,
                'msg'  => $msg,
                'data' => self::progressPayload($meta, true),
            );
        }

        self::redisPutMeta($meta);
        return array(
            'ok'   => true,
            'msg'  => '进行中',
            'data' => self::progressPayload($meta, false),
        );
    }

    /**
     * @param array $meta
     * @param bool  $finished
     * @return array
     */
    private static function progressPayload(array $meta, $finished)
    {
        $total = max(0, (int) (isset($meta['total']) ? $meta['total'] : 0));
        $done = max(0, (int) (isset($meta['done']) ? $meta['done'] : 0));
        if ($done > $total) {
            $done = $total;
        }
        $percent = $total > 0 ? (int) floor(($done * 100) / $total) : 0;
        if ($finished) {
            $percent = 100;
            $done = $total;
        }
        return array(
            'phase'          => 'gift',
            'token'          => (string) (isset($meta['token']) ? $meta['token'] : ''),
            'total'          => $total,
            'done'           => $done,
            'success_count'  => (int) (isset($meta['success']) ? $meta['success'] : 0),
            'fail_count'     => (int) (isset($meta['fail']) ? $meta['fail'] : 0),
            'percent'        => $percent,
            'finished'       => $finished ? 1 : 0,
            'matched_count'  => $total,
            'amount'         => isset($meta['amount']) ? $meta['amount'] : 0,
            'remark'         => isset($meta['remark']) ? (string) $meta['remark'] : '',
        );
    }

    /**
     * @param array $filter
     * @param bool  $countOnly
     * @return array
     */
    private static function resolveMatched(array $filter, $countOnly)
    {
        $needStat7 = isset($filter['calls_within_days']) && $filter['calls_within_days'] !== null;
        try {
            $pdo = Database::connect();
            $built = self::buildSqlWhere($filter);
            $table = Database::table('user');
            $cols = '`id`, `username`, `role`';
            if ($needStat7) {
                $cols .= ', `stat7`';
            }

            if (!$needStat7 && $countOnly) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $built['sql']);
                $stmt->execute($built['bind']);
                $count = (int) $stmt->fetchColumn();
                $sample = array();
                if ($count > 0) {
                    $st2 = $pdo->prepare(
                        'SELECT `id`, `username`, `role` FROM `' . $table
                        . '` WHERE ' . $built['sql'] . ' ORDER BY `id` DESC LIMIT ' . (int) self::SAMPLE_MAX
                    );
                    $st2->execute($built['bind']);
                    while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
                        $sample[] = self::sampleUser($row);
                    }
                }
                return array(
                    'ok'       => true,
                    'count'    => $count,
                    'sample'   => $sample,
                    'ids'      => array(),
                    'warnings' => array(),
                );
            }

            // 按调用量过滤须读 stat7：先 COUNT 候选，过大则拒，避免全表拖 mediumtext 卡死站点
            if ($needStat7) {
                $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $built['sql']);
                $cntStmt->execute($built['bind']);
                $candidate = (int) $cntStmt->fetchColumn();
                if ($candidate > self::STAT7_CANDIDATE_MAX) {
                    return array(
                        'ok'  => false,
                        'msg' => '当前条件候选约 ' . $candidate . ' 人，超过按调用量筛选上限 '
                            . self::STAT7_CANDIDATE_MAX
                            . '。请先叠加「仅开发者/登录天数」等条件缩小范围后再筛',
                    );
                }
            }

            $stmt = $pdo->prepare(
                'SELECT ' . $cols . ' FROM `' . $table . '` WHERE ' . $built['sql'] . ' ORDER BY `id` ASC'
            );
            $stmt->execute($built['bind']);

            $ids = array();
            $sample = array();
            $callsWithin = $needStat7 ? (int) $filter['calls_within_days'] : 0;
            $callsMin = $needStat7 ? (int) $filter['calls_min'] : 0;
            $over = false;
            $scanCap = self::SYNC_MAX + 1;
            $rowsSeen = 0;
            $rowHardCap = $needStat7 ? (self::STAT7_CANDIDATE_MAX + 1) : ($scanCap + 5000);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rowsSeen++;
                if ($rowsSeen > $rowHardCap) {
                    return array(
                        'ok'  => false,
                        'msg' => '筛选扫描行数过多，请收窄条件后重试',
                    );
                }
                if ($needStat7) {
                    $sum = self::sumStat7Calls(isset($row['stat7']) ? $row['stat7'] : '', $callsWithin);
                    if ($sum < $callsMin) {
                        continue;
                    }
                }
                $uid = (int) $row['id'];
                $ids[] = $uid;
                if ($countOnly && count($sample) < self::SAMPLE_MAX) {
                    $sample[] = self::sampleUser($row);
                }
                if (count($ids) > $scanCap) {
                    $over = true;
                    break;
                }
            }

            $count = $over ? (self::SYNC_MAX + 1) : count($ids);
            return array(
                'ok'       => true,
                'count'    => $count,
                'sample'   => $sample,
                'ids'      => $countOnly ? array() : ($over ? array_slice($ids, 0, self::SYNC_MAX + 1) : $ids),
                'warnings' => array(),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '筛选用户失败');
        }
    }

    /**
     * @param array $filter
     * @return array{sql:string,bind:array}
     */
    private static function buildSqlWhere(array $filter)
    {
        $parts = array('1=1');
        $bind = array();

        if ($filter['audience'] === 'developer') {
            $parts[] = '`role` = ?';
            $bind[] = UserRole::ROLE_DEVELOPER;
        } elseif ($filter['audience'] === 'user') {
            $parts[] = '`role` = ?';
            $bind[] = UserRole::ROLE_USER;
        }

        if ($filter['status_scope'] !== 'include_banned') {
            $parts[] = '`status` = 1';
        }

        if ($filter['login_within_days'] !== null) {
            $parts[] = '`lastlogin` IS NOT NULL AND `lastlogin` != \'\' AND `lastlogin` >= ?';
            $bind[] = date('Y-m-d H:i:s', time() - ((int) $filter['login_within_days'] * 86400));
        }

        if ($filter['keycalls_min'] !== null) {
            $parts[] = '`keycalls` >= ?';
            $bind[] = (int) $filter['keycalls_min'];
        }

        if ($filter['pointsspent_min'] !== null) {
            $parts[] = '`pointsspent` >= ?';
            $bind[] = (float) $filter['pointsspent_min'];
        }

        if (!empty($filter['user_ids']) && is_array($filter['user_ids'])) {
            $ids = array();
            foreach ($filter['user_ids'] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            $ids = array_values(array_unique($ids));
            if ($ids !== array()) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $parts[] = '`id` IN (' . $placeholders . ')';
                foreach ($ids as $id) {
                    $bind[] = $id;
                }
            }
        }

        return array('sql' => implode(' AND ', $parts), 'bind' => $bind);
    }

    /**
     * @param int   $adminId
     * @param int   $matched
     * @param float $amount
     * @return bool
     */
    private static function acquireRunLock($adminId, $matched, $amount)
    {
        $payload = array(
            'admin_id' => (int) $adminId,
            'matched'  => (int) $matched,
            'amount'   => (float) $amount,
            'started'  => time(),
        );

        try {
            $ok = RedisService::withClient(function ($redis) use ($payload) {
                $key = RedisService::buildKey(self::REDIS_LOCK);
                if (method_exists($redis, 'set')) {
                    return (bool) $redis->set($key, serialize($payload), array('nx', 'ex' => self::REDIS_TTL));
                }
                return false;
            });
            if ($ok) {
                return true;
            }
            // SET NX 未抢到：已有锁则拒绝。禁止再走无 NX 的 put（防双开赠送竞态）
            if (RedisCache::get(self::REDIS_LOCK) !== null) {
                return false;
            }
            // 客户端可能不支持 set+nx：回落 MySQL 命名锁，仍不要用非原子 put
        } catch (Exception $e) {
            // Redis 异常 → MySQL 命名锁
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SELECT GET_LOCK(' . $pdo->quote(self::MYSQL_LOCK) . ', 0)');
            $v = $stmt ? $stmt->fetchColumn() : 0;
            return (int) $v === 1;
        } catch (Exception $e2) {
            return false;
        }
    }

    /**
     * @return void
     */
    private static function releaseRunLock()
    {
        if (class_exists('RedisCache')) {
            try {
                RedisCache::forget(self::REDIS_LOCK);
            } catch (Exception $e) {
            }
        }
        try {
            $pdo = Database::connect();
            $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote(self::MYSQL_LOCK) . ')');
        } catch (Exception $e) {
        }
    }

    /**
     * @param array $ids
     * @return bool
     */
    private static function redisPushIds(array $ids)
    {
        return (bool) RedisService::withClient(function ($redis) use ($ids) {
            $key = RedisService::buildKey(self::REDIS_IDS);
            $redis->del($key);
            foreach (array_chunk($ids, 200) as $chunk) {
                $args = array($key);
                foreach ($chunk as $id) {
                    $args[] = (string) (int) $id;
                }
                call_user_func_array(array($redis, 'rPush'), $args);
            }
            $redis->expire($key, self::REDIS_TTL);
            return true;
        });
    }

    /**
     * @param int $n
     * @return string[]|null 成功为队列元素（uid:mac）；Redis 故障返回 null
     */
    private static function redisPopIds($n)
    {
        $n = (int) $n;
        if ($n < 1) {
            return array();
        }
        try {
            $out = RedisService::withClient(function ($redis) use ($n) {
                $key = RedisService::buildKey(self::REDIS_IDS);
                $ids = array();
                for ($i = 0; $i < $n; $i++) {
                    $v = $redis->lPop($key);
                    if ($v === false || $v === null) {
                        break;
                    }
                    $ids[] = (string) $v;
                }
                return $ids;
            });
            return is_array($out) ? $out : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return int 队列长度；失败返回 -1（禁止当成 0 误结束赠送）
     */
    private static function redisIdsLen()
    {
        try {
            return (int) RedisService::withClient(function ($redis) {
                return (int) $redis->lLen(RedisService::buildKey(self::REDIS_IDS));
            });
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * @param array $meta
     * @return bool
     */
    private static function redisPutMeta(array $meta)
    {
        try {
            RedisCache::put(self::REDIS_META, $meta, self::REDIS_TTL);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array|null
     */
    private static function redisGetMeta()
    {
        $m = RedisCache::get(self::REDIS_META);
        return is_array($m) ? $m : null;
    }

    /**
     * @return void
     */
    private static function clearGiftRedis()
    {
        try {
            RedisCache::forget(self::REDIS_META);
            RedisService::withClient(function ($redis) {
                $redis->del(RedisService::buildKey(self::REDIS_IDS));
            });
        } catch (Exception $e) {
        }
    }

    /**
     * @param array $meta
     * @return bool
     */
    private static function redisPutFilterMeta(array $meta)
    {
        try {
            RedisCache::put(self::filterMetaRedisKey(), $meta, self::REDIS_TTL);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array|null
     */
    private static function redisGetFilterMeta()
    {
        $m = RedisCache::get(self::filterMetaRedisKey());
        return is_array($m) ? $m : null;
    }

    /**
     * 运营其它模块（邮件群发）读取已完成筛选的 uid 列表（不转入赠送队列）
     *
     * @param int    $adminId
     * @param string $filterToken
     * @param string $scope
     * @return array{ok:bool,msg?:string,uids?:int[],matched?:int}
     */
    public static function snapshotFilterIds($adminId, $filterToken, $scope = 'incentive')
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $adminId = (int) $adminId;
        self::beginFilterScope($scope, $adminId);
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'msg' => '需要 Redis：请先启用后再筛选');
        }
        $filterToken = trim((string) $filterToken);
        if ($filterToken === '') {
            return array('ok' => false, 'msg' => '请先点击「筛选」完成用户匹配后再发送');
        }
        $fmeta = self::redisGetFilterMeta();
        if (!is_array($fmeta) || empty($fmeta['finished'])
            || !isset($fmeta['token']) || $fmeta['token'] !== $filterToken) {
            return array('ok' => false, 'msg' => '请先完成筛选（筛选结果已过期请重筛）');
        }
        if ((int) (isset($fmeta['admin_id']) ? $fmeta['admin_id'] : 0) !== (int) $adminId) {
            return array('ok' => false, 'msg' => '无权使用该筛选结果');
        }
        $count = (int) (isset($fmeta['matched']) ? $fmeta['matched'] : 0);
        if ($count < 1) {
            return array('ok' => false, 'msg' => '没有命中任何用户');
        }
        if ($count > self::SYNC_MAX) {
            return array(
                'ok'  => false,
                'msg' => '命中超过一次上限 ' . self::SYNC_MAX . '，请收窄条件后重试',
            );
        }
        $idsLen = self::redisFilterIdsLen();
        if ($idsLen !== $count) {
            return array('ok' => false, 'msg' => '筛选结果不完整，请重新筛选');
        }
        try {
            $uids = RedisService::withClient(function ($redis) use ($count) {
                $key = RedisService::buildKey(self::filterIdsRedisKey());
                $raw = $redis->lRange($key, 0, max(0, $count - 1));
                $out = array();
                if (is_array($raw)) {
                    foreach ($raw as $v) {
                        $id = (int) $v;
                        if ($id > 0) {
                            $out[$id] = $id;
                        }
                    }
                }
                return array_values($out);
            });
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '读取筛选结果失败，请重试');
        }
        if (!is_array($uids) || count($uids) < 1) {
            return array('ok' => false, 'msg' => '没有命中任何用户');
        }
        return array(
            'ok'      => true,
            'uids'    => $uids,
            'matched' => count($uids),
        );
    }

    /**
     * 群发启动成功后一次性作废筛选结果（防同 token 重复发送）
     *
     * @param int    $adminId
     * @param string $filterToken
     * @param string $scope
     * @return array{ok:bool,msg?:string}
     */
    public static function consumeFilterSnapshot($adminId, $filterToken, $scope = 'incentive')
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $adminId = (int) $adminId;
        self::beginFilterScope($scope, $adminId);
        $filterToken = trim((string) $filterToken);
        if ($filterToken === '') {
            return array('ok' => false, 'msg' => '筛选令牌无效');
        }
        $fmeta = self::redisGetFilterMeta();
        if (!is_array($fmeta) || !isset($fmeta['token']) || $fmeta['token'] !== $filterToken) {
            // 已过期/已消费：视为成功，避免启动后二次失败
            return array('ok' => true);
        }
        if ((int) (isset($fmeta['admin_id']) ? $fmeta['admin_id'] : 0) !== (int) $adminId) {
            return array('ok' => false, 'msg' => '无权作废该筛选结果');
        }
        self::clearFilterRedis();
        return array('ok' => true);
    }

    /**
     * 按筛选 token 生成白话摘要（供 maillog.filtersum）
     *
     * @param int    $adminId
     * @param string $filterToken
     * @param string $scope
     * @return string
     */
    public static function filterSummaryForToken($adminId, $filterToken, $scope = 'incentive')
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return '';
        }
        $adminId = (int) $adminId;
        self::beginFilterScope($scope, $adminId);
        $filterToken = trim((string) $filterToken);
        if ($filterToken === '') {
            return '';
        }
        $fmeta = self::redisGetFilterMeta();
        if (!is_array($fmeta) || !isset($fmeta['token']) || $fmeta['token'] !== $filterToken) {
            return '';
        }
        if ((int) (isset($fmeta['admin_id']) ? $fmeta['admin_id'] : 0) !== (int) $adminId) {
            return '';
        }
        $filter = isset($fmeta['filter']) && is_array($fmeta['filter']) ? $fmeta['filter'] : array();
        return self::describeFilterSummary($filter);
    }

    /**
     * @param array $filter
     * @return string
     */
    public static function describeFilterSummary(array $filter)
    {
        $parts = array();
        $audience = isset($filter['audience']) ? (string) $filter['audience'] : 'all';
        if ($audience === 'developer') {
            $parts[] = '仅开发者';
        } elseif ($audience === 'user') {
            $parts[] = '仅普通用户';
        } else {
            $parts[] = '全部用户';
        }
        if (!empty($filter['status_scope']) && $filter['status_scope'] === 'include_banned') {
            $parts[] = '含封禁';
        }
        $login = isset($filter['login_within_days']) ? (int) $filter['login_within_days'] : 0;
        if ($login > 0) {
            $parts[] = '近' . $login . '日登录';
        }
        $callsWithin = isset($filter['calls_within_days']) ? (int) $filter['calls_within_days'] : 0;
        $callsMin = isset($filter['calls_min']) ? (int) $filter['calls_min'] : 0;
        if ($callsWithin > 0 || $callsMin > 0) {
            $parts[] = '近' . ($callsWithin > 0 ? $callsWithin : 7) . '日调用≥' . ($callsMin > 0 ? $callsMin : 1);
        }
        $keycalls = isset($filter['keycalls_min']) ? (int) $filter['keycalls_min'] : 0;
        if ($keycalls > 0) {
            $parts[] = '总调用≥' . $keycalls;
        }
        if (isset($filter['pointsspent_min']) && $filter['pointsspent_min'] !== null && $filter['pointsspent_min'] !== '') {
            $parts[] = '消耗≥' . $filter['pointsspent_min'];
        }
        if (!empty($filter['user_ids']) && is_array($filter['user_ids'])) {
            $parts[] = '指定用户' . count($filter['user_ids']) . '人';
        }
        $out = implode(' · ', $parts);
        if (function_exists('mb_substr') && mb_strlen($out, 'UTF-8') > 500) {
            $out = mb_substr($out, 0, 500, 'UTF-8');
        }
        return $out;
    }

    /**
     * @param int[] $ids
     * @param bool  $replace  true=先清空再写
     * @return bool
     */
    private static function redisPushFilterIds(array $ids, $replace = false)
    {
        if ($ids === array()) {
            return true;
        }
        return (bool) RedisService::withClient(function ($redis) use ($ids, $replace) {
            $key = RedisService::buildKey(self::filterIdsRedisKey());
            if ($replace) {
                $redis->del($key);
            }
            foreach (array_chunk($ids, 200) as $chunk) {
                $args = array($key);
                foreach ($chunk as $id) {
                    $args[] = (string) (int) $id;
                }
                call_user_func_array(array($redis, 'rPush'), $args);
            }
            $redis->expire($key, self::REDIS_TTL);
            return true;
        });
    }

    /**
     * @return int
     */
    private static function redisFilterIdsLen()
    {
        try {
            return (int) RedisService::withClient(function ($redis) {
                return (int) $redis->lLen(RedisService::buildKey(self::filterIdsRedisKey()));
            });
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 筛选 LIST → 赠送 LIST：每项写成「uid:hmac」，防止 Redis 注水伪造用户 ID
     *
     * @param string $token 本次赠送 token
     * @return bool
     */
    private static function redisPromoteFilterIdsToGift($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        try {
            return (bool) RedisService::withClient(function ($redis) use ($token) {
                // 赠送只消费激励筛选队列
                $from = RedisService::buildKey(self::REDIS_FILTER_IDS);
                $to = RedisService::buildKey(self::REDIS_IDS);
                if (!(int) $redis->exists($from)) {
                    return false;
                }
                $rawIds = $redis->lRange($from, 0, -1);
                if (!is_array($rawIds) || $rawIds === array()) {
                    return false;
                }
                $redis->del($to);
                $chunk = array($to);
                $n = 0;
                foreach ($rawIds as $raw) {
                    $uid = (int) $raw;
                    if ($uid <= 0) {
                        continue;
                    }
                    $chunk[] = self::formatSignedGiftId($uid, $token);
                    $n++;
                    if (count($chunk) >= 201) {
                        call_user_func_array(array($redis, 'rPush'), $chunk);
                        $chunk = array($to);
                    }
                }
                if (count($chunk) > 1) {
                    call_user_func_array(array($redis, 'rPush'), $chunk);
                }
                if ($n < 1) {
                    $redis->del($to);
                    return false;
                }
                $redis->del($from);
                $redis->expire($to, self::REDIS_TTL);
                return true;
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return string
     */
    private static function giftSigningKey()
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $secret = '';
        try {
            if (class_exists('Config') && class_exists('InstallChecker') && InstallChecker::isInstalled()) {
                $secret = (string) Config::get('incentive_gift_secret', '');
                if (strlen($secret) < 32) {
                    $secret = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
                    Config::set('incentive_gift_secret', $secret);
                }
            }
        } catch (Exception $e) {
            $secret = '';
        }
        if ($secret === '' || strlen($secret) < 32) {
            // 未安装/无配置时退化为路径派生（弱于持久密钥，但仍优于明文队列）
            $seed = defined('VS_ROOT') ? (string) VS_ROOT : __DIR__;
            $key = hash('sha256', 'apinexus-incentive-gift-fallback|' . $seed);
            return $key;
        }
        $key = hash('sha256', 'apinexus-incentive-gift-v1|' . $secret);
        return $key;
    }

    /**
     * 签名不可变字段（token/admin/amount/remark/total/started）；done 等进度字段不参与
     *
     * @param array $meta
     * @return string
     */
    private static function signGiftMeta(array $meta)
    {
        $amountNorm = number_format((float) (isset($meta['amount']) ? $meta['amount'] : 0), 4, '.', '');
        $payload = implode('|', array(
            (string) (isset($meta['token']) ? $meta['token'] : ''),
            (string) (int) (isset($meta['admin_id']) ? $meta['admin_id'] : 0),
            $amountNorm,
            (string) (isset($meta['remark']) ? $meta['remark'] : ''),
            (string) (int) (isset($meta['total']) ? $meta['total'] : 0),
            (string) (int) (isset($meta['started']) ? $meta['started'] : 0),
        ));
        return hash_hmac('sha256', $payload, self::giftSigningKey());
    }

    /**
     * @param array $meta
     * @return bool
     */
    private static function verifyGiftMeta(array $meta)
    {
        $sig = isset($meta['sig']) ? (string) $meta['sig'] : '';
        if ($sig === '' || strlen($sig) < 32) {
            return false;
        }
        $expect = self::signGiftMeta($meta);
        return hash_equals($expect, $sig);
    }

    /**
     * @param int    $uid
     * @param string $token
     * @return string
     */
    private static function formatSignedGiftId($uid, $token)
    {
        $uid = (int) $uid;
        $mac = hash_hmac('sha256', (string) $uid . '|' . (string) $token, self::giftSigningKey());
        return $uid . ':' . $mac;
    }

    /**
     * @param mixed  $entry
     * @param string $token
     * @return int 校验失败返回 0
     */
    private static function parseSignedGiftId($entry, $token)
    {
        $entry = trim((string) $entry);
        $token = trim((string) $token);
        if ($entry === '' || $token === '' || strpos($entry, ':') === false) {
            return 0;
        }
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
            return 0;
        }
        $uid = (int) $parts[0];
        $mac = (string) $parts[1];
        if ($uid <= 0 || $mac === '') {
            return 0;
        }
        $expect = hash_hmac('sha256', (string) $uid . '|' . $token, self::giftSigningKey());
        if (!hash_equals($expect, $mac)) {
            return 0;
        }
        return $uid;
    }

    /**
     * @return void
     */
    private static function redisForgetFilterMetaOnly()
    {
        try {
            RedisCache::forget(self::filterMetaRedisKey());
        } catch (Exception $e) {
        }
    }

    /**
     * @return void
     */
    private static function clearFilterRedis()
    {
        try {
            RedisCache::forget(self::filterMetaRedisKey());
            RedisService::withClient(function ($redis) {
                $redis->del(RedisService::buildKey(self::filterIdsRedisKey()));
            });
        } catch (Exception $e) {
        }
    }

    /**
     * @param string $raw
     * @param int    $days
     * @return int
     */
    private static function sumStat7Calls($raw, $days)
    {
        $days = (int) $days;
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 7) {
            $days = 7;
        }
        $map = json_decode((string) $raw, true);
        if (!is_array($map)) {
            return 0;
        }
        $sum = 0;
        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d', strtotime('-' . $i . ' day'));
            if (!isset($map[$day]) || !is_array($map[$day])) {
                continue;
            }
            $sum += isset($map[$day]['calls']) ? (int) $map[$day]['calls'] : 0;
        }
        return $sum;
    }

    /**
     * @param array $row
     * @return array
     */
    private static function sampleUser(array $row)
    {
        $role = UserRole::normalize(isset($row['role']) ? $row['role'] : UserRole::ROLE_USER);
        return array(
            'id'         => (int) $row['id'],
            'username'   => (string) (isset($row['username']) ? $row['username'] : ''),
            'role'       => $role,
            'role_label' => UserRole::label($role),
        );
    }

    /**
     * @param int   $count
     * @param float $amount
     * @param array $filter
     * @return string
     */
    private static function riskLevel($count, $amount, array $filter)
    {
        $count = (int) $count;
        $amount = (float) $amount;
        if (!empty($filter['status_scope']) && $filter['status_scope'] === 'include_banned') {
            return 'high';
        }
        if (!empty($filter['audience']) && $filter['audience'] === 'all' && $count > 200) {
            return 'high';
        }
        if ($count > 2000 || ($count * $amount) > 500000) {
            return 'high';
        }
        if ($count > 50 || $amount > 1000) {
            return 'mid';
        }
        return 'low';
    }

    /**
     * @param mixed $amount
     * @return array
     */
    private static function validateAmount($amount)
    {
        if ($amount === null || $amount === '' || (is_string($amount) && !is_numeric(trim($amount)))) {
            return array('ok' => false, 'msg' => '赠送积分须至少为 1');
        }
        $amount = (float) $amount;
        if (!is_finite($amount) || $amount < 1) {
            return array('ok' => false, 'msg' => '赠送积分须至少为 1');
        }
        if ($amount > self::AMOUNT_MAX) {
            return array('ok' => false, 'msg' => '单次人均积分不能超过 ' . self::AMOUNT_MAX);
        }
        return array('ok' => true, 'amount' => $amount);
    }

    /**
     * @param string $remark
     * @return array
     */
    private static function buildRemark($remark)
    {
        $remark = trim((string) $remark);
        $remark = strip_tags($remark);
        $remark = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $remark);
        if ($remark === '') {
            return array('ok' => false, 'msg' => '赠送理由不能为空');
        }
        if (self::strLen($remark) > self::REMARK_MAX) {
            $remark = self::strCut($remark, self::REMARK_MAX);
        }
        return array('ok' => true, 'remark' => $remark);
    }

    /**
     * @param mixed $v
     * @param int   $min
     * @param int   $max
     * @return int|null
     */
    private static function nullablePositiveInt($v, $min, $max)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $n = (int) $v;
        if ($n < $min || $n > $max) {
            return null;
        }
        return $n;
    }

    /**
     * @param mixed $v
     * @param float $min
     * @param float $max
     * @return float|null
     */
    private static function nullableFloat($v, $min, $max)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $n = (float) $v;
        if ($n < $min || $n > $max) {
            return null;
        }
        return $n;
    }

    /**
     * @param mixed $raw
     * @return array|null|false
     */
    private static function parseUserIds($raw)
    {
        if ($raw === null || $raw === '' || $raw === array()) {
            return null;
        }
        if (is_string($raw)) {
            $parts = preg_split('/[\s,;]+/', $raw);
            $raw = is_array($parts) ? $parts : array();
        }
        if (!is_array($raw)) {
            return false;
        }
        $ids = array();
        foreach ($raw as $item) {
            if (!is_numeric($item)) {
                continue;
            }
            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 500) {
            return false;
        }
        return $ids === array() ? null : $ids;
    }

    /**
     * @param string $col
     * @return bool
     */
    private static function userHasColumn($col)
    {
        if (!class_exists('DatabaseMigrator')) {
            return false;
        }
        return DatabaseMigrator::tableColumnExists('user', $col);
    }

    /**
     * @param PDO $pdo
     * @param int $userId
     * @return bool
     */
    private static function userIsActive(PDO $pdo, $userId)
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT `status` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array((int) $userId));
            $st = $stmt->fetchColumn();
            return $st !== false && (int) $st === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $s
     * @return int
     */
    private static function strLen($s)
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen((string) $s, 'UTF-8');
        }
        return strlen((string) $s);
    }

    /**
     * @param string $s
     * @param int    $max
     * @return string
     */
    private static function strCut($s, $max)
    {
        if (function_exists('mb_substr')) {
            return (string) mb_substr((string) $s, 0, (int) $max, 'UTF-8');
        }
        return substr((string) $s, 0, (int) $max);
    }

    // ─── 模块二：全站优惠（vs_config 单键 + Redis 镜像；不改 api 表价）───

    /**
     * 解析当前全站优惠（未过期才 active）；Redis miss 则读配置并按剩余秒数重建镜像
     *
     * @param bool $refresh 忽略请求内缓存
     * @return array{active:bool,mode:string,percent_pay:int|null,expire_at:string,label:string,ttl_seconds:int,base_only:bool}
     */
    public static function sitePromoResolve($refresh = false)
    {
        if (!$refresh && self::$promoResolveCache !== null) {
            return self::$promoResolveCache;
        }

        // 计费/展示权威只认 vs_config（Config 请求内已有内存缓存）。
        // Redis 仅作镜像：禁止「只信 Redis」——镜像被清/被污都不能改活动免扣/折扣规则。
        $raw = self::readPromoConfigRaw();
        $resolved = self::normalizePromoState($raw);

        if (class_exists('RedisCache') && RedisCache::enabled()) {
            if (!empty($resolved['active'])) {
                $ttl = (int) $resolved['ttl_seconds'];
                if ($ttl > 0) {
                    RedisCache::put(
                        self::REDIS_SITE_PROMO,
                        self::promoStorePayload($resolved),
                        min($ttl, self::PROMO_REDIS_TTL_CAP)
                    );
                }
            } else {
                RedisCache::forget(self::REDIS_SITE_PROMO);
            }
        }

        self::$promoResolveCache = $resolved;
        return $resolved;
    }

    /**
     * 本次调用实扣积分（活动规则下的扣费额；不改库表原价、不改 needkey）
     *
     * - 全免：返回 0 = 本次免扣，不是把接口标价改成 0
     * - 折扣：返回原价×比例 = 本次实扣
     * - 鉴权 KEY 只看库表，禁止用本返回值判断要不要 KEY
     *
     * @param mixed $basePrice 库表原价
     * @param mixed $charge
     * @return float
     */
    public static function sitePromoEffectivePrice($basePrice, $charge)
    {
        if (!class_exists('ApiManager')) {
            return max(0.0, round((float) $basePrice, 4));
        }
        $chargeN = ApiManager::normalizeCharge($charge);
        $base = ApiManager::normalizePrice($basePrice);
        if ($chargeN !== ApiManager::CHARGE_PAID || $base <= 0) {
            return $base;
        }
        $r = self::sitePromoResolve();
        if (empty($r['active'])) {
            return $base;
        }
        if ($r['mode'] === 'free') {
            return 0.0;
        }
        if ($r['mode'] === 'discount') {
            $pct = isset($r['percent_pay']) ? (int) $r['percent_pay'] : 0;
            if ($pct < 1 || $pct > 99) {
                return $base;
            }
            // 折扣实扣：对齐万分之一且禁止「四舍五入成 0」；正数至少扣 0.0001（全免模式除外）
            return self::discountEffectivePrice($base, $pct);
        }
        return $base;
    }

    /**
     * 折扣实扣（原价 × 应付百分比），对齐 decimal(14,4)
     *
     * - 不用 round：过小会被舍成 0，活动期等价免扣
     - 正数向上取到 0.0001；结果不超过原价
     *
     * @param float $base 库表原价（已 normalize，>0）
     * @param int   $percentPay 1～99
     * @return float
     */
    private static function discountEffectivePrice($base, $percentPay)
    {
        $base = (float) $base;
        $percentPay = (int) $percentPay;
        if ($base <= 0 || $percentPay < 1 || $percentPay > 99) {
            return $base > 0 ? $base : 0.0;
        }
        $raw = $base * $percentPay / 100.0;
        if ($raw <= 0) {
            return min($base, 0.0001);
        }
        // 向上对齐到 0.0001（减极小量规避浮点边界）
        $eff = ceil($raw * 10000.0 - 1e-9) / 10000.0;
        if ($eff < 0.0001) {
            $eff = 0.0001;
        }
        if ($eff > $base) {
            $eff = $base;
        }
        return $eff;
    }

    /**
     * 管理端状态（含已过期提示）
     *
     * @return array
     */
    public static function sitePromoAdminStatus()
    {
        $r = self::sitePromoResolve(true);
        $store = self::readPromoConfigRaw();
        $expired = false;
        if (is_array($store) && isset($store['mode']) && $store['mode'] !== 'off') {
            $exp = isset($store['expire_at']) ? strtotime((string) $store['expire_at']) : false;
            if ($exp !== false && $exp <= time()) {
                $expired = true;
            }
        }
        return array(
            'active'       => !empty($r['active']),
            'mode'         => isset($r['mode']) ? (string) $r['mode'] : 'off',
            'percent_pay'  => isset($r['percent_pay']) ? $r['percent_pay'] : null,
            'expire_at'    => isset($r['expire_at']) ? (string) $r['expire_at'] : '',
            'label'        => isset($r['label']) ? (string) $r['label'] : '',
            'ttl_seconds'  => isset($r['ttl_seconds']) ? (int) $r['ttl_seconds'] : 0,
            'expired_hint' => $expired,
            'store_mode'   => is_array($store) && isset($store['mode']) ? (string) $store['mode'] : 'off',
        );
    }

    /**
     * 保存折扣（与全免互斥）
     *
     * @param mixed  $zhe       打几折：8 / 8.0 / 80（≥10 视为 percent_pay）
     * @param string $expireAt
     * @param string $label
     * @param int    $adminId
     * @return array{ok:bool,msg:string,data?:array}
     */
    public static function saveSitePromoDiscount($zhe, $expireAt, $label, $adminId)
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $pct = self::parseZheToPercentPay($zhe);
        if ($pct === null) {
            return array('ok' => false, 'msg' => '请填写有效折扣（如打 8 折填 8，范围约 0.1～9.9 折）');
        }
        $exp = self::validatePromoExpireAt($expireAt);
        if (empty($exp['ok'])) {
            return array('ok' => false, 'msg' => isset($exp['msg']) ? $exp['msg'] : '到期时间无效');
        }
        $label = self::sanitizePromoLabel($label);
        if ($label === '') {
            $label = '打' . self::formatZheLabel($pct) . '折';
        }
        $payload = array(
            'mode'         => 'discount',
            'percent_pay'  => $pct,
            'expire_at'    => $exp['expire_at'],
            'label'        => $label,
            'updated_at'   => date('Y-m-d H:i:s'),
            'updated_by'   => (int) $adminId,
        );
        return self::persistSitePromo($payload);
    }

    /**
     * 一键全站免费（与折扣互斥）
     *
     * @param string $expireAt
     * @param string $label
     * @param int    $adminId
     * @return array{ok:bool,msg:string,data?:array}
     */
    public static function saveSitePromoFree($expireAt, $label, $adminId)
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $exp = self::validatePromoExpireAt($expireAt);
        if (empty($exp['ok'])) {
            return array('ok' => false, 'msg' => isset($exp['msg']) ? $exp['msg'] : '到期时间无效');
        }
        $label = self::sanitizePromoLabel($label);
        if ($label === '') {
            $label = '活动免费';
        }
        $payload = array(
            'mode'         => 'free',
            'percent_pay'  => null,
            'expire_at'    => $exp['expire_at'],
            'label'        => $label,
            'updated_at'   => date('Y-m-d H:i:s'),
            'updated_by'   => (int) $adminId,
        );
        return self::persistSitePromo($payload);
    }

    /**
     * 重置 / 关闭全站优惠
     *
     * @param int $adminId
     * @return array{ok:bool,msg:string,data?:array}
     */
    public static function clearSitePromo($adminId)
    {
        $adminGate = self::requireAdminCaller($adminId);
        if ($adminGate !== null) {
            return $adminGate;
        }
        $payload = array(
            'mode'         => 'off',
            'percent_pay'  => null,
            'expire_at'    => '',
            'label'        => '',
            'updated_at'   => date('Y-m-d H:i:s'),
            'updated_by'   => (int) $adminId,
        );
        return self::persistSitePromo($payload);
    }

    /**
     * @param array $payload
     * @return array{ok:bool,msg:string,data?:array}
     */
    private static function persistSitePromo(array $payload)
    {
        if (!class_exists('Config')) {
            return array('ok' => false, 'msg' => '配置系统不可用');
        }
        try {
            Config::set(self::PROMO_CONFIG_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '保存失败，请稍后重试');
        }
        self::$promoResolveCache = null;
        if (class_exists('RedisCache') && RedisCache::enabled()) {
            RedisCache::forget(self::REDIS_SITE_PROMO);
            $resolved = self::normalizePromoState($payload);
            if (!empty($resolved['active'])) {
                $ttl = (int) $resolved['ttl_seconds'];
                if ($ttl > 0) {
                    RedisCache::put(
                        self::REDIS_SITE_PROMO,
                        self::promoStorePayload($resolved),
                        min($ttl, self::PROMO_REDIS_TTL_CAP)
                    );
                }
            }
        }
        if (class_exists('RedisCache')) {
            RedisCache::forget(RedisCache::KEY_FRONTEND_API);
        }
        return array(
            'ok'   => true,
            'msg'  => '已保存',
            'data' => self::sitePromoAdminStatus(),
        );
    }

    /**
     * @param mixed $raw
     * @return array
     */
    private static function normalizePromoState($raw)
    {
        $off = array(
            'active'      => false,
            'mode'        => 'off',
            'percent_pay' => null,
            'expire_at'   => '',
            'label'       => '',
            'ttl_seconds' => 0,
            'base_only'   => true,
        );
        if (!is_array($raw)) {
            return $off;
        }
        $mode = isset($raw['mode']) ? strtolower(trim((string) $raw['mode'])) : 'off';
        if ($mode !== 'discount' && $mode !== 'free') {
            return $off;
        }
        $expireAt = isset($raw['expire_at']) ? trim((string) $raw['expire_at']) : '';
        $expTs = $expireAt !== '' ? strtotime($expireAt) : false;
        if ($expTs === false || $expTs <= time()) {
            return $off;
        }
        $pct = null;
        if ($mode === 'discount') {
            $pct = isset($raw['percent_pay']) ? (int) $raw['percent_pay'] : 0;
            if ($pct < 1 || $pct > 99) {
                return $off;
            }
        }
        $label = self::sanitizePromoLabel(isset($raw['label']) ? $raw['label'] : '');
        return array(
            'active'      => true,
            'mode'        => $mode,
            'percent_pay' => $pct,
            'expire_at'   => date('Y-m-d H:i:s', $expTs),
            'label'       => $label,
            'ttl_seconds' => max(1, $expTs - time()),
            'base_only'   => false,
        );
    }

    /**
     * @param array $resolved
     * @return array
     */
    private static function promoStorePayload(array $resolved)
    {
        return array(
            'mode'        => isset($resolved['mode']) ? $resolved['mode'] : 'off',
            'percent_pay' => isset($resolved['percent_pay']) ? $resolved['percent_pay'] : null,
            'expire_at'   => isset($resolved['expire_at']) ? $resolved['expire_at'] : '',
            'label'       => isset($resolved['label']) ? $resolved['label'] : '',
        );
    }

    /**
     * @return array|null
     */
    private static function readPromoConfigRaw()
    {
        if (!class_exists('Config')) {
            return null;
        }
        $cfg = Config::get(self::PROMO_CONFIG_KEY, '');
        if (!is_string($cfg) || $cfg === '') {
            return null;
        }
        $decoded = json_decode($cfg, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string $expireAt
     * @return array{ok:bool,msg?:string,expire_at?:string}
     */
    private static function validatePromoExpireAt($expireAt)
    {
        $expireAt = trim((string) $expireAt);
        if ($expireAt === '') {
            return array('ok' => false, 'msg' => '请填写到期时间');
        }
        $ts = strtotime($expireAt);
        if ($ts === false) {
            return array('ok' => false, 'msg' => '到期时间格式无效');
        }
        $now = time();
        $diff = $ts - $now;
        if ($diff < self::PROMO_MIN_SECONDS) {
            return array('ok' => false, 'msg' => '到期时间须至少晚于当前 5 分钟');
        }
        if ($diff > self::PROMO_MAX_SECONDS) {
            return array('ok' => false, 'msg' => '到期时间最长不超过约 366 天');
        }
        return array('ok' => true, 'expire_at' => date('Y-m-d H:i:s', $ts));
    }

    /**
     * 打 X 折 → percent_pay（付原价百分比）
     *
     * @param mixed $zhe
     * @return int|null
     */
    private static function parseZheToPercentPay($zhe)
    {
        if (is_string($zhe)) {
            $zhe = trim(str_replace(array('折', ' '), '', $zhe));
        }
        if ($zhe === '' || $zhe === null) {
            return null;
        }
        $n = (float) $zhe;
        if (!is_finite($n) || $n <= 0) {
            return null;
        }
        // ≥10：视为已是应付百分比（如 80）
        if ($n >= 10) {
            $pct = (int) round($n);
        } else {
            // 8 / 8.5 → 80 / 85
            $pct = (int) round($n * 10);
        }
        if ($pct < 1 || $pct > 99) {
            return null;
        }
        return $pct;
    }

    /**
     * @param int $percentPay
     * @return string
     */
    private static function formatZheLabel($percentPay)
    {
        $percentPay = (int) $percentPay;
        if ($percentPay % 10 === 0) {
            return (string) (int) ($percentPay / 10);
        }
        return rtrim(rtrim(number_format($percentPay / 10, 1, '.', ''), '0'), '.');
    }

    /**
     * @param mixed $label
     * @return string
     */
    private static function sanitizePromoLabel($label)
    {
        $s = trim(strip_tags((string) $label));
        if ($s === '') {
            return '';
        }
        return self::strCut($s, self::PROMO_LABEL_MAX);
    }
}
