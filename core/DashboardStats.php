<?php
/**
 * 文件：core/DashboardStats.php
 * 作用：管理员控制台 / 数据大屏统计聚合（分层 TTL 缓存，避免大表反复扫）
 */

class DashboardStats
{
    const TTL_LIVE = 8;
    const TTL_TODAY = 60;
    const TTL_HOUR = 120;
    const TTL_WEEK = 300;
    const TTL_GEO = 60;

    /** @var string|null */
    private static $epochCache = null;

    /**
     * 控制台/大屏 AJAX 限流（按管理员）
     *
     * @param string $action live|refresh|snapshot
     * @return void 超限时直接 AjaxResponse::error
     */
    public static function assertAjaxRateLimit($action)
    {
        $action = (string) $action;
        $admin = class_exists('Auth') ? Auth::user() : null;
        $aid = ($admin && isset($admin['id'])) ? (int) $admin['id'] : 0;
        $bucket = 'admin:dashboard:' . $action . ':' . $aid;
        $max = 20;
        $window = 60;
        if ($action === 'live') {
            // E202：按所选间隔放行 + 缓冲；1 秒档需约 60+/min，上限 80
            $interval = self::liveIntervalSeconds();
            $max = (int) ceil(60 / max(1, $interval)) + 8;
            if ($max < 12) {
                $max = 12;
            }
            if ($max > 80) {
                $max = 80;
            }
            $window = 60;
        } elseif ($action === 'refresh') {
            $max = 10;
            $window = 60;
        } elseif ($action === 'snapshot') {
            $max = 16;
            $window = 60;
        }
        if (class_exists('RateLimitStore') && !RateLimitStore::allow($bucket, $window, $max, true)) {
            AjaxResponse::error('操作过于频繁，请稍后再试', 429);
        }
    }

    /**
     * data-boot 安全 JSON（防属性/`<` 注入）
     *
     * @param array $data
     * @return string
     */
    public static function bootAttrJson(array $data)
    {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_HEX_TAG')) {
            $flags |= JSON_HEX_TAG;
        }
        if (defined('JSON_HEX_AMP')) {
            $flags |= JSON_HEX_AMP;
        }
        if (defined('JSON_HEX_APOS')) {
            $flags |= JSON_HEX_APOS;
        }
        $json = json_encode($data, $flags);
        if ($json === false) {
            $json = '{}';
        }
        return vs_e($json);
    }

    /**
     * 控制台首屏壳（禁止同步扫大表；重数据走 AJAX snapshot）
     *
     * @return array
     */
    public static function consoleBootShell()
    {
        return array(
            'server_time'  => date('Y-m-d H:i:s'),
            'weekday'      => self::weekdayLabel(),
            'kpi'          => array(),
            'type_trend'   => array('labels' => array(), 'guest' => array(), 'key' => array(), 'points' => array()),
            'rate_trend'   => array('labels' => array(), 'success' => array(), 'fail' => array()),
            'top_apis'     => array(),
            'sys_overview' => array(),
            'recent'       => array(),
            'server'       => array(),
            'boot_light'   => true,
            'live_interval'=> self::liveIntervalSeconds(),
        );
    }

    /**
     * 控制台整页快照
     *
     * @param bool $refresh
     * @return array
     */
    public static function consoleSnapshot($refresh = false)
    {
        if ($refresh) {
            self::bumpEpoch();
            if (class_exists('RedisCache') && method_exists('RedisCache', 'invalidateApiLog')) {
                RedisCache::invalidateApiLog();
            }
        }
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            StatDayManager::ensureDay(date('Y-m-d'));
        }
        $cached = self::remember('console_full', self::TTL_TODAY, function () {
            return array(
                'kpi'          => self::kpiBlock(),
                'type_trend'   => self::typeTrend7d(),
                'rate_trend'   => self::rateTrend7d(),
                'top_apis'     => self::topApisToday(10),
                'sys_overview' => self::sysOverview(),
            );
        });
        if (!is_array($cached)) {
            $cached = array();
        }
        // 最近调用不进整页快照：避免 45s 软刷用旧列表覆盖 live 已刷新的数据
        $cached['recent'] = self::recentCallsCompact();
        $cached['server'] = self::safePanelSnapshot($refresh);
        $cached['server_time'] = date('Y-m-d H:i:s');
        $cached['weekday'] = self::weekdayLabel();
        $cached['boot_light'] = false;
        $cached['live_interval'] = self::liveIntervalSeconds();
        return $cached;
    }

    /** live 间隔下限（秒）；高性能站可开 1～5（E202） */
    const LIVE_INTERVAL_MIN = 1;
    /** live 间隔上限（秒） */
    const LIVE_INTERVAL_MAX = 30;
    /** live 间隔默认（秒）：偏稳，降防火墙误拦 */
    const LIVE_INTERVAL_DEFAULT = 10;

    /**
     * 可选档位：1～5 秒 + 10/15/20/30（E202）
     *
     * @return int[]
     */
    public static function liveIntervalChoices()
    {
        return array(1, 2, 3, 4, 5, 10, 15, 20, 30);
    }

    /**
     * 控制台 / 大屏 live 轮询间隔（秒），1～30（档位见 liveIntervalChoices），默认 10（E202）
     *
     * @return int
     */
    public static function liveIntervalSeconds()
    {
        $n = self::LIVE_INTERVAL_DEFAULT;
        try {
            $n = (int) Config::get('dashboard_live_interval', (string) self::LIVE_INTERVAL_DEFAULT);
        } catch (Exception $e) {
            $n = self::LIVE_INTERVAL_DEFAULT;
        }
        if ($n < self::LIVE_INTERVAL_MIN) {
            $n = self::LIVE_INTERVAL_MIN;
        }
        if ($n > self::LIVE_INTERVAL_MAX) {
            $n = self::LIVE_INTERVAL_MAX;
        }
        return $n;
    }

    /**
     * 控制台轻量轮询：时钟 + 今日 KPI + 最近调用（趋势/TOP 走 snapshot）
     *
     * @return array
     */
    public static function consoleLiveTick()
    {
        $ttl = max(1, self::liveIntervalSeconds());
        $of = self::remember('okfail_today_live', $ttl, function () {
            return self::okFailToday();
        });
        $ok = (int) $of['ok'];
        $fail = (int) $of['fail'];
        $total = max(1, $ok + $fail);
        $counts = self::remember('api_user_counts_live', $ttl, function () {
            return array(
                'api_total'   => self::countApis(),
                'user_total'  => self::countUsers(),
                'user_delta'  => self::countUsersCreatedSince(date('Y-m-d 00:00:00')),
                'total_calls' => self::sumApiCalls(),
            );
        });
        return array(
            'server_time'   => date('Y-m-d H:i:s'),
            'weekday'       => self::weekdayLabel(),
            'live_interval' => self::liveIntervalSeconds(),
            'kpi'           => array(
                'api_total'     => (int) $counts['api_total'],
                'user_total'    => (int) $counts['user_total'],
                'user_delta'    => (int) $counts['user_delta'],
                'today_calls'   => self::countTodayLive(),
                'total_calls'   => (int) $counts['total_calls'],
                'success_rate'  => round($ok * 100 / $total, 2),
                'fail_rate'     => round($fail * 100 / $total, 2),
                'success_count' => $ok,
                'fail_count'    => $fail,
            ),
            'recent'        => self::recentCallsCompact(),
            'sys_overview'  => self::sysOverviewLive($ttl),
            // live 强制刷新面板，与设置间隔一致（禁止 10s 成功缓存拖慢网速/负载）
            'server'        => self::safePanelSnapshot(true),
            'top_apis'      => self::remember('top_apis_live_10', $ttl, function () {
                return self::topApisToday(10);
            }),
        );
    }

    /**
     * 面板监控失败不得影响控制台 KPI / 最近调用
     *
     * @param bool $refresh
     * @return array
     */
    private static function safePanelSnapshot($refresh = false)
    {
        if (!class_exists('PanelMonitor')) {
            return array(
                'enabled'    => false,
                'configured' => false,
                'ok'         => false,
                'error'      => '面板暂时不可用',
                'provider'   => '',
            );
        }
        try {
            $snap = PanelMonitor::snapshot($refresh);
            if (!is_array($snap) || !array_key_exists('enabled', $snap)) {
                return PanelMonitor::configOnlySnapshot('面板暂时不可用');
            }
            return $snap;
        } catch (Exception $e) {
            return self::fallbackPanelSnapshot('面板暂时不可用');
        } catch (Throwable $e) {
            return self::fallbackPanelSnapshot('面板暂时不可用');
        }
    }

    /**
     * 面板快照异常回落：必须带上 Config 中的类型/地址状态，禁止只写 enabled 导致「请选择面板类型」误判（E192）
     *
     * @param string $msg
     * @return array
     */
    private static function fallbackPanelSnapshot($msg)
    {
        if (!class_exists('PanelMonitor')) {
            return array();
        }
        try {
            return PanelMonitor::configOnlySnapshot($msg);
        } catch (Exception $e) {
            $s = PanelMonitor::emptySnapshot();
            $s['error'] = '面板暂时不可用';
            return $s;
        } catch (Throwable $e) {
            $s = PanelMonitor::emptySnapshot();
            $s['error'] = '面板暂时不可用';
            return $s;
        }
    }

    /**
     * 数据大屏整页快照（Redis：screen_full；地理分今日/实时双缓存）
     *
     * @param bool $refresh
     * @return array
     */
    public static function screenSnapshot($refresh = false)
    {
        if ($refresh) {
            self::bumpEpoch();
        }
        $pack = self::remember('screen_full', self::TTL_TODAY, function () {
            $kpi = self::kpiBlock();
            return array(
                'kpi' => array(
                    'today_calls'   => $kpi['today_calls'],
                    'today_delta'   => $kpi['today_delta'],
                    'total_calls'   => $kpi['total_calls'],
                    'total_delta'   => $kpi['total_delta'],
                    'success_rate'  => $kpi['success_rate'],
                    'success_delta' => $kpi['success_delta'],
                    'fail_rate'     => $kpi['fail_rate'],
                    'fail_delta'    => $kpi['fail_delta'],
                ),
                'hourly'      => self::hourly24h(),
                'top_apis'    => self::topApisToday(12),
                'geo_today'   => self::geoDistributionToday(),
                'recent'      => self::recentCalls(12),
                'current_rpm' => self::currentRpm(),
            );
        });
        $geoLive = self::geoDistributionLive();
        $geoToday = isset($pack['geo_today']) ? $pack['geo_today'] : self::geoDistributionToday();
        return array(
            'server_time'   => date('Y-m-d H:i:s'),
            'live_interval' => self::liveIntervalSeconds(),
            'kpi'           => isset($pack['kpi']) ? $pack['kpi'] : array(),
            'hourly'        => isset($pack['hourly']) ? $pack['hourly'] : array('labels' => array(), 'series' => array()),
            'top_apis'      => isset($pack['top_apis']) ? $pack['top_apis'] : array(),
            'geo_live'      => $geoLive,
            'geo_today'     => $geoToday,
            'geo'           => $geoLive,
            'recent'        => isset($pack['recent']) ? $pack['recent'] : array(),
            'current_rpm'   => isset($pack['current_rpm']) ? (int) $pack['current_rpm'] : 0,
        );
    }

    /**
     * 大屏轻量轮询（最新日志 + RPM + 今日 KPI + 地理飞线双模）
     *
     * @return array
     */
    public static function screenLiveTick()
    {
        $ttl = max(1, self::liveIntervalSeconds());
        $geoLive = self::geoDistributionLive();
        $geoToday = self::geoDistributionToday();
        return array(
            'server_time'   => date('Y-m-d H:i:s'),
            'live_interval' => self::liveIntervalSeconds(),
            'current_rpm'   => self::currentRpm(),
            'kpi'           => array(
                'today_calls'  => self::countTodayCached(),
                'total_calls'  => self::sumApiCallsCached($ttl),
                'success_rate' => self::todaySuccessRateCached(),
                'fail_rate'    => self::todayFailRateCached(),
            ),
            'geo_live'      => $geoLive,
            'geo_today'     => $geoToday,
            'geo'           => $geoLive,
            'recent'        => self::recentCalls(12),
            // live 必须带 TOP，否则大屏排行只靠 soft 才刷新（易错点 E150）
            'top_apis'      => self::remember('top_apis_live_12', $ttl, function () {
                return self::topApisToday(12);
            }),
        );
    }

    /**
     * @return array
     */
    private static function kpiBlock()
    {
        return self::remember('kpi', self::TTL_TODAY, function () {
            $today = self::countTodayCached();
            $yesterday = self::countRange(
                date('Y-m-d 00:00:00', strtotime('-1 day')),
                date('Y-m-d 23:59:59', strtotime('-1 day'))
            );
            $okFail = self::okFailToday();
            $okFailY = self::okFailDay(date('Y-m-d', strtotime('-1 day')));
            $total = self::sumApiCalls();
            $weekAgoTotal = max(0, $total - self::countRange(
                date('Y-m-d 00:00:00', strtotime('-7 day')),
                date('Y-m-d 23:59:59')
            ));
            $apiCount = self::countApis();
            $apiCountWeek = self::countApisCreatedSince(date('Y-m-d 00:00:00', strtotime('-7 day')));
            $userCount = self::countUsers();
            $userToday = self::countUsersCreatedSince(date('Y-m-d 00:00:00'));
            $spark7 = self::sparkFromDaily(7);

            $todayOk = (int) $okFail['ok'];
            $todayFail = (int) $okFail['fail'];
            $todayTotal = max(1, $todayOk + $todayFail);
            $rate = round($todayOk * 100 / $todayTotal, 2);
            $failRate = round($todayFail * 100 / $todayTotal, 2);

            $yOk = (int) $okFailY['ok'];
            $yFail = (int) $okFailY['fail'];
            $yTotal = max(1, $yOk + $yFail);
            $yRate = round($yOk * 100 / $yTotal, 2);
            $yFailRate = round($yFail * 100 / $yTotal, 2);

            return array(
                'api_total'       => $apiCount,
                'api_delta'       => $apiCountWeek,
                'api_spark'       => $spark7,
                'user_total'      => $userCount,
                'user_delta'      => $userToday,
                'user_spark'      => self::sparkFromUsers(7),
                'today_calls'     => $today,
                'today_delta'     => self::pctDelta($today, $yesterday),
                'today_spark'     => $spark7,
                'success_rate'    => $rate,
                'success_delta'   => round($rate - $yRate, 2),
                'fail_rate'       => $failRate,
                'fail_delta'      => round($failRate - $yFailRate, 2),
                'success_count'   => $todayOk,
                'fail_count'      => $todayFail,
                'total_calls'     => $total,
                'total_delta'     => self::pctDelta($total, $weekAgoTotal),
            );
        });
    }

    /**
     * 近 7 日：游客 / 密钥 / 积分（单次聚合，避免 7 次全表扫）
     *
     * @return array
     */
    private static function typeTrend7d()
    {
        return self::remember('type_trend_7d', self::TTL_WEEK, function () {
            $labels = array();
            $guest = array();
            $key = array();
            $points = array();
            $byDay = self::typeCountsLastDays(7);
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime('-' . $i . ' day'));
                $labels[] = self::dayShortLabel(strtotime($day));
                $row = isset($byDay[$day]) ? $byDay[$day] : array('guest' => 0, 'key' => 0, 'points' => 0);
                $guest[] = (int) $row['guest'];
                $key[] = (int) $row['key'];
                $points[] = (int) $row['points'];
            }
            return array(
                'labels' => $labels,
                'guest'  => $guest,
                'key'    => $key,
                'points' => $points,
            );
        });
    }

    /**
     * 近 7 日成功率 / 失败率（单次聚合）
     *
     * @return array
     */
    private static function rateTrend7d()
    {
        return self::remember('rate_trend_7d', self::TTL_WEEK, function () {
            $labels = array();
            $success = array();
            $fail = array();
            $byDay = self::okFailLastDays(7);
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime('-' . $i . ' day'));
                $labels[] = self::dayShortLabel(strtotime($day));
                $of = isset($byDay[$day]) ? $byDay[$day] : array('ok' => 0, 'fail' => 0);
                $t = max(1, (int) $of['ok'] + (int) $of['fail']);
                $success[] = round(((int) $of['ok']) * 100 / $t, 2);
                $fail[] = round(((int) $of['fail']) * 100 / $t, 2);
            }
            return array(
                'labels'  => $labels,
                'success' => $success,
                'fail'    => $fail,
            );
        });
    }

    /**
     * @param int $limit
     * @return array
     */
    private static function topApisToday($limit = 10)
    {
        $limit = max(1, min(20, (int) $limit));
        return self::remember('top_apis_' . $limit, self::TTL_TODAY, function () use ($limit) {
            if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
                $row = StatDayManager::todayRow();
                $json = $row && isset($row['topjson']) ? (string) $row['topjson'] : '[]';
                return StatDayManager::topListFromJson($json, $limit);
            }
            if (!ApiLogManager::tableReady()) {
                return array();
            }
            try {
                $pdo = Database::connect();
                self::applyTimeout($pdo);
                $sql = 'SELECT `apiname`, COUNT(*) AS c
                    FROM `' . Database::table('apilog') . '`
                    WHERE `createtime` >= CURDATE() AND `createtime` < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    GROUP BY `apiname`
                    ORDER BY c DESC
                    LIMIT ' . (int) $limit;
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $max = 0;
                $out = array();
                foreach ($rows as $r) {
                    $c = (int) $r['c'];
                    if ($c > $max) {
                        $max = $c;
                    }
                }
                foreach ($rows as $r) {
                    $c = (int) $r['c'];
                    $name = trim((string) $r['apiname']);
                    if ($name === '') {
                        $name = '未命名接口';
                    }
                    $out[] = array(
                        'name'  => $name,
                        'count' => $c,
                        'pct'   => $max > 0 ? round($c * 100 / $max, 1) : 0,
                    );
                }
                return $out;
            } catch (Exception $e) {
                return array();
            }
        });
    }

    /**
     * @return array
     */
    private static function sysOverview()
    {
        return self::remember('sys_overview', self::TTL_TODAY, function () {
            return self::sysOverviewData();
        });
    }

    /**
     * 控制台 live：系统概览与刷新间隔对齐的短缓存
     *
     * @param int $ttl
     * @return array
     */
    private static function sysOverviewLive($ttl)
    {
        $ttl = max(1, (int) $ttl);
        return self::remember('sys_overview_live', $ttl, function () {
            return self::sysOverviewData();
        });
    }

    /**
     * @return array
     */
    private static function sysOverviewData()
    {
        $pending = class_exists('ApiManager') ? (int) ApiManager::countPendingReview() : 0;
        $disabled = self::countApisByStatus(ApiManager::STATUS_DISABLED);
        $maint = self::countApisByStatus(ApiManager::STATUS_MAINTENANCE);
        $orders = self::countTodayOrders();
        $income = self::sumTodayRecharge();
        $feedback = self::countPendingFeedback();
        return array(
            array('key' => 'pending', 'name' => '待审核接口', 'value' => $pending, 'tone' => 'warn'),
            array('key' => 'disabled', 'name' => '已禁用接口', 'value' => $disabled, 'tone' => 'danger'),
            array('key' => 'maint', 'name' => '维护中接口', 'value' => $maint, 'tone' => 'info'),
            array('key' => 'orders', 'name' => '今日订单', 'value' => $orders, 'tone' => 'neutral'),
            array('key' => 'income', 'name' => '今日收入', 'value' => '¥' . number_format($income, 2), 'tone' => 'success'),
            array('key' => 'feedback', 'name' => '待处理反馈', 'value' => $feedback, 'tone' => 'warn'),
        );
    }

    /**
     * 控制台最近调用：按 live 间隔短缓存，listPaged skip_cache 避免页缓存拖慢
     * 下发 id / apiname / ip / httpcode / time
     *
     * @return array
     */
    private static function recentCallsCompact()
    {
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            return array();
        }
        $ttl = max(1, self::liveIntervalSeconds());
        return self::remember('recent_compact_live', $ttl, function () {
            try {
                $paged = ApiLogManager::listPaged(array(
                    'page'       => 1,
                    'pagesize'   => 20,
                    'q'          => '',
                    'ok'         => null,
                    'apiid'      => 0,
                    'before_id'  => 0,
                    'skip_cache' => true,
                ));
                $list = isset($paged['list']) && is_array($paged['list']) ? $paged['list'] : array();
                $out = array();
                foreach ($list as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $ct = isset($item['createtime']) ? (string) $item['createtime'] : '';
                    $ts = $ct !== '' ? strtotime($ct) : false;
                    $out[] = array(
                        'id'       => (int) (isset($item['id']) ? $item['id'] : 0),
                        'apiname'  => (string) (isset($item['apiname']) ? $item['apiname'] : ''),
                        'ip'       => (string) (isset($item['ip']) ? $item['ip'] : ''),
                        'httpcode' => (int) (isset($item['httpcode']) ? $item['httpcode'] : 0),
                        'time'     => $ts ? date('H:i:s', $ts) : '',
                    );
                }
                return $out;
            } catch (Exception $e) {
                return array();
            }
        });
    }

    /**
     * 大屏最近调用（含状态/调用者等字段）
     *
     * @param int $limit
     * @return array
     */
    private static function recentCalls($limit = 12)
    {
        $limit = max(1, min(30, (int) $limit));
        return self::remember('recent_sf_' . $limit, self::TTL_LIVE, function () use ($limit) {
            if (!ApiLogManager::tableReady()) {
                return array();
            }
            try {
                $pdo = Database::connect();
                self::applyTimeout($pdo);
                $sql = 'SELECT l.`id`, l.`apiname`, l.`ok`, l.`httpcode`,
                        l.`userid`, l.`apikey`, l.`createtime`, u.`username`
                    FROM `' . Database::table('apilog') . '` l
                    LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = l.`userid`
                    ORDER BY l.`id` DESC
                    LIMIT ' . (int) $limit;
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $out = array();
                foreach ($rows as $r) {
                    $ok = (int) $r['ok'] === 1;
                    $uid = (int) $r['userid'];
                    $name = trim((string) (isset($r['username']) ? $r['username'] : ''));
                    if ($uid > 0) {
                        $caller = $name !== '' ? $name : ('用户#' . $uid);
                    } elseif (trim((string) $r['apikey']) !== '') {
                        $caller = '密钥调用';
                    } else {
                        $caller = '游客';
                    }
                    $initial = function_exists('mb_substr')
                        ? mb_substr($caller, 0, 1, 'UTF-8')
                        : substr($caller, 0, 1);
                    $code = (int) $r['httpcode'];
                    $codeLabel = '';
                    if (!$ok) {
                        $label = class_exists('ApiLogManager')
                            ? ApiLogManager::httpcodeLabel($code)
                            : '';
                        $codeLabel = $label !== '' ? ($code . ' · ' . $label) : (string) $code;
                    }
                    $out[] = array(
                        'id'         => (int) $r['id'],
                        'time'       => date('H:i:s', strtotime((string) $r['createtime'])),
                        'apiname'    => (string) $r['apiname'],
                        'ok'         => $ok ? 1 : 0,
                        'status'     => $ok ? 'success' : 'error',
                        'httpcode'   => $code,
                        'code_label' => $codeLabel,
                        'caller'     => $caller,
                        'initial'    => $initial !== '' ? $initial : '访',
                        // 仅成功/失败：游客与密钥一视同仁，勿因 httpcode=302 等标成「信息」
                        'level'      => $ok ? 'success' : 'error',
                    );
                }
                return $out;
            } catch (Exception $e) {
                return array();
            }
        });
    }

    /**
     * 近 24 小时按「整点桶」聚合（禁止只用 HOUR()，否则跨日同小时会叠在一起）
     *
     * @return array
     */
    private static function hourly24h()
    {
        return self::remember('hourly_24h', self::TTL_HOUR, function () {
            $nowHour = strtotime(date('Y-m-d H:00:00'));
            $bucketStarts = array();
            $labels = array();
            $series = array_fill(0, 24, 0);
            for ($i = 23; $i >= 0; $i--) {
                $ts = $nowHour - ($i * 3600);
                $bucketStarts[] = $ts;
                $labels[] = $i === 0 ? '现在' : date('H:00', $ts);
            }
            if (!ApiLogManager::tableReady()) {
                return array('labels' => $labels, 'series' => $series);
            }
            try {
                $pdo = Database::connect();
                self::applyTimeout($pdo);
                $start = date('Y-m-d H:i:s', $bucketStarts[0]);
                $stmt = $pdo->prepare(
                    'SELECT DATE_FORMAT(`createtime`, \'%Y-%m-%d %H:00:00\') AS slot, COUNT(*) AS c
                     FROM `' . Database::table('apilog') . '`
                     WHERE `createtime` >= ?
                     GROUP BY slot'
                );
                $stmt->execute(array($start));
                $map = array();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $map[(string) $r['slot']] = (int) $r['c'];
                }
                foreach ($bucketStarts as $idx => $ts) {
                    $key = date('Y-m-d H:00:00', $ts);
                    $series[$idx] = isset($map[$key]) ? (int) $map[$key] : 0;
                }
            } catch (Exception $e) {
                // ignore
            }
            return array('labels' => $labels, 'series' => $series);
        });
    }

    /**
     * @return int
     */
    private static function currentRpm()
    {
        return (int) self::remember('rpm_1m', self::TTL_LIVE, function () {
            if (!ApiLogManager::tableReady()) {
                return 0;
            }
            try {
                $pdo = Database::connect();
                self::applyTimeout($pdo);
                $stmt = $pdo->query(
                    'SELECT COUNT(*) FROM `' . Database::table('apilog') . '`
                     WHERE `createtime` >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
                );
                return max(0, (int) $stmt->fetchColumn());
            } catch (Exception $e) {
                return 0;
            }
        });
    }

    /**
     * 地理分布 · 今日（Redis geo_dist_today）
     *
     * @return array
     */
    private static function geoDistributionToday()
    {
        return self::remember('geo_dist_today', self::TTL_GEO, function () {
            return self::buildGeoPayload('today');
        });
    }

    /**
     * 地理分布 · 近窗实时（Redis geo_dist_live，短 TTL）
     *
     * @return array
     */
    private static function geoDistributionLive()
    {
        $ttl = max(1, self::liveIntervalSeconds());
        return self::remember('geo_dist_live', $ttl, function () {
            return self::buildGeoPayload('live');
        });
    }

    /**
     * @deprecated 兼容旧调用，等同今日
     * @return array
     */
    private static function geoDistribution()
    {
        return self::geoDistributionToday();
    }

    /**
     * @param string $mode live|today
     * @return array
     */
    private static function buildGeoPayload($mode)
    {
        $mode = ($mode === 'live') ? 'live' : 'today';
        $chinaCoords = self::chinaCityCoords();
        $worldCoords = self::worldCityCoords();
        if ($mode === 'live') {
            $kindCounts = self::iplocCityKindCountsRecentMinutes(45);
            $flowLimit = 36;
        } else {
            $kindCounts = self::iplocCityKindCounts(1);
            $flowLimit = 48;
        }
        $counts = array();
        foreach ($kindCounts as $city => $kinds) {
            $counts[$city] = (int) (isset($kinds['total']) ? $kinds['total'] : 0);
        }
        $hub = self::serverHubPoint();
        $china = self::buildGeoCities($chinaCoords, $counts);
        $world = self::buildGeoCities($worldCoords, $counts);
        $chinaHub = $hub;
        $worldHub = array(
            'name'  => isset($hub['name']) ? (string) $hub['name'] : '本站',
            'coord' => isset($hub['coord']) ? $hub['coord'] : array(116.4074, 39.9042),
        );
        return array(
            'mode'  => $mode,
            'china' => array(
                'cities' => $china,
                'flows'  => self::buildKindFlows($chinaCoords, $kindCounts, $chinaHub, $flowLimit),
                'hub'    => $chinaHub,
            ),
            'world' => array(
                'cities' => $world,
                'flows'  => self::buildKindFlows($worldCoords, $kindCounts, $worldHub, $flowLimit),
                'hub'    => $worldHub,
            ),
        );
    }

    /**
     * 服务器所在城市（枢纽终点）：解析本机公网 IP 归属地，失败则回退已知城市坐标
     *
     * @return array{name:string,coord:array{0:float,1:float}}
     */
    private static function serverHubPoint()
    {
        return self::remember('server_hub_point', 86400, function () {
            $coords = array_merge(self::worldCityCoords(), self::chinaCityCoords());
            $ip = self::guessServerPublicIp();
            $loc = '';
            if ($ip !== '' && class_exists('IpLocator') && IpLocator::enabled()) {
                $loc = IpLocator::lookup($ip);
            }
            $city = self::parseCityFromIploc($loc);
            if ($city !== '' && isset($coords[$city])) {
                return array(
                    'name'  => $city,
                    'coord' => array((float) $coords[$city][0], (float) $coords[$city][1]),
                );
            }
            // 仅解析到省时落到省会
            $prov = self::parseProvinceCapital($loc);
            if ($prov !== '' && isset($coords[$prov])) {
                return array(
                    'name'  => $prov,
                    'coord' => array((float) $coords[$prov][0], (float) $coords[$prov][1]),
                );
            }
            // 禁止用「调用量最高城市」作枢纽：否则同城流量被跳过，国内飞线会整片消失
            return array(
                'name'  => '本站',
                'coord' => array(116.4074, 39.9042),
            );
        });
    }

    /**
     * @return string
     */
    private static function guessServerPublicIp()
    {
        $candidates = array();
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $candidates[] = (string) $_SERVER['SERVER_ADDR'];
        }
        if (!empty($_SERVER['LOCAL_ADDR'])) {
            $candidates[] = (string) $_SERVER['LOCAL_ADDR'];
        }
        $host = '';
        if (function_exists('vs_base_url')) {
            $base = (string) vs_base_url();
            if ($base !== '') {
                $host = (string) parse_url($base, PHP_URL_HOST);
            }
        }
        if ($host === '' && class_exists('Config')) {
            $base = (string) Config::get('site_url', '');
            if ($base !== '') {
                $host = (string) parse_url($base, PHP_URL_HOST);
            }
        }
        if ($host === '' && !empty($_SERVER['HTTP_HOST'])) {
            $host = preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']);
        }
        if ($host !== '' && $host !== 'localhost' && filter_var($host, FILTER_VALIDATE_IP)) {
            $candidates[] = $host;
        } elseif ($host !== '' && $host !== 'localhost' && function_exists('gethostbynamel')) {
            $ips = @gethostbynamel($host);
            if (is_array($ips)) {
                foreach ($ips as $ip) {
                    $candidates[] = (string) $ip;
                }
            }
        }
        foreach ($candidates as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        return '';
    }

    /**
     * @param array<string,array{0:float,1:float}> $coords
     * @param array<string,int> $counts
     * @return array
     */
    private static function buildGeoCities($coords, $counts)
    {
        $out = array();
        foreach ($coords as $name => $ll) {
            $c = isset($counts[$name]) ? (int) $counts[$name] : 0;
            // 全量坐标库很大：payload 只下发有调用的城市，减轻 live 体积
            if ($c <= 0) {
                continue;
            }
            $out[] = array(
                'name'   => $name,
                'coord'  => array((float) $ll[0], (float) $ll[1]),
                'count'  => $c,
                'status' => '有调用',
                'level'  => 'active',
            );
        }
        usort($out, function ($a, $b) {
            return $b['count'] - $a['count'];
        });
        return $out;
    }

    /**
     * 按调用身份生成飞线：同城可同时有绿(密钥)/黄(游客)/红(失败)
     *
     * @param array<string,array{0:float,1:float}> $coords
     * @param array<string,array{key:int,guest:int,fail:int,total:int}> $kindCounts
     * @param array $hub
     * @param int $limit 城市×种类条目上限（前端再按 count 拆粒子）
     * @return array
     */
    private static function buildKindFlows($coords, $kindCounts, $hub, $limit = 36)
    {
        $limit = max(1, min(96, (int) $limit));
        $hubCoord = isset($hub['coord']) && is_array($hub['coord']) ? $hub['coord'] : array(116.4074, 39.9042);
        $hubName = isset($hub['name']) ? (string) $hub['name'] : '本站';
        // 总量高的城市优先
        $ranked = array();
        foreach ($kindCounts as $name => $kinds) {
            if (!isset($coords[$name])) {
                continue;
            }
            $total = (int) (isset($kinds['total']) ? $kinds['total'] : 0);
            if ($total <= 0) {
                continue;
            }
            $ranked[] = array('name' => $name, 'kinds' => $kinds, 'total' => $total);
        }
        usort($ranked, function ($a, $b) {
            return $b['total'] - $a['total'];
        });

        $toneOrder = array('key' => 'green', 'guest' => 'yellow', 'fail' => 'red');
        // 同城三种身份微偏，避免完全重叠
        $offsets = array(
            'key'   => array(0.0, 0.0),
            'guest' => array(0.18, -0.12),
            'fail'  => array(-0.16, 0.14),
        );
        $out = array();
        foreach ($ranked as $row) {
            $name = $row['name'];
            $ll = $coords[$name];
            $baseLng = (float) $ll[0];
            $baseLat = (float) $ll[1];
            $sameHub = ($name === $hubName)
                || (abs($baseLng - (float) $hubCoord[0]) < 0.05 && abs($baseLat - (float) $hubCoord[1]) < 0.05);
            foreach ($toneOrder as $kind => $tone) {
                $c = (int) (isset($row['kinds'][$kind]) ? $row['kinds'][$kind] : 0);
                if ($c <= 0) {
                    continue;
                }
                $off = $offsets[$kind];
                $fromLng = $baseLng + $off[0];
                $fromLat = $baseLat + $off[1];
                if ($sameHub) {
                    $fromLng -= 1.15;
                    $fromLat += 0.85;
                }
                $out[] = array(
                    'from'   => $name,
                    'to'     => $hubName,
                    'count'  => $c,
                    'kind'   => $kind,
                    'tone'   => $tone,
                    'coords' => array(
                        array($fromLng, $fromLat),
                        array((float) $hubCoord[0], (float) $hubCoord[1]),
                    ),
                );
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }
        return $out;
    }

    /**
     * 按调用量：有量城市 → 服务器枢纽飞线（兼容旧逻辑，供内部/回退）
     *
     * @param array $cities
     * @param array $hub
     * @param int $limit
     * @return array
     */
    private static function buildDynamicFlows($cities, $hub, $limit = 8)
    {
        $limit = max(1, min(24, (int) $limit));
        $hubCoord = isset($hub['coord']) && is_array($hub['coord']) ? $hub['coord'] : array(116.4074, 39.9042);
        $hubName = isset($hub['name']) ? (string) $hub['name'] : '本站';
        $out = array();
        foreach ($cities as $city) {
            $c = isset($city['count']) ? (int) $city['count'] : 0;
            if ($c <= 0) {
                continue;
            }
            $name = isset($city['name']) ? (string) $city['name'] : '';
            if ($name === '') {
                continue;
            }
            $coord = isset($city['coord']) && is_array($city['coord']) ? $city['coord'] : null;
            if ($coord === null || count($coord) < 2) {
                continue;
            }
            $fromLng = (float) $coord[0];
            $fromLat = (float) $coord[1];
            if ($name === $hubName
                || (abs($fromLng - (float) $hubCoord[0]) < 0.05 && abs($fromLat - (float) $hubCoord[1]) < 0.05)
            ) {
                $fromLng -= 1.15;
                $fromLat += 0.85;
            }
            $out[] = array(
                'from'   => $name,
                'to'     => $hubName,
                'count'  => $c,
                'kind'   => 'key',
                'tone'   => 'green',
                'coords' => array(
                    array($fromLng, $fromLat),
                    array((float) $hubCoord[0], (float) $hubCoord[1]),
                ),
            );
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @deprecated 飞线色改由 kind 决定，保留空实现防旧调用
     * @param int $count
     * @param int $max
     * @return string
     */
    private static function flowTone($count, $max)
    {
        return 'green';
    }

    /**
     * @param array $pairs
     * @param array<string,int> $counts
     * @return array
     */
    private static function buildFlows($pairs, $counts)
    {
        $out = array();
        foreach ($pairs as $pair) {
            $from = isset($pair[0]) ? (string) $pair[0] : '';
            $to = isset($pair[1]) ? (string) $pair[1] : '';
            if ($from === '' || $to === '') {
                continue;
            }
            $cFrom = isset($counts[$from]) ? (int) $counts[$from] : 0;
            $cTo = isset($counts[$to]) ? (int) $counts[$to] : 0;
            $out[] = array(
                'from'  => $from,
                'to'    => $to,
                'count' => max($cFrom, $cTo),
            );
        }
        return $out;
    }

    /**
     * 近 N 天：按城市拆分密钥成功 / 游客成功 / 失败
     *
     * @param int $days
     * @return array<string,array{key:int,guest:int,fail:int,total:int}>
     */
    private static function iplocCityKindCounts($days = 7)
    {
        $days = max(1, min(30, (int) $days));
        return self::aggregateIplocKinds(
            'createtime >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)',
            240
        );
    }

    /**
     * 近 N 分钟：按城市拆分密钥成功 / 游客成功 / 失败
     *
     * @param int $minutes
     * @return array<string,array{key:int,guest:int,fail:int,total:int}>
     */
    private static function iplocCityKindCountsRecentMinutes($minutes = 45)
    {
        $minutes = max(5, min(180, (int) $minutes));
        return self::aggregateIplocKinds(
            'createtime >= DATE_SUB(NOW(), INTERVAL ' . (int) $minutes . ' MINUTE)',
            160
        );
    }

    /**
     * @param string $whereTime SQL 时间条件（已内嵌安全整型）
     * @param int $limit
     * @return array<string,array{key:int,guest:int,fail:int,total:int}>
     */
    private static function aggregateIplocKinds($whereTime, $limit = 200)
    {
        $limit = max(20, min(400, (int) $limit));
        if (!ApiLogManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            // ok=0 → 失败；ok=1 且有密钥 → 密钥成功；ok=1 无密钥 → 游客
            $sql = 'SELECT `iploc`,
                    SUM(CASE WHEN `ok` = 0 THEN 1 ELSE 0 END) AS fail_c,
                    SUM(CASE WHEN `ok` = 1 AND `apikey` <> \'\' THEN 1 ELSE 0 END) AS key_c,
                    SUM(CASE WHEN `ok` = 1 AND (`apikey` = \'\' OR `apikey` IS NULL) THEN 1 ELSE 0 END) AS guest_c,
                    COUNT(*) AS total_c
                FROM `' . Database::table('apilog') . '`
                WHERE ' . $whereTime . '
                  AND `iploc` <> \'\'
                GROUP BY `iploc`
                ORDER BY total_c DESC
                LIMIT ' . (int) $limit;
            $out = array();
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $city = self::parseCityFromIploc((string) $r['iploc']);
                if ($city === '') {
                    $city = self::parseProvinceCapital((string) $r['iploc']);
                }
                if ($city === '') {
                    continue;
                }
                if (!isset($out[$city])) {
                    $out[$city] = array('key' => 0, 'guest' => 0, 'fail' => 0, 'total' => 0);
                }
                $out[$city]['key'] += (int) $r['key_c'];
                $out[$city]['guest'] += (int) $r['guest_c'];
                $out[$city]['fail'] += (int) $r['fail_c'];
                $out[$city]['total'] += (int) $r['total_c'];
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @param int $days
     * @return array<string,int>
     */
    private static function iplocCityCounts($days = 7)
    {
        $out = array();
        foreach (self::iplocCityKindCounts($days) as $city => $kinds) {
            $out[$city] = (int) $kinds['total'];
        }
        return $out;
    }

    /**
     * 近 N 分钟 iploc 城市计数（飞线实时感）
     *
     * @param int $minutes
     * @return array<string,int>
     */
    private static function iplocCityCountsRecentMinutes($minutes = 45)
    {
        $out = array();
        foreach (self::iplocCityKindCountsRecentMinutes($minutes) as $city => $kinds) {
            $out[$city] = (int) $kinds['total'];
        }
        return $out;
    }

    /**
     * @param string $iploc
     * @return string
     */
    private static function parseCityFromIploc($iploc)
    {
        if (class_exists('GeoCityCoords')) {
            return GeoCityCoords::resolveCityName($iploc);
        }
        return '';
    }

    /**
     * 仅省名时回落到省会城市名
     *
     * @param string $iploc
     * @return string
     */
    private static function parseProvinceCapital($iploc)
    {
        $iploc = trim((string) $iploc);
        if ($iploc === '') {
            return '';
        }
        // 已能解析到具体城市时，勿再被省名覆盖（如「云南省曲靖市」应保留曲靖）
        if (self::parseCityFromIploc($iploc) !== '') {
            return '';
        }
        $map = array(
            '北京' => '北京', '上海' => '上海', '天津' => '天津', '重庆' => '重庆',
            '广东' => '广州', '浙江' => '杭州', '江苏' => '南京', '四川' => '成都',
            '湖北' => '武汉', '湖南' => '长沙', '河南' => '郑州', '河北' => '石家庄',
            '山东' => '济南', '山西' => '太原', '陕西' => '西安', '福建' => '福州',
            '安徽' => '合肥', '江西' => '南昌', '辽宁' => '沈阳', '吉林' => '长春',
            '黑龙江' => '哈尔滨', '云南' => '昆明', '贵州' => '贵阳', '广西' => '南宁',
            '海南' => '海口', '甘肃' => '兰州', '青海' => '西宁', '宁夏' => '银川',
            '新疆' => '乌鲁木齐', '西藏' => '拉萨', '内蒙古' => '呼和浩特',
            '香港' => '香港', '澳门' => '澳门', '台湾' => '台北',
        );
        // 长省名优先（黑龙江 > 吉林）
        $keys = array_keys($map);
        usort($keys, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        foreach ($keys as $prov) {
            if (mb_strpos($iploc, $prov) !== false) {
                return $map[$prov];
            }
        }
        return '';
    }

    /**
     * 中国城市经纬度（委托全量库）
     *
     * @return array<string,array{0:float,1:float}>
     */
    private static function chinaCityCoords()
    {
        return class_exists('GeoCityCoords') ? GeoCityCoords::china() : array();
    }

    /**
     * 世界城市经纬度（委托全量库）
     *
     * @return array<string,array{0:float,1:float}>
     */
    private static function worldCityCoords()
    {
        return class_exists('GeoCityCoords') ? GeoCityCoords::world() : array();
    }

    private static function typeCountsLastDays($days)
    {
        $days = max(1, min(31, (int) $days));
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            $map = StatDayManager::mapLastDays($days);
            $out = array();
            foreach ($map as $d => $row) {
                $out[$d] = array(
                    'guest'  => (int) $row['guestcalls'],
                    'key'    => (int) $row['keycalls'],
                    'points' => (int) $row['pointscalls'],
                );
            }
            return $out;
        }
        $out = array();
        if (!ApiLogManager::tableReady()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day'));
            $stmt = $pdo->prepare(
                'SELECT DATE(`createtime`) AS d,
                    SUM(CASE WHEN `charged` = 1 THEN 1 ELSE 0 END) AS points_c,
                    SUM(CASE WHEN `charged` = 0 AND `apikey` <> \'\' THEN 1 ELSE 0 END) AS key_c,
                    SUM(CASE WHEN `charged` = 0 AND (`apikey` = \'\' OR `apikey` IS NULL) THEN 1 ELSE 0 END) AS guest_c
                 FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ?
                 GROUP BY DATE(`createtime`)'
            );
            $stmt->execute(array($start));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = (string) $r['d'];
                $out[$d] = array(
                    'guest'  => (int) (isset($r['guest_c']) ? $r['guest_c'] : 0),
                    'key'    => (int) (isset($r['key_c']) ? $r['key_c'] : 0),
                    'points' => (int) (isset($r['points_c']) ? $r['points_c'] : 0),
                );
            }
        } catch (Exception $e) {
            return array();
        }
        return $out;
    }

    /**
     * @param int $days
     * @return array<string,array{ok:int,fail:int}>
     */
    private static function okFailLastDays($days)
    {
        $days = max(1, min(31, (int) $days));
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            $map = StatDayManager::mapLastDays($days);
            $out = array();
            foreach ($map as $d => $row) {
                $out[$d] = array(
                    'ok'   => (int) $row['okcount'],
                    'fail' => (int) $row['failcount'],
                );
            }
            return $out;
        }
        $out = array();
        if (!ApiLogManager::tableReady()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day'));
            $stmt = $pdo->prepare(
                'SELECT DATE(`createtime`) AS d,
                    SUM(CASE WHEN `ok` = 1 THEN 1 ELSE 0 END) AS ok_c,
                    SUM(CASE WHEN `ok` = 0 THEN 1 ELSE 0 END) AS fail_c
                 FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ?
                 GROUP BY DATE(`createtime`)'
            );
            $stmt->execute(array($start));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = (string) $r['d'];
                $out[$d] = array(
                    'ok'   => (int) (isset($r['ok_c']) ? $r['ok_c'] : 0),
                    'fail' => (int) (isset($r['fail_c']) ? $r['fail_c'] : 0),
                );
            }
        } catch (Exception $e) {
            return array();
        }
        return $out;
    }

    /**
     * @param string $start
     * @param string $end
     * @return int
     */
    private static function countRange($start, $end)
    {
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            $startDay = substr((string) $start, 0, 10);
            $endDay = substr((string) $end, 0, 10);
            return StatDayManager::sumCallsBetween($startDay, $endDay);
        }
        if (!ApiLogManager::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ? AND `createtime` <= ?'
            );
            $stmt->execute(array($start, $end));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @return array{ok:int,fail:int}
     */
    private static function okFailToday()
    {
        return self::okFailDay(date('Y-m-d'));
    }

    /**
     * @param string $day
     * @return array{ok:int,fail:int}
     */
    private static function okFailDay($day)
    {
        $empty = array('ok' => 0, 'fail' => 0);
        $day = substr((string) $day, 0, 10);
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            $row = StatDayManager::getDay($day);
            if (!$row) {
                return $empty;
            }
            return array(
                'ok'   => (int) $row['okcount'],
                'fail' => (int) $row['failcount'],
            );
        }
        if (!ApiLogManager::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $stmt = $pdo->prepare(
                'SELECT
                    SUM(CASE WHEN `ok` = 1 THEN 1 ELSE 0 END) AS ok_c,
                    SUM(CASE WHEN `ok` = 0 THEN 1 ELSE 0 END) AS fail_c
                 FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ? AND `createtime` <= ?'
            );
            $stmt->execute(array($day . ' 00:00:00', $day . ' 23:59:59'));
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return array(
                'ok'   => (int) (isset($r['ok_c']) ? $r['ok_c'] : 0),
                'fail' => (int) (isset($r['fail_c']) ? $r['fail_c'] : 0),
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @return int
     */
    private static function countTodayCached()
    {
        return ApiLogManager::countToday();
    }

    /**
     * 控制台 live 专用今日调用数：复用 ApiLogManager 今日缓存键（Redis 监控可见）
     *
     * @return int
     */
    private static function countTodayLive()
    {
        return self::countTodayCached();
    }

    /**
     * @return float
     */
    private static function todaySuccessRateCached()
    {
        $of = self::remember('okfail_today', self::TTL_TODAY, function () {
            return self::okFailToday();
        });
        $t = max(1, (int) $of['ok'] + (int) $of['fail']);
        return round(((int) $of['ok']) * 100 / $t, 2);
    }

    /**
     * @return float
     */
    private static function todayFailRateCached()
    {
        $of = self::remember('okfail_today', self::TTL_TODAY, function () {
            return self::okFailToday();
        });
        $t = max(1, (int) $of['ok'] + (int) $of['fail']);
        return round(((int) $of['fail']) * 100 / $t, 2);
    }

    /**
     * @return int
     */
    private static function sumApiCalls()
    {
        if (!ApiManager::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $n = $pdo->query('SELECT COALESCE(SUM(`calls`),0) FROM `' . Database::table('api') . '`')->fetchColumn();
            return max(0, (int) $n);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $ttl
     * @return int
     */
    private static function sumApiCallsCached($ttl = 8)
    {
        $ttl = max(1, (int) $ttl);
        return (int) self::remember('sum_api_calls_live', $ttl, function () {
            return self::sumApiCalls();
        });
    }

    /**
     * @return int
     */
    private static function countApis()
    {
        if (!ApiManager::tableReady()) {
            return 0;
        }
        try {
            return (int) Database::connect()->query(
                'SELECT COUNT(*) FROM `' . Database::table('api') . '`'
            )->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param string $since
     * @return int
     */
    private static function countApisCreatedSince($since)
    {
        if (!ApiManager::tableReady()) {
            return 0;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('api') . '` WHERE `createtime` >= ?'
            );
            $stmt->execute(array($since));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $status
     * @return int
     */
    private static function countApisByStatus($status)
    {
        if (!ApiManager::tableReady()) {
            return 0;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('api') . '` WHERE `status` = ?'
            );
            $stmt->execute(array((int) $status));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @return int
     */
    private static function countUsers()
    {
        try {
            return (int) Database::connect()->query(
                'SELECT COUNT(*) FROM `' . Database::table('user') . '`'
            )->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param string $since
     * @return int
     */
    private static function countUsersCreatedSince($since)
    {
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('user') . '` WHERE `createtime` >= ?'
            );
            $stmt->execute(array($since));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @return int
     */
    private static function countTodayOrders()
    {
        if (!OrderManager::tableReady()) {
            return 0;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('orders') . '`
                 WHERE `direct` = ? AND `kind` = ? AND `createtime` >= CURDATE()'
            );
            $stmt->execute(array(OrderManager::DIRECT_INC, OrderManager::KIND_RECHARGE));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @return float
     */
    private static function sumTodayRecharge()
    {
        if (!OrderManager::tableReady()) {
            return 0.0;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COALESCE(SUM(`money`),0) FROM `' . Database::table('orders') . '`
                 WHERE `direct` = ? AND `kind` = ? AND `status` = ? AND `createtime` >= CURDATE()'
            );
            $stmt->execute(array(
                OrderManager::DIRECT_INC,
                OrderManager::KIND_RECHARGE,
                OrderManager::STATUS_DONE,
            ));
            return (float) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * @return int
     */
    private static function countPendingFeedback()
    {
        if (!class_exists('ApiFeedbackManager')) {
            return 0;
        }
        return (int) ApiFeedbackManager::countPending();
    }

    /**
     * @param int $days
     * @return int[]
     */
    private static function sparkFromDaily($days)
    {
        $days = max(1, min(31, (int) $days));
        $map = array();
        if (class_exists('StatDayManager') && StatDayManager::tableReady()) {
            foreach (StatDayManager::mapLastDays($days) as $d => $row) {
                $map[$d] = (int) $row['calls'];
            }
        } elseif (ApiLogManager::tableReady()) {
            try {
                $pdo = Database::connect();
                self::applyTimeout($pdo);
                $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day'));
                $stmt = $pdo->prepare(
                    'SELECT DATE(`createtime`) AS d, COUNT(*) AS c
                     FROM `' . Database::table('apilog') . '`
                     WHERE `createtime` >= ?
                     GROUP BY DATE(`createtime`)'
                );
                $stmt->execute(array($start));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $map[(string) $r['d']] = (int) $r['c'];
                }
            } catch (Exception $e) {
                $map = array();
            }
        }
        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' day'));
            $out[] = isset($map[$d]) ? (int) $map[$d] : 0;
        }
        return $out;
    }

    /**
     * @param int $days
     * @return int[]
     */
    private static function sparkFromUsers($days)
    {
        $days = max(1, min(31, (int) $days));
        $map = array();
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day'));
            $stmt = $pdo->prepare(
                'SELECT DATE(`createtime`) AS d, COUNT(*) AS c
                 FROM `' . Database::table('user') . '`
                 WHERE `createtime` >= ?
                 GROUP BY DATE(`createtime`)'
            );
            $stmt->execute(array($start));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[(string) $r['d']] = (int) $r['c'];
            }
        } catch (Exception $e) {
            $map = array();
        }
        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' day'));
            $out[] = isset($map[$d]) ? (int) $map[$d] : 0;
        }
        return $out;
    }

    /**
     * @param int $cur
     * @param int $prev
     * @return float
     */
    private static function pctDelta($cur, $prev)
    {
        $cur = (int) $cur;
        $prev = (int) $prev;
        if ($prev <= 0) {
            return $cur > 0 ? 100.0 : 0.0;
        }
        return round(($cur - $prev) * 100 / $prev, 1);
    }

    /**
     * 趋势图横轴：月日（如 7月26日），禁止再用周一/周二
     *
     * @param int|null $ts
     * @return string
     */
    private static function dayShortLabel($ts = null)
    {
        return date('n月j日', $ts === null ? time() : (int) $ts);
    }

    /**
     * @param int|null $ts
     * @return string
     */
    private static function weekdayLabel($ts = null)
    {
        $map = array('日', '一', '二', '三', '四', '五', '六');
        $n = (int) date('w', $ts === null ? time() : (int) $ts);
        return '周' . $map[$n];
    }

    /**
     * @param string   $suffix
     * @param int      $ttl
     * @param callable $factory
     * @return mixed
     */
    private static function remember($suffix, $ttl, $factory)
    {
        $key = (class_exists('RedisCache') ? RedisCache::KEY_DASHBOARD_PREFIX : 'cache:dashboard:')
            . self::epoch() . ':' . $suffix;
        if (class_exists('RedisCache')) {
            return RedisCache::remember($key, (int) $ttl, $factory);
        }
        return call_user_func($factory);
    }

    /**
     * @return string
     */
    private static function epoch()
    {
        if (self::$epochCache !== null) {
            return self::$epochCache;
        }
        try {
            self::$epochCache = (string) Config::get('dashboard_stats_epoch', '1');
        } catch (Exception $ex) {
            self::$epochCache = '1';
        }
        if (self::$epochCache === '') {
            self::$epochCache = '1';
        }
        return self::$epochCache;
    }

    /**
     * @return void
     */
    private static function bumpEpoch()
    {
        try {
            $cur = (int) Config::get('dashboard_stats_epoch', '1');
            $next = (string) ($cur + 1);
            Config::set('dashboard_stats_epoch', $next);
            self::$epochCache = $next;
        } catch (Exception $e) {
            self::$epochCache = (string) time();
        }
    }

    /**
     * @param PDO $pdo
     * @return void
     */
    private static function applyTimeout(PDO $pdo)
    {
        try {
            $pdo->exec('SET SESSION MAX_EXECUTION_TIME=5000');
        } catch (Exception $e) {
            // ignore
        }
    }
}
