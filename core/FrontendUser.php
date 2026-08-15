<?php
/**
 * 文件：core/FrontendUser.php
 * 作用：前台/用户中心统一用户信息调度（主题与布局通过本类获取用户资料，禁止直读数据库）
 */

class FrontendUser
{
    /**
     * 当前登录用户的标准化资料
     *
     * @return array|null
     */
    public static function current()
    {
        if (!UserAuth::check()) {
            return null;
        }

        $user = UserAuth::user();
        if (!$user) {
            return null;
        }

        return self::format($user);
    }

    /**
     * 将用户表行格式化为前台可用结构
     *
     * @param array $user
     * @return array
     */
    public static function format(array $user)
    {
        $role = UserRole::normalize(isset($user['role']) ? $user['role'] : UserRole::ROLE_USER);

        return array(
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'avatar' => UserAvatar::resolve($user),
            'bio' => isset($user['bio']) ? trim((string) $user['bio']) : '',
            'blog' => isset($user['blog']) ? trim((string) $user['blog']) : '',
            'wallpaper' => isset($user['wallpaper']) ? trim((string) $user['wallpaper']) : '',
            'role' => $role,
            'role_label' => UserRole::label($role),
            'can_publish_api' => UserRole::canPublishApi($role),
            'points' => class_exists('PointsManager') && PointsManager::hasPointsColumn()
                ? PayConfig::fmtPoints(isset($user['points']) ? $user['points'] : PointsManager::balance((int) $user['id']))
                : '0',
            'createtime' => isset($user['createtime']) ? (string) $user['createtime'] : '',
            'lastlogin' => isset($user['lastlogin']) ? (string) $user['lastlogin'] : '',
            'profile_url' => UserRole::canPublishApi($role) ? vs_profile_url((int) $user['id']) : '',
        );
    }

    /**
     * 用户中心签到横幅状态（主题只读本方法）
     *
     * @return array{enabled:bool,checked_today:bool,min:int,max:int,show_banner:bool}
     */
    public static function checkinBanner()
    {
        $empty = array(
            'enabled'       => false,
            'checked_today' => false,
            'min'           => 0,
            'max'           => 0,
            'show_banner'   => false,
        );
        if (!class_exists('CheckinManager')) {
            return $empty;
        }
        $user = self::current();
        if (!$user) {
            return $empty;
        }
        return CheckinManager::bannerState((int) $user['id']);
    }

    /**
     * 当前用户签到
     *
     * @return array{ok:bool,msg:string,amount?:float,balance?:float,points?:string}
     */
    public static function doCheckin()
    {
        if (!UserAuth::check()) {
            return array('ok' => false, 'msg' => '请先登录');
        }
        if (!class_exists('PointsManager')) {
            return array('ok' => false, 'msg' => '积分系统未就绪');
        }
        $result = PointsManager::checkin((int) UserAuth::id());
        if (!empty($result['ok']) && isset($result['balance'])) {
            $result['points'] = PayConfig::fmtPoints($result['balance']);
        }
        return $result;
    }

    /**
     * 用户中心控制台汇总数据（主题只读本方法，勿在视图直查库）
     *
     * @return array
     */
    public static function dashboardStats()
    {
        $empty = array(
            'points'           => '0',
            'points_spent'     => '0',
            'email'            => '',
            'createtime'       => '',
            'lastlogin'        => '',
            'role_label'       => '',
            'can_publish_api'  => false,
            'api_total'        => 0,
            'api_approved'     => 0,
            'api_pending'      => 0,
            'api_rejected'     => 0,
            'api_calls'        => 0,
            'key_total'        => 0,
            'key_calls'        => 0,
            'stat7'            => array(),
            'recent'           => array(),
            'detail_enabled'   => false,
            'checkin_enabled'  => false,
            'checked_today'    => false,
        );
        $user = self::current();
        if (!$user) {
            return $empty;
        }
        $uid = (int) $user['id'];
        $out = $empty;
        $out['points'] = isset($user['points']) ? (string) $user['points'] : '0';
        $out['email'] = isset($user['email']) ? (string) $user['email'] : '';
        $out['createtime'] = isset($user['createtime']) ? (string) $user['createtime'] : '';
        $out['lastlogin'] = isset($user['lastlogin']) ? (string) $user['lastlogin'] : '';
        $out['role_label'] = isset($user['role_label']) ? (string) $user['role_label'] : '';
        $out['can_publish_api'] = !empty($user['can_publish_api']);
        // 累计消耗 / 密钥调用：读用户表缓存列（v13.22.2+），避免每次扫 orders / 汇总令牌
        if (class_exists('PointsManager') && method_exists('PointsManager', 'spentTotal')) {
            $spent = PointsManager::spentTotal($uid);
            $out['points_spent'] = class_exists('PayConfig')
                ? PayConfig::fmtPoints($spent)
                : (string) $spent;
        } elseif (class_exists('OrderManager') && method_exists('OrderManager', 'sumUserSpent')) {
            $spent = OrderManager::sumUserSpent($uid);
            $out['points_spent'] = class_exists('PayConfig')
                ? PayConfig::fmtPoints($spent)
                : (string) $spent;
        }

        // 发布接口统计：所有登录用户均可有归属接口；「发布被调用」KPI 仅开发者展示
        if (class_exists('ApiManager') && ApiManager::tableReady()) {
            $apis = ApiManager::listByUser($uid);
            $out['api_total'] = count($apis);
            foreach ($apis as $row) {
                $out['api_calls'] += isset($row['calls']) ? (int) $row['calls'] : 0;
                if (!ApiManager::hasAuditColumn()) {
                    $out['api_approved'] += 1;
                    continue;
                }
                $audit = ApiManager::normalizeAuditStatus(isset($row['audit']) ? $row['audit'] : ApiManager::AUDIT_APPROVED);
                if ($audit === ApiManager::AUDIT_APPROVED) {
                    $out['api_approved'] += 1;
                } elseif ($audit === ApiManager::AUDIT_PENDING) {
                    $out['api_pending'] += 1;
                } else {
                    $out['api_rejected'] += 1;
                }
            }
        }

        if (class_exists('ApiKeyManager') && ApiKeyManager::tableReady()) {
            $keys = ApiKeyManager::listByUser($uid);
            $out['key_total'] = count($keys);
            if (method_exists('ApiKeyManager', 'userKeyCallsTotal')) {
                $out['key_calls'] = ApiKeyManager::userKeyCallsTotal($uid);
            } else {
                foreach ($keys as $k) {
                    $out['key_calls'] += isset($k['calls']) ? (int) $k['calls'] : 0;
                }
            }
        }

        // 近 7 日聚合：仅当前登录用户；主题只读本字段，禁止直查库
        if (class_exists('UserStat7Manager')) {
            $out['stat7'] = UserStat7Manager::dashboardSlice($uid);
            $out['stat7'] = self::enrichStat7Top($out['stat7']);
            if (class_exists('PayConfig') && isset($out['stat7']['today_cost'])) {
                $out['stat7']['today_cost_fmt'] = PayConfig::fmtPoints($out['stat7']['today_cost']);
            } else {
                $out['stat7']['today_cost_fmt'] = isset($out['stat7']['today_cost'])
                    ? (string) $out['stat7']['today_cost']
                    : '0';
            }
        }

        if (class_exists('ApiLogManager')) {
            $out['detail_enabled'] = ApiLogManager::detailEnabled();
            if ($out['detail_enabled']) {
                $out['recent'] = ApiLogManager::recentForUser($uid, 20);
            }
        }

        $banner = self::checkinBanner();
        $out['checkin_enabled'] = !empty($banner['enabled']);
        $out['checked_today'] = !empty($banner['checked_today']);

        return $out;
    }

    /**
     * 用户侧日志分页（强制当前登录用户；主题禁止直查库）
     *
     * @param array $opts pagesize / before_id / ok / page
     * @return array
     */
    public static function myLogsPaged(array $opts = array())
    {
        $empty = array(
            'list'           => array(),
            'total'          => 0,
            'page'           => 1,
            'pagesize'       => 20,
            'before_id'      => 0,
            'next_before_id' => 0,
            'has_more'       => false,
            'detail_enabled' => false,
        );
        $user = self::current();
        if (!$user || !class_exists('ApiLogManager')) {
            return $empty;
        }
        return ApiLogManager::listForUser((int) $user['id'], $opts);
    }

    /**
     * 热门接口补名称（仅读 api 表 id→name）
     *
     * @param array $stat7
     * @return array
     */
    private static function enrichStat7Top(array $stat7)
    {
        if (empty($stat7['top']) || !is_array($stat7['top'])) {
            return $stat7;
        }
        $ids = array();
        foreach ($stat7['top'] as $row) {
            $id = isset($row['apiid']) ? (int) $row['apiid'] : 0;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $nameMap = array();
        if ($ids !== array() && class_exists('ApiManager') && ApiManager::tableReady()) {
            try {
                $pdo = Database::connect();
                $idList = array_keys($ids);
                $ph = implode(',', array_fill(0, count($idList), '?'));
                $stmt = $pdo->prepare(
                    'SELECT `id`, `name` FROM `' . Database::table('api') . '` WHERE `id` IN (' . $ph . ')'
                );
                $stmt->execute(array_values($idList));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $nameMap[(int) $r['id']] = (string) $r['name'];
                }
            } catch (Exception $e) {
                $nameMap = array();
            }
        }
        foreach ($stat7['top'] as &$row) {
            $aid = isset($row['apiid']) ? (int) $row['apiid'] : 0;
            $row['name'] = isset($nameMap[$aid]) && $nameMap[$aid] !== ''
                ? $nameMap[$aid]
                : ('接口 #' . $aid);
        }
        unset($row);
        return $stat7;
    }
}
