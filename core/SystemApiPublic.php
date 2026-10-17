<?php
/**
 * 文件：core/SystemApiPublic.php
 * 作用：系统级「接口查询 / 调用排行 / 用户信息 / 全站概况」业务（脱敏输出；不含密码、OAuth、upkey 等机密）
 *
 * 归属：系统级外部 API（见《系统级外部API规范》）
 */

if (!defined('VS_ROOT')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

class SystemApiPublic
{
    /**
     * @return bool
     */
    public static function isQueryEnabled()
    {
        return class_exists('SystemApiKey') && SystemApiKey::isGateOn('apiquery');
    }

    /**
     * @return bool
     */
    public static function isRankEnabled()
    {
        return class_exists('SystemApiKey') && SystemApiKey::isGateOn('apirank');
    }

    /**
     * @return bool
     */
    public static function isUserInfoEnabled()
    {
        return class_exists('SystemApiKey') && SystemApiKey::isGateOn('userinfo');
    }

    /**
     * @return bool
     */
    public static function isSiteStatsEnabled()
    {
        return class_exists('SystemApiKey') && SystemApiKey::isGateOn('sitestats');
    }

    /**
     * 全站概况：累计调用、今日调用、累计积分消耗、注册用户数
     *
     * @return array{ok:bool,msg:string,stats?:array}
     */
    public static function siteStats()
    {
        $totalCalls = 0;
        $todayCalls = 0;
        $users = 0;
        $spent = 0.0;

        if (class_exists('FrontendStats')) {
            $totalCalls = (int) FrontendStats::totalCallCount();
            $todayCalls = (int) FrontendStats::todayCallCount();
            $users = (int) FrontendStats::userCount();
        } else {
            if (class_exists('ApiManager') && method_exists('ApiManager', 'totalCallCount')) {
                $totalCalls = max(0, (int) ApiManager::totalCallCount());
            }
            if (class_exists('ApiLogManager') && method_exists('ApiLogManager', 'countToday')) {
                $todayCalls = max(0, (int) ApiLogManager::countToday());
            }
            if (class_exists('UserManager') && method_exists('UserManager', 'count')) {
                $users = max(0, (int) UserManager::count());
            }
        }

        // 今日调用优先读日表（与后台 KPI 一致）
        if (class_exists('StatDayManager') && method_exists('StatDayManager', 'todayCalls')) {
            try {
                $todayCalls = max(0, (int) StatDayManager::todayCalls());
            } catch (Exception $e) {
                // 保持 FrontendStats / apilog 结果
            }
        }

        $spent = self::sitePointsSpentTotal();

        return array(
            'ok'    => true,
            'msg'   => 'ok',
            'stats' => array(
                'calls'         => max(0, $totalCalls),
                'today_calls'   => max(0, $todayCalls),
                'pointsspent'   => round(max(0.0, (float) $spent), 4),
                'users'         => max(0, $users),
            ),
        );
    }

    /**
     * 全站累计积分消耗（优先 SUM user.pointsspent）
     *
     * @return float
     */
    private static function sitePointsSpentTotal()
    {
        if (!class_exists('Database')) {
            return 0.0;
        }
        try {
            $pdo = Database::connect();
            $hasSpent = class_exists('PointsManager') && method_exists('PointsManager', 'hasSpentColumn')
                ? PointsManager::hasSpentColumn()
                : false;
            if ($hasSpent) {
                $v = $pdo->query(
                    'SELECT COALESCE(SUM(`pointsspent`), 0) FROM `' . Database::table('user') . '`'
                )->fetchColumn();
                return $v !== false ? (float) $v : 0.0;
            }
        } catch (Exception $e) {
            return 0.0;
        }
        return 0.0;
    }

    /**
     * 查询用户信息（资料 / 积分 / 调用 / 签到 / 近7日排行；可选某一日）
     * 不含密码、OAuth、头像路径等敏感字段。封禁用户仍可查（带 status）。
     *
     * @param array $input userid|username|email；可选 date=Y-m-d（近7日内）
     * @return array{ok:bool,msg:string,reason?:string,user?:array}
     */
    public static function userInfo(array $input)
    {
        $resolved = self::resolveUserForInfo($input);
        if (empty($resolved['ok'])) {
            return $resolved;
        }
        $userId = (int) $resolved['userid'];
        $row = self::loadUserInfoRow($userId);
        if ($row === null) {
            return array('ok' => false, 'msg' => '该用户未注册', 'reason' => 'not_registered');
        }

        $status = isset($row['status']) ? (int) $row['status'] : 0;
        $role = class_exists('UserRole')
            ? UserRole::normalize(isset($row['role']) ? $row['role'] : 'user')
            : (isset($row['role']) ? (string) $row['role'] : 'user');
        $roleLabel = class_exists('UserRole') ? UserRole::label($role) : $role;

        if (class_exists('CheckinManager')) {
            $checkinView = CheckinManager::present(isset($row['lastcheckin']) ? $row['lastcheckin'] : '');
        } else {
            $checkinView = array(
                'lastcheckin'   => '',
                'checked_today' => false,
                'checkin_days'  => array(),
                'streak_days'   => 0,
                'streak_full'   => false,
            );
        }

        $stats = array();
        if (class_exists('UserCallStats')) {
            $stats = UserCallStats::query($userId, array(
                UserCallStats::FIELD_CALLS,
                UserCallStats::FIELD_SPENT,
                UserCallStats::FIELD_POINTS,
                UserCallStats::FIELD_TODAY,
                UserCallStats::FIELD_WEEK,
                UserCallStats::FIELD_RANK7,
            ));
        }

        $user = array(
            'userid'        => $userId,
            'username'      => isset($row['username']) ? (string) $row['username'] : '',
            'email'         => isset($row['email']) ? (string) $row['email'] : '',
            'status'        => $status,
            'status_label'  => $status === 1 ? '正常' : '封禁',
            'role'          => $role,
            'role_label'    => $roleLabel,
            'createtime'    => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'lastlogin'     => isset($row['lastlogin']) && $row['lastlogin'] !== null
                ? (string) $row['lastlogin'] : '',
            'points'        => isset($stats[UserCallStats::FIELD_POINTS])
                ? round((float) $stats[UserCallStats::FIELD_POINTS], 4) : 0.0,
            'pointsspent'   => isset($stats[UserCallStats::FIELD_SPENT])
                ? round((float) $stats[UserCallStats::FIELD_SPENT], 4) : 0.0,
            'calls'         => isset($stats[UserCallStats::FIELD_CALLS])
                ? (int) $stats[UserCallStats::FIELD_CALLS] : 0,
            'today_calls'   => isset($stats[UserCallStats::FIELD_TODAY])
                ? (int) $stats[UserCallStats::FIELD_TODAY] : 0,
            'week_calls'    => isset($stats[UserCallStats::FIELD_WEEK])
                ? (int) $stats[UserCallStats::FIELD_WEEK] : 0,
            'checked_today' => !empty($checkinView['checked_today']),
            'lastcheckin'   => isset($checkinView['lastcheckin']) ? (string) $checkinView['lastcheckin'] : '',
            'checkin_days'  => isset($checkinView['checkin_days']) ? $checkinView['checkin_days'] : array(),
            'streak_days'   => isset($checkinView['streak_days']) ? (int) $checkinView['streak_days'] : 0,
            'streak_full'   => !empty($checkinView['streak_full']),
            'rank7'         => isset($stats[UserCallStats::FIELD_RANK7])
                && is_array($stats[UserCallStats::FIELD_RANK7])
                ? $stats[UserCallStats::FIELD_RANK7] : array(),
        );

        $date = '';
        if (isset($input['date'])) {
            $date = substr(trim((string) $input['date']), 0, 10);
        }
        if ($date !== '') {
            if (!class_exists('UserStat7Manager')) {
                return array('ok' => false, 'msg' => '日统计未就绪');
            }
            $day = UserStat7Manager::dayDetail($userId, $date);
            if (empty($day['ok'])) {
                return array(
                    'ok'  => false,
                    'msg' => isset($day['msg']) ? (string) $day['msg'] : '日期无效',
                );
            }
            $rankRows = isset($day['rank']) && is_array($day['rank']) ? $day['rank'] : array();
            $user['day'] = array(
                'date'  => isset($day['date']) ? (string) $day['date'] : $date,
                'calls' => isset($day['calls']) ? (int) $day['calls'] : 0,
                'cost'  => isset($day['cost']) ? round((float) $day['cost'], 4) : 0.0,
                'rank'  => class_exists('UserCallStats')
                    ? UserCallStats::publicRankList($rankRows) : array(),
            );
        }

        return array('ok' => true, 'msg' => 'ok', 'user' => $user);
    }

    /**
     * 解析用户：未注册失败；封禁仍返回 userid（由业务带 status）
     *
     * @param array $input
     * @return array{ok:bool,msg?:string,reason?:string,userid?:int}
     */
    private static function resolveUserForInfo(array $input)
    {
        $userId = 0;
        if (isset($input['userid'])) {
            $userId = (int) $input['userid'];
        } elseif (isset($input['user_id'])) {
            $userId = (int) $input['user_id'];
        } elseif (isset($input['uid'])) {
            $userId = (int) $input['uid'];
        }

        $account = '';
        if (isset($input['username'])) {
            $account = trim((string) $input['username']);
        }
        if ($account === '' && isset($input['email'])) {
            $account = trim((string) $input['email']);
        }
        if ($account === '' && isset($input['account'])) {
            $account = trim((string) $input['account']);
        }

        if ($userId <= 0 && $account === '') {
            return array('ok' => false, 'msg' => '请提供 userid 或 username 或 email');
        }
        if (!class_exists('UserManager')) {
            return array('ok' => false, 'msg' => '用户模块未就绪');
        }

        $row = null;
        if ($userId > 0) {
            $row = UserManager::findById($userId);
        } else {
            if (function_exists('mb_substr')) {
                $account = mb_substr($account, 0, 128, 'UTF-8');
            } else {
                $account = substr($account, 0, 128);
            }
            $row = UserManager::findByAccount($account);
        }
        if (!is_array($row) || empty($row['id'])) {
            return array('ok' => false, 'msg' => '该用户未注册', 'reason' => 'not_registered');
        }
        return array('ok' => true, 'userid' => (int) $row['id']);
    }

    /**
     * @param int $userId
     * @return array|null
     */
    private static function loadUserInfoRow($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !class_exists('Database')) {
            return null;
        }
        $sqlFull = 'SELECT `id`, `username`, `email`, `status`, `role`, `createtime`, `lastlogin`, `lastcheckin`
                 FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1';
        $sqlBasic = 'SELECT `id`, `username`, `email`, `status`, `role`, `createtime`
                 FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1';
        try {
            $pdo = Database::connect();
            try {
                $stmt = $pdo->prepare($sqlFull);
                $stmt->execute(array($userId));
            } catch (Exception $e) {
                $stmt = $pdo->prepare($sqlBasic);
                $stmt->execute(array($userId));
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 按 id / name / slug（代理短码）取一条前台可见接口详情
     *
     * @param array $input
     * @return array{ok:bool,msg:string,api?:array}
     */
    public static function queryOne(array $input)
    {
        if (!ApiManager::tableReady()) {
            return array('ok' => false, 'msg' => '接口表未就绪，请先完成系统升级');
        }

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = isset($input['name']) ? trim((string) $input['name']) : '';
        $slug = isset($input['slug']) ? trim((string) $input['slug']) : '';
        if ($slug === '' && isset($input['proxyslug'])) {
            $slug = trim((string) $input['proxyslug']);
        }

        $row = null;
        if ($id > 0) {
            $row = ApiManager::findById($id);
        } elseif ($slug !== '') {
            $row = self::findPublicRowBySlug($slug);
        } elseif ($name !== '') {
            $row = self::findPublicRowByName($name);
        } else {
            return array('ok' => false, 'msg' => '请提供 id 或 name 或 slug');
        }

        if (!is_array($row) || !self::isPublicVisible($row)) {
            return array('ok' => false, 'msg' => '接口不存在或不可用');
        }

        $api = self::buildPublicDetail($row);
        if ($api === null) {
            return array('ok' => false, 'msg' => '接口不存在或不可用');
        }
        return array('ok' => true, 'msg' => 'ok', 'api' => $api);
    }

    /**
     * 随机一条前台可见接口详情
     *
     * @return array{ok:bool,msg:string,api?:array}
     */
    public static function randomOne()
    {
        if (!ApiManager::tableReady()) {
            return array('ok' => false, 'msg' => '接口表未就绪，请先完成系统升级');
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.* FROM `' . ApiManager::table() . '` AS a
                    WHERE a.`status` IN (?, ?)';
            $params = array(
                (int) ApiManager::STATUS_NORMAL,
                (int) ApiManager::STATUS_MAINTENANCE,
            );
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND a.`audit` = ?';
                $params[] = (int) ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' ORDER BY RAND() LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !self::isPublicVisible($row)) {
                return array('ok' => false, 'msg' => '暂无可用接口');
            }
            $api = self::buildPublicDetail($row);
            if ($api === null) {
                return array('ok' => false, 'msg' => '暂无可用接口');
            }
            return array('ok' => true, 'msg' => 'ok', 'api' => $api);
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '随机查询失败，请稍后重试');
        }
    }

    /**
     * 关键词搜索前台可见接口（摘要列表）
     *
     * @param string $q
     * @param int    $limit
     * @return array{ok:bool,msg:string,total?:int,list?:array}
     */
    public static function search($q, $limit = 20)
    {
        $q = trim((string) $q);
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 64, 'UTF-8');
        } else {
            $q = substr($q, 0, 64);
        }
        $limit = max(1, min(50, (int) $limit));
        if ($q === '') {
            return array('ok' => false, 'msg' => '请提供搜索关键词 q');
        }
        if (!ApiManager::tableReady()) {
            return array('ok' => false, 'msg' => '接口表未就绪，请先完成系统升级');
        }

        try {
            $pdo = Database::connect();
            $like = function_exists('vs_sql_like_contains')
                ? vs_sql_like_contains($q)
                : ('%' . addcslashes($q, '\\%_') . '%');
            $sql = 'SELECT a.* FROM `' . ApiManager::table() . '` AS a
                    WHERE a.`status` IN (?, ?)
                      AND (a.`name` LIKE ? ESCAPE \'\\\\\'
                           OR a.`description` LIKE ? ESCAPE \'\\\\\'
                           OR a.`category` LIKE ? ESCAPE \'\\\\\')';
            $params = array(
                (int) ApiManager::STATUS_NORMAL,
                (int) ApiManager::STATUS_MAINTENANCE,
                $like,
                $like,
                $like,
            );
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND a.`audit` = ?';
                $params[] = (int) ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' ORDER BY a.`calls` DESC, a.`id` DESC LIMIT ' . (int) $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $sum = self::buildPublicSummary($row);
                if ($sum !== null) {
                    $list[] = $sum;
                }
            }
            return array(
                'ok'    => true,
                'msg'   => 'ok',
                'total' => count($list),
                'list'  => $list,
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '搜索失败，请稍后重试');
        }
    }

    /**
     * 调用排行：today / 7d / 30d（聚合 statday.topjson）；total（接口表累计 calls）
     *
     * @param string $period
     * @param int    $limit
     * @return array{ok:bool,msg:string,period?:string,days?:int,list?:array}
     */
    public static function rank($period, $limit = 20)
    {
        $period = strtolower(trim((string) $period));
        $limit = max(1, min(50, (int) $limit));

        // 总计：读接口表累计 calls（全站历史），不依赖日表 topjson
        $totalAliases = array('total' => true, 'all' => true, 'alltime' => true, 'sum' => true);
        if (isset($totalAliases[$period])) {
            if (!ApiManager::tableReady()) {
                return array('ok' => false, 'msg' => '接口表未就绪，请先完成系统升级');
            }
            $list = self::rankByTotalCalls($limit);
            return array(
                'ok'     => true,
                'msg'    => 'ok',
                'period' => 'total',
                'days'   => 0,
                'limit'  => $limit,
                'list'   => $list,
            );
        }

        $map = array(
            'today' => 1,
            '1d'    => 1,
            'day'   => 1,
            '7d'    => 7,
            '7'     => 7,
            'week'  => 7,
            '30d'   => 30,
            '30'    => 30,
            'month' => 30,
        );
        if ($period === '' || !isset($map[$period])) {
            return array('ok' => false, 'msg' => 'period 须为 today / 7d / 30d / total');
        }
        $days = (int) $map[$period];

        if (!class_exists('StatDayManager') || !StatDayManager::tableReady()) {
            return array('ok' => false, 'msg' => '日统计表未就绪，请先完成系统升级');
        }

        $list = self::mergeTopFromLastDays($days, $limit);
        $periodOut = ($days === 1) ? 'today' : ($days . 'd');
        return array(
            'ok'     => true,
            'msg'    => 'ok',
            'period' => $periodOut,
            'days'   => $days,
            'limit'  => $limit,
            'list'   => $list,
        );
    }

    /**
     * 总计排行：前台可见接口按累计 calls 降序
     *
     * @param int $limit
     * @return array<int,array{rank:int,apiid:int,name:string,calls:int,detail_url:string,call_url:string,category:string}>
     */
    private static function rankByTotalCalls($limit)
    {
        $limit = max(1, min(50, (int) $limit));
        $list = array();
        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.`id`, a.`name`, a.`category`, a.`endpoint`, a.`apitype`, a.`proxyslug`, a.`status`, a.`calls`
                    FROM `' . ApiManager::table() . '` AS a
                    WHERE a.`status` IN (?, ?)';
            $params = array(
                (int) ApiManager::STATUS_NORMAL,
                (int) ApiManager::STATUS_MAINTENANCE,
            );
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND a.`audit` = ?';
                $params[] = (int) ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' ORDER BY a.`calls` DESC, a.`id` DESC LIMIT ' . (int) $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rank = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row) || !self::isPublicVisible($row)) {
                    continue;
                }
                $rank++;
                $aid = (int) $row['id'];
                $list[] = array(
                    'rank'       => $rank,
                    'apiid'      => $aid,
                    'name'       => (string) $row['name'],
                    'calls'      => isset($row['calls']) ? (int) $row['calls'] : 0,
                    'detail_url' => function_exists('vs_api_detail_url') ? vs_api_detail_url($aid) : '',
                    'call_url'   => ApiManager::resolveCallUrl($row),
                    'category'   => isset($row['category']) ? (string) $row['category'] : '',
                );
            }
        } catch (Exception $e) {
            return array();
        }
        return $list;
    }

    /**
     * @param array $row
     * @return bool
     */
    private static function isPublicVisible(array $row)
    {
        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : ApiManager::STATUS_DISABLED);
        if ($status !== ApiManager::STATUS_NORMAL && $status !== ApiManager::STATUS_MAINTENANCE) {
            return false;
        }
        if (ApiManager::hasAuditColumn()) {
            $audit = ApiManager::normalizeAuditStatus(
                isset($row['audit']) ? $row['audit'] : ApiManager::AUDIT_APPROVED
            );
            if ($audit !== ApiManager::AUDIT_APPROVED) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $name
     * @return array|null
     */
    private static function findPublicRowByName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.* FROM `' . ApiManager::table() . '` AS a
                    WHERE a.`name` = ? AND a.`status` IN (?, ?)';
            $params = array(
                $name,
                (int) ApiManager::STATUS_NORMAL,
                (int) ApiManager::STATUS_MAINTENANCE,
            );
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND a.`audit` = ?';
                $params[] = (int) ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' ORDER BY a.`id` DESC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $slug
     * @return array|null
     */
    private static function findPublicRowBySlug($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '' || !ApiManager::hasProxyColumns()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.* FROM `' . ApiManager::table() . '` AS a
                    WHERE a.`proxyslug` = ? AND a.`status` IN (?, ?)';
            $params = array(
                $slug,
                (int) ApiManager::STATUS_NORMAL,
                (int) ApiManager::STATUS_MAINTENANCE,
            );
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND a.`audit` = ?';
                $params[] = (int) ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 详情（含文档 / 参数 / OpenAPI）；禁止带上游机密字段
     *
     * @param array $row
     * @return array|null
     */
    private static function buildPublicDetail(array $row)
    {
        if (!class_exists('FrontendApi')) {
            return null;
        }
        $item = FrontendApi::formatForTheme($row, false, true);
        if (!is_array($item)) {
            return null;
        }
        $item = FrontendApi::applySitePromoToThemeItem(FrontendApi::bindRequestHost($item));
        return self::shapePublicApi($item, true);
    }

    /**
     * @param array $row
     * @return array|null
     */
    private static function buildPublicSummary(array $row)
    {
        if (!class_exists('FrontendApi')) {
            return null;
        }
        $item = FrontendApi::formatForTheme($row, false, false);
        if (!is_array($item)) {
            return null;
        }
        $item = FrontendApi::applySitePromoToThemeItem(FrontendApi::bindRequestHost($item));
        return self::shapePublicApi($item, false);
    }

    /**
     * @param array $item formatForTheme 结果
     * @param bool  $detail
     * @return array
     */
    private static function shapePublicApi(array $item, $detail)
    {
        $tags = array();
        foreach (array('needkey_label', 'charge_label', 'billing_label', 'qpm_label', 'keyways_label', 'method_label') as $k) {
            if (!empty($item[$k]) && is_string($item[$k])) {
                $tags[] = (string) $item[$k];
            }
        }
        if (!empty($item['category_name'])) {
            array_unshift($tags, (string) $item['category_name']);
        } elseif (!empty($item['category'])) {
            array_unshift($tags, (string) $item['category']);
        }
        if (!empty($item['promo_label']) && is_string($item['promo_label'])) {
            $tags[] = (string) $item['promo_label'];
        }
        if (!empty($item['maintenance'])) {
            $tags[] = '维护中';
        }
        if (!empty($item['disabled'])) {
            $tags[] = '已禁用';
        }
        $tags = array_values(array_unique(array_filter($tags)));

        $out = array(
            'id'            => isset($item['id']) ? (int) $item['id'] : 0,
            'name'          => isset($item['name']) ? (string) $item['name'] : '',
            'description'   => isset($item['desc']) ? (string) $item['desc'] : '',
            'category'      => isset($item['category']) ? (string) $item['category'] : '',
            'category_name' => isset($item['category_name']) ? (string) $item['category_name'] : '',
            'methods'       => isset($item['methods']) && is_array($item['methods']) ? $item['methods'] : array(),
            'method_label'  => isset($item['method_label']) ? (string) $item['method_label'] : '',
            'call_url'      => isset($item['endpoint']) ? (string) $item['endpoint'] : '',
            'detail_url'    => isset($item['detail_url']) ? (string) $item['detail_url'] : '',
            'calls'         => isset($item['calls']) ? (int) $item['calls'] : 0,
            'needkey'       => isset($item['needkey']) ? (int) $item['needkey'] : 0,
            'needkey_label' => isset($item['needkey_label']) ? (string) $item['needkey_label'] : '',
            'keyways'       => isset($item['keyways']) ? $item['keyways'] : array(),
            'keyways_label' => isset($item['keyways_label']) ? (string) $item['keyways_label'] : '',
            'qpm'           => isset($item['qpm']) ? (int) $item['qpm'] : 0,
            'qpm_label'     => isset($item['qpm_label']) ? (string) $item['qpm_label'] : '',
            'charge'        => isset($item['charge']) ? (int) $item['charge'] : 0,
            'charge_label'  => isset($item['charge_label']) ? (string) $item['charge_label'] : '',
            'base_points'   => isset($item['base_points'])
                ? (float) $item['base_points']
                : (isset($item['points']) ? (float) $item['points'] : 0),
            'points'        => isset($item['points']) ? (float) $item['points'] : 0,
            'billing_label' => isset($item['billing_label']) ? (string) $item['billing_label'] : '',
            'promo_mode'    => isset($item['promo_mode']) ? (string) $item['promo_mode'] : 'off',
            'promo_label'   => isset($item['promo_label']) ? (string) $item['promo_label'] : '',
            'apitype'       => isset($item['apitype']) ? (int) $item['apitype'] : 0,
            'maintenance'   => !empty($item['maintenance']) ? 1 : 0,
            'disabled'      => !empty($item['disabled']) ? 1 : 0,
            'tags'          => $tags,
            'icon'          => isset($item['icon']) ? (string) $item['icon'] : '',
            'createtime'    => isset($item['createtime']) ? (string) $item['createtime'] : '',
        );

        if ($detail) {
            $out['params'] = isset($item['params']) ? (string) $item['params'] : '';
            $out['params_list'] = isset($item['params_list']) && is_array($item['params_list'])
                ? $item['params_list']
                : array();
            $out['response'] = isset($item['response']) ? (string) $item['response'] : '';
            $out['doc'] = isset($item['doc']) ? (string) $item['doc'] : '';
            $out['aidoc'] = isset($item['aidoc']) ? (string) $item['aidoc'] : '';
            $out['openapi_json'] = isset($item['openapi_json']) ? (string) $item['openapi_json'] : '';
            if ($out['openapi_json'] === '' && class_exists('OpenApiBuilder') && empty($item['disabled'])) {
                $out['openapi_json'] = OpenApiBuilder::jsonForApiRow(array(
                    'name'        => $out['name'],
                    'description' => $out['description'],
                    'endpoint'    => $out['call_url'],
                    'method'      => implode(',', $out['methods']),
                    'params'      => $out['params'],
                    'keyways'     => is_array($out['keyways']) ? implode(',', $out['keyways']) : (string) $out['keyways'],
                ), true);
            }
        }

        return $out;
    }

    /**
     * @param int $days
     * @param int $limit
     * @return array<int,array{rank:int,apiid:int,name:string,calls:int,detail_url:string,call_url:string}>
     */
    private static function mergeTopFromLastDays($days, $limit)
    {
        $days = max(1, min(30, (int) $days));
        $limit = max(1, min(50, (int) $limit));
        $map = array();
        $dayRows = StatDayManager::mapLastDays($days);
        foreach ($dayRows as $row) {
            if (!is_array($row) || empty($row['topjson'])) {
                continue;
            }
            $raw = json_decode((string) $row['topjson'], true);
            if (!is_array($raw)) {
                continue;
            }
            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $aid = (int) (isset($item['apiid']) ? $item['apiid'] : 0);
                $c = (int) (isset($item['calls']) ? $item['calls'] : 0);
                if ($aid <= 0 || $c <= 0) {
                    continue;
                }
                if (!isset($map[$aid])) {
                    $map[$aid] = 0;
                }
                $map[$aid] += $c;
            }
        }

        arsort($map, SORT_NUMERIC);
        $ids = array();
        $n = 0;
        foreach ($map as $aid => $calls) {
            $ids[] = (int) $aid;
            $n++;
            if ($n >= $limit) {
                break;
            }
        }

        $meta = self::publicMetaByIds($ids);
        $list = array();
        $rank = 0;
        foreach ($ids as $aid) {
            $rank++;
            $m = isset($meta[$aid]) ? $meta[$aid] : array();
            $list[] = array(
                'rank'       => $rank,
                'apiid'      => $aid,
                'name'       => isset($m['name']) ? (string) $m['name'] : ('接口#' . $aid),
                'calls'      => (int) $map[$aid],
                'detail_url' => isset($m['detail_url']) ? (string) $m['detail_url'] : (function_exists('vs_api_detail_url') ? vs_api_detail_url($aid) : ''),
                'call_url'   => isset($m['call_url']) ? (string) $m['call_url'] : '',
                'category'   => isset($m['category']) ? (string) $m['category'] : '',
            );
        }
        return $list;
    }

    /**
     * @param int[] $ids
     * @return array<int,array>
     */
    private static function publicMetaByIds(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = array();
        if ($ids === array() || !ApiManager::tableReady()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                'SELECT `id`, `name`, `category`, `endpoint`, `apitype`, `proxyslug`, `status`
                 FROM `' . ApiManager::table() . '` WHERE `id` IN (' . $ph . ')'
            );
            $stmt->execute($ids);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $aid = (int) $row['id'];
                $out[$aid] = array(
                    'name'       => (string) $row['name'],
                    'category'   => isset($row['category']) ? (string) $row['category'] : '',
                    'detail_url' => function_exists('vs_api_detail_url') ? vs_api_detail_url($aid) : '',
                    'call_url'   => ApiManager::resolveCallUrl($row),
                );
            }
        } catch (Exception $e) {
            return $out;
        }
        return $out;
    }
}
