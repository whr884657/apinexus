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
            'role_label'       => '',
            'can_publish_api'  => false,
            'bound_admin'      => false,
            'api_total'        => 0,
            'api_approved'     => 0,
            'api_pending'      => 0,
            'api_rejected'     => 0,
            'api_calls'        => 0,
            'key_total'        => 0,
            'key_calls'        => 0,
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
        $out['role_label'] = isset($user['role_label']) ? (string) $user['role_label'] : '';
        $out['can_publish_api'] = !empty($user['can_publish_api']);
        $out['bound_admin'] = class_exists('AdminUserBinding') && AdminUserBinding::isUserBoundToAdmin($uid);

        if (class_exists('ApiManager') && ApiManager::tableReady() && $out['can_publish_api']) {
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
            foreach ($keys as $k) {
                $out['key_calls'] += isset($k['calls']) ? (int) $k['calls'] : 0;
            }
        }

        $banner = self::checkinBanner();
        $out['checkin_enabled'] = !empty($banner['enabled']);
        $out['checked_today'] = !empty($banner['checked_today']);

        return $out;
    }
}
