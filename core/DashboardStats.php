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
    const TTL_GEO = 300;

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
            $interval = self::liveIntervalSeconds();
            $max = (int) ceil(60 / max(1, $interval)) + 8;
            if ($max < 20) {
                $max = 20;
            }
            if ($max > 80) {
                $max = 80;
            }
            $window = 60;
        } elseif ($action === 'refresh') {
            $max = 10;
            $window = 60;
        } elseif ($action === 'snapshot') {
            $max = 20;
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
        $cached['server_time'] = date('Y-m-d H:i:s');
        $cached['weekday'] = self::weekdayLabel();
        $cached['boot_light'] = false;
        $cached['live_interval'] = self::liveIntervalSeconds();
        return $cached;
    }

    /**
     * 控制台 live 轮询间隔（秒），设置项 1～5，默认 5
     *
     * @return int
     */
    public static function liveIntervalSeconds()
    {
        $n = 5;
        try {
            $n = (int) Config::get('dashboard_live_interval', '5');
        } catch (Exception $e) {
            $n = 5;
        }
        if ($n < 1) {
            $n = 1;
        }
        if ($n > 5) {
            $n = 5;
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
        $of = self::remember('okfail_today_live', self::TTL_LIVE, function () {
            return self::okFailToday();
        });
        $ok = (int) $of['ok'];
        $fail = (int) $of['fail'];
        $total = max(1, $ok + $fail);
        $counts = self::remember('api_user_counts_live', self::TTL_TODAY, function () {
            return array(
                'api_total'  => self::countApis(),
                'user_total' => self::countUsers(),
                'user_delta' => self::countUsersCreatedSince(date('Y-m-d 00:00:00')),
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
                'success_rate'  => round($ok * 100 / $total, 2),
                'fail_rate'     => round($fail * 100 / $total, 2),
                'success_count' => $ok,
                'fail_count'    => $fail,
            ),
            'recent'        => self::recentCallsCompact(),
        );
    }

    /**
     * 数据大屏整页快照
     *
     * @param bool $refresh
     * @return array
     */
    public static function screenSnapshot($refresh = false)
    {
        if ($refresh) {
            self::bumpEpoch();
        }
        $kpi = self::kpiBlock();
        return array(
            'server_time'    => date('Y-m-d H:i:s'),
            'kpi'            => array(
                'today_calls'   => $kpi['today_calls'],
                'today_delta'   => $kpi['today_delta'],
                'total_calls'   => $kpi['total_calls'],
                'total_delta'   => $kpi['total_delta'],
                'success_rate'  => $kpi['success_rate'],
                'success_delta' => $kpi['success_delta'],
                'fail_rate'     => $kpi['fail_rate'],
                'fail_delta'    => $kpi['fail_delta'],
            ),
            'hourly'         => self::hourly24h(),
            'top_apis'       => self::topApisToday(8),
            'geo'            => self::geoDistribution(),
            'recent'         => self::recentCalls(10),
            'current_rpm'    => self::currentRpm(),
        );
    }

    /**
     * 大屏轻量轮询（最新日志 + 当前 RPM + 今日 KPI 数字）
     *
     * @return array
     */
    public static function screenLiveTick()
    {
        return array(
            'server_time' => date('Y-m-d H:i:s'),
            'current_rpm' => self::currentRpm(),
            'kpi'         => array(
                'today_calls'  => self::countTodayCached(),
                'success_rate' => self::todaySuccessRateCached(),
                'fail_rate'    => self::todayFailRateCached(),
            ),
            'recent'      => self::recentCalls(8),
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
                $labels[] = self::weekdayLabel(strtotime($day));
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
                $labels[] = self::weekdayLabel(strtotime($day));
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
        });
    }

    /**
     * 控制台最近调用：与日志查询首页同一通道（listPaged 默认 20 + Redis 页缓存）
     * 下发 id / apiname / ip / httpcode / time
     *
     * @return array
     */
    private static function recentCallsCompact()
    {
        if (!class_exists('ApiLogManager') || !ApiLogManager::tableReady()) {
            return array();
        }
        try {
            $paged = ApiLogManager::listPaged(array(
                'page'      => 1,
                'pagesize'  => 20,
                'q'         => '',
                'ok'        => null,
                'apiid'     => 0,
                'before_id' => 0,
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
        return self::remember('recent_' . $limit, self::TTL_LIVE, function () use ($limit) {
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
                        'level'      => $ok ? ($code >= 200 && $code < 300 ? 'success' : 'info') : 'error',
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
     * 地理分布（基于 iploc 聚合；无归属地时回落空列表）
     *
     * @return array
     */
    private static function geoDistribution()
    {
        return self::remember('geo_dist', self::TTL_GEO, function () {
            $chinaCities = self::chinaCityCoords();
            $worldCities = self::worldCityCoords();
            $counts = self::iplocCityCounts();
            $china = array();
            $world = array();
            foreach ($chinaCities as $name => $xy) {
                $c = isset($counts[$name]) ? (int) $counts[$name] : 0;
                if ($c <= 0 && empty($counts)) {
                    // 无 iploc 数据时给示意零值点，避免地图空白
                    $c = 0;
                }
                $china[] = array(
                    'name'   => $name,
                    'x'      => $xy[0],
                    'y'      => $xy[1],
                    'count'  => $c,
                    'status' => $c > 0 ? '正常' : '暂无数据',
                    'level'  => 'normal',
                );
            }
            // 把有数据但不在预设表的城市挂到最近预设点（略）：仅展示预设城市
            usort($china, function ($a, $b) {
                return $b['count'] - $a['count'];
            });
            foreach ($worldCities as $name => $xy) {
                $c = isset($counts[$name]) ? (int) $counts[$name] : 0;
                $world[] = array(
                    'name'   => $name,
                    'x'      => $xy[0],
                    'y'      => $xy[1],
                    'count'  => $c,
                    'status' => $c > 0 ? '正常' : '暂无数据',
                    'level'  => 'normal',
                );
            }
            $flowsChina = self::buildFlows(
                array(
                    array('北京', '上海'),
                    array('上海', '广州'),
                    array('北京', '成都'),
                    array('广州', '深圳'),
                    array('武汉', '杭州'),
                ),
                $counts
            );
            $flowsWorld = self::buildFlows(
                array(
                    array('北京', '新加坡'),
                    array('纽约', '伦敦'),
                    array('伦敦', '新加坡'),
                    array('北京', '悉尼'),
                ),
                $counts
            );
            return array(
                'china' => array('cities' => $china, 'flows' => $flowsChina),
                'world' => array('cities' => $world, 'flows' => $flowsWorld),
            );
        });
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
     * @return array<string,int>
     */
    private static function iplocCityCounts()
    {
        if (!ApiLogManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $sql = 'SELECT `iploc`, COUNT(*) AS c
                FROM `' . Database::table('apilog') . '`
                WHERE `createtime` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND `iploc` <> \'\'
                GROUP BY `iploc`
                ORDER BY c DESC
                LIMIT 80';
            $out = array();
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $city = self::parseCityFromIploc((string) $r['iploc']);
                if ($city === '') {
                    continue;
                }
                if (!isset($out[$city])) {
                    $out[$city] = 0;
                }
                $out[$city] += (int) $r['c'];
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @param string $iploc
     * @return string
     */
    private static function parseCityFromIploc($iploc)
    {
        $iploc = trim((string) $iploc);
        if ($iploc === '') {
            return '';
        }
        $known = array_merge(array_keys(self::chinaCityCoords()), array_keys(self::worldCityCoords()));
        foreach ($known as $city) {
            if (mb_strpos($iploc, $city) !== false) {
                return $city;
            }
        }
        // 中国-北京 / 北京市
        if (preg_match('/([\x{4e00}-\x{9fa5}]{2,8}?)(?:市|省|自治区)?$/u', $iploc, $m)) {
            $c = $m[1];
            if (mb_strlen($c, 'UTF-8') >= 2) {
                return $c;
            }
        }
        return '';
    }

    /**
     * 视图坐标（viewBox 0 0 400 300）
     *
     * @return array<string,array{0:float,1:float}>
     */
    private static function chinaCityCoords()
    {
        return array(
            '北京' => array(268, 88),
            '上海' => array(318, 168),
            '广州' => array(278, 248),
            '深圳' => array(288, 258),
            '成都' => array(198, 178),
            '武汉' => array(268, 178),
            '西安' => array(228, 148),
            '重庆' => array(218, 198),
            '杭州' => array(308, 188),
        );
    }

    /**
     * @return array<string,array{0:float,1:float}>
     */
    private static function worldCityCoords()
    {
        return array(
            '北京'   => array(300, 110),
            '纽约'   => array(95, 105),
            '伦敦'   => array(185, 85),
            '新加坡' => array(295, 195),
            '圣保罗' => array(125, 220),
            '悉尼'   => array(345, 240),
        );
    }

    /**
     * @param int $days
     * @return array<string,array{guest:int,key:int,points:int}>
     */
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
        if (!class_exists('ApiFeedbackManager') || !ApiFeedbackManager::tableReady()) {
            return 0;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('feedback') . '` WHERE `status` = ?'
            );
            $stmt->execute(array(ApiFeedbackManager::STATUS_PENDING));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
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
