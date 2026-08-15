<?php
/**
 * 文件：core/UserStat7Manager.php
 * 作用：用户近 7 日调用聚合（user.stat7 JSON，按日分桶）
 *
 * 写入：ApiStats 记账成功后 recordHit（失败静默，不拖垮接口）
 * 读取：仅经 FrontendUser / 本类；主题禁止直查库
 * 日桶字段：calls/ok/fail/cost/keycalls/pointscalls/apis
 * 排行输出：top_today / top_7d（top 兼容=近7日）
 * 禁止：从 apilog 全表回填（见 E215 / 方案讨论第 8～9 轮）
 */

class UserStat7Manager
{
    const KEEP_DAYS = 7;

    /** @var bool|null */
    private static $hasCol = null;

    /**
     * @return bool
     */
    public static function hasColumn()
    {
        if (self::$hasCol !== null) {
            return self::$hasCol;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . Database::table('user') . '` LIKE ' . $pdo->quote('stat7')
            );
            self::$hasCol = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            self::$hasCol = false;
        }
        return self::$hasCol;
    }

    /**
     * 迁移后刷新列探测
     *
     * @return void
     */
    public static function resetColumnCache()
    {
        self::$hasCol = null;
    }

    /**
     * 单次调用写入用户近 7 日窗（失败静默）
     *
     * @param int   $userId
     * @param int   $apiId
     * @param bool  $ok
     * @param float $cost 本次扣费积分数（未扣为 0）
     * @return void
     */
    public static function recordHit($userId, $apiId, $ok, $cost = 0.0)
    {
        $userId = (int) $userId;
        $apiId = (int) $apiId;
        if ($userId <= 0 || $apiId <= 0 || !self::hasColumn()) {
            return;
        }
        $cost = round(max(0, (float) $cost), 4);
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $started = false;
            try {
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                    $started = true;
                }
            } catch (Exception $eTx) {
                $started = false;
            }
            $stmt = $pdo->prepare(
                'SELECT `stat7` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
                . ($started ? ' FOR UPDATE' : '')
            );
            $stmt->execute(array($userId));
            $raw = $stmt->fetchColumn();
            if ($raw === false) {
                if ($started) {
                    $pdo->rollBack();
                }
                return;
            }
            $map = self::decode((string) $raw);
            $day = date('Y-m-d');
            if (!isset($map[$day]) || !is_array($map[$day])) {
                $map[$day] = array(
                    'calls'        => 0,
                    'ok'           => 0,
                    'fail'         => 0,
                    'cost'         => 0.0,
                    'keycalls'     => 0,
                    'pointscalls'  => 0,
                    'apis'         => array(),
                );
            }
            $bucket = &$map[$day];
            if (!isset($bucket['keycalls'])) {
                $bucket['keycalls'] = 0;
            }
            if (!isset($bucket['pointscalls'])) {
                $bucket['pointscalls'] = 0;
            }
            $bucket['calls'] = (int) $bucket['calls'] + 1;
            if ($ok) {
                $bucket['ok'] = (int) $bucket['ok'] + 1;
            } else {
                $bucket['fail'] = (int) $bucket['fail'] + 1;
            }
            // 与管理端 type 趋势口径一致：扣费=积分调用，否则=普通密钥调用
            if ($cost > 0) {
                $bucket['pointscalls'] = (int) $bucket['pointscalls'] + 1;
            } else {
                $bucket['keycalls'] = (int) $bucket['keycalls'] + 1;
            }
            $bucket['cost'] = round((float) $bucket['cost'] + $cost, 4);
            if (!isset($bucket['apis']) || !is_array($bucket['apis'])) {
                $bucket['apis'] = array();
            }
            $aid = (string) $apiId;
            $bucket['apis'][$aid] = (int) (isset($bucket['apis'][$aid]) ? $bucket['apis'][$aid] : 0) + 1;
            unset($bucket);
            $map = self::pruneToWindow($map);
            $json = json_encode($map, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                if ($started) {
                    $pdo->rollBack();
                }
                return;
            }
            $up = $pdo->prepare('UPDATE `' . $table . '` SET `stat7` = ? WHERE `id` = ? LIMIT 1');
            $up->execute(array($json, $userId));
            if ($started) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e2) {
                // ignore
            }
            // 非关键：静默，不影响接口响应
        }
    }

    /**
     * 读取并规范化近 7 日结构（供控制台；未登录勿调用）
     *
     * @param int $userId
     * @return array{days:array,today_calls:int,today_cost:float,avg_calls:float,top:array,top_today:array,top_7d:array}
     */
    public static function dashboardSlice($userId)
    {
        $empty = array(
            'days'        => array(),
            'today_calls' => 0,
            'today_cost'  => 0.0,
            'avg_calls'   => 0.0,
            'top'         => array(),
            'top_today'   => array(),
            'top_7d'      => array(),
        );
        $userId = (int) $userId;
        if ($userId <= 0 || !self::hasColumn()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `stat7` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $raw = $stmt->fetchColumn();
            if ($raw === false) {
                return $empty;
            }
            $map = self::pruneToWindow(self::decode((string) $raw));
            return self::buildSlice($map);
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @param array $map
     * @return array
     */
    private static function buildSlice(array $map)
    {
        $labels = array();
        $callsSeries = array();
        $keySeries = array();
        $pointsSeries = array();
        $costSeries = array();
        $successSeries = array();
        $failSeries = array();
        $totalCalls = 0;
        $today = date('Y-m-d');
        $todayCalls = 0;
        $todayCost = 0.0;
        $apiMerge7 = array();
        $apiToday = array();
        for ($i = self::KEEP_DAYS - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' day'));
            $row = isset($map[$day]) && is_array($map[$day]) ? $map[$day] : array();
            $c = isset($row['calls']) ? (int) $row['calls'] : 0;
            $ok = isset($row['ok']) ? (int) $row['ok'] : 0;
            $fail = isset($row['fail']) ? (int) $row['fail'] : 0;
            if ($fail < 0) {
                $fail = 0;
            }
            // 旧桶无 keycalls/pointscalls：全部归入密钥，避免臆造积分线
            $hasSplit = array_key_exists('keycalls', $row) || array_key_exists('pointscalls', $row);
            if ($hasSplit) {
                $keyC = isset($row['keycalls']) ? (int) $row['keycalls'] : 0;
                $ptsC = isset($row['pointscalls']) ? (int) $row['pointscalls'] : 0;
            } else {
                $keyC = $c;
                $ptsC = 0;
            }
            $cost = isset($row['cost']) ? (float) $row['cost'] : 0.0;
            $labels[] = $day;
            $callsSeries[] = $c;
            $keySeries[] = max(0, $keyC);
            $pointsSeries[] = max(0, $ptsC);
            $costSeries[] = round($cost, 4);
            // 与管理端 rateTrend7d 一致：无调用时成功/失败率均为 0（禁止 100-成功率 得出 100% 失败）
            $t = $ok + $fail;
            if ($t > 0) {
                $successSeries[] = round(($ok * 100) / (float) $t, 2);
                $failSeries[] = round(($fail * 100) / (float) $t, 2);
            } else {
                $successSeries[] = 0.0;
                $failSeries[] = 0.0;
            }
            $totalCalls += $c;
            if ($day === $today) {
                $todayCalls = $c;
                $todayCost = round($cost, 4);
                if (!empty($row['apis']) && is_array($row['apis'])) {
                    foreach ($row['apis'] as $aid => $n) {
                        $apiToday[(string) $aid] = (int) $n;
                    }
                }
            }
            if (!empty($row['apis']) && is_array($row['apis'])) {
                foreach ($row['apis'] as $aid => $n) {
                    $k = (string) $aid;
                    $apiMerge7[$k] = (int) (isset($apiMerge7[$k]) ? $apiMerge7[$k] : 0) + (int) $n;
                }
            }
        }
        $topToday = self::rankApis($apiToday, 10);
        $top7d = self::rankApis($apiMerge7, 10);
        return array(
            'days'         => array(
                'labels'       => $labels,
                'calls'        => $callsSeries,
                'key_calls'    => $keySeries,
                'points_calls' => $pointsSeries,
                'cost'         => $costSeries,
                'success_rate' => $successSeries,
                'fail_rate'    => $failSeries,
            ),
            'today_calls'  => $todayCalls,
            'today_cost'   => $todayCost,
            'avg_calls'    => round($totalCalls / (float) self::KEEP_DAYS, 2),
            // top 保留为近 7 日（兼容旧读法）；排行板块默认用 top_today
            'top'          => $top7d,
            'top_today'    => $topToday,
            'top_7d'       => $top7d,
        );
    }

    /**
     * apiid => calls 映射 → 排行列表
     *
     * @param array $map
     * @param int   $limit
     * @return array
     */
    private static function rankApis(array $map, $limit = 10)
    {
        if ($map === array()) {
            return array();
        }
        arsort($map, SORT_NUMERIC);
        $top = array();
        $rank = 0;
        $limit = max(1, (int) $limit);
        foreach ($map as $aid => $n) {
            $rank++;
            if ($rank > $limit) {
                break;
            }
            $top[] = array(
                'apiid' => (int) $aid,
                'calls' => (int) $n,
            );
        }
        return $top;
    }

    /**
     * @param string $raw
     * @return array
     */
    private static function decode($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === 'null') {
            return array();
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    /**
     * 只保留含今天共 KEEP_DAYS 个日历日
     *
     * @param array $map
     * @return array
     */
    private static function pruneToWindow(array $map)
    {
        $keep = array();
        for ($i = self::KEEP_DAYS - 1; $i >= 0; $i--) {
            $keep[date('Y-m-d', strtotime('-' . $i . ' day'))] = true;
        }
        $out = array();
        foreach ($map as $day => $row) {
            $day = (string) $day;
            if (!isset($keep[$day]) || !is_array($row)) {
                continue;
            }
            $out[$day] = $row;
        }
        return $out;
    }
}
