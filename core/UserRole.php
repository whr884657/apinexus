<?php
/**
 * 文件：core/UserRole.php
 * 作用：用户角色常量、校验与权限判断
 */

class UserRole
{
    /** 普通用户：可调用平台接口、管理令牌，不可发布接口 */
    const ROLE_USER = 'user';

    /** 开发者：可发布自己的接口 */
    const ROLE_DEVELOPER = 'developer';

    /**
     * @param string $role
     * @return string
     */
    public static function normalize($role)
    {
        $role = strtolower(trim((string) $role));
        if ($role === self::ROLE_DEVELOPER) {
            return self::ROLE_DEVELOPER;
        }
        return self::ROLE_USER;
    }

    /**
     * @param string $role
     * @return string
     */
    public static function label($role)
    {
        return self::normalize($role) === self::ROLE_DEVELOPER ? '开发者' : '普通用户';
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function canPublishApi($role)
    {
        return self::normalize($role) === self::ROLE_DEVELOPER;
    }

    /**
     * 当前登录用户是否可访问 API 管理
     *
     * @return bool
     */
    public static function currentCanPublishApi()
    {
        if (!UserAuth::check()) {
            return false;
        }

        $user = UserAuth::user();
        if (!$user) {
            return false;
        }

        return self::canPublishApi(isset($user['role']) ? $user['role'] : self::ROLE_USER);
    }

    /**
     * @return array<int, string>
     */
    public static function allLabels()
    {
        return array(
            self::ROLE_USER => '普通用户',
            self::ROLE_DEVELOPER => '开发者',
        );
    }
}
