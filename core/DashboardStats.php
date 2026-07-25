<?php
/**
 * 文件：core/DashboardStats.php
 * 作用：管理员控制台 / 数据大屏统计聚合（分层 TTL 缓存，避免大表反复扫）
 */

class DashboardStats
{
    const TTL_LIVE = 8;
    const TTL_TODAY = 45;
    const TTL_HOUR = 90;
    const TTL_WEEK = 600;
    const TTL_GEO = 300;

    /** @var string|null */
    private static $epochCache = null;

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
        }
        return array(
            'server_time'   => date('Y-m-d H:i:s'),
            'weekday'       => self::weekdayLabel(),
            'kpi'           => self::kpiBlock(),
            'type_trend'    => self::typeTrend7d(),
            'rate_trend'    => self::rateTrend7d(),
            'top_apis'      => self::topApisToday(10),
            'sys_overview'  => self::sysOverview(),
            'recent'        => self::recentCalls(12),
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
            $today = self::countRange(date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'));
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
                'api_spark'       => self::sparkFromDaily(7),
                'user_total'      => $userCount,
                'user_delta'      => $userToday,
                'user_spark'      => self::sparkFromUsers(7),
                'today_calls'     => $today,
                'today_delta'     => self::pctDelta($today, $yesterday),
                'today_spark'     => self::sparkFromDaily(7),
                'success_rate'    => $rate,
                'success_delta'   => round($rate - $yRate, 2),
                'fail_rate'       => $failRate,
                'fail_delta'      => round($failRate - $yFailRate, 2),
                'success_count'   => $todayOk,
                'fail_count'      => $todayFail,
                'total_calls'     => $total,
                'total_delta'     => self::pctDelta($total, max(1, $weekAgoTotal)),
            );
        });
    }

    /**
     * 近 7 日：游客 / 密钥 / 积分
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
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime('-' . $i . ' day'));
                $labels[] = self::weekdayLabel(strtotime($day));
                $row = self::typeCountsForDay($day);
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
     * 近 7 日成功率 / 失败率
     *
     * @return array
     */
    private static function rateTrend7d()
    {
        return self::remember('rate_trend_7d', self::TTL_WEEK, function () {
            $labels = array();
            $success = array();
            $fail = array();
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime('-' . $i . ' day'));
                $labels[] = self::weekdayLabel(strtotime($day));
                $of = self::okFailDay($day);
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
                $sql = 'SELECT l.`id`, l.`apiname`, l.`path`, l.`method`, l.`ok`, l.`httpcode`,
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
                        'id'       => (int) $r['id'],
                        'time'     => date('H:i:s', strtotime((string) $r['createtime'])),
                        'apiname'  => (string) $r['apiname'],
                        'path'     => (string) $r['path'],
                        'method'   => (string) $r['method'],
                        'ok'       => $ok ? 1 : 0,
                        'status'   => $ok ? 'success' : 'error',
                        'httpcode' => $code,
                        'code_label' => $codeLabel,
                        'caller'   => $caller,
                        'initial'  => $initial !== '' ? $initial : '访',
                        'level'    => $ok ? ($code >= 200 && $code < 300 ? 'success' : 'info') : 'error',
                    );
                }
                return $out;
            } catch (Exception $e) {
                return array();
            }
        });
    }

    /**
     * 近 24 小时按小时
     *
     * @return array
     */
    private static function hourly24h()
    {
        return self::remember('hourly_24h', self::TTL_HOUR, function () {
            $map = array_fill(0, 24, 0);
            if (!ApiLogManager::tableReady()) {
                return array('labels' => self::hourLabels(), 'series' => array_values($map));
            }
            try {
                $pdo = Database::connect();
                self::applyTimeout($pdo);
                $sql = 'SELECT HOUR(`createtime`) AS h, COUNT(*) AS c
                    FROM `' . Database::table('apilog') . '`
                    WHERE `createtime` >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY HOUR(`createtime`)';
                foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $h = (int) $r['h'];
                    if ($h >= 0 && $h < 24) {
                        $map[$h] = (int) $r['c'];
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
            // 按「当前小时往前 24」重排为时间序
            $nowH = (int) date('G');
            $series = array();
            $labels = array();
            for ($i = 23; $i >= 0; $i--) {
                $h = ($nowH - $i + 24) % 24;
                $series[] = (int) $map[$h];
                $labels[] = sprintf('%02d:00', $h);
            }
            $labels[count($labels) - 1] = '现在';
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
     * @param string $day Y-m-d
     * @return array{guest:int,key:int,points:int}
     */
    private static function typeCountsForDay($day)
    {
        $empty = array('guest' => 0, 'key' => 0, 'points' => 0);
        if (!ApiLogManager::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            self::applyTimeout($pdo);
            $start = $day . ' 00:00:00';
            $end = $day . ' 23:59:59';
            $stmt = $pdo->prepare(
                'SELECT
                    SUM(CASE WHEN `charged` = 1 THEN 1 ELSE 0 END) AS points_c,
                    SUM(CASE WHEN `charged` = 0 AND `apikey` <> \'\' THEN 1 ELSE 0 END) AS key_c,
                    SUM(CASE WHEN `charged` = 0 AND (`apikey` = \'\' OR `apikey` IS NULL) THEN 1 ELSE 0 END) AS guest_c
                 FROM `' . Database::table('apilog') . '`
                 WHERE `createtime` >= ? AND `createtime` <= ?'
            );
            $stmt->execute(array($start, $end));
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return array(
                'guest'  => (int) (isset($r['guest_c']) ? $r['guest_c'] : 0),
                'key'    => (int) (isset($r['key_c']) ? $r['key_c'] : 0),
                'points' => (int) (isset($r['points_c']) ? $r['points_c'] : 0),
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @param string $start
     * @param string $end
     * @return int
     */
    private static function countRange($start, $end)
    {
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
        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' day'));
            $out[] = self::countRange($d . ' 00:00:00', $d . ' 23:59:59');
        }
        return $out;
    }

    /**
     * @param int $days
     * @return int[]
     */
    private static function sparkFromUsers($days)
    {
        $out = array();
        try {
            $pdo = Database::connect();
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime('-' . $i . ' day'));
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM `' . Database::table('user') . '`
                     WHERE `createtime` >= ? AND `createtime` <= ?'
                );
                $stmt->execute(array($d . ' 00:00:00', $d . ' 23:59:59'));
                $out[] = max(0, (int) $stmt->fetchColumn());
            }
        } catch (Exception $e) {
            $out = array_fill(0, $days, 0);
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
     * @return string[]
     */
    private static function hourLabels()
    {
        $out = array();
        for ($i = 0; $i < 24; $i++) {
            $out[] = sprintf('%02d:00', $i);
        }
        return $out;
    }

    /**
     * @param string   $suffix
     * @param int      $ttl
     * @param callable $factory
     * @return mixed
     */
    private static function remember($suffix, $ttl, $factory)
    {
        $key = 'cache:dashboard:' . self::epoch() . ':' . $suffix;
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
