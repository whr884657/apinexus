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
}
